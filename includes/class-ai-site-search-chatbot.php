<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AISite_Search_Chatbot {
	const VERSION = '0.1.0';
	const OPTION_KEY = 'aiscb_settings';
	const OPTION_GROUP = 'aiscb_settings_group';
	const REST_NAMESPACE = 'ai-site-search-chatbot/v1';
	const SHORTCODE = 'ai_site_search_chatbot';

	private static $assets_enqueued = false;

	public static function activate(): void {
		if ( false !== get_option( self::OPTION_KEY, false ) ) {
			return;
		}

		add_option( self::OPTION_KEY, self::default_settings() );
	}

	public static function init(): void {
		self::load_textdomain();
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
	}

	public static function load_textdomain(): void {
		$domain = 'ai-site-search-chatbot';
		$language_dir = trailingslashit( AISCB_DIR . 'languages' );

		load_plugin_textdomain( $domain, false, dirname( plugin_basename( AISCB_FILE ) ) . '/languages' );

		if ( is_textdomain_loaded( $domain ) ) {
			return;
		}

		$locales = array_filter(
			array_unique(
				array(
					determine_locale(),
					get_locale(),
					substr( (string) determine_locale(), 0, 2 ),
					substr( (string) get_locale(), 0, 2 ),
				)
			)
		);

		foreach ( $locales as $locale ) {
			$mofile = $language_dir . $domain . '-' . $locale . '.mo';

			if ( file_exists( $mofile ) && load_textdomain( $domain, $mofile ) ) {
				return;
			}
		}
	}

	public static function default_settings(): array {
		return array(
			'ai_provider'   => 'openai',
			'api_key'       => '',
			'model'         => '',
			'system_prompt' => self::get_default_system_prompt(),
			'max_sources'   => 5,
		);
	}

	private static function get_default_system_prompt(): string {
		return __( 'You are a WordPress site search assistant. Answer the visitor using only the provided site search results. Give a concise, helpful answer based on the most relevant result snippets. If the answer is not clearly present in the results, say that you could not find enough information in the site search results, do not guess, and suggest a relevant page or search keyword.', 'ai-site-search-chatbot' );
	}

	private static function get_legacy_default_system_prompts(): array {
		$legacy_prompt = 'You are a public website assistant. Answer only from the provided site search results. If the answer is not present, say so clearly and suggest related pages.';

		return array_unique(
			array_filter(
				array(
					$legacy_prompt,
					__( $legacy_prompt, 'ai-site-search-chatbot' ),
				)
			)
		);
	}

	public static function get_settings(): array {
		$settings = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( ! isset( $settings['system_prompt'] ) || '' === trim( (string) $settings['system_prompt'] ) || in_array( (string) $settings['system_prompt'], self::get_legacy_default_system_prompts(), true ) ) {
			$settings['system_prompt'] = self::get_default_system_prompt();
		}

		return wp_parse_args( $settings, self::default_settings() );
	}

	public static function sanitize_settings( $input ): array {
		$defaults = self::default_settings();
		$input = is_array( $input ) ? $input : array();

		$provider = isset( $input['ai_provider'] ) ? sanitize_text_field( wp_unslash( $input['ai_provider'] ) ) : $defaults['ai_provider'];
		$valid_providers = array( 'openai', 'claude', 'github-copilot', 'gemini' );
		if ( ! in_array( $provider, $valid_providers, true ) ) {
			$provider = $defaults['ai_provider'];
		}

		return array(
			'ai_provider'   => $provider,
			'api_key'       => isset( $input['api_key'] ) ? sanitize_text_field( wp_unslash( $input['api_key'] ) ) : $defaults['api_key'],
			'model'         => isset( $input['model'] ) ? sanitize_text_field( wp_unslash( $input['model'] ) ) : $defaults['model'],
			'system_prompt' => isset( $input['system_prompt'] ) ? sanitize_textarea_field( wp_unslash( $input['system_prompt'] ) ) : $defaults['system_prompt'],
			'max_sources'   => isset( $input['max_sources'] ) ? max( 1, min( 10, absint( $input['max_sources'] ) ) ) : $defaults['max_sources'],
		);
	}

	public static function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::default_settings(),
			)
		);
	}

	public static function register_admin_menu(): void {
		add_options_page(
			__( 'AI Site Search Chatbot', 'ai-site-search-chatbot' ),
			__( 'AI Site Search Chatbot', 'ai-site-search-chatbot' ),
			'manage_options',
			'ai-site-search-chatbot',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		$providers = self::get_providers_config();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( __( 'AI Site Search Chatbot', 'ai-site-search-chatbot' ) ); ?></h1>
			<p><?php echo esc_html( __( 'Configure the AI provider and the prompt used when answering visitors with site search results.', 'ai-site-search-chatbot' ) ); ?></p>
			<form method="post" action="options.php" id="aiscb-settings-form">
				<?php settings_fields( self::OPTION_GROUP ); ?>
				
				<!-- AI Provider Selection -->
				<div class="aiscb-provider-selector" style="margin-bottom: 2rem; padding: 1.5rem; background: #f5f5f5; border-radius: 8px;">
					<h2 style="margin-top: 0; margin-bottom: 1rem; color: #333;"><?php echo esc_html( __( 'AI Provider', 'ai-site-search-chatbot' ) ); ?></h2>
					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
						<?php foreach ( $providers as $provider_key => $provider_info ) : ?>
							<label style="padding: 1rem; background: #fff; border: 2px solid #ddd; border-radius: 8px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 0.75rem;" class="aiscb-provider-option" data-provider="<?php echo esc_attr( $provider_key ); ?>">
								<input 
									type="radio" 
									name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ai_provider]" 
									value="<?php echo esc_attr( $provider_key ); ?>" 
									<?php checked( $settings['ai_provider'], $provider_key ); ?>
									class="aiscb-provider-radio"
									style="width: 18px; height: 18px; cursor: pointer;"
								/>
								<div style="flex: 1;">
									<strong style="display: block; color: #333;"><?php echo esc_html( $provider_info['label'] ); ?></strong>
									<small style="color: #666;"><?php echo esc_html( $provider_info['description'] ); ?></small>
								</div>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Provider Information Panel -->
				<div id="aiscb-provider-info" style="margin-bottom: 2rem; padding: 1.5rem; background: #e8f4f8; border-left: 4px solid #0073aa; border-radius: 4px;">
					<div id="aiscb-provider-info-content"></div>
				</div>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aiscb_api_key"><?php echo esc_html( __( 'API Key', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="aiscb_api_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>" autocomplete="off" />
							<p class="description" id="aiscb_api_key_help"></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiscb_model"><?php echo esc_html( __( 'Model', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<div id="aiscb_model_container">
								<input type="text" class="regular-text code" id="aiscb_model" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[model]" value="<?php echo esc_attr( $settings['model'] ); ?>" spellcheck="false" autocomplete="off" />
								<p class="description"><?php echo esc_html( __( 'The AI model to use for generating answers.', 'ai-site-search-chatbot' ) ); ?></p>
								<p class="description" id="aiscb_model_help"></p>
								<p class="description" id="aiscb_model_reference"></p>
								<p>
									<button type="button" class="button button-secondary" id="aiscb_validate_button"><?php echo esc_html( __( 'Validate API Key and Model', 'ai-site-search-chatbot' ) ); ?></button>
								</p>
								<div id="aiscb_validation_result" class="notice inline" style="display:none; margin: 12px 0 0; padding: 10px 12px;"></div>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiscb_system_prompt"><?php echo esc_html( __( 'System Prompt', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<textarea class="large-text" rows="7" id="aiscb_system_prompt" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[system_prompt]"><?php echo esc_textarea( $settings['system_prompt'] ); ?></textarea>
							<p class="description"><?php echo esc_html( __( 'The system prompt that instructs the AI on how to behave.', 'ai-site-search-chatbot' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiscb_max_sources"><?php echo esc_html( __( 'Maximum Sources', 'ai-site-search-chatbot' ) ); ?></label></th>
						<td>
							<input type="number" min="1" max="10" id="aiscb_max_sources" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_sources]" value="<?php echo esc_attr( (string) absint( $settings['max_sources'] ) ); ?>" />
							<p class="description"><?php echo esc_html( __( 'Maximum number of search results to use as sources.', 'ai-site-search-chatbot' ) ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>

		<style>
			.aiscb-provider-option {
				border: 2px solid #ddd;
				transition: all 0.2s ease;
			}
			.aiscb-provider-option:hover {
				border-color: #0073aa;
				background-color: #f9f9f9;
			}
			.aiscb-provider-option input[type="radio"]:checked ~ div {
				color: #0073aa;
			}
			.aiscb-provider-option input[type="radio"]:checked {
				outline: 2px solid #0073aa;
			}
			.aiscb-provider-info-item {
				margin-bottom: 1rem;
			}
			.aiscb-provider-info-item strong {
				display: block;
				margin-bottom: 0.25rem;
				color: #333;
			}
			.aiscb-provider-info-item ul {
				margin: 0.5rem 0 0 1.5rem;
				padding: 0;
			}
			.aiscb-provider-info-item li {
				margin-bottom: 0.25rem;
			}
			#aiscb_validation_result.notice-success {
				border-left: 4px solid #00a32a;
			}
			#aiscb_validation_result.notice-error {
				border-left: 4px solid #d63638;
			}
			#aiscb_validation_result.notice-warning {
				border-left: 4px solid #dba617;
			}
		</style>

		<script>
		( function() {
			const providers = <?php echo wp_json_encode( $providers ); ?>;
			const validateEndpoint = '<?php echo esc_js( rest_url( self::REST_NAMESPACE . '/validate' ) ); ?>';
			const restNonce = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';

			function formatProviderInfo( provider ) {
				let html = '';
				
				if ( provider.setup_steps ) {
					html += '<div class="aiscb-provider-info-item">';
					html += '<strong><?php echo esc_js( __( 'How to get your API Key:', 'ai-site-search-chatbot' ) ); ?></strong>';
					html += '<ol>';
					provider.setup_steps.forEach( function( step ) {
						html += '<li>' + linkifyText( step ) + '</li>';
					} );
					html += '</ol>';
					html += '</div>';
				}

				if ( provider.note ) {
					html += '<div class="aiscb-provider-info-item" style="padding: 1rem; background: #fff; border-radius: 4px; border-left: 3px solid #0073aa;">';
					html += '<strong><?php echo esc_js( __( 'Note:', 'ai-site-search-chatbot' ) ); ?></strong>';
					html += '<p style="margin: 0.5rem 0 0 0;">' + escapeHtml( provider.note ) + '</p>';
					html += '</div>';
				}

				return html;
			}

			function escapeHtml( text ) {
				const div = document.createElement( 'div' );
				div.textContent = text;
				return div.innerHTML;
			}

			function linkifyText( text ) {
				const escapedText = escapeHtml( text );
				return escapedText.replace(
					/(https?:\/\/[^\s<]+)/g,
					function( url ) {
						return '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + url + '</a>';
					}
				);
			}

			function updateProviderInfo() {
				const selectedProvider = document.querySelector( 'input[name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ai_provider]"]:checked' ).value;
				const providerInfo = providers[ selectedProvider ];
				const infoPanel = document.getElementById( 'aiscb-provider-info-content' );

				if ( providerInfo ) {
					infoPanel.innerHTML = formatProviderInfo( providerInfo );
				}
			}

			function updateModelOptions() {
				const selectedProvider = document.querySelector( 'input[name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ai_provider]"]:checked' ).value;
				const modelHelp = document.getElementById( 'aiscb_model_help' );
				const modelReference = document.getElementById( 'aiscb_model_reference' );
				const providerInfo = providers[ selectedProvider ];

				if ( providerInfo ) {
					modelHelp.textContent = '<?php echo esc_js( __( 'Enter the exact model ID. Example:', 'ai-site-search-chatbot' ) ); ?> ' + ( providerInfo.example_model || '' );

					if ( providerInfo.model_docs_url ) {
						modelReference.innerHTML = '<?php echo esc_js( __( 'Model ID reference:', 'ai-site-search-chatbot' ) ); ?> ' + '<a href="' + escapeHtml( providerInfo.model_docs_url ) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml( providerInfo.model_docs_label || providerInfo.model_docs_url ) + '</a>';
					} else {
						modelReference.textContent = '';
					}
				} else {
					modelHelp.textContent = '';
					modelReference.textContent = '';
				}

				updateProviderInfo();
			}

			function showValidationResult( type, message ) {
				const result = document.getElementById( 'aiscb_validation_result' );
				result.className = 'notice inline notice-' + type;
				result.textContent = message;
				result.style.display = 'block';
			}

			async function validateSettings() {
				const button = document.getElementById( 'aiscb_validate_button' );
				const provider = document.querySelector( 'input[name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ai_provider]"]:checked' );
				const apiKey = document.getElementById( 'aiscb_api_key' );
				const model = document.getElementById( 'aiscb_model' );
				const systemPrompt = document.getElementById( 'aiscb_system_prompt' );

				if ( ! provider || ! apiKey.value.trim() || ! model.value.trim() ) {
					showValidationResult( 'warning', '<?php echo esc_js( __( 'Enter an API key and model ID before running validation.', 'ai-site-search-chatbot' ) ); ?>' );
					return;
				}

				button.disabled = true;
				showValidationResult( 'warning', '<?php echo esc_js( __( 'Validating connection...', 'ai-site-search-chatbot' ) ); ?>' );

				try {
					const response = await fetch( validateEndpoint, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': restNonce
						},
						body: JSON.stringify( {
							ai_provider: provider.value,
							api_key: apiKey.value,
							model: model.value,
							system_prompt: systemPrompt.value
						} )
					} );

					const data = await response.json();

					if ( response.ok && data.success ) {
						showValidationResult( 'success', data.message || '<?php echo esc_js( __( 'Validation succeeded.', 'ai-site-search-chatbot' ) ); ?>' );
						return;
					}

					showValidationResult( 'error', ( data && data.message ) ? data.message : '<?php echo esc_js( __( 'Validation failed.', 'ai-site-search-chatbot' ) ); ?>' );
				} catch ( error ) {
					showValidationResult( 'error', '<?php echo esc_js( __( 'Validation request failed. Please try again.', 'ai-site-search-chatbot' ) ); ?>' );
				} finally {
					button.disabled = false;
				}
			}

			// Update models and info on provider change
			document.querySelectorAll( 'input[name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ai_provider]"]' ).forEach( function( radio ) {
				radio.addEventListener( 'change', updateModelOptions );
			} );

			document.getElementById( 'aiscb_validate_button' ).addEventListener( 'click', validateSettings );

			// Initialize on page load
			updateModelOptions();
		} )();
		</script>
		<?php
	}

	private static function get_providers_config(): array {
		return array(
			'openai'         => array(
				'label'        => __( 'OpenAI', 'ai-site-search-chatbot' ),
				'description'  => __( 'GPT-4, GPT-3.5 Turbo and more', 'ai-site-search-chatbot' ),
				'example_model' => 'gpt-4o-mini',
				'model_docs_url' => 'https://developers.openai.com/api/docs/models',
				'model_docs_label' => __( 'OpenAI models documentation', 'ai-site-search-chatbot' ),
				'setup_steps'  => array(
					__( 'Go to https://platform.openai.com/api-keys', 'ai-site-search-chatbot' ),
					__( 'Sign in or create an OpenAI account', 'ai-site-search-chatbot' ),
					__( 'Click "Create new secret key"', 'ai-site-search-chatbot' ),
					__( 'Copy the generated API key and paste it above', 'ai-site-search-chatbot' ),
				),
				'note'         => __( 'Requires a paid OpenAI account with available credits.', 'ai-site-search-chatbot' ),
			),
			'claude'         => array(
				'label'        => __( 'Claude (Anthropic)', 'ai-site-search-chatbot' ),
				'description'  => __( 'Claude Sonnet, Opus, Haiku and more', 'ai-site-search-chatbot' ),
				'example_model' => 'claude-sonnet-4-6',
				'model_docs_url' => 'https://platform.claude.com/docs/en/docs/about-claude/models',
				'model_docs_label' => __( 'Anthropic model documentation', 'ai-site-search-chatbot' ),
				'setup_steps'  => array(
					__( 'Visit https://platform.claude.com/settings/keys', 'ai-site-search-chatbot' ),
					__( 'Sign in or create an Anthropic account', 'ai-site-search-chatbot' ),
					__( 'Navigate to the API keys section', 'ai-site-search-chatbot' ),
					__( 'Generate a new API key', 'ai-site-search-chatbot' ),
					__( 'Copy and paste the key above', 'ai-site-search-chatbot' ),
				),
				'note'         => __( 'Claude Sonnet is usually the best balance of performance and cost. You can also use the Anthropic Models API to inspect currently available model IDs.', 'ai-site-search-chatbot' ),
			),
			'github-copilot' => array(
				'label'        => __( 'GitHub Models', 'ai-site-search-chatbot' ),
				'description'  => __( 'GitHub Models API with a token that has models:read permission', 'ai-site-search-chatbot' ),
				'example_model' => 'openai/gpt-4.1',
				'model_docs_url' => 'https://github.com/marketplace/models',
				'model_docs_label' => __( 'GitHub Models catalog', 'ai-site-search-chatbot' ),
				'setup_steps'  => array(
					__( 'Visit https://github.com/settings/tokens', 'ai-site-search-chatbot' ),
					__( 'Create a fine-grained personal access token or a token that supports the models:read permission', 'ai-site-search-chatbot' ),
					__( 'Grant the token the "models:read" permission', 'ai-site-search-chatbot' ),
					__( 'Generate and copy the token', 'ai-site-search-chatbot' ),
					__( 'Paste it above as your API key', 'ai-site-search-chatbot' ),
				),
				'note'         => __( 'Use GitHub Models for external API access. Personal access tokens are not supported on the internal Copilot endpoint. For Japanese site content, a multilingual model such as openai/gpt-4.1 is recommended over openai/gpt-5-nano.', 'ai-site-search-chatbot' ),
			),
			'gemini'         => array(
				'label'        => __( 'Google Gemini', 'ai-site-search-chatbot' ),
				'description'  => __( 'Gemini 2.5 Flash, Gemini 2.5 Pro and more', 'ai-site-search-chatbot' ),
				'example_model' => 'gemini-2.5-flash',
				'model_docs_url' => 'https://ai.google.dev/gemini-api/docs/models',
				'model_docs_label' => __( 'Google Gemini model documentation', 'ai-site-search-chatbot' ),
				'setup_steps'  => array(
					__( 'Go to https://aistudio.google.com/app/apikey', 'ai-site-search-chatbot' ),
					__( 'Click "Get API key"', 'ai-site-search-chatbot' ),
					__( 'Create a new project or select an existing one', 'ai-site-search-chatbot' ),
					__( 'Generate an API key for use in the application', 'ai-site-search-chatbot' ),
					__( 'Copy and paste the key above', 'ai-site-search-chatbot' ),
				),
				'note'         => __( 'Use a current stable Gemini model such as gemini-2.5-flash. Older Gemini 2.0 model IDs are being deprecated.', 'ai-site-search-chatbot' ),
			),
		);
	}

	public static function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_chat_request' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'message' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							if ( ! is_string( $value ) ) {
								return false;
							}

							$trimmed = trim( $value );

							if ( '' === $trimmed ) {
								return false;
							}

							return strlen( $trimmed ) <= 500;
						},
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/validate',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_validate_request' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	public static function handle_validate_request( WP_REST_Request $request ) {
		$settings = array(
			'ai_provider'   => sanitize_text_field( (string) $request->get_param( 'ai_provider' ) ),
			'api_key'       => sanitize_text_field( (string) $request->get_param( 'api_key' ) ),
			'model'         => sanitize_text_field( (string) $request->get_param( 'model' ) ),
			'system_prompt' => sanitize_textarea_field( (string) $request->get_param( 'system_prompt' ) ),
			'max_sources'   => 1,
		);

		$settings = wp_parse_args( $settings, self::default_settings() );

		if ( '' === trim( $settings['api_key'] ) || '' === trim( $settings['model'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Enter an API key and model ID before running validation.', 'ai-site-search-chatbot' ),
				),
				400
			);
		}

		$result = self::validate_provider_settings( $settings );

		$status = ! empty( $result['success'] ) ? 200 : 400;

		return new WP_REST_Response( $result, $status );
	}

	public static function handle_chat_request( WP_REST_Request $request ) {
		if ( self::is_rate_limited() ) {
			return new WP_REST_Response(
				array(
					'answer' => __( 'Too many requests. Please wait a moment and try again.', 'ai-site-search-chatbot' ),
				),
				429
			);
		}

		$message = self::sanitize_message( (string) $request->get_param( 'message' ) );

		if ( '' === $message ) {
			return new WP_REST_Response(
				array(
					'answer' => __( 'Please enter a question.', 'ai-site-search-chatbot' ),
				),
				400
			);
		}

		$results = self::search_site_content( $message );
		$answer = self::generate_answer( $message, $results );

		return rest_ensure_response(
			array(
				'query'     => $message,
				'answer'    => $answer['answer'],
				'used_ai'   => $answer['used_ai'],
				'sources'   => $answer['sources'],
				'searches'  => $results,
			)
		);
	}

	public static function render_shortcode( $atts = array() ): string {
		self::enqueue_assets();

		$atts = shortcode_atts(
			array(
				'title'   => __( 'Ask about this site', 'ai-site-search-chatbot' ),
				'greeting' => __( 'Hi, ask me about this site and I will search the content for you.', 'ai-site-search-chatbot' ),
			),
			$atts,
			self::SHORTCODE
		);

		ob_start();
		?>
		<div
			class="aiscb-widget"
			data-endpoint="<?php echo esc_url( rest_url( self::REST_NAMESPACE . '/chat' ) ); ?>"
			data-greeting="<?php echo esc_attr( $atts['greeting'] ); ?>"
			data-thinking-label="<?php echo esc_attr__( 'Thinking...', 'ai-site-search-chatbot' ); ?>"
			data-source-label="<?php echo esc_attr__( 'Sources', 'ai-site-search-chatbot' ); ?>"
			data-error-label="<?php echo esc_attr__( 'The chatbot is temporarily unavailable. Please try again later.', 'ai-site-search-chatbot' ); ?>"
		>
			<div class="aiscb-widget__shell">
				<div class="aiscb-widget__header">
					<div class="aiscb-widget__eyebrow"><?php echo esc_html__( 'Site Assistant', 'ai-site-search-chatbot' ); ?></div>
					<h3 class="aiscb-widget__title"><?php echo esc_html( $atts['title'] ); ?></h3>
				</div>
				<div class="aiscb-widget__messages" aria-live="polite" aria-label="<?php echo esc_attr__( 'Chat messages', 'ai-site-search-chatbot' ); ?>"></div>
				<form class="aiscb-widget__form">
					<label class="screen-reader-text" for="aiscb-message"><?php echo esc_html__( 'Your question', 'ai-site-search-chatbot' ); ?></label>
					<textarea id="aiscb-message" class="aiscb-widget__input" rows="3" placeholder="<?php echo esc_attr__( 'Ask a question about products, services, or help pages...', 'ai-site-search-chatbot' ); ?>"></textarea>
					<button type="submit" class="aiscb-widget__submit"><?php echo esc_html__( 'Send', 'ai-site-search-chatbot' ); ?></button>
				</form>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function enqueue_assets(): void {
		if ( self::$assets_enqueued ) {
			return;
		}

		self::$assets_enqueued = true;

		wp_register_style( 'aiscb-frontend', false, array(), self::VERSION );
		wp_enqueue_style( 'aiscb-frontend' );
		wp_add_inline_style( 'aiscb-frontend', self::inline_css() );

		wp_register_script( 'aiscb-frontend', false, array(), self::VERSION, true );
		wp_enqueue_script( 'aiscb-frontend' );
		wp_add_inline_script( 'aiscb-frontend', self::inline_js() );
	}

	private static function inline_css(): string {
		return <<<'CSS'
.aiscb-widget {
		max-width: 760px;
		margin: 2rem auto;
		padding: 0;
	}

.aiscb-widget__shell {
		border: 1px solid rgba(15, 23, 42, 0.12);
		border-radius: 24px;
		background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
		box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
		overflow: hidden;
	}

.aiscb-widget__header {
		padding: 1.5rem 1.5rem 1rem;
		background: linear-gradient(135deg, #0f172a 0%, #1f2937 100%);
		color: #ffffff;
	}

.aiscb-widget__eyebrow {
		text-transform: uppercase;
		letter-spacing: 0.12em;
		font-size: 0.72rem;
		opacity: 0.8;
		margin-bottom: 0.25rem;
	}

.aiscb-widget__title {
		margin: 0;
		font-size: 1.35rem;
		line-height: 1.3;
	}

.aiscb-widget__messages {
		padding: 1.25rem 1.5rem;
		min-height: 240px;
		display: flex;
		flex-direction: column;
		gap: 0.85rem;
	}

.aiscb-widget__message {
		max-width: 88%;
		padding: 0.9rem 1rem;
		border-radius: 18px;
		line-height: 1.6;
		white-space: pre-wrap;
		word-break: break-word;
	}

.aiscb-widget__message--assistant {
		background: #e2e8f0;
		color: #0f172a;
	}

.aiscb-widget__message--user {
		align-self: flex-end;
		background: #2563eb;
		color: #ffffff;
	}

.aiscb-widget__sources {
		margin-top: 0.75rem;
		padding-top: 0.75rem;
		border-top: 1px solid rgba(15, 23, 42, 0.1);
	}

.aiscb-widget__sources-title {
		font-size: 0.82rem;
		font-weight: 700;
		margin-bottom: 0.35rem;
	}

.aiscb-widget__source-list {
		margin: 0;
		padding-left: 1.1rem;
	}

.aiscb-widget__form {
		display: grid;
		gap: 0.75rem;
		padding: 1.25rem 1.5rem 1.5rem;
		background: rgba(148, 163, 184, 0.12);
	}

.aiscb-widget__input {
		width: 100%;
		min-height: 96px;
		padding: 0.9rem 1rem;
		border-radius: 16px;
		border: 1px solid rgba(15, 23, 42, 0.15);
		background: #ffffff;
		resize: vertical;
	}

.aiscb-widget__submit {
		justify-self: end;
		padding: 0.75rem 1.15rem;
		border: 0;
		border-radius: 999px;
		background: #0f172a;
		color: #ffffff;
		font-weight: 700;
		cursor: pointer;
	}

.aiscb-widget__submit:disabled {
		opacity: 0.6;
		cursor: progress;
	}

@media (max-width: 640px) {
	.aiscb-widget__message {
		max-width: 100%;
	}

	.aiscb-widget__header,
	.aiscb-widget__messages,
	.aiscb-widget__form {
		padding-left: 1rem;
		padding-right: 1rem;
	}
}
CSS;
	}

	private static function inline_js(): string {
		return <<<'JS'
( function () {
	function addMessage( container, role, text, sources ) {
		var bubble = document.createElement( 'div' );
		bubble.className = 'aiscb-widget__message aiscb-widget__message--' + role;
		bubble.textContent = text;

		if ( Array.isArray( sources ) && sources.length ) {
			var sourceWrap = document.createElement( 'div' );
			sourceWrap.className = 'aiscb-widget__sources';

			var sourceTitle = document.createElement( 'div' );
			sourceTitle.className = 'aiscb-widget__sources-title';
			sourceTitle.textContent = container.closest( '.aiscb-widget' ).dataset.sourceLabel || 'Sources';
			sourceWrap.appendChild( sourceTitle );

			var list = document.createElement( 'ul' );
			list.className = 'aiscb-widget__source-list';

			sources.forEach( function ( source ) {
				var item = document.createElement( 'li' );
				var link = document.createElement( 'a' );
				link.href = source.url;
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
				link.textContent = source.title;
				item.appendChild( link );
				list.appendChild( item );
			} );

			sourceWrap.appendChild( list );
			bubble.appendChild( sourceWrap );
		}

		container.appendChild( bubble );
		container.scrollTop = container.scrollHeight;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var widgets = document.querySelectorAll( '.aiscb-widget' );

		widgets.forEach( function ( widget ) {
			var endpoint = widget.dataset.endpoint;
			var greeting = widget.dataset.greeting || 'Hi, ask me a question about this site.';
			var thinkingLabel = widget.dataset.thinkingLabel || 'Thinking...';
			var errorLabel = widget.dataset.errorLabel || 'The chatbot is temporarily unavailable. Please try again later.';
			var messages = widget.querySelector( '.aiscb-widget__messages' );
			var form = widget.querySelector( '.aiscb-widget__form' );
			var input = widget.querySelector( '.aiscb-widget__input' );
			var submit = widget.querySelector( '.aiscb-widget__submit' );

			addMessage( messages, 'assistant', greeting );

			form.addEventListener( 'submit', async function ( event ) {
				event.preventDefault();

				var value = input.value.trim();

				if ( ! value ) {
					return;
				}

				addMessage( messages, 'user', value );
				input.value = '';
				submit.disabled = true;
				input.disabled = true;

				var pending = document.createElement( 'div' );
				pending.className = 'aiscb-widget__message aiscb-widget__message--assistant';
				pending.textContent = thinkingLabel;
				messages.appendChild( pending );

				try {
					var response = await fetch( endpoint, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json'
						},
						body: JSON.stringify( {
							message: value
						} )
					} );

					var data = await response.json();
					messages.removeChild( pending );
					addMessage( messages, 'assistant', data.answer || 'No answer was returned.', data.sources || [] );
				} catch ( error ) {
					messages.removeChild( pending );
					addMessage( messages, 'assistant', errorLabel );
				} finally {
					submit.disabled = false;
					input.disabled = false;
					input.focus();
				}
			} );
		} );
	} );
} )();
JS;
	}

	private static function sanitize_message( string $message ): string {
		$message = trim( wp_strip_all_tags( $message ) );

		if ( strlen( $message ) > 500 ) {
			$message = substr( $message, 0, 500 );
		}

		return $message;
	}

	private static function is_rate_limited(): bool {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'aiscb_rate_' . md5( $ip );
		$state = get_transient( $key );

		if ( ! is_array( $state ) ) {
			$state = array(
				'count' => 0,
				'last'  => time(),
			);
		}

		$state['count']++;

		set_transient( $key, $state, MINUTE_IN_SECONDS );

		return $state['count'] > 30;
	}

	private static function search_site_content( string $message ): array {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );

		$query = new WP_Query(
			array(
				's'                   => $message,
				'post_type'           => array_values( $post_types ),
				'post_status'         => 'publish',
				'posts_per_page'      => 10,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		$results = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$excerpt = get_the_excerpt( $post );

			if ( '' === trim( $excerpt ) ) {
				$excerpt = wp_strip_all_tags( wp_trim_words( $post->post_content, 36 ) );
			}

			$results[] = array(
				'id'      => (int) $post->ID,
				'title'   => get_the_title( $post ),
				'url'     => get_permalink( $post ),
				'excerpt' => wp_strip_all_tags( $excerpt ),
			);
		}

		wp_reset_postdata();

		return $results;
	}

	private static function generate_answer( string $message, array $results ): array {
		$settings = self::get_settings();

		if ( empty( $settings['api_key'] ) ) {
			return array(
				'answer'   => self::build_fallback_answer( $message, $results ),
				'used_ai'  => false,
				'sources'  => self::build_sources( $results, (int) $settings['max_sources'] ),
			);
		}

		$provider = $settings['ai_provider'] ?? 'openai';

		// Call appropriate provider API
		switch ( $provider ) {
			case 'claude':
				$response_data = self::call_claude_api( $settings, $message, $results );
				break;
			case 'github-copilot':
				$response_data = self::call_github_copilot_api( $settings, $message, $results );
				break;
			case 'gemini':
				$response_data = self::call_gemini_api( $settings, $message, $results );
				break;
			case 'openai':
			default:
				$response_data = self::call_openai_api( $settings, $message, $results );
		}

		if ( is_wp_error( $response_data ) || ! $response_data['success'] ) {
			return array(
				'answer'  => self::build_fallback_answer( $message, $results ),
				'used_ai' => false,
				'sources' => self::build_sources( $results, (int) $settings['max_sources'] ),
			);
		}

		return array(
			'answer'  => $response_data['content'],
			'used_ai' => true,
			'sources' => self::build_sources( $results, (int) $settings['max_sources'] ),
		);
	}

	private static function validate_provider_settings( array $settings ): array {
		if ( 'github-copilot' === $settings['ai_provider'] && false === strpos( (string) $settings['model'], '/' ) ) {
			return array(
				'success' => false,
				'message' => __( 'For GitHub Models, enter the model ID in the format publisher/model_name, for example openai/gpt-5-nano.', 'ai-site-search-chatbot' ),
			);
		}

		if ( 'github-copilot' === $settings['ai_provider'] ) {
			$model_check = self::validate_github_models_catalog( $settings );

			if ( ! empty( $model_check ) ) {
				return $model_check;
			}
		}

		$probe_message = 'Reply with OK only.';
		$probe_results = array();

		switch ( $settings['ai_provider'] ) {
			case 'claude':
				$result = self::call_claude_api( $settings, $probe_message, $probe_results );
				break;
			case 'github-copilot':
					$result = self::call_github_copilot_api( $settings, $probe_message, $probe_results, true );
				break;
			case 'gemini':
				$result = self::call_gemini_api( $settings, $probe_message, $probe_results );
				break;
			case 'openai':
			default:
				$result = self::call_openai_api( $settings, $probe_message, $probe_results );
				break;
		}

		if ( ! empty( $result['success'] ) ) {
			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: 1: provider name, 2: model id */
					__( 'Validation succeeded for %1$s using model %2$s.', 'ai-site-search-chatbot' ),
					self::get_provider_label( (string) $settings['ai_provider'] ),
					(string) $settings['model']
				),
			);
		}

		return array(
			'success' => false,
			'message' => ! empty( $result['message'] ) ? $result['message'] : __( 'Validation failed.', 'ai-site-search-chatbot' ),
		);
	}

	private static function get_provider_label( string $provider ): string {
		$config = self::get_providers_config();

		if ( isset( $config[ $provider ]['label'] ) ) {
			return (string) $config[ $provider ]['label'];
		}

		return $provider;
	}

	private static function build_api_error_result( $response, string $fallback_message ): array {
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => self::append_response_request_id(
					sprintf(
					/* translators: 1: fallback message, 2: detailed error message */
					__( '%1$s Details: %2$s', 'ai-site-search-chatbot' ),
					$fallback_message,
					$response->get_error_message()
					),
					$response
				),
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$response_message = (string) wp_remote_retrieve_response_message( $response );
		$raw_body = (string) wp_remote_retrieve_body( $response );
		$body = json_decode( $raw_body, true );
		$detail = self::extract_api_error_detail( $body, $raw_body );

		if ( '' === $detail ) {
			$detail = $response_message;
		}

		if ( '' !== $detail && 0 < $status_code ) {
			$message = sprintf(
				/* translators: 1: fallback message, 2: HTTP status code, 3: detailed error message */
				__( '%1$s HTTP %2$d: %3$s', 'ai-site-search-chatbot' ),
				$fallback_message,
				$status_code,
				$detail
			);
		} elseif ( '' !== $detail ) {
			$message = sprintf(
				/* translators: 1: fallback message, 2: detailed error message */
				__( '%1$s Details: %2$s', 'ai-site-search-chatbot' ),
				$fallback_message,
				$detail
			);
		} elseif ( 0 < $status_code ) {
			$message = sprintf(
				/* translators: 1: fallback message, 2: HTTP status code */
				__( '%1$s HTTP %2$d', 'ai-site-search-chatbot' ),
				$fallback_message,
				$status_code
			);
		} else {
			$message = $fallback_message;
		}

		return array(
			'success' => false,
			'message' => self::append_response_request_id( $message, $response ),
		);
	}

	private static function append_response_request_id( string $message, $response ): string {
		$request_id = '';

		if ( ! is_wp_error( $response ) ) {
			$request_id = (string) wp_remote_retrieve_header( $response, 'x-request-id' );

			if ( '' === $request_id ) {
				$request_id = (string) wp_remote_retrieve_header( $response, 'x-github-request-id' );
			}
		}

		if ( '' === trim( $request_id ) ) {
			return $message;
		}

		return sprintf(
			/* translators: 1: message, 2: upstream request id */
			__( '%1$s Request ID: %2$s', 'ai-site-search-chatbot' ),
			$message,
			trim( $request_id )
		);
	}

	private static function build_github_models_inference_error_result( $response ): array {
		$message = self::build_api_error_result( $response, __( 'GitHub Models validation failed.', 'ai-site-search-chatbot' ) );
		$status_code = (int) wp_remote_retrieve_response_code( $response );

		if ( 500 !== $status_code || empty( $message['message'] ) ) {
			return $message;
		}

		$message['message'] .= ' ' . __( 'The request reached GitHub Models, but the inference endpoint returned an internal error. This usually indicates a temporary provider-side issue or a model-specific availability problem. Try another GitHub Models model such as openai/gpt-4.1, or retry later.', 'ai-site-search-chatbot' );

		return $message;
	}

	private static function validate_github_models_catalog( array $settings ): array {
		$response = wp_remote_get(
			'https://models.github.ai/catalog/models',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization'         => 'Bearer ' . $settings['api_key'],
					'Accept'                => 'application/vnd.github+json',
					'X-GitHub-Api-Version'  => '2026-03-10',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: 1: fallback message, 2: detailed error message */
					__( '%1$s Details: %2$s', 'ai-site-search-chatbot' ),
					__( 'GitHub Models catalog check failed.', 'ai-site-search-chatbot' ),
					$response->get_error_message()
				),
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return self::build_api_error_result( $response, __( 'GitHub Models catalog check failed.', 'ai-site-search-chatbot' ) );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return array(
				'success' => false,
				'message' => __( 'GitHub Models catalog check failed. The catalog response could not be parsed.', 'ai-site-search-chatbot' ),
			);
		}

		foreach ( $body as $model ) {
			if ( is_array( $model ) && isset( $model['id'] ) && (string) $model['id'] === (string) $settings['model'] ) {
				return array();
			}
		}

		return array(
			'success' => false,
			'message' => sprintf(
				/* translators: %s: model id */
				__( 'The model %s was not found in the GitHub Models catalog for this token. Open the model catalog reference and choose a model ID listed there.', 'ai-site-search-chatbot' ),
				(string) $settings['model']
			),
		);
	}

	private static function extract_api_error_detail( $body, string $raw_body ): string {
		$candidates = array();

		if ( is_array( $body ) ) {
			$paths = array(
				array( 'error', 'message' ),
				array( 'error', 'status' ),
				array( 'error', 'type' ),
				array( 'message' ),
				array( 'detail' ),
				array( 'error_description' ),
				array( 'details' ),
				array( 'errors', 0, 'message' ),
				array( 'errors', 0, 'detail' ),
				array( 'error' ),
			);

			foreach ( $paths as $path ) {
				$value = $body;

				foreach ( $path as $segment ) {
					if ( is_array( $value ) && isset( $value[ $segment ] ) ) {
						$value = $value[ $segment ];
					} else {
						$value = null;
						break;
					}
				}

				if ( is_string( $value ) && '' !== trim( $value ) ) {
					$candidates[] = trim( $value );
				} elseif ( is_array( $value ) ) {
					$flattened = wp_json_encode( $value );

					if ( is_string( $flattened ) && '' !== trim( $flattened ) ) {
						$candidates[] = trim( $flattened );
					}
				}
			}
		}

		if ( empty( $candidates ) ) {
			$stripped_body = trim( wp_strip_all_tags( $raw_body ) );

			if ( '' !== $stripped_body ) {
				$candidates[] = preg_replace( '/\s+/', ' ', $stripped_body );
			}
		}

		foreach ( $candidates as $candidate ) {
			if ( '' !== $candidate ) {
				return mb_substr( $candidate, 0, 220 );
			}
		}

		return '';
	}

	private static function call_openai_api( array $settings, string $message, array $results ): array {
		$prompt = self::build_ai_prompt( $message, $results, (int) $settings['max_sources'] );
		$payload = array(
			'model'       => $settings['model'],
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $settings['system_prompt'],
				),
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $settings['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::build_api_error_result( $response, __( 'OpenAI validation failed.', 'ai-site-search-chatbot' ) );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return self::build_api_error_result( $response, __( 'OpenAI validation failed.', 'ai-site-search-chatbot' ) );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$content = '';

		if ( isset( $body['choices'][0]['message']['content'] ) ) {
			$content = trim( (string) $body['choices'][0]['message']['content'] );
		}

		if ( '' === $content ) {
			return array(
				'success' => false,
				'message' => __( 'OpenAI returned an empty response.', 'ai-site-search-chatbot' ),
			);
		}

		return array(
			'success' => true,
			'content' => $content,
		);
	}

	private static function call_claude_api( array $settings, string $message, array $results ): array {
		$prompt = self::build_ai_prompt( $message, $results, (int) $settings['max_sources'] );
		$payload = array(
			'model'       => $settings['model'],
			'max_tokens'  => 1024,
			'system'      => $settings['system_prompt'],
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => 20,
				'headers' => array(
					'x-api-key'       => $settings['api_key'],
					'anthropic-version' => '2023-06-01',
					'Content-Type'    => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::build_api_error_result( $response, __( 'Claude validation failed.', 'ai-site-search-chatbot' ) );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return self::build_api_error_result( $response, __( 'Claude validation failed.', 'ai-site-search-chatbot' ) );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$content = '';

		if ( isset( $body['content'][0]['text'] ) ) {
			$content = trim( (string) $body['content'][0]['text'] );
		}

		if ( '' === $content ) {
			return array(
				'success' => false,
				'message' => __( 'Claude returned an empty response.', 'ai-site-search-chatbot' ),
			);
		}

		return array(
			'success' => true,
			'content' => $content,
		);
	}

	private static function call_github_copilot_api( array $settings, string $message, array $results, bool $is_validation = false ): array {
		$prompt = $is_validation ? $message : self::build_ai_prompt( $message, $results, (int) $settings['max_sources'] );
		$messages = array(
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		);

		if ( ! $is_validation && '' !== trim( (string) $settings['system_prompt'] ) ) {
			array_unshift(
				$messages,
				array(
					'role'    => 'system',
					'content' => $settings['system_prompt'],
				)
			);
		}

		$payload = array(
			'model'    => $settings['model'],
			'messages' => $messages,
		);

		$response = wp_remote_post(
			'https://models.github.ai/inference/chat/completions',
			array(
				'timeout' => 45,
				'headers' => array(
					'Authorization' => 'Bearer ' . $settings['api_key'],
					'Accept'        => 'application/vnd.github+json',
					'X-GitHub-Api-Version' => '2026-03-10',
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::build_github_models_inference_error_result( $response );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return self::build_github_models_inference_error_result( $response );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$content = '';

		if ( isset( $body['choices'][0]['message']['content'] ) ) {
			$content = trim( (string) $body['choices'][0]['message']['content'] );
		}

		if ( '' === $content ) {
			return array(
				'success' => false,
				'message' => __( 'GitHub Models returned an empty response.', 'ai-site-search-chatbot' ),
			);
		}

		return array(
			'success' => true,
			'content' => $content,
		);
	}

	private static function call_gemini_api( array $settings, string $message, array $results ): array {
		$prompt = self::build_ai_prompt( $message, $results, (int) $settings['max_sources'] );
		$model = str_replace( '/', '%2F', $settings['model'] );

		$payload = array(
			'contents' => array(
				array(
					'parts' => array(
						array(
							'text' => $settings['system_prompt'] . "\n\n" . $prompt,
						),
					),
				),
			),
		);

		$response = wp_remote_post(
			'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $settings['api_key'],
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::build_api_error_result( $response, __( 'Gemini validation failed.', 'ai-site-search-chatbot' ) );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return self::build_api_error_result( $response, __( 'Gemini validation failed.', 'ai-site-search-chatbot' ) );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$content = '';

		if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$content = trim( (string) $body['candidates'][0]['content']['parts'][0]['text'] );
		}

		if ( '' === $content ) {
			return array(
				'success' => false,
				'message' => __( 'Gemini returned an empty response.', 'ai-site-search-chatbot' ),
			);
		}

		return array(
			'success' => true,
			'content' => $content,
		);
	}

	private static function build_ai_prompt( string $message, array $results, int $max_sources ): string {
		$lines = array(
			'Visitor question:',
			$message,
			'',
			'Site search results:',
		);

		foreach ( array_slice( $results, 0, $max_sources ) as $result ) {
			$lines[] = sprintf( '- %s', $result['title'] );
			$lines[] = sprintf( '  URL: %s', $result['url'] );
			$lines[] = sprintf( '  Excerpt: %s', $result['excerpt'] );
		}

		$lines[] = '';
		$lines[] = 'Instructions: answer in a helpful, concise tone. Use only the site results above. If no result is relevant, say that the site does not currently contain a clear answer and suggest a related page or keyword.';

		return implode( "\n", $lines );
	}

	private static function build_fallback_answer( string $message, array $results ): string {
		if ( empty( $results ) ) {
			return __( 'No matching content was found on this site. Try a different keyword or open a more specific page.', 'ai-site-search-chatbot' );
		}

		$lines = array(
			__( 'I found some related pages on this site:', 'ai-site-search-chatbot' ),
		);

		foreach ( array_slice( $results, 0, 3 ) as $result ) {
			$lines[] = sprintf( '%s: %s', $result['title'], $result['excerpt'] );
		}

		$lines[] = __( 'If you want, I can search again with a narrower keyword.', 'ai-site-search-chatbot' );

		return implode( "\n", $lines );
	}

	private static function build_sources( array $results, int $max_sources ): array {
		$sources = array();

		foreach ( array_slice( $results, 0, $max_sources ) as $result ) {
			$sources[] = array(
				'title'   => $result['title'],
				'url'     => $result['url'],
				'excerpt' => $result['excerpt'],
			);
		}

		return $sources;
	}
}
