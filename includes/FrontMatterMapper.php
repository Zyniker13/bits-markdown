<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown;

/**
 * Apply YAML front matter to WordPress post fields.
 */
final class FrontMatterMapper {

	private static ?self $instance = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * @param array<string, mixed> $front_matter
	 */
	public function apply( int $post_id, array $front_matter ): void {
		if ( $post_id <= 0 || $front_matter === array() ) {
			return;
		}

		$map = array();
		foreach ( $front_matter as $key => $value ) {
			$map[ strtolower( (string) $key ) ] = $value;
		}

		$update = array( 'ID' => $post_id );
		$post   = get_post( $post_id );

		if ( ! empty( $map['title'] ) && is_scalar( $map['title'] ) ) {
			$title             = (string) $map['title'];
			$auto_draft_titles = array( '', 'Auto Draft' );
			$auto_draft_status = get_post_status_object( 'auto-draft' );
			if ( $auto_draft_status && is_string( $auto_draft_status->label ) && $auto_draft_status->label !== '' ) {
				$auto_draft_titles[] = $auto_draft_status->label;
			}
			if ( $post && in_array( $post->post_title, $auto_draft_titles, true ) ) {
				$update['post_title'] = $title;
			}
		}

		if ( ! empty( $map['excerpt'] ) && is_scalar( $map['excerpt'] ) && $post && $post->post_excerpt === '' ) {
			$update['post_excerpt'] = (string) $map['excerpt'];
		} elseif ( ! empty( $map['description'] ) && is_scalar( $map['description'] ) && $post && $post->post_excerpt === '' ) {
			$update['post_excerpt'] = (string) $map['description'];
		}

		if ( ! empty( $map['slug'] ) && is_scalar( $map['slug'] ) ) {
			$update['post_name'] = sanitize_title( (string) $map['slug'] );
		} elseif ( ! empty( $update['post_title'] ) && $post && in_array( $post->post_name, array( '', 'auto-draft' ), true ) ) {
			$update['post_name'] = sanitize_title( $update['post_title'] );
		}

		if ( count( $update ) > 1 ) {
			remove_filter( 'wp_insert_post_data', array( Storage::instance(), 'filter_insert_post_data' ), 10 );
			wp_update_post( $update );
			add_filter( 'wp_insert_post_data', array( Storage::instance(), 'filter_insert_post_data' ), 10, 2 );
		}

		$tags = $map['tags'] ?? $map['tag'] ?? null;
		if ( null !== $tags ) {
			wp_set_post_tags( $post_id, $this->to_list( $tags ), false );
		}

		$categories = $map['categories'] ?? $map['category'] ?? null;
		if ( null !== $categories ) {
			$ids = array();
			foreach ( $this->to_list( $categories ) as $name ) {
				$term = get_term_by( 'name', $name, 'category' );
				if ( $term ) {
					$ids[] = (int) $term->term_id;
				} else {
					$created = wp_insert_term( $name, 'category' );
					if ( ! is_wp_error( $created ) ) {
						$ids[] = (int) $created['term_id'];
					}
				}
			}
			if ( $ids !== array() ) {
				wp_set_post_categories( $post_id, $ids, false );
			}
		}
	}

	/**
	 * @param mixed $value
	 * @return list<string>
	 */
	private function to_list( $value ): array {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $item ) {
				if ( is_scalar( $item ) && (string) $item !== '' ) {
					$out[] = (string) $item;
				}
			}
			return $out;
		}

		if ( is_string( $value ) && $value !== '' ) {
			return array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
		}

		return array();
	}
}
