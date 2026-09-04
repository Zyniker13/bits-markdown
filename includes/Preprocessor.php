<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown;

/**
 * Normalizes iA Writer-oriented Markdown before CommonMark parsing.
 */
final class Preprocessor {

	/**
	 * @return array{markdown: string, citations: array<string, string>}
	 */
	public function process( string $markdown ): array {
		$citations = array();
		$lines     = preg_split( '/\R/', $markdown ) ?: array();
		$in_fence  = false;
		$fence     = '';
		$kept      = array();

		foreach ( $lines as $line ) {
			if ( $this->toggle_fence( $line, $in_fence, $fence ) ) {
				$kept[] = $line;
				continue;
			}

			if ( $in_fence ) {
				$kept[] = $line;
				continue;
			}

			if ( preg_match( '/^\[#([^\]]+)\]:\s*(.*)$/', $line, $match ) ) {
				$citations[ trim( $match[1] ) ] = $match[2];
				continue;
			}

			$kept[] = $line;
		}

		$body = implode( "\n", $kept );
		$body = $this->rewrite_citations( $body, $citations );
		$body = $this->inject_heading_references( $body );

		if ( $citations !== array() ) {
			$body .= "\n\n" . $this->bibliography_html( $citations );
		}

		return array(
			'markdown'  => $body,
			'citations' => $citations,
		);
	}

	/**
	 * @param array<string, string> $citations
	 */
	private function rewrite_citations( string $markdown, array $citations ): string {
		if ( $citations === array() ) {
			return $markdown;
		}

		return $this->map_outside_fences(
			$markdown,
			static function ( string $chunk ) use ( $citations ): string {
				return (string) preg_replace_callback(
					'/\[([^\]]*)\]\[#([^\]]+)\]/',
					static function ( array $match ) use ( $citations ): string {
						$key = $match[2];
						if ( ! array_key_exists( $key, $citations ) ) {
							return $match[0];
						}
						$locator = trim( $match[1] );
						$id      = self::citation_id( $key );
						$label   = $locator !== '' ? $locator : $key;
						return '<cite class="bits-markdown-citation"><a href="#' . $id . '">' . self::escape_html( $label ) . '</a></cite>';
					},
					$chunk
				);
			}
		);
	}

	private function inject_heading_references( string $markdown ): string {
		$lines    = preg_split( '/\R/', $markdown ) ?: array();
		$in_fence = false;
		$fence    = '';
		$used     = array();
		$refs     = array();
		$out      = array();

		foreach ( $lines as $line ) {
			if ( $this->toggle_fence( $line, $in_fence, $fence ) ) {
				$out[] = $line;
				continue;
			}

			if ( ! $in_fence && preg_match( '/^(#{1,6})\s+(.+?)\s*$/', $line, $match ) ) {
				$hashes = $match[1];
				$rest   = rtrim( $match[2], " \t#" );
				$label  = null;
				$attr   = '';

				if ( preg_match( '/^(.*)\s+\{([^}]*)\}\s*$/', $rest, $attr_match ) ) {
					$rest = rtrim( $attr_match[1] );
					$attr = '{' . $attr_match[2] . '}';
				}

				if ( preg_match( '/^(.*)\s+\[([^\]]+)\]\s*$/', $rest, $label_match ) ) {
					$rest  = rtrim( $label_match[1] );
					$label = $label_match[2];
				}

				$slug = $this->unique_slug( $rest, $used );
				if ( $attr === '' ) {
					$attr = '{#' . $slug . '}';
				}

				$line           = $hashes . ' ' . $rest . ' ' . $attr;
				$refs[ $rest ]  = $slug;
				if ( is_string( $label ) && $label !== '' ) {
					$refs[ $label ] = $slug;
				}
			}

			$out[] = $line;
		}

		$body = implode( "\n", $out );
		if ( $refs === array() ) {
			return $body;
		}

		$existing = array();
		if ( preg_match_all( '/^\[([^\]]+)\]:/m', $body, $found ) ) {
			foreach ( $found[1] as $label ) {
				$existing[ mb_strtolower( $label, 'UTF-8' ) ] = true;
			}
		}

		$appendix = array();
		foreach ( $refs as $label => $slug ) {
			$key = mb_strtolower( $label, 'UTF-8' );
			if ( isset( $existing[ $key ] ) ) {
				continue;
			}
			$appendix[]       = '[' . $label . ']: #' . $slug;
			$existing[ $key ] = true;
		}

		if ( $appendix === array() ) {
			return $body;
		}

		return $body . "\n\n" . implode( "\n", $appendix ) . "\n";
	}

	/**
	 * @param array<string, string> $citations
	 */
	private function bibliography_html( array $citations ): string {
		$items = array();
		foreach ( $citations as $key => $text ) {
			$id      = self::citation_id( $key );
			$encoded = self::escape_html( $text );
			$items[] = '<li id="' . $id . '">' . $encoded . '</li>';
		}

		return '<div class="bits-markdown-bibliography" role="doc-bibliography"><h2 class="bits-markdown-bibliography-title">References</h2><ol>' . implode( '', $items ) . '</ol></div>';
	}

	/**
	 * @param callable(string): string $callback
	 */
	private function map_outside_fences( string $markdown, callable $callback ): string {
		$parts  = preg_split( '/(^ {0,3}(?:`{3,}|~{3,}).*$)/m', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE );
		$out    = '';
		$inside = false;
		if ( ! is_array( $parts ) ) {
			return $callback( $markdown );
		}
		foreach ( $parts as $part ) {
			if ( preg_match( '/^ {0,3}(?:`{3,}|~{3,})/', $part ) ) {
				$inside = ! $inside;
				$out   .= $part;
				continue;
			}
			$out .= $inside ? $part : $callback( $part );
		}
		return $out;
	}

	private function toggle_fence( string $line, bool &$in_fence, string &$fence ): bool {
		if ( preg_match( '/^ {0,3}(`{3,}|~{3,})/', $line, $match ) ) {
			$marker = $match[1];
			if ( ! $in_fence ) {
				$in_fence = true;
				$fence    = $marker[0];
				return true;
			}
			if ( isset( $marker[0] ) && $marker[0] === $fence ) {
				$in_fence = false;
				$fence    = '';
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string, int> $used
	 */
	private function unique_slug( string $text, array &$used ): string {
		$slug = $this->slug( $text );
		if ( $slug === '' ) {
			$slug = 'section';
		}
		if ( ! isset( $used[ $slug ] ) ) {
			$used[ $slug ] = 1;
			return $slug;
		}
		$suffix = $used[ $slug ];
		while ( isset( $used[ $slug . '-' . $suffix ] ) ) {
			++$suffix;
		}
		$used[ $slug ]                 = $suffix + 1;
		$used[ $slug . '-' . $suffix ] = 1;
		return $slug . '-' . $suffix;
	}

	public function slug( string $text ): string {
		$slug = mb_strtolower( trim( $text ), 'UTF-8' );
		$slug = preg_replace( '/\s+/u', '-', $slug ) ?? $slug;
		$slug = preg_replace( '/[^\p{L}\p{Nd}\p{Nl}\p{M}-]+/u', '', $slug ) ?? $slug;
		return mb_substr( $slug, 0, 80, 'UTF-8' );
	}

	public static function citation_id( string $key ): string {
		$slug = strtolower( preg_replace( '/[^a-zA-Z0-9]+/', '-', $key ) ?? $key );
		return 'bits-ref-' . trim( $slug, '-' );
	}

	public static function escape_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}
