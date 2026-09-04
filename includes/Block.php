<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown;

/**
 * Gutenberg Markdown block.
 */
final class Block {

	private static ?self $instance = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	public function register_block(): void {
		$block_js = BITS_MARKDOWN_DIR . 'assets/js/block.js';

		wp_register_script(
			'bits-markdown-block',
			BITS_MARKDOWN_URL . 'assets/js/block.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-i18n',
				'wp-api-fetch',
				'wp-data',
			),
			file_exists( $block_js ) ? (string) filemtime( $block_js ) : BITS_MARKDOWN_VERSION,
			true
		);

		wp_set_script_translations( 'bits-markdown-block', 'bits-markdown' );

		wp_localize_script(
			'bits-markdown-block',
			'bitsMarkdownBlock',
			array(
				'previewUrl' => esc_url_raw( rest_url( 'bits-markdown/v1/preview' ) ),
			)
		);

		register_block_type(
			BITS_MARKDOWN_DIR . 'blocks/markdown',
			array(
				'editor_script'   => 'bits-markdown-block',
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	public function render( array $attributes, string $content, $block = null ): string {
		$markdown = isset( $attributes['markdown'] ) && is_string( $attributes['markdown'] )
			? $attributes['markdown']
			: '';

		if ( $markdown === '' ) {
			return $content;
		}

		$post_id = get_the_ID();
		$result  = Storage::instance()->convert(
			$markdown,
			array(
				'id' => $post_id ? (string) $post_id : 'block',
			)
		);

		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => 'wp-block-bits-markdown bits-markdown',
			)
		);

		return '<div ' . $wrapper . '>' . $result->html . '</div>';
	}
}
