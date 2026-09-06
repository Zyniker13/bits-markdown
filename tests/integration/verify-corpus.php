<?php

/**
 * Verify the spec corpus on a running WordPress site.
 *
 *   wp eval-file tests/integration/verify-corpus.php
 *
 * @package BITSMarkdown
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file with: wp eval-file tests/integration/verify-corpus.php\n" );
	exit( 1 );
}

$GLOBALS['bits_spec_failures'] = 0;

function bits_spec_fail( string $message ): void {
	++$GLOBALS['bits_spec_failures'];
	WP_CLI::warning( $message );
}

function bits_spec_ok( string $message ): void {
	WP_CLI::log( 'ok  ' . $message );
}

function bits_spec_post( string $slug, string $type = 'post' ): ?WP_Post {
	$found = get_posts(
		array(
			'name'           => $slug,
			'post_type'      => $type,
			'post_status'    => 'any',
			'posts_per_page' => 1,
		)
	);
	return $found[0] ?? null;
}

$kitchen = bits_spec_post( 'spec-kitchen-sink' );
if ( ! $kitchen ) {
	bits_spec_fail( 'missing spec-kitchen-sink' );
} else {
	$html = $kitchen->post_content;
	foreach ( array( 'bits-markdown-toc', 'bits-markdown-footnotes', 'bits-markdown-bibliography', 'bits-markdown-page-break', '<mark>', '<table>', 'Bob Loblaw' ) as $needle ) {
		if ( ! str_contains( $html, $needle ) ) {
			bits_spec_fail( "kitchen sink missing {$needle}" );
		}
	}
	if ( str_contains( $html, '<script>' ) ) {
		bits_spec_fail( 'kitchen sink contains <script>' );
	}
	if ( str_contains( $html, 'this writer comment must not appear' ) ) {
		bits_spec_fail( 'kitchen sink leaked writer comment' );
	}
	if ( ! get_post_meta( $kitchen->ID, '_bits_markdown', true ) ) {
		bits_spec_fail( 'kitchen sink missing _bits_markdown' );
	}
	if ( $kitchen->post_content_filtered === '' ) {
		bits_spec_fail( 'kitchen sink missing post_content_filtered' );
	}
	if ( ! str_contains( $html, 'bits-fn-' . $kitchen->ID . '-' ) ) {
		bits_spec_fail( 'kitchen sink footnote ids not namespaced to post id' );
	}
	$permalink = get_permalink( $kitchen );
	bits_spec_ok( 'kitchen sink id=' . $kitchen->ID . ' ' . $permalink );
}

$page = bits_spec_post( 'spec-kitchen-sink-page', 'page' );
if ( ! $page ) {
	bits_spec_fail( 'missing spec-kitchen-sink-page' );
} else {
	if ( ! str_contains( $page->post_content, 'bits-markdown-toc' ) ) {
		bits_spec_fail( 'page missing TOC' );
	}
	bits_spec_ok( 'page ' . get_permalink( $page ) );
}

$mapped = bits_spec_post( 'spec-ia-writer-publish' );
if ( ! $mapped ) {
	bits_spec_fail( 'missing spec-ia-writer-publish' );
} else {
	if ( 'iA Writer publish sample' !== $mapped->post_title && 'Placeholder' === $mapped->post_title ) {
		bits_spec_fail( 'YAML title was not mapped (still Placeholder)' );
	}
	$tags = wp_get_post_tags( $mapped->ID, array( 'fields' => 'names' ) );
	if ( ! in_array( 'alpha', $tags, true ) ) {
		bits_spec_fail( 'YAML tags were not mapped' );
	}
	bits_spec_ok( 'yaml mapped title=' . $mapped->post_title );
}

$title_only = bits_spec_post( 'yaml-title-only' );
if ( ! $title_only ) {
	bits_spec_fail( 'missing yaml-title-only permalink mapping' );
} else {
	if ( 'auto-draft' === $title_only->post_name ) {
		bits_spec_fail( 'yaml title-only left post_name as auto-draft' );
	}
	bits_spec_ok( 'yaml-title-only name=' . $title_only->post_name );
}

$clobber = bits_spec_post( 'spec-yaml-does-not-clobber' );
if ( ! $clobber ) {
	bits_spec_fail( 'missing spec-yaml-does-not-clobber' );
} elseif ( 'Keep Me' !== $clobber->post_title ) {
	bits_spec_fail( 'YAML title clobbered existing title: ' . $clobber->post_title );
} else {
	bits_spec_ok( 'yaml did not clobber existing title' );
}

$unsafe = bits_spec_post( 'spec-unsafe-html' );
if ( $unsafe && str_contains( $unsafe->post_content, '<script>' ) ) {
	bits_spec_fail( 'unsafe-html stored a script tag' );
} elseif ( $unsafe ) {
	bits_spec_ok( 'unsafe html stripped script' );
}

$block = bits_spec_post( 'spec-block-sample' );
if ( ! $block ) {
	bits_spec_fail( 'missing spec-block-sample' );
} elseif ( ! has_blocks( $block->post_content ) ) {
	bits_spec_fail( 'block sample is not block markup' );
} else {
	bits_spec_ok( 'block sample uses bits/markdown' );
}

$a = bits_spec_post( 'spec-footnotes-archive-a' );
$b = bits_spec_post( 'spec-footnotes-archive-b' );
if ( $a && $b ) {
	if ( ! str_contains( $a->post_content, 'bits-fn-' . $a->ID . '-' ) ) {
		bits_spec_fail( 'archive A footnote id' );
	}
	if ( ! str_contains( $b->post_content, 'bits-fn-' . $b->ID . '-' ) ) {
		bits_spec_fail( 'archive B footnote id' );
	}
	if ( $a->ID === $b->ID ) {
		bits_spec_fail( 'archive posts share an id' );
	}
	bits_spec_ok( 'archive footnote ids ' . $a->ID . ' vs ' . $b->ID );
}

$negative = bits_spec_post( 'spec-negative-ia-writer' );
if ( $negative && str_contains( $negative->post_content, 'checkbox' ) ) {
	bits_spec_fail( 'task list became a checkbox' );
} elseif ( $negative ) {
	bits_spec_ok( 'task lists remain text' );
}

$host = bits_spec_post( 'spec-comment-host' );
if ( $host ) {
	$comments = get_comments( array( 'post_id' => $host->ID, 'status' => 'all', 'number' => 20 ) );
	$found    = array();
	foreach ( $comments as $comment ) {
		$found[ (string) get_comment_meta( (int) $comment->comment_ID, '_bits_spec_comment_key', true ) ] = $comment;
	}
	if ( empty( $found['basic'] ) || ! str_contains( $found['basic']->comment_content, '<strong>' ) ) {
		bits_spec_fail( 'basic comment not converted' );
	} else {
		bits_spec_ok( 'basic comment converted' );
	}
	if ( ! empty( $found['xss'] ) && str_contains( $found['xss']->comment_content, '<script>' ) ) {
		bits_spec_fail( 'xss comment kept script' );
	} elseif ( ! empty( $found['xss'] ) ) {
		bits_spec_ok( 'xss comment stripped script' );
	}
	if ( ! empty( $found['math'] ) && str_contains( $found['math']->comment_content, 'x^2' ) ) {
		bits_spec_ok( 'math comment stored (span/div math wrappers are not in the comment KSES allowlist)' );
	}
	if ( ! empty( $found['basic'] ) && ! get_comment_meta( (int) $found['basic']->comment_ID, '_bits_markdown', true ) ) {
		bits_spec_fail( 'comment missing _bits_markdown meta' );
	}
}

$user_id = (int) get_current_user_id();
if ( $user_id <= 0 ) {
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	$user_id = $admins ? (int) $admins[0]->ID : 0;
}

if ( $kitchen && $user_id ) {
	$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $kitchen->ID );
	$request->set_param( 'context', 'edit' );
	$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	wp_set_current_user( $user_id );
	$ia = rest_do_request( $request );
	$data = $ia->get_data();
	$raw  = is_array( $data ) && isset( $data['content']['raw'] ) ? (string) $data['content']['raw'] : '';
	if ( ! str_contains( $raw, '{{TOC}}' ) && ! str_contains( $raw, 'Sincerely' ) ) {
		bits_spec_fail( 'REST iA Writer edit payload is not Markdown source' );
	} else {
		bits_spec_ok( 'REST edit without _locale returns Markdown' );
	}

	$request->set_param( '_locale', 'user' );
	$gb   = rest_do_request( $request );
	$graw = (string) ( $gb->get_data()['content']['raw'] ?? '' );
	if ( ! str_contains( $graw, 'wp:bits/markdown' ) ) {
		bits_spec_fail( 'REST Gutenberg edit payload is not a markdown block' );
	} else {
		bits_spec_ok( 'REST edit with _locale wraps bits/markdown' );
	}

	$preview = new WP_REST_Request( 'POST', '/bits-markdown/v1/preview' );
	$preview->set_param( 'markdown', '**preview** $x$' );
	$preview->set_param( 'post_id', $kitchen->ID );
	$pres = rest_do_request( $preview );
	$pdat = $pres->get_data();
	if ( empty( $pdat['html'] ) || ! str_contains( (string) $pdat['html'], '<strong>preview</strong>' ) ) {
		bits_spec_fail( 'preview endpoint did not convert markdown' );
	} else {
		bits_spec_ok( 'preview endpoint converts' );
	}
}

if ( $GLOBALS['bits_spec_failures'] > 0 ) {
	WP_CLI::error( $GLOBALS['bits_spec_failures'] . ' corpus checks failed.' );
}

WP_CLI::success( 'Corpus verification passed.' );
