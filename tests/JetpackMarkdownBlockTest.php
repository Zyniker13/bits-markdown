<?php

declare(strict_types=1);

namespace Bristlecone\Markdown\Tests;

use Bristlecone\Markdown\BlockMarkup;
use Bristlecone\Markdown\JetpackMarkdownBlock;
use Bristlecone\Markdown\Parser;
use Bristlecone\Markdown\Settings;
use PHPUnit\Framework\TestCase;

final class JetpackMarkdownBlockTest extends TestCase {

	public function test_parses_sample_jetpack_markdown_markup(): void {
		$markup  = $this->fixture();
		$sources = BlockMarkup::extract_jetpack_sources( $markup );

		$this->assertSame(
			array(
				"# Hello\n\nA quote: \"Hi\" and a dash -- plus **bold**.",
				'Second block',
			),
			$sources
		);
	}

	public function test_document_markdown_reads_jetpack_source_attr(): void {
		$from_jetpack = BlockMarkup::document_markdown_from_blocks(
			array(
				array(
					'blockName' => 'jetpack/markdown',
					'attrs'     => array( 'source' => '# Title' ),
					'innerHTML' => '<h1>Title</h1>',
				),
			)
		);
		$from_own     = BlockMarkup::document_markdown_from_blocks(
			array(
				array(
					'blockName' => 'bristlecone/markdown',
					'attrs'     => array( 'markdown' => '# Own' ),
					'innerHTML' => '',
				),
			)
		);
		$from_legacy  = BlockMarkup::document_markdown_from_blocks(
			array(
				array(
					'blockName' => 'bits/markdown',
					'attrs'     => array( 'markdown' => '# Legacy' ),
					'innerHTML' => '',
				),
			)
		);

		$this->assertSame( '# Title', $from_jetpack );
		$this->assertSame( '# Own', $from_own );
		$this->assertSame( '# Legacy', $from_legacy );
	}

	public function test_document_markdown_rejects_mixed_blocks(): void {
		$markdown = BlockMarkup::document_markdown_from_blocks(
			array(
				array(
					'blockName' => 'core/paragraph',
					'attrs'     => array(),
					'innerHTML' => '<p>Hi</p>',
				),
				array(
					'blockName' => 'jetpack/markdown',
					'attrs'     => array( 'source' => 'Nope' ),
					'innerHTML' => '',
				),
			)
		);
		$this->assertNull( $markdown );
	}

	public function test_conversion_maps_source_to_markdown_and_html(): void {
		$attrs = BlockMarkup::bristlecone_attrs_from_jetpack(
			array(
				'source' => '# Hello',
				'align'  => 'wide',
			),
			'<h1>Hello</h1>'
		);

		$this->assertSame( '# Hello', $attrs['markdown'] );
		$this->assertSame( '<h1>Hello</h1>', $attrs['html'] );
		$this->assertSame( 'wide', $attrs['align'] );
		$this->assertArrayNotHasKey( 'source', $attrs );
	}

	public function test_rewrite_converts_jetpack_blocks_and_leaves_others(): void {
		$markup = $this->fixture();
		$result = BlockMarkup::rewrite_jetpack_markdown_blocks(
			$markup,
			static function ( string $source ): string {
				return '<p>CONVERTED:' . $source . '</p>';
			}
		);

		$this->assertSame( 2, $result['converted'] );
		$this->assertSame( 0, $result['errors'] );
		$this->assertStringContainsString( 'wp:bristlecone/markdown', $result['content'] );
		$this->assertStringContainsString( 'wp-block-bristlecone-markdown', $result['content'] );
		$this->assertStringContainsString( '"markdown":', $result['content'] );
		$this->assertStringNotContainsString( 'wp:jetpack/markdown', $result['content'] );
		$this->assertStringContainsString( 'Keep this paragraph.', $result['content'] );
		$this->assertStringContainsString( 'CONVERTED:# Hello', $result['content'] );
		$this->assertStringContainsString( 'CONVERTED:Second block', $result['content'] );
	}

	public function test_rewrite_leaves_bristlecone_blocks_alone(): void {
		$markup = BlockMarkup::serialize_bristlecone( '# Already', '<h1>Already</h1>' );
		$result = BlockMarkup::rewrite_jetpack_markdown_blocks(
			$markup,
			static function (): string {
				return '<p>should-not-run</p>';
			}
		);

		$this->assertSame( 0, $result['converted'] );
		$this->assertSame( $markup, $result['content'] );
		$this->assertStringNotContainsString( 'should-not-run', $result['content'] );
	}

