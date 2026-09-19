<?php

declare(strict_types=1);

namespace Bristlecone\Markdown;

/**
 * Gutenberg markup helpers for Markdown blocks (Bristlecone, legacy BITS, Jetpack).
 *
 * Parsing and rewriting here is WordPress-free so unit tests can cover Jetpack
 * compatibility without bootstrapping WordPress.
 */
final class BlockMarkup {

	public const BRISTLECONE = 'bristlecone/markdown';
	public const JETPACK     = 'jetpack/markdown';
	public const LEGACY      = 'bits/markdown';

	/**
	 * @param array<string, mixed> $block
	 */
	public static function markdown_from_block( array $block ): ?string {
		$name  = isset( $block['blockName'] ) && is_string( $block['blockName'] ) ? $block['blockName'] : '';
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		if ( in_array( $name, array( self::BRISTLECONE, self::LEGACY ), true ) ) {
			$markdown = $attrs['markdown'] ?? '';
			return is_string( $markdown ) ? $markdown : null;
		}

		if ( self::JETPACK === $name ) {
			$source = $attrs['source'] ?? '';
			return is_string( $source ) ? $source : null;
		}

		return null;
	}

	/**
	 * Extract Markdown from a document that is a single Markdown block (plus empty freeform).
	 *
	 * @param list<array<string, mixed>> $blocks
	 */
	public static function document_markdown_from_blocks( array $blocks ): ?string {
		$named = array();
		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				if ( trim( (string) ( $block['innerHTML'] ?? '' ) ) !== '' ) {
					return null;
				}
				continue;
			}
			$named[] = $block;
		}

		if ( count( $named ) !== 1 ) {
			return null;
		}

		return self::markdown_from_block( $named[0] );
	}

	/**
	 * @return list<string>
	 */
	public static function extract_jetpack_sources( string $content ): array {
		$sources = array();
		foreach ( self::find_jetpack_markdown_blocks( $content ) as $block ) {
			$sources[] = $block['source'];
		}
		return $sources;
	}

	/**
	 * Rewrite jetpack/markdown comments to bristlecone/markdown. Other blocks are unchanged.
	 *
	 * @param callable(string): string $html_from_source
	 * @return array{content: string, converted: int, errors: int}
	 */
	public static function rewrite_jetpack_markdown_blocks( string $content, callable $html_from_source ): array {
		$found = self::find_jetpack_markdown_blocks( $content );
		if ( $found === array() ) {
			return array(
				'content'   => $content,
				'converted' => 0,
				'errors'    => 0,
			);
		}

		$out       = '';
		$cursor    = 0;
		$converted = 0;
		$errors    = 0;

		foreach ( $found as $block ) {
			$out .= substr( $content, $cursor, $block['start'] - $cursor );
			try {
				$html = $html_from_source( $block['source'] );
				if ( ! is_string( $html ) ) {
					throw new \RuntimeException( 'Converter must return a string.' );
				}
				$out .= self::serialize_bristlecone( $block['source'], $html, $block['attrs'] );
				++$converted;
			} catch ( \Throwable ) {
				$out .= substr( $content, $block['start'], $block['end'] - $block['start'] );
				++$errors;
			}
			$cursor = $block['end'];
		}

		$out .= substr( $content, $cursor );

		return array(
			'content'   => $out,
			'converted' => $converted,
			'errors'    => $errors,
		);
	}

	/**
	 * Map Jetpack block attributes onto a Bristlecone Markdown block attribute set.
	 *
	 * @param array<string, mixed> $jetpack_attrs
	 * @return array<string, mixed>
	 */
	public static function bristlecone_attrs_from_jetpack( array $jetpack_attrs, string $html ): array {
		$source = isset( $jetpack_attrs['source'] ) && is_string( $jetpack_attrs['source'] )
			? $jetpack_attrs['source']
			: '';
		unset( $jetpack_attrs['source'] );
		$jetpack_attrs['markdown'] = $source;
		$jetpack_attrs['html']     = $html;
		return $jetpack_attrs;
	}

	/**
	 * @param array<string, mixed> $extra_attrs
	 */
	public static function serialize_bristlecone( string $markdown, string $html, array $extra_attrs = array() ): string {
		$attrs = self::bristlecone_attrs_from_jetpack(
			array_merge( $extra_attrs, array( 'source' => $markdown ) ),
			$html
		);

		$inner = self::inner_html( $html );

		if ( function_exists( 'serialize_block' ) ) {
			return serialize_block(
				array(
					'blockName'    => self::BRISTLECONE,
					'attrs'        => $attrs,
					'innerBlocks'  => array(),
					'innerHTML'    => $inner,
					'innerContent' => array( $inner ),
				)
			);
		}

		$encoded = self::encode_block_attributes( $attrs );
		return '<!-- wp:' . self::BRISTLECONE . ' ' . $encoded . " -->\n" . $inner . "\n<!-- /wp:" . self::BRISTLECONE . ' -->';
	}

	public static function inner_html( string $html ): string {
		return '<div class="wp-block-bristlecone-markdown bristlecone-markdown">' . $html . '</div>';
	}

	/**
	 * Gutenberg comment-safe JSON (same escapes as serialize_block_attributes()).
	 *
	 * @param array<string, mixed> $attributes
	 */
	public static function encode_block_attributes( array $attributes ): string {
		$encoded = json_encode( $attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) ) {
			return '{}';
		}

		$encoded = preg_replace( '/--/', '\\u002d\\u002d', $encoded ) ?? $encoded;
		$encoded = preg_replace( '/</', '\\u003c', $encoded ) ?? $encoded;
		$encoded = preg_replace( '/>/', '\\u003e', $encoded ) ?? $encoded;
		$encoded = preg_replace( '/&/', '\\u0026', $encoded ) ?? $encoded;
		$encoded = preg_replace( '/\\\\"/', '\\u0022', $encoded ) ?? $encoded;

		return $encoded;
	}

	/**
	 * @return list<array{source: string, attrs: array<string, mixed>, start: int, end: int, inner_html: string}>
	 */
	public static function find_jetpack_markdown_blocks( string $content ): array {
		$found  = array();
		$offset = 0;
		$length = strlen( $content );

		while ( $offset < $length ) {
			$pos = strpos( $content, '<!--', $offset );
			if ( false === $pos ) {
				break;
			}

			$parsed = self::parse_jetpack_opener( $content, $pos );
			if ( null === $parsed ) {
				$offset = $pos + 4;
				continue;
			}

			if ( $parsed['void'] ) {
				$found[] = array(
					'source'     => $parsed['source'],
					'attrs'      => $parsed['attrs'],
					'start'      => $pos,
					'end'        => $parsed['opener_end'],
					'inner_html' => '',
				);
				$offset = $parsed['opener_end'];
				continue;
			}

			$search_from = $parsed['opener_end'];
			$closer      = null;
			$end         = null;
			while ( $search_from < $length ) {
				$next = strpos( $content, '<!--', $search_from );
				if ( false === $next ) {
					break;
				}
				if ( preg_match( '/^<!--\s+\/wp:jetpack\/markdown\s+-->/', substr( $content, $next ), $close_match ) ) {
					$closer = $next;
					$end    = $next + strlen( $close_match[0] );
					break;
				}
				$search_from = $next + 4;
			}

			if ( null === $end || null === $closer ) {
				$offset = $pos + 4;
				continue;
			}

			$found[] = array(
				'source'     => $parsed['source'],
				'attrs'      => $parsed['attrs'],
				'start'      => $pos,
				'end'        => $end,
				'inner_html' => substr( $content, $parsed['opener_end'], $closer - $parsed['opener_end'] ),
			);
			$offset = $end;
		}

		return $found;
	}

	/**
	 * @return array{attrs: array<string, mixed>, source: string, void: bool, opener_end: int}|null
	 */
	private static function parse_jetpack_opener( string $content, int $pos ): ?array {
		$slice = substr( $content, $pos );
		if ( ! preg_match( '/^<!--\s+wp:jetpack\/markdown\b/', $slice, $match ) ) {
			return null;
		}

		$i      = $pos + strlen( $match[0] );
		$length = strlen( $content );

		while ( $i < $length && self::is_ascii_space( $content[ $i ] ) ) {
			++$i;
		}

		$attrs = array();
		if ( $i < $length && '{' === $content[ $i ] ) {
			$json = self::extract_json_object( $content, $i );
			if ( null === $json ) {
				return null;
			}
			$decoded = json_decode( $json['json'], true );
			if ( is_array( $decoded ) ) {
				$attrs = $decoded;
			}
			$i = $json['end'] + 1;
			while ( $i < $length && self::is_ascii_space( $content[ $i ] ) ) {
				++$i;
			}
		}

		$void = false;
		if ( $i < $length && '/' === $content[ $i ] ) {
			$void = true;
			++$i;
			while ( $i < $length && self::is_ascii_space( $content[ $i ] ) ) {
				++$i;
			}
		}

		if ( $i + 2 >= $length || '-->' !== substr( $content, $i, 3 ) ) {
			return null;
		}

		$source = isset( $attrs['source'] ) && is_string( $attrs['source'] ) ? $attrs['source'] : '';

		return array(
			'attrs'      => $attrs,
			'source'     => $source,
			'void'       => $void,
			'opener_end' => $i + 3,
		);
	}

	/**
	 * @return array{json: string, end: int}|null
	 */
	private static function extract_json_object( string $content, int $start ): ?array {
		$length    = strlen( $content );
		$depth     = 0;
		$in_string = false;
		$escape    = false;

		for ( $i = $start; $i < $length; $i++ ) {
			$char = $content[ $i ];
			if ( $in_string ) {
				if ( $escape ) {
					$escape = false;
					continue;
				}
				if ( '\\' === $char ) {
					$escape = true;
					continue;
				}
				if ( '"' === $char ) {
					$in_string = false;
				}
				continue;
			}

			if ( '"' === $char ) {
				$in_string = true;
				continue;
			}
			if ( '{' === $char ) {
				++$depth;
			} elseif ( '}' === $char ) {
				--$depth;
				if ( 0 === $depth ) {
					return array(
						'json' => substr( $content, $start, $i - $start + 1 ),
						'end'  => $i,
					);
				}
			}
		}

		return null;
	}

	private static function is_ascii_space( string $char ): bool {
		return ' ' === $char || "\t" === $char || "\n" === $char || "\r" === $char;
	}
}
