<?php

declare(strict_types=1);

namespace Bristlecone\Markdown\Tests {

	use Bristlecone\Markdown\Settings;
	use PHPUnit\Framework\TestCase;

	/**
	 * Shared Bristlecone admin menu contracts (no WordPress runtime).
	 */
	final class MenuRegistrationTest extends TestCase {

		protected function setUp(): void {
			parent::setUp();
			$GLOBALS['admin_page_hooks']              = array();
			$GLOBALS['bristlecone_markdown_test_menu'] = array(
				'menu'    => array(),
				'submenu' => array(),
				'removed' => array(),
			);
		}

		public function test_slugs_and_screen_id(): void {
			$this->assertSame( 'bristlecone', Settings::PARENT_SLUG );
			$this->assertSame( 'bristlecone-markdown', Settings::PAGE_SLUG );
			$this->assertSame( 'bristlecone_page_bristlecone-markdown', Settings::screen_id() );
			$this->assertSame( 'http://example.test/wp-admin/admin.php?page=bristlecone-markdown', Settings::settings_url() );
		}

		public function test_source_does_not_use_options_page(): void {
			$settings = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Settings.php' );
			$plugin   = (string) file_get_contents( dirname( __DIR__ ) . '/includes/Plugin.php' );
			$block    = (string) file_get_contents( dirname( __DIR__ ) . '/includes/JetpackMarkdownBlock.php' );
			$compat   = (string) file_get_contents( dirname( __DIR__ ) . '/includes/JetpackCompat.php' );

			$this->assertStringNotContainsString( 'add_options_page', $settings );
			$this->assertStringContainsString( "add_action( 'admin_menu', array( \$this, 'register_menu' ), 10 )", $settings );
			$this->assertStringContainsString( 'empty( $GLOBALS[\'admin_page_hooks\'][ $parent_slug ] )', $settings );
			$this->assertStringContainsString( 'remove_submenu_page( $parent_slug, $parent_slug )', $settings );
			$this->assertStringContainsString( 'data:image/svg+xml;base64', $settings );
			$this->assertStringContainsString( 'fill="black"', $settings );
			$this->assertStringContainsString( "__( 'Markdown', 'bristlecone-markdown' )", $settings );
			$this->assertStringContainsString( 'redirect_legacy_settings_url', $settings );
			$this->assertStringContainsString( 'Settings::screen_id()', $plugin );
			$this->assertStringContainsString( "'toplevel_page_' . Settings::PARENT_SLUG", $plugin );
			$this->assertStringNotContainsString( 'options-general.php?page=bristlecone-markdown', $block );
			$this->assertStringNotContainsString( 'options-general.php?page=bristlecone-markdown', $compat );
			$this->assertStringContainsString( 'Settings::settings_url()', $block );
			$this->assertStringContainsString( 'Settings::settings_url()', $compat );
			$this->assertStringContainsString( 'Bristlecone → Markdown', $block );
		}

		public function test_creates_parent_when_missing(): void {
			Settings::instance()->register_menu();

			$log = $GLOBALS['bristlecone_markdown_test_menu'];
			$this->assertCount( 1, $log['menu'] );
			$this->assertSame( 'bristlecone', $log['menu'][0]['menu_slug'] );
			$this->assertSame( 'Bristlecone', $log['menu'][0]['menu_title'] );
			$this->assertSame( 'manage_options', $log['menu'][0]['capability'] );
			$this->assertSame( 58, $log['menu'][0]['position'] );
			$this->assertStringStartsWith( 'data:image/svg+xml;base64,', (string) $log['menu'][0]['icon_url'] );

			$svg = (string) base64_decode( substr( (string) $log['menu'][0]['icon_url'], strlen( 'data:image/svg+xml;base64,' ) ), true );
			$this->assertStringContainsString( '<svg', $svg );
			$this->assertStringContainsString( 'fill="black"', $svg );

			$this->assertCount( 1, $log['submenu'] );
			$this->assertSame( 'bristlecone', $log['submenu'][0]['parent_slug'] );
			$this->assertSame( 'Bristlecone Markdown', $log['submenu'][0]['page_title'] );
			$this->assertSame( 'Markdown', $log['submenu'][0]['menu_title'] );
			$this->assertSame( 'manage_options', $log['submenu'][0]['capability'] );
			$this->assertSame( 'bristlecone-markdown', $log['submenu'][0]['menu_slug'] );

			$this->assertSame(
				array(
					array(
						'menu_slug'    => 'bristlecone',
						'submenu_slug' => 'bristlecone',
					),
				),
				$log['removed']
			);
		}

		public function test_attaches_to_existing_parent_without_duplicate(): void {
			$GLOBALS['admin_page_hooks']['bristlecone'] = 'bristlecone';

			Settings::instance()->register_menu();

			$log = $GLOBALS['bristlecone_markdown_test_menu'];
			$this->assertSame( array(), $log['menu'] );
			$this->assertCount( 1, $log['submenu'] );
			$this->assertSame( 'bristlecone', $log['submenu'][0]['parent_slug'] );
			$this->assertSame( 'bristlecone-markdown', $log['submenu'][0]['menu_slug'] );
			$this->assertSame( array(), $log['removed'] );
		}

		public function test_docs_use_bristlecone_markdown_path(): void {
			$readme = (string) file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
			$md     = (string) file_get_contents( dirname( __DIR__ ) . '/README.md' );

			$this->assertStringContainsString( 'Bristlecone → Markdown', $readme );
			$this->assertStringContainsString( 'Bristlecone → Markdown', $md );
			$this->assertStringNotContainsString( 'Open Settings → Bristlecone Markdown', $readme );
			$this->assertStringContainsString( 'options-general.php?page=bristlecone-markdown', $readme );
			$this->assertStringContainsString( 'admin.php?page=bristlecone-markdown', $readme );
		}
	}
}

namespace {

	if ( ! function_exists( 'add_menu_page' ) ) {
		function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {
			$GLOBALS['admin_page_hooks'][ $menu_slug ]         = $menu_slug;
			$GLOBALS['bristlecone_markdown_test_menu']['menu'][] = array(
				'page_title' => $page_title,
				'menu_title' => $menu_title,
				'capability' => $capability,
				'menu_slug'  => $menu_slug,
				'callback'   => $callback,
				'icon_url'   => $icon_url,
				'position'   => $position,
			);
			return 'toplevel_page_' . $menu_slug;
		}
	}

	if ( ! function_exists( 'add_submenu_page' ) ) {
		function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {
			$GLOBALS['bristlecone_markdown_test_menu']['submenu'][] = array(
				'parent_slug' => $parent_slug,
				'page_title'  => $page_title,
				'menu_title'  => $menu_title,
				'capability'  => $capability,
				'menu_slug'   => $menu_slug,
				'callback'    => $callback,
				'position'    => $position,
			);
			return $parent_slug . '_page_' . $menu_slug;
		}
	}

	if ( ! function_exists( 'remove_submenu_page' ) ) {
		function remove_submenu_page( $menu_slug, $submenu_slug ) {
			$GLOBALS['bristlecone_markdown_test_menu']['removed'][] = array(
				'menu_slug'    => $menu_slug,
				'submenu_slug' => $submenu_slug,
			);
			return array();
		}
	}

	if ( ! function_exists( '__' ) ) {
		function __( $text, $domain = 'default' ) {
			return $text;
		}
	}

	if ( ! function_exists( 'admin_url' ) ) {
		function admin_url( $path = '', $scheme = 'admin' ) {
			return 'http://example.test/wp-admin/' . ltrim( (string) $path, '/' );
		}
	}
}
