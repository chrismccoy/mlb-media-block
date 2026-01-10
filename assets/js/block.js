/**
 * MLB Media Block
 *
 * A WordPress block that imports MLB video data from URLs
 * and creates native paragraph and video blocks.
 */

(function (wp) {
	'use strict';

	// WordPress dependencies
	const { registerBlockType, createBlock } = wp.blocks;
	const { createElement: el, useState } = wp.element;
	const { useBlockProps } = wp.blockEditor;
	const { Button, TextControl, Spinner } = wp.components;
	const { useDispatch } = wp.data;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;

	const CONFIG = {
		BLOCK_NAME: 'mlb-media-block/video',
		API_NAMESPACE: 'mlb-media-block/v1',
		URL_PATTERN: /(?:https?:\/\/)?(?:\w+\.)?mlb\.com\/video\/([^\/?&#]+)/,
		ICON: 'video-alt3',
		CATEGORY: 'media',
	};

	// Get localized configuration
	const config = window.mlbMediaBlockConfig || {};
	const i18n = config.i18n || {};

	/**
	 * URL Validation Module
	 */
	const URLValidator = {
		/**
		 * Check if URL matches MLB video format.
		 */
		isValid(url) {
			if (!url || typeof url !== 'string') {
				return false;
			}

			const trimmed = url.trim();
			if (!trimmed) {
				return false;
			}

			return CONFIG.URL_PATTERN.test(trimmed);
		},

		/**
		 * Extract video slug from URL.
		 */
		extractSlug(url) {
			const matches = url.match(CONFIG.URL_PATTERN);
			return matches ? matches[1] : null;
		},
	};

	/**
	 * API Client Module
	 */
	const APIClient = {
		/**
		 * Import video data from the MLB API.
		 */
		async importVideo(url) {
			try {
				const response = await apiFetch({
					path: `/${CONFIG.API_NAMESPACE}/import`,
					method: 'POST',
					data: { url: url.trim() },
				});

				return response;
			} catch (error) {
				throw this.handleError(error);
			}
		},

		/**
		 * Validate URL via API.
		 */
		async validateUrl(url) {
			try {
				const response = await apiFetch({
					path: `/${CONFIG.API_NAMESPACE}/validate?url=${encodeURIComponent(
						url
					)}`,
					method: 'GET',
				});

				return response;
			} catch (error) {
				return { valid: false, error: error.message };
			}
		},

		/**
		 * Transform API error into user-friendly message.
		 */
		handleError(error) {
			const errorMap = {
				invalid_url: i18n.invalidUrl,
				fetch_failed: i18n.importError,
				rest_forbidden: i18n.permissionError,
			};

			// Check for specific error codes
			if (error.code && errorMap[error.code]) {
				return new Error(errorMap[error.code]);
			}

			// Check for network errors
			if (error.message && error.message.includes('Failed to fetch')) {
				return new Error(i18n.networkError);
			}

			// Return original or default error
			return new Error(error.message || i18n.importError);
		},
	};

	/**
	 * Block Factory Module
	 */
	const BlockFactory = {
		/**
		 * Create WordPress blocks from video data.
		 */
		createBlocks(videoData) {
			const blocks = [];

			// Add description paragraph if available
			if (this.hasValidDescription(videoData.description)) {
				blocks.push(this.createParagraphBlock(videoData.description));
			}

			// Add video block
			blocks.push(this.createVideoBlock(videoData));

			return blocks;
		},

		/**
		 * Check if description is valid and non-empty.
		 */
		hasValidDescription(description) {
			return description && typeof description === 'string' && description.trim();
		},

		/**
		 * Create a paragraph block.
		 */
		createParagraphBlock(content) {
			return createBlock('core/paragraph', {
				content: content.trim(),
			});
		},

		/**
		 * Create a video block.
		 */
		createVideoBlock(videoData) {
			return createBlock('core/video', {
				src: videoData.videoUrl,
				poster: videoData.posterUrl,
			});
		},
	};

	/**
	 * URL Input Field Component
	 */
	function URLInput({ value, onChange, onKeyDown, disabled, error }) {
		return el(TextControl, {
			label: i18n.urlLabel || __('MLB Video URL', 'mlb-media-block'),
			value: value,
			onChange: onChange,
			onKeyDown: onKeyDown,
			placeholder:
				i18n.urlPlaceholder || 'https://www.mlb.com/video/...',
			disabled: disabled,
			type: 'url',
			className: 'mlb-media-block-url-control',
			help: error
				? null
				: __('Paste an MLB.com video URL', 'mlb-media-block'),
			__nextHasNoMarginBottom: true,
			autoComplete: 'off',
		});
	}

	/**
	 * Import Button Component
	 */
	function ImportButton({ onClick, disabled, isImporting }) {
		return el(
			Button,
			{
				variant: 'primary',
				onClick: onClick,
				disabled: disabled,
				isBusy: isImporting,
				className: 'mlb-media-block-import-button',
				style: { marginTop: '12px' },
			},
			isImporting
				? el(
						'span',
						{ style: { display: 'flex', alignItems: 'center', gap: '8px' } },
						el(Spinner),
						i18n.importing || __('Importing...', 'mlb-media-block')
				  )
				: i18n.importButton || __('Import Video', 'mlb-media-block')
		);
	}

	/**
	 * Error Notice Component
	 */
	function ErrorNotice({ message }) {
		if (!message) {
			return null;
		}

		return el(
			'div',
			{
				className: 'mlb-media-block-notice mlb-media-block-error',
				role: 'alert',
				'aria-live': 'polite',
			},
			el('strong', null, __('Error:', 'mlb-media-block') + ' '),
			message
		);
	}

	/**
	 * Block Placeholder Component
	 */
	function BlockPlaceholder({ children }) {
		return el(
			'div',
			{
				className: 'mlb-media-block-placeholder',
				role: 'region',
				'aria-label': i18n.blockTitle || __('MLB Video', 'mlb-media-block'),
			},
			el(
				'h3',
				{ className: 'mlb-media-block-title' },
				i18n.blockTitle || __('MLB Video Import', 'mlb-media-block')
			),
			el(
				'p',
				{ className: 'mlb-media-block-description' },
				i18n.helpText ||
					__('Enter an MLB.com video URL to import and embed the video.', 'mlb-media-block')
			),
			children
		);
	}

	/**
	 * Edit Component
	 *
	 * Renders a URL input field and import button. On successful import,
	 * updates the post title and replaces itself with native WordPress blocks.
	 */
	function EditBlock({ clientId }) {
		// Component state
		const [url, setUrl] = useState('');
		const [isImporting, setIsImporting] = useState(false);
		const [error, setError] = useState('');

		// WordPress data hooks
		const blockProps = useBlockProps({
			className: 'wp-block-mlb-media-block-video',
		});

		const { editPost } = useDispatch('core/editor');
		const { replaceBlocks } = useDispatch('core/block-editor');

		/**
		 * Handle URL input change.
		 */
		function handleUrlChange(value) {
			setUrl(value);

			// Clear error when user starts typing
			if (error) {
				setError('');
			}
		}

		/**
		 * Handle keyboard events in URL input.
		 */
		function handleKeyDown(event) {
			if (event.key === 'Enter' && !isImporting && url.trim()) {
				event.preventDefault();
				handleImport();
			}
		}

		/**
		 * Validate URL before import.
		 */
		function validateUrl() {
			const trimmedUrl = url.trim();

			if (!trimmedUrl) {
				setError(
					i18n.invalidUrl ||
						__('Please enter a valid MLB.com video URL.', 'mlb-media-block')
				);
				return false;
			}

			if (!URLValidator.isValid(trimmedUrl)) {
				setError(
					i18n.invalidUrl ||
						__('Please enter a valid MLB.com video URL.', 'mlb-media-block')
				);
				return false;
			}

			return true;
		}

		/**
		 * Handle import button click.
		 *
		 * Steps
		 * 1. Validates URL
		 * 2. Fetches video data from API
		 * 3. Updates post title
		 * 4. Replaces block with native blocks
		 */
		async function handleImport() {
			// Validate URL format
			if (!validateUrl()) {
				return;
			}

			// Clear previous error and start import
			setError('');
			setIsImporting(true);

			try {
				// Fetch video data from REST API
				const videoData = await APIClient.importVideo(url.trim());

				// Update post title if available
				if (videoData.title) {
					editPost({ title: videoData.title });
				}

				// Create native WordPress blocks
				const newBlocks = BlockFactory.createBlocks(videoData);

				// Replace this block with the new blocks
				replaceBlocks(clientId, newBlocks);

				// Success - no need to reset state as block is replaced
			} catch (err) {
				// Display error to user
				setError(err.message || i18n.importError);
				setIsImporting(false);
			}
		}

		// Render the block UI
		return el(
			'div',
			blockProps,
			el(
				BlockPlaceholder,
				null,
				el(URLInput, {
					value: url,
					onChange: handleUrlChange,
					onKeyDown: handleKeyDown,
					disabled: isImporting,
					error: error,
				}),
				el(ImportButton, {
					onClick: handleImport,
					disabled: isImporting || !url.trim(),
					isImporting: isImporting,
				}),
				el(ErrorNotice, { message: error })
			)
		);
	}

	/**
	 * Save Component
	 *
	 * This block doesn't save content directly. Instead, it transforms
	 * itself into native WordPress blocks during the import process.
	 */
	function SaveBlock() {
		return null;
	}

	/**
	 * Register the MLB Video block with WordPress.
	 */
	registerBlockType(CONFIG.BLOCK_NAME, {
		// Block metadata
		apiVersion: 3,
		title: i18n.blockTitle || __('MLB Video', 'mlb-media-block'),
		description:
			i18n.blockDescription ||
			__('Import and embed MLB.com videos.', 'mlb-media-block'),
		icon: CONFIG.ICON,
		category: CONFIG.CATEGORY,

		// Block attributes
		attributes: {},

		// Block support configuration
		supports: {
			html: false,
			multiple: false,
			reusable: false,
		},

		// Search keywords for block inserter
		keywords: ['mlb', 'video', 'baseball', 'import', 'embed', 'sports'],

		// Editor component
		edit: EditBlock,

		// Save component
		save: SaveBlock,
	});
})(window.wp);
