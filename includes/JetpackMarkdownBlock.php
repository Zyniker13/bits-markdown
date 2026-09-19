<?php

declare(strict_types=1);

namespace Bristlecone\Markdown;

/**
 * Compatibility aliases for existing Markdown Gutenberg blocks.
 *
 * Registers built-in (Jetpack, Simple Markdown) and custom identifiers only when
 * that block type is not already registered. Saving and an opt-in Tools converter
 * rewrite those blocks to bristlecone/markdown. A gated Tools scanner can list
 * other unregistered *markdown* names for review→convert; it never auto-registers.
 */
final class JetpackMarkdownBlock {

	public const TOOLS_SLUG       = 'bristlecone-markdown-converter';
	public const BATCH_SIZE       = 20;
	public const NOTICE_KEY       = 'bristlecone_markdown_convert_result';
	public const ACTION_NAME      = 'bristlecone_markdown_convert_blocks';
	public const SCAN_NOTICE_KEY  = 'bristlecone_markdown_scan_result';
	public const SCAN_ACTION_NAME = 'bristlecone_markdown_scan_blocks';

	private static ?self $instance = null;

	/** @var array<string, BlockAlias> */
	private array $adopting = array();

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
		return $this->adopting !== array();
	}

	/**
	 * Tools submenu is registered only when the settings checkbox is on.
	 */
	public static function should_register_tools_page( bool $converter_enabled ): bool {
		return $converter_enabled;
	}

	/**
	 * Bulk rewrite of Jetpack blocks is refused while Jetpack Markdown is still converting content.
	 */
	public static function conversion_allowed( bool $converter_enabled, bool $jetpack_markdown_active ): bool {
		return $converter_enabled && ! $jetpack_markdown_active;
	}

	/**
	 * Scanner and non-Jetpack alias conversion share the same settings unlock.
	 */
	public static function scan_allowed( bool $converter_enabled ): bool {
		return $converter_enabled;
	}

	public function register_alias_block(): void {
		$jetpack_active = JetpackCompat::instance()->is_jetpack_markdown_active();
		$custom         = Settings::instance()->custom_block_alias_objects();
		$to_adopt       = BlockAliasRegistry::aliases_to_adopt(
			$custom,
			fn( string $name ): bool => $this->block_already_registered( $name ),
			$jetpack_active
		);

		foreach ( $to_adopt as $alias ) {
			if ( $this->register_one_alias( $alias ) ) {
				$this->adopting[ $alias->name ] = $alias;
			}
		}

		$this->localize_block_script();
	}

	private function register_one_alias( BlockAlias $alias ): bool {
		if ( ! function_exists( 'register_block_type' ) ) {
			return false;
		}

		if ( $this->block_already_registered( $alias->name ) ) {
			return false;
		}

		$title = __( 'Markdown', 'bristlecone-markdown' );
		if ( ! in_array( $alias->name, array( BlockAliasRegistry::JETPACK, BlockAliasRegistry::SIMPLE_MARKDOWN ), true ) ) {
			$title = $alias->name;
		}

		$registered = register_block_type(
			$alias->name,
			array(
				'api_version'     => 3,
				'title'           => $title,
				'category'        => 'text',
				'icon'            => 'editor-code',
				'description'     => __( 'Compatibility for an existing Markdown block. Saving this post stores it as a Bristlecone Markdown block.', 'bristlecone-markdown' ),
				'textdomain'      => 'bristlecone-markdown',
				'attributes'      => $this->alias_block_attributes( $alias ),
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

		return (bool) $registered;
	}

	/**
	 * @return array<string, array{type: string, default: string}>
	 */
	private function alias_block_attributes( BlockAlias $alias ): array {
		$keys = ( is_string( $alias->attribute ) && $alias->attribute !== '' )
			? array( $alias->attribute )
			: BlockAliasRegistry::fallback_attributes();

		$attrs = array();
		foreach ( $keys as $key ) {
			$attrs[ $key ] = array(
				'type'    => 'string',
				'default' => '',
			);
		}

		return $attrs;
	}

	private function localize_block_script(): void {
		if ( ! function_exists( 'wp_localize_script' ) ) {
			return;
		}

		$aliases = array();
		foreach ( $this->adopting as $alias ) {
			$aliases[] = array(
				'name'      => $alias->name,
				'attribute' => $alias->attribute ?? '',
			);
		}

		wp_localize_script(
			'bristlecone-markdown-block',
			'bristleconeMarkdownBlock',
			array(
				'previewUrl'        => esc_url_raw( rest_url( 'bristlecone-markdown/v1/preview' ) ),
				'adoptJetpackBlock' => isset( $this->adopting[ BlockAliasRegistry::JETPACK ] ),
				'aliases'           => $aliases,
			)
		);
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	public function render( array $attributes, string $content, $block = null ): string {
		$name      = ( is_object( $block ) && isset( $block->name ) ) ? (string) $block->name : '';
		$preferred = $this->adopting[ $name ]->attribute ?? ( BlockMarkup::JETPACK === $name ? 'source' : null );
		$source    = BlockMarkup::source_from_attrs( $attributes, $preferred );
		$attributes['markdown'] = is_string( $source ) ? $source : '';
		return Block::instance()->render( $attributes, $content, $block );
	}

	/**
	 * Soft-migrate adopted aliases → bristlecone/markdown when we own them.
	 *
	 * @param array<string, mixed> $data
	 * @param array<string, mixed> $postarr
	 * @return array<string, mixed>
	 */
	public function filter_insert_post_data( array $data, array $postarr ): array {
		if ( $this->adopting === array() ) {
			return $data;
		}

		if ( ( $data['post_type'] ?? '' ) === 'revision' ) {
			return $data;
		}

		$content = (string) ( $data['post_content'] ?? '' );
		if ( $content === '' ) {
			return $data;
		}

		$unslashed = function_exists( 'wp_unslash' ) ? wp_unslash( $content ) : $content;
		if ( ! is_string( $unslashed ) ) {
			return $data;
		}

		if ( ! $this->content_mentions_aliases( $unslashed, array_values( $this->adopting ) ) ) {
			return $data;
		}

		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		$result  = $this->rewrite_content( $unslashed, $post_id, array_values( $this->adopting ) );
		if ( $result['converted'] > 0 ) {
			$data['post_content'] = function_exists( 'wp_slash' ) ? wp_slash( $result['content'] ) : $result['content'];
		}

		return $data;
	}

	public function register_tools_page(): void {
		if ( ! self::should_register_tools_page( Settings::instance()->block_converter_enabled() ) ) {
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
		$is_convert      = isset( $_POST['bristlecone_markdown_convert'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$is_scan         = isset( $_POST['bristlecone_markdown_scan'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$is_scan_convert = isset( $_POST['bristlecone_markdown_scan_convert'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $is_convert && ! $is_scan && ! $is_scan_convert ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( self::ACTION_NAME );

		$settings_url = admin_url( 'options-general.php?page=bristlecone-markdown' );
		$tools_url    = admin_url( 'tools.php?page=' . self::TOOLS_SLUG );

		if ( ! Settings::instance()->block_converter_enabled() ) {
			$this->store_notice(
				array(
					'status'  => 'error',
					'kind'    => 'error',
					'message' => __( 'The converter is disabled. Enable it under Settings → Bristlecone Markdown first.', 'bristlecone-markdown' ),
				)
			);
			wp_safe_redirect( $settings_url );
			exit;
		}

		if ( $is_scan ) {
			$this->run_scan_batch();
			wp_safe_redirect( $tools_url );
			exit;
		}

		if ( $is_scan_convert ) {
			$this->run_scan_convert_batch();
			wp_safe_redirect( $tools_url );
			exit;
		}

		if ( JetpackCompat::instance()->is_jetpack_markdown_active() && $this->adopting_without_jetpack() === array() ) {
			$this->store_notice(
				array(
					'status'  => 'error',
					'kind'    => 'error',
					'message' => __( 'Jetpack Markdown is still active. Turn it off before converting Jetpack blocks, so content is not processed twice.', 'bristlecone-markdown' ),
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
					'kind'    => 'error',
					'message' => __( 'Conversion was not run. Check the confirmation box on the Tools page first.', 'bristlecone-markdown' ),
				)
			);
			wp_safe_redirect( $tools_url );
			exit;
		}

		$this->run_convert_batch( array_values( $this->adopting ), 'convert' );
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

		$kind = (string) ( $notice['kind'] ?? '' );
		if ( empty( $notice['continue'] ) && 'scan' !== $kind ) {
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
		$allowed        = Settings::instance()->block_converter_enabled();
		$jetpack_ok     = self::conversion_allowed( $allowed, $jetpack_active );
		$count          = $this->count_affected_posts( array_values( $this->adopting ) );
		$notice         = get_transient( $this->notice_transient_key() );
		$kind           = is_array( $notice ) ? (string) ( $notice['kind'] ?? '' ) : '';
		$continuing     = is_array( $notice ) && ! empty( $notice['continue'] ) && $allowed;

		$stats = array(
			'converted' => is_array( $notice ) ? (int) ( $notice['converted'] ?? 0 ) : 0,
			'skipped'   => is_array( $notice ) ? (int) ( $notice['skipped'] ?? 0 ) : 0,
			'errors'    => is_array( $notice ) ? (int) ( $notice['errors'] ?? 0 ) : 0,
			'processed' => is_array( $notice ) ? (int) ( $notice['processed'] ?? 0 ) : 0,
			'last_id'   => is_array( $notice ) ? (int) ( $notice['last_id'] ?? 0 ) : 0,
		);

		$scan_blocks = ( is_array( $notice ) && isset( $notice['blocks'] ) && is_array( $notice['blocks'] ) )
			? $notice['blocks']
			: array();
		$scan_done   = is_array( $notice ) && ! empty( $notice['done'] ) && 'scan' === $kind && empty( $notice['continue'] );

		$alias_labels = $this->adopting === array()
			? array()
			: array_map( static fn( BlockAlias $alias ): string => $alias->line(), array_values( $this->adopting ) );

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Bristlecone Markdown Converter', 'bristlecone-markdown' ); ?></h1>
			<p><?php echo esc_html__( 'This tool rewrites compatible Markdown blocks to Bristlecone Markdown (bristlecone/markdown). Built-in identifiers are jetpack/markdown (source) and simple-markdown/markdown-block (content), plus any custom identifiers from settings. It does not impersonate those plugins, and it does not add their blocks to the inserter.', 'bristlecone-markdown' ); ?></p>
			<p><?php echo esc_html__( 'Opening and saving a post already converts adopted blocks. Use this page only when you want to update matching posts or pages in the database, or to review other unregistered blocks whose names contain “markdown”. That cannot be undone automatically; WordPress revisions are created where enabled.', 'bristlecone-markdown' ); ?></p>

			<?php if ( $jetpack_active ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php echo esc_html__( 'Jetpack Markdown is still active. Conversion of jetpack/markdown is paused so content is not processed twice. Disable that Jetpack module first. Other adopted identifiers can still be converted.', 'bristlecone-markdown' ); ?>
				</p></div>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'Convert known identifiers', 'bristlecone-markdown' ); ?></h2>
			<?php if ( $alias_labels !== array() ) : ?>
				<p>
					<?php echo esc_html__( 'Active aliases (hidden from the inserter):', 'bristlecone-markdown' ); ?>
					<code><?php echo esc_html( implode( ', ', $alias_labels ) ); ?></code>
				</p>
			<?php else : ?>
				<p><?php echo esc_html__( 'No aliases are active. Either those plugins are still registering the blocks, or no custom identifiers are configured.', 'bristlecone-markdown' ); ?></p>
			<?php endif; ?>

			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of posts that still contain adopted Markdown blocks */
						_n(
							'%d post or page in the enabled post types still contains a compatible Markdown block.',
							'%d posts or pages in the enabled post types still contain a compatible Markdown block.',
							$count,
							'bristlecone-markdown'
						),
						$count
					)
				);
				?>
			</p>

			<?php if ( $continuing && in_array( $kind, array( 'convert', 'scan', 'scan_convert' ), true ) ) : ?>
				<?php $this->render_continue_form( $stats, $kind, $notice ); ?>
			<?php elseif ( $allowed && $count > 0 && ( $jetpack_ok || $this->adopting_without_jetpack() !== array() ) ) : ?>
				<form method="post" action="">
					<?php wp_nonce_field( self::ACTION_NAME ); ?>
					<input type="hidden" name="bristlecone_markdown_convert" value="1" />
					<p>
						<label>
							<input type="checkbox" name="bristlecone_markdown_confirm" value="1" required />
							<?php echo esc_html__( 'I understand this will update matching posts and pages in the database.', 'bristlecone-markdown' ); ?>
						</label>
					</p>
					<?php submit_button( __( 'Convert compatible Markdown blocks', 'bristlecone-markdown' ) ); ?>
				</form>
			<?php elseif ( $allowed && ! $jetpack_ok && $this->adopting === array() ) : ?>
				<p><?php echo esc_html__( 'Jetpack conversion is paused while Jetpack Markdown is active.', 'bristlecone-markdown' ); ?></p>
			<?php elseif ( $allowed ) : ?>
				<p><?php echo esc_html__( 'Nothing to convert.', 'bristlecone-markdown' ); ?></p>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'Find other Markdown-named blocks', 'bristlecone-markdown' ); ?></h2>
			<p><?php echo esc_html__( 'Scan looks through enabled post types for unregistered Gutenberg blocks whose names contain “markdown”. Results are listed for review. Nothing is rewritten or added to the custom identifier list until you select names and confirm.', 'bristlecone-markdown' ); ?></p>
			<p><?php echo esc_html__( 'Editor-comment style blocks (for example names containing markdown-comment) are flagged and left unchecked. Select them only if you really want those converted as Markdown.', 'bristlecone-markdown' ); ?></p>

			<?php if ( $allowed && ! $continuing ) : ?>
				<form method="post" action="">
					<?php wp_nonce_field( self::ACTION_NAME ); ?>
					<input type="hidden" name="bristlecone_markdown_scan" value="1" />
					<?php submit_button( __( 'Scan for Markdown-named blocks', 'bristlecone-markdown' ), 'secondary' ); ?>
				</form>
			<?php endif; ?>

			<?php if ( $scan_done ) : ?>
				<?php $this->render_scan_review( $scan_blocks ); ?>
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
	 * @param array{converted: int, skipped: int, errors: int, processed: int, last_id: int} $stats
	 * @param array<string, mixed>                                                           $notice
	 */
	private function render_continue_form( array $stats, string $kind, array $notice ): void {
		$action = 'bristlecone_markdown_convert';
		if ( 'scan' === $kind ) {
			$action = 'bristlecone_markdown_scan';
		} elseif ( 'scan_convert' === $kind ) {
			$action = 'bristlecone_markdown_scan_convert';
		}

		?>
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
			<input type="hidden" name="<?php echo esc_attr( $action ); ?>" value="1" />
			<input type="hidden" name="bristlecone_markdown_confirm" value="1" />
			<input type="hidden" name="converted" value="<?php echo esc_attr( (string) $stats['converted'] ); ?>" />
			<input type="hidden" name="skipped" value="<?php echo esc_attr( (string) $stats['skipped'] ); ?>" />
			<input type="hidden" name="errors" value="<?php echo esc_attr( (string) $stats['errors'] ); ?>" />
			<input type="hidden" name="processed" value="<?php echo esc_attr( (string) $stats['processed'] ); ?>" />
			<input type="hidden" name="last_id" value="<?php echo esc_attr( (string) $stats['last_id'] ); ?>" />
			<?php
			if ( 'scan_convert' === $kind ) {
				$names = isset( $notice['scan_names'] ) && is_array( $notice['scan_names'] ) ? $notice['scan_names'] : array();
				$attrs = isset( $notice['scan_attrs'] ) && is_array( $notice['scan_attrs'] ) ? $notice['scan_attrs'] : array();
				foreach ( $names as $name ) {
					echo '<input type="hidden" name="scan_names[]" value="' . esc_attr( (string) $name ) . '" />';
				}
				foreach ( $attrs as $name => $attr ) {
					echo '<input type="hidden" name="scan_attrs[' . esc_attr( (string) $name ) . ']" value="' . esc_attr( (string) $attr ) . '" />';
				}
				if ( ! empty( $notice['add_to_list'] ) ) {
					echo '<input type="hidden" name="bristlecone_markdown_add_to_list" value="1" />';
				}
			}
			if ( 'scan' === $kind && isset( $notice['blocks'] ) && is_array( $notice['blocks'] ) ) {
				echo '<input type="hidden" name="scan_payload" value="' . esc_attr( (string) wp_json_encode( $notice['blocks'] ) ) . '" />';
			}
			submit_button( __( 'Continue', 'bristlecone-markdown' ), 'primary', 'submit', false );
			?>
		</form>
		<script>
		(function () {
			var form = document.getElementById( 'bristlecone-markdown-convert-continue' );
			if ( form ) {
				form.submit();
			}
		})();
		</script>
		<?php
	}

	/**
	 * @param array<string, array<string, mixed>> $blocks
	 */
	private function render_scan_review( array $blocks ): void {
		if ( $blocks === array() ) {
			echo '<p>' . esc_html__( 'Scan finished. No unregistered Markdown-named blocks were found.', 'bristlecone-markdown' ) . '</p>';
			return;
		}

		echo '<h3>' . esc_html__( 'Review scan results', 'bristlecone-markdown' ) . '</h3>';
		echo '<p>' . esc_html__( 'Check the identifiers you want to convert. Optionally add those same names to the custom list so they become aliases on the next page load. The scan itself never registers aliases.', 'bristlecone-markdown' ) . '</p>';
		echo '<form method="post" action="">';
		wp_nonce_field( self::ACTION_NAME );
		echo '<input type="hidden" name="bristlecone_markdown_scan_convert" value="1" />';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Convert', 'bristlecone-markdown' ) . '</th>';
		echo '<th>' . esc_html__( 'Block', 'bristlecone-markdown' ) . '</th>';
		echo '<th>' . esc_html__( 'Counts', 'bristlecone-markdown' ) . '</th>';
		echo '<th>' . esc_html__( 'Attribute keys', 'bristlecone-markdown' ) . '</th>';
		echo '<th>' . esc_html__( 'Source attribute', 'bristlecone-markdown' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $blocks as $name => $row ) {
			$false_friend = ! empty( $row['false_friend'] );
			$suggested    = isset( $row['suggested_attribute'] ) ? (string) $row['suggested_attribute'] : '';
			$attr_bits    = array();
			if ( isset( $row['attributes'] ) && is_array( $row['attributes'] ) ) {
				foreach ( $row['attributes'] as $key => $attr_count ) {
					$attr_bits[] = $key . ' (' . (int) $attr_count . ')';
				}
			}

			echo '<tr>';
			echo '<td><label><input type="checkbox" name="scan_names[]" value="' . esc_attr( (string) $name ) . '" ' . ( $false_friend ? '' : 'checked="checked"' ) . ' /></label></td>';
			echo '<td><code>' . esc_html( (string) $name ) . '</code>';
			if ( $false_friend ) {
				echo '<p class="description">' . esc_html__( 'Looks like an editor-comment block, not Markdown content. Left unchecked on purpose.', 'bristlecone-markdown' ) . '</p>';
			}
			echo '</td>';
			echo '<td>' . esc_html(
				sprintf(
					/* translators: 1: block instance count, 2: post count */
					__( '%1$d blocks in %2$d posts', 'bristlecone-markdown' ),
					(int) ( $row['count'] ?? 0 ),
					(int) ( $row['posts'] ?? 0 )
				)
			) . '</td>';
			echo '<td>' . esc_html( $attr_bits === array() ? '—' : implode( ', ', $attr_bits ) ) . '</td>';
			echo '<td><input type="text" name="scan_attrs[' . esc_attr( (string) $name ) . ']" value="' . esc_attr( $suggested ) . '" class="regular-text" /></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<p><label><input type="checkbox" name="bristlecone_markdown_add_to_list" value="1" /> ';
		echo esc_html__( 'Also add the selected names to Settings → custom block identifiers (so they become aliases).', 'bristlecone-markdown' );
		echo '</label></p>';
		echo '<p><label><input type="checkbox" name="bristlecone_markdown_confirm" value="1" required /> ';
		echo esc_html__( 'I understand this will update matching posts and pages in the database.', 'bristlecone-markdown' );
		echo '</label></p>';
		submit_button( __( 'Convert selected blocks', 'bristlecone-markdown' ) );
		echo '</form>';
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
	 * @param list<BlockAlias> $aliases
	 * @return list<int>
	 */
	private function query_post_ids_after( int $last_id, int $limit, array $aliases, bool $scan_like = false ): array {
		global $wpdb;

		$types = Settings::instance()->enabled_post_types();
		if ( $types === array() || ! isset( $wpdb ) ) {
			return array();
		}

		$type_placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		if ( $scan_like ) {
			$sql    = "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ($type_placeholders) AND post_status NOT IN ('trash','auto-draft','inherit') AND post_content LIKE %s AND post_content LIKE %s ORDER BY ID ASC LIMIT %d";
			$params = array_merge( array( $last_id ), $types, array( '%<!-- wp:%', '%markdown%', $limit ) );
		} else {
			$likes = $this->alias_like_patterns( $aliases );
			if ( $likes === array() ) {
				return array();
			}
			$like_sql = implode( ' OR ', array_fill( 0, count( $likes ), 'post_content LIKE %s' ) );
			$sql      = "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ($type_placeholders) AND post_status NOT IN ('trash','auto-draft','inherit') AND ($like_sql) ORDER BY ID ASC LIMIT %d";
			$params   = array_merge( array( $last_id ), $types, $likes, array( $limit ) );
		}

		$prepared = $wpdb->prepare( $sql, $params );
		if ( ! is_string( $prepared ) ) {
			return array();
		}

		$ids = $wpdb->get_col( $prepared );
		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_values( array_map( 'intval', $ids ) );
	}

	/**
	 * @param list<BlockAlias> $aliases
	 */
	private function count_affected_posts( array $aliases ): int {
		global $wpdb;

		$types = Settings::instance()->enabled_post_types();
		if ( $types === array() || ! isset( $wpdb ) || $aliases === array() ) {
			return 0;
		}

		$type_placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$likes             = $this->alias_like_patterns( $aliases );
		if ( $likes === array() ) {
			return 0;
		}

		$like_sql = implode( ' OR ', array_fill( 0, count( $likes ), 'post_content LIKE %s' ) );
		$sql      = "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type IN ($type_placeholders) AND post_status NOT IN ('trash','auto-draft','inherit') AND ($like_sql)";
		$params   = array_merge( $types, $likes );
		$prepared = $wpdb->prepare( $sql, $params );
		if ( ! is_string( $prepared ) ) {
			return 0;
		}

		return (int) $wpdb->get_var( $prepared );
	}

	/**
	 * @param list<BlockAlias> $aliases
	 * @return list<string>
	 */
	private function alias_like_patterns( array $aliases ): array {
		global $wpdb;

		$likes = array();
		foreach ( $aliases as $alias ) {
			$name = $alias->name;
			if ( isset( $wpdb ) && method_exists( $wpdb, 'esc_like' ) ) {
				$name = $wpdb->esc_like( $name );
			}
			$likes[] = '%<!-- wp:' . $name . '%';
		}

		return $likes;
	}

	/**
	 * @param list<BlockAlias> $aliases
	 */
	private function convert_post( \WP_Post $post, array $aliases ): string {
		$result = $this->rewrite_content( (string) $post->post_content, (int) $post->ID, $aliases );
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
	 * @param list<BlockAlias> $aliases
	 * @return array{content: string, converted: int, errors: int}
	 */
	private function rewrite_content( string $content, int $post_id, array $aliases ): array {
		return BlockMarkup::rewrite_aliased_blocks(
			$content,
			$aliases,
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
		return $this->block_already_registered( BlockMarkup::JETPACK );
	}

	private function block_already_registered( string $name ): bool {
		if ( ! class_exists( '\WP_Block_Type_Registry' ) ) {
			return false;
		}

		return \WP_Block_Type_Registry::get_instance()->is_registered( $name );
	}

	/**
	 * @return list<string>
	 */
	private function registered_block_names(): array {
		if ( ! class_exists( '\WP_Block_Type_Registry' ) ) {
			return array();
		}

		return array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() );
	}

	/**
	 * @return list<BlockAlias>
	 */
	private function adopting_without_jetpack(): array {
		$out = array();
		foreach ( $this->adopting as $alias ) {
			if ( BlockAliasRegistry::JETPACK !== $alias->name ) {
				$out[] = $alias;
			}
		}

		return $out;
	}

	/**
	 * @param list<BlockAlias> $aliases
	 */
	private function content_mentions_aliases( string $content, array $aliases ): bool {
		foreach ( $aliases as $alias ) {
			if ( str_contains( $content, 'wp:' . $alias->name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param list<BlockAlias> $aliases
	 */
	private function run_convert_batch( array $aliases, string $kind, array $extra_notice = array() ): void {
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 60 );
		}

		$stats = array(
			'converted' => isset( $_POST['converted'] ) ? max( 0, (int) $_POST['converted'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'skipped'   => isset( $_POST['skipped'] ) ? max( 0, (int) $_POST['skipped'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'errors'    => isset( $_POST['errors'] ) ? max( 0, (int) $_POST['errors'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'processed' => isset( $_POST['processed'] ) ? max( 0, (int) $_POST['processed'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing
		);
		$last_id = isset( $_POST['last_id'] ) ? max( 0, (int) $_POST['last_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$ids = $this->query_post_ids_after( $last_id, self::BATCH_SIZE, $aliases );
		if ( $ids === array() ) {
			$this->store_notice(
				array_merge(
					$extra_notice,
					array(
						'status'    => 'success',
						'kind'      => $kind,
						'message'   => $this->format_result_message( $stats ),
						'converted' => $stats['converted'],
						'skipped'   => $stats['skipped'],
						'errors'    => $stats['errors'],
						'processed' => $stats['processed'],
						'done'      => true,
					)
				)
			);
			return;
		}

		foreach ( $ids as $id ) {
			$last_id = $id;
			++$stats['processed'];
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post ) {
				++$stats['errors'];
				continue;
			}

			$outcome = $this->convert_post( $post, $aliases );
			if ( 'converted' === $outcome ) {
				++$stats['converted'];
			} elseif ( 'skipped' === $outcome ) {
				++$stats['skipped'];
			} else {
				++$stats['errors'];
			}
		}

		$more = count( $ids ) === self::BATCH_SIZE && $this->query_post_ids_after( $last_id, 1, $aliases ) !== array();

		$this->store_notice(
			array_merge(
				$extra_notice,
				array(
					'status'    => $more ? 'info' : 'success',
					'kind'      => $kind,
					'message'   => $this->format_result_message( $stats ),
					'converted' => $stats['converted'],
					'skipped'   => $stats['skipped'],
					'errors'    => $stats['errors'],
					'processed' => $stats['processed'],
					'done'      => ! $more,
					'continue'  => $more,
					'last_id'   => $last_id,
				)
			)
		);
	}

	private function run_scan_batch(): void {
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 60 );
		}

		$processed = isset( $_POST['processed'] ) ? max( 0, (int) $_POST['processed'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$last_id   = isset( $_POST['last_id'] ) ? max( 0, (int) $_POST['last_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$blocks    = array();

		if ( isset( $_POST['scan_payload'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$decoded = json_decode( wp_unslash( (string) $_POST['scan_payload'] ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( is_array( $decoded ) ) {
				$blocks = $decoded;
			}
		}

		$exclude = array_merge(
			MarkdownBlockScanner::exclude_defaults(),
			BlockAliasRegistry::names( Settings::instance()->custom_block_alias_objects() ),
			array_keys( $this->adopting ),
			$this->registered_block_names()
		);

		$ids = $this->query_post_ids_after( $last_id, self::BATCH_SIZE, array(), true );
		if ( $ids === array() ) {
			ksort( $blocks );
			$this->store_notice(
				array(
					'status'    => 'success',
					'kind'      => 'scan',
					'message'   => __( 'Scan finished. Review any matches below. Nothing was converted.', 'bristlecone-markdown' ),
					'blocks'    => $blocks,
					'processed' => $processed,
					'done'      => true,
				)
			);
			return;
		}

		foreach ( $ids as $id ) {
			$last_id = $id;
			++$processed;
			$post = get_post( $id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$found  = MarkdownBlockScanner::summarize( (string) $post->post_content, $exclude );
			$blocks = MarkdownBlockScanner::merge_summaries( $blocks, $found, true );
		}

		$more = count( $ids ) === self::BATCH_SIZE && $this->query_post_ids_after( $last_id, 1, array(), true ) !== array();

		$this->store_notice(
			array(
				'status'    => $more ? 'info' : 'success',
				'kind'      => 'scan',
				'message'   => $more
					? sprintf(
						/* translators: %d: posts scanned so far */
						__( 'Scanned %d posts so far. Continuing…', 'bristlecone-markdown' ),
						$processed
					)
					: __( 'Scan finished. Review any matches below. Nothing was converted.', 'bristlecone-markdown' ),
				'blocks'    => $blocks,
				'processed' => $processed,
				'done'      => ! $more,
				'continue'  => $more,
				'last_id'   => $last_id,
			)
		);
	}

	private function run_scan_convert_batch(): void {
		$confirmed = isset( $_POST['bristlecone_markdown_confirm'] )
			? sanitize_text_field( wp_unslash( $_POST['bristlecone_markdown_confirm'] ) )
			: '';
		if ( '1' !== $confirmed ) {
			$this->store_notice(
				array(
					'status'  => 'error',
					'kind'    => 'error',
					'message' => __( 'Conversion was not run. Check the confirmation box on the Tools page first.', 'bristlecone-markdown' ),
				)
			);
			return;
		}

		$selected = $this->selected_scan_aliases();
		if ( $selected === array() ) {
			$this->store_notice(
				array(
					'status'  => 'error',
					'kind'    => 'error',
					'message' => __( 'No blocks were selected. Scan again and check the identifiers you want to convert.', 'bristlecone-markdown' ),
				)
			);
			return;
		}

		$add_to_list = isset( $_POST['bristlecone_markdown_add_to_list'] )
			&& '1' === sanitize_text_field( wp_unslash( $_POST['bristlecone_markdown_add_to_list'] ) );
		$already     = isset( $_POST['processed'] ) && (int) $_POST['processed'] > 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $add_to_list && ! $already ) {
			$lines = array();
			foreach ( $selected as $alias ) {
				$lines[] = $alias->line();
			}
			Settings::instance()->append_custom_aliases( $lines );
		}

		$extra = array(
			'scan_names'  => BlockAliasRegistry::names( $selected ),
			'scan_attrs'  => array(),
			'add_to_list' => $add_to_list,
		);
		foreach ( $selected as $alias ) {
			$extra['scan_attrs'][ $alias->name ] = $alias->attribute ?? '';
		}

		$this->run_convert_batch( $selected, 'scan_convert', $extra );
	}

	/**
	 * @return list<BlockAlias>
	 */
	private function selected_scan_aliases(): array {
		$raw_names = isset( $_POST['scan_names'] ) && is_array( $_POST['scan_names'] ) ? $_POST['scan_names'] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw_attrs = isset( $_POST['scan_attrs'] ) && is_array( $_POST['scan_attrs'] ) ? $_POST['scan_attrs'] : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$aliases = array();
		$seen    = array();
		foreach ( $raw_names as $raw ) {
			$name = BlockAliasRegistry::sanitize_block_name( sanitize_text_field( wp_unslash( (string) $raw ) ) );
			if ( null === $name || isset( $seen[ $name ] ) ) {
				continue;
			}

			if ( BlockAliasRegistry::JETPACK === $name && JetpackCompat::instance()->is_jetpack_markdown_active() ) {
				continue;
			}

			$attr = null;
			if ( isset( $raw_attrs[ $name ] ) ) {
				$attr = BlockAliasRegistry::sanitize_attribute_key( sanitize_text_field( wp_unslash( (string) $raw_attrs[ $name ] ) ) );
			} elseif ( isset( $raw_attrs[ $raw ] ) ) {
				$attr = BlockAliasRegistry::sanitize_attribute_key( sanitize_text_field( wp_unslash( (string) $raw_attrs[ $raw ] ) ) );
			}

			$seen[ $name ] = true;
			$aliases[]     = new BlockAlias( $name, $attr, BlockAlias::ORIGIN_CUSTOM );
		}

		return $aliases;
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
