<?php

declare(strict_types=1);

namespace Bristlecone\Markdown;

/**
 * Compatibility alias for existing jetpack/markdown Gutenberg blocks.
 *
 * Registers the Jetpack block name only when Jetpack Markdown is inactive and
 * the type is not already registered. Saving (and an opt-in Tools converter)
 * rewrites those blocks to bristlecone/markdown.
 */
final class JetpackMarkdownBlock {

	public const TOOLS_SLUG  = 'bristlecone-markdown-converter';
	public const BATCH_SIZE  = 20;
	public const NOTICE_KEY  = 'bristlecone_markdown_convert_result';
	public const ACTION_NAME = 'bristlecone_markdown_convert_blocks';

	private static ?self $instance = null;

	private bool $adopting = false;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function register(): void {
		add_action( 'init', array( $this, 'register_alias_block' ), 20 );
		add_filter( 'wp_insert_post_data', array( $this, 'filter_insert_post_data' ), 9, 2 );
		add_action( 'admin_menu', array( $this, 'register_tools_page' ) );
		add_action( 'admin_init', array( $this, 'handle_tools_request' ) );
		add_action( 'admin_notices', array( $this, 'render_tools_notice' ) );
	}

	public function is_adopting(): bool {
		return $this->adopting;
	}

	/**
	 * Tools submenu is registered only when the settings checkbox is on.
	 */
	public static function should_register_tools_page( bool $converter_enabled ): bool {
		return $converter_enabled;
	}

	/**
	 * Bulk rewrite is refused while Jetpack Markdown is still converting content.
	 */
	public static function conversion_allowed( bool $converter_enabled, bool $jetpack_markdown_active ): bool {
		return $converter_enabled && ! $jetpack_markdown_active;
	}

	public function register_alias_block(): void {
		if ( JetpackCompat::instance()->is_jetpack_markdown_active() ) {
			$this->localize_block_script();
			return;
		}

		if ( $this->jetpack_block_already_registered() ) {
			$this->localize_block_script();
			return;
		}

		if ( ! function_exists( 'register_block_type' ) ) {
			$this->localize_block_script();
			return;
		}

		$registered = register_block_type(
			BlockMarkup::JETPACK,
			array(
				'api_version'     => 3,
				'title'           => __( 'Markdown', 'bristlecone-markdown' ),
				'category'        => 'text',
				'icon'            => 'editor-code',
				'description'     => __( 'Compatibility for an existing Markdown block. Saving this post stores it as a Bristlecone Markdown block.', 'bristlecone-markdown' ),
				'textdomain'      => 'bristlecone-markdown',
				'attributes'      => array(
					'source' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports'        => array(
					'html'      => false,
					'inserter'  => false,
					'align'     => array( 'wide', 'full' ),
					'anchor'    => true,
					'className' => true,
				),
				'editor_script'   => 'bristlecone-markdown-block',
				'render_callback' => array( $this, 'render' ),
			)
		);

		if ( ! $registered ) {
			$this->localize_block_script();
			return;
		}

		$this->adopting = true;
		$this->localize_block_script();
	}

