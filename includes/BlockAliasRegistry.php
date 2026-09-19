<?php

declare(strict_types=1);

namespace Bristlecone\Markdown;

/**
 * Built-in and custom Markdown block identifiers (WordPress-free).
 *
 * Built-ins: jetpack/markdown (source) and simple-markdown/markdown-block (content).
 * Custom lines: namespace/block-name or namespace/block-name|attribute.
 */
final class BlockAliasRegistry {

	public const JETPACK         = 'jetpack/markdown';
	public const SIMPLE_MARKDOWN = 'simple-markdown/markdown-block';

	public const NAME_PATTERN      = '/^[a-z][a-z0-9-]*\/[a-z0-9-]+$/';
	public const ATTRIBUTE_PATTERN = '/^[A-Za-z_][A-Za-z0-9_-]*$/';

	/**
	 * @return list<string>
	 */
	public static function fallback_attributes(): array {
		return array( 'source', 'content', 'markdown' );
	}

	/**
	 * @return list<string>
	 */
	public static function own_block_names(): array {
		return array(
			BlockMarkup::BRISTLECONE,
			BlockMarkup::LEGACY,
		);
	}

	/**
	 * @return list<BlockAlias>
	 */
	public static function builtins(): array {
		return array(
			new BlockAlias( self::JETPACK, 'source', BlockAlias::ORIGIN_BUILTIN ),
			new BlockAlias( self::SIMPLE_MARKDOWN, 'content', BlockAlias::ORIGIN_BUILTIN ),
		);
	}

	public static function sanitize_block_name( string $name ): ?string {
		$name = strtolower( trim( $name ) );
		if ( $name === '' || ! preg_match( self::NAME_PATTERN, $name ) ) {
			return null;
		}

		if ( in_array( $name, self::own_block_names(), true ) ) {
			return null;
		}

		return $name;
	}

	public static function sanitize_attribute_key( string $key ): ?string {
		$key = trim( $key );
		if ( $key === '' || ! preg_match( self::ATTRIBUTE_PATTERN, $key ) ) {
			return null;
		}

		return $key;
	}

	/**
	 * @return list<BlockAlias>
	 */
	public static function parse_custom_text( string $text ): array {
		$aliases = array();
		$seen    = array();

		foreach ( preg_split( '/\R/', $text ) ?: array() as $line ) {
			$alias = self::parse_custom_line( (string) $line );
			if ( null === $alias || isset( $seen[ $alias->name ] ) ) {
				continue;
			}
			$seen[ $alias->name ] = true;
			$aliases[]            = $alias;
		}

		return $aliases;
	}

	public static function parse_custom_line( string $line ): ?BlockAlias {
		$line = trim( $line );
		if ( $line === '' || str_starts_with( $line, '#' ) ) {
			return null;
		}

		$attribute = null;
		$name      = $line;
		if ( str_contains( $line, '|' ) ) {
			$parts     = explode( '|', $line, 2 );
			$name      = $parts[0];
			$attribute = self::sanitize_attribute_key( $parts[1] );
		}

		$sanitized = self::sanitize_block_name( $name );
		if ( null === $sanitized ) {
			return null;
		}

		return new BlockAlias( $sanitized, $attribute, BlockAlias::ORIGIN_CUSTOM );
	}

	/**
	 * @param list<BlockAlias> $aliases
	 */
	public static function format_lines( array $aliases ): string {
		$lines = array();
		foreach ( $aliases as $alias ) {
			$lines[] = $alias->line();
		}

		return implode( "\n", $lines );
	}

	/**
	 * Identifiers we may register (inserter:false) and soft-migrate / bulk-convert.
	 *
	 * Skips names already registered by another plugin, and jetpack/markdown while
	 * that Jetpack module is still converting content. Does not include scanner hits.
	 *
	 * @param list<BlockAlias>         $custom
	 * @param callable(string): bool $is_registered
	 * @return list<BlockAlias>
	 */
	public static function aliases_to_adopt( array $custom, callable $is_registered, bool $jetpack_markdown_active ): array {
		$out  = array();
		$seen = array();

		foreach ( array_merge( self::builtins(), $custom ) as $alias ) {
			if ( isset( $seen[ $alias->name ] ) ) {
				continue;
			}
			$seen[ $alias->name ] = true;

			if ( in_array( $alias->name, self::own_block_names(), true ) ) {
				continue;
			}

			if ( self::JETPACK === $alias->name && $jetpack_markdown_active ) {
				continue;
			}

			if ( $is_registered( $alias->name ) ) {
				continue;
			}

			$out[] = $alias;
		}

		return $out;
	}

	/**
	 * @param list<BlockAlias> $aliases
	 * @return array<string, BlockAlias>
	 */
	public static function index_by_name( array $aliases ): array {
		$out = array();
		foreach ( $aliases as $alias ) {
			$out[ $alias->name ] = $alias;
		}

		return $out;
	}

	/**
	 * @param list<BlockAlias> $aliases
	 * @return list<string>
	 */
	public static function names( array $aliases ): array {
		return array_values( array_map( static fn( BlockAlias $alias ): string => $alias->name, $aliases ) );
	}
}
