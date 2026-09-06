<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown;

use Bristlecone\BitsMarkdown\Extension\BitsMarkdownExtension;
use Bristlecone\BitsMarkdown\Renderer\FencedCodeRenderer;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;
use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\FrontMatter\Data\SymfonyYamlFrontMatterParser;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;
use League\CommonMark\Extension\FrontMatter\FrontMatterParser;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkProcessor;
use League\CommonMark\Extension\Highlight\HighlightExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TableOfContents\TableOfContentsExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Converts Markdown to HTML using a CommonMark environment with iA Writer-aligned extras.
 */
final class Parser {

	private Preprocessor $preprocessor;

	public function __construct( ?Preprocessor $preprocessor = null ) {
		$this->preprocessor = $preprocessor ?? new Preprocessor();
	}

	/**
	 * @param array{id?: string, highlight?: bool, math?: bool, interpolate?: bool} $context
	 */
	public function convert( string $markdown, array $context = array() ): ConversionResult {
		$id = $this->sanitize_id( (string) ( $context['id'] ?? 'p' ) );

		$front_matter = array();
		try {
			$fm_parser = new FrontMatterParser( new SymfonyYamlFrontMatterParser() );
			$parsed    = $fm_parser->parse( $markdown );
			$raw_fm    = $parsed->getFrontMatter();
			if ( is_array( $raw_fm ) ) {
				$front_matter = $raw_fm;
			}
			$markdown = $parsed->getContent();
		} catch ( InvalidFrontMatterException ) {
			// Keep the original document if YAML is not actually front matter.
		}

		if ( ( $context['interpolate'] ?? true ) && $front_matter !== array() ) {
			$markdown = $this->interpolate( $markdown, $front_matter );
		}

		$preprocessed = $this->preprocessor->process( $markdown );
		$markdown     = $preprocessed['markdown'];

		$config = array(
			'html_input'          => 'allow',
			'allow_unsafe_links'  => false,
			'max_nesting_level'   => 100,
			'heading_permalink'   => array(
				'min_heading_level'   => 1,
				'max_heading_level'   => 6,
				'insert'              => HeadingPermalinkProcessor::INSERT_BEFORE,
				'id_prefix'           => '',
				'fragment_prefix'     => '',
				'apply_id_to_heading' => true,
				'html_class'          => 'bits-markdown-heading-permalink',
				'symbol'              => '#',
				'title'               => 'Permalink',
				'aria_hidden'         => true,
			),
			'table_of_contents'   => array(
				'position'          => 'placeholder',
				'placeholder'       => '{{TOC}}',
				'html_class'        => 'bits-markdown-toc',
				'min_heading_level' => 1,
				'max_heading_level' => 6,
				'style'             => 'bullet',
				'normalize'         => 'relative',
			),
			'footnote'            => array(
				'backref_class'           => 'bits-markdown-footnote-backref',
				'container_class'         => 'bits-markdown-footnotes',
				'ref_class'               => 'bits-markdown-footnote-ref',
				'footnote_class'          => 'bits-markdown-footnote',
				'ref_id_prefix'           => 'bits-fnref-' . $id . '-',
				'footnote_id_prefix'      => 'bits-fn-' . $id . '-',
				'enable_inline_footnotes' => true,
			),
			'disallowed_raw_html' => array(
				'disallowed_tags' => array(
					'title',
					'textarea',
					'style',
					'xmp',
					'iframe',
					'noembed',
					'noframes',
					'script',
					'plaintext',
					'form',
					'input',
					'button',
					'select',
					'option',
					'object',
					'embed',
					'link',
					'meta',
					'base',
				),
			),
			'slug_normalizer'     => array(
				'max_length' => 80,
			),
		);

		$environment = new Environment( $config );
		$environment->addExtension( new CommonMarkCoreExtension() );
		$environment->addExtension( new AutolinkExtension() );
		$environment->addExtension( new DisallowedRawHtmlExtension() );
		$environment->addExtension( new StrikethroughExtension() );
		$environment->addExtension( new TableExtension() );
		$environment->addExtension( new HighlightExtension() );
		$environment->addExtension( new AttributesExtension() );
		$environment->addExtension( new DescriptionListExtension() );
		$environment->addExtension( new FootnoteExtension() );
		$environment->addExtension( new HeadingPermalinkExtension() );
		$environment->addExtension( new TableOfContentsExtension() );
		$environment->addExtension( new BitsMarkdownExtension( (bool) ( $context['math'] ?? true ) ) );

		if ( $context['highlight'] ?? true ) {
			$environment->addRenderer( FencedCode::class, new FencedCodeRenderer() );
		}

		$converter = new MarkdownConverter( $environment );
		$html      = trim( (string) $converter->convert( $markdown ) );

		return new ConversionResult(
			$html,
			$front_matter,
			str_contains( $html, 'bits-markdown-math' ),
			str_contains( $html, 'hljs' ) || str_contains( $html, '<pre>' ),
		);
	}

	/**
	 * @param array<string, mixed> $front_matter
	 */
	public function interpolate( string $markdown, array $front_matter ): string {
		$map = array();
		foreach ( $front_matter as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$map[ mb_strtolower( (string) $key, 'UTF-8' ) ] = (string) $value;
		}

		return (string) preg_replace_callback(
			'/\[%([A-Za-z0-9 _-]+)\]/',
			static function ( array $match ) use ( $map ): string {
				$lookup = mb_strtolower( trim( $match[1] ), 'UTF-8' );
				return $map[ $lookup ] ?? $match[0];
			},
			$markdown
		);
	}

	private function sanitize_id( string $id ): string {
		$clean = preg_replace( '/[^a-zA-Z0-9_-]/', '', $id ) ?? '';
		return $clean !== '' ? $clean : 'p';
	}
}
