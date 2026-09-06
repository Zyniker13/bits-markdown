<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Tests;

use Bristlecone\BitsMarkdown\Parser;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase {

	private Parser $parser;

	protected function setUp(): void {
		$this->parser = new Parser();
	}

	public function test_emphasis_and_highlight(): void {
		$html = $this->html( '**bold** *italic* ~~strike~~ ==mark==' );
		$this->assertStringContainsString( '<strong>bold</strong>', $html );
		$this->assertStringContainsString( '<em>italic</em>', $html );
		$this->assertStringContainsString( '<del>strike</del>', $html );
		$this->assertStringContainsString( '<mark>mark</mark>', $html );
	}

	public function test_headings_tables_and_code(): void {
		$markdown = "# Title\n\n| A | B |\n| --- | --- |\n| 1 | 2 |\n\n```php\necho \"hi\";\n```\n";
		$html     = $this->html( $markdown );
		$this->assertStringContainsString( '<h1', $html );
		$this->assertStringContainsString( '<table>', $html );
		$this->assertStringContainsString( '<pre', $html );
		$this->assertStringContainsString( 'echo', $html );
	}

	public function test_named_and_inline_footnotes(): void {
		$markdown = "Hello[^1] and more[^this is inline.]\n\n[^1]: Named footnote.\n";
		$html     = $this->html( $markdown, array( 'id' => '12' ) );
		$this->assertStringContainsString( 'bits-fn-12-', $html );
		$this->assertStringContainsString( 'Named footnote', $html );
		$this->assertStringContainsString( 'this is inline', $html );
		$this->assertStringContainsString( 'bits-markdown-footnotes', $html );
	}

	public function test_math_and_sup_sub(): void {
		$html = $this->html( 'Area is $x^2$ and 100m^2 and x~z and y^(a+b)^.' );
		$this->assertStringContainsString( 'bits-markdown-math', $html );
		$this->assertStringContainsString( '<sup>2</sup>', $html );
		$this->assertStringContainsString( '<sub>z</sub>', $html );
		$this->assertStringContainsString( '<sup>a+b</sup>', $html );
	}

	public function test_display_math(): void {
		$html = $this->html( "$$\nE=mc^2\n$$" );
		$this->assertStringContainsString( 'bits-markdown-math-display', $html );
		$this->assertStringContainsString( 'E=mc^2', $html );
	}

	public function test_page_break_and_writer_comment(): void {
		$html = $this->html( "Visible\n\n// secret comment\n\n+++\n\nAfter break" );
		$this->assertStringNotContainsString( 'secret comment', $html );
		$this->assertStringContainsString( 'bits-markdown-page-break', $html );
		$this->assertStringContainsString( 'Visible', $html );
		$this->assertStringContainsString( 'After break', $html );
	}

	public function test_front_matter_interpolation_and_toc(): void {
		$markdown = "---\nMe: Bob Loblaw\ntitle: Hello\n---\n\nSincerely, [%me]\n\n{{TOC}}\n\n## One\n\n## Two\n";
		$result   = $this->parser->convert( $markdown );
		$this->assertSame( 'Hello', $result->front_matter['title'] ?? null );
		$this->assertStringContainsString( 'Bob Loblaw', $result->html );
		$this->assertStringContainsString( 'bits-markdown-toc', $result->html );
		$this->assertStringContainsString( '#one', $result->html );
	}

	public function test_heading_cross_reference_and_label(): void {
		$markdown = "# My Level 1 Header [My Label]\n\nSee [My Level 1 Header][] and [My Label][].\n";
		$html     = $this->html( $markdown );
		$this->assertStringContainsString( 'href="#my-level-1-header"', $html );
		$this->assertStringNotContainsString( '[My Label]', $html );
	}

	public function test_citations(): void {
		$markdown = "A claim[p. 23][#Doe:2006].\n\n[#Doe:2006]: John Doe. Some Big Fancy Book. Vanity Press, 2006.\n";
		$html     = $this->html( $markdown );
		$this->assertStringContainsString( 'bits-markdown-citation', $html );
		$this->assertStringContainsString( 'p. 23', $html );
		$this->assertStringContainsString( 'bits-markdown-bibliography', $html );
		$this->assertStringContainsString( 'John Doe', $html );
	}

	public function test_safe_html_passes_and_script_is_disallowed(): void {
		$html = $this->html( 'Hello <span class="ok">there</span> and <script>alert(1)</script>' );
		$this->assertStringContainsString( '<span class="ok">there</span>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_conversion_result_flags(): void {
		$result = $this->parser->convert( '$$a$$ and `code`' );
		$this->assertTrue( $result->has_math );
	}

	private function html( string $markdown, array $context = array() ): string {
		return $this->parser->convert( $markdown, $context )->html;
	}
}
