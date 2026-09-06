<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown;

/**
 * Comment Markdown conversion.
 */
final class Comments {

	public const META_KEY = '_bits_markdown';

	private static ?self $instance = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function register(): void {
		add_filter( 'pre_comment_content', array( $this, 'filter_pre_comment_content' ), 9 );
		add_action( 'comment_post', array( $this, 'on_comment_post' ) );
		add_filter( 'comment_text', array( $this, 'maybe_disable_wpautop' ), 20, 2 );
	}

	public function filter_pre_comment_content( string $content ): string {
		$hash   = 'c-' . substr( md5( $content ), 0, 8 );
		$result = Storage::instance()->convert(
			$content,
			array(
				'id'      => $hash,
				'comment' => true,
			)
		);

		$GLOBALS['bits_markdown_last_comment'] = true;

		return $result->html;
	}

	public function on_comment_post( int $comment_id ): void {
		if ( ! empty( $GLOBALS['bits_markdown_last_comment'] ) ) {
			update_comment_meta( $comment_id, self::META_KEY, 1 );
			unset( $GLOBALS['bits_markdown_last_comment'] );
		}
	}

	/**
	 * @param string           $text
	 * @param \WP_Comment|null $comment
	 */
	public function maybe_disable_wpautop( string $text, $comment = null ): string {
		$id = 0;
		if ( $comment instanceof \WP_Comment ) {
			$id = (int) $comment->comment_ID;
		}

		if ( $id && get_comment_meta( $id, self::META_KEY, true ) ) {
			remove_filter( 'comment_text', 'wpautop', 30 );
			add_filter( 'comment_text', array( $this, 'reenable_wpautop' ), 31 );
		}

		return $text;
	}

	public function reenable_wpautop( string $text ): string {
		add_filter( 'comment_text', 'wpautop', 30 );
		remove_filter( 'comment_text', array( $this, 'reenable_wpautop' ), 31 );
		return $text;
	}
}
