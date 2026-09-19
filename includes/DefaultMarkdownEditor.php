<?php

declare(strict_types=1);

namespace Bristlecone\Markdown;

/**
 * Opt-in Markdown-first block editor: new-post template and default block name.
 *
 * WordPress-free helpers so PHPUnit can cover the gates without bootstrapping WP.
 */
final class DefaultMarkdownEditor {

	public const BLOCK_NAME = 'bristlecone/markdown';

	/**
	 * Unlocked block template: a single empty Markdown block.
	 *
	 * @return list<array{0: string, 1: array{markdown: string}}>
	 */
	public static function template(): array {
		return array(
			array(
				self::BLOCK_NAME,
				array(
					'markdown' => '',
				),
			),
		);
	}

	public static function should_apply( bool $setting_enabled, bool $post_type_enabled, bool $uses_block_editor ): bool {
		return $setting_enabled && $post_type_enabled && $uses_block_editor;
	}

	/**
	 * Prefer Markdown as Gutenberg's default block (Enter / appender) for this post type.
	 *
	 * @param list<string> $enabled_post_types
	 */
	public static function should_set_default_block_name( bool $setting_enabled, string $post_type, array $enabled_post_types ): bool {
		return $setting_enabled
			&& $post_type !== ''
			&& in_array( $post_type, $enabled_post_types, true );
	}

	/**
	 * @param list<string> $enabled_post_types
	 * @return array{defaultToMarkdown: bool, enabledPostTypes: list<string>}
	 */
	public static function script_flags( bool $setting_enabled, array $enabled_post_types ): array {
		return array(
			'defaultToMarkdown' => $setting_enabled,
			'enabledPostTypes'  => array_values( $enabled_post_types ),
		);
	}

	/**
	 * Set the Markdown template when applying; never lock it, never replace an existing template.
	 *
	 * @param object $post_type Post type object (WP_Post_Type or a stand-in in tests).
	 */
	public static function assign_unlocked_template( object $post_type, bool $apply ): object {
		if ( ! $apply ) {
			return $post_type;
		}

		if ( isset( $post_type->template ) && is_array( $post_type->template ) && $post_type->template !== array() ) {
			return $post_type;
		}

		$post_type->template = self::template();

		return $post_type;
	}
}