	private function localize_block_script(): void {
		if ( ! function_exists( 'wp_localize_script' ) ) {
			return;
		}

		wp_localize_script(
			'bristlecone-markdown-block',
			'bristleconeMarkdownBlock',
			array(
				'previewUrl'         => esc_url_raw( rest_url( 'bristlecone-markdown/v1/preview' ) ),
				'adoptJetpackBlock'  => $this->adopting,
			)
		);
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	public function render( array $attributes, string $content, $block = null ): string {
		$source = isset( $attributes['source'] ) && is_string( $attributes['source'] )
			? $attributes['source']
			: '';
		$attributes['markdown'] = $source;
		return Block::instance()->render( $attributes, $content, $block );
	}

	/**
	 * Soft-migrate jetpack/markdown → bristlecone/markdown when we own the alias.
	 *
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $postarr
	 * @return array<string, mixed>
	 */
	public function filter_insert_post_data( array $data, array $postarr ): array {
		if ( ! $this->adopting || JetpackCompat::instance()->is_jetpack_markdown_active() ) {
			return $data;
		}

		if ( ( $data['post_type'] ?? '' ) === 'revision' ) {
			return $data;
		}

		$content = (string) ( $data['post_content'] ?? '' );
		if ( $content === '' || ! str_contains( $content, 'wp:jetpack/markdown' ) ) {
			return $data;
		}

		$unslashed = function_exists( 'wp_unslash' ) ? wp_unslash( $content ) : $content;
		if ( ! is_string( $unslashed ) ) {
			return $data;
		}

		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		$result  = $this->rewrite_content( $unslashed, $post_id );
		if ( $result['converted'] > 0 ) {
			$data['post_content'] = function_exists( 'wp_slash' ) ? wp_slash( $result['content'] ) : $result['content'];
		}

		return $data;
	}

	public function register_tools_page(): void {
		if ( ! self::should_register_tools_page( Settings::instance()->jetpack_block_converter_enabled() ) ) {
			return;
		}

		add_management_page(
			__( 'Bristlecone Markdown Converter', 'bristlecone-markdown' ),
			__( 'Bristlecone Markdown Converter', 'bristlecone-markdown' ),
			'manage_options',
			self::TOOLS_SLUG,
			array( $this, 'render_tools_page' )
		);
	}

	public function handle_tools_request(): void {
		if ( ! isset( $_POST['bristlecone_markdown_convert'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( self::ACTION_NAME );

		$settings_url = admin_url( 'options-general.php?page=bristlecone-markdown' );
		$tools_url    = admin_url( 'tools.php?page=' . self::TOOLS_SLUG );

		if ( ! Settings::instance()->jetpack_block_converter_enabled() ) {
			$this->store_notice(
				array(
					'status'  => 'error',
					'message' => __( 'The converter is disabled. Enable it under Settings → Bristlecone Markdown first.', 'bristlecone-markdown' ),
				)
			);
			wp_safe_redirect( $settings_url );
			exit;
		}

		if ( JetpackCompat::instance()->is_jetpack_markdown_active() ) {
			$this->store_notice(
				array(
					'status'  => 'error',
					'message' => __( 'Jetpack Markdown is still active. Turn it off before converting blocks, so content is not processed twice.', 'bristlecone-markdown' ),
				)
			);
			wp_safe_redirect( $tools_url );
			exit;
		}

		$confirmed = isset( $_POST['bristlecone_markdown_confirm'] )
			? sanitize_text_field( wp_unslash( $_POST['bristlecone_markdown_confirm'] ) )
			: '';
		if ( '1' !== $confirmed ) {
			$this->store_notice(
				array(
					'status'  => 'error',
					'message' => __( 'Conversion was not run. Check the confirmation box on the Tools page first.', 'bristlecone-markdown' ),
				)
			);
			wp_safe_redirect( $tools_url );
			exit;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 60 );
		}

		$stats = array(
			'converted' => isset( $_POST['converted'] ) ? max( 0, (int) $_POST['converted'] ) : 0,
			'skipped'   => isset( $_POST['skipped'] ) ? max( 0, (int) $_POST['skipped'] ) : 0,
			'errors'    => isset( $_POST['errors'] ) ? max( 0, (int) $_POST['errors'] ) : 0,
			'processed' => isset( $_POST['processed'] ) ? max( 0, (int) $_POST['processed'] ) : 0,
		);
		$last_id = isset( $_POST['last_id'] ) ? max( 0, (int) $_POST['last_id'] ) : 0;

		$ids = $this->query_post_ids_after( $last_id, self::BATCH_SIZE );
		if ( $ids === array() ) {
			$this->store_notice(
				array(
					'status'    => 'success',
					'message'   => $this->format_result_message( $stats ),
					'converted' => $stats['converted'],
					'skipped'   => $stats['skipped'],
					'errors'    => $stats['errors'],
					'processed' => $stats['processed'],
					'done'      => true,
				)
			);
			wp_safe_redirect( $tools_url );
			exit;
		}

		foreach ( $ids as $id ) {
			$last_id = $id;
			++$stats['processed'];
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post ) {
				++$stats['errors'];
				continue;
			}

			$outcome = $this->convert_post( $post );
			if ( 'converted' === $outcome ) {
				++$stats['converted'];
			} elseif ( 'skipped' === $outcome ) {
				++$stats['skipped'];
			} else {
				++$stats['errors'];
			}
		}

		$more = count( $ids ) === self::BATCH_SIZE && $this->query_post_ids_after( $last_id, 1 ) !== array();

		$this->store_notice(
			array(
				'status'    => $more ? 'info' : 'success',
				'message'   => $this->format_result_message( $stats ),
				'converted' => $stats['converted'],
				'skipped'   => $stats['skipped'],
				'errors'    => $stats['errors'],
				'processed' => $stats['processed'],
				'done'      => ! $more,
				'continue'  => $more,
				'last_id'   => $last_id,
			)
		);

		wp_safe_redirect( $tools_url );
		exit;
	}

	public function render_tools_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'tools_page_' . self::TOOLS_SLUG !== $screen->id ) {
			return;
		}

		$notice = get_transient( $this->notice_transient_key() );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		if ( empty( $notice['continue'] ) ) {
			delete_transient( $this->notice_transient_key() );
		}

		$class = 'notice notice-success is-dismissible';
		if ( ( $notice['status'] ?? '' ) === 'error' ) {
			$class = 'notice notice-error is-dismissible';
		} elseif ( ( $notice['status'] ?? '' ) === 'info' ) {
			$class = 'notice notice-info';
		}

		echo '<div class="' . esc_attr( $class ) . '"><p>' . esc_html( (string) $notice['message'] ) . '</p></div>';
	}

