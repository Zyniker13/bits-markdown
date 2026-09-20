<?php

declare(strict_types=1);

namespace Bristlecone\Markdown {

	/**
	 * @param mixed $post_id
	 * @param mixed $key
	 * @param mixed $single
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$value = \Bristlecone\Markdown\Tests\StorageWpState::$meta[ (int) $post_id ][ (string) $key ] ?? '';
		return $single ? $value : array( $value );
	}

	/**
	 * @param mixed $post
	 * @return object|null
	 */
	function get_post( $post = null ) {
		return \Bristlecone\Markdown\Tests\StorageWpState::$posts[ (int) $post ] ?? null;
	}

	/**
	 * @param mixed $post_id
	 * @param mixed $key
	 * @param mixed $value
	 */
	function update_post_meta( $post_id, $key, $value ): bool {
		\Bristlecone\Markdown\Tests\StorageWpState::$meta[ (int) $post_id ][ (string) $key ] = $value;
		return true;
	}
}

namespace Bristlecone\Markdown\Tests {

	use Bristlecone\Markdown\Storage;
	use PHPUnit\Framework\Attributes\DataProvider;
	use PHPUnit\Framework\TestCase;

	final class StorageWpState {
		/** @var array<int, array<string, mixed>> */
		public static array $meta = array();

		/** @var array<int, object> */
		public static array $posts = array();
	}

	final class StorageTest extends TestCase {

		protected function setUp(): void {
			StorageWpState::$meta  = array();
			StorageWpState::$posts = array();
		}

		public function test_markdown_flag_keys_include_jetpack_and_native(): void {
			$keys = Storage::markdown_flag_keys();

			$this->assertContains( Storage::META_KEY, $keys );
			$this->assertContains( Storage::META_LEGACY_KEY, $keys );
			$this->assertContains( Storage::META_WPCOM_IS_MARKDOWN_KEY, $keys );
			$this->assertContains( Storage::META_WPCOM_KEY, $keys );
			$this->assertSame( '_wpcom_is_markdown', Storage::META_WPCOM_IS_MARKDOWN_KEY );
			$this->assertSame( '_wpcom_markdown', Storage::META_WPCOM_KEY );
		}

		/**
		 * @return \Generator<string, array{string, mixed}>
		 */
		public static function markdown_flag_provider(): \Generator {
			yield 'native bristlecone flag' => array( Storage::META_KEY, '1' );
			yield 'legacy bits flag' => array( Storage::META_LEGACY_KEY, '1' );
			yield 'jetpack _wpcom_is_markdown alone' => array( Storage::META_WPCOM_IS_MARKDOWN_KEY, '1' );
			yield 'jetpack _wpcom_markdown alone' => array( Storage::META_WPCOM_KEY, '1' );
			yield 'jetpack _wpcom_is_markdown integer' => array( Storage::META_WPCOM_IS_MARKDOWN_KEY, 1 );
		}

		#[DataProvider( 'markdown_flag_provider' )]
		public function test_is_markdown_post_true_for_each_flag_alone( string $key, mixed $value ): void {
			$post_id = 13;
			StorageWpState::$meta[ $post_id ] = array( $key => $value );

			$this->assertTrue( Storage::instance()->is_markdown_post( $post_id ) );
		}

		public function test_is_markdown_post_false_without_flags(): void {
			StorageWpState::$meta[ 13 ] = array();

			$this->assertFalse( Storage::instance()->is_markdown_post( 13 ) );
			$this->assertFalse( Storage::instance()->is_markdown_post( 0 ) );
			$this->assertFalse( Storage::instance()->is_markdown_post( -1 ) );
		}

		public function test_is_markdown_post_false_for_empty_jetpack_flags(): void {
			StorageWpState::$meta[ 13 ] = array(
				Storage::META_WPCOM_IS_MARKDOWN_KEY => '',
				Storage::META_WPCOM_KEY             => '',
			);

			$this->assertFalse( Storage::instance()->is_markdown_post( 13 ) );
		}

		public function test_edit_filter_returns_markdown_for_wpcom_is_markdown_only(): void {
			$post_id = 42;
			$html    = '<p>Hello</p>';
			$source  = "# Hello\n";

			StorageWpState::$meta[ $post_id ]  = array( Storage::META_WPCOM_IS_MARKDOWN_KEY => '1' );
			StorageWpState::$posts[ $post_id ] = (object) array(
				'ID'                    => $post_id,
				'post_content'          => $html,
				'post_content_filtered' => $source,
			);

			$this->assertSame(
				$source,
				Storage::instance()->filter_edit_post_content( $html, $post_id )
			);
		}

		public function test_edit_filter_returns_markdown_for_wpcom_markdown_only(): void {
			$post_id = 43;
			$html    = '<p>Legacy</p>';
			$source  = 'Legacy source';

			StorageWpState::$meta[ $post_id ]  = array( Storage::META_WPCOM_KEY => '1' );
			StorageWpState::$posts[ $post_id ] = (object) array(
				'ID'                    => $post_id,
				'post_content'          => $html,
				'post_content_filtered' => $source,
			);

			$this->assertSame(
				$source,
				Storage::instance()->filter_edit_post_content( $html, $post_id )
			);
		}

		public function test_edit_filter_leaves_html_when_not_markdown(): void {
			$html = '<p>Plain</p>';
			StorageWpState::$posts[ 7 ] = (object) array(
				'ID'                    => 7,
				'post_content'          => $html,
				'post_content_filtered' => '# Not used',
			);

			$this->assertSame( $html, Storage::instance()->filter_edit_post_content( $html, 7 ) );
		}

		public function test_successful_edit_stamps_native_markdown_flag(): void {
			$post_id = 44;
			StorageWpState::$meta[ $post_id ]  = array( Storage::META_WPCOM_IS_MARKDOWN_KEY => '1' );
			StorageWpState::$posts[ $post_id ] = (object) array(
				'ID'                    => $post_id,
				'post_content'          => '<p>Hi</p>',
				'post_content_filtered' => 'Hi',
			);

			Storage::instance()->filter_edit_post_content( '<p>Hi</p>', $post_id );

			$this->assertSame( 1, StorageWpState::$meta[ $post_id ][ Storage::META_KEY ] );
			$this->assertTrue( Storage::instance()->is_markdown_post( $post_id ) );
		}

		public function test_ensure_native_flag_does_not_mark_non_markdown_posts(): void {
			Storage::instance()->ensure_native_markdown_flag( 99 );

			$this->assertArrayNotHasKey( Storage::META_KEY, StorageWpState::$meta[ 99 ] ?? array() );
			$this->assertFalse( Storage::instance()->is_markdown_post( 99 ) );
		}
	}
}
