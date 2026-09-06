<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Extension\WriterComment;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

/**
 * iA Writer unpublished comments: a line beginning with //.
 */
final class WriterCommentStartParser implements BlockStartParserInterface {

	public function tryStart( Cursor $cursor, MarkdownParserStateInterface $parserState ): ?BlockStart {
		if ( $cursor->isIndented() ) {
			return BlockStart::none();
		}

		if ( $cursor->matchInPlace( '/\G\/\//' ) === null ) {
			return BlockStart::none();
		}

		$cursor->advanceToEnd();

		return BlockStart::of( new WriterCommentParser() )->at( $cursor );
	}
}
