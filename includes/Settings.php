<?php

declare(strict_types=1);

namespace Bristlecone\BitsMarkdown;

/**
 * Plugin settings stored in bits_markdown_settings.
 */
final class Settings {

	public const OPTION = 'bits_markdown_settings';

	private static ?self $instance = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * @return array{
	 *   post_types: array<string, bool>,
	 *   comments: bool,
	 *   syntax_highlighting: bool,
	 *   math: bool
	 * }
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, $this->defaults() );
	}

	/**
	 * @return array{
	 *   post_types: array<string, bool>,
	 *   comments: bool,
	 *   syntax_highlighting: bool,
	 *   math: bool
	 * }
	 */
	public function defaults(): array {
		return array(
			'post_types'          => array(
				'post' => true,
				'page' => true,
			),
			'comments'            => true,
			'syntax_highlighting' => true,
			'math'                => true,
		);
	}

	public function ensure_defaults(): void {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $this->defaults() );
		}
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
			'bits_markdown',
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
			__( 'BITS Markdown', 'bits-markdown' ),
			__( 'BITS Markdown', 'bits-markdown' ),
			'manage_options',
			'bits-markdown',
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
			'post_types'          => $post_types !== array() ? $post_types : $defaults['post_types'],
			'comments'            => ! empty( $value['comments'] ),
			'syntax_highlighting' => ! empty( $value['syntax_highlighting'] ),
			'math'                => ! empty( $value['math'] ),
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
			<h1><?php echo esc_html__( 'BITS Markdown', 'bits-markdown' ); ?></h1>
			<p><?php echo esc_html__( 'Write posts, pages, and comments in Markdown. Syntax is aligned with iA Writer. HTML is stored on save so content still displays if the plugin is deactivated.', 'bits-markdown' ); ?></p>

			<?php JetpackCompat::instance()->render_settings_notice(); ?>

			<form action="options.php" method="post">
				<?php settings_fields( 'bits_markdown' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Post types', 'bits-markdown' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php echo esc_html__( 'Enable Markdown for these post types', 'bits-markdown' ); ?></legend>
								<?php foreach ( $this->available_post_types() as $type => $object ) : ?>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[post_types][<?php echo esc_attr( $type ); ?>]" value="1" <?php checked( ! empty( $settings['post_types'][ $type ] ) ); ?> />
										<?php echo esc_html( $object->labels->name ); ?>
										<code><?php echo esc_html( $type ); ?></code>
									</label>
									<br />
								<?php endforeach; ?>
								<p class="description"><?php echo esc_html__( 'Whole-document Markdown (Classic Editor, REST API, and iA Writer) is enabled for the selected types. The Markdown block is always available in the block editor.', 'bits-markdown' ); ?></p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Comments', 'bits-markdown' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[comments]" value="1" <?php checked( $settings['comments'] ); ?> />
								<?php echo esc_html__( 'Allow visitors to write comments in Markdown', 'bits-markdown' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Code highlighting', 'bits-markdown' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[syntax_highlighting]" value="1" <?php checked( $settings['syntax_highlighting'] ); ?> />
								<?php echo esc_html__( 'Highlight fenced code blocks on the front end (server-side, no extra JavaScript)', 'bits-markdown' ); ?>
							</label>
							<p class="description"><?php echo esc_html__( 'Highlighted output uses the hljs CSS classes. Theme developers can override .hljs rules or dequeue bits-markdown-highlight.', 'bits-markdown' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Mathematics', 'bits-markdown' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[math]" value="1" <?php checked( $settings['math'] ); ?> />
								<?php echo esc_html__( 'Render $inline$ and $$block$$ TeX with KaTeX when a post contains math', 'bits-markdown' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
