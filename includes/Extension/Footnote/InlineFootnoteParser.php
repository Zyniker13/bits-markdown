<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Extension\Footnote;

use League\CommonMark\Extension\Footnote\Node\FootnoteRef;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use League\CommonMark\Reference\Reference;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;

/**
 * iA Writer inline footnotes: [^The footnote itself.]
 *
 * Named markers such as [^1] are left to the stock footnote parser.
 */
final class InlineFootnoteParser implements InlineParserInterface, ConfigurationAwareInterface {

	private ConfigurationInterface $config;

	public function getMatchDefinition(): InlineParserMatch {
		return InlineParserMatch::regex( '\[\^([^\]]+?)\]' );
	}

	public function parse( InlineParserContext $inlineContext ): bool {
		[ $label ] = $inlineContext->getSubMatches();

		if ( ! preg_match( '/\s/', $label ) ) {
			return false;
		}

		$inlineContext->getCursor()->advanceBy( $inlineContext->getFullMatchLength() );

		$slug      = strtolower( preg_replace( '/[^a-zA-Z0-9]+/', '-', $label ) ?? $label );
		$slug      = trim( $slug, '-' );
		$slug      = $slug !== '' ? substr( $slug, 0, 24 ) : 'note';
		$reference = new Reference(
			$slug,
			'#' . $this->config->get( 'footnote/footnote_id_prefix' ) . $slug,
			$label
		);

		$inlineContext->getContainer()->appendChild( new FootnoteRef( $reference, $label ) );

		return true;
	}

	public function setConfiguration( ConfigurationInterface $configuration ): void {
		$this->config = $configuration;
	}
}
