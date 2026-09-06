<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Extension\Math;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

final class MathRenderer implements NodeRendererInterface {

	public function render( Node $node, ChildNodeRendererInterface $childRenderer ): \Stringable {
		$display = $node instanceof MathBlock;
		$literal = '';
		if ( $node instanceof MathBlock || $node instanceof MathInline ) {
			$literal = $node->getLiteral();
		}

		return new HtmlElement(
			$display ? 'div' : 'span',
			array(
				'class'        => $display ? 'bits-markdown-math bits-markdown-math-display' : 'bits-markdown-math bits-markdown-math-inline',
				'data-display' => $display ? 'true' : 'false',
			),
			Xml::escape( $literal )
		);
	}
}
