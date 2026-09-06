<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Extension\SupSub;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

final class SubscriptParser implements InlineParserInterface {

	public function getMatchDefinition(): InlineParserMatch {
		return InlineParserMatch::string( '~' )->caseSensitive();
	}

	public function parse( InlineParserContext $inlineContext ): bool {
		$cursor = $inlineContext->getCursor();

		if ( $cursor->peek() === '~' ) {
			return false;
		}

		$matched = $cursor->match( '/^~([A-Za-z0-9]+)(?:~|(?![A-Za-z0-9]))/' );
		if ( $matched === null ) {
			$matched = $cursor->match( '/^~([^~\n]+)~/' );
		}
		if ( $matched === null ) {
			return false;
		}

		$literal = substr( $matched, 1 );
		if ( str_ends_with( $literal, '~' ) ) {
			$literal = substr( $literal, 0, -1 );
		}

		$inlineContext->getContainer()->appendChild( new Subscript( $literal ) );

		return true;
	}
}
