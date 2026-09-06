<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown;

/**
 * Jetpack Markdown coexistence: warn, optionally disable the Jetpack module, adopt existing posts.
 */
final class JetpackCompat {

	private static ?self $instance = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'handle_disable_module' ) );
		add_action( 'admin_notices', array( $this, 'maybe_notice' ) );
	}

	public function is_jetpack_markdown_active(): bool {
		if ( ! class_exists( '\Jetpack' ) ) {
			return false;
		}

		if ( method_exists( '\Jetpack', 'is_module_active' ) ) {
			return (bool) \Jetpack::is_module_active( 'markdown' );
		}

		return false;
	}

	public function handle_disable_module(): void {
		$requested = isset( $_GET['bits_markdown_disable_jetpack'] )
			? sanitize_text_field( wp_unslash( $_GET['bits_markdown_disable_jetpack'] ) )
			: '';
		if ( '1' !== $requested ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'bits_markdown_disable_jetpack' );

		if ( class_exists( '\Jetpack' ) && method_exists( '\Jetpack', 'deactivate_module' ) ) {
			\Jetpack::deactivate_module( 'markdown' );
		}

		set_transient( 'bits_markdown_jetpack_disabled_' . get_current_user_id(), '1', MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'options-general.php?page=bits-markdown' ) );
		exit;
	}

	public function maybe_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! $this->is_jetpack_markdown_active() ) {
			return;
		}

		$url = wp_nonce_url(
			add_query_arg( 'bits_markdown_disable_jetpack', '1' ),
			'bits_markdown_disable_jetpack'
		);

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Jetpack Markdown is still active. BITS Markdown will not convert posts or comments until Jetpack Markdown is turned off, so existing content is not processed twice.', 'bits-markdown' );
		echo ' <a class="button button-secondary" href="' . esc_url( $url ) . '">';
		echo esc_html__( 'Disable Jetpack Markdown', 'bits-markdown' );
		echo '</a></p></div>';
	}

	public function render_settings_notice(): void {
		$notice_key = 'bits_markdown_jetpack_disabled_' . get_current_user_id();
		if ( get_transient( $notice_key ) ) {
			delete_transient( $notice_key );
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Jetpack Markdown has been disabled. BITS Markdown will now convert posts and comments, including existing Jetpack Markdown documents.', 'bits-markdown' );
			echo '</p></div>';
		}

		if ( $this->is_jetpack_markdown_active() ) {
			$this->maybe_notice();
		}
	}
}
