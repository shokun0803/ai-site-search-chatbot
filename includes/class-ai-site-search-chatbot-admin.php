<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AISite_Search_Chatbot_Admin {
	private static $settings_page_hook = '';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_admin_menu(): void {
		self::$settings_page_hook = (string) add_options_page(
			__( 'AI Site Search Chatbot', 'ai-site-search-chatbot' ),
			__( 'AI Site Search Chatbot', 'ai-site-search-chatbot' ),
			'manage_options',
			'ai-site-search-chatbot',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( self::$settings_page_hook !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'aiscb-admin',
			plugins_url( 'assets/css/aiscb-admin.css', AISCB_FILE ),
			array(),
			AISite_Search_Chatbot::VERSION
		);

		wp_enqueue_script(
			'aiscb-admin',
			plugins_url( 'assets/js/aiscb-admin.js', AISCB_FILE ),
			array(),
			AISite_Search_Chatbot::VERSION,
			true
		);

		wp_localize_script(
			'aiscb-admin',
			'AISCBAdmin',
			array(
				'providers'        => AISite_Search_Chatbot::get_providers_config(),
				'credentialStatus' => AISite_Search_Chatbot::get_admin_credential_status(),
				'validateEndpoint' => rest_url( AISite_Search_Chatbot::REST_NAMESPACE . '/validate' ),
				'testChatEndpoint' => rest_url( AISite_Search_Chatbot::REST_NAMESPACE . '/test-chat' ),
				'restNonce'        => wp_create_nonce( 'wp_rest' ),
				'optionKey'        => AISite_Search_Chatbot::OPTION_KEY,
				'i18n'             => array(
					'apiKeyHelpTitle'          => __( 'How to get your API Key:', 'ai-site-search-chatbot' ),
					'bearerTokenHelpTitle'     => __( 'How to get your Bearer Token:', 'ai-site-search-chatbot' ),
					'noteTitle'                => __( 'Note:', 'ai-site-search-chatbot' ),
					'modelExample'             => __( 'Enter the exact model ID. Example:', 'ai-site-search-chatbot' ),
					'modelReference'           => __( 'Model ID reference:', 'ai-site-search-chatbot' ),
					'assistantReply'           => __( 'Assistant Reply', 'ai-site-search-chatbot' ),
					'referencedSources'        => __( 'Referenced Sources', 'ai-site-search-chatbot' ),
					'validationMissing'        => __( 'Enter an API key and model ID before running validation.', 'ai-site-search-chatbot' ),
					'validationMissingBearer'  => __( 'Enter a bearer token and model ID before running validation.', 'ai-site-search-chatbot' ),
					'validationRunning'        => __( 'Validating connection...', 'ai-site-search-chatbot' ),
					'validationSuccess'        => __( 'Validation succeeded.', 'ai-site-search-chatbot' ),
					'validationFailed'         => __( 'Validation failed.', 'ai-site-search-chatbot' ),
					'validationRequestFail'    => __( 'Validation request failed. Please try again.', 'ai-site-search-chatbot' ),
					'adminTestMissingModel'    => __( 'Enter an API key and model ID before running the admin chat test.', 'ai-site-search-chatbot' ),
					'adminTestMissingBearer'   => __( 'Enter a bearer token and model ID before running the admin chat test.', 'ai-site-search-chatbot' ),
					'adminTestMissingText'     => __( 'Enter a test question before running the admin chat test.', 'ai-site-search-chatbot' ),
					'adminTestRunning'         => __( 'Running admin chat test...', 'ai-site-search-chatbot' ),
					'adminTestSuccess'         => __( 'Admin chat test succeeded.', 'ai-site-search-chatbot' ),
					'adminTestFailed'          => __( 'The admin chat test failed.', 'ai-site-search-chatbot' ),
					'adminTestRequestFail'     => __( 'The admin chat test request failed. Please try again.', 'ai-site-search-chatbot' ),
					'apiKeyStored'             => __( 'A saved API key is available for this provider. Enter a new value only if you want to replace it.', 'ai-site-search-chatbot' ),
					'apiKeyConfig'             => __( 'A server-defined API key is active for this provider. Enter a value here only if you want to save a database fallback.', 'ai-site-search-chatbot' ),
					'apiKeyEmpty'              => __( 'No saved API key exists for this provider yet.', 'ai-site-search-chatbot' ),
					'bearerTokenStored'        => __( 'A saved bearer token is available. Enter a new value only if you want to replace it.', 'ai-site-search-chatbot' ),
					'bearerTokenConfig'        => __( 'A server-defined bearer token is active. Enter a value here only if you want to save a database fallback.', 'ai-site-search-chatbot' ),
					'bearerTokenEmpty'         => __( 'No saved bearer token exists yet.', 'ai-site-search-chatbot' ),
				),
			)
		);
	}

	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = AISite_Search_Chatbot::get_settings();
		$chat_logs = AISite_Search_Chatbot::get_chat_logs();
		$providers = AISite_Search_Chatbot::get_providers_config();
		$widget_themes = AISite_Search_Chatbot::get_widget_themes();
		$widget_display_modes = AISite_Search_Chatbot::get_widget_display_modes();
		?>
		<div class="wrap aiscb-admin">
			<h1><?php echo esc_html( __( 'AI Site Search Chatbot', 'ai-site-search-chatbot' ) ); ?></h1>
			<p><?php echo esc_html( __( 'Configure the AI provider and the prompt used when answering visitors with site search results.', 'ai-site-search-chatbot' ) ); ?></p>
			<form method="post" action="options.php" id="aiscb-settings-form">
				<?php settings_fields( AISite_Search_Chatbot::OPTION_GROUP ); ?>
				<div class="aiscb-provider-selector">
					<h2 class="aiscb-section-title"><?php echo esc_html( __( 'AI Provider', 'ai-site-search-chatbot' ) ); ?></h2>
					<div class="aiscb-provider-grid">
						<?php foreach ( $providers as $provider_key => $provider_info ) : ?>
							<label class="aiscb-provider-option" data-provider="<?php echo esc_attr( $provider_key ); ?>">
								<input
									type="radio"
									name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[ai_provider]"
									value="<?php echo esc_attr( $provider_key ); ?>"
									<?php checked( $settings['ai_provider'], $provider_key ); ?>
									class="aiscb-provider-radio"
								/>
								<div class="aiscb-provider-summary">
									<strong class="aiscb-provider-label"><?php echo esc_html( $provider_info['label'] ); ?></strong>
									<small class="aiscb-provider-description"><?php echo esc_html( $provider_info['description'] ); ?></small>
								</div>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div id="aiscb-provider-info" class="aiscb-provider-info">
					<div id="aiscb-provider-info-content"></div>
				</div>

				<table class="form-table" role="presentation">
					<tr id="aiscb_claude_auth_mode_row" hidden>
						<th scope="row"><?php echo esc_html( __( 'Authentication Method', 'ai-site-search-chatbot' ) ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="radio"
										name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[claude_auth_mode]"
										id="aiscb_claude_auth_mode_api_key"
										value="api_key"
										<?php checked( $settings['claude_auth_mode'] ?? 'api_key', 'api_key' ); ?>
									/>
									<?php echo esc_html( __( 'API Key (pay-per-use)', 'ai-site-search-chatbot' ) ); ?>
								</label>
								<br />
								<label>
									<input type="radio"
										name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[claude_auth_mode]"
										id="aiscb_claude_auth_mode_bearer_token"
										value="bearer_token"
										<?php checked( $settings['claude_auth_mode'] ?? 'api_key', 'bearer_token' ); ?>
									/>
									<?php echo esc_html( __( 'Bearer Token (Agent SDK credits)', 'ai-site-search-chatbot' ) ); ?>
								</label>
							</fieldset>
							<p class="description"><?php echo esc_html( __( 'API Key uses pay-per-use billing. Bearer Token uses your Claude subscription monthly credits (Pro, Max, Team, or Enterprise plan required).', 'ai-site-search-chatbot' ) ); ?></p>
						</td>
					</tr>
					<tr id="aiscb_api_key_row">
						<th scope="row"><label for="aiscb_api_key"><?php echo esc_html( __( 'API Key', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="aiscb_api_key" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[api_key]" value="" autocomplete="off" />
							<p class="description" id="aiscb_api_key_status"></p>
							<p class="description" id="aiscb_api_key_help"></p>
						</td>
					</tr>
					<tr id="aiscb_claude_bearer_token_row" hidden>
						<th scope="row"><label for="aiscb_claude_bearer_token"><?php echo esc_html( __( 'Bearer Token', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="aiscb_claude_bearer_token" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[claude_bearer_token]" value="" autocomplete="off" />
							<p class="description" id="aiscb_claude_bearer_token_status"></p>
							<p class="description"><?php echo esc_html( __( 'The auth token from your Claude account. Obtained by authenticating with Claude Code (claude auth login) and copying the session token.', 'ai-site-search-chatbot' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiscb_model"><?php echo esc_html( __( 'Model', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<div class="aiscb-model-container">
								<input type="text" class="regular-text code" id="aiscb_model" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[model]" value="<?php echo esc_attr( $settings['model'] ); ?>" spellcheck="false" autocomplete="off" />
								<p class="description"><?php echo esc_html( __( 'The AI model to use for generating answers.', 'ai-site-search-chatbot' ) ); ?></p>
								<p class="description" id="aiscb_model_help"></p>
								<p class="description" id="aiscb_model_reference"></p>
								<p>
									<button type="button" class="button button-secondary" id="aiscb_validate_button"><?php echo esc_html( __( 'Validate API Key and Model', 'ai-site-search-chatbot' ) ); ?></button>
								</p>
								<div id="aiscb_validation_result" class="notice inline aiscb-notice" hidden></div>
								<div class="aiscb-admin-test">
									<strong class="aiscb-admin-test-title"><?php echo esc_html( __( 'Admin Chat Test', 'ai-site-search-chatbot' ) ); ?></strong>
									<p class="description aiscb-admin-test-description"><?php echo esc_html( __( 'Test a real chatbot reply in the admin screen with the current API key, model, and system prompt before exposing it publicly.', 'ai-site-search-chatbot' ) ); ?></p>
									<textarea class="large-text" rows="4" id="aiscb_test_message" placeholder="<?php echo esc_attr( __( 'Enter a sample visitor question to test the chatbot.', 'ai-site-search-chatbot' ) ); ?>"></textarea>
									<p>
										<button type="button" class="button button-secondary" id="aiscb_test_chat_button"><?php echo esc_html( __( 'Run Admin Chat Test', 'ai-site-search-chatbot' ) ); ?></button>
									</p>
									<div id="aiscb_test_result" class="notice inline aiscb-notice" hidden></div>
									<div id="aiscb_test_answer" class="aiscb-test-answer" hidden></div>
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiscb_system_prompt"><?php echo esc_html( __( 'System Prompt', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<textarea class="large-text" rows="7" id="aiscb_system_prompt" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[system_prompt]"><?php echo esc_textarea( $settings['system_prompt'] ); ?></textarea>
							<p class="description"><?php echo esc_html( __( 'The system prompt that instructs the AI on how to behave.', 'ai-site-search-chatbot' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiscb_max_sources"><?php echo esc_html( __( 'Maximum Sources', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<input type="number" min="1" max="10" id="aiscb_max_sources" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[max_sources]" value="<?php echo esc_attr( (string) absint( $settings['max_sources'] ) ); ?>" />
							<p class="description"><?php echo esc_html( __( 'Maximum number of search results to use as sources.', 'ai-site-search-chatbot' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiscb_ai_limit_window_10m"><?php echo esc_html( __( 'AI Reply Limit (10 minutes)', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<input type="number" min="1" max="30" id="aiscb_ai_limit_window_10m" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[ai_limit_window_10m]" value="<?php echo esc_attr( (string) absint( $settings['ai_limit_window_10m'] ) ); ?>" />
							<p class="description"><?php echo esc_html( __( 'Limit the number of AI replies allowed from the same connection within 10 minutes. Normal short conversations should still pass, while repeated trial-and-error questions are switched to site-search-based guidance only. A practical starting point is around 6 to 10.', 'ai-site-search-chatbot' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiscb_ai_limit_window_1h"><?php echo esc_html( __( 'AI Reply Limit (1 hour)', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<input type="number" min="1" max="100" id="aiscb_ai_limit_window_1h" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[ai_limit_window_1h]" value="<?php echo esc_attr( (string) absint( $settings['ai_limit_window_1h'] ) ); ?>" />
							<p class="description"><?php echo esc_html( __( 'Cap the total AI replies allowed from the same connection within 1 hour. Use this to suppress prolonged back-and-forth play while keeping normal customer use available. Set this higher than the 10-minute limit; a practical starting point is around 20 to 40.', 'ai-site-search-chatbot' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( __( 'Chatbot Display', 'ai-site-search-chatbot' ) ); ?></th>
						<td>
							<label for="aiscb_widget_enabled">
								<input type="checkbox" id="aiscb_widget_enabled" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[widget_enabled]" value="1" <?php checked( ! empty( $settings['widget_enabled'] ) ); ?> />
								<?php echo esc_html( __( 'Enable chatbot display on the public site', 'ai-site-search-chatbot' ) ); ?>
							</label>
							<p class="description"><?php echo esc_html( __( 'The chatbot is not shown on the public site until this setting is enabled.', 'ai-site-search-chatbot' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiscb_widget_display_mode"><?php echo esc_html( __( 'Display Location', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<select id="aiscb_widget_display_mode" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[widget_display_mode]">
								<?php foreach ( $widget_display_modes as $mode_key => $mode_info ) : ?>
									<option value="<?php echo esc_attr( $mode_key ); ?>" <?php selected( $settings['widget_display_mode'], $mode_key ); ?>><?php echo esc_html( $mode_info['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<div class="aiscb-theme-descriptions">
								<?php foreach ( $widget_display_modes as $mode_info ) : ?>
									<p class="description"><?php echo esc_html( $mode_info['label'] . ': ' . $mode_info['description'] ); ?></p>
								<?php endforeach; ?>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiscb_widget_theme"><?php echo esc_html( __( 'Chatbot Design', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<select id="aiscb_widget_theme" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[widget_theme]">
								<?php foreach ( $widget_themes as $theme_key => $theme_info ) : ?>
									<option value="<?php echo esc_attr( $theme_key ); ?>" <?php selected( $settings['widget_theme'], $theme_key ); ?>><?php echo esc_html( $theme_info['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php echo esc_html( __( 'Choose the floating chatbot design shown at the bottom right of the public site.', 'ai-site-search-chatbot' ) ); ?></p>
							<div class="aiscb-theme-descriptions">
								<?php foreach ( $widget_themes as $theme_info ) : ?>
									<p class="description"><?php echo esc_html( $theme_info['label'] . ': ' . $theme_info['description'] ); ?></p>
								<?php endforeach; ?>
							</div>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<?php self::render_chat_logs_panel( $chat_logs ); ?>
		</div>
		<?php
	}

	private static function render_chat_logs_panel( array $chat_logs ): void {
		$status_map = self::get_chat_log_status_map();
		?>
		<div class="aiscb-log-panel">
			<div class="aiscb-log-panel__header">
				<div>
					<h2 class="aiscb-section-title"><?php echo esc_html( __( 'Recent Visitor Chat Logs', 'ai-site-search-chatbot' ) ); ?></h2>
					<p class="description"><?php echo esc_html( sprintf( __( 'The latest %d public chat interactions are stored here so you can review visitor questions and how the chatbot responded.', 'ai-site-search-chatbot' ), AISite_Search_Chatbot::CHAT_LOG_LIMIT ) ); ?></p>
				</div>
				<div class="aiscb-log-panel__count"><?php echo esc_html( sprintf( __( '%d entries', 'ai-site-search-chatbot' ), count( $chat_logs ) ) ); ?></div>
			</div>

			<div class="aiscb-log-legend" aria-label="<?php echo esc_attr__( 'Chat log status legend', 'ai-site-search-chatbot' ); ?>">
				<?php foreach ( $status_map as $status ) : ?>
					<span class="aiscb-log-pill aiscb-log-pill--<?php echo esc_attr( $status['tone'] ); ?>">
						<span class="dashicons <?php echo esc_attr( $status['icon'] ); ?>" aria-hidden="true"></span>
						<?php echo esc_html( $status['label'] ); ?>
					</span>
				<?php endforeach; ?>
			</div>

			<?php if ( empty( $chat_logs ) ) : ?>
				<p class="aiscb-log-empty"><?php echo esc_html( __( 'No public chat logs have been recorded yet.', 'ai-site-search-chatbot' ) ); ?></p>
			<?php else : ?>
				<div class="aiscb-log-table-wrap">
					<table class="widefat fixed striped aiscb-log-table">
						<thead>
							<tr>
								<th><?php echo esc_html( __( 'Status', 'ai-site-search-chatbot' ) ); ?></th>
								<th><?php echo esc_html( __( 'Time', 'ai-site-search-chatbot' ) ); ?></th>
								<th><?php echo esc_html( __( 'Visitor Message', 'ai-site-search-chatbot' ) ); ?></th>
								<th><?php echo esc_html( __( 'Reply', 'ai-site-search-chatbot' ) ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $chat_logs as $log ) : ?>
								<?php
								$status_key = isset( $log['status'] ) ? (string) $log['status'] : 'unknown';
								$status = $status_map[ $status_key ] ?? $status_map['unknown'];
								$timestamp = isset( $log['time'] ) ? absint( $log['time'] ) : 0;
								?>
								<tr>
									<td class="aiscb-log-table__status">
										<span class="aiscb-log-pill aiscb-log-pill--<?php echo esc_attr( $status['tone'] ); ?>">
											<span class="dashicons <?php echo esc_attr( $status['icon'] ); ?>" aria-hidden="true"></span>
											<?php echo esc_html( $status['label'] ); ?>
										</span>
									</td>
									<td class="aiscb-log-table__time">
										<div><?php echo esc_html( $timestamp ? wp_date( 'Y-m-d H:i:s', $timestamp ) : '-' ); ?></div>
										<div class="aiscb-log-meta"><?php echo esc_html( sprintf( __( 'IP: %s', 'ai-site-search-chatbot' ), self::mask_ip_address( isset( $log['ip'] ) ? (string) $log['ip'] : '' ) ) ); ?></div>
										<div class="aiscb-log-meta"><?php echo esc_html( sprintf( __( 'Sources: %d', 'ai-site-search-chatbot' ), isset( $log['source_count'] ) ? absint( $log['source_count'] ) : 0 ) ); ?></div>
									</td>
									<td><?php self::render_log_text_block( isset( $log['question'] ) ? (string) $log['question'] : '' ); ?></td>
									<td><?php self::render_log_text_block( isset( $log['answer'] ) ? (string) $log['answer'] : '' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_log_text_block( string $text ): void {
		$text = trim( $text );

		if ( '' === $text ) {
			echo '<span class="aiscb-log-meta">-</span>';

			return;
		}

		if ( strlen( $text ) <= 180 ) {
			printf( '<div class="aiscb-log-text">%s</div>', esc_html( $text ) );

			return;
		}

		printf( '<div class="aiscb-log-text">%s</div>', esc_html( substr( $text, 0, 180 ) . '...' ) );
		?>
		<details class="aiscb-log-details">
			<summary><?php echo esc_html( __( 'Show full text', 'ai-site-search-chatbot' ) ); ?></summary>
			<div class="aiscb-log-details__body"><?php echo esc_html( $text ); ?></div>
		</details>
		<?php
	}

	private static function get_chat_log_status_map(): array {
		return array(
			'ai-generated' => array(
				'label' => __( 'AI Reply', 'ai-site-search-chatbot' ),
				'icon'  => 'dashicons-format-chat',
				'tone'  => 'success',
			),
			'ai-cached' => array(
				'label' => __( 'Cached Reply', 'ai-site-search-chatbot' ),
				'icon'  => 'dashicons-update',
				'tone'  => 'info',
			),
			'ai-limited' => array(
				'label' => __( 'AI Limit Hit', 'ai-site-search-chatbot' ),
				'icon'  => 'dashicons-clock',
				'tone'  => 'warning',
			),
			'request-blocked' => array(
				'label' => __( 'Spam Blocked', 'ai-site-search-chatbot' ),
				'icon'  => 'dashicons-shield-alt',
				'tone'  => 'danger',
			),
			'fallback-no-results' => array(
				'label' => __( 'No Search Match', 'ai-site-search-chatbot' ),
				'icon'  => 'dashicons-search',
				'tone'  => 'muted',
			),
			'fallback-provider-error' => array(
				'label' => __( 'AI Error Fallback', 'ai-site-search-chatbot' ),
				'icon'  => 'dashicons-warning',
				'tone'  => 'warning',
			),
			'fallback-no-config' => array(
				'label' => __( 'Search Only', 'ai-site-search-chatbot' ),
				'icon'  => 'dashicons-admin-site-alt3',
				'tone'  => 'muted',
			),
			'unknown' => array(
				'label' => __( 'Other', 'ai-site-search-chatbot' ),
				'icon'  => 'dashicons-marker',
				'tone'  => 'muted',
			),
		);
	}

	private static function mask_ip_address( string $ip_address ): string {
		if ( '' === $ip_address ) {
			return '-';
		}

		if ( false !== strpos( $ip_address, ':' ) ) {
			$parts = explode( ':', $ip_address );

			if ( count( $parts ) > 2 ) {
				return implode( ':', array_slice( $parts, 0, 2 ) ) . ':*';
			}
		}

		if ( false !== strpos( $ip_address, '.' ) ) {
			$parts = explode( '.', $ip_address );

			if ( 4 === count( $parts ) ) {
				$parts[3] = '*';
				return implode( '.', $parts );
			}
		}

		return $ip_address;
	}
}
