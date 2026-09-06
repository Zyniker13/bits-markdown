<?php

/**
 * Corpus manifest for the live WordPress test instance.
 *
 * @return array{
 *   posts: list<array{type: string, slug: string, title: string, file: string, mode?: string, status?: string}>,
 *   comments: list<array{key: string, file: string}>
 * }
 */
return array(
	'posts'    => array(
		array(
			'type'  => 'post',
			'slug'  => 'spec-kitchen-sink',
			'title' => 'Spec kitchen sink',
			'file'  => 'kitchen-sink.md',
		),
		array(
			'type'  => 'page',
			'slug'  => 'spec-kitchen-sink-page',
			'title' => 'Spec kitchen sink page',
			'file'  => 'kitchen-sink-page.md',
		),
		array(
			'type'  => 'post',
			'slug'  => 'spec-commonmark-torture',
			'title' => 'CommonMark torture',
			'file'  => 'commonmark-torture.md',
		),
		array(
			'type'  => 'post',
			'slug'  => 'spec-ia-writer-publish',
			'title' => '',
			'file'  => 'ia-writer-publish.md',
		),
		array(
			'type'  => 'post',
			'slug'  => 'spec-unicode',
			'title' => 'Unicode',
			'file'  => 'unicode.md',
		),
		array(
			'type'  => 'post',
			'slug'  => 'spec-negative-ia-writer',
			'title' => 'Negative iA Writer',
			'file'  => 'negative-ia-writer.md',
		),
		array(
			'type'  => 'post',
			'slug'  => 'spec-unsafe-html',
			'title' => 'Unsafe HTML',
			'file'  => 'unsafe-html.md',
		),
		array(
			'type'  => 'post',
			'slug'  => 'spec-footnotes',
			'title' => 'Footnotes',
			'file'  => 'footnotes.md',
		),
		array(
			'type'  => 'post',
			'slug'  => 'spec-footnotes-archive-a',
			'title' => 'Footnotes archive A',
			'file'  => 'footnotes-archive-a.md',
		),
		array(
			'type'  => 'post',
			'slug'  => 'spec-footnotes-archive-b',
			'title' => 'Footnotes archive B',
			'file'  => 'footnotes-archive-b.md',
		),
		array(
			'type'  => 'post',
			'slug'  => 'spec-empty',
			'title' => 'Empty',
			'file'  => 'empty.md',
		),
		array(
			'type'  => 'post',
			'slug'  => 'spec-interpolate-in-fence',
			'title' => 'Interpolate in fence',
			'file'  => 'interpolate-in-fence.md',
		),
		array(
			'type'  => 'post',
			'slug'  => 'spec-setext-crossref',
			'title' => 'Setext cross-reference',
			'file'  => 'setext-crossref.md',
		),
		array(
			'type'  => 'post',
			'slug'  => 'spec-block-sample',
			'title' => 'Markdown block sample',
			'file'  => 'block-sample.md',
			'mode'  => 'block',
		),
	),
	'comments' => array(
		array(
			'key'  => 'basic',
			'file' => 'basic.md',
		),
		array(
			'key'  => 'table-footnote',
			'file' => 'table-footnote.md',
		),
		array(
			'key'  => 'xss',
			'file' => 'xss.md',
		),
		array(
			'key'  => 'math',
			'file' => 'math.md',
		),
	),
);
