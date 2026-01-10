<?php
/**
 * MLB API Client
 */

namespace MLBMediaBlock\API;

/**
 * Handles communication with the MLB API.
 */
class Client {
	/**
	 * API base URL.
	 */
	private const API_BASE_URL = 'https://www.mlb.com/data-service/en/videos/';

	/**
	 * CDN base URL.
	 */
	private const CDN_BASE_URL = 'https://img.mlbstatic.com/mlb-images/image/upload/mlb/';

	/**
	 * URL regex pattern.
	 */
	private const URL_PATTERN = '/(?:https?:\/\/)?(?:\w+\.)?mlb\.com\/video\/([^\/?&#]+)/';

	/**
	 * Default timeout in seconds.
	 */
	private const DEFAULT_TIMEOUT = 15;

	/**
	 * Default cache duration in seconds.
	 */
	private const DEFAULT_CACHE_DURATION = 3600;

	/**
	 * Extract video slug from URL.
	 */
	public function extract_video_slug( string $url ): ?string {
		if ( empty( $url ) ) {
			return null;
		}

		if ( ! preg_match( self::URL_PATTERN, $url, $matches ) ) {
			return null;
		}

		return $matches[1] ?? null;
	}

	/**
	 * Validate MLB video URL.
	 */
	public function is_valid_url( string $url ): bool {
		return null !== $this->extract_video_slug( $url );
	}

	/**
	 * Fetch video data with caching.
	 */
	public function fetch_video_data( string $video_slug ): ?array {
		// Check cache first.
		$cache_key = $this->get_cache_key( $video_slug );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// Fetch from API.
		$raw_data = $this->fetch_from_api( $video_slug );

		if ( null === $raw_data ) {
			return null;
		}

		// Parse and validate.
		$parsed_data = $this->parse_video_data( $raw_data );

		if ( null === $parsed_data ) {
			return null;
		}

		// Cache the result.
		set_transient( $cache_key, $parsed_data, $this->get_cache_duration() );

		return $parsed_data;
	}

	/**
	 * Fetch raw data from MLB API.
	 */
	private function fetch_from_api( string $video_slug ): ?array {
		$url = self::API_BASE_URL . rawurlencode( $video_slug );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => $this->get_timeout(),
				'user-agent'  => $this->get_user_agent(),
				'httpversion' => '1.1',
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error(
				'API request failed',
				array(
					'error' => $response->get_error_message(),
					'code'  => $response->get_error_code(),
					'slug'  => $video_slug,
				)
			);
			return null;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status_code ) {
			$this->log_error(
				'API returned non-200 status',
				array(
					'status' => $status_code,
					'slug'   => $video_slug,
				)
			);
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			$this->log_error(
				'JSON decode failed',
				array(
					'error' => json_last_error_msg(),
					'slug'  => $video_slug,
				)
			);
			return null;
		}

		return $data;
	}

	/**
	 * Parse video data from API response.
	 */
	private function parse_video_data( array $data ): ?array {
		// Validate required fields.
		if ( empty( $data['feeds'][0]['playbacks'][0]['url'] ) ) {
			$this->log_error(
				'Missing required fields in API response',
				array( 'keys' => array_keys( $data ) )
			);
			return null;
		}

		$video_url  = $data['feeds'][0]['playbacks'][0]['url'] ?? '';
		$poster_url = $this->build_poster_url(
			$data['feeds'][0]['image']['cuts'][2]['src'] ?? ''
		);

		return array(
			'title'       => $this->clean_title( $data['title'] ?? '' ),
			'description' => $this->clean_description( $data['description'] ?? '' ),
			'videoUrl'    => esc_url_raw( $video_url ),
			'posterUrl'   => $poster_url,
			'duration'    => absint( $data['duration'] ?? 0 ),
			'date'        => sanitize_text_field( $data['date'] ?? '' ),
		);
	}

	/**
	 * Clean video title by removing parenthetical content.
	 */
	private function clean_title( string $title ): string {
		$cleaned = preg_replace( '/\([^)]+\)/', '', $title );
		return trim( wp_strip_all_tags( $cleaned ) );
	}

	/**
	 * Clean video description by removing hashtags.
	 */
	private function clean_description( string $description ): string {
		$cleaned = preg_replace( '/(#[\w]+)/', '', $description );
		return trim( wp_strip_all_tags( $cleaned ) );
	}

	/**
	 * Build poster image URL from CDN path.
	 */
	private function build_poster_url( string $image_path ): string {
		if ( empty( $image_path ) ) {
			return '';
		}

		$basename = basename( $image_path );
		return esc_url( sprintf( '%s%s.jpg', self::CDN_BASE_URL, $basename ) );
	}

	/**
	 * Get cache key for video slug.
	 */
	private function get_cache_key( string $video_slug ): string {
		return 'mlb_video_' . md5( $video_slug );
	}

	/**
	 * Get request timeout.
	 */
	private function get_timeout(): int {
		/**
		 * Filter the API request timeout.
		 */
		return apply_filters( 'mlb_media_block_api_timeout', self::DEFAULT_TIMEOUT );
	}

	/**
	 * Get cache duration.
	 */
	private function get_cache_duration(): int {
		/**
		 * Filter the cache duration.
		 */
		return apply_filters( 'mlb_media_block_cache_duration', self::DEFAULT_CACHE_DURATION );
	}

	/**
	 * Get user agent string.
	 */
	private function get_user_agent(): string {
		return sprintf(
			'WordPress/%s; %s; MLB-Media-Block/%s',
			get_bloginfo( 'version' ),
			home_url(),
			MLB_MEDIA_BLOCK_VERSION
		);
	}

	/**
	 * Log error message.
	 */
	private function log_error( string $message, array $context = array() ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log(
				sprintf(
					'[MLB Media Block] %s: %s',
					$message,
					wp_json_encode( $context, JSON_UNESCAPED_SLASHES )
				)
			);
		}

		/**
		 * Fires when an API error occurs.
		 */
		do_action( 'mlb_media_block_api_error', $message, $context );
	}

	/**
	 * Clear cache for a specific video.
	 */
	public function clear_cache( string $video_slug ): bool {
		return delete_transient( $this->get_cache_key( $video_slug ) );
	}

	/**
	 * Clear all cached videos.
	 */
	public function clear_all_cache(): int {
		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_mlb_video_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_mlb_video_' ) . '%'
			)
		);

		return absint( $deleted );
	}
}
