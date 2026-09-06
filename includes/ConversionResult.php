<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown;

/**
 * Result of converting Markdown to HTML.
 */
final class ConversionResult {

	/**
	 * @param array<string, mixed> $front_matter
	 */
	public function __construct(
		public readonly string $html,
		public readonly array $front_matter = array(),
		public readonly bool $has_math = false,
		public readonly bool $has_code = false,
	) {}
}
