<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Tests;

use Bristlecone\BitsMarkdown\Sanitizer;
use PHPUnit\Framework\TestCase;

final class SanitizerTest extends TestCase {

	private Sanitizer $sanitizer;

	protected function setUp(): void {
		$this->sanitizer = new Sanitizer();
	}

	public function test_comment_fallback_strips_script_without_wp(): void {
		$html = $this->sanitizer->sanitize_comment( '<p>ok</p><script>alert(1)</script><img src=x>' );
		$this->assertStringContainsString( '<p>ok</p>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	public function test_comment_fallback_keeps_markdown_tags(): void {
		$html = $this->sanitizer->sanitize_comment( '<p><em>a</em> <strong>b</strong> <mark>c</mark> <del>d</del></p>' );
		$this->assertStringContainsString( '<em>a</em>', $html );
		$this->assertStringContainsString( '<strong>b</strong>', $html );
		$this->assertStringContainsString( '<mark>c</mark>', $html );
		$this->assertStringContainsString( '<del>d</del>', $html );
	}

	public function test_post_passthrough_without_wp(): void {
		$raw = '<div class="bits-markdown"><script>alert(1)</script></div>';
		$this->assertSame( $raw, $this->sanitizer->sanitize_post( $raw ) );
	}

	public function test_expand_allowed_html_adds_markdown_tags_and_global_attrs(): void {
		$allowed = $this->sanitizer->expand_allowed_html(
			array(
				'p'  => array(),
				'a'  => array(),
				'th' => array(),
				'td' => array(),
				'hr' => array(),
				'div' => array(),
				'span' => array(),
				'img' => array(),
			)
		);
		foreach ( array( 'mark', 'cite', 'section', 'aside', 'figure', 'figcaption', 'colgroup', 'col' ) as $tag ) {
			$this->assertArrayHasKey( $tag, $allowed );
		}
		$this->assertTrue( $allowed['p']['class'] );
		$this->assertTrue( $allowed['p']['id'] );
		$this->assertTrue( $allowed['a']['href'] );
		$this->assertTrue( $allowed['img']['src'] );
		$this->assertTrue( $allowed['th']['colspan'] );
		$this->assertTrue( $allowed['div']['data-display'] );
		$this->assertTrue( $allowed['span']['data-display'] );
	}

	public function test_comment_allowlist_omits_div_span_and_img(): void {
		$allowed = $this->sanitizer->comment_allowed_html();
		$this->assertArrayNotHasKey( 'div', $allowed );
		$this->assertArrayNotHasKey( 'span', $allowed );
		$this->assertArrayNotHasKey( 'img', $allowed );
		$this->assertArrayNotHasKey( 'script', $allowed );
		$this->assertArrayHasKey( 'mark', $allowed );
		$this->assertArrayHasKey( 'table', $allowed );
		$this->assertArrayHasKey( 'cite', $allowed );
	}
}
