<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown;

/**
 * REST API: iA Writer publish, Gutenberg document wrapping, live preview.
 */
final class Rest {

	private static ?self $instance = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this, 'register_type_filters' ) );
	}

	public function register_type_filters(): void {
		foreach ( Settings::instance()->enabled_post_types() as $type ) {
			add_filter( "rest_prepare_{$type}", array( $this, 'filter_prepare' ), 10, 3 );
		}
	}

	public function register_routes(): void {
		register_rest_route(
			'bits-markdown/v1',
			'/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'markdown' => array(
						'type'     => 'string',
						'required' => true,
					),
					'post_id'  => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function preview( $request ) {
		$markdown = (string) $request->get_param( 'markdown' );
		$post_id  = (int) $request->get_param( 'post_id' );
		$result   = Storage::instance()->convert(
			$markdown,
			array(
				'id' => $post_id > 0 ? (string) $post_id : 'preview',
			)
		);

		return rest_ensure_response(
			array(
				'html'     => $result->html,
				'has_math' => $result->has_math,
				'has_code' => $result->has_code,
			)
		);
	}

	/**
	 * @param \WP_REST_Response $response
	 * @param \WP_Post          $post
	 * @param \WP_REST_Request  $request
	 * @return \WP_REST_Response
	 */
	public function filter_prepare( $response, $post, $request ) {
		if ( 'edit' !== $request->get_param( 'context' ) ) {
			return $response;
		}

		if ( ! $post instanceof \WP_Post || ! Storage::instance()->is_markdown_post( (int) $post->ID ) ) {
			return $response;
		}

		$source = is_string( $post->post_content_filtered ) ? $post->post_content_filtered : '';
		if ( $source === '' ) {
			return $response;
		}

		$data = $response->get_data();
		if ( ! is_array( $data ) || empty( $data['content'] ) || ! is_array( $data['content'] ) ) {
			return $response;
		}

		if ( $this->is_block_editor_request( $request ) ) {
			if ( ! has_blocks( $post->post_content ) ) {
				$data['content']['raw'] = $this->wrap_markdown_block( $source );
				$response->set_data( $data );
			}
			return $response;
		}

		$data['content']['raw'] = $source;
		$response->set_data( $data );

		return $response;
	}

	/**
	 * @param \WP_REST_Request $request
	 */
	private function is_block_editor_request( $request ): bool {
		$locale = $request->get_param( '_locale' );
		if ( is_string( $locale ) && $locale !== '' ) {
			return true;
		}

		$referer = $request->get_header( 'referer' );
		if ( is_string( $referer ) && ( str_contains( $referer, 'post.php' ) || str_contains( $referer, 'post-new.php' ) ) ) {
			return true;
		}

		return false;
	}

	private function wrap_markdown_block( string $markdown ): string {
		return serialize_block(
			array(
				'blockName'    => 'bits/markdown',
				'attrs'        => array(
					'markdown' => $markdown,
				),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}
}
