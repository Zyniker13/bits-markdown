<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Tests;

use Bristlecone\BitsMarkdown\Parser;
use Bristlecone\BitsMarkdown\Tests\Support\HtmlNormalizer;
use Bristlecone\BitsMarkdown\Tests\Support\SpecExamples;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * CommonMark 0.31.2 examples through the production parser.
 *
 * Heading permalinks and the fenced-code wrapper class are stripped before
 * comparison. Remaining mismatches must be listed in production-exceptions.php.
 */
final class CommonMarkProductionSpecTest extends TestCase {

	private Parser $parser;

	/** @var array<int, string> */
	private static array $ledger = array();

	public static function setUpBeforeClass(): void {
		self::$ledger = require dirname( __DIR__ ) . '/tests/fixtures/commonmark/production-exceptions.php';
	}

	protected function setUp(): void {
		$this->parser = new Parser();
	}

	/**
	 * @return \Generator<string, array{int, string, string, string}>
	 */
	public static function specExamples(): \Generator {
		foreach ( SpecExamples::all() as $example ) {
			$label = sprintf( 'example %d (%s)', $example['number'], $example['section'] );
			yield $label => array(
				$example['number'],
				$example['section'],
				$example['input'],
				$example['output'],
			);
		}
	}

	#[DataProvider( 'specExamples' )]
	public function test_production_parser_against_spec( int $number, string $section, string $input, string $output ): void {
		$actual   = HtmlNormalizer::production(
			$this->parser->convert(
				$input,
				array(
					'highlight' => false,
					'id'        => 'spec',
				)
			)->html
		);
		$expected = HtmlNormalizer::spec( $output );

		if ( isset( self::$ledger[ $number ] ) ) {
			$reason = self::$ledger[ $number ];
			$this->assertNotSame(
				$expected,
				$actual,
				sprintf(
					'Example %d (%s) is listed as a %s deviation but now matches CommonMark. Remove it from production-exceptions.php.',
					$number,
					$section,
					$reason
				)
			);
			return;
		}

		$this->assertSame(
			$expected,
			$actual,
			sprintf(
				"Unexpected production deviation for CommonMark example %d (%s).\nIf this is an intentional extra, add it to production-exceptions.php.",
				$number,
				$section
			)
		);
	}
}
