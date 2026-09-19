<?php

declare(strict_types=1);

namespace Bristlecone\Markdown\Tests;

use Bristlecone\Markdown\BlockAlias;
use Bristlecone\Markdown\BlockAliasRegistry;
use Bristlecone\Markdown\BlockMarkup;
use Bristlecone\Markdown\JetpackMarkdownBlock;
use Bristlecone\Markdown\Settings;
use PHPUnit\Framework\TestCase;

final class BlockAliasRegistryTest extends TestCase {

	public function test_builtins_are_jetpack_and_simple_markdown(): void {
		$names = BlockAliasRegistry::names( BlockAliasRegistry::builtins() );

		$this->assertSame(
			array( 'jetpack/markdown', 'simple-markdown/markdown-block' ),
			$names
		);
		$this->assertSame( 'source', BlockAliasRegistry::builtins()[0]->attribute );
		$this->assertSame( 'content', BlockAliasRegistry::builtins()[1]->attribute );
	}

	public function test_parse_custom_lines_prefers_pipe_attribute(): void {
		$aliases = BlockAliasRegistry::parse_custom_text(
			"acme/markdown|content\n# comment\n\ninvalid\nfoo/bar\nbristlecone/markdown|markdown\n"
		);

		$this->assertCount( 2, $aliases );
		$this->assertSame( 'acme/markdown', $aliases[0]->name );
		$this->assertSame( 'content', $aliases[0]->attribute );
		$this->assertSame( BlockAlias::ORIGIN_CUSTOM, $aliases[0]->origin );
		$this->assertSame( 'foo/bar', $aliases[1]->name );
		$this->assertNull( $aliases[1]->attribute );
	}

	public function test_parse_skips_duplicates_and_own_names(): void {
		$aliases = BlockAliasRegistry::parse_custom_text( "acme/md|source\nacme/md|content\nbits/markdown\n" );

		$this->assertCount( 1, $aliases );
		$this->assertSame( 'acme/md', $aliases[0]->name );
		$this->assertSame( 'source', $aliases[0]->attribute );
	}

	public function test_sanitize_custom_block_aliases_round_trips(): void {
		$text = Settings::sanitize_custom_block_aliases( "ACME/Markdown|Content\nnot a name\n" );
		$this->assertSame( 'acme/markdown|Content', $text );
	}

	public function test_aliases_to_adopt_skips_registered_and_active_jetpack(): void {
		$custom = BlockAliasRegistry::parse_custom_text( "acme/markdown|content\n" );
		$registered = array( 'simple-markdown/markdown-block', 'acme/markdown' );

		$adopt = BlockAliasRegistry::aliases_to_adopt(
			$custom,
			static fn( string $name ): bool => in_array( $name, $registered, true ),
			true
		);

		$this->assertSame( array(), BlockAliasRegistry::names( $adopt ) );

		$adopt_inactive = BlockAliasRegistry::aliases_to_adopt(
			$custom,
			static fn( string $name ): bool => in_array( $name, $registered, true ),
			false
		);

		$this->assertSame( array( 'jetpack/markdown' ), BlockAliasRegistry::names( $adopt_inactive ) );
	}

	public function test_aliases_to_adopt_does_not_include_unlisted_scan_hits(): void {
		$adopt = BlockAliasRegistry::aliases_to_adopt( array(), static fn(): bool => false, false );
		$names = BlockAliasRegistry::names( $adopt );

		$this->assertContains( 'jetpack/markdown', $names );
		$this->assertContains( 'simple-markdown/markdown-block', $names );
		$this->assertNotContains( 'acme/markdown', $names );
		$this->assertNotContains( 'foo/markdown-comment', $names );
	}

	public function test_custom_identifier_drives_rewrite_with_mapped_attribute(): void {
		$markup  = '<!-- wp:acme/markdown {"content":"# Title","align":"wide"} /-->';
		$aliases = BlockAliasRegistry::parse_custom_text( "acme/markdown|content\n" );
		$result  = BlockMarkup::rewrite_aliased_blocks(
			$markup,
			$aliases,
			static function ( string $source ): string {
				return '<p>' . $source . '</p>';
			}
		);

		$this->assertSame( 1, $result['converted'] );
		$this->assertStringContainsString( 'wp:bristlecone/markdown', $result['content'] );
		$this->assertStringContainsString( '"markdown":"# Title"', $result['content'] );
		$this->assertStringContainsString( '"align":"wide"', $result['content'] );
		$this->assertStringNotContainsString( '"content":', $result['content'] );
		$this->assertStringNotContainsString( 'wp:acme/markdown', $result['content'] );
	}

	public function test_omitted_attribute_uses_source_then_content_then_markdown(): void {
		$this->assertSame( 'from-source', BlockMarkup::source_from_attrs( array( 'source' => 'from-source', 'content' => 'from-content' ), null ) );
		$this->assertSame( 'from-content', BlockMarkup::source_from_attrs( array( 'content' => 'from-content', 'markdown' => 'from-md' ), null ) );
		$this->assertSame( 'from-md', BlockMarkup::source_from_attrs( array( 'markdown' => 'from-md' ), null ) );
		$this->assertSame( 'explicit', BlockMarkup::source_from_attrs( array( 'body' => 'explicit', 'source' => 'other' ), 'body' ) );
		$this->assertSame( '', BlockMarkup::source_from_attrs( array( 'other' => 'x' ), null ) );
		$this->assertNull( BlockMarkup::source_from_attrs( array( 'source' => array( 'nope' ) ), 'source' ) );
	}

	public function test_settings_defaults_include_empty_custom_list_and_converter_off(): void {
		$defaults = ( new Settings() )->defaults();
		$this->assertArrayHasKey( 'custom_block_aliases', $defaults );
		$this->assertSame( '', $defaults['custom_block_aliases'] );
		$this->assertFalse( $defaults['jetpack_block_converter'] );
		$this->assertFalse( $defaults['default_to_markdown'] );
	}

	public function test_tools_and_scan_share_settings_unlock(): void {
		$this->assertFalse( JetpackMarkdownBlock::scan_allowed( false ) );
		$this->assertTrue( JetpackMarkdownBlock::scan_allowed( true ) );
		$this->assertFalse( JetpackMarkdownBlock::should_register_tools_page( false ) );
		$this->assertTrue( JetpackMarkdownBlock::should_register_tools_page( true ) );
		$this->assertFalse( JetpackMarkdownBlock::conversion_allowed( true, true ) );
		$this->assertTrue( JetpackMarkdownBlock::conversion_allowed( true, false ) );
	}
}
