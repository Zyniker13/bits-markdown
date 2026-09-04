<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown;

/**
 * Front-end and editor assets. KaTeX loads only when math is present.
 */
final class Assets {

	private static ?self $instance = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor' ) );
	}

	public function enqueue_frontend(): void {
		if ( is_admin() ) {
			return;
		}

		wp_register_style(
			'bits-markdown',
			BITS_MARKDOWN_URL . 'assets/css/frontend.css',
			array(),
			BITS_MARKDOWN_VERSION
		);

		wp_register_style(
			'bits-markdown-highlight',
			BITS_MARKDOWN_URL . 'assets/css/highlight-github.css',
			array( 'bits-markdown' ),
			BITS_MARKDOWN_VERSION
		);

		wp_register_style(
			'bits-markdown-katex',
			BITS_MARKDOWN_URL . 'assets/vendor/katex/katex.min.css',
			array(),
			BITS_MARKDOWN_VERSION
		);

		wp_register_script(
			'bits-markdown-katex',
			BITS_MARKDOWN_URL . 'assets/vendor/katex/katex.min.js',
			array(),
			BITS_MARKDOWN_VERSION,
			true
		);

		wp_register_script(
			'bits-markdown-math',
			BITS_MARKDOWN_URL . 'assets/js/math.js',
			array( 'bits-markdown-katex' ),
			BITS_MARKDOWN_VERSION,
			true
		);

		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$content = $post->post_content . ' ' . (string) $post->post_content_filtered;
		$needs   = $this->needs_assets( $content );

		if ( $needs['css'] ) {
			wp_enqueue_style( 'bits-markdown' );
		}

		if ( $needs['highlight'] && Settings::instance()->highlighting_enabled() ) {
			wp_enqueue_style( 'bits-markdown-highlight' );
		}

		if ( $needs['math'] && Settings::instance()->math_enabled() ) {
			wp_enqueue_style( 'bits-markdown-katex' );
			wp_enqueue_script( 'bits-markdown-math' );
		}
	}

	public function enqueue_editor(): void {
		wp_enqueue_style(
			'bits-markdown-editor',
			BITS_MARKDOWN_URL . 'assets/css/editor.css',
			array(),
			BITS_MARKDOWN_VERSION
		);

		if ( Settings::instance()->highlighting_enabled() ) {
			wp_enqueue_style(
				'bits-markdown-highlight',
				BITS_MARKDOWN_URL . 'assets/css/highlight-github.css',
				array(),
				BITS_MARKDOWN_VERSION
			);
		}

		if ( Settings::instance()->math_enabled() ) {
			wp_enqueue_style(
				'bits-markdown-katex',
				BITS_MARKDOWN_URL . 'assets/vendor/katex/katex.min.css',
				array(),
				BITS_MARKDOWN_VERSION
			);
			wp_enqueue_script(
				'bits-markdown-katex',
				BITS_MARKDOWN_URL . 'assets/vendor/katex/katex.min.js',
				array(),
				BITS_MARKDOWN_VERSION,
				true
			);
			wp_enqueue_script(
				'bits-markdown-math',
				BITS_MARKDOWN_URL . 'assets/js/math.js',
				array( 'bits-markdown-katex' ),
				BITS_MARKDOWN_VERSION,
				true
			);
		}

		wp_enqueue_style( 'bits-markdown', BITS_MARKDOWN_URL . 'assets/css/frontend.css', array(), BITS_MARKDOWN_VERSION );
	}

	/**
	 * @return array{css: bool, highlight: bool, math: bool}
	 */
	private function needs_assets( string $content ): array {
		$math      = str_contains( $content, 'bits-markdown-math' ) || str_contains( $content, '$$' ) || (bool) preg_match( '/\$[^$]+\$/', $content );
		$highlight = str_contains( $content, 'hljs' ) || str_contains( $content, '```' ) || str_contains( $content, '<pre' );
		$css       = $math || $highlight || str_contains( $content, 'bits-markdown' ) || Storage::instance()->is_markdown_post( (int) get_the_ID() ) || has_block( 'bits/markdown' );

		return array(
			'css'       => $css,
			'highlight' => $highlight || has_block( 'bits/markdown' ) || str_contains( $content, '<pre' ),
			'math'      => $math,
		);
	}
}
