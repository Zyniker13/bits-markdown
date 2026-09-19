<?php

declare(strict_types=1);

namespace Bristlecone\Markdown;

/**
 * Gutenberg markup helpers for Markdown blocks (Bristlecone, legacy BITS, aliases).
 *
 * Parsing and rewriting here is WordPress-free so unit tests can cover Jetpack
 * and other identifiers without bootstrapping WordPress.
 */
final class BlockMarkup {

	public const BRISTLECONE = 'bristlecone/markdown';
	public const JETPACK     = 'jetpack/markdown';
	public const LEGACY      = 'bits/markdown';

	/**
	 * Read Markdown from block attributes. Built-ins use a fixed key; custom
	 * aliases may omit a key and fall back to source, then content, then markdown.
	 *
	 * @param array<string, mixed> $block
	 * @param list<BlockAlias>     $extra_aliases
	 */
	public static function markdown_from_block( array $block, array $extra_aliases = array() ): ?string {
		$name  = isset( $block['blockName'] ) && is_string( $block['blockName'] ) ? $block['blockName'] : '';
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		if ( in_array( $name, array( self::BRISTLECONE, self::LEGACY ), true ) ) {
			$markdown = $attrs['markdown'] ?? '';
			return is_string( $markdown ) ? $markdown : null;
		}

		$aliases = array_merge( BlockAliasRegistry::builtins(), $extra_aliases );
		foreach ( $aliases as $alias ) {
			if ( $alias->name !== $name ) {
				continue;
			}

			return self::source_from_attrs( $attrs, $alias->attribute );
		}

		return null;
	}

	/**
	 * Prefer an explicit attribute; if omitted, try source, then content, then markdown.
	 *
	 * @param array<string, mixed> $attrs
	 */
	public static function source_from_attrs( array $attrs, ?string $preferred = null ): ?string {
		if ( is_string( $preferred ) && $preferred !== '' ) {
			if ( array_key_exists( $preferred, $attrs ) ) {
				return is_string( $attrs[ $preferred ] ) ? $attrs[ $preferred ] : null;
			}

			return '';
		}

		foreach ( BlockAliasRegistry::fallback_attributes() as $key ) {
			if ( isset( $attrs[ $key ] ) && is_string( $attrs[ $key ] ) ) {
				return $attrs[ $key ];
			}
		}

		return '';
	}

