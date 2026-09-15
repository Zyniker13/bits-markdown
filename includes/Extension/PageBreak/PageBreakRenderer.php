<?php

declare(strict_types=1);

namespace Bristlecone\Markdown\Extension\PageBreak;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

final class PageBreakRenderer implements NodeRendererInterface {

	public function render( Node $node, ChildNodeRendererInterface $childRenderer ): \Stringable {
		PageBreak::assertInstanceOf( $node );

		return new HtmlElement(
			'hr',
			array(
				'class' => 'bristlecone-markdown-page-break',
			),
			'',
			true
		);
	}
}
