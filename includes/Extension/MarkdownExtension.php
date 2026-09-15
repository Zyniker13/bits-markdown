<?php

declare(strict_types=1);

namespace Bristlecone\Markdown\Extension;

use Bristlecone\Markdown\Extension\Heading\HeadingIdProcessor;
use Bristlecone\Markdown\Extension\Footnote\InlineFootnoteParser;
use Bristlecone\Markdown\Extension\Math\MathBlock;
use Bristlecone\Markdown\Extension\Math\MathBlockStartParser;
use Bristlecone\Markdown\Extension\Math\MathInline;
use Bristlecone\Markdown\Extension\Math\MathInlineParser;
use Bristlecone\Markdown\Extension\Math\MathRenderer;
use Bristlecone\Markdown\Extension\PageBreak\PageBreak;
use Bristlecone\Markdown\Extension\PageBreak\PageBreakRenderer;
use Bristlecone\Markdown\Extension\PageBreak\PageBreakStartParser;
use Bristlecone\Markdown\Extension\SupSub\Subscript;
use Bristlecone\Markdown\Extension\SupSub\SubscriptParser;
use Bristlecone\Markdown\Extension\SupSub\Superscript;
use Bristlecone\Markdown\Extension\SupSub\SuperscriptParser;
use Bristlecone\Markdown\Extension\SupSub\SupSubRenderer;
use Bristlecone\Markdown\Extension\WriterComment\WriterComment;
use Bristlecone\Markdown\Extension\WriterComment\WriterCommentRenderer;
use Bristlecone\Markdown\Extension\WriterComment\WriterCommentStartParser;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * iA Writer-aligned extras not provided by league/commonmark core extensions.
 */
final class MarkdownExtension implements ExtensionInterface {

	public function __construct( private bool $math = true ) {}

	public function register( EnvironmentBuilderInterface $environment ): void {
		$environment->addBlockStartParser( new PageBreakStartParser(), 80 );
		$environment->addBlockStartParser( new WriterCommentStartParser(), 79 );
		if ( $this->math ) {
			$environment->addBlockStartParser( new MathBlockStartParser(), 78 );
			$environment->addInlineParser( new MathInlineParser(), 70 );
		}
		$environment->addInlineParser( new InlineFootnoteParser(), 60 );
		$environment->addInlineParser( new SuperscriptParser(), 40 );
		$environment->addInlineParser( new SubscriptParser(), 39 );

		$environment->addRenderer( MathInline::class, new MathRenderer() );
		$environment->addRenderer( MathBlock::class, new MathRenderer() );
		$environment->addRenderer( PageBreak::class, new PageBreakRenderer() );
		$environment->addRenderer( WriterComment::class, new WriterCommentRenderer() );
		$environment->addRenderer( Superscript::class, new SupSubRenderer( 'sup' ) );
		$environment->addRenderer( Subscript::class, new SupSubRenderer( 'sub' ) );

		$ids = new HeadingIdProcessor();
		$environment->addEventListener( DocumentParsedEvent::class, array( $ids, 'stash' ), -90 );
		$environment->addEventListener( DocumentParsedEvent::class, array( $ids, 'restore' ), -125 );
	}
}