	/**
	 * Extract Markdown from a document that is a single Markdown block (plus empty freeform).
	 *
	 * @param list<array<string, mixed>> $blocks
	 * @param list<BlockAlias>           $extra_aliases
	 */
	public static function document_markdown_from_blocks( array $blocks, array $extra_aliases = array() ): ?string {
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

		return self::markdown_from_block( $named[0], $extra_aliases );
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
	 * @return list<string>
	 */
	public static function extract_named_sources( string $content, string $block_name, ?string $attribute = null ): array {
		$sources = array();
		foreach ( self::find_named_blocks( $content, $block_name, $attribute ) as $block ) {
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
		return self::rewrite_named_blocks( $content, self::JETPACK, 'source', $html_from_source );
	}

	/**
	 * @param list<BlockAlias>         $aliases
	 * @param callable(string): string $html_from_source
	 * @return array{content: string, converted: int, errors: int}
	 */
	public static function rewrite_aliased_blocks( string $content, array $aliases, callable $html_from_source ): array {
		$converted = 0;
		$errors    = 0;

		foreach ( $aliases as $alias ) {
			$result     = self::rewrite_named_blocks( $content, $alias->name, $alias->attribute, $html_from_source );
			$content    = $result['content'];
			$converted += $result['converted'];
			$errors    += $result['errors'];
		}

		return array(
			'content'   => $content,
			'converted' => $converted,
			'errors'    => $errors,
		);
	}

	/**
	 * @param callable(string): string $html_from_source
	 * @return array{content: string, converted: int, errors: int}
	 */
	public static function rewrite_named_blocks( string $content, string $block_name, ?string $attribute, callable $html_from_source ): array {
		$found = self::find_named_blocks( $content, $block_name, $attribute );
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
				$out .= self::serialize_bristlecone( $block['source'], $html, self::attrs_without_source( $block['attrs'], $attribute ) );
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
	 * Map a foreign Markdown block’s attributes onto a Bristlecone attribute set.
	 *
	 * @param array<string, mixed> $foreign_attrs
	 * @return array<string, mixed>
	 */
	public static function bristlecone_attrs_from_alias( array $foreign_attrs, string $html, ?string $preferred = null ): array {
		$source = self::source_from_attrs( $foreign_attrs, $preferred );
		$source = is_string( $source ) ? $source : '';
		$attrs  = self::attrs_without_source( $foreign_attrs, $preferred );
		$attrs['markdown'] = $source;
		$attrs['html']     = $html;
		return $attrs;
	}

	/**
	 * Map Jetpack block attributes onto a Bristlecone Markdown block attribute set.
	 *
	 * @param array<string, mixed> $jetpack_attrs
	 * @return array<string, mixed>
	 */
	public static function bristlecone_attrs_from_jetpack( array $jetpack_attrs, string $html ): array {
		return self::bristlecone_attrs_from_alias( $jetpack_attrs, $html, 'source' );
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
		return self::find_named_blocks( $content, self::JETPACK, 'source' );
	}

	/**
	 * @return list<array{source: string, attrs: array<string, mixed>, start: int, end: int, inner_html: string, name: string}>
	 */
	public static function find_named_blocks( string $content, string $block_name, ?string $attribute = null ): array {
		$found  = array();
		$offset = 0;
		$length = strlen( $content );

		while ( $offset < $length ) {
			$pos = strpos( $content, '<!--', $offset );
			if ( false === $pos ) {
				break;
			}

			$parsed = self::parse_block_opener( $content, $pos, $block_name );
			if ( null === $parsed ) {
				$offset = $pos + 4;
				continue;
			}

			$source = self::source_from_attrs( $parsed['attrs'], $attribute );
			$source = is_string( $source ) ? $source : '';

			if ( $parsed['void'] ) {
				$found[] = array(
					'name'       => $parsed['name'],
					'source'     => $source,
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
			$close_raw   = $parsed['raw'];
			while ( $search_from < $length ) {
				$next = strpos( $content, '<!--', $search_from );
				if ( false === $next ) {
					break;
				}
				if ( preg_match( '/^<!--\s+\/wp:' . preg_quote( $close_raw, '/' ) . '\s+-->/', substr( $content, $next ), $close_match ) ) {
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
				'name'       => $parsed['name'],
				'source'     => $source,
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
	 * Every Gutenberg opener in $content, including nested blocks.
	 *
	 * @return list<array{name: string, attrs: array<string, mixed>, start: int, end: int, inner_html: string}>
	 */
	public static function find_all_blocks( string $content ): array {
		$found  = array();
		$offset = 0;
		$length = strlen( $content );

		while ( $offset < $length ) {
			$pos = strpos( $content, '<!--', $offset );
			if ( false === $pos ) {
				break;
			}

			$parsed = self::parse_block_opener( $content, $pos, null );
			if ( null === $parsed ) {
				$offset = $pos + 4;
				continue;
			}

			if ( $parsed['void'] ) {
				$found[] = array(
					'name'       => $parsed['name'],
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
				if ( preg_match( '/^<!--\s+\/wp:' . preg_quote( $parsed['raw'], '/' ) . '\s+-->/', substr( $content, $next ), $close_match ) ) {
					$closer = $next;
					$end    = $next + strlen( $close_match[0] );
					break;
				}
				$search_from = $next + 4;
			}

			$found[] = array(
				'name'       => $parsed['name'],
				'attrs'      => $parsed['attrs'],
				'start'      => $pos,
				'end'        => $end ?? $parsed['opener_end'],
				'inner_html' => ( null !== $closer )
					? substr( $content, $parsed['opener_end'], $closer - $parsed['opener_end'] )
					: '',
			);

			// Continue inside the block so nested Markdown blocks are visible to the scanner.
			$offset = $parsed['opener_end'];
		}

		return $found;
	}

	/**
	 * @param array<string, mixed> $attrs
	 * @return array<string, mixed>
	 */
	private static function attrs_without_source( array $attrs, ?string $preferred ): array {
		if ( is_string( $preferred ) && $preferred !== '' ) {
			unset( $attrs[ $preferred ] );
			return $attrs;
		}

		unset( $attrs['source'], $attrs['content'] );
		return $attrs;
	}

	/**
	 * @return array{name: string, raw: string, attrs: array<string, mixed>, void: bool, opener_end: int}|null
	 */
	private static function parse_block_opener( string $content, int $pos, ?string $only_name ): ?array {
		$slice = substr( $content, $pos );
		if ( ! preg_match( '/^<!--\s+wp:((?:[a-z0-9-]+\/)?[a-z0-9-]+)\b/', $slice, $match ) ) {
			return null;
		}

		$raw  = $match[1];
		$name = str_contains( $raw, '/' ) ? $raw : 'core/' . $raw;

		if ( null !== $only_name && $name !== $only_name && $raw !== $only_name ) {
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

		return array(
			'name'       => $name,
			'raw'        => $raw,
			'attrs'      => $attrs,
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