	public function render_tools_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$jetpack_active = JetpackCompat::instance()->is_jetpack_markdown_active();
		$allowed        = self::conversion_allowed(
			Settings::instance()->jetpack_block_converter_enabled(),
			$jetpack_active
		);
		$count          = $this->count_affected_posts();
		$notice         = get_transient( $this->notice_transient_key() );
		$continuing     = is_array( $notice ) && ! empty( $notice['continue'] ) && $allowed && ! $jetpack_active;

		$stats = array(
			'converted' => is_array( $notice ) ? (int) ( $notice['converted'] ?? 0 ) : 0,
			'skipped'   => is_array( $notice ) ? (int) ( $notice['skipped'] ?? 0 ) : 0,
			'errors'    => is_array( $notice ) ? (int) ( $notice['errors'] ?? 0 ) : 0,
			'processed' => is_array( $notice ) ? (int) ( $notice['processed'] ?? 0 ) : 0,
			'last_id'   => is_array( $notice ) ? (int) ( $notice['last_id'] ?? 0 ) : 0,
		);

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Bristlecone Markdown Converter', 'bristlecone-markdown' ); ?></h1>
			<p><?php echo esc_html__( 'This tool rewrites existing Jetpack Markdown blocks (jetpack/markdown) to Bristlecone Markdown blocks (bristlecone/markdown). It does not impersonate Jetpack, and it does not add a Jetpack block to the inserter.', 'bristlecone-markdown' ); ?></p>
			<p><?php echo esc_html__( 'Opening and saving a post already converts its Jetpack Markdown blocks. Use this page only when you want to update every matching post or page in the database. That cannot be undone automatically; WordPress revisions are created where enabled.', 'bristlecone-markdown' ); ?></p>

