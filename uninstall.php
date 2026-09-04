<?php
/**
 * Uninstall BITS Markdown.
 *
 * @package BITSMarkdown
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'bits_markdown_settings' );
delete_option( 'bits_markdown_jetpack_notice_dismissed' );
delete_option( 'bits_markdown_conflict_notice_dismissed' );