	public function test_rewrite_uses_server_parser_html(): void {
		$source = '**bold**';
		$markup = '<!-- wp:jetpack/markdown {"source":"**bold**"} -->' . "\n"
			. '<div class="wp-block-jetpack-markdown"><p><strong>bold</strong></p></div>' . "\n"
			. '<!-- /wp:jetpack/markdown -->';

		$parser = new Parser();
		$result = BlockMarkup::rewrite_jetpack_markdown_blocks(
			$markup,
			static function ( string $markdown ) use ( $parser ): string {
				return $parser->convert( $markdown, array( 'id' => 't' ) )->html;
			}
		);

		$this->assertSame( 1, $result['converted'] );
		$this->assertStringContainsString( '<strong>bold</strong>', $result['content'] );
		$this->assertStringContainsString( $source, $result['content'] );
		$this->assertStringContainsString( 'wp:bristlecone/markdown', $result['content'] );
	}

	public function test_source_with_closing_brace_in_json_is_parsed(): void {
		$source = 'Use {this} brace';
		$json   = json_encode( array( 'source' => $source ), JSON_UNESCAPED_SLASHES );
		$markup = '<!-- wp:jetpack/markdown ' . $json . " -->\n"
			. '<div class="wp-block-jetpack-markdown"><p>Use {this} brace</p></div>' . "\n"
			. '<!-- /wp:jetpack/markdown -->';

		$this->assertSame( array( $source ), BlockMarkup::extract_jetpack_sources( $markup ) );
	}

	public function test_converter_setting_defaults_off(): void {
		$defaults = ( new Settings() )->defaults();
		$this->assertArrayHasKey( 'jetpack_block_converter', $defaults );
		$this->assertFalse( $defaults['jetpack_block_converter'] );
	}

	public function test_tools_page_and_conversion_gates(): void {
		$this->assertFalse( JetpackMarkdownBlock::should_register_tools_page( false ) );
		$this->assertTrue( JetpackMarkdownBlock::should_register_tools_page( true ) );

		$this->assertFalse( JetpackMarkdownBlock::conversion_allowed( false, false ) );
		$this->assertFalse( JetpackMarkdownBlock::conversion_allowed( false, true ) );
		$this->assertFalse( JetpackMarkdownBlock::conversion_allowed( true, true ) );
		$this->assertTrue( JetpackMarkdownBlock::conversion_allowed( true, false ) );
	}

	public function test_rewrite_jetpack_leaves_simple_markdown_blocks(): void {
		$markup = '<!-- wp:simple-markdown/markdown-block {"content":"# Simple"} /-->' . "\n"
			. '<!-- wp:jetpack/markdown {"source":"JP"} /-->';
		$result = BlockMarkup::rewrite_jetpack_markdown_blocks(
			$markup,
			static function ( string $source ): string {
				return '<p>' . $source . '</p>';
			}
		);

		$this->assertSame( 1, $result['converted'] );
		$this->assertStringContainsString( 'wp:simple-markdown/markdown-block', $result['content'] );
		$this->assertStringContainsString( '"content":"# Simple"', $result['content'] );
		$this->assertStringNotContainsString( 'wp:jetpack/markdown', $result['content'] );
		$this->assertStringContainsString( 'wp:bristlecone/markdown', $result['content'] );
	}

	public function test_rewrite_nested_in_group_markup(): void {
		$markup = "<!-- wp:group -->\n<div class=\"wp-block-group\"><!-- wp:jetpack/markdown {\"source\":\"Hi\"} /--></div>\n<!-- /wp:group -->";
		$result = BlockMarkup::rewrite_jetpack_markdown_blocks(
			$markup,
			static function ( string $source ): string {
				return '<p>' . $source . '</p>';
			}
		);

		$this->assertSame( 1, $result['converted'] );
		$this->assertStringContainsString( 'wp:group', $result['content'] );
		$this->assertStringContainsString( 'wp:bristlecone/markdown', $result['content'] );
		$this->assertStringNotContainsString( 'wp:jetpack/markdown', $result['content'] );
	}

	private function fixture(): string {
		return (string) file_get_contents( dirname( __DIR__ ) . '/tests/fixtures/blocks/jetpack-markdown.html' );
	}
}
