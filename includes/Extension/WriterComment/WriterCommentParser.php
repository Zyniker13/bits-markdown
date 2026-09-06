<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Extension\WriterComment;

use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Cursor;

final class WriterCommentParser extends AbstractBlockContinueParser {

	private WriterComment $block;

	public function __construct() {
		$this->block = new WriterComment();
	}

	public function getBlock(): WriterComment {
		return $this->block;
	}

	public function tryContinue( Cursor $cursor, BlockContinueParserInterface $activeBlockParser ): ?BlockContinue {
		return BlockContinue::none();
	}
}
