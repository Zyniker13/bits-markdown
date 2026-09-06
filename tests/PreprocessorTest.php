<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Tests;

use Bristlecone\BitsMarkdown\Parser;
use Bristlecone\BitsMarkdown\Preprocessor;
use PHPUnit\Framework\TestCase;

final class PreprocessorTest extends TestCase {

	public function test_utf8_continuation_byte_is_not_treated_as_newline(): void {
		$pre  = new Preprocessor();
		$in   = "[ΑΓΩ]: /φου\n\n[αγω]\n";
		$out  = $pre->process( $in );
		$this->assertTrue( mb_check_encoding( $out['markdown'], 'UTF-8' ) );
		$this->assertStringContainsString( '/φου', $out['markdown'] );

		$html = ( new Parser() )->convert( $in )->html;
		$this->assertStringContainsString( 'αγω', $html );
		$this->assertTrue(
			str_contains( $html, '/φου' ) || str_contains( $html, '%CF%86%CE%BF%CF%85' ),
			'Greek destination should appear decoded or percent-encoded'
		);
	}

	public function test_angstrom_is_not_split(): void {
		$html = ( new Parser() )->convert( 'Ångström' )->html;
		$this->assertStringContainsString( 'Ångström', $html );
	}

	public function test_heading_refs_skip_fences(): void {
		$pre = new Preprocessor();
		$in  = "```\n# Not a heading\n```\n\n# Real\n";
		$out = $pre->process( $in );
		$this->assertStringContainsString( '# Not a heading', $out['markdown'] );
		$this->assertStringContainsString( '{#real}', $out['markdown'] );
		$this->assertStringContainsString( '[Real]: #real', $out['markdown'] );
	}

	public function test_citation_definitions_are_removed_from_body(): void {
		$pre = new Preprocessor();
		$out = $pre->process( "See[p. 1][#K]\n\n[#K]: Book title\n" );
		$this->assertArrayHasKey( 'K', $out['citations'] );
		$this->assertStringNotContainsString( '[#K]: Book title', $out['markdown'] );
		$this->assertStringContainsString( 'bits-markdown-citation', $out['markdown'] );
		$this->assertStringContainsString( 'bits-markdown-bibliography', $out['markdown'] );
	}

	public function test_empty_locator_uses_citation_key(): void {
		$pre = new Preprocessor();
		$out = $pre->process( "See[][#K]\n\n[#K]: Book\n" );
		$this->assertStringContainsString( '>K</a></cite>', $out['markdown'] );
	}

	public function test_id_sanitizer_strips_non_slug_characters(): void {
		$html = ( new Parser() )->convert( "Hi[^1]\n\n[^1]: n\n", array( 'id' => 'post/12' ) )->html;
		$this->assertStringContainsString( 'bits-fn-post12-', $html );
		$this->assertStringNotContainsString( 'bits-fn-post/12-', $html );
	}
}
