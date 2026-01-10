<?php
/**
 * Main Plugin Class
 */

namespace MLBMediaBlock;

use MLBMediaBlock\API\Client;
use MLBMediaBlock\API\RestController;
use MLBMediaBlock\Blocks\VideoBlock;

/**
 * Main Plugin Class
 */
final class Plugin {
	/**
	 * Singleton instance.
	 */
	private static $instance = null;

	/**
	 * API client instance.
	 */
	private $api_client;

	/**
	 * REST controller instance.
	 */
	private $rest_controller;

	/**
	 * Video block instance.
	 */
	private $video_block;

	/**
	 * Get singleton instance.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Initialize the plugin.
	 */
	private function init(): void {
		$this->load_textdomain();
		$this->initialize_components();
		$this->register_hooks();
	}

	/**
	 * Load plugin text domain.
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'mlb-media-block',
			false,
			dirname( MLB_MEDIA_BLOCK_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize plugin components.
	 */
	private function initialize_components(): void {
		$this->api_client      = new Client();
		$this->rest_controller = new RestController( $this->api_client );
		$this->video_block     = new VideoBlock();
	}

	/**
	 * Register WordPress hooks.
	 */
	private function register_hooks(): void {
		add_action( 'rest_api_init', array( $this->rest_controller, 'register_routes' ) );
		add_action( 'init', array( $this->video_block, 'register' ) );
	}

	/**
	 * Get API client instance.
	 */
	public function get_api_client(): Client {
		return $this->api_client;
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
