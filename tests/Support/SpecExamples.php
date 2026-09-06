<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown\Tests\Support;

use League\CommonMark\Util\SpecReader;

/**
 * CommonMark 0.31.2 examples. SpecReader numbers are 0-based; we expose 1-based
 * numbers to match https://spec.commonmark.org/0.31.2/
 */
final class SpecExamples {

	public const VERSION = '0.31.2';

	public static function path(): string {
		return dirname( __DIR__ ) . '/fixtures/commonmark/spec.txt';
	}

	/**
	 * @return list<array{number: int, section: string, input: string, output: string, type: string}>
	 */
	public static function all(): array {
		$examples = array();
		foreach ( SpecReader::readFile( self::path() ) as $example ) {
			$examples[] = array(
				'number'  => $example['number'] + 1,
				'section' => $example['section'],
				'input'   => $example['input'],
				'output'  => $example['output'],
				'type'    => $example['type'],
			);
		}

		return $examples;
	}
}
