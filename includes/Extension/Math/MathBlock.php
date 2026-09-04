<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Extension\Math;

use League\CommonMark\Node\Block\AbstractBlock;

final class MathBlock extends AbstractBlock {

	public function __construct( private string $literal = '' ) {
		parent::__construct();
	}

	public function getLiteral(): string {
		return $this->literal;
	}

	public function setLiteral( string $literal ): void {
		$this->literal = $literal;
	}
}
