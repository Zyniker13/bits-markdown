<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Extension\PageBreak;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

final class PageBreakStartParser implements BlockStartParserInterface {

	public function tryStart( Cursor $cursor, MarkdownParserStateInterface $parserState ): ?BlockStart {
		if ( $cursor->isIndented() ) {
			return BlockStart::none();
		}

		$paragraph = $parserState->getParagraphContent();
		if ( is_string( $paragraph ) && trim( $paragraph ) !== '' ) {
			return BlockStart::none();
		}

		if ( $cursor->matchInPlace( '/\G[ \t]*\+\+\+[ \t]*$/' ) === null ) {
			return BlockStart::none();
		}

		$cursor->advanceToEnd();

		return BlockStart::of( new PageBreakParser() )->at( $cursor );
	}
}
