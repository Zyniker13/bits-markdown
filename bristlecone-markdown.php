<?php
/**
 * Plugin Name: Bristlecone Markdown
 * Plugin URI: https://bristleconeit.com/bristlecone-markdown
 * Description: Markdown for WordPress without Jetpack. Write in the block editor, Classic Editor, comments, or from iA Writer, with syntax aligned to iA Writer.
 * Version: 1.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.4
 * Author: Bristlecone IT Services
 * Author URI: https://bristleconeit.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bristlecone-markdown
 * Domain Path: /languages
 *
 * @package BristleconeMarkdown
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BRISTLECONE_MARKDOWN_VERSION', '1.1.0' );
define( 'BRISTLECONE_MARKDOWN_FILE', __FILE__ );
define( 'BRISTLECONE_MARKDOWN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BRISTLECONE_MARKDOWN_URL', plugin_dir_url( __FILE__ ) );

$bristlecone_markdown_autoload = BRISTLECONE_MARKDOWN_DIR . 'vendor/autoload.php';

if ( ! is_readable( $bristlecone_markdown_autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Bristlecone Markdown is missing its Composer dependencies. Run composer install in the plugin directory.', 'bristlecone-markdown' );
			echo '</p></div>';
		}
	);
	return;
}

require_once $bristlecone_markdown_autoload;

add_action(
	'plugins_loaded',
	static function (): void {
		\Bristlecone\Markdown\Plugin::instance()->init();
	}
);

register_activation_hook(
	__FILE__,
	static function (): void {
		\Bristlecone\Markdown\Plugin::instance()->activate();
	}
);