			<?php if ( $jetpack_active ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php echo esc_html__( 'Jetpack Markdown is still active. Conversion is paused so content is not processed twice. Disable that Jetpack module first.', 'bristlecone-markdown' ); ?>
				</p></div>
			<?php endif; ?>

			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of posts that still contain jetpack/markdown blocks */
						_n(
							'%d post or page in the enabled post types still contains a Jetpack Markdown block.',
							'%d posts or pages in the enabled post types still contain a Jetpack Markdown block.',
							$count,
							'bristlecone-markdown'
						),
						$count
					)
				);
				?>
			</p>

			<?php if ( $continuing ) : ?>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of posts processed so far */
							__( 'Processed %d so far. Continuing with the next batch…', 'bristlecone-markdown' ),
							$stats['processed']
						)
					);
					?>
				</p>
				<form method="post" action="" id="bristlecone-markdown-convert-continue">
					<?php wp_nonce_field( self::ACTION_NAME ); ?>
					<input type="hidden" name="bristlecone_markdown_convert" value="1" />
					<input type="hidden" name="bristlecone_markdown_confirm" value="1" />
					<input type="hidden" name="converted" value="<?php echo esc_attr( (string) $stats['converted'] ); ?>" />
					<input type="hidden" name="skipped" value="<?php echo esc_attr( (string) $stats['skipped'] ); ?>" />
					<input type="hidden" name="errors" value="<?php echo esc_attr( (string) $stats['errors'] ); ?>" />
					<input type="hidden" name="processed" value="<?php echo esc_attr( (string) $stats['processed'] ); ?>" />
					<input type="hidden" name="last_id" value="<?php echo esc_attr( (string) $stats['last_id'] ); ?>" />
					<?php submit_button( __( 'Continue conversion', 'bristlecone-markdown' ), 'primary', 'submit', false ); ?>
				</form>
				<script>
				(function () {
					var form = document.getElementById( 'bristlecone-markdown-convert-continue' );
					if ( form ) {
						form.submit();
					}
				})();
				</script>
			<?php elseif ( $allowed && $count > 0 ) : ?>
				<form method="post" action="">
					<?php wp_nonce_field( self::ACTION_NAME ); ?>
					<input type="hidden" name="bristlecone_markdown_convert" value="1" />
					<p>
						<label>
							<input type="checkbox" name="bristlecone_markdown_confirm" value="1" required />
							<?php echo esc_html__( 'I understand this will update matching posts and pages in the database.', 'bristlecone-markdown' ); ?>
						</label>
					</p>
					<?php submit_button( __( 'Convert Jetpack Markdown blocks', 'bristlecone-markdown' ) ); ?>
				</form>
			<?php elseif ( $allowed ) : ?>
				<p><?php echo esc_html__( 'Nothing to convert.', 'bristlecone-markdown' ); ?></p>
			<?php endif; ?>

			<p>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=bristlecone-markdown' ) ); ?>">
					<?php echo esc_html__( 'Back to Bristlecone Markdown settings', 'bristlecone-markdown' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * @param array{converted: int, skipped: int, errors: int, processed: int} $stats
	 */
	private function format_result_message( array $stats ): string {
		return sprintf(
			/* translators: 1: processed count, 2: converted count, 3: skipped count, 4: error count */
			__( 'Processed %1$d posts. Converted: %2$d. Skipped: %3$d. Errors: %4$d.', 'bristlecone-markdown' ),
			$stats['processed'],
			$stats['converted'],
			$stats['skipped'],
			$stats['errors']
		);
	}

	/**
	 * @return list<int>
	 */
	private function query_post_ids_after( int $last_id, int $limit ): array {
		global $wpdb;

		$types = Settings::instance()->enabled_post_types();
		if ( $types === array() || ! isset( $wpdb ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$like         = '%<!-- wp:jetpack/markdown%';
		$sql          = "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ($placeholders) AND post_status NOT IN ('trash','auto-draft','inherit') AND post_content LIKE %s ORDER BY ID ASC LIMIT %d";
		$params       = array_merge( array( $last_id ), $types, array( $like, $limit ) );
		$prepared     = $wpdb->prepare( $sql, $params );
		if ( ! is_string( $prepared ) ) {
			return array();
		}

		$ids = $wpdb->get_col( $prepared );
		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_values( array_map( 'intval', $ids ) );
	}

	private function count_affected_posts(): int {
		global $wpdb;

		$types = Settings::instance()->enabled_post_types();
		if ( $types === array() || ! isset( $wpdb ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$like         = '%<!-- wp:jetpack/markdown%';
		$sql          = "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type IN ($placeholders) AND post_status NOT IN ('trash','auto-draft','inherit') AND post_content LIKE %s";
		$params       = array_merge( $types, array( $like ) );
		$prepared     = $wpdb->prepare( $sql, $params );
		if ( ! is_string( $prepared ) ) {
			return 0;
		}

		return (int) $wpdb->get_var( $prepared );
	}

	private function convert_post( \WP_Post $post ): string {
		$result = $this->rewrite_content( (string) $post->post_content, (int) $post->ID );
		if ( $result['converted'] === 0 ) {
			return $result['errors'] > 0 ? 'error' : 'skipped';
		}

		$update = wp_update_post(
			wp_slash(
				array(
					'ID'           => $post->ID,
					'post_content' => $result['content'],
				)
			),
			true
		);

		if ( is_wp_error( $update ) || 0 === $update ) {
			return 'error';
		}

		return 'converted';
	}

	/**
	 * @return array{content: string, converted: int, errors: int}
	 */
	private function rewrite_content( string $content, int $post_id ): array {
		return BlockMarkup::rewrite_jetpack_markdown_blocks(
			$content,
			function ( string $source ) use ( $post_id ): string {
				return Storage::instance()->convert(
					$source,
					array(
						'id' => $post_id > 0 ? (string) $post_id : 'block',
					)
				)->html;
			}
		);
	}

	private function jetpack_block_already_registered(): bool {
		if ( ! class_exists( '\WP_Block_Type_Registry' ) ) {
			return false;
		}

		return \WP_Block_Type_Registry::get_instance()->is_registered( BlockMarkup::JETPACK );
	}

	/**
	 * @param array<string, mixed> $notice
	 */
	private function store_notice( array $notice ): void {
		set_transient( $this->notice_transient_key(), $notice, 10 * MINUTE_IN_SECONDS );
	}

	private function notice_transient_key(): string {
		$user = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		return self::NOTICE_KEY . '_' . $user;
	}
}
