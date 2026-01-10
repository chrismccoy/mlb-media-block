<?php
/**
 * Video Block Registration
 */

namespace MLBMediaBlock\Blocks;

/**
 * Registers and manages the MLB Video block.
 */
class VideoBlock {
	/**
	 * Block name.
	 */
	private const BLOCK_NAME = 'mlb-media-block/video';

	/**
	 * Script handle.
	 */
	private const SCRIPT_HANDLE = 'mlb-media-block-editor';

	/**
	 * Register the block type.
	 */
	public function register(): void {
		register_block_type(
			self::BLOCK_NAME,
			array(
				'api_version'   => 3,
				'editor_script' => self::SCRIPT_HANDLE,
				'attributes'    => array(),
				'supports'      => array(
					'html'     => false,
					'multiple' => false,
					'reusable' => false,
				),
			)
		);

		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Enqueue block editor assets.
	 */
	public function enqueue_editor_assets(): void {
		$asset_file = $this->get_asset_metadata();

		// Enqueue the block script
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			MLB_MEDIA_BLOCK_URL . 'assets/js/block.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		// Localize script with configuration
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'mlbMediaBlockConfig',
			array(
				'namespace'    => 'mlb-media-block/v1',
				'restUrl'      => rest_url(),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'i18n'         => $this->get_translations(),
				'urlPattern'   => '(?:https?:\/\/)?(?:\w+\.)?mlb\.com\/video\/([^\/?&#]+)',
				'version'      => MLB_MEDIA_BLOCK_VERSION,
			)
		);

		// Add inline styles
		wp_add_inline_style(
			'wp-block-editor',
			$this->get_editor_styles()
		);
	}

	/**
	 * Get asset metadata (dependencies and version).
	 */
	private function get_asset_metadata(): array {
		return array(
			'dependencies' => array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-data',
				'wp-i18n',
				'wp-api-fetch',
			),
			'version'      => MLB_MEDIA_BLOCK_VERSION,
		);
	}

	/**
	 * Get translations for the block.
	 */
	private function get_translations(): array {
		return array(
			'blockTitle'       => __( 'MLB Video', 'mlb-media-block' ),
			'blockDescription' => __( 'Import and embed MLB.com videos.', 'mlb-media-block' ),
			'urlLabel'         => __( 'MLB Video URL', 'mlb-media-block' ),
			'urlPlaceholder'   => __( 'https://www.mlb.com/video/...', 'mlb-media-block' ),
			'importButton'     => __( 'Import Video', 'mlb-media-block' ),
			'importing'        => __( 'Importing...', 'mlb-media-block' ),
			'invalidUrl'       => __( 'Please enter a valid MLB.com video URL.', 'mlb-media-block' ),
			'importError'      => __( 'Failed to import video. Please try again.', 'mlb-media-block' ),
			'permissionError'  => __( 'You do not have permission to import videos.', 'mlb-media-block' ),
			'networkError'     => __( 'Network error. Please check your connection.', 'mlb-media-block' ),
			'helpText'         => __( 'Enter an MLB.com video URL to import and embed the video.', 'mlb-media-block' ),
		);
	}

	/**
	 * Get inline editor styles.
	 */
	private function get_editor_styles(): string {
		return '
			/* MLB Media Block Styles */
			.wp-block-mlb-media-block-video {
				margin: 0;
			}

			/* Placeholder Container */
			.mlb-media-block-placeholder {
				padding: 32px 24px;
				background: #f8f9fa;
				border: 2px dashed #ddd;
				border-radius: 8px;
				text-align: center;
				transition: border-color 0.2s ease;
			}

			.mlb-media-block-placeholder:hover {
				border-color: #2271b1;
			}

			/* Block Title */
			.mlb-media-block-title {
				margin-top: 0;
				margin-bottom: 8px;
				font-size: 20px;
				font-weight: 600;
				color: #1e1e1e;
				line-height: 1.4;
			}

			/* Block Description */
			.mlb-media-block-description {
				margin-bottom: 20px;
				font-size: 14px;
				color: #757575;
				line-height: 1.5;
			}

			/* URL Input Control */
			.mlb-media-block-url-control {
				margin-bottom: 0;
				text-align: left;
			}

			.mlb-media-block-url-control input[type="url"] {
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			}

			/* Import Button */
			.mlb-media-block-import-button {
				min-width: 140px;
				height: 36px;
			}

			.mlb-media-block-import-button.is-busy {
				opacity: 0.8;
				cursor: wait;
			}

			/* Error Notice */
			.mlb-media-block-notice {
				margin-top: 16px;
				padding: 12px 16px;
				border-left: 4px solid #d63638;
				background: #fcf0f1;
				color: #1e1e1e;
				text-align: left;
				border-radius: 2px;
				font-size: 14px;
				line-height: 1.5;
			}

			.mlb-media-block-notice strong {
				font-weight: 600;
				color: #d63638;
			}

			/* Success State */
			.mlb-media-block-notice.is-success {
				border-left-color: #00a32a;
				background: #f0f6fc;
			}

			.mlb-media-block-notice.is-success strong {
				color: #00a32a;
			}

			/* Loading Spinner Alignment */
			.mlb-media-block-import-button .components-spinner {
				margin: 0;
			}

			/* Responsive Design */
			@media (max-width: 600px) {
				.mlb-media-block-placeholder {
					padding: 24px 16px;
				}

				.mlb-media-block-title {
					font-size: 18px;
				}

				.mlb-media-block-description {
					font-size: 13px;
				}

				.mlb-media-block-import-button {
					width: 100%;
					justify-content: center;
				}
			}

			/* Focus States for Accessibility */
			.mlb-media-block-url-control input[type="url"]:focus {
				border-color: #2271b1;
				box-shadow: 0 0 0 1px #2271b1;
				outline: 2px solid transparent;
			}

			.mlb-media-block-import-button:focus {
				box-shadow:
					0 0 0 1px #fff,
					0 0 0 3px #2271b1;
				outline: 2px solid transparent;
			}

			/* Dark Mode Support */
			@media (prefers-color-scheme: dark) {
				.mlb-media-block-placeholder {
					background: #1e1e1e;
					border-color: #3c3c3c;
				}

				.mlb-media-block-placeholder:hover {
					border-color: #4f94d4;
				}

				.mlb-media-block-title {
					color: #f0f0f0;
				}

				.mlb-media-block-description {
					color: #a0a0a0;
				}

				.mlb-media-block-notice {
					background: #2a1f1f;
					color: #f0f0f0;
				}
			}

			/* High Contrast Mode Support */
			@media (prefers-contrast: high) {
				.mlb-media-block-placeholder {
					border-width: 3px;
				}

				.mlb-media-block-notice {
					border-left-width: 6px;
				}
			}

			/* Reduced Motion Support */
			@media (prefers-reduced-motion: reduce) {
				.mlb-media-block-placeholder {
					transition: none;
				}

				.components-spinner {
					animation: none;
				}
			}

			/* Print Styles */
			@media print {
				.mlb-media-block-placeholder {
					display: none;
				}
			}
		';
	}
}
