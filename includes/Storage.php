<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown;

/**
 * Jetpack-compatible Markdown storage: HTML in post_content, source in post_content_filtered.
 */
final class Storage {

	public const META_KEY          = '_bits_markdown';
	public const META_WPCOM_KEY    = '_wpcom_markdown';
	public const META_FRONT_MATTER = '_bits_markdown_front_matter';

	private static ?self $instance = null;

	/** @var array<int, true> */
	private array $pending = array();

	/** @var array<int, true> */
	private array $rewrite_ids = array();

	private bool $skip_conversion = false;

	/** @var array<string, mixed>|null */
	private ?array $last_front_matter = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function register(): void {
		add_filter( 'wp_insert_post_data', array( $this, 'filter_insert_post_data' ), 10, 2 );
		add_action( 'wp_insert_post', array( $this, 'on_insert_post' ), 10, 2 );
		add_filter( 'edit_post_content', array( $this, 'filter_edit_post_content' ), 10, 2 );
		add_filter( '_wp_post_revision_fields', array( $this, 'revision_fields' ) );
		add_action( 'wp_restore_post_revision', array( $this, 'restore_revision' ), 10, 2 );
		add_filter( 'the_content', array( $this, 'maybe_disable_wpautop' ), 8 );
		add_filter( 'the_content', array( $this, 'wrap_document_content' ), 9 );
		add_filter( 'wp_kses_allowed_html', array( $this, 'allow_html' ), 10, 2 );
	}

