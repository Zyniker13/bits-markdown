<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Extension\Math;

use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;

final class MathBlockStartParser implements BlockStartParserInterface {

	public function tryStart( Cursor $cursor, MarkdownParserStateInterface $parserState ): ?BlockStart {
		if ( $cursor->isIndented() ) {
			return BlockStart::none();
		}

		$line = ltrim( $cursor->getLine() );
		if ( ! str_starts_with( $line, '$$' ) ) {
			return BlockStart::none();
		}

		if ( preg_match( '/^\$\$(.+)\$\$\s*$/', $line, $match ) ) {
			$cursor->advanceToEnd();
			return BlockStart::of( new MathBlockParser( $match[1], true ) )->at( $cursor );
		}

		if ( preg_match( '/^\$\$\s*$/', $line ) ) {
			$cursor->advanceToEnd();
			return BlockStart::of( new MathBlockParser( '', false ) )->at( $cursor );
		}

		return BlockStart::none();
	}
}
