<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Extension\WriterComment;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class WriterCommentRenderer implements NodeRendererInterface {

	public function render( Node $node, ChildNodeRendererInterface $childRenderer ): string {
		return '';
	}
}
