<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Extension\SupSub;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

final class SupSubRenderer implements NodeRendererInterface {

	public function __construct( private string $tag ) {}

	public function render( Node $node, ChildNodeRendererInterface $childRenderer ): \Stringable {
		$literal = '';
		if ( $node instanceof Superscript || $node instanceof Subscript ) {
			$literal = $node->getLiteral();
		}

		return new HtmlElement( $this->tag, $node->data->get( 'attributes' ), Xml::escape( $literal ) );
	}
}
