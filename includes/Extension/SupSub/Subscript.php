<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Extension\SupSub;

use League\CommonMark\Node\Inline\AbstractInline;

final class Subscript extends AbstractInline {

	public function __construct( private string $literal ) {
		parent::__construct();
	}

	public function getLiteral(): string {
		return $this->literal;
	}
}
