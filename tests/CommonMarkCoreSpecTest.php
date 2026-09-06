<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Tests;

use Bristlecone\BitsMarkdown\Tests\Support\SpecExamples;
use League\CommonMark\CommonMarkConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Official CommonMark 0.31.2 examples against a core-only converter
 * (no permalinks, autolink, highlighting, or iA Writer extras).
 */
final class CommonMarkCoreSpecTest extends TestCase {

	private static CommonMarkConverter $converter;

	public static function setUpBeforeClass(): void {
		self::$converter = new CommonMarkConverter(
			array(
				'html_input'         => 'allow',
				'allow_unsafe_links' => true,
			)
		);
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
	public function test_spec_example( int $number, string $section, string $input, string $output ): void {
		unset( $number, $section );
		$actual = (string) self::$converter->convert( $input );
		$this->assertSame( $output, $actual );
	}

	public function test_spec_fixture_is_version_0_31_2(): void {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/tests/fixtures/commonmark/SOURCE' );
		$this->assertStringContainsString( SpecExamples::VERSION, $source );
		$this->assertCount( 652, SpecExamples::all() );
	}
}
