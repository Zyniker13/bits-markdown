<?php

declare(strict_types=1);

namespace Bristlecone\Markdown\Tests;

use Bristlecone\Markdown\DefaultMarkdownEditor;
use Bristlecone\Markdown\Settings;
use PHPUnit\Framework\TestCase;

final class DefaultMarkdownEditorTest extends TestCase {

	public function test_setting_defaults_off(): void {
		$defaults = ( new Settings() )->defaults();

		$this->assertArrayHasKey( 'default_to_markdown', $defaults );
		$this->assertFalse( $defaults['default_to_markdown'] );
	}

	public function test_template_is_single_unlocked_empty_markdown_block(): void {
		$template = DefaultMarkdownEditor::template();

		$this->assertSame(
			array(
				array(
					'bristlecone/markdown',
					array(
						'markdown' => '',
					),
				),
			),
			$template
		);
	}

	public function test_should_apply_only_when_setting_type_and_block_editor_align(): void {
		$this->assertFalse( DefaultMarkdownEditor::should_apply( false, true, true ) );
		$this->assertFalse( DefaultMarkdownEditor::should_apply( true, false, true ) );
		$this->assertFalse( DefaultMarkdownEditor::should_apply( true, true, false ) );
		$this->assertTrue( DefaultMarkdownEditor::should_apply( true, true, true ) );
	}

	public function test_should_set_default_block_name_scopes_to_enabled_post_types(): void {
		$enabled = array( 'post', 'page' );

		$this->assertFalse( DefaultMarkdownEditor::should_set_default_block_name( false, 'post', $enabled ) );
		$this->assertFalse( DefaultMarkdownEditor::should_set_default_block_name( true, '', $enabled ) );
		$this->assertFalse( DefaultMarkdownEditor::should_set_default_block_name( true, 'product', $enabled ) );
		$this->assertTrue( DefaultMarkdownEditor::should_set_default_block_name( true, 'post', $enabled ) );
		$this->assertTrue( DefaultMarkdownEditor::should_set_default_block_name( true, 'page', $enabled ) );
		$this->assertTrue( DefaultMarkdownEditor::should_set_default_block_name( true, 'docs', array( 'docs' ) ) );
	}

	public function test_script_flags_pass_setting_and_enabled_types(): void {
		$this->assertSame(
			array(
				'defaultToMarkdown' => false,
				'enabledPostTypes'  => array( 'post', 'page' ),
			),
			DefaultMarkdownEditor::script_flags( false, array( 'post', 'page' ) )
		);
		$this->assertSame(
			array(
				'defaultToMarkdown' => true,
				'enabledPostTypes'  => array( 'post' ),
			),
			DefaultMarkdownEditor::script_flags( true, array( 'post' ) )
		);
	}

	public function test_assign_sets_template_without_locking(): void {
		$type = new \stdClass();

		DefaultMarkdownEditor::assign_unlocked_template( $type, true );

		$this->assertSame( DefaultMarkdownEditor::template(), $type->template );
		$this->assertFalse( isset( $type->template_lock ) );
	}

	public function test_assign_is_noop_when_not_applying(): void {
		$type = new \stdClass();

		DefaultMarkdownEditor::assign_unlocked_template( $type, false );

		$this->assertFalse( isset( $type->template ) );
		$this->assertFalse( isset( $type->template_lock ) );
	}

	public function test_assign_does_not_replace_existing_template(): void {
		$type           = new \stdClass();
		$type->template = array( array( 'core/paragraph' ) );

		DefaultMarkdownEditor::assign_unlocked_template( $type, true );

		$this->assertSame( array( array( 'core/paragraph' ) ), $type->template );
	}

	public function test_assign_fills_empty_template_array(): void {
		$type           = new \stdClass();
		$type->template = array();

		DefaultMarkdownEditor::assign_unlocked_template( $type, true );

		$this->assertSame( DefaultMarkdownEditor::template(), $type->template );
	}
}
