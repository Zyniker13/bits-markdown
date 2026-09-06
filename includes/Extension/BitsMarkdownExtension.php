<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Extension;

use Bristlecone\BitsMarkdown\Extension\Heading\HeadingIdProcessor;
use Bristlecone\BitsMarkdown\Extension\Footnote\InlineFootnoteParser;
use Bristlecone\BitsMarkdown\Extension\Math\MathBlock;
use Bristlecone\BitsMarkdown\Extension\Math\MathBlockStartParser;
use Bristlecone\BitsMarkdown\Extension\Math\MathInline;
use Bristlecone\BitsMarkdown\Extension\Math\MathInlineParser;
use Bristlecone\BitsMarkdown\Extension\Math\MathRenderer;
use Bristlecone\BitsMarkdown\Extension\PageBreak\PageBreak;
use Bristlecone\BitsMarkdown\Extension\PageBreak\PageBreakRenderer;
use Bristlecone\BitsMarkdown\Extension\PageBreak\PageBreakStartParser;
use Bristlecone\BitsMarkdown\Extension\SupSub\Subscript;
use Bristlecone\BitsMarkdown\Extension\SupSub\SubscriptParser;
use Bristlecone\BitsMarkdown\Extension\SupSub\Superscript;
use Bristlecone\BitsMarkdown\Extension\SupSub\SuperscriptParser;
use Bristlecone\BitsMarkdown\Extension\SupSub\SupSubRenderer;
use Bristlecone\BitsMarkdown\Extension\WriterComment\WriterComment;
use Bristlecone\BitsMarkdown\Extension\WriterComment\WriterCommentRenderer;
use Bristlecone\BitsMarkdown\Extension\WriterComment\WriterCommentStartParser;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * iA Writer-aligned extras not provided by league/commonmark core extensions.
 */
final class BitsMarkdownExtension implements ExtensionInterface {

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
