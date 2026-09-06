<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Tests\Support;

/**
 * Compare production parser HTML to CommonMark spec fragments.
 */
final class HtmlNormalizer {

	public static function spec( string $html ): string {
		return trim( str_replace( array( "\r\n", "\r" ), "\n", $html ) );
	}

	/**
	 * Strip heading permalinks, heading ids, and the fenced-code wrapper class
	 * so production HTML can be compared to CommonMark examples.
	 */
	public static function production( string $html ): string {
		$html = preg_replace(
			'/<a\b[^>]*class="bits-markdown-heading-permalink"[^>]*>.*?<\/a>/s',
			'',
			$html
		) ?? $html;
		$html = preg_replace( '/<(h[1-6]) id="[^"]*"/', '<$1', $html ) ?? $html;
		$html = preg_replace( '/<pre class="bits-markdown-code">/', '<pre>', $html ) ?? $html;

		return self::spec( $html );
	}
}
