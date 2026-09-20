<?php

declare(strict_types=1);

namespace Bristlecone\Markdown\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Editor writing-surface contracts for 1.2.0 (no WordPress runtime).
 */
final class EditorAestheticsTest extends TestCase {

	private function plugin_root(): string {
		return dirname( __DIR__ );
	}

	public function test_version_is_feature_bump(): void {
		$plugin = file_get_contents( $this->plugin_root() . '/bristlecone-markdown.php' );
		$readme = file_get_contents( $this->plugin_root() . '/readme.txt' );

		$this->assertIsString( $plugin );
		$this->assertIsString( $readme );
		$this->assertMatchesRegularExpression( '/^\s*\*\s*Version:\s*1\.2\.0$/m', $plugin );
		$this->assertStringContainsString( "define( 'BRISTLECONE_MARKDOWN_VERSION', '1.2.0' );", $plugin );
		$this->assertMatchesRegularExpression( '/^Stable tag:\s*1\.2\.0$/m', $readme );
		$this->assertStringContainsString( '= 1.2.0 =', $readme );
	}

	public function test_source_uses_plain_text_and_placeholder(): void {
		$js = file_get_contents( $this->plugin_root() . '/assets/js/block.js' );
		$this->assertIsString( $js );
		$this->assertStringContainsString( 'wp.blockEditor.PlainText', $js );
		$this->assertStringNotContainsString( 'TextareaControl', $js );
		$this->assertStringContainsString( 'Write your _Markdown_ **here**…', $js );
		$this->assertStringContainsString( 'showingPlaceholder', $js );
		$this->assertStringContainsString( 'showingPreview', $js );
		$this->assertStringContainsString( "path: '/bristlecone-markdown/v1/preview'", $js );
		$this->assertStringContainsString( 'renderMath', $js );
		$this->assertStringContainsString( 'persistHtml', $js );
		$this->assertStringContainsString( 'compatibility', $js );
	}

	public function test_editor_css_is_borderless_and_not_flexinvoice(): void {
		$css = file_get_contents( $this->plugin_root() . '/assets/css/editor.css' );
		$this->assertIsString( $css );
		$this->assertStringContainsString( 'resize: none', $css );
		$this->assertStringContainsString( 'opacity: 0.62', $css );
		$this->assertStringContainsString( 'bristlecone-markdown-placeholder', $css );
		$this->assertStringNotContainsString( 'border: 1px solid #dcdcde', $css );
		$this->assertStringNotContainsString( 'min-height: 280px', $css );
		$this->assertStringNotContainsString( 'Newsreader', $css );
		$this->assertStringNotContainsString( '#0f5c59', $css );
		$this->assertStringNotContainsString( '#f3efe6', $css );
		$this->assertStringNotContainsString( 'jetpack', $css );
	}

	public function test_frontend_content_chrome_is_unchanged(): void {
		$css = file_get_contents( $this->plugin_root() . '/assets/css/frontend.css' );
		$this->assertIsString( $css );
		$this->assertStringContainsString( 'border: 1px solid #c3c4c7', $css );
		$this->assertStringContainsString( 'border: 1px solid #dcdcde', $css );
		$this->assertStringContainsString( '.bristlecone-markdown-toc', $css );
	}

	public function test_product_and_design_docs_are_bristlecone_owned(): void {
		$product = file_get_contents( $this->plugin_root() . '/PRODUCT.md' );
		$design  = file_get_contents( $this->plugin_root() . '/DESIGN.md' );
		$this->assertIsString( $product );
		$this->assertIsString( $design );
		$this->assertStringContainsString( 'impeccable:product-schema 1', $product );
		$this->assertStringContainsString( 'Bristlecone Markdown', $product );
		$this->assertStringContainsString( 'The Writing Canvas', $design );
		$this->assertStringContainsString( 'Do not copy FlexInvoice', $design );
		$this->assertStringContainsString( 'Do not load Newsreader', $design );
		$this->assertStringContainsString( 'ship Jetpack logos', $design );
		$this->assertStringContainsString( 'Must not import FlexInvoice', $product );
	}
}
