<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AISite_Search_Chatbot_Admin {
	private static $settings_page_hook = '';
	private static $knowledge_page_hook = '';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_aiscb_delete_data', array( __CLASS__, 'handle_delete_data_action' ) );
		add_action( 'admin_post_aiscb_clear_cache', array( __CLASS__, 'handle_clear_cache_action' ) );
		add_action( 'admin_post_aiscb_update_knowledge_editors', array( __CLASS__, 'handle_update_knowledge_editors_action' ) );
	}

	public static function register_admin_menu(): void {
		self::$settings_page_hook = (string) add_options_page(
			__( 'AI Site Search Chatbot', 'ai-site-search-chatbot' ),
			__( 'AI Site Search Chatbot', 'ai-site-search-chatbot' ),
			'manage_options',
			'ai-site-search-chatbot',
			array( __CLASS__, 'render_settings_page' )
		);

		self::$knowledge_page_hook = (string) add_menu_page(
			__( 'AI Site Search Chatbot - Saved Knowledge Base', 'ai-site-search-chatbot' ),
			__( 'Saved Knowledge Base', 'ai-site-search-chatbot' ),
			AISite_Search_Chatbot::KNOWLEDGE_EDITOR_CAP,
			'ai-site-search-chatbot-knowledge',
			array( __CLASS__, 'render_knowledge_only_page' ),
			'dashicons-database'
		);
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( self::$settings_page_hook !== $hook_suffix && self::$knowledge_page_hook !== $hook_suffix ) {
			return;
		}

		$current_tab = self::get_current_tab();

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

		if ( self::$settings_page_hook === $hook_suffix && 'logs' === $current_tab ) {
			wp_enqueue_script(
				'aiscb-metrics',
				plugins_url( 'assets/js/aiscb-metrics.js', AISCB_FILE ),
				array(),
				AISite_Search_Chatbot::VERSION,
				true
			);
		}

		wp_localize_script(
			'aiscb-admin',
			'AISCBAdmin',
			array(
				'providers'        => AISite_Search_Chatbot::get_providers_config(),
				'knowledgeStatuses' => array(
					'draft'    => __( 'Draft', 'ai-site-search-chatbot' ),
					'approved' => __( 'Approved', 'ai-site-search-chatbot' ),
					'archived' => __( 'Archived', 'ai-site-search-chatbot' ),
				),
				'knowledgeBaseMatchModes' => AISite_Search_Chatbot::get_knowledge_base_match_modes(),
				'uninstallCleanupModes' => AISite_Search_Chatbot::get_uninstall_cleanup_modes(),
				'connectorStatus' => AISite_Search_Chatbot::get_connector_status(),
				'connectorsPageUrl' => admin_url( 'options-connectors.php' ),
				'pluginInstallUrl' => admin_url( 'plugin-install.php' ),
				'validateEndpoint' => rest_url( AISite_Search_Chatbot::REST_NAMESPACE . '/validate' ),
				'testChatEndpoint' => rest_url( AISite_Search_Chatbot::REST_NAMESPACE . '/test-chat' ),
				'knowledgeBaseEndpoint' => rest_url( AISite_Search_Chatbot::REST_NAMESPACE . '/knowledge-base' ),
				'knowledgeBaseExportEndpoint' => rest_url( AISite_Search_Chatbot::REST_NAMESPACE . '/knowledge-base/export' ),
				'knowledgeBaseImportEndpoint' => rest_url( AISite_Search_Chatbot::REST_NAMESPACE . '/knowledge-base/import' ),
				'knowledgeBasePageUrl' => admin_url( 'options-general.php?page=ai-site-search-chatbot&tab=knowledge-base' ),
				'knowledgeFullAccess' => current_user_can( 'manage_options' ),
				'restNonce'        => wp_create_nonce( 'wp_rest' ),
				'optionKey'        => AISite_Search_Chatbot::OPTION_KEY,
				'i18n'             => array(
					'noteTitle'                => __( 'Note:', 'ai-site-search-chatbot' ),
					'modelExample'             => __( 'Enter the exact model ID. Example:', 'ai-site-search-chatbot' ),
					'modelReference'           => __( 'Model ID reference:', 'ai-site-search-chatbot' ),
					'assistantReply'           => __( 'Assistant Reply', 'ai-site-search-chatbot' ),
					'referencedSources'        => __( 'Referenced Sources', 'ai-site-search-chatbot' ),
					'validationMissing'        => __( 'Enter a model ID before running validation.', 'ai-site-search-chatbot' ),
					'validationRunning'        => __( 'Validating connection...', 'ai-site-search-chatbot' ),
					'validationSuccess'        => __( 'Validation succeeded.', 'ai-site-search-chatbot' ),
					'validationFailed'         => __( 'Validation failed.', 'ai-site-search-chatbot' ),
					'validationRequestFail'    => __( 'Validation request failed. Please try again.', 'ai-site-search-chatbot' ),
					'adminTestMissingModel'    => __( 'Enter a model ID before running the admin chat test.', 'ai-site-search-chatbot' ),
					'adminTestMissingText'     => __( 'Enter a test question before running the admin chat test.', 'ai-site-search-chatbot' ),
					'adminTestRunning'         => __( 'Running admin chat test...', 'ai-site-search-chatbot' ),
					'adminTestSuccess'         => __( 'Admin chat test succeeded.', 'ai-site-search-chatbot' ),
					'adminTestFailed'          => __( 'The admin chat test failed.', 'ai-site-search-chatbot' ),
					'adminTestRequestFail'     => __( 'The admin chat test request failed. Please try again.', 'ai-site-search-chatbot' ),
					'connectorConnected'       => __( 'Connected via Settings > Connectors.', 'ai-site-search-chatbot' ),
					'connectorNotConnected'    => __( 'Not connected yet.', 'ai-site-search-chatbot' ),
					'connectorPluginMissing'   => __( 'The official AI provider plugin for this provider is not installed or active.', 'ai-site-search-chatbot' ),
					'connectorManageLink'      => __( 'Manage connections in Settings > Connectors', 'ai-site-search-chatbot' ),
					'connectorInstallLink'     => __( 'Install the provider plugin', 'ai-site-search-chatbot' ),
					'knowledgeLoadFailed'      => __( 'The saved knowledge list could not be loaded.', 'ai-site-search-chatbot' ),
					'knowledgeSaveFailed'      => __( 'The knowledge entry could not be saved.', 'ai-site-search-chatbot' ),
					'knowledgeDeleteFailed'    => __( 'The knowledge entry could not be deleted.', 'ai-site-search-chatbot' ),
					'knowledgeImportFailed'    => __( 'The CSV import could not be completed.', 'ai-site-search-chatbot' ),
					'knowledgeExportFailed'    => __( 'The CSV export could not be generated.', 'ai-site-search-chatbot' ),
					'knowledgeSaved'           => __( 'The knowledge entry was saved.', 'ai-site-search-chatbot' ),
					'knowledgeDeleted'         => __( 'The knowledge entry was deleted.', 'ai-site-search-chatbot' ),
					'knowledgeImported'        => __( 'The CSV import completed.', 'ai-site-search-chatbot' ),
					'knowledgeConfirmDelete'   => __( 'Delete this saved knowledge entry?', 'ai-site-search-chatbot' ),
					'knowledgeNewEntry'        => __( 'New entry', 'ai-site-search-chatbot' ),
					'knowledgeEditEntry'       => __( 'Edit entry', 'ai-site-search-chatbot' ),
					'knowledgeNoEntries'       => __( 'No saved knowledge entries yet.', 'ai-site-search-chatbot' ),
					'knowledgeImportMissing'   => __( 'Choose a CSV file before importing.', 'ai-site-search-chatbot' ),
					'knowledgeSearchPlaceholder' => __( 'Search saved knowledge', 'ai-site-search-chatbot' ),
					'knowledgeStatusAll'       => __( 'All statuses', 'ai-site-search-chatbot' ),
					'knowledgeExport'          => __( 'Export CSV', 'ai-site-search-chatbot' ),
					'knowledgeImport'          => __( 'Import CSV', 'ai-site-search-chatbot' ),
					'knowledgeRefresh'         => __( 'Refresh', 'ai-site-search-chatbot' ),
					'knowledgeCreate'          => __( 'Create entry', 'ai-site-search-chatbot' ),
					'knowledgeSave'            => __( 'Save entry', 'ai-site-search-chatbot' ),
					'knowledgeCancel'          => __( 'Cancel', 'ai-site-search-chatbot' ),
					'knowledgeQuestion'        => __( 'Generalized question', 'ai-site-search-chatbot' ),
					'knowledgeAnswer'          => __( 'Generalized answer', 'ai-site-search-chatbot' ),
					'knowledgeStatus'          => __( 'Status', 'ai-site-search-chatbot' ),
					'knowledgeSources'         => __( 'Source post IDs', 'ai-site-search-chatbot' ),
					'knowledgeMatchingHint'    => __( 'Matching hint', 'ai-site-search-chatbot' ),
					'knowledgeConfidence'      => __( 'Confidence note', 'ai-site-search-chatbot' ),
					'knowledgeAdminNotes'      => __( 'Admin notes', 'ai-site-search-chatbot' ),
					'knowledgePIIFlag'         => __( 'Needs privacy review', 'ai-site-search-chatbot' ),
					'knowledgeUpdatedAt'       => __( 'Updated', 'ai-site-search-chatbot' ),
					'knowledgeActions'         => __( 'Actions', 'ai-site-search-chatbot' ),
					'knowledgeEdit'            => __( 'Edit', 'ai-site-search-chatbot' ),
					'knowledgeDelete'          => __( 'Delete', 'ai-site-search-chatbot' ),
					'knowledgeApplyStatus'     => __( 'Apply status', 'ai-site-search-chatbot' ),
					'knowledgeStatusUpdated'   => __( 'The knowledge status was updated.', 'ai-site-search-chatbot' ),
				),
			)
		);
	}

	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_tab = self::get_current_tab();

		$settings = AISite_Search_Chatbot::get_settings();
		$chat_logs = AISite_Search_Chatbot::get_chat_logs();
		$providers = AISite_Search_Chatbot::get_providers_config();
		$widget_themes = AISite_Search_Chatbot::get_widget_themes();
		$widget_display_modes = AISite_Search_Chatbot::get_widget_display_modes();
		$knowledge_match_modes = AISite_Search_Chatbot::get_knowledge_base_match_modes();
		$uninstall_cleanup_modes = AISite_Search_Chatbot::get_uninstall_cleanup_modes();
		$usage_overview = 'logs' === $current_tab ? AISite_Search_Chatbot::get_usage_metrics_overview() : array();
		?>
		<div class="wrap aiscb-admin">
			<h1><?php echo esc_html( __( 'AI Site Search Chatbot', 'ai-site-search-chatbot' ) ); ?></h1>
			<?php self::render_page_tabs( $current_tab ); ?>
			<?php self::render_admin_notice(); ?>
			<?php self::render_legacy_provider_migration_notice(); ?>
			<?php if ( 'knowledge-base' === $current_tab ) : ?>
				<?php self::render_knowledge_base_panel(); ?>
			<?php elseif ( 'logs' === $current_tab ) : ?>
				<?php self::render_chat_logs_panel( $chat_logs, $usage_overview ); ?>
			<?php else : ?>
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

				<?php $connector_status = AISite_Search_Chatbot::get_connector_status(); ?>
				<div class="aiscb-connector-status" id="aiscb-connector-status">
					<h2 class="aiscb-section-title"><?php echo esc_html( __( 'Connection Status', 'ai-site-search-chatbot' ) ); ?></h2>
					<p class="description"><?php echo esc_html( __( 'AI provider connections (API keys) are managed centrally in Settings > Connectors, shared by every compatible plugin. This screen only controls which connected provider and model this chatbot uses.', 'ai-site-search-chatbot' ) ); ?></p>
					<ul class="aiscb-connector-list">
						<?php foreach ( $providers as $provider_key => $provider_info ) : ?>
							<?php $status = $connector_status[ $provider_key ] ?? array(); ?>
							<li class="aiscb-connector-item" data-provider="<?php echo esc_attr( $provider_key ); ?>">
								<strong class="aiscb-connector-item__label"><?php echo esc_html( $provider_info['label'] ); ?></strong>
								<?php if ( ! empty( $status['connected'] ) ) : ?>
									<span class="aiscb-connector-badge aiscb-connector-badge--connected"><?php echo esc_html( __( 'Connected', 'ai-site-search-chatbot' ) ); ?></span>
								<?php elseif ( empty( $status['plugin_active'] ) ) : ?>
									<span class="aiscb-connector-badge aiscb-connector-badge--disconnected"><?php echo esc_html( __( 'Provider plugin not active', 'ai-site-search-chatbot' ) ); ?></span>
								<?php else : ?>
									<span class="aiscb-connector-badge aiscb-connector-badge--disconnected"><?php echo esc_html( __( 'Not connected', 'ai-site-search-chatbot' ) ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
					<p>
						<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>"><?php echo esc_html( __( 'Manage connections in Settings > Connectors', 'ai-site-search-chatbot' ) ); ?></a>
					</p>
				</div>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aiscb_model"><?php echo esc_html( __( 'Model', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<div class="aiscb-model-container">
								<input type="text" class="regular-text code" id="aiscb_model" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[model]" value="<?php echo esc_attr( $settings['model'] ); ?>" spellcheck="false" autocomplete="off" />
								<p class="description"><?php echo esc_html( __( 'The AI model to use for generating answers.', 'ai-site-search-chatbot' ) ); ?></p>
								<p class="description" id="aiscb_model_help"></p>
								<p class="description" id="aiscb_model_reference"></p>
								<p>
									<button type="button" class="button button-secondary" id="aiscb_validate_button"><?php echo esc_html( __( 'Validate Connection and Model', 'ai-site-search-chatbot' ) ); ?></button>
								</p>
								<div id="aiscb_validation_result" class="notice inline aiscb-notice" hidden></div>
								<div class="aiscb-admin-test">
									<strong class="aiscb-admin-test-title"><?php echo esc_html( __( 'Admin Chat Test', 'ai-site-search-chatbot' ) ); ?></strong>
									<p class="description aiscb-admin-test-description"><?php echo esc_html( __( 'Test a real chatbot reply in the admin screen with the connected provider, model, and system prompt before exposing it publicly.', 'ai-site-search-chatbot' ) ); ?></p>
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
						<th scope="row"><label for="aiscb_ai_limit_global_daily"><?php echo esc_html( __( 'AI Reply Limit (site-wide, daily)', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<input type="number" min="0" max="100000" id="aiscb_ai_limit_global_daily" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[ai_limit_global_daily]" value="<?php echo esc_attr( (string) absint( $settings['ai_limit_global_daily'] ) ); ?>" />
							<p class="description"><?php echo esc_html( __( 'Hard ceiling on the total number of AI provider calls made across the whole site each day, regardless of visitor IP. This is the main protection against runaway API costs from distributed or automated abuse. Set 0 to disable the cap (not recommended for public sites).', 'ai-site-search-chatbot' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( __( 'Trust Proxy Headers', 'ai-site-search-chatbot' ) ); ?></th>
						<td>
							<label for="aiscb_trust_proxy_headers">
								<input type="checkbox" id="aiscb_trust_proxy_headers" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[trust_proxy_headers]" value="1" <?php checked( ! empty( $settings['trust_proxy_headers'] ) ); ?> />
								<?php echo esc_html( __( 'This site sits behind a trusted reverse proxy or CDN (e.g. Cloudflare, nginx, a load balancer).', 'ai-site-search-chatbot' ) ); ?>
							</label>
							<p class="description"><?php echo esc_html( __( 'When enabled, the visitor IP used for rate limiting is read from forwarded headers (CF-Connecting-IP, X-Real-IP, X-Forwarded-For). Only enable this if a trusted proxy sets those headers; otherwise visitors could spoof them to bypass rate limits. Leave disabled if visitors connect directly to WordPress.', 'ai-site-search-chatbot' ) ); ?></p>
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
						<th scope="row"><?php echo esc_html( __( 'Saved Knowledge Reuse', 'ai-site-search-chatbot' ) ); ?></th>
						<td>
							<label for="aiscb_knowledge_base_enabled">
								<input type="checkbox" id="aiscb_knowledge_base_enabled" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[knowledge_base_enabled]" value="1" <?php checked( ! empty( $settings['knowledge_base_enabled'] ) ); ?> />
								<?php echo esc_html( __( 'Enable approved saved knowledge entries before generating a new AI answer.', 'ai-site-search-chatbot' ) ); ?>
							</label>
							<p class="description"><?php echo esc_html( __( 'Saved knowledge is managed separately from public chat logs.', 'ai-site-search-chatbot' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html( __( 'Knowledge Candidate Drafts', 'ai-site-search-chatbot' ) ); ?></th>
						<td>
							<label for="aiscb_knowledge_base_auto_draft">
								<input type="checkbox" id="aiscb_knowledge_base_auto_draft" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[knowledge_base_auto_draft]" value="1" <?php checked( ! empty( $settings['knowledge_base_auto_draft'] ) ); ?> />
								<?php echo esc_html( __( 'Allow AI-generated generalized knowledge candidates to be saved as drafts for admin review.', 'ai-site-search-chatbot' ) ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiscb_knowledge_base_match_mode"><?php echo esc_html( __( 'Knowledge Match Mode', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<select id="aiscb_knowledge_base_match_mode" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[knowledge_base_match_mode]">
								<?php foreach ( $knowledge_match_modes as $mode_key => $mode_info ) : ?>
									<option value="<?php echo esc_attr( $mode_key ); ?>" <?php selected( $settings['knowledge_base_match_mode'], $mode_key ); ?>><?php echo esc_html( $mode_info['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<div class="aiscb-theme-descriptions">
								<?php foreach ( $knowledge_match_modes as $mode_info ) : ?>
									<p class="description"><?php echo esc_html( $mode_info['label'] . ': ' . $mode_info['description'] ); ?></p>
								<?php endforeach; ?>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiscb_knowledge_base_candidate_ttl_hours"><?php echo esc_html( __( 'Knowledge Candidate TTL (hours)', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<input type="number" min="1" max="720" id="aiscb_knowledge_base_candidate_ttl_hours" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[knowledge_base_candidate_ttl_hours]" value="<?php echo esc_attr( (string) absint( $settings['knowledge_base_candidate_ttl_hours'] ) ); ?>" />
							<p class="description"><?php echo esc_html( __( 'How long auto-generated draft candidates remain eligible before they should be reviewed or regenerated.', 'ai-site-search-chatbot' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiscb_uninstall_cleanup_mode"><?php echo esc_html( __( 'Uninstall Data Policy', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<select id="aiscb_uninstall_cleanup_mode" name="<?php echo esc_attr( AISite_Search_Chatbot::OPTION_KEY ); ?>[uninstall_cleanup_mode]">
								<?php foreach ( $uninstall_cleanup_modes as $cleanup_key => $cleanup_info ) : ?>
									<option value="<?php echo esc_attr( $cleanup_key ); ?>" <?php selected( $settings['uninstall_cleanup_mode'], $cleanup_key ); ?>><?php echo esc_html( $cleanup_info['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<div class="aiscb-theme-descriptions">
								<?php foreach ( $uninstall_cleanup_modes as $cleanup_info ) : ?>
									<p class="description"><?php echo esc_html( $cleanup_info['label'] . ': ' . $cleanup_info['description'] ); ?></p>
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
				<div class="aiscb-inline-callout">
					<strong><?php echo esc_html( __( 'Saved Knowledge Management', 'ai-site-search-chatbot' ) ); ?></strong>
					<p><?php echo esc_html( __( 'Review generalized question and answer pairs, manage approval status, and export or import CSV data from the dedicated Saved Knowledge Base screen.', 'ai-site-search-chatbot' ) ); ?></p>
					<p><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'options-general.php?page=ai-site-search-chatbot&tab=knowledge-base' ) ); ?>"><?php echo esc_html( __( 'Open Saved Knowledge Base', 'ai-site-search-chatbot' ) ); ?></a></p>
				</div>
				<?php submit_button(); ?>
			</form>
			<?php self::render_knowledge_editor_access_panel(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function render_knowledge_only_page(): void {
		if ( ! AISite_Search_Chatbot::is_knowledge_editor_or_admin() ) {
			return;
		}
		?>
		<div class="wrap aiscb-admin">
			<h1><?php echo esc_html( __( 'Saved Knowledge Base', 'ai-site-search-chatbot' ) ); ?></h1>
			<?php self::render_knowledge_base_panel(); ?>
		</div>
		<?php
	}

	private static function render_knowledge_editor_access_panel(): void {
		$users = get_users( array( 'orderby' => 'display_name', 'order' => 'ASC' ) );
		?>
		<div class="aiscb-inline-callout">
			<strong><?php echo esc_html( __( 'Knowledge Base Editors', 'ai-site-search-chatbot' ) ); ?></strong>
			<p><?php echo esc_html( __( 'Grant users who are not administrators access to create and edit entries on the Saved Knowledge Base screen only. Deleting entries and CSV import/export remain administrator-only actions.', 'ai-site-search-chatbot' ) ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aiscb-danger-zone__form">
				<?php wp_nonce_field( 'aiscb_update_knowledge_editors' ); ?>
				<input type="hidden" name="action" value="aiscb_update_knowledge_editors" />
				<div class="aiscb-user-access-list">
					<?php foreach ( $users as $user ) : ?>
						<?php $is_admin = user_can( $user, 'manage_options' ); ?>
						<label class="aiscb-user-access-list__item">
							<input
								type="checkbox"
								name="knowledge_editor_user_ids[]"
								value="<?php echo esc_attr( (string) $user->ID ); ?>"
								<?php checked( $is_admin || user_can( $user, AISite_Search_Chatbot::KNOWLEDGE_EDITOR_CAP ) ); ?>
								<?php disabled( $is_admin ); ?>
							/>
							<?php echo esc_html( $user->display_name . ' (' . $user->user_login . ')' ); ?>
							<?php if ( $is_admin ) : ?>
								<span class="description"><?php echo esc_html( __( '— Administrator (full access)', 'ai-site-search-chatbot' ) ); ?></span>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</div>
				<p><button type="submit" class="button button-secondary"><?php echo esc_html( __( 'Save Knowledge Editors', 'ai-site-search-chatbot' ) ); ?></button></p>
			</form>
		</div>
		<?php
	}

	public static function handle_update_knowledge_editors_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ai-site-search-chatbot' ) );
		}

		check_admin_referer( 'aiscb_update_knowledge_editors' );

		$user_ids = isset( $_POST['knowledge_editor_user_ids'] ) && is_array( $_POST['knowledge_editor_user_ids'] )
			? array_map( 'absint', wp_unslash( $_POST['knowledge_editor_user_ids'] ) )
			: array();

		AISite_Search_Chatbot::sync_knowledge_editor_capabilities( $user_ids );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'ai-site-search-chatbot',
					'aiscb_notice' => 'knowledge-editors-updated',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	private static function get_current_tab(): string {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'logs';

		return in_array( $tab, array( 'logs', 'settings', 'knowledge-base' ), true ) ? $tab : 'logs';
	}

	private static function render_page_tabs( string $current_tab ): void {
		$tabs = array(
			'logs' => __( 'Logs', 'ai-site-search-chatbot' ),
			'settings' => __( 'Settings', 'ai-site-search-chatbot' ),
			'knowledge-base' => __( 'Saved Knowledge Base', 'ai-site-search-chatbot' ),
		);
		?>
		<nav class="nav-tab-wrapper aiscb-nav-tabs" aria-label="<?php echo esc_attr( __( 'AI Site Search Chatbot sections', 'ai-site-search-chatbot' ) ); ?>">
			<?php foreach ( $tabs as $tab_key => $label ) : ?>
				<?php $url = admin_url( 'options-general.php?page=ai-site-search-chatbot' . ( 'logs' === $tab_key ? '' : '&tab=' . $tab_key ) ); ?>
				<a href="<?php echo esc_url( $url ); ?>" class="nav-tab <?php echo esc_attr( $current_tab === $tab_key ? 'nav-tab-active' : '' ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	public static function handle_delete_data_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ai-site-search-chatbot' ) );
		}

		check_admin_referer( 'aiscb_delete_data' );

		$scope = isset( $_POST['delete_scope'] ) ? sanitize_key( wp_unslash( (string) $_POST['delete_scope'] ) ) : '';
		$notice = 'delete-failed';

		switch ( $scope ) {
			case 'logs':
				AISite_Search_Chatbot::delete_chat_logs();
				$notice = 'logs-deleted';
				break;
			case 'usage':
				AISite_Search_Chatbot::delete_usage_metrics_data();
				$notice = 'usage-deleted';
				break;
			case 'all':
				AISite_Search_Chatbot::delete_chat_logs();
				AISite_Search_Chatbot::delete_usage_metrics_data();
				$notice = 'logs-usage-deleted';
				break;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'ai-site-search-chatbot',
					'aiscb_notice' => $notice,
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	public static function handle_clear_cache_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ai-site-search-chatbot' ) );
		}

		check_admin_referer( 'aiscb_clear_cache' );
		self::clear_plugin_cache();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'ai-site-search-chatbot',
					'aiscb_notice' => 'cache-cleared',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	private static function clear_plugin_cache(): void {
		if ( is_callable( array( 'AISite_Search_Chatbot', 'clear_plugin_transients' ) ) ) {
			call_user_func( array( 'AISite_Search_Chatbot', 'clear_plugin_transients' ) );
		}
	}

	private static function render_admin_notice(): void {
		$notice_key = isset( $_GET['aiscb_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['aiscb_notice'] ) ) : '';
		$notices = array(
			'cache-cleared' => array( 'success', __( 'Plugin caches were cleared.', 'ai-site-search-chatbot' ) ),
			'logs-deleted' => array( 'success', __( 'Chat logs were deleted.', 'ai-site-search-chatbot' ) ),
			'usage-deleted' => array( 'success', __( 'Usage totals were deleted.', 'ai-site-search-chatbot' ) ),
			'logs-usage-deleted' => array( 'success', __( 'Chat logs and usage totals were deleted.', 'ai-site-search-chatbot' ) ),
			'delete-failed' => array( 'error', __( 'The delete action could not be completed.', 'ai-site-search-chatbot' ) ),
			'knowledge-editors-updated' => array( 'success', __( 'Knowledge base editor access was updated.', 'ai-site-search-chatbot' ) ),
		);

		if ( ! isset( $notices[ $notice_key ] ) ) {
			return;
		}

		list( $type, $message ) = $notices[ $notice_key ];
		?>
		<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
		<?php
	}

	/**
	 * Shows a one-time notice after the plugin drops the "GitHub Models" provider
	 * and resets sites that had it selected back to the OpenAI default.
	 */
	private static function render_legacy_provider_migration_notice(): void {
		if ( ! get_option( AISite_Search_Chatbot::LEGACY_PROVIDER_MIGRATION_NOTICE_OPTION ) ) {
			return;
		}

		delete_option( AISite_Search_Chatbot::LEGACY_PROVIDER_MIGRATION_NOTICE_OPTION );
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php echo esc_html( __( 'AI Site Search Chatbot no longer supports the GitHub Models provider and now connects through WordPress Settings > Connectors instead of its own saved API keys. The AI provider was reset to OpenAI — please review the Settings tab and connect a provider.', 'ai-site-search-chatbot' ) ); ?></p>
		</div>
		<?php
	}

	private static function render_knowledge_base_panel(): void {
		$full_access = current_user_can( 'manage_options' );
		?>
		<p><?php echo esc_html( __( 'Manage generalized question and answer pairs that can be approved for reuse. This screen is separate from the public chat logs.', 'ai-site-search-chatbot' ) ); ?></p>

			<div class="aiscb-knowledge-layout">
				<div class="aiscb-knowledge-panel">
					<div class="aiscb-knowledge-toolbar">
						<input type="search" id="aiscb_knowledge_search" class="regular-text" placeholder="<?php echo esc_attr( __( 'Search saved knowledge', 'ai-site-search-chatbot' ) ); ?>" />
						<select id="aiscb_knowledge_status_filter">
							<option value=""><?php echo esc_html( __( 'All statuses', 'ai-site-search-chatbot' ) ); ?></option>
							<option value="draft"><?php echo esc_html( __( 'Draft', 'ai-site-search-chatbot' ) ); ?></option>
							<option value="approved"><?php echo esc_html( __( 'Approved', 'ai-site-search-chatbot' ) ); ?></option>
							<option value="archived"><?php echo esc_html( __( 'Archived', 'ai-site-search-chatbot' ) ); ?></option>
						</select>
						<button type="button" class="button" id="aiscb_knowledge_refresh"><?php echo esc_html( __( 'Refresh', 'ai-site-search-chatbot' ) ); ?></button>
						<button type="button" class="button button-primary" id="aiscb_knowledge_create"><?php echo esc_html( __( 'Create entry', 'ai-site-search-chatbot' ) ); ?></button>
						<?php if ( $full_access ) : ?>
							<button type="button" class="button" id="aiscb_knowledge_export"><?php echo esc_html( __( 'Export CSV', 'ai-site-search-chatbot' ) ); ?></button>
						<?php endif; ?>
					</div>

					<?php if ( $full_access ) : ?>
						<div class="aiscb-knowledge-import-row">
							<input type="file" id="aiscb_knowledge_import_file" accept=".csv,text/csv" />
							<button type="button" class="button" id="aiscb_knowledge_import"><?php echo esc_html( __( 'Import CSV', 'ai-site-search-chatbot' ) ); ?></button>
						</div>
					<?php endif; ?>

					<div id="aiscb_knowledge_notice" class="notice inline aiscb-notice" hidden></div>

					<div class="aiscb-knowledge-table-wrap">
						<table class="widefat fixed striped aiscb-knowledge-table">
							<thead>
								<tr>
									<th><?php echo esc_html( __( 'Status / Actions', 'ai-site-search-chatbot' ) ); ?></th>
									<th><?php echo esc_html( __( 'Generalized Question', 'ai-site-search-chatbot' ) ); ?></th>
									<th><?php echo esc_html( __( 'Generalized answer', 'ai-site-search-chatbot' ) ); ?></th>
									<th><?php echo esc_html( __( 'Updated', 'ai-site-search-chatbot' ) ); ?></th>
								</tr>
							</thead>
							<tbody id="aiscb_knowledge_table_body">
								<tr>
									<td colspan="4"><?php echo esc_html( __( 'Loading saved knowledge…', 'ai-site-search-chatbot' ) ); ?></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<div class="aiscb-knowledge-panel">
					<h2 class="aiscb-section-title" id="aiscb_knowledge_form_title"><?php echo esc_html( __( 'New entry', 'ai-site-search-chatbot' ) ); ?></h2>
					<form id="aiscb_knowledge_form">
						<input type="hidden" id="aiscb_knowledge_id" value="" />
						<input type="hidden" id="aiscb_knowledge_export_uid" value="" />
						<p>
							<label for="aiscb_knowledge_status"><strong><?php echo esc_html( __( 'Status', 'ai-site-search-chatbot' ) ); ?></strong></label><br />
							<select id="aiscb_knowledge_status">
								<option value="draft"><?php echo esc_html( __( 'Draft', 'ai-site-search-chatbot' ) ); ?></option>
								<option value="approved"><?php echo esc_html( __( 'Approved', 'ai-site-search-chatbot' ) ); ?></option>
								<option value="archived"><?php echo esc_html( __( 'Archived', 'ai-site-search-chatbot' ) ); ?></option>
							</select>
						</p>
						<p>
							<label for="aiscb_knowledge_question"><strong><?php echo esc_html( __( 'Generalized question', 'ai-site-search-chatbot' ) ); ?></strong></label>
							<textarea id="aiscb_knowledge_question" class="large-text" rows="4"></textarea>
						</p>
						<p>
							<label for="aiscb_knowledge_answer"><strong><?php echo esc_html( __( 'Generalized answer', 'ai-site-search-chatbot' ) ); ?></strong></label>
							<textarea id="aiscb_knowledge_answer" class="large-text" rows="8"></textarea>
						</p>
						<p>
							<label for="aiscb_knowledge_source_post_ids"><strong><?php echo esc_html( __( 'Source post IDs', 'ai-site-search-chatbot' ) ); ?></strong></label>
							<input type="text" id="aiscb_knowledge_source_post_ids" class="regular-text" />
						</p>
						<p>
							<label for="aiscb_knowledge_matching_method_hint"><strong><?php echo esc_html( __( 'Matching hint', 'ai-site-search-chatbot' ) ); ?></strong></label>
							<input type="text" id="aiscb_knowledge_matching_method_hint" class="regular-text" />
						</p>
						<p>
							<label for="aiscb_knowledge_confidence_note"><strong><?php echo esc_html( __( 'Confidence note', 'ai-site-search-chatbot' ) ); ?></strong></label>
							<textarea id="aiscb_knowledge_confidence_note" class="large-text" rows="3"></textarea>
						</p>
						<p>
							<label for="aiscb_knowledge_admin_notes"><strong><?php echo esc_html( __( 'Admin notes', 'ai-site-search-chatbot' ) ); ?></strong></label>
							<textarea id="aiscb_knowledge_admin_notes" class="large-text" rows="4"></textarea>
						</p>
						<p>
							<label for="aiscb_knowledge_pii_flag">
								<input type="checkbox" id="aiscb_knowledge_pii_flag" value="1" />
								<?php echo esc_html( __( 'Needs privacy review', 'ai-site-search-chatbot' ) ); ?>
							</label>
						</p>
						<p class="submit">
							<button type="submit" class="button button-primary" id="aiscb_knowledge_save"><?php echo esc_html( __( 'Save entry', 'ai-site-search-chatbot' ) ); ?></button>
							<button type="button" class="button" id="aiscb_knowledge_cancel"><?php echo esc_html( __( 'Cancel', 'ai-site-search-chatbot' ) ); ?></button>
						</p>
					</form>
				</div>
			</div>
		<?php
	}

	private static function render_chat_logs_panel( array $chat_logs, array $usage_overview = array() ): void {
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

			<?php self::render_usage_overview_panel( $usage_overview ); ?>
			<p class="description"><?php echo esc_html( __( 'Displayed token counts are estimates. Actual provider-side usage or billing may differ.', 'ai-site-search-chatbot' ) ); ?></p>
			<?php self::render_delete_actions_panel(); ?>

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
								<th><?php echo esc_html( __( 'Status / AI', 'ai-site-search-chatbot' ) ); ?></th>
								<th><?php echo esc_html( __( 'Details', 'ai-site-search-chatbot' ) ); ?></th>
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
										<?php if ( array_key_exists( 'ai_usage_summary', $log ) ) : ?>
											<?php self::render_ai_usage_summary( isset( $log['ai_usage_summary'] ) && is_array( $log['ai_usage_summary'] ) ? $log['ai_usage_summary'] : array() ); ?>
										<?php endif; ?>
									</td>
									<td class="aiscb-log-table__time">
										<div><?php echo esc_html( $timestamp ? wp_date( 'Y-m-d H:i:s', $timestamp ) : '-' ); ?></div>
										<div class="aiscb-log-meta"><?php echo esc_html( sprintf( __( 'IP: %s', 'ai-site-search-chatbot' ), self::mask_ip_address( isset( $log['ip'] ) ? (string) $log['ip'] : '' ) ) ); ?></div>
										<div class="aiscb-log-meta"><?php echo esc_html( sprintf( __( 'Sources: %d', 'ai-site-search-chatbot' ), isset( $log['source_count'] ) ? absint( $log['source_count'] ) : 0 ) ); ?></div>
										<div class="aiscb-log-meta"><?php echo esc_html( sprintf( __( 'Search Terms: %s', 'ai-site-search-chatbot' ), self::format_search_queries( isset( $log['search_queries'] ) && is_array( $log['search_queries'] ) ? $log['search_queries'] : array() ) ) ); ?></div>
										<?php if ( ! empty( $log['knowledge_candidate_status'] ) ) : ?>
											<div class="aiscb-log-meta"><?php echo esc_html( sprintf( __( 'Knowledge Draft: %s', 'ai-site-search-chatbot' ), self::format_knowledge_candidate_status( (string) $log['knowledge_candidate_status'] ) ) ); ?></div>
										<?php endif; ?>
										<?php if ( ! empty( $log['knowledge_candidate_note'] ) ) : ?>
											<div class="aiscb-log-meta"><?php echo esc_html( sprintf( __( 'Knowledge Note: %s', 'ai-site-search-chatbot' ), (string) $log['knowledge_candidate_note'] ) ); ?></div>
										<?php endif; ?>
										<?php if ( ! empty( $log['knowledge_candidate_pii_flag'] ) ) : ?>
											<div class="aiscb-log-meta"><?php echo esc_html( __( 'Knowledge candidate marked for privacy review.', 'ai-site-search-chatbot' ) ); ?></div>
										<?php endif; ?>
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

	private static function render_usage_overview_panel( array $usage_overview ): void {
		$today = isset( $usage_overview['today'] ) && is_array( $usage_overview['today'] ) ? $usage_overview['today'] : array();
		$this_month = isset( $usage_overview['this_month'] ) && is_array( $usage_overview['this_month'] ) ? $usage_overview['this_month'] : array();
		$daily = isset( $usage_overview['daily'] ) && is_array( $usage_overview['daily'] ) ? $usage_overview['daily'] : array();
		$metrics_payload = wp_json_encode( $usage_overview );
		?>
		<div class="aiscb-usage-panel"<?php echo is_string( $metrics_payload ) ? ' data-metrics="' . esc_attr( $metrics_payload ) . '"' : ''; ?>>
			<div class="aiscb-usage-cards">
				<div class="aiscb-usage-card">
					<div class="aiscb-usage-card__label"><?php echo esc_html( __( 'Today', 'ai-site-search-chatbot' ) ); ?></div>
					<div class="aiscb-usage-card__value"><?php echo esc_html( number_format_i18n( isset( $today['total_tokens'] ) ? absint( $today['total_tokens'] ) : 0 ) ); ?></div>
					<div class="aiscb-usage-card__meta"><?php echo esc_html( sprintf( __( 'Requests: %d', 'ai-site-search-chatbot' ), isset( $today['requests_count'] ) ? absint( $today['requests_count'] ) : 0 ) ); ?></div>
					<div class="aiscb-usage-card__meta"><?php echo esc_html( sprintf( __( 'Input / Output: %1$d / %2$d', 'ai-site-search-chatbot' ), isset( $today['input_tokens'] ) ? absint( $today['input_tokens'] ) : 0, isset( $today['output_tokens'] ) ? absint( $today['output_tokens'] ) : 0 ) ); ?></div>
				</div>
				<div class="aiscb-usage-card">
					<div class="aiscb-usage-card__label"><?php echo esc_html( __( 'This Month', 'ai-site-search-chatbot' ) ); ?></div>
					<div class="aiscb-usage-card__value"><?php echo esc_html( number_format_i18n( isset( $this_month['total_tokens'] ) ? absint( $this_month['total_tokens'] ) : 0 ) ); ?></div>
					<div class="aiscb-usage-card__meta"><?php echo esc_html( sprintf( __( 'Requests: %d', 'ai-site-search-chatbot' ), isset( $this_month['requests_count'] ) ? absint( $this_month['requests_count'] ) : 0 ) ); ?></div>
					<div class="aiscb-usage-card__meta"><?php echo esc_html( sprintf( __( 'Input / Output: %1$d / %2$d', 'ai-site-search-chatbot' ), isset( $this_month['input_tokens'] ) ? absint( $this_month['input_tokens'] ) : 0, isset( $this_month['output_tokens'] ) ? absint( $this_month['output_tokens'] ) : 0 ) ); ?></div>
				</div>
			</div>

			<div class="aiscb-usage-chart-panel">
				<div class="aiscb-usage-chart-panel__header">
					<h3><?php echo esc_html( __( 'Last 30 Days Usage', 'ai-site-search-chatbot' ) ); ?></h3>
					<p class="description"><?php echo esc_html( __( 'This chart shows total tokens per day so you can spot configuration changes or unusual traffic quickly.', 'ai-site-search-chatbot' ) ); ?></p>
				</div>
				<canvas class="aiscb-usage-chart" height="180" aria-label="<?php echo esc_attr__( 'Daily usage chart', 'ai-site-search-chatbot' ); ?>"></canvas>
			</div>

			<div class="aiscb-usage-table-wrap">
				<table class="widefat striped aiscb-usage-table">
					<thead>
						<tr>
							<th><?php echo esc_html( __( 'Day', 'ai-site-search-chatbot' ) ); ?></th>
							<th><?php echo esc_html( __( 'Requests', 'ai-site-search-chatbot' ) ); ?></th>
							<th><?php echo esc_html( __( 'Input Tokens', 'ai-site-search-chatbot' ) ); ?></th>
							<th><?php echo esc_html( __( 'Output Tokens', 'ai-site-search-chatbot' ) ); ?></th>
							<th><?php echo esc_html( __( 'Total Tokens', 'ai-site-search-chatbot' ) ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $daily ) ) : ?>
							<tr>
								<td colspan="5"><?php echo esc_html( __( 'No usage totals have been recorded yet.', 'ai-site-search-chatbot' ) ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( array_reverse( $daily ) as $row ) : ?>
								<tr>
									<td><?php echo esc_html( isset( $row['local_day_key'] ) ? (string) $row['local_day_key'] : '-' ); ?></td>
									<td><?php echo esc_html( number_format_i18n( isset( $row['requests_count'] ) ? absint( $row['requests_count'] ) : 0 ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( isset( $row['input_tokens'] ) ? absint( $row['input_tokens'] ) : 0 ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( isset( $row['output_tokens'] ) ? absint( $row['output_tokens'] ) : 0 ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( isset( $row['total_tokens'] ) ? absint( $row['total_tokens'] ) : 0 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private static function render_delete_actions_panel(): void {
		$actions = array(
			'logs' => __( 'Delete Logs Only', 'ai-site-search-chatbot' ),
			'usage' => __( 'Delete Usage Only', 'ai-site-search-chatbot' ),
			'all' => __( 'Delete Logs and Usage', 'ai-site-search-chatbot' ),
		);
		$descriptions = array(
			'logs' => __( 'Remove the stored visitor chat history while keeping daily usage totals.', 'ai-site-search-chatbot' ),
			'usage' => __( 'Remove the daily usage totals while keeping detailed visitor chat logs.', 'ai-site-search-chatbot' ),
			'all' => __( 'Remove both the visitor chat history and the aggregated usage totals.', 'ai-site-search-chatbot' ),
		);
		?>
		<div class="aiscb-danger-zone">
			<h3><?php echo esc_html( __( 'Delete Stored Data', 'ai-site-search-chatbot' ) ); ?></h3>
			<p class="description"><?php echo esc_html( __( 'Use these actions when you want to clear logs or usage totals without uninstalling the plugin.', 'ai-site-search-chatbot' ) ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aiscb-danger-zone__form" onsubmit="return window.confirm('<?php echo esc_js( __( 'Clear cached AI replies and transient state for fresh verification?', 'ai-site-search-chatbot' ) ); ?>');">
				<?php wp_nonce_field( 'aiscb_clear_cache' ); ?>
				<input type="hidden" name="action" value="aiscb_clear_cache" />
				<button type="submit" class="button"><?php echo esc_html( __( 'Clear Plugin Cache', 'ai-site-search-chatbot' ) ); ?></button>
				<div class="aiscb-danger-zone__hint"><?php echo esc_html( __( 'Remove cached AI replies and transient usage state so the next chat sends fresh provider requests.', 'ai-site-search-chatbot' ) ); ?></div>
			</form>
			<div class="aiscb-danger-zone__actions">
				<?php foreach ( $actions as $scope => $label ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aiscb-danger-zone__form" onsubmit="return window.confirm('<?php echo esc_js( __( 'This delete action cannot be undone. Continue?', 'ai-site-search-chatbot' ) ); ?>');">
						<?php wp_nonce_field( 'aiscb_delete_data' ); ?>
						<input type="hidden" name="action" value="aiscb_delete_data" />
						<input type="hidden" name="delete_scope" value="<?php echo esc_attr( $scope ); ?>" />
						<button type="submit" class="button <?php echo esc_attr( 'all' === $scope ? 'button-primary' : '' ); ?>"><?php echo esc_html( $label ); ?></button>
						<div class="aiscb-danger-zone__hint"><?php echo esc_html( $descriptions[ $scope ] ); ?></div>
					</form>
				<?php endforeach; ?>
			</div>
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

	private static function render_ai_usage_summary( array $summary ): void {
		$total_requests = isset( $summary['total_requests'] ) ? absint( $summary['total_requests'] ) : 0;
		$total_input_tokens = isset( $summary['total_input_tokens'] ) ? absint( $summary['total_input_tokens'] ) : 0;
		$total_output_tokens = ( isset( $summary['total_output_tokens'] ) ? absint( $summary['total_output_tokens'] ) : 0 )
			+ ( isset( $summary['total_thinking_tokens'] ) ? absint( $summary['total_thinking_tokens'] ) : 0 );
		$total_request_characters = isset( $summary['total_request_characters_in'] ) ? absint( $summary['total_request_characters_in'] ) : 0;
		$total_response_characters = isset( $summary['total_response_characters_out'] ) ? absint( $summary['total_response_characters_out'] ) : 0;
		$total_thinking_tokens = isset( $summary['total_thinking_tokens'] ) ? absint( $summary['total_thinking_tokens'] ) : 0;
		$total_cache_creation_tokens = isset( $summary['total_cache_creation_tokens'] ) ? absint( $summary['total_cache_creation_tokens'] ) : 0;
		$total_cache_read_tokens = isset( $summary['total_cache_read_tokens'] ) ? absint( $summary['total_cache_read_tokens'] ) : 0;
		$usage_sources = isset( $summary['usage_sources'] ) && is_array( $summary['usage_sources'] ) ? $summary['usage_sources'] : array();

		echo '<div class="aiscb-log-meta">' . esc_html( sprintf( __( 'AI Requests: %d', 'ai-site-search-chatbot' ), $total_requests ) ) . '</div>';

		if ( 0 === $total_requests ) {
			return;
		}

		$actual_count = isset( $usage_sources['actual'] ) ? absint( $usage_sources['actual'] ) : 0;
		$estimated_count = isset( $usage_sources['estimated'] ) ? absint( $usage_sources['estimated'] ) : 0;
		$unavailable_count = isset( $usage_sources['unavailable'] ) ? absint( $usage_sources['unavailable'] ) : 0;
		$token_label = ( 0 === $actual_count && $estimated_count > 0 && 0 === $unavailable_count )
			? __( 'Chat Token Estimate: in %1$d / out %2$d', 'ai-site-search-chatbot' )
			: __( 'Chat Tokens: in %1$d / out %2$d', 'ai-site-search-chatbot' );

		echo '<div class="aiscb-log-meta">' . esc_html( sprintf( $token_label, $total_input_tokens, $total_output_tokens ) ) . '</div>';
		echo '<div class="aiscb-log-meta">' . esc_html( sprintf( __( 'Chat Characters: in %1$d / out %2$d', 'ai-site-search-chatbot' ), $total_request_characters, $total_response_characters ) ) . '</div>';

		if ( $total_thinking_tokens > 0 ) {
			echo '<div class="aiscb-log-meta">' . esc_html( sprintf( __( 'Thinking Tokens: %d', 'ai-site-search-chatbot' ), $total_thinking_tokens ) ) . '</div>';
		}

		if ( $total_cache_creation_tokens > 0 || $total_cache_read_tokens > 0 ) {
			echo '<div class="aiscb-log-meta">' . esc_html( sprintf( __( 'Cache Tokens: create %1$d / read %2$d', 'ai-site-search-chatbot' ), $total_cache_creation_tokens, $total_cache_read_tokens ) ) . '</div>';
		}

		echo '<div class="aiscb-log-meta">' . esc_html( sprintf( __( 'Usage Sources: %s', 'ai-site-search-chatbot' ), self::format_usage_source_breakdown( $usage_sources ) ) ) . '</div>';

		$provider_breakdown = self::format_ai_usage_bucket_breakdown( isset( $summary['providers'] ) && is_array( $summary['providers'] ) ? $summary['providers'] : array(), 'provider' );
		if ( '' !== $provider_breakdown ) {
			echo '<div class="aiscb-log-meta">' . esc_html( sprintf( __( 'Providers: %s', 'ai-site-search-chatbot' ), $provider_breakdown ) ) . '</div>';
		}

		$purpose_breakdown = self::format_ai_usage_bucket_breakdown( isset( $summary['purposes'] ) && is_array( $summary['purposes'] ) ? $summary['purposes'] : array(), 'purpose' );
		if ( '' !== $purpose_breakdown ) {
			echo '<div class="aiscb-log-meta">' . esc_html( sprintf( __( 'AI Steps: %s', 'ai-site-search-chatbot' ), $purpose_breakdown ) ) . '</div>';
		}
	}

	private static function format_usage_source_breakdown( array $sources ): string {
		$labels = array(
			'actual' => __( 'actual', 'ai-site-search-chatbot' ),
			'estimated' => __( 'estimated', 'ai-site-search-chatbot' ),
			'unavailable' => __( 'unavailable', 'ai-site-search-chatbot' ),
		);
		$parts = array();

		foreach ( $labels as $key => $label ) {
			$count = isset( $sources[ $key ] ) ? absint( $sources[ $key ] ) : 0;
			if ( $count <= 0 ) {
				continue;
			}

			$parts[] = sprintf( '%s %d', $label, $count );
		}

		return empty( $parts ) ? '-' : implode( ', ', $parts );
	}

	private static function format_ai_usage_bucket_breakdown( array $buckets, string $type ): string {
		if ( empty( $buckets ) ) {
			return '';
		}

		$providers = AISite_Search_Chatbot::get_providers_config();
		$parts = array();

		foreach ( $buckets as $key => $bucket ) {
			if ( ! is_array( $bucket ) ) {
				continue;
			}

			$requests = isset( $bucket['requests'] ) ? absint( $bucket['requests'] ) : 0;
			if ( $requests <= 0 ) {
				continue;
			}

			$input_tokens = isset( $bucket['input_tokens'] ) ? absint( $bucket['input_tokens'] ) : 0;
			$output_tokens = isset( $bucket['output_tokens'] ) ? absint( $bucket['output_tokens'] ) : 0;
			$label = 'provider' === $type
				? ( isset( $providers[ $key ]['label'] ) ? (string) $providers[ $key ]['label'] : (string) $key )
				: self::format_ai_purpose_label( (string) $key );

			$parts[] = sprintf( '%1$s %2$d (%3$d/%4$d)', $label, $requests, $input_tokens, $output_tokens );
		}

		return implode( ', ', $parts );
	}

	private static function format_ai_purpose_label( string $purpose ): string {
		$labels = array(
			'route_classification' => __( 'Route', 'ai-site-search-chatbot' ),
			'knowledge_selection' => __( 'Knowledge Match', 'ai-site-search-chatbot' ),
			'answer_generation' => __( 'Answer', 'ai-site-search-chatbot' ),
			'site_guidance_generation' => __( 'Site Guidance', 'ai-site-search-chatbot' ),
			'knowledge_candidate_generation' => __( 'Knowledge Draft', 'ai-site-search-chatbot' ),
		);

		return $labels[ $purpose ] ?? $purpose;
	}

	private static function format_search_queries( array $queries ): string {
		$queries = array_values(
			array_filter(
				array_map(
					static function ( $query ): string {
						return is_scalar( $query ) ? trim( (string) $query ) : '';
					},
					$queries
				)
			)
		);

		if ( empty( $queries ) ) {
			return '-';
		}

		return implode( ', ', $queries );
	}

	private static function format_knowledge_candidate_status( string $status ): string {
		$status = sanitize_key( $status );

		$labels = array(
			'saved' => __( 'Draft saved', 'ai-site-search-chatbot' ),
			'updated' => __( 'Draft updated', 'ai-site-search-chatbot' ),
			'kept-approved' => __( 'Approved entry kept', 'ai-site-search-chatbot' ),
			'rejected' => __( 'Draft not saved', 'ai-site-search-chatbot' ),
			'provider-error' => __( 'Draft evaluation failed', 'ai-site-search-chatbot' ),
			'disabled' => __( 'Draft saving disabled', 'ai-site-search-chatbot' ),
		);

		return $labels[ $status ] ?? '';
	}

	private static function get_chat_log_status_map(): array {
		return array(
			'ai-generated' => array(
				'label' => __( 'AI Reply', 'ai-site-search-chatbot' ),
				'icon'  => 'dashicons-format-chat',
				'tone'  => 'success',
			),
			'ai-knowledge-reused' => array(
				'label' => __( 'AI Matched Knowledge', 'ai-site-search-chatbot' ),
				'icon'  => 'dashicons-database-view',
				'tone'  => 'info',
			),
			'ai-cached' => array(
				'label' => __( 'Cached Reply', 'ai-site-search-chatbot' ),
				'icon'  => 'dashicons-update',
				'tone'  => 'info',
			),
			'knowledge-reused' => array(
				'label' => __( 'Saved Knowledge Reply', 'ai-site-search-chatbot' ),
				'icon'  => 'dashicons-yes-alt',
				'tone'  => 'success',
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
