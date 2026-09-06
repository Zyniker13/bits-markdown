<?php

/**
 * Idempotent publisher for the BITS Markdown spec corpus.
 *
 * Run on a WordPress site that should remain the primary test instance:
 *
 *   wp eval-file tests/integration/publish-corpus.php
 *
 * @package BITSMarkdown
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file with: wp eval-file tests/integration/publish-corpus.php\n" );
	exit( 1 );
}

$plugin_root = dirname( __DIR__, 2 );
$docs        = $plugin_root . '/tests/fixtures/documents';
$comments    = $plugin_root . '/tests/fixtures/comments';
$manifest    = require __DIR__ . '/manifest.php';

if ( ! is_dir( $docs ) ) {
	WP_CLI::error( 'Fixture directory missing: ' . $docs );
}

register_post_type(
	'bits_spec',
	array(
		'label'  => 'Spec CPT',
		'public' => true,
	)
);

$results = array();

foreach ( $manifest['posts'] as $item ) {
	$markdown = bits_spec_read( $docs . '/' . $item['file'] );
	$id       = bits_spec_upsert(
		$item['type'],
		$item['slug'],
		$item['title'],
		$markdown,
		$item['mode'] ?? 'document',
		$item['status'] ?? 'publish'
	);
	$results[] = array(
		'id'   => $id,
		'slug' => $item['slug'],
		'type' => $item['type'],
		'mode' => $item['mode'] ?? 'document',
	);
	WP_CLI::log( sprintf( 'upsert %s %s => %d', $item['type'], $item['slug'], $id ) );
}

$title_only_slug = 'yaml-title-only';
$title_only_id   = bits_spec_find( 'post', $title_only_slug );
if ( ! $title_only_id ) {
	$title_only_id = bits_spec_insert_auto_draft( bits_spec_read( $docs . '/yaml-title-only.md' ) );
	bits_spec_publish_existing( $title_only_id );
}
$results[] = array(
	'id'   => $title_only_id,
	'slug' => get_post_field( 'post_name', $title_only_id ),
	'type' => 'post',
	'mode' => 'yaml-title-only',
);
WP_CLI::log( 'yaml-title-only => ' . $title_only_id . ' name=' . get_post_field( 'post_name', $title_only_id ) );

$clobber_id = bits_spec_upsert( 'post', 'spec-yaml-does-not-clobber', 'Keep Me', bits_spec_read( $docs . '/yaml-clobber.md' ), 'document', 'publish' );
$clobber    = get_post( $clobber_id );
WP_CLI::log( 'yaml-does-not-clobber title=' . ( $clobber ? $clobber->post_title : '' ) );

$comment_post = bits_spec_upsert( 'post', 'spec-comment-host', 'Comment host', "Host for spec comments.\n\nLeave a note.", 'document', 'publish' );
wp_update_post(
	array(
		'ID'             => $comment_post,
		'comment_status' => 'open',
	)
);

add_filter( 'comment_flood_filter', '__return_false', 99 );
add_filter( 'duplicate_comment_id', '__return_false' );

foreach ( $manifest['comments'] as $item ) {
	$content = bits_spec_read( $comments . '/' . $item['file'] );
	$cid     = bits_spec_upsert_comment( $comment_post, $item['key'], $content );
	WP_CLI::log( sprintf( 'comment %s => %d', $item['key'], $cid ) );
}

$settings = get_option( 'bits_markdown_settings', array() );
update_option(
	'bits_markdown_settings',
	array(
		'post_types'          => array(
			'post'      => true,
			'page'      => true,
			'bits_spec' => true,
		),
		'comments'            => true,
		'syntax_highlighting' => true,
		'math'                => true,
	)
);

$cpt_id = bits_spec_upsert( 'bits_spec', 'spec-custom-type', 'Spec CPT', "# CPT heading\n\n**Hello CPT.**\n", 'document', 'publish' );
WP_CLI::log( 'cpt => ' . $cpt_id );

if ( is_array( $settings ) && $settings !== array() ) {
	update_option( 'bits_markdown_settings', $settings );
}

file_put_contents( '/tmp/bits-markdown-spec-corpus.json', wp_json_encode( $results, JSON_PRETTY_PRINT ) );
WP_CLI::success( 'Published ' . count( $results ) . ' corpus items.' );

/**
 * @return string
 */
function bits_spec_read( string $path ): string {
	$contents = file_get_contents( $path );
	if ( false === $contents ) {
		WP_CLI::error( 'Missing fixture: ' . $path );
	}
	return $contents;
}

/**
 * @return int
 */
function bits_spec_upsert( string $type, string $slug, string $title, string $markdown, string $mode, string $status ): int {
	$existing = bits_spec_find( $type, $slug );
	$content  = $markdown;
	if ( 'block' === $mode ) {
		$content = \Bristlecone\BitsMarkdown\Block::serialize_source( $markdown );
	}

	$data = array(
		'post_type'    => $type,
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => $content,
		'post_status'  => $status,
	);

	if ( $existing ) {
		$data['ID'] = $existing;
		$id         = wp_update_post( wp_slash( $data ), true );
	} else {
		$id = wp_insert_post( wp_slash( $data ), true );
	}

	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}

	return (int) $id;
}

function bits_spec_find( string $type, string $slug ): int {
	$found = get_posts(
		array(
			'name'           => $slug,
			'post_type'      => $type,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	return isset( $found[0] ) ? (int) $found[0] : 0;
}

function bits_spec_insert_auto_draft( string $markdown ): int {
	$id = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => 'post',
				'post_status'  => 'auto-draft',
				'post_title'   => '',
				'post_content' => $markdown,
			)
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}
	return (int) $id;
}

function bits_spec_publish_existing( int $id ): void {
	$post = get_post( $id );
	if ( ! $post ) {
		return;
	}
	wp_update_post(
		wp_slash(
			array(
				'ID'           => $id,
				'post_status'  => 'publish',
				'post_content' => $post->post_content_filtered !== '' ? $post->post_content_filtered : $post->post_content,
			)
		)
	);
}

function bits_spec_upsert_comment( int $post_id, string $key, string $content ): int {
	$existing = get_comments(
		array(
			'post_id'    => $post_id,
			'meta_key'   => '_bits_spec_comment_key',
			'meta_value' => $key,
			'number'     => 1,
			'status'     => 'all',
		)
	);
	if ( $existing ) {
		wp_delete_comment( (int) $existing[0]->comment_ID, true );
	}

	$cid = wp_new_comment(
		array(
			'comment_post_ID'      => $post_id,
			'comment_content'      => $content,
			'comment_author'       => 'Spec Bot',
			'comment_author_email' => 'spec@bristleconeit.com',
			'comment_author_url'   => 'https://bristleconeit.com',
			'comment_approved'     => 1,
		),
		true
	);
	if ( is_wp_error( $cid ) ) {
		WP_CLI::error( $cid->get_error_message() );
	}
	update_comment_meta( (int) $cid, '_bits_spec_comment_key', $key );
	wp_set_comment_status( (int) $cid, 'approve' );
	return (int) $cid;
}
