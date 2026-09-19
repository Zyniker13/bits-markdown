<?php

declare(strict_types=1);

namespace Bristlecone\Markdown;

/**
 * One Gutenberg block name that Bristlecone can alias or convert.
 */
final class BlockAlias {

	public const ORIGIN_BUILTIN = 'builtin';
	public const ORIGIN_CUSTOM  = 'custom';

	public function __construct(
		public readonly string $name,
		public readonly ?string $attribute,
		public readonly string $origin = self::ORIGIN_CUSTOM,
	) {}

	public function line(): string {
		return null !== $this->attribute && $this->attribute !== ''
			? $this->name . '|' . $this->attribute
			: $this->name;
	}
}
