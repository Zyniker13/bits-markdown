<?php

declare(strict_types=1);

namespace Bristlecone\Markdown;

/**
 * Plugin settings stored in bristlecone_markdown_settings.
 */
final class Settings {

	public const OPTION        = 'bristlecone_markdown_settings';
	public const LEGACY_OPTION = 'bits_markdown_settings';

	private static ?self $instance = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * @return array{
	 *   post_types: array<string, bool>,
	 *   comments: bool,
	 *   syntax_highlighting: bool,
	 *   math: bool,
	 *   default_to_markdown: bool,
	 *   jetpack_block_converter: bool,
	 *   custom_block_aliases: string
	 * }
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, false );
		if ( ! is_array( $stored ) ) {
			$legacy = get_option( self::LEGACY_OPTION, false );
			$stored = is_array( $legacy ) ? $legacy : array();
		}
		return wp_parse_args( $stored, $this->defaults() );
	}

	/**
	 * @return array{
	 *   post_types: array<string, bool>,
	 *   comments: bool,
	 *   syntax_highlighting: bool,
	 *   math: bool,
	 *   default_to_markdown: bool,
	 *   jetpack_block_converter: bool,
	 *   custom_block_aliases: string
	 * }
	 */
	public function defaults(): array {
		return array(
			'post_types'              => array(
				'post' => true,
				'page' => true,
			),
			'comments'                => true,
			'syntax_highlighting'     => true,
			'math'                    => true,
			'default_to_markdown'     => false,
			'jetpack_block_converter' => false,
			'custom_block_aliases'    => '',
		);
	}

	public function ensure_defaults(): void {
		if ( false !== get_option( self::OPTION, false ) ) {
			return;
		}

		$legacy = get_option( self::LEGACY_OPTION, false );
		if ( is_array( $legacy ) ) {
			add_option( self::OPTION, wp_parse_args( $legacy, $this->defaults() ) );
			return;
		}

		add_option( self::OPTION, $this->defaults() );
	}

	public function comments_enabled(): bool {
		return (bool) $this->all()['comments'];
	}

	public function highlighting_enabled(): bool {
		return (bool) $this->all()['syntax_highlighting'];
	}

	public function math_enabled(): bool {
		return (bool) $this->all()['math'];
	}

	public function default_to_markdown_enabled(): bool {
		return ! empty( $this->all()['default_to_markdown'] );
	}

	public function jetpack_block_converter_enabled(): bool {
		return ! empty( $this->all()['jetpack_block_converter'] );
	}

	/**
	 * Same settings unlock as the Jetpack Tools converter.
	 */
	public function block_converter_enabled(): bool {
		return $this->jetpack_block_converter_enabled();
	}

	public function custom_block_aliases_text(): string {
		return (string) ( $this->all()['custom_block_aliases'] ?? '' );
	}

	/**
	 * @return list<BlockAlias>
	 */
	public function custom_block_alias_objects(): array {
		return BlockAliasRegistry::parse_custom_text( $this->custom_block_aliases_text() );
	}

	public static function sanitize_custom_block_aliases( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		return BlockAliasRegistry::format_lines( BlockAliasRegistry::parse_custom_text( $value ) );
	}

	/**
	 * Persist user-selected scanner identifiers. Regex hits are never added here.
	 *
	 * @param list<string> $lines
	 */
	public function append_custom_aliases( array $lines ): void {
		$merged = $this->custom_block_alias_objects();
		$seen   = array();
		foreach ( $merged as $alias ) {
			$seen[ $alias->name ] = true;
		}

		foreach ( $lines as $line ) {
			$alias = BlockAliasRegistry::parse_custom_line( (string) $line );
			if ( null === $alias || isset( $seen[ $alias->name ] ) ) {
				continue;
			}
			$seen[ $alias->name ] = true;
			$merged[]             = $alias;
		}

		$current                         = $this->all();
		$current['custom_block_aliases'] = BlockAliasRegistry::format_lines( $merged );
		update_option( self::OPTION, $current );
	}

	/**
	 * @return list<string>
	 */
	public function enabled_post_types(): array {
		$types = array();
		foreach ( $this->all()['post_types'] as $type => $enabled ) {
			if ( $enabled ) {
				$types[] = (string) $type;
			}
		}
		return $types;
	}

	public function is_post_type_enabled( string $post_type ): bool {
		return in_array( $post_type, $this->enabled_post_types(), true );
	}

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	public function register_setting(): void {
		register_setting(
			'bristlecone_markdown',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => $this->defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	public function register_menu(): void {
		add_options_page(
			__( 'Bristlecone Markdown', 'bristlecone-markdown' ),
			__( 'Bristlecone Markdown', 'bristlecone-markdown' ),
			'manage_options',
			'bristlecone-markdown',
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param mixed $value
	 * @return array<string, mixed>
	 */
	public function sanitize( $value ): array {
		$defaults = $this->defaults();
		if ( ! is_array( $value ) ) {
			return $defaults;
		}

		$post_types = array();
		foreach ( $this->available_post_types() as $type => $object ) {
			$post_types[ $type ] = ! empty( $value['post_types'][ $type ] );
		}

		return array(
			'post_types'              => $post_types !== array() ? $post_types : $defaults['post_types'],
			'comments'                => ! empty( $value['comments'] ),
			'syntax_highlighting'     => ! empty( $value['syntax_highlighting'] ),
			'math'                    => ! empty( $value['math'] ),
			'default_to_markdown'     => ! empty( $value['default_to_markdown'] ),
			'jetpack_block_converter' => ! empty( $value['jetpack_block_converter'] ),
			'custom_block_aliases'    => self::sanitize_custom_block_aliases( $value['custom_block_aliases'] ?? '' ),
		);
	}

	/**
	 * @return array<string, \WP_Post_Type>
	 */
	public function available_post_types(): array {
		$types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);
		unset( $types['attachment'] );
		return $types;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->all();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Bristlecone Markdown', 'bristlecone-markdown' ); ?></h1>
			<p><?php echo esc_html__( 'Write posts, pages, and comments in Markdown. Syntax is aligned with iA Writer. HTML is stored on save so content still displays if the plugin is deactivated.', 'bristlecone-markdown' ); ?></p>

			<?php JetpackCompat::instance()->render_settings_notice(); ?>

			<form action="options.php" method="post">
				<?php settings_fields( 'bristlecone_markdown' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Post types', 'bristlecone-markdown' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php echo esc_html__( 'Enable Markdown for these post types', 'bristlecone-markdown' ); ?></legend>
								<?php foreach ( $this->available_post_types() as $type => $object ) : ?>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[post_types][<?php echo esc_attr( $type ); ?>]" value="1" <?php checked( ! empty( $settings['post_types'][ $type ] ) ); ?> />
										<?php echo esc_html( $object->labels->name ); ?>
										<code><?php echo esc_html( $type ); ?></code>
									</label>
									<br />
								<?php endforeach; ?>
								<p class="description"><?php echo esc_html__( 'Whole-document Markdown (Classic Editor, REST API, and iA Writer) is enabled for the selected types. The Markdown block is always available in the block editor.', 'bristlecone-markdown' ); ?></p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Block editor', 'bristlecone-markdown' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[default_to_markdown]" value="1" <?php checked( ! empty( $settings['default_to_markdown'] ) ); ?> />
								<?php echo esc_html__( 'Default to Markdown for new posts and pages', 'bristlecone-markdown' ); ?>
							</label>
							<p class="description"><?php echo esc_html__( 'When enabled, new posts of the types above that use the block editor start with an empty Markdown block, and inserting a new block prefers Markdown instead of a paragraph. Existing content is not changed. The Classic Editor and whole-document Markdown are unaffected. Off by default.', 'bristlecone-markdown' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Comments', 'bristlecone-markdown' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[comments]" value="1" <?php checked( $settings['comments'] ); ?> />
								<?php echo esc_html__( 'Allow visitors to write comments in Markdown', 'bristlecone-markdown' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Code highlighting', 'bristlecone-markdown' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[syntax_highlighting]" value="1" <?php checked( $settings['syntax_highlighting'] ); ?> />
								<?php echo esc_html__( 'Highlight fenced code blocks on the front end (server-side, no extra JavaScript)', 'bristlecone-markdown' ); ?>
							</label>
							<p class="description"><?php echo esc_html__( 'Highlighted output uses the hljs CSS classes. Theme developers can override .hljs rules or dequeue bristlecone-markdown-highlight.', 'bristlecone-markdown' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Mathematics', 'bristlecone-markdown' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[math]" value="1" <?php checked( $settings['math'] ); ?> />
								<?php echo esc_html__( 'Render $inline$ and $$block$$ TeX with KaTeX when a post contains math', 'bristlecone-markdown' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Jetpack Markdown blocks', 'bristlecone-markdown' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[jetpack_block_converter]" value="1" <?php checked( ! empty( $settings['jetpack_block_converter'] ) ); ?> />
								<?php echo esc_html__( 'Enable Jetpack Markdown block converter under Tools', 'bristlecone-markdown' ); ?>
							</label>
							<p class="description">
								<?php echo esc_html__( 'Existing Jetpack Markdown blocks are already editable and convert to Bristlecone Markdown when you save a post. Turn this on only if you want a Tools page that can rewrite matching posts at once, scan for other unregistered blocks whose names contain “markdown”, or convert Simple Markdown and custom identifiers. Off by default to avoid accidents. This does not replace other Markdown plugins.', 'bristlecone-markdown' ); ?>
							</p>
							<?php if ( ! empty( $settings['jetpack_block_converter'] ) ) : ?>
								<p>
									<a href="<?php echo esc_url( admin_url( 'tools.php?page=' . JetpackMarkdownBlock::TOOLS_SLUG ) ); ?>">
										<?php echo esc_html__( 'Open Tools → Bristlecone Markdown Converter', 'bristlecone-markdown' ); ?>
									</a>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Other Markdown blocks', 'bristlecone-markdown' ); ?></th>
						<td>
							<p class="description">
								<?php echo esc_html__( 'simple-markdown/markdown-block is built in (attribute content). Jetpack’s jetpack/markdown (attribute source) stays first-class. Custom names below are optional.', 'bristlecone-markdown' ); ?>
							</p>
							<details>
								<summary><?php echo esc_html__( 'Advanced: custom block identifiers', 'bristlecone-markdown' ); ?></summary>
								<p>
									<label for="bristlecone-markdown-custom-aliases">
										<?php echo esc_html__( 'Custom block names, one per line', 'bristlecone-markdown' ); ?>
									</label>
								</p>
								<textarea
									id="bristlecone-markdown-custom-aliases"
									name="<?php echo esc_attr( self::OPTION ); ?>[custom_block_aliases]"
									rows="5"
									cols="50"
									class="large-text code"
								><?php echo esc_textarea( (string) ( $settings['custom_block_aliases'] ?? '' ) ); ?></textarea>
								<p class="description">
									<?php echo esc_html__( 'Prefer namespace/block-name|attribute (for example acme/markdown|content). If |attribute is omitted, Bristlecone tries source, then content, then markdown when reading the block. Aliases are registered only when that block name is not already registered by another plugin. A Tools scan never adds names here by itself.', 'bristlecone-markdown' ); ?>
								</p>
							</details>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