	public function is_markdown_post( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}
		return (bool) get_post_meta( $post_id, self::META_KEY, true )
			|| (bool) get_post_meta( $post_id, self::META_WPCOM_KEY, true );
	}

	/**
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $postarr
	 * @return array<string, mixed>
	 */
	public function filter_insert_post_data( array $data, array $postarr ): array {
		if ( $this->skip_conversion ) {
			return $data;
		}

		$post_id   = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		$post_type = (string) ( $data['post_type'] ?? 'post' );

		if ( 'revision' === $post_type ) {
			return $data;
		}

		if ( isset( $_POST['_inline_edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $data;
		}

		$content = (string) ( $data['post_content'] ?? '' );
		if ( $content === '' ) {
			return $data;
		}

		$unslashed = wp_unslash( $content );

		if ( has_blocks( $unslashed ) ) {
			$extracted = $this->extract_document_markdown( $unslashed );
			if ( is_string( $extracted ) && $extracted !== '' ) {
				$data['post_content_filtered'] = wp_slash( $extracted );
				$this->pending[ $post_id ]     = true;
				$this->transform_and_map( $extracted, $post_id );
			}
			return $data;
		}

		if ( ! Settings::instance()->is_post_type_enabled( $post_type ) ) {
			return $data;
		}

		$data['post_content_filtered'] = $content;
		$result                        = $this->transform_and_map( $unslashed, $post_id );
		$data['post_content']          = wp_slash( $result->html );
		$this->pending[ $post_id ]     = true;
		if ( 0 === $post_id ) {
			$this->rewrite_ids[0] = true;
		}

		return $data;
	}

	public function on_insert_post( int $post_id, $post ): void {
		if ( ! isset( $this->pending[ $post_id ] ) && ! isset( $this->pending[0] ) ) {
			return;
		}

		$rewrite = isset( $this->rewrite_ids[ $post_id ] ) || isset( $this->rewrite_ids[0] );
		unset( $this->pending[ $post_id ], $this->pending[0], $this->rewrite_ids[ $post_id ], $this->rewrite_ids[0] );

		update_post_meta( $post_id, self::META_KEY, 1 );

		if ( is_array( $this->last_front_matter ) ) {
			FrontMatterMapper::instance()->apply( $post_id, $this->last_front_matter );
			update_post_meta( $post_id, self::META_FRONT_MATTER, $this->last_front_matter );
			$this->last_front_matter = null;
		}

		if ( $rewrite && $post_id > 0 ) {
			$stored = get_post( $post_id );
			$source = $stored instanceof \WP_Post ? (string) $stored->post_content_filtered : '';
			if ( $source !== '' && ! has_blocks( (string) $stored->post_content ) ) {
				$result                = $this->convert( $source, array( 'id' => (string) $post_id ) );
				$this->skip_conversion = true;
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => $result->html,
					)
				);
				$this->skip_conversion = false;
			}
		}
	}

	public function filter_edit_post_content( string $content, int $post_id ): string {
		if ( ! $this->is_markdown_post( $post_id ) ) {
			return $content;
		}

		$post = get_post( $post_id );
		if ( $post && is_string( $post->post_content_filtered ) && $post->post_content_filtered !== '' ) {
			return $post->post_content_filtered;
		}

		return $content;
	}

	/**
	 * @param array<string, string> $fields
	 * @return array<string, string>
	 */
	public function revision_fields( array $fields ): array {
		$fields['post_content_filtered'] = __( 'Markdown content', 'bits-markdown' );
		return $fields;
	}

	public function restore_revision( int $post_id, int $revision_id ): void {
		if ( ! $this->is_markdown_post( $revision_id ) && ! $this->is_markdown_post( $post_id ) ) {
			return;
		}

		$revision = get_post( $revision_id );
		if ( ! $revision || $revision->post_content_filtered === '' ) {
			return;
		}

		add_filter( 'wp_revisions_to_keep', '__return_false', 99 );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $revision->post_content_filtered,
			)
		);
		remove_filter( 'wp_revisions_to_keep', '__return_false', 99 );
	}

	public function maybe_disable_wpautop( string $content ): string {
		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return $content;
		}

		if ( has_blocks( $post->post_content ) ) {
			return $content;
		}

		if ( ! $this->is_markdown_post( (int) $post->ID ) ) {
			return $content;
		}

		remove_filter( 'the_content', 'wpautop' );
		add_filter(
			'the_content',
			static function ( string $value ): string {
				add_filter( 'the_content', 'wpautop' );
				return $value;
			},
			12
		);

		return $content;
	}

	public function wrap_document_content( string $content ): string {
		$post = get_post();
		if ( ! $post instanceof \WP_Post || $content === '' ) {
			return $content;
		}

		if ( has_blocks( $post->post_content ) ) {
			return $content;
		}

		if ( ! $this->is_markdown_post( (int) $post->ID ) ) {
			return $content;
		}

		if ( str_starts_with( ltrim( $content ), '<div class="bits-markdown">' ) ) {
			return $content;
		}

		return '<div class="bits-markdown">' . $content . '</div>';
	}

	/**
	 * @param array<string, mixed> $tags
	 * @param string|array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	public function allow_html( array $tags, $context ): array {
		$sanitizer = new Sanitizer();

		if ( 'post' === $context ) {
			return $sanitizer->expand_allowed_html( $tags );
		}

		if ( in_array( $context, array( 'pre_comment_content', 'comment' ), true ) ) {
			return $sanitizer->comment_allowed_html();
		}

		return $tags;
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public function convert( string $markdown, array $context = array() ): ConversionResult {
		$settings = Settings::instance();
		$result   = ( new Parser() )->convert(
			$markdown,
			array_merge(
				array(
					'highlight' => $settings->highlighting_enabled(),
					'math'      => $settings->math_enabled(),
				),
				$context
			)
		);

		$sanitizer = new Sanitizer();
		$html      = ( $context['comment'] ?? false )
			? $sanitizer->sanitize_comment( $result->html )
			: $sanitizer->sanitize_post( $result->html );

		return new ConversionResult( $html, $result->front_matter, $result->has_math, $result->has_code );
	}

	private function transform_and_map( string $markdown, int $post_id ): ConversionResult {
		$result                  = $this->convert( $markdown, array( 'id' => $post_id > 0 ? (string) $post_id : 'p' ) );
		$this->last_front_matter = $result->front_matter;
		return $result;
	}

	private function extract_document_markdown( string $content ): ?string {
		if ( ! function_exists( 'parse_blocks' ) ) {
			return null;
		}

		$named = array();
		foreach ( parse_blocks( $content ) as $block ) {
			if ( empty( $block['blockName'] ) ) {
				if ( trim( (string) ( $block['innerHTML'] ?? '' ) ) !== '' ) {
					return null;
				}
				continue;
			}
			$named[] = $block;
		}

		if ( count( $named ) === 1 && 'bits/markdown' === $named[0]['blockName'] ) {
			$markdown = $named[0]['attrs']['markdown'] ?? '';
			return is_string( $markdown ) ? $markdown : null;
		}

		return null;
	}
}
