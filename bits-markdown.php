<?php
/**
 * Plugin Name: BITS Markdown
 * Plugin URI: https://bristleconeit.com/bits-markdown
 * Description: Markdown for WordPress without Jetpack. Write in the block editor, Classic Editor, comments, or from iA Writer, with syntax aligned to iA Writer.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Bristlecone IT Services
 * Author URI: https://bristleconeit.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bits-markdown
 * Domain Path: /languages
 *
 * @package BITSMarkdown
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BITS_MARKDOWN_VERSION', '1.0.0' );
define( 'BITS_MARKDOWN_FILE', __FILE__ );
define( 'BITS_MARKDOWN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BITS_MARKDOWN_URL', plugin_dir_url( __FILE__ ) );

$bits_markdown_autoload = BITS_MARKDOWN_DIR . 'vendor/autoload.php';

if ( ! is_readable( $bits_markdown_autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'BITS Markdown is missing its Composer dependencies. Run composer install in the plugin directory.', 'bits-markdown' );
			echo '</p></div>';
		}
	);
	return;
}

require_once $bits_markdown_autoload;

add_action(
	'plugins_loaded',
	static function (): void {
		\Bristlecone\BitsMarkdown\Plugin::instance()->init();
	}
);

register_activation_hook(
	__FILE__,
	static function (): void {
		\Bristlecone\BitsMarkdown\Plugin::instance()->activate();
	}
);
