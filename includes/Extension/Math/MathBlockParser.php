<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Extension\Math;

use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

final class MathBlockParser extends AbstractBlockContinueParser {

	private MathBlock $block;

	/** @var list<string> */
	private array $lines = array();

	public function __construct( string $literal, private bool $closed ) {
		$this->block = new MathBlock( $literal );
	}

	public function getBlock(): MathBlock {
		return $this->block;
	}

	public function tryContinue( Cursor $cursor, BlockContinueParserInterface $activeBlockParser ): ?BlockContinue {
		if ( $this->closed ) {
			return BlockContinue::none();
		}

		$line = ltrim( $cursor->getLine() );
		if ( preg_match( '/^\$\$\s*$/', $line ) ) {
			$cursor->advanceToEnd();
			return BlockContinue::finished();
		}

		return BlockContinue::at( $cursor );
	}

	public function addLine( string $line ): void {
		if ( $this->closed ) {
			return;
		}
		$this->lines[] = $line;
	}

	public function closeBlock(): void {
		if ( $this->block->getLiteral() === '' && $this->lines !== array() ) {
			$this->block->setLiteral( implode( "\n", $this->lines ) );
		}
	}
}
