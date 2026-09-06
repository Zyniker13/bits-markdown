<?php

declare(strict_types=1);

/**
 * CommonMark 0.31.2 examples (1-based) that the production Parser is expected
 * to change because extras are enabled. A ledger entry that starts matching
 * spec HTML must be removed from this list.
 *
 * @return array<int, string>
 */
return array(
	44  => 'page_break',
	96  => 'yaml_front_matter',
	170 => 'disallowed_raw_html',
	171 => 'disallowed_raw_html',
	172 => 'disallowed_raw_html',
	173 => 'disallowed_raw_html',
	176 => 'disallowed_raw_html',
	178 => 'disallowed_raw_html',
	214 => 'heading_crossref',
	608 => 'autolink',
	611 => 'autolink',
	612 => 'autolink',
	646 => 'heading_crossref',
);
