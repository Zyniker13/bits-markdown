<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Extension\Math;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * iA Writer inline math: $tex$ with no interior edge spaces.
 */
final class MathInlineParser implements InlineParserInterface {

	public function getMatchDefinition(): InlineParserMatch {
		return InlineParserMatch::string( '$' )->caseSensitive();
	}

	public function parse( InlineParserContext $inlineContext ): bool {
		$cursor = $inlineContext->getCursor();

		if ( $cursor->peek() === '$' ) {
			return false;
		}

		$previous = $cursor->peek( -1 );
		if ( $previous !== null && preg_match( '/[\p{L}\p{N}]/u', $previous ) ) {
			return false;
		}

		$state   = $cursor->saveState();
		$matched = $cursor->match( '/^\$([^$\s](?:[^$\n]*[^$\s])?)\$/' );
		if ( $matched === null ) {
			return false;
		}

		$tex = substr( $matched, 1, -1 );
		if ( preg_match( '/^[\d.,]+$/', $tex ) ) {
			$cursor->restoreState( $state );
			return false;
		}

		$inlineContext->getContainer()->appendChild( new MathInline( $tex ) );

		return true;
	}
}
