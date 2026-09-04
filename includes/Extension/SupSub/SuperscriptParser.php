<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Extension\SupSub;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

final class SuperscriptParser implements InlineParserInterface {

	public function getMatchDefinition(): InlineParserMatch {
		return InlineParserMatch::string( '^' )->caseSensitive();
	}

	public function parse( InlineParserContext $inlineContext ): bool {
		$cursor = $inlineContext->getCursor();

		if ( $cursor->peek() === '[' ) {
			return false;
		}

		$matched = $cursor->match( '/^\^(?:([A-Za-z0-9]+(?!\^))|\(([^)]+)\)\^)/' );
		if ( $matched === null ) {
			return false;
		}

		if ( str_starts_with( $matched, '^(' ) && str_ends_with( $matched, ')^' ) ) {
			$literal = substr( $matched, 2, -2 );
		} else {
			$literal = substr( $matched, 1 );
		}

		$inlineContext->getContainer()->appendChild( new Superscript( $literal ) );

		return true;
	}
}
