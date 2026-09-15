<?php
/**
 * Uninstall Bristlecone Markdown.
 *
 * @package BristleconeMarkdown
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'bristlecone_markdown_settings' );
delete_option( 'bits_markdown_settings' );
delete_option( 'bristlecone_markdown_jetpack_notice_dismissed' );
delete_option( 'bristlecone_markdown_conflict_notice_dismissed' );
delete_option( 'bits_markdown_jetpack_notice_dismissed' );
delete_option( 'bits_markdown_conflict_notice_dismissed' );
