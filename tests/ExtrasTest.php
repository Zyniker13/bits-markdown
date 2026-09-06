<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Tests;

use Bristlecone\BitsMarkdown\Parser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExtrasTest extends TestCase {

	private Parser $parser;

	protected function setUp(): void {
		$this->parser = new Parser();
	}

	private function html( string $markdown, array $context = array() ): string {
		return $this->parser->convert( $markdown, $context )->html;
	}

	public function test_strikethrough(): void {
		$html = $this->html( '~~a~~ and ~~a ~~ b~~' );
		$this->assertStringContainsString( '<del>a</del>', $html );
		$this->assertStringContainsString( '<del>a ~~ b</del>', $html );
		$this->assertStringNotContainsString( '<del>unmatched</del>', $this->html( '~~unmatched' ) );
	}

	public function test_highlight(): void {
		$html = $this->html( '==a== and ==**x**==' );
		$this->assertStringContainsString( '<mark>a</mark>', $html );
		$this->assertStringContainsString( '<mark><strong>x</strong></mark>', $html );
		$this->assertStringNotContainsString( '<mark>', $this->html( '=== triple' ) );
	}

	public function test_table_alignment_and_uneven_rows(): void {
		$markdown = <<<MD
| Left | Center | Right |
| :--- | :----: | ----: |
| a | b |
| 1 | 2 | 3 | extra |
MD;
		$html = $this->html( $markdown );
		$this->assertStringContainsString( '<table>', $html );
		$this->assertMatchesRegularExpression( '/<th[^>]*align="left"/', $html );
		$this->assertMatchesRegularExpression( '/<th[^>]*align="center"/', $html );
		$this->assertMatchesRegularExpression( '/<th[^>]*align="right"/', $html );
	}

	public function test_table_with_escaped_pipe(): void {
		$html = $this->html( "| A |\n| --- |\n| a \\| b |" );
		$this->assertStringContainsString( '<table>', $html );
	}

	public function test_description_list_does_not_steal_colon_in_prose(): void {
		$html = $this->html( "Note: this is prose.\n\nTerm\n: definition\n" );
		$this->assertStringContainsString( 'Note: this is prose', $html );
		$this->assertTrue(
			str_contains( $html, '<dl>' ) || str_contains( $html, '<dd>' ),
			'Description list term/definition should render as a description list'
		);
	}

	public function test_attributes_on_heading(): void {
		$html = $this->html( "# Title {#custom-id .hero}\n" );
		$this->assertStringContainsString( 'id="custom-id"', $html );
	}

	public function test_named_and_inline_footnotes(): void {
		$html = $this->html(
			"Hello[^1] and more[^this is inline.]\n\n[^1]: Named footnote.\n",
			array( 'id' => '12' )
		);
		$this->assertStringContainsString( 'bits-fn-12-', $html );
		$this->assertStringContainsString( 'bits-fnref-12-', $html );
		$this->assertStringContainsString( 'Named footnote', $html );
		$this->assertStringContainsString( 'this is inline', $html );
		$this->assertStringContainsString( 'bits-markdown-footnotes', $html );
	}

	public function test_nospace_footnote_is_named_not_inline(): void {
		$html = $this->html( "See[^note]\n\n[^note]: Named.\n", array( 'id' => '9' ) );
		$this->assertStringContainsString( 'Named', $html );
		$this->assertStringContainsString( 'bits-fn-9-', $html );
	}

	public function test_missing_footnote_definition_does_not_fatal(): void {
		$html = $this->html( 'Dangling[^missing]', array( 'id' => '1' ) );
		$this->assertNotSame( '', $html );
	}

	public function test_heading_cross_reference_and_label(): void {
		$html = $this->html( "# My Level 1 Header [My Label]\n\nSee [My Level 1 Header][] and [My Label][].\n" );
		$this->assertStringContainsString( 'href="#my-level-1-header"', $html );
		$this->assertStringNotContainsString( '[My Label]', $html );
	}

	public function test_duplicate_headings_get_unique_slugs(): void {
		$html = $this->html( "## Dup\n\n## Dup\n" );
		$this->assertStringContainsString( 'id="dup"', $html );
		$this->assertStringContainsString( 'id="dup-1"', $html );
	}

	public function test_explicit_heading_id_is_kept(): void {
		$html = $this->html( "# Title {#explicit}\n\nSee [Title][].\n" );
		$this->assertStringContainsString( 'id="explicit"', $html );
		$this->assertStringContainsString( 'href="#explicit"', $html );
	}

	public function test_toc_placeholder(): void {
		$html = $this->html( "{{TOC}}\n\n## One\n\n### Two\n" );
		$this->assertStringContainsString( 'bits-markdown-toc', $html );
		$this->assertStringContainsString( '#one', $html );
		$this->assertStringContainsString( '#two', $html );
	}

	public function test_toc_inside_fence_is_literal(): void {
		$html = $this->html( "```\n{{TOC}}\n```\n\n## One\n" );
		$this->assertStringContainsString( '{{TOC}}', $html );
	}

	public function test_toc_lowercase_is_literal(): void {
		$html = $this->html( "{{toc}}\n\n## One\n" );
		$this->assertStringContainsString( '{{toc}}', $html );
		$this->assertStringNotContainsString( 'bits-markdown-toc', $html );
	}

	public function test_front_matter_interpolation_is_case_insensitive(): void {
		$result = $this->parser->convert( "---\nMe: Bob Loblaw\ntitle: Hello\n---\n\n[%me] [%Me] [% missing]\n" );
		$this->assertSame( 'Hello', $result->front_matter['title'] ?? null );
		$this->assertStringContainsString( 'Bob Loblaw', $result->html );
		$this->assertStringContainsString( '[% missing]', $result->html );
	}

	public function test_interpolation_inside_fences_is_current_behavior(): void {
		$html = $this->html( "---\nMe: Bob\n---\n\n```\n[%me]\n```\n" );
		$this->assertStringContainsString( 'Bob', $html );
		$this->assertStringNotContainsString( '[%me]', $html );
	}

	public function test_invalid_yaml_is_kept_as_document(): void {
		$markdown = "---\nthis: [unterminated\n---\n\nVisible\n";
		$result   = $this->parser->convert( $markdown );
		$this->assertSame( array(), $result->front_matter );
		$this->assertStringContainsString( 'Visible', $result->html );
	}

	public function test_nested_yaml_is_not_interpolated(): void {
		$html = $this->html( "---\nparent:\n  child: nope\n---\n\n[%parent] [%child]\n" );
		$this->assertStringContainsString( '[%parent]', $html );
		$this->assertStringContainsString( '[%child]', $html );
	}

	public function test_inline_math_rules(): void {
		$html = $this->html( 'Area $x^2$ and $ x$ and $x $ and a$x$ and $12.00$ and $1,234.56$.' );
		$this->assertStringContainsString( 'bits-markdown-math-inline', $html );
		$this->assertStringContainsString( 'x^2', $html );
		$this->assertStringNotContainsString( 'data-display="false"> x', $html );
		$this->assertStringContainsString( '$12.00$', $html );
		$this->assertStringContainsString( '$1,234.56$', $html );
	}

	public function test_display_math_fenced_and_one_line(): void {
		$block = $this->html( "$$\nE=mc^2\n$$" );
		$this->assertStringContainsString( 'bits-markdown-math-display', $block );
		$this->assertStringContainsString( 'E=mc^2', $block );
		$one = $this->html( '$$E=mc^2$$' );
		$this->assertStringContainsString( 'bits-markdown-math-display', $one );
	}

	public function test_math_disabled_context(): void {
		$html = $this->html( '$x^2$', array( 'math' => false ) );
		$this->assertStringNotContainsString( 'bits-markdown-math', $html );
	}

	public function test_superscript_and_subscript(): void {
		$html = $this->html( '100m^2 and x~z and y^(a+b)^ and x~long~.' );
		$this->assertStringContainsString( '<sup>2</sup>', $html );
		$this->assertStringContainsString( '<sub>z</sub>', $html );
		$this->assertStringContainsString( '<sup>a+b</sup>', $html );
		$this->assertStringContainsString( '<sub>long</sub>', $html );
	}

	public function test_caret_before_bracket_is_not_superscript(): void {
		$html = $this->html( "See[^1]\n\n[^1]: Note.\n" );
		$this->assertStringNotContainsString( '<sup>1</sup>', $html );
		$this->assertStringContainsString( 'bits-markdown-footnote', $html );
	}

	public function test_math_does_not_grow_extra_sup_inside_tex(): void {
		$html = $this->html( '$x^2$' );
		$this->assertStringContainsString( 'bits-markdown-math', $html );
		$this->assertStringNotContainsString( '<sup>', $html );
	}

	public function test_page_break_rules(): void {
		$html = $this->html( "Visible\n\n+++\n\nAfter" );
		$this->assertStringContainsString( 'bits-markdown-page-break', $html );
		$this->assertStringContainsString( 'Visible', $html );
		$this->assertStringContainsString( 'After', $html );
		$this->assertStringNotContainsString( 'page-break', $this->html( '++++' ) );
		$this->assertStringNotContainsString( 'page-break', $this->html( '    +++' ) );
	}

	public function test_writer_comment_stripped_but_url_kept(): void {
		$html = $this->html( "Visible\n\n// secret comment\n\nVisit https://example.com/path\n" );
		$this->assertStringNotContainsString( 'secret comment', $html );
		$this->assertStringContainsString( 'Visible', $html );
		$this->assertStringContainsString( 'https://example.com/path', $html );
	}

	public function test_writer_comment_inside_fence_is_literal(): void {
		$html = $this->html( "```\n// secret\n```\n" );
		$this->assertStringContainsString( '// secret', $html );
	}

	public function test_citations(): void {
		$html = $this->html( "A claim[p. 23][#Doe:2006] and [][#Doe:2006].\n\n[#Doe:2006]: John Doe. Some Big Fancy Book.\n" );
		$this->assertStringContainsString( 'bits-markdown-citation', $html );
		$this->assertStringContainsString( 'p. 23', $html );
		$this->assertStringContainsString( 'bits-markdown-bibliography', $html );
		$this->assertStringContainsString( 'John Doe', $html );
		$this->assertStringContainsString( 'bits-ref-doe-2006', $html );
	}

	public function test_unknown_citation_left_as_markdown(): void {
		$html = $this->html( 'A claim[p. 1][#Missing].' );
		$this->assertStringNotContainsString( 'bits-markdown-citation', $html );
		$this->assertStringContainsString( '[#Missing]', $html );
	}

	public function test_citation_inside_fence_is_literal(): void {
		$html = $this->html( "```\n[p. 1][#Doe:2006]\n```\n\n[#Doe:2006]: Book.\n" );
		$this->assertStringContainsString( '[p. 1][#Doe:2006]', $html );
	}

	public function test_citation_bibliography_escapes_html(): void {
		$html = $this->html( "See[p. 1][#X].\n\n[#X]: <script>alert(1)</script>\n" );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_syntax_highlighting_on_and_off(): void {
		$on  = $this->html( "```php\necho 1;\n```\n", array( 'highlight' => true ) );
		$off = $this->html( "```php\necho 1;\n```\n", array( 'highlight' => false ) );
		$this->assertStringContainsString( 'hljs', $on );
		$this->assertStringNotContainsString( 'hljs', $off );
		$this->assertStringContainsString( '<pre', $off );
	}

	public function test_unknown_language_is_escaped_not_highlighted(): void {
		$html = $this->html( "```not-a-real-language-xyz\n<\n```\n" );
		$this->assertStringContainsString( '&lt;', $html );
		$this->assertStringNotContainsString( 'hljs', $html );
	}

	public function test_language_aliases(): void {
		$html = $this->html( "```js\nconst x = 1;\n```\n" );
		$this->assertStringContainsString( 'hljs', $html );
		$this->assertStringContainsString( 'language-javascript', $html );
	}

	public function test_unsafe_javascript_links_are_not_kept(): void {
		$html = $this->html( '[xss](javascript:alert(1))' );
		$this->assertStringNotContainsString( 'javascript:alert', $html );
	}

	public function test_script_html_is_disallowed(): void {
		$html = $this->html( 'Hello <span class="ok">there</span> and <script>alert(1)</script>' );
		$this->assertStringContainsString( '<span class="ok">there</span>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_task_lists_are_not_checkboxes(): void {
		$html = $this->html( "- [ ] write\n- [x] done\n" );
		$this->assertStringNotContainsString( 'checkbox', $html );
		$this->assertStringNotContainsString( 'type="checkbox"', $html );
		$this->assertStringContainsString( '[ ]', $html );
		$this->assertStringContainsString( '[x]', $html );
	}

	public function test_content_block_paths_are_literal(): void {
		$html = $this->html( "/includes/chapter.md\n" );
		$this->assertStringContainsString( '/includes/chapter.md', $html );
	}

	public function test_setext_heading_does_not_get_label_rewrite(): void {
		$html = $this->html( "Setext title [My Setext Label]\n==============================\n\nSee [My Setext Label][].\n" );
		$this->assertStringContainsString( '<h1', $html );
		$this->assertStringContainsString( '[My Setext Label]', $html );
	}

	public function test_author_link_definition_wins_over_heading_crossref(): void {
		$html = $this->html( "# Title\n\n[Title]: https://example.com/override\n\nSee [Title][].\n" );
		$this->assertStringContainsString( 'https://example.com/override', $html );
	}

	public function test_conversion_result_flags(): void {
		$result = $this->parser->convert( '$$a$$ and `code`' );
		$this->assertTrue( $result->has_math );
	}

	public function test_atx_content_hash_is_kept(): void {
		$html = $this->html( "# foo#\n" );
		$this->assertStringContainsString( '>foo#</h1>', $html );
	}

	/**
	 * @return \Generator<string, array{string, list<string>}>
	 */
	public static function highlightAliasProvider(): \Generator {
		foreach ( array( 'js', 'ts', 'yml', 'sh', 'py', 'c#', 'md' ) as $lang ) {
			yield $lang => array( $lang, array( 'hljs', '<pre' ) );
		}
	}

	#[DataProvider( 'highlightAliasProvider' )]
	public function test_fenced_language_aliases_do_not_fatal( string $lang, array $contains ): void {
		$html = $this->html( "```{$lang}\nvalue\n```\n" );
		foreach ( $contains as $needle ) {
			$this->assertStringContainsString( $needle, $html );
		}
	}
}
