<?php

declare(strict_types=1);

namespace Bristlecone\Markdown;

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
		add_action( 'wp_loaded', array( $this, 'maybe_apply_default_templates' ) );
	}

	public function register_block(): void {
		$block_js = BRISTLECONE_MARKDOWN_DIR . 'assets/js/block.js';

		wp_register_script(
			'bristlecone-markdown-block',
			BRISTLECONE_MARKDOWN_URL . 'assets/js/block.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-i18n',
				'wp-api-fetch',
				'wp-data',
				'wp-dom-ready',
			),
			file_exists( $block_js ) ? (string) filemtime( $block_js ) : BRISTLECONE_MARKDOWN_VERSION,
			true
		);

		wp_set_script_translations( 'bristlecone-markdown-block', 'bristlecone-markdown' );

		wp_localize_script(
			'bristlecone-markdown-block',
			'bristleconeMarkdownBlock',
			self::editor_script_config()
		);

		register_block_type(
			BRISTLECONE_MARKDOWN_DIR . 'blocks/markdown',
			array(
				'editor_script'   => 'bristlecone-markdown-block',
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Data passed to the block editor script. JetpackMarkdownBlock merges alias flags on top.
	 *
	 * @return array<string, mixed>
	 */
	public static function editor_script_config(): array {
		$settings = Settings::instance();
		$preview  = '';
		if ( function_exists( 'rest_url' ) && function_exists( 'esc_url_raw' ) ) {
			$preview = esc_url_raw( rest_url( 'bristlecone-markdown/v1/preview' ) );
		}

		return array_merge(
			array(
				'previewUrl' => $preview,
			),
			DefaultMarkdownEditor::script_flags(
				$settings->default_to_markdown_enabled(),
				$settings->enabled_post_types()
			)
		);
	}

	/**
	 * When the setting is on, start new block-editor posts with an empty Markdown block.
	 * Existing content and Classic Editor / document-mode paths are left alone.
	 */
	public function maybe_apply_default_templates(): void {
		$settings = Settings::instance();
		if ( ! $settings->default_to_markdown_enabled() ) {
			return;
		}

		foreach ( $settings->enabled_post_types() as $post_type ) {
			$object = function_exists( 'get_post_type_object' ) ? get_post_type_object( $post_type ) : null;
			if ( ! is_object( $object ) ) {
				continue;
			}

			$apply = DefaultMarkdownEditor::should_apply(
				true,
				true,
				$this->post_type_uses_block_editor( $post_type )
			);
			DefaultMarkdownEditor::assign_unlocked_template( $object, $apply );
		}
	}

	private function post_type_uses_block_editor( string $post_type ): bool {
		if ( function_exists( 'post_type_supports' ) && ! post_type_supports( $post_type, 'editor' ) ) {
			return false;
		}

		if ( function_exists( 'use_block_editor_for_post_type' ) ) {
			return (bool) use_block_editor_for_post_type( $post_type );
		}

		return true;
	}

	public static function serialize_source( string $markdown, string $html = '' ): string {
		$inner = '<div class="wp-block-bristlecone-markdown bristlecone-markdown">' . $html . '</div>';

		return serialize_block(
			array(
				'blockName'    => 'bristlecone/markdown',
				'attrs'        => array(
					'markdown' => $markdown,
					'html'     => $html,
				),
				'innerBlocks'  => array(),
				'innerHTML'    => $inner,
				'innerContent' => array( $inner ),
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

		$sanitizer = new Sanitizer();

		if ( $markdown === '' ) {
			return $sanitizer->sanitize_post( $content );
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
				'class' => 'wp-block-bristlecone-markdown bristlecone-markdown',
			)
		);

		return '<div ' . $wrapper . '>' . $sanitizer->sanitize_post( $result->html ) . '</div>';
	}
}
