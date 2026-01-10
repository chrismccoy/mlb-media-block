<?php
/**
 * REST API Controller
 */

namespace MLBMediaBlock\API;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API endpoints for MLB video import.
 */
class RestController extends WP_REST_Controller {
	/**
	 * API namespace.
	 */
	private const NAMESPACE = 'mlb-media-block/v1';

	/**
	 * API client instance.
	 */
	private $client;

	/**
	 * Constructor.
	 */
	public function __construct( Client $client ) {
		$this->client    = $client;
		$this->namespace = self::NAMESPACE;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		// Import video endpoint.
		register_rest_route(
			$this->namespace,
			'/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_video' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_import_args(),
				),
				'schema' => array( $this, 'get_import_schema' ),
			)
		);

		// Validate URL endpoint.
		register_rest_route(
			$this->namespace,
			'/validate',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'validate_url' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_validate_args(),
				),
			)
		);

		// Clear cache endpoint.
		register_rest_route(
			$this->namespace,
			'/cache',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_cache' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'slug' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Import video data from MLB URL.
	 */
	public function import_video( WP_REST_Request $request ) {
		$url = $request->get_param( 'url' );

		// Extract video slug.
		$slug = $this->client->extract_video_slug( $url );

		if ( null === $slug ) {
			return new WP_Error(
				'invalid_url',
				__( 'Invalid MLB video URL format. Please enter a valid MLB.com video URL.', 'mlb-media-block' ),
				array( 'status' => 400 )
			);
		}

		// Fetch video data.
		$data = $this->client->fetch_video_data( $slug );

		if ( null === $data ) {
			return new WP_Error(
				'fetch_failed',
				__( 'Failed to fetch video data from MLB API. The video may not exist or the API may be unavailable.', 'mlb-media-block' ),
				array( 'status' => 502 )
			);
		}

		return new WP_REST_Response(
			$data,
			200,
			array( 'X-MLB-Video-Slug' => $slug )
		);
	}

	/**
	 * Validate MLB video URL.
	 */
	public function validate_url( WP_REST_Request $request ) {
		$url  = $request->get_param( 'url' );
		$slug = $this->client->extract_video_slug( $url );

		return new WP_REST_Response(
			array(
				'valid' => null !== $slug,
				'slug'  => $slug,
			),
			200
		);
	}

	/**
	 * Clear video cache.
	 */
	public function clear_cache( WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );

		if ( $slug ) {
			$result = $this->client->clear_cache( $slug );
			$count  = $result ? 1 : 0;
		} else {
			$count = $this->client->clear_all_cache();
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'cleared' => $count,
				'message' => sprintf(
					_n(
						'Cleared %d cache entry.',
						'Cleared %d cache entries.',
						$count,
						'mlb-media-block'
					),
					$count
				),
			),
			200
		);
	}

	/**
	 * Check user permission for importing.
	 */
	public function check_permission() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to import videos.', 'mlb-media-block' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Check admin permission.
	 */
	public function check_admin_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to manage cache.', 'mlb-media-block' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get import endpoint arguments schema.
	 */
	private function get_import_args(): array {
		return array(
			'url' => array(
				'description'       => __( 'MLB video URL to import.', 'mlb-media-block' ),
				'type'              => 'string',
				'format'            => 'uri',
				'required'          => true,
				'sanitize_callback' => 'esc_url_raw',
				'validate_callback' => function ( $param ) {
					if ( ! filter_var( $param, FILTER_VALIDATE_URL ) ) {
						return new WP_Error(
							'invalid_url',
							__( 'The URL provided is not valid.', 'mlb-media-block' )
						);
					}
					return true;
				},
			),
		);
	}

	/**
	 * Get validate endpoint arguments schema.
	 */
	private function get_validate_args(): array {
		return array(
			'url' => array(
				'description'       => __( 'MLB video URL to validate.', 'mlb-media-block' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'esc_url_raw',
			),
		);
	}

	/**
	 * Get import endpoint schema.
	 */
	public function get_import_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'mlb-video',
			'type'       => 'object',
			'properties' => array(
				'title'       => array(
					'description' => __( 'Video title.', 'mlb-media-block' ),
					'type'        => 'string',
				),
				'description' => array(
					'description' => __( 'Video description.', 'mlb-media-block' ),
					'type'        => 'string',
				),
				'videoUrl'    => array(
					'description' => __( 'Video playback URL.', 'mlb-media-block' ),
					'type'        => 'string',
					'format'      => 'uri',
				),
				'posterUrl'   => array(
					'description' => __( 'Video poster image URL.', 'mlb-media-block' ),
					'type'        => 'string',
					'format'      => 'uri',
				),
				'duration'    => array(
					'description' => __( 'Video duration in seconds.', 'mlb-media-block' ),
					'type'        => 'integer',
				),
				'date'        => array(
					'description' => __( 'Video publication date.', 'mlb-media-block' ),
					'type'        => 'string',
				),
			),
		);
	}
}
