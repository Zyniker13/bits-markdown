<?php

declare(strict_types=1);

namespace Bristlecone\Markdown\Tests;

use Bristlecone\Markdown\BlockAlias;
use Bristlecone\Markdown\BlockAliasRegistry;
use Bristlecone\Markdown\BlockMarkup;
use Bristlecone\Markdown\MarkdownBlockScanner;
use PHPUnit\Framework\TestCase;

final class MarkdownBlockScannerTest extends TestCase {

	public function test_simple_markdown_reads_content_attr(): void {
		$from_simple = BlockMarkup::document_markdown_from_blocks(
			array(
				array(
					'blockName' => 'simple-markdown/markdown-block',
					'attrs'     => array( 'content' => '# Simple' ),
					'innerHTML' => '',
				),
			)
		);
		$sources     = BlockMarkup::extract_named_sources(
			$this->simple_fixture(),
			BlockAliasRegistry::SIMPLE_MARKDOWN,
			'content'
		);

		$this->assertSame( '# Simple', $from_simple );
		$this->assertSame(
			array(
				"# Simple\n\nA quote: \"Hi\" and a dash -- plus **bold**.",
				'Second simple',
			),
			$sources
		);
	}

	public function test_rewrite_simple_markdown_maps_content_to_markdown(): void {
		$result = BlockMarkup::rewrite_aliased_blocks(
			$this->simple_fixture(),
			BlockAliasRegistry::builtins(),
			static function ( string $source ): string {
				return '<p>CONVERTED:' . $source . '</p>';
			}
		);

		$this->assertSame( 2, $result['converted'] );
		$this->assertStringContainsString( 'wp:bristlecone/markdown', $result['content'] );
		$this->assertStringContainsString( '"markdown":', $result['content'] );
		$this->assertStringNotContainsString( 'wp:simple-markdown/markdown-block', $result['content'] );
		$this->assertStringContainsString( 'Keep this paragraph.', $result['content'] );
		$this->assertStringContainsString( 'CONVERTED:# Simple', $result['content'] );
	}

	public function test_scan_lists_unregistered_markdown_names_with_attribute_counts(): void {
		$summary = MarkdownBlockScanner::summarize(
			$this->scan_fixture(),
			MarkdownBlockScanner::exclude_defaults()
		);

		$this->assertArrayHasKey( 'acme/markdown', $summary );
		$this->assertArrayHasKey( 'foo/markdown-comment', $summary );
		$this->assertArrayHasKey( 'vendor/extra-markdown', $summary );
		$this->assertArrayNotHasKey( 'jetpack/markdown', $summary );
		$this->assertArrayNotHasKey( 'bristlecone/markdown', $summary );
		$this->assertArrayNotHasKey( 'core/paragraph', $summary );

		$this->assertSame( 1, $summary['acme/markdown']['count'] );
		$this->assertSame( array( 'content' => 1 ), $summary['acme/markdown']['attributes'] );
		$this->assertSame( 'content', $summary['acme/markdown']['suggested_attribute'] );
		$this->assertFalse( $summary['acme/markdown']['false_friend'] );

		$this->assertTrue( $summary['foo/markdown-comment']['false_friend'] );
		$this->assertSame( array( 'comment' => 1 ), $summary['foo/markdown-comment']['attributes'] );
	}

	public function test_scan_does_not_rewrite_content(): void {
		$before = $this->scan_fixture();
		MarkdownBlockScanner::summarize( $before, array() );
		$this->assertSame( $before, $this->scan_fixture() );
	}

	public function test_scan_excludes_custom_list_names_without_auto_registering(): void {
		$custom  = BlockAliasRegistry::parse_custom_text( "acme/markdown|content\n" );
		$exclude = array_merge(
			MarkdownBlockScanner::exclude_defaults(),
			BlockAliasRegistry::names( $custom )
		);
		$summary = MarkdownBlockScanner::summarize( $this->scan_fixture(), $exclude );

		$this->assertArrayNotHasKey( 'acme/markdown', $summary );
		$this->assertArrayHasKey( 'vendor/extra-markdown', $summary );

		$adopt = BlockAliasRegistry::aliases_to_adopt( array(), static fn(): bool => false, false );
		$this->assertNotContains( 'vendor/extra-markdown', BlockAliasRegistry::names( $adopt ) );
	}

	public function test_false_friend_is_not_converted_unless_selected(): void {
		$markup = $this->scan_fixture();
		$skip   = BlockMarkup::rewrite_named_blocks(
			$markup,
			'foo/markdown-comment',
			'comment',
			static function (): string {
				return '<p>should-not-run-unless-selected</p>';
			}
		);

		$selected = array( new BlockAlias( 'acme/markdown', 'content', BlockAlias::ORIGIN_CUSTOM ) );
		$result   = BlockMarkup::rewrite_aliased_blocks(
			$markup,
			$selected,
			static function ( string $source ): string {
				return '<p>' . $source . '</p>';
			}
		);

		$this->assertSame( 1, $skip['converted'] );
		$this->assertSame( 1, $result['converted'] );
		$this->assertStringContainsString( 'wp:foo/markdown-comment', $result['content'] );
		$this->assertStringNotContainsString( 'should-not-run-unless-selected', $result['content'] );
		$this->assertStringContainsString( 'Hello from Acme', $result['content'] );
		$this->assertStringNotContainsString( 'wp:acme/markdown', $result['content'] );
	}

	public function test_merge_summaries_adds_post_counts(): void {
		$first = MarkdownBlockScanner::summarize(
			'<!-- wp:acme/markdown {"content":"A"} /-->',
			array()
		);
		$second = MarkdownBlockScanner::summarize(
			'<!-- wp:acme/markdown {"content":"B"} /--><!-- wp:acme/markdown {"source":"C"} /-->',
			array()
		);

		$merged = MarkdownBlockScanner::merge_summaries( $first, $second, true );
		$this->assertSame( 3, $merged['acme/markdown']['count'] );
		$this->assertSame( 2, $merged['acme/markdown']['posts'] );
		$this->assertSame( 2, $merged['acme/markdown']['attributes']['content'] );
		$this->assertSame( 1, $merged['acme/markdown']['attributes']['source'] );
		$this->assertSame( 'source', $merged['acme/markdown']['suggested_attribute'] );
	}

	private function simple_fixture(): string {
		return (string) file_get_contents( dirname( __DIR__ ) . '/tests/fixtures/blocks/simple-markdown.html' );
	}

	private function scan_fixture(): string {
		return (string) file_get_contents( dirname( __DIR__ ) . '/tests/fixtures/blocks/scan-candidates.html' );
	}
}
