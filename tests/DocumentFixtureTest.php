<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Tests;

use Bristlecone\BitsMarkdown\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocumentFixtureTest extends TestCase {

	private Parser $parser;

	protected function setUp(): void {
		$this->parser = new Parser();
	}

	public static function documentFiles(): \Generator {
		$dir = dirname( __DIR__ ) . '/tests/fixtures/documents';
		foreach ( glob( $dir . '/*.md' ) ?: array() as $path ) {
			yield basename( $path ) => array( $path );
		}
	}

	#[DataProvider( 'documentFiles' )]
	public function test_document_fixture_converts( string $path ): void {
		$markdown = (string) file_get_contents( $path );
		$result   = $this->parser->convert( $markdown, array( 'id' => 'doc' ) );
		$this->assertIsString( $result->html );
	}

	public function test_kitchen_sink_document(): void {
		$markdown = $this->read( 'kitchen-sink.md' );
		$result   = $this->parser->convert( $markdown, array( 'id' => 'sink' ) );
		$html     = $result->html;

		$this->assertSame( 'Spec kitchen sink', $result->front_matter['title'] ?? null );
		$this->assertSame( 'spec-kitchen-sink', $result->front_matter['slug'] ?? null );
		$this->assertStringContainsString( 'Bob Loblaw', $html );
		$this->assertStringContainsString( 'bits-markdown-toc', $html );
		$this->assertStringContainsString( 'bits-markdown-heading-permalink', $html );
		$this->assertStringContainsString( 'id="explicit-sink-three"', $html );
		$this->assertStringContainsString( 'bits-fn-sink-', $html );
		$this->assertStringContainsString( '<em>', $html );
		$this->assertStringContainsString( '<strong>', $html );
		$this->assertStringContainsString( '<del>', $html );
		$this->assertStringContainsString( '<mark>', $html );
		$this->assertStringContainsString( '<table>', $html );
		$this->assertStringContainsString( 'hljs', $html );
		$this->assertStringContainsString( 'unlabeled fence with **bold**', $html );
		$this->assertStringContainsString( 'bits-markdown-math-inline', $html );
		$this->assertStringContainsString( 'bits-markdown-math-display', $html );
		$this->assertStringContainsString( '$12.00$', $html );
		$this->assertStringContainsString( '<sup>2</sup>', $html );
		$this->assertStringContainsString( 'bits-markdown-page-break', $html );
		$this->assertStringNotContainsString( 'this writer comment must not appear', $html );
		$this->assertStringContainsString( 'bits-markdown-citation', $html );
		$this->assertStringContainsString( 'bits-markdown-bibliography', $html );
		$this->assertStringContainsString( '<span class="ok">there</span>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '[ ] not a task', $html );
		$this->assertStringContainsString( '/includes/chapter.md', $html );
		$this->assertStringContainsString( 'href="#heading-two"', $html );
		$this->assertStringContainsString( 'href="#explicit-sink-three"', $html );
		$this->assertTrue( $result->has_math );
	}

	public function test_unicode_document_round_trips(): void {
		$html = $this->parser->convert( $this->read( 'unicode.md' ) )->html;
		$this->assertStringContainsString( 'Café', $html );
		$this->assertStringContainsString( '日本語見出し', $html );
		$this->assertStringContainsString( 'العربية', $html );
		$this->assertStringContainsString( 'Ångström', $html );
		$this->assertStringContainsString( 'αγω', $html );
		$this->assertTrue(
			str_contains( $html, '/φου' ) || str_contains( $html, '%CF%86%CE%BF%CF%85' )
		);
	}

	public function test_negative_ia_writer_document(): void {
		$html = $this->parser->convert( $this->read( 'negative-ia-writer.md' ) )->html;
		$this->assertStringNotContainsString( 'checkbox', $html );
		$this->assertStringContainsString( '/includes/chapter.md', $html );
	}

	public function test_unsafe_html_document(): void {
		$html = $this->parser->convert( $this->read( 'unsafe-html.md' ) )->html;
		$this->assertStringContainsString( '<span class="ok">there</span>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<iframe', $html );
		$this->assertStringNotContainsString( 'javascript:alert', $html );
	}

	public function test_footnotes_document_ids_are_namespaced(): void {
		$html = $this->parser->convert( $this->read( 'footnotes.md' ), array( 'id' => '77' ) )->html;
		$this->assertStringContainsString( 'bits-fn-77-', $html );
		$this->assertStringContainsString( 'Named footnote', $html );
		$this->assertStringContainsString( 'this is inline', $html );
	}

	public function test_comment_fixtures_convert(): void {
		$dir = dirname( __DIR__ ) . '/tests/fixtures/comments';
		foreach ( glob( $dir . '/*.md' ) ?: array() as $path ) {
			$html = $this->parser->convert( (string) file_get_contents( $path ), array( 'id' => 'c1', 'comment' => true ) )->html;
			$this->assertIsString( $html );
		}
		$xss = $this->parser->convert(
			(string) file_get_contents( $dir . '/xss.md' ),
			array( 'id' => 'c1' )
		)->html;
		$this->assertStringNotContainsString( '<script>', $xss );
	}

	private function read( string $name ): string {
		return (string) file_get_contents( dirname( __DIR__ ) . '/tests/fixtures/documents/' . $name );
	}
}
