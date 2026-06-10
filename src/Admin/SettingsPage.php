<?php
/**
 * Plugin settings page.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Admin;

defined( 'ABSPATH' ) || exit;

use RemoteMediaSource\Source\KeyManager;

/**
 * Registers and renders the Settings > Remote Media Source admin page.
 * Handles form saves and all admin-side AJAX actions.
 */
class SettingsPage {

	/**
	 * Register all hooks for the settings page.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'add_page' ) );
		add_action( 'admin_post_rms_save_settings', array( self::class, 'handle_save' ) );
		add_action( 'wp_ajax_rms_generate_key', array( self::class, 'ajax_generate_key' ) );
		add_action( 'wp_ajax_rms_test_connection', array( self::class, 'ajax_test_connection' ) );
	}

	/**
	 * Register the options page (admin_menu callback).
	 */
	public static function add_page(): void {
		add_options_page(
			esc_html__( 'Remote Media Source', 'remote-media-source' ),
			esc_html__( 'Remote Media Source', 'remote-media-source' ),
			'manage_options',
			'remote-media-source',
			array( self::class, 'render' )
		);
	}

	/**
	 * Render the full settings page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$role = get_option( 'rms_role', '' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Remote Media Source', 'remote-media-source' ); ?></h1>

			<?php if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'remote-media-source' ); ?></p>
				</div>
			<?php endif; ?>

			<?php self::render_key_modal(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'rms_save_settings', 'rms_nonce' ); ?>
				<input type="hidden" name="action" value="rms_save_settings">

				<?php self::render_role_section( $role ); ?>
				<?php self::render_source_section( $role ); ?>
				<?php self::render_consumer_section( $role ); ?>

				<?php submit_button( __( 'Save Settings', 'remote-media-source' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the role selector.
	 *
	 * @param string $role Current role value.
	 */
	private static function render_role_section( string $role ): void {
		?>
		<h2><?php esc_html_e( 'Role', 'remote-media-source' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="rms_role"><?php esc_html_e( 'This site is a', 'remote-media-source' ); ?></label>
				</th>
				<td>
					<select name="rms_role" id="rms_role">
						<option value="" <?php selected( $role, '' ); ?>><?php esc_html_e( '— Disabled —', 'remote-media-source' ); ?></option>
						<option value="source" <?php selected( $role, 'source' ); ?>><?php esc_html_e( 'Source (this site is the media truth)', 'remote-media-source' ); ?></option>
						<option value="consumer" <?php selected( $role, 'consumer' ); ?>><?php esc_html_e( 'Consumer (serve media from remote)', 'remote-media-source' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Set to Source on your production site. Set to Consumer on local/dev/staging.', 'remote-media-source' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the Source role configuration section.
	 *
	 * @param string $role Current role value.
	 */
	private static function render_source_section( string $role ): void {
		$style = 'source' === $role ? '' : 'display:none';
		?>
		<div id="rms-source-settings" style="<?php echo esc_attr( $style ); ?>">
			<h2><?php esc_html_e( 'Source Settings', 'remote-media-source' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Connection Key', 'remote-media-source' ); ?></th>
					<td>
						<?php if ( KeyManager::has_key() ) : ?>
							<code><?php echo esc_html( KeyManager::get_prefix() ); ?>••••••••••••••••••••••••••••••••••••••••••••••••••••••••</code>
							<p>
								<button type="button" class="button" id="rms-regenerate-key">
									<?php esc_html_e( 'Regenerate Key', 'remote-media-source' ); ?>
								</button>
							</p>
							<p class="description">
								<?php esc_html_e( 'Regenerating will invalidate the current key and disconnect all consumers.', 'remote-media-source' ); ?>
							</p>
						<?php else : ?>
							<button type="button" class="button button-primary" id="rms-generate-key">
								<?php esc_html_e( 'Generate Connection Key', 'remote-media-source' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Generate a key to share with consumer environments.', 'remote-media-source' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the Consumer role configuration section.
	 *
	 * @param string $role Current role value.
	 */
	private static function render_consumer_section( string $role ): void {
		$style       = 'consumer' === $role ? '' : 'display:none';
		$remote_url  = (string) get_option( 'rms_remote_url', '' );
		$upload_mode = (string) get_option( 'rms_upload_mode', 'local' );
		$last        = get_option( 'rms_last_connection', array() );
		?>
		<div id="rms-consumer-settings" style="<?php echo esc_attr( $style ); ?>">
			<h2><?php esc_html_e( 'Consumer Settings', 'remote-media-source' ); ?></h2>

			<?php if ( ! empty( $last['success'] ) ) : ?>
				<div class="notice notice-success inline">
					<p>
						<strong><?php esc_html_e( 'Consumer active.', 'remote-media-source' ); ?></strong>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: human-readable time ago */
								__( 'Last verified %s ago.', 'remote-media-source' ),
								human_time_diff( (int) ( $last['timestamp'] ?? 0 ) )
							)
						);
						?>
					</p>
				</div>
			<?php elseif ( 'consumer' === $role ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'Consumer not yet verified. Save settings and click "Test Connection".', 'remote-media-source' ); ?></p>
				</div>
			<?php endif; ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="rms_remote_url"><?php esc_html_e( 'Remote Source URL', 'remote-media-source' ); ?></label>
					</th>
					<td>
						<input type="url" id="rms_remote_url" name="rms_remote_url" class="regular-text"
							value="<?php echo esc_url( $remote_url ); ?>">
						<p class="description">
							<?php esc_html_e( 'Base URL of the source WordPress site (e.g. https://example.com).', 'remote-media-source' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rms_remote_key"><?php esc_html_e( 'Connection Key', 'remote-media-source' ); ?></label>
					</th>
					<td>
						<input type="password" id="rms_remote_key" name="rms_remote_key" class="regular-text"
							autocomplete="new-password"
							placeholder="<?php echo get_option( 'rms_remote_key', '' ) ? esc_attr( __( '(key saved — enter new key to replace)', 'remote-media-source' ) ) : ''; ?>"
							value="">
						<button type="button" class="button" id="rms-reveal-key">
							<?php esc_html_e( 'Reveal', 'remote-media-source' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Connection key copied from the source site. Stored in the database; restrict wp-admin access accordingly.', 'remote-media-source' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Upload Mode', 'remote-media-source' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="rms_upload_mode" value="local"
									<?php checked( $upload_mode, 'local' ); ?>>
								<?php esc_html_e( 'Local only (default) — uploads land in the local filesystem', 'remote-media-source' ); ?>
							</label><br>
							<label>
								<input type="radio" name="rms_upload_mode" value="block"
									<?php checked( $upload_mode, 'block' ); ?>>
								<?php esc_html_e( 'Block uploads — prevent any file uploads on this environment', 'remote-media-source' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Connection', 'remote-media-source' ); ?></th>
					<td>
						<button type="button" class="button" id="rms-test-connection">
							<?php esc_html_e( 'Test Connection', 'remote-media-source' ); ?>
						</button>
						<span id="rms-test-result" style="margin-left:10px;"></span>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the one-time key display modal (hidden until JS shows it).
	 */
	private static function render_key_modal(): void {
		?>
		<div id="rms-key-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:99999;">
			<div style="background:#fff;max-width:600px;margin:80px auto;padding:30px;border-radius:4px;box-shadow:0 4px 20px rgba(0,0,0,.3);">
				<h2><?php esc_html_e( 'Connection Key', 'remote-media-source' ); ?></h2>
				<p>
					<strong style="color:#d63638;">
						<?php esc_html_e( 'Copy this key now. It will not be shown again.', 'remote-media-source' ); ?>
					</strong>
				</p>
				<textarea id="rms-key-modal-value" rows="3"
					style="width:100%;font-family:monospace;font-size:13px;resize:none;"
					readonly></textarea>
				<p style="margin-top:15px;">
					<button type="button" class="button button-primary" id="rms-key-modal-copy">
						<?php esc_html_e( 'Copy Key', 'remote-media-source' ); ?>
					</button>
					<button type="button" class="button" id="rms-key-modal-dismiss" style="margin-left:10px;">
						<?php esc_html_e( "I've copied this key", 'remote-media-source' ); ?>
					</button>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Validate and normalize a consumer remote URL.
	 *
	 * Security boundary for the plugin's entire outbound HTTP surface: only
	 * URLs that survive this method are ever stored or fetched. Enforces an
	 * https-only scheme allowlist (filterable for deliberate local-dev use)
	 * and WordPress' own SSRF guard, wp_http_validate_url(), which rejects
	 * loopback/private hosts unless explicitly allowed via the
	 * http_request_host_is_external filter.
	 *
	 * @param string $raw Unsanitized URL (unslashed form input or option value).
	 * @return string Validated URL without trailing slash, or '' when invalid.
	 */
	private static function sanitize_remote_url( string $raw ): string {
		$url = esc_url_raw( trim( $raw ) );
		if ( '' === $url ) {
			return '';
		}

		/**
		 * Filters the URL schemes a consumer may use for its remote source.
		 *
		 * @param array<string> $schemes Allowed schemes. Default: https only.
		 */
		$schemes = (array) apply_filters( 'remote_media_source_allowed_url_schemes', array( 'https' ) );

		if ( ! in_array( wp_parse_url( $url, PHP_URL_SCHEME ), $schemes, true ) ) {
			return '';
		}

		if ( ! wp_http_validate_url( $url ) ) {
			return '';
		}

		return untrailingslashit( $url );
	}

	/**
	 * Handle the settings form save (admin_post_rms_save_settings).
	 */
	public static function handle_save(): void {
		check_admin_referer( 'rms_save_settings', 'rms_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'remote-media-source' ) );
		}

		$new_role = sanitize_text_field( wp_unslash( $_POST['rms_role'] ?? '' ) );
		if ( ! in_array( $new_role, array( '', 'source', 'consumer' ), true ) ) {
			$new_role = '';
		}

		$old_role = get_option( 'rms_role', '' );

		if ( $new_role !== $old_role ) {
			if ( 'source' === $old_role ) {
				delete_option( 'rms_connection_key_hash' );
				delete_option( 'rms_connection_key_prefix' );
			}
			if ( 'consumer' === $old_role ) {
				delete_option( 'rms_remote_url' );
				delete_option( 'rms_remote_key' );
				delete_option( 'rms_upload_mode' );
				delete_option( 'rms_last_connection' );
			}
		}

		update_option( 'rms_role', $new_role );

		if ( 'consumer' === $new_role ) {
			$new_url = self::sanitize_remote_url( esc_url_raw( wp_unslash( $_POST['rms_remote_url'] ?? '' ) ) );
			$old_url = (string) get_option( 'rms_remote_url', '' );

			if ( $new_url !== $old_url ) {
				delete_option( 'rms_last_connection' );
			}

			update_option( 'rms_remote_url', $new_url );

			// Keys are 64 hex chars (KeyManager::generate). Anything else is
			// noise that must never reach an Authorization header — ignore it
			// and keep whatever valid key is already stored.
			$key = sanitize_text_field( wp_unslash( $_POST['rms_remote_key'] ?? '' ) );
			if ( '' !== $key && preg_match( '/^[a-f0-9]{64}$/i', $key ) ) {
				update_option( 'rms_remote_key', $key );
			}

			$mode = sanitize_text_field( wp_unslash( $_POST['rms_upload_mode'] ?? 'local' ) );
			update_option( 'rms_upload_mode', in_array( $mode, array( 'local', 'block' ), true ) ? $mode : 'local' );
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=remote-media-source&saved=1' ) );
		exit;
	}

	/**
	 * AJAX: generate or regenerate the connection key.
	 *
	 * Returns the raw key once. Never stored after this response.
	 */
	public static function ajax_generate_key(): void {
		check_ajax_referer( 'rms_generate_key', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized.', 'remote-media-source' ) ) );
			return;
		}

		if ( 'source' !== get_option( 'rms_role', '' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'This site is not configured as a source.', 'remote-media-source' ) ) );
			return;
		}

		$key = KeyManager::generate();

		wp_send_json_success(
			array(
				'key'    => $key,
				'prefix' => substr( $key, 0, 8 ),
			)
		);
	}

	/**
	 * AJAX: test the consumer connection to the remote source.
	 */
	public static function ajax_test_connection(): void {
		check_ajax_referer( 'rms_test_connection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized.', 'remote-media-source' ) ) );
			return;
		}

		// Re-validate at request time so a value written outside handle_save()
		// (direct option edits, imports) cannot widen the outbound surface.
		$url = self::sanitize_remote_url( (string) get_option( 'rms_remote_url', '' ) );
		$key = (string) get_option( 'rms_remote_key', '' );

		if ( ! $url ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Remote Source URL is not configured or not a valid HTTPS URL.', 'remote-media-source' ) ) );
			return;
		}

		// REST verify handshake. Redirects are refused: following one could
		// re-send the connection key to an unintended host.
		$response = wp_remote_get(
			esc_url_raw( $url . '/wp-json/rms/v1/verify' ),
			array(
				'headers'            => array( 'Authorization' => 'RMS ' . $key ),
				'timeout'            => 15,
				'redirection'        => 0,
				'reject_unsafe_urls' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: error message */
						esc_html__( 'Verification request failed: %s', 'remote-media-source' ),
						$response->get_error_message()
					),
				)
			);
			return;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 300 && $code < 400 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'The remote URL redirected. Configure the final URL directly — redirects are not followed.', 'remote-media-source' ) ) );
			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['verified'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Remote did not verify. Check your connection key.', 'remote-media-source' ) ) );
			return;
		}

		// The source reports its real uploads baseurl so consumers work against
		// non-standard layouts (Bedrock app/uploads, custom upload_url_path,
		// multisite /sites/N). Only a baseurl on the SAME HOST as the
		// configured remote is honored — a compromised source must not be able
		// to point this site's media at an arbitrary third-party domain.
		$reported        = esc_url_raw( (string) ( $body['uploads_baseurl'] ?? '' ) );
		$uploads_baseurl = '';
		if ( '' !== $reported && wp_parse_url( $reported, PHP_URL_HOST ) === wp_parse_url( $url, PHP_URL_HOST ) ) {
			$uploads_baseurl = untrailingslashit( $reported );
		}

		$status = array(
			'timestamp'       => time(),
			'success'         => true,
			'uploads_baseurl' => $uploads_baseurl,
			'message'         => sprintf(
				/* translators: %s: remote site name */
				esc_html__( 'Connected to %s', 'remote-media-source' ),
				sanitize_text_field( $body['site_name'] ?? 'remote site' )
			),
		);

		update_option( 'rms_last_connection', $status );

		wp_send_json_success( $status );
	}
}
