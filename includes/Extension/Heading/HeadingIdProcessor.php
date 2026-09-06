<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Extension\Heading;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Node\NodeIterator;

/**
 * Keep explicit {#id} attributes on headings. HeadingPermalinkProcessor
 * otherwise overwrites them with a slug of the visible text, which also
 * desynchronizes TOC links.
 */
final class HeadingIdProcessor {

	public function stash( DocumentParsedEvent $event ): void {
		foreach ( $event->getDocument()->iterator( NodeIterator::FLAG_BLOCKS_ONLY ) as $node ) {
			if ( ! $node instanceof Heading ) {
				continue;
			}
			$id = $node->data->get( 'attributes/id', null );
			if ( is_string( $id ) && $id !== '' ) {
				$node->data->set( 'bits/explicit_id', $id );
			}
		}
	}

	public function restore( DocumentParsedEvent $event ): void {
		foreach ( $event->getDocument()->iterator( NodeIterator::FLAG_BLOCKS_ONLY ) as $node ) {
			if ( ! $node instanceof Heading ) {
				continue;
			}
			$id = $node->data->get( 'bits/explicit_id', null );
			if ( ! is_string( $id ) || $id === '' ) {
				continue;
			}

			$node->data->set( 'attributes/id', $id );

			foreach ( $node->children() as $child ) {
				if ( $child instanceof HeadingPermalink ) {
					$child->replaceWith( new HeadingPermalink( $id ) );
					break;
				}
			}
		}
	}
}
