<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown;

/**
 * Plugin bootstrap.
 */
final class Plugin {

	private static ?self $instance = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function init(): void {
		load_plugin_textdomain( 'bits-markdown', false, dirname( plugin_basename( BITS_MARKDOWN_FILE ) ) . '/languages' );

		Settings::instance()->register();
		AdminNotices::instance()->register();
		JetpackCompat::instance()->register();
		Block::instance()->register();
		Assets::instance()->register();

		if ( ! JetpackCompat::instance()->is_jetpack_markdown_active() ) {
			Storage::instance()->register();
			Rest::instance()->register();
			if ( Settings::instance()->comments_enabled() ) {
				Comments::instance()->register();
			}
		}
	}

	public function activate(): void {
		Settings::instance()->ensure_defaults();
	}
}
