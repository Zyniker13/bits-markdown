<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown;

/**
 * Warn when another Markdown plugin is active.
 */
final class AdminNotices {

	private static ?self $instance = null;

	private const KNOWN = array(
		'wp-githuber-md/githuber-md.php',
		'githuber-md/githuber-md.php',
		'wp-editormd/wp-editormd.php',
		'wp-editormd/wp-editor.md.php',
		'ultimate-markdown/ultimate-markdown.php',
		'kototsugi/kototsugi.php',
		'wp-markdown/wp-markdown.php',
		'easy-markdown/easy-markdown.php',
		'jetpack/jetpack.php',
	);

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function register(): void {
		add_action( 'admin_notices', array( $this, 'conflict_notice' ) );
	}

	public function conflict_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$conflicts = $this->active_conflicts();
		if ( $conflicts === array() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'BITS Markdown detected another Markdown-related plugin. Disable the extra Markdown conversion to avoid double-processing:', 'bits-markdown' );
		echo ' <strong>' . esc_html( implode( ', ', $conflicts ) ) . '</strong>';
		echo '</p></div>';
	}

	/**
	 * @return list<string>
	 */
	private function active_conflicts(): array {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$found = array();
		foreach ( self::KNOWN as $file ) {
			if ( 'jetpack/jetpack.php' === $file ) {
				if ( JetpackCompat::instance()->is_jetpack_markdown_active() ) {
					$found[] = 'Jetpack Markdown';
				}
				continue;
			}
			if ( is_plugin_active( $file ) ) {
				$found[] = $file;
			}
		}

		return $found;
	}
}
