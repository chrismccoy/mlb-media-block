<?php
/**
 * Plugin Name:     MLB Media Block
 * Plugin URI:      https://github.com/chrismccoy/mlb-media-block
 * Description:     A WordPress block to import MLB Videos by URL and embed them in your posts.
 * Version:         1.0.0
 * Author:          Chris McCoy
 * Author URI:      https://github.com/chrismccoy
 * Text Domain:     mlb-media-block
 * Domain Path:     /languages
 * @package MLBMediaBlock
 */

namespace MLBMediaBlock;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Constants
 */
define( 'MLB_MEDIA_BLOCK_VERSION', '1.1.0' );
define( 'MLB_MEDIA_BLOCK_FILE', __FILE__ );
define( 'MLB_MEDIA_BLOCK_PATH', plugin_dir_path( __FILE__ ) );
define( 'MLB_MEDIA_BLOCK_URL', plugin_dir_url( __FILE__ ) );
define( 'MLB_MEDIA_BLOCK_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load Plugin Files
 */
require_once MLB_MEDIA_BLOCK_PATH . 'includes/API/Client.php';
require_once MLB_MEDIA_BLOCK_PATH . 'includes/API/RestController.php';
require_once MLB_MEDIA_BLOCK_PATH . 'includes/Blocks/VideoBlock.php';
require_once MLB_MEDIA_BLOCK_PATH . 'includes/Plugin.php';

/**
 * Initialize the plugin.
 */
add_action( 'plugins_loaded', array( Plugin::class, 'instance' ) );
