<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Renderer;

use Highlight\Highlighter;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

/**
 * Renders fenced code with highlight.php when a language is known.
 */
final class FencedCodeRenderer implements NodeRendererInterface {

	private const ALIASES = array(
		'js'        => 'javascript',
		'node'      => 'javascript',
		'ts'        => 'typescript',
		'html'      => 'xml',
		'htm'       => 'xml',
		'svg'       => 'xml',
		'yml'       => 'yaml',
		'sh'        => 'bash',
		'shell'     => 'bash',
		'zsh'       => 'bash',
		'py'        => 'python',
		'c#'        => 'cs',
		'csharp'    => 'cs',
		'c++'       => 'cpp',
		'md'        => 'markdown',
		'plaintext' => '',
		'text'      => '',
		'plain'     => '',
	);

	private Highlighter $highlighter;

	public function __construct( ?Highlighter $highlighter = null ) {
		$this->highlighter = $highlighter ?? new Highlighter();
	}

	public function render( Node $node, ChildNodeRendererInterface $childRenderer ): \Stringable {
		FencedCode::assertInstanceOf( $node );

		$attrs    = $node->data->getData( 'attributes' );
		$info     = $node->getInfoWords();
		$language = $info[0] ?? '';
		$code     = $node->getLiteral();
		$resolved = $this->resolve_language( $language );

		if ( $resolved !== '' ) {
			try {
				$highlighted = $this->highlighter->highlight( $resolved, $code );
				$attrs->append( 'class', 'hljs language-' . $highlighted->language );
				return new HtmlElement(
					'pre',
					array( 'class' => 'bits-markdown-code' ),
					new HtmlElement( 'code', $attrs->export(), $highlighted->value )
				);
			} catch ( \Throwable ) {
				// Fall through to escaped output.
			}
		}

		if ( $language !== '' ) {
			$class = str_starts_with( $language, 'language-' ) ? $language : 'language-' . $language;
			$attrs->append( 'class', $class );
		}

		return new HtmlElement(
			'pre',
			array( 'class' => 'bits-markdown-code' ),
			new HtmlElement( 'code', $attrs->export(), Xml::escape( $code ) )
		);
	}

	private function resolve_language( string $language ): string {
		$language = strtolower( ltrim( $language, '.' ) );
		if ( str_starts_with( $language, 'language-' ) ) {
			$language = substr( $language, 9 );
		}
		if ( $language === '' ) {
			return '';
		}
		if ( array_key_exists( $language, self::ALIASES ) ) {
			return self::ALIASES[ $language ];
		}
		return $language;
	}
}
