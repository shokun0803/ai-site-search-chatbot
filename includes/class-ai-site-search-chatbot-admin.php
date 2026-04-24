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
				'validateEndpoint' => rest_url( AISite_Search_Chatbot::REST_NAMESPACE . '/validate' ),
				'testChatEndpoint' => rest_url( AISite_Search_Chatbot::REST_NAMESPACE . '/test-chat' ),
				'restNonce'        => wp_create_nonce( 'wp_rest' ),
				'optionKey'        => AISite_Search_Chatbot::OPTION_KEY,
				'i18n'             => array(
					'apiKeyHelpTitle'       => __( 'How to get your API Key:', 'ai-site-search-chatbot' ),
					'noteTitle'             => __( 'Note:', 'ai-site-search-chatbot' ),
					'modelExample'          => __( 'Enter the exact model ID. Example:', 'ai-site-search-chatbot' ),
					'modelReference'        => __( 'Model ID reference:', 'ai-site-search-chatbot' ),
					'assistantReply'        => __( 'Assistant Reply', 'ai-site-search-chatbot' ),
					'referencedSources'     => __( 'Referenced Sources', 'ai-site-search-chatbot' ),
					'validationMissing'     => __( 'Enter an API key and model ID before running validation.', 'ai-site-search-chatbot' ),
					'validationRunning'     => __( 'Validating connection...', 'ai-site-search-chatbot' ),
					'validationSuccess'     => __( 'Validation succeeded.', 'ai-site-search-chatbot' ),
					'validationFailed'      => __( 'Validation failed.', 'ai-site-search-chatbot' ),
					'validationRequestFail' => __( 'Validation request failed. Please try again.', 'ai-site-search-chatbot' ),
					'adminTestMissingModel' => __( 'Enter an API key and model ID before running the admin chat test.', 'ai-site-search-chatbot' ),
					'adminTestMissingText'  => __( 'Enter a test question before running the admin chat test.', 'ai-site-search-chatbot' ),
					'adminTestRunning'      => __( 'Running admin chat test...', 'ai-site-search-chatbot' ),
					'adminTestSuccess'      => __( 'Admin chat test succeeded.', 'ai-site-search-chatbot' ),
					'adminTestFailed'       => __( 'The admin chat test failed.', 'ai-site-search-chatbot' ),
					'adminTestRequestFail'  => __( 'The admin chat test request failed. Please try again.', 'ai-site-search-chatbot' ),
				),
			)
		);
	}

	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = AISite_Search_Chatbot::get_settings();
		$providers = AISite_Search_Chatbot::get_providers_config();
		$widget_themes = AISite_Search_Chatbot::get_widget_themes();
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
					<tr>
						<th scope="row"><label for="aiscb_api_key"><?php echo esc_html( __( 'API Key', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="aiscb_api_key" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>" autocomplete="off" />
							<p class="description" id="aiscb_api_key_help"></p>
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
		</div>
		<?php
	}
}
