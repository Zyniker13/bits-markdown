<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Tests;

use Bristlecone\BitsMarkdown\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConflictMatrixTest extends TestCase {

	private Parser $parser;

	protected function setUp(): void {
		$this->parser = new Parser();
	}

	/**
	 * @return \Generator<string, array{string, list<string>, list<string>}>
	 */
	public static function matrix(): \Generator {
		yield 'currency and math' => array(
			'Price $12.00 and formula $x$.',
			array( 'bits-markdown-math', '$12.00' ),
			array(),
		);
		yield 'footnote and superscript' => array(
			"See [^1] and 100m^2\n\n[^1]: Note.\n",
			array( 'bits-markdown-footnote', '<sup>2</sup>' ),
			array( '<sup>1</sup>' ),
		);
		yield 'strike and subscript' => array(
			'~~a~~ and x~z~',
			array( '<del>a</del>', '<sub>z</sub>' ),
			array(),
		);
		yield 'autolink not writer comment' => array(
			'Visit https://example.com/path',
			array( 'https://example.com/path' ),
			array( 'bits-markdown-page-break' ),
		);
		yield 'page break vs thematic breaks' => array(
			"+++\n\n---\n\n***\n",
			array( 'bits-markdown-page-break', '<hr />' ),
			array(),
		);
		yield 'citation in fenced code vs real citation' => array(
			"A claim[p. 1][#K]\n\n```\n[p. 1][#K]\n```\n\n[#K]: Book.\n",
			array( 'bits-markdown-citation', '[p. 1][#K]' ),
			array(),
		);
		yield 'toc in fence' => array(
			"```\n{{TOC}}\n```\n\n## One\n",
			array( '{{TOC}}' ),
			array( 'bits-markdown-toc' ),
		);
		yield 'math caret not extra sup' => array(
			'$x^2$',
			array( 'bits-markdown-math', 'x^2' ),
			array( '<sup>' ),
		);
		yield 'task list not checkbox' => array(
			'- [ ] task',
			array( '[ ]' ),
			array( 'checkbox' ),
		);
		yield 'yaml then thematic' => array(
			"---\ntitle: Doc\n---\n\n---\n\nPara\n",
			array( 'Para' ),
			array(),
		);
		yield 'emphasis in table' => array(
			"| A |\n| --- |\n| *em* _also_ |",
			array( '<em>em</em>', '<em>also</em>' ),
			array(),
		);
		yield 'quote table footnote' => array(
			"> | H |\n> | --- |\n> | cell[^1] |\n\n[^1]: Note in quote context.\n",
			array( '<table>', 'bits-markdown-footnote' ),
			array(),
		);
		yield 'triple plus plus plus not break' => array(
			'++++',
			array( '++++' ),
			array( 'bits-markdown-page-break' ),
		);
		yield 'http autolink' => array(
			'http://example.com',
			array( 'href="http://example.com"' ),
			array(),
		);
	}

	/**
	 * @param list<string> $contains
	 * @param list<string> $absent
	 */
	#[DataProvider( 'matrix' )]
	public function test_conflict_case( string $markdown, array $contains, array $absent ): void {
		$html = $this->parser->convert( $markdown, array( 'id' => 'c' ) )->html;
		foreach ( $contains as $needle ) {
			$this->assertStringContainsString( $needle, $html );
		}
		foreach ( $absent as $needle ) {
			$this->assertStringNotContainsString( $needle, $html );
		}
	}

	public function test_kitchen_sink_has_toc_footnotes_and_citations(): void {
		$markdown = (string) file_get_contents( dirname( __DIR__ ) . '/tests/fixtures/documents/kitchen-sink.md' );
		$html     = $this->parser->convert( $markdown, array( 'id' => 'sink' ) )->html;
		$this->assertStringContainsString( 'bits-markdown-toc', $html );
		$this->assertStringContainsString( 'bits-markdown-footnotes', $html );
		$this->assertStringContainsString( 'bits-markdown-bibliography', $html );
		$this->assertStringContainsString( 'bits-fn-sink-', $html );
		$this->assertStringContainsString( 'bits-fn-sink-', $html );
		$this->assertNotEquals(
			substr_count( $html, 'bits-fn-sink-' ),
			0
		);
	}
}
