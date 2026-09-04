<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown;

/**
 * HTML sanitization for posts (safe subset) and comments (strict).
 */
final class Sanitizer {

	public function sanitize_post( string $html ): string {
		if ( function_exists( 'wp_kses' ) ) {
			return wp_kses( $html, $this->expand_allowed_html( $this->base_post_allowed_html() ) );
		}

		return $html;
	}

	public function sanitize_comment( string $html ): string {
		if ( function_exists( 'wp_kses' ) ) {
			return wp_kses( $html, $this->comment_allowed_html() );
		}

		return strip_tags(
			$html,
			'<a><p><br><em><strong><code><pre><blockquote><ul><ol><li><h1><h2><h3><h4><h5><h6><hr><del><mark><sup><sub><table><thead><tbody><tr><th><td><cite>'
		);
	}

	/**
	 * Expand an existing KSES allowlist with Markdown output tags.
	 *
	 * This must not call wp_kses_allowed_html(), which would recurse through Storage::allow_html().
	 *
	 * @param array<string, mixed> $allowed
	 * @return array<string, mixed>
	 */
	public function expand_allowed_html( array $allowed ): array {
		$extra_tags = array( 'mark', 'cite', 'colgroup', 'col', 'section', 'aside', 'figure', 'figcaption' );
		foreach ( $extra_tags as $tag ) {
			if ( ! isset( $allowed[ $tag ] ) ) {
				$allowed[ $tag ] = array();
			}
		}

		$global = array(
			'id'              => true,
			'class'           => true,
			'role'            => true,
			'aria-hidden'     => true,
			'aria-label'      => true,
			'aria-labelledby' => true,
			'data-*'          => true,
		);

		foreach ( array_keys( $allowed ) as $tag ) {
			$existing        = is_array( $allowed[ $tag ] ) ? $allowed[ $tag ] : array();
			$allowed[ $tag ] = array_merge( $global, $existing );
		}

		$allowed['a']['href']            = true;
		$allowed['a']['rel']             = true;
		$allowed['a']['target']          = true;
		$allowed['a']['title']           = true;
		$allowed['img']['src']           = true;
		$allowed['img']['alt']           = true;
		$allowed['img']['width']         = true;
		$allowed['img']['height']        = true;
		$allowed['th']['align']          = true;
		$allowed['td']['align']          = true;
		$allowed['th']['colspan']        = true;
		$allowed['td']['colspan']        = true;
		$allowed['hr']['class']          = true;
		$allowed['div']['data-display']  = true;
		$allowed['span']['data-display'] = true;

		return $allowed;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function post_allowed_html(): array {
		return $this->expand_allowed_html( $this->base_post_allowed_html() );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function base_post_allowed_html(): array {
		if ( ! function_exists( 'wp_kses_allowed_html' ) ) {
			return array();
		}

		// Avoid recursion when this runs inside the wp_kses_allowed_html filter.
		$storage = Storage::instance();
		remove_filter( 'wp_kses_allowed_html', array( $storage, 'allow_html' ), 10 );
		$allowed = wp_kses_allowed_html( 'post' );
		add_filter( 'wp_kses_allowed_html', array( $storage, 'allow_html' ), 10, 2 );

		return is_array( $allowed ) ? $allowed : array();
	}

	/**
	 * @return array<string, array<string, bool>>
	 */
	public function comment_allowed_html(): array {
		$attr = array(
			'id'    => true,
			'class' => true,
			'role'  => true,
		);

		return array(
			'a'          => array_merge( $attr, array( 'href' => true, 'rel' => true, 'title' => true ) ),
			'p'          => $attr,
			'br'         => array(),
			'em'         => $attr,
			'strong'     => $attr,
			'code'       => $attr,
			'pre'        => $attr,
			'blockquote' => $attr,
			'ul'         => $attr,
			'ol'         => $attr,
			'li'         => $attr,
			'h1'         => $attr,
			'h2'         => $attr,
			'h3'         => $attr,
			'h4'         => $attr,
			'h5'         => $attr,
			'h6'         => $attr,
			'hr'         => $attr,
			'del'        => $attr,
			'mark'       => $attr,
			'sup'        => $attr,
			'sub'        => $attr,
			'table'      => $attr,
			'thead'      => $attr,
			'tbody'      => $attr,
			'tr'         => $attr,
			'th'         => array_merge( $attr, array( 'align' => true, 'colspan' => true ) ),
			'td'         => array_merge( $attr, array( 'align' => true, 'colspan' => true ) ),
			'cite'       => $attr,
		);
	}
}
