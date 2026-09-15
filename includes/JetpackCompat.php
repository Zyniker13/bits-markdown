<?php

declare(strict_types=1);

namespace Bristlecone\Markdown;

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
		$requested = isset( $_GET['bristlecone_markdown_disable_jetpack'] )
			? sanitize_text_field( wp_unslash( $_GET['bristlecone_markdown_disable_jetpack'] ) )
			: '';
		if ( '1' !== $requested ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'bristlecone_markdown_disable_jetpack' );

		if ( class_exists( '\Jetpack' ) && method_exists( '\Jetpack', 'deactivate_module' ) ) {
			\Jetpack::deactivate_module( 'markdown' );
		}

		set_transient( 'bristlecone_markdown_jetpack_disabled_' . get_current_user_id(), '1', MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'options-general.php?page=bristlecone-markdown' ) );
		exit;
	}

	public function maybe_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! Plugin::is_plugin_admin_screen() ) {
			return;
		}

		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}

		$this->render_jetpack_warning();
	}

	public function render_settings_notice(): void {
		$notice_key = 'bristlecone_markdown_jetpack_disabled_' . get_current_user_id();
		if ( get_transient( $notice_key ) ) {
			delete_transient( $notice_key );
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Jetpack Markdown has been disabled. Bristlecone Markdown will now convert posts and comments, including existing Jetpack Markdown documents.', 'bristlecone-markdown' );
			echo '</p></div>';
		}

		if ( $this->is_jetpack_markdown_active() ) {
			$this->render_jetpack_warning();
		}
	}

	private function render_jetpack_warning(): void {
		if ( ! $this->is_jetpack_markdown_active() ) {
			return;
		}

		$url = wp_nonce_url(
			add_query_arg( 'bristlecone_markdown_disable_jetpack', '1' ),
			'bristlecone_markdown_disable_jetpack'
		);

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Jetpack Markdown is still active. Bristlecone Markdown will not convert posts or comments until Jetpack Markdown is turned off, so existing content is not processed twice.', 'bristlecone-markdown' );
		echo ' <a class="button button-secondary" href="' . esc_url( $url ) . '">';
		echo esc_html__( 'Disable Jetpack Markdown', 'bristlecone-markdown' );
		echo '</a></p></div>';
	}
}
