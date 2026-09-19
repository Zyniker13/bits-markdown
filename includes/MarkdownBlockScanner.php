<?php

declare(strict_types=1);

namespace Bristlecone\Markdown;

/**
 * Read-only scan of Gutenberg markup for unregistered *markdown* block names.
 *
 * Never rewrites content and never registers aliases. Tools review→convert is separate.
 */
final class MarkdownBlockScanner {

	/**
	 * @return list<string>
	 */
	public static function exclude_defaults(): array {
		return array_merge(
			BlockAliasRegistry::own_block_names(),
			BlockAliasRegistry::names( BlockAliasRegistry::builtins() )
		);
	}

	public static function name_looks_like_markdown( string $name ): bool {
		return (bool) preg_match( '/markdown/i', $name );
	}

	public static function is_false_friend( string $name ): bool {
		return (bool) preg_match( '/markdown[-_]?comment/i', $name );
	}

	/**
	 * @param array<string, int> $attribute_counts
	 */
	public static function suggested_attribute( array $attribute_counts ): string {
		foreach ( BlockAliasRegistry::fallback_attributes() as $key ) {
			if ( ( $attribute_counts[ $key ] ?? 0 ) > 0 ) {
				return $key;
			}
		}

		if ( $attribute_counts === array() ) {
			return '';
		}

		arsort( $attribute_counts );
		$first = array_key_first( $attribute_counts );

		return is_string( $first ) ? $first : '';
	}

	/**
	 * @param list<string> $exclude_names
	 * @return array<string, array{count: int, posts: int, attributes: array<string, int>, false_friend: bool, suggested_attribute: string}>
	 */
	public static function summarize( string $content, array $exclude_names = array() ): array {
		$exclude = array();
		foreach ( $exclude_names as $name ) {
			$exclude[ strtolower( $name ) ] = true;
		}

		$out = array();
		foreach ( BlockMarkup::find_all_blocks( $content ) as $block ) {
			$name = $block['name'];
			if ( ! self::name_looks_like_markdown( $name ) ) {
				continue;
			}
			if ( isset( $exclude[ strtolower( $name ) ] ) ) {
				continue;
			}

			if ( ! isset( $out[ $name ] ) ) {
				$out[ $name ] = array(
					'count'               => 0,
					'posts'               => 1,
					'attributes'          => array(),
					'false_friend'        => self::is_false_friend( $name ),
					'suggested_attribute' => '',
				);
			}

			++$out[ $name ]['count'];
			foreach ( $block['attrs'] as $key => $value ) {
				if ( ! is_string( $key ) || $key === '' ) {
					continue;
				}
				$out[ $name ]['attributes'][ $key ] = ( $out[ $name ]['attributes'][ $key ] ?? 0 ) + 1;
			}
		}

		foreach ( $out as $name => $row ) {
			$out[ $name ]['suggested_attribute'] = self::suggested_attribute( $row['attributes'] );
		}

		return $out;
	}

	/**
	 * Merge two summarize() maps (used when scanning posts in batches).
	 *
	 * @param array<string, array{count: int, posts: int, attributes: array<string, int>, false_friend: bool, suggested_attribute: string}> $into
	 * @param array<string, array{count: int, posts: int, attributes: array<string, int>, false_friend: bool, suggested_attribute: string}> $add
	 * @return array<string, array{count: int, posts: int, attributes: array<string, int>, false_friend: bool, suggested_attribute: string}>
	 */
	public static function merge_summaries( array $into, array $add, bool $counts_as_post = true ): array {
		foreach ( $add as $name => $row ) {
			if ( ! isset( $into[ $name ] ) ) {
				$into[ $name ] = $row;
				if ( $counts_as_post && (int) $into[ $name ]['posts'] < 1 ) {
					$into[ $name ]['posts'] = 1;
				}
				continue;
			}

			$into[ $name ]['count'] += (int) $row['count'];
			if ( $counts_as_post ) {
				++$into[ $name ]['posts'];
			} else {
				$into[ $name ]['posts'] += (int) ( $row['posts'] ?? 0 );
			}
			foreach ( $row['attributes'] as $key => $count ) {
				$into[ $name ]['attributes'][ $key ] = ( $into[ $name ]['attributes'][ $key ] ?? 0 ) + (int) $count;
			}
			$into[ $name ]['false_friend']        = $into[ $name ]['false_friend'] || ! empty( $row['false_friend'] );
			$into[ $name ]['suggested_attribute'] = self::suggested_attribute( $into[ $name ]['attributes'] );
		}

		return $into;
	}
}
