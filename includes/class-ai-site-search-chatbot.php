<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AISite_Search_Chatbot {
	const VERSION = '0.4.0';
	const OPTION_KEY = 'aiscb_settings';
	const OPTION_GROUP = 'aiscb_settings_group';
	const REST_NAMESPACE = 'ai-site-search-chatbot/v1';
	const SHORTCODE = 'ai_site_search_chatbot';
	const CHAT_LOG_OPTION = 'aiscb_chat_logs';
	const CHAT_LOG_LIMIT = 50;

	public static function activate(): void {
		if ( false !== get_option( self::OPTION_KEY, false ) ) {
			return;
		}

		add_option( self::OPTION_KEY, self::default_settings() );
	}

	public static function init(): void {
		self::load_textdomain();
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		AISite_Search_Chatbot_Admin::init();
		AISite_Search_Chatbot_Block::init();
		AISite_Search_Chatbot_Frontend::init();
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
			'ai_provider'         => 'openai',
			'api_key'             => '',
			'model'               => '',
			'system_prompt'       => self::get_default_system_prompt(),
			'max_sources'         => 5,
			'ai_limit_window_10m' => 8,
			'ai_limit_window_1h'  => 24,
			'widget_enabled'      => 0,
			'widget_display_mode' => 'all-pages',
			'widget_theme'        => 'business',
			'claude_auth_mode'    => 'api_key',
			'claude_bearer_token' => '',
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

		$widget_theme = isset( $input['widget_theme'] ) ? sanitize_key( wp_unslash( $input['widget_theme'] ) ) : $defaults['widget_theme'];
		if ( ! array_key_exists( $widget_theme, self::get_widget_themes() ) ) {
			$widget_theme = $defaults['widget_theme'];
		}

		$widget_display_mode = isset( $input['widget_display_mode'] ) ? sanitize_key( wp_unslash( $input['widget_display_mode'] ) ) : $defaults['widget_display_mode'];
		if ( ! array_key_exists( $widget_display_mode, self::get_widget_display_modes() ) ) {
			$widget_display_mode = $defaults['widget_display_mode'];
		}

		$claude_auth_mode = isset( $input['claude_auth_mode'] ) ? sanitize_key( wp_unslash( $input['claude_auth_mode'] ) ) : $defaults['claude_auth_mode'];
		if ( ! in_array( $claude_auth_mode, array( 'api_key', 'bearer_token' ), true ) ) {
			$claude_auth_mode = $defaults['claude_auth_mode'];
		}

		return array(
			'ai_provider'         => $provider,
			'api_key'             => isset( $input['api_key'] ) ? sanitize_text_field( wp_unslash( $input['api_key'] ) ) : $defaults['api_key'],
			'model'               => isset( $input['model'] ) ? sanitize_text_field( wp_unslash( $input['model'] ) ) : $defaults['model'],
			'system_prompt'       => isset( $input['system_prompt'] ) ? sanitize_textarea_field( wp_unslash( $input['system_prompt'] ) ) : $defaults['system_prompt'],
			'max_sources'         => isset( $input['max_sources'] ) ? max( 1, min( 10, absint( $input['max_sources'] ) ) ) : $defaults['max_sources'],
			'ai_limit_window_10m' => isset( $input['ai_limit_window_10m'] ) ? max( 1, min( 30, absint( $input['ai_limit_window_10m'] ) ) ) : $defaults['ai_limit_window_10m'],
			'ai_limit_window_1h'  => isset( $input['ai_limit_window_1h'] ) ? max( 1, min( 100, absint( $input['ai_limit_window_1h'] ) ) ) : $defaults['ai_limit_window_1h'],
			'widget_enabled'      => isset( $input['widget_enabled'] ) ? 1 : 0,
			'widget_display_mode' => $widget_display_mode,
			'widget_theme'        => $widget_theme,
			'claude_auth_mode'    => $claude_auth_mode,
			'claude_bearer_token' => isset( $input['claude_bearer_token'] ) ? sanitize_text_field( wp_unslash( $input['claude_bearer_token'] ) ) : $defaults['claude_bearer_token'],
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

	public static function get_providers_config(): array {
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
				'auth_modes'   => array(
					'api_key'      => __( 'API Key (pay-per-use)', 'ai-site-search-chatbot' ),
					'bearer_token' => __( 'Bearer Token (Agent SDK credits)', 'ai-site-search-chatbot' ),
				),
				'setup_steps'  => array(
					__( 'Visit https://platform.claude.com/settings/keys', 'ai-site-search-chatbot' ),
					__( 'Sign in or create an Anthropic account', 'ai-site-search-chatbot' ),
					__( 'Navigate to the API keys section', 'ai-site-search-chatbot' ),
					__( 'Generate a new API key', 'ai-site-search-chatbot' ),
					__( 'Copy and paste the key above', 'ai-site-search-chatbot' ),
				),
				'note'         => __( 'Uses the official Anthropic PHP SDK with automatic system prompt caching, which reduces API costs when the system prompt is reused across requests. Claude Sonnet 4.6 is a good balance of performance and cost; Claude Opus 4.7 offers the highest capability. See the Anthropic model documentation for available model IDs.', 'ai-site-search-chatbot' ),
				'bearer_token_setup_steps' => array(
					__( 'Ensure you have a Pro, Max, Team, or Enterprise Claude plan', 'ai-site-search-chatbot' ),
					__( 'Claim your Agent SDK credits at claude.ai → Settings → Agent SDK (available from June 15, 2026)', 'ai-site-search-chatbot' ),
					__( 'On your computer (Windows or Mac), install Node.js if not already installed: https://nodejs.org', 'ai-site-search-chatbot' ),
					__( 'On your computer (Windows or Mac), install Claude Code: npm install -g @anthropic-ai/claude-code', 'ai-site-search-chatbot' ),
					__( 'On your computer (Windows or Mac), sign in: claude auth login', 'ai-site-search-chatbot' ),
					__( 'Copy your auth token from the Claude Code session and paste it in the Bearer Token field', 'ai-site-search-chatbot' ),
				),
				'bearer_token_note' => __( 'Consumes your Claude plan monthly Agent SDK credits (e.g. $20/month for Pro). Credits reset each billing cycle and are not shared across team members. When credits run out, requests stop unless additional usage billing is enabled. Available from June 15, 2026.', 'ai-site-search-chatbot' ),
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

	public static function get_widget_themes(): array {
		return array(
			'business' => array(
				'label'       => __( 'Business', 'ai-site-search-chatbot' ),
				'description' => __( 'Clean and trustworthy styling for company sites, support pages, and professional services.', 'ai-site-search-chatbot' ),
			),
			'cute'     => array(
				'label'       => __( 'Cute', 'ai-site-search-chatbot' ),
				'description' => __( 'Soft colors and rounded shapes for friendly brands, salons, schools, and personal sites.', 'ai-site-search-chatbot' ),
			),
		);
	}

	public static function get_widget_display_modes(): array {
		return array(
			'all-pages' => array(
				'label' => __( 'Display on all pages', 'ai-site-search-chatbot' ),
				'description' => __( 'Automatically show the chatbot on every public page.', 'ai-site-search-chatbot' ),
			),
			'front-page' => array(
				'label' => __( 'Display only on the site top page', 'ai-site-search-chatbot' ),
				'description' => __( 'Automatically show the chatbot only on the top page of the site.', 'ai-site-search-chatbot' ),
			),
			'shortcode' => array(
				'label' => __( 'Display only where the shortcode is placed', 'ai-site-search-chatbot' ),
				'description' => __( 'Show the chatbot only on pages where the shortcode or dedicated block is inserted.', 'ai-site-search-chatbot' ),
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

		register_rest_route(
			self::REST_NAMESPACE,
			'/test-chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_test_chat_request' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	public static function handle_validate_request( WP_REST_Request $request ) {
		$claude_auth_mode = sanitize_key( (string) $request->get_param( 'claude_auth_mode' ) );
		if ( ! in_array( $claude_auth_mode, array( 'api_key', 'bearer_token' ), true ) ) {
			$claude_auth_mode = 'api_key';
		}

		$settings = array(
			'ai_provider'         => sanitize_text_field( (string) $request->get_param( 'ai_provider' ) ),
			'api_key'             => sanitize_text_field( (string) $request->get_param( 'api_key' ) ),
			'model'               => sanitize_text_field( (string) $request->get_param( 'model' ) ),
			'system_prompt'       => sanitize_textarea_field( (string) $request->get_param( 'system_prompt' ) ),
			'max_sources'         => 1,
			'claude_auth_mode'    => $claude_auth_mode,
			'claude_bearer_token' => sanitize_text_field( (string) $request->get_param( 'claude_bearer_token' ) ),
		);

		$settings = wp_parse_args( $settings, self::default_settings() );

		$is_bearer_mode = 'claude' === $settings['ai_provider'] && 'bearer_token' === $settings['claude_auth_mode'];
		$credential     = $is_bearer_mode ? $settings['claude_bearer_token'] : $settings['api_key'];

		if ( '' === trim( $credential ) || '' === trim( $settings['model'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $is_bearer_mode
						? __( 'Enter a bearer token and model ID before running validation.', 'ai-site-search-chatbot' )
						: __( 'Enter an API key and model ID before running validation.', 'ai-site-search-chatbot' ),
				),
				400
			);
		}

		$result = self::validate_provider_settings( $settings );

		$status = ! empty( $result['success'] ) ? 200 : 400;

		return new WP_REST_Response( $result, $status );
	}

	public static function handle_test_chat_request( WP_REST_Request $request ) {
		$claude_auth_mode = sanitize_key( (string) $request->get_param( 'claude_auth_mode' ) );
		if ( ! in_array( $claude_auth_mode, array( 'api_key', 'bearer_token' ), true ) ) {
			$claude_auth_mode = 'api_key';
		}

		$settings = array(
			'ai_provider'         => sanitize_text_field( (string) $request->get_param( 'ai_provider' ) ),
			'api_key'             => sanitize_text_field( (string) $request->get_param( 'api_key' ) ),
			'model'               => sanitize_text_field( (string) $request->get_param( 'model' ) ),
			'system_prompt'       => sanitize_textarea_field( (string) $request->get_param( 'system_prompt' ) ),
			'max_sources'         => max( 1, min( 10, absint( $request->get_param( 'max_sources' ) ) ) ),
			'claude_auth_mode'    => $claude_auth_mode,
			'claude_bearer_token' => sanitize_text_field( (string) $request->get_param( 'claude_bearer_token' ) ),
		);

		$settings = wp_parse_args( $settings, self::default_settings() );
		$message = self::sanitize_message( (string) $request->get_param( 'message' ) );

		$is_bearer_mode = 'claude' === $settings['ai_provider'] && 'bearer_token' === $settings['claude_auth_mode'];
		$credential     = $is_bearer_mode ? $settings['claude_bearer_token'] : $settings['api_key'];

		if ( '' === trim( $credential ) || '' === trim( $settings['model'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $is_bearer_mode
						? __( 'Enter a bearer token and model ID before running the admin chat test.', 'ai-site-search-chatbot' )
						: __( 'Enter an API key and model ID before running the admin chat test.', 'ai-site-search-chatbot' ),
				),
				400
			);
		}

		if ( '' === $message ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Enter a test question before running the admin chat test.', 'ai-site-search-chatbot' ),
				),
				400
			);
		}

		$route = self::analyze_message_route( $message, $settings );

		if ( 'reject' === $route['intent'] ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $route['message'],
				),
				400
			);
		}

		$results = self::search_site_content( $message, $settings, $route );
		$answer = self::generate_answer( $message, $results, $route );

		if ( ! empty( $answer['used_ai'] ) || 0 === strpos( $answer['log_status'], 'fallback-' ) ) {
			return new WP_REST_Response(
				array(
					'success'  => true,
					'answer'   => $answer['answer'],
					'used_ai'  => ! empty( $answer['used_ai'] ),
					'sources'  => $answer['sources'],
					'searches' => $results,
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success'  => false,
				'message'  => __( 'The admin chat test failed.', 'ai-site-search-chatbot' ),
				'searches' => $results,
			),
			400
		);
	}

	public static function handle_chat_request( WP_REST_Request $request ) {
		$message = self::sanitize_message( (string) $request->get_param( 'message' ) );

		if ( self::is_rate_limited() ) {
			$answer = __( 'Too many requests. Please wait a moment and try again.', 'ai-site-search-chatbot' );
			self::append_chat_log(
				array(
					'question'   => $message,
					'answer'     => $answer,
					'status'     => 'request-blocked',
					'used_ai'    => false,
					'source_count' => 0,
				)
			);

			return new WP_REST_Response(
				array(
					'answer' => $answer,
				),
				429
			);
		}

		if ( '' === $message ) {
			$answer = __( 'Please enter a question.', 'ai-site-search-chatbot' );
			return new WP_REST_Response(
				array(
					'answer' => $answer,
				),
				400
			);
		}

		$settings = self::get_settings();
		$route = self::analyze_message_route( $message, $settings );

		if ( 'reject' === $route['intent'] ) {
			self::append_chat_log(
				array(
					'question'     => $message,
					'answer'       => $route['message'],
					'status'       => 'rejected-pre-ai',
					'used_ai'      => false,
					'source_count' => 0,
				)
			);

			return new WP_REST_Response(
				array(
					'query'     => $message,
					'answer'    => $route['message'],
					'used_ai'   => false,
					'sources'   => array(),
					'searches'  => array(),
				),
				400
			);
		}

		$results = self::search_site_content( $message, $settings, $route );
		$answer = self::generate_answer( $message, $results, $route );
		self::append_chat_log(
			array(
				'question'     => $message,
				'answer'       => $answer['answer'],
				'status'       => $answer['log_status'],
				'used_ai'      => ! empty( $answer['used_ai'] ),
				'source_count' => count( $answer['sources'] ),
			)
		);

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


	private static function sanitize_message( string $message ): string {
		$message = trim( wp_strip_all_tags( $message ) );

		if ( strlen( $message ) > 500 ) {
			$message = substr( $message, 0, 500 );
		}

		return $message;
	}

	private static function is_rate_limited(): bool {
		$ip = self::get_client_ip_address();
		$key = 'aiscb_rate_' . md5( $ip );
		$now = time();
		$state = get_transient( $key );

		if ( ! is_array( $state ) ) {
			$state = array(
				'timestamps' => array(),
				'blocked_until' => 0,
			);
		}

		$state = wp_parse_args(
			$state,
			array(
				'timestamps' => array(),
				'blocked_until' => 0,
			)
		);

		if ( $state['blocked_until'] > $now ) {
			set_transient( $key, $state, max( 1, $state['blocked_until'] - $now ) );

			return true;
		}

		$timestamps = array_values(
			array_filter(
				array_map( 'absint', (array) $state['timestamps'] ),
				static function ( int $timestamp ) use ( $now ): bool {
					return $timestamp >= ( $now - MINUTE_IN_SECONDS );
				}
			)
		);

		$recent_burst_count = 0;

		foreach ( $timestamps as $timestamp ) {
			if ( $timestamp >= ( $now - 10 ) ) {
				++$recent_burst_count;
			}
		}

		if ( $recent_burst_count >= 3 || count( $timestamps ) >= 20 ) {
			$state['timestamps'] = $timestamps;
			$state['blocked_until'] = $now + 60;
			set_transient( $key, $state, 60 );

			return true;
		}

		$timestamps[] = $now;
		$state['timestamps'] = $timestamps;
		$state['blocked_until'] = 0;
		set_transient( $key, $state, MINUTE_IN_SECONDS );

		return false;
	}

	private static function get_client_ip_address(): string {
		$candidates = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		);

		foreach ( $candidates as $server_key ) {
			if ( empty( $_SERVER[ $server_key ] ) ) {
				continue;
			}

			$raw_value = sanitize_text_field( wp_unslash( (string) $_SERVER[ $server_key ] ) );
			$parts = array_map( 'trim', explode( ',', $raw_value ) );

			foreach ( $parts as $part ) {
				if ( '' === $part ) {
					continue;
				}

				if ( false !== filter_var( $part, FILTER_VALIDATE_IP ) ) {
					return $part;
				}
			}
		}

		return 'unknown';
	}

	private static function is_ai_usage_limited(): bool {
		$limits = self::get_ai_usage_limits();
		$state = self::get_ai_usage_state();
		$now = time();
		$recent_ten_minutes = 0;
		$recent_hour = count( $state['timestamps'] );

		foreach ( $state['timestamps'] as $timestamp ) {
			if ( $timestamp >= ( $now - ( 10 * MINUTE_IN_SECONDS ) ) ) {
				++$recent_ten_minutes;
			}
		}

		return $recent_ten_minutes >= $limits['ten_minutes'] || $recent_hour >= $limits['one_hour'];
	}

	private static function get_ai_usage_limits(): array {
		$settings = self::get_settings();

		return array(
			'ten_minutes' => max( 1, min( 30, absint( $settings['ai_limit_window_10m'] ?? 8 ) ) ),
			'one_hour'    => max( 1, min( 100, absint( $settings['ai_limit_window_1h'] ?? 24 ) ) ),
		);
	}

	private static function register_ai_usage(): void {
		$state = self::get_ai_usage_state();
		$state['timestamps'][] = time();
		self::store_ai_usage_state( $state );
	}

	private static function get_ai_usage_state(): array {
		$key = self::get_ai_usage_key();
		$now = time();
		$state = get_transient( $key );

		if ( ! is_array( $state ) ) {
			$state = array(
				'timestamps' => array(),
			);
		}

		$state = wp_parse_args(
			$state,
			array(
				'timestamps' => array(),
			)
		);

		$state['timestamps'] = array_values(
			array_filter(
				array_map( 'absint', (array) $state['timestamps'] ),
				static function ( int $timestamp ) use ( $now ): bool {
					return $timestamp >= ( $now - HOUR_IN_SECONDS );
				}
			)
		);

		set_transient( $key, $state, HOUR_IN_SECONDS );

		return $state;
	}

	private static function store_ai_usage_state( array $state ): void {
		set_transient( self::get_ai_usage_key(), $state, HOUR_IN_SECONDS );
	}

	private static function get_ai_usage_key(): string {
		return 'aiscb_ai_usage_' . md5( self::get_client_ip_address() );
	}

	private static function get_cached_ai_answer( array $settings, string $message, array $results ): string {
		$cached = get_transient( self::get_ai_answer_cache_key( $settings, $message, $results ) );

		if ( ! is_string( $cached ) ) {
			return '';
		}

		return trim( $cached );
	}

	private static function store_cached_ai_answer( array $settings, string $message, array $results, string $answer ): void {
		$answer = trim( $answer );

		if ( '' === $answer ) {
			return;
		}

		set_transient( self::get_ai_answer_cache_key( $settings, $message, $results ), $answer, 6 * HOUR_IN_SECONDS );
	}

	private static function get_ai_answer_cache_key( array $settings, string $message, array $results ): string {
		$result_fingerprint = array();

		foreach ( array_slice( $results, 0, 5 ) as $result ) {
			$result_fingerprint[] = array(
				'id' => isset( $result['id'] ) ? (int) $result['id'] : 0,
				'title' => isset( $result['title'] ) ? sanitize_text_field( (string) $result['title'] ) : '',
				'excerpt' => isset( $result['excerpt'] ) ? sanitize_text_field( (string) $result['excerpt'] ) : '',
			);
		}

		$payload = wp_json_encode(
			array(
				'provider' => isset( $settings['ai_provider'] ) ? (string) $settings['ai_provider'] : '',
				'model' => isset( $settings['model'] ) ? (string) $settings['model'] : '',
				'message' => self::normalize_message_for_cache( $message ),
				'results' => $result_fingerprint,
			)
		);

		return 'aiscb_ai_answer_' . md5( (string) $payload );
	}

	private static function normalize_message_for_cache( string $message ): string {
		$message = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $message ) ) );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $message, 'UTF-8' ) : strtolower( $message );
	}

	private static function search_site_content( string $message, array $settings = array(), array $route = array() ): array {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );
		$post_types = array_values( $post_types );
		$queries = ! empty( $route['queries'] ) && is_array( $route['queries'] ) ? $route['queries'] : self::build_search_queries( $message, $settings );
		$results = self::search_site_content_by_queries( $queries, $post_types );

		if ( ! empty( $results ) ) {
			return $results;
		}

		return self::search_site_content_by_like_matching( $queries, $post_types );
	}

	private static function search_site_content_by_queries( array $queries, array $post_types ): array {
		$results = array();
		$seen_ids = array();

		foreach ( $queries as $query_string ) {
			if ( count( $results ) >= 10 ) {
				break;
			}

			$query = new WP_Query(
				array(
					's'                   => $query_string,
					'post_type'           => $post_types,
					'post_status'         => 'publish',
					'posts_per_page'      => 10,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
				)
			);

			foreach ( $query->posts as $post ) {
				if ( ! $post instanceof WP_Post ) {
					continue;
				}

				$post_id = (int) $post->ID;

				if ( isset( $seen_ids[ $post_id ] ) ) {
					continue;
				}

				$seen_ids[ $post_id ] = true;
				$results[] = self::build_search_result_item( $post );

				if ( count( $results ) >= 10 ) {
					break;
				}
			}
		}

		wp_reset_postdata();

		return $results;
	}

	private static function build_search_queries( string $message, array $settings = array() ): array {
		$queries = array_merge(
			self::extract_search_queries_with_ai( $message, $settings ),
			self::build_rule_based_search_queries( $message )
		);

		return self::normalize_search_queries( $queries );
	}

	private static function analyze_message_route( string $message, array $settings ): array {
		if ( self::is_obvious_spam_message( $message ) ) {
			return array(
				'intent'  => 'reject',
				'queries' => array(),
				'message' => __( 'Your message looks automated or repetitive. Please rewrite it as a short, natural question about this site.', 'ai-site-search-chatbot' ),
			);
		}

		$rule_based_queries = self::build_rule_based_search_queries( $message );

		if ( empty( $settings['api_key'] ) || empty( $settings['model'] ) ) {
			return array(
				'intent'  => 'site-search',
				'queries' => self::normalize_search_queries( $rule_based_queries ),
			);
		}

		$response = self::request_ai_message_route( $settings, $message );

		if ( empty( $response['success'] ) || empty( $response['content'] ) ) {
			return array(
				'intent'  => 'site-search',
				'queries' => self::normalize_search_queries( $rule_based_queries ),
			);
		}

		$route = self::parse_ai_message_route( (string) $response['content'], $message );

		if ( 'site-search' === $route['intent'] ) {
			$route['queries'] = self::normalize_search_queries( array_merge( $route['queries'], $rule_based_queries ) );
		}

		return $route;
	}

	private static function is_obvious_spam_message( string $message ): bool {
		if ( preg_match( '/https?:\/\//i', $message ) ) {
			return true;
		}

		if ( preg_match( '/(.)\1{11,}/u', $message ) ) {
			return true;
		}

		preg_match_all( '/[\p{L}\p{N}_-]+/u', $message, $token_matches );
		$tokens = array_values( array_filter( array_map( 'strtolower', $token_matches[0] ) ) );

		if ( count( $tokens ) >= 6 ) {
			$frequencies = array_count_values( $tokens );
			rsort( $frequencies );

			if ( isset( $frequencies[0] ) && $frequencies[0] >= 4 ) {
				return true;
			}
		}

		$symbol_heavy = preg_replace( '/[\p{L}\p{N}\s]/u', '', $message );

		if ( is_string( $symbol_heavy ) && '' !== $message ) {
			$symbol_ratio = self::unicode_length( $symbol_heavy ) / max( 1, self::unicode_length( $message ) );

			if ( $symbol_ratio > 0.45 ) {
				return true;
			}
		}

		return false;
	}

	private static function build_rule_based_search_queries( string $message ): array {
		$queries = array();
		$normalized = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $message ) ) );
		$normalized = trim( preg_replace( '/[?？!！。．、,，]+/u', ' ', $normalized ) );

		if ( '' === $normalized ) {
			return $queries;
		}

		$queries[] = $normalized;

		$trimmed_question = preg_replace(
			'/\s*(を教えてください|を教えて|について教えてください|について教えて|について知りたい|はありますか|ありますか|ですか|でしょうか|とは何ですか|とは|はどこですか|はどこ|はあります|って何ですか|って何)\s*$/u',
			'',
			$normalized
		);

		if ( is_string( $trimmed_question ) ) {
			$trimmed_question = trim( $trimmed_question, " \t\n\r\0\x0B?？!！。.,、" );

			if ( '' !== $trimmed_question ) {
				$queries[] = $trimmed_question;

				$without_generic_suffix = preg_replace( '/(ページ|記事|フォーム|内容|情報|方法|場所)$/u', '', $trimmed_question );
				if ( is_string( $without_generic_suffix ) ) {
					$without_generic_suffix = trim( $without_generic_suffix );

					if ( '' !== $without_generic_suffix && $without_generic_suffix !== $trimmed_question ) {
						$queries[] = $without_generic_suffix;
					}
				}
			}
		}

		preg_match_all( '/[\p{Han}\p{Hiragana}\p{Katakana}A-Za-z0-9_-]+/u', $normalized, $matches );

		foreach ( $matches[0] as $token ) {
			$token = trim( (string) $token );

			if ( self::unicode_length( $token ) < 2 ) {
				continue;
			}

			$queries[] = $token;

			$without_suffix = preg_replace( '/(ページ|記事|フォーム|内容|情報|方法|場所|ありますか|ですか|でしょうか)$/u', '', $token );
			if ( is_string( $without_suffix ) ) {
				$without_suffix = trim( $without_suffix );

				if ( '' !== $without_suffix && $without_suffix !== $token ) {
					$queries[] = $without_suffix;
				}
			}

			if ( 0 === strpos( $token, 'お問い合わせ' ) ) {
				$queries[] = str_replace( 'お問い合わせ', '問い合わせ', $token );
			}
		}

		return $queries;
	}

	private static function extract_search_queries_with_ai( string $message, array $settings ): array {
		if ( empty( $settings['api_key'] ) || empty( $settings['model'] ) ) {
			return array();
		}

		$response = self::request_ai_message_route( $settings, $message );

		if ( empty( $response['success'] ) || empty( $response['content'] ) ) {
			return array();
		}

		$route = self::parse_ai_message_route( (string) $response['content'], $message );

		if ( 'site-search' !== $route['intent'] ) {
			return array();
		}

		return self::normalize_search_queries( $route['queries'] );
	}

	private static function request_ai_message_route( array $settings, string $message ): array {
		$system_prompt = self::get_search_query_system_prompt();
		$user_prompt = self::build_search_query_prompt( $message );
		$provider = $settings['ai_provider'] ?? 'openai';

		switch ( $provider ) {
			case 'claude':
				return self::call_claude_api(
					$settings,
					$message,
					array(),
					array(
						'system_prompt' => $system_prompt,
						'user_prompt'   => $user_prompt,
					)
				);
			case 'github-copilot':
				return self::call_github_copilot_api(
					$settings,
					$message,
					array(),
					array(
						'system_prompt' => $system_prompt,
						'user_prompt'   => $user_prompt,
					)
				);
			case 'gemini':
				return self::call_gemini_api(
					$settings,
					$message,
					array(),
					array(
						'system_prompt' => $system_prompt,
						'user_prompt'   => $user_prompt,
					)
				);
			case 'openai':
			default:
				return self::call_openai_api(
					$settings,
					$message,
					array(),
					array(
						'system_prompt' => $system_prompt,
						'user_prompt'   => $user_prompt,
					)
				);
		}
	}

	private static function parse_ai_message_route( string $content, string $message ): array {
		$route = array(
			'intent'  => 'site-search',
			'queries' => array(),
		);

		$trimmed = trim( $content );

		if ( preg_match( '/\{[\s\S]*\}/', $trimmed, $matches ) ) {
			$decoded = json_decode( $matches[0], true );

			if ( is_array( $decoded ) ) {
				$intent = isset( $decoded['intent'] ) ? sanitize_key( (string) $decoded['intent'] ) : 'site-search';
				$queries = array();

				if ( ! empty( $decoded['queries'] ) && is_array( $decoded['queries'] ) ) {
					foreach ( $decoded['queries'] as $query ) {
						if ( is_string( $query ) ) {
							$queries[] = $query;
						}
					}
				}

				if ( 'site_guidance' === $intent ) {
					$intent = 'site-guidance';
				} elseif ( 'site_search' === $intent ) {
					$intent = 'site-search';
				}

				if ( in_array( $intent, array( 'site-search', 'site-guidance', 'reject' ), true ) ) {
					$route['intent'] = $intent;
				}

				$route['queries'] = self::filter_queries_by_message_language( $queries, $message );
			}
		}

		if ( empty( $route['queries'] ) && 'site-search' === $route['intent'] ) {
			$route['queries'] = self::parse_ai_search_queries( $content, $message );
		}

		if ( 'reject' === $route['intent'] ) {
			$route['message'] = __( 'Your message looks automated or unrelated. Please ask a short question about this site.', 'ai-site-search-chatbot' );
		}

		return $route;
	}

	private static function get_search_query_system_prompt(): string {
		return __( 'You classify a visitor question for a WordPress site assistant. Return only a JSON object with two keys: intent and queries. intent must be one of site_search, site_guidance, or reject. Use site_search when the visitor is looking for a specific page, service, policy, product, location, form, or site term. Use site_guidance for lightweight site-related questions that can be answered without external facts, such as who you are, what kind of information exists on this site, how to use the site, or what the visitor can ask here. Use reject for obvious spam, gibberish, repeated text, or automated promotional content. queries must be an array of 0 to 8 short site-search phrases in the same language as the visitor question. Prefer words likely to appear in page titles, menu labels, headings, form labels, and short content snippets. Return JSON only.', 'ai-site-search-chatbot' );
	}

	private static function build_search_query_prompt( string $message ): string {
		return sprintf(
			/* translators: %s: visitor question */
			__( "Visitor question:\n%s\n\nReturn JSON only. Example output: {\"intent\":\"site_search\",\"queries\":[\"contact\",\"contact page\",\"inquiry\"]}", 'ai-site-search-chatbot' ),
			$message
		);
	}

	private static function parse_ai_search_queries( string $content, string $message ): array {
		$queries = array();
		$trimmed = trim( $content );

		if ( preg_match( '/\[[\s\S]*\]/', $trimmed, $matches ) ) {
			$decoded = json_decode( $matches[0], true );

			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $item ) {
					if ( is_string( $item ) ) {
						$queries[] = $item;
					}
				}
			}
		}

		if ( empty( $queries ) ) {
			$parts = preg_split( '/[\r\n,、]+/u', $trimmed );

			if ( is_array( $parts ) ) {
				foreach ( $parts as $part ) {
					$part = trim( (string) $part, " \t\n\r\0\x0B\"'-*•[]" );

					if ( '' !== $part ) {
						$queries[] = $part;
					}
				}
			}
		}

		$queries = self::filter_queries_by_message_language( $queries, $message );

		return self::normalize_search_queries( $queries );
	}

	private static function filter_queries_by_message_language( array $queries, string $message ): array {
		if ( ! preg_match( '/[\p{Han}\p{Hiragana}\p{Katakana}]/u', $message ) ) {
			return $queries;
		}

		$filtered_queries = array();

		foreach ( $queries as $query ) {
			if ( ! is_string( $query ) ) {
				continue;
			}

			if ( preg_match( '/[\p{Han}\p{Hiragana}\p{Katakana}]/u', $query ) ) {
				$filtered_queries[] = $query;
				continue;
			}

			if ( ! preg_match( '/[A-Za-z]/', $query ) ) {
				$filtered_queries[] = $query;
			}
		}

		return empty( $filtered_queries ) ? $queries : $filtered_queries;
	}

	private static function normalize_search_queries( array $queries ): array {
		$normalized_queries = array();

		foreach ( $queries as $query ) {
			if ( ! is_string( $query ) ) {
				continue;
			}

			$query = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $query ) ) );
			$query = trim( preg_replace( '/[?？!！。．、,，]+/u', ' ', $query ) );

			if ( '' === $query ) {
				continue;
			}

			$normalized_queries[] = $query;
		}

		$normalized_queries = array_values( array_unique( $normalized_queries ) );

		usort(
			$normalized_queries,
			static function ( string $left, string $right ): int {
				return self::unicode_length( $right ) <=> self::unicode_length( $left );
			}
		);

		return $normalized_queries;
	}

	private static function search_site_content_by_like_matching( array $queries, array $post_types ): array {
		global $wpdb;

		if ( empty( $queries ) || empty( $post_types ) ) {
			return array();
		}

		$type_placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
		$score_parts = array();
		$where_parts = array();
		$params = $post_types;

		foreach ( $queries as $query_string ) {
			$like = '%' . $wpdb->esc_like( $query_string ) . '%';

			$score_parts[] = '(CASE WHEN post_title LIKE %s THEN 12 ELSE 0 END + CASE WHEN post_excerpt LIKE %s THEN 6 ELSE 0 END + CASE WHEN post_content LIKE %s THEN 3 ELSE 0 END)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;

			$where_parts[] = '(post_title LIKE %s OR post_excerpt LIKE %s OR post_content LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT ID, (" . implode( ' + ', $score_parts ) . ") AS relevance_score FROM {$wpdb->posts} WHERE post_type IN ({$type_placeholders}) AND post_status = 'publish' AND (" . implode( ' OR ', $where_parts ) . ") ORDER BY relevance_score DESC, post_modified_gmt DESC LIMIT 10";
		$prepared_sql = $wpdb->prepare( $sql, $params );
		$post_rows = $wpdb->get_results( $prepared_sql );

		if ( empty( $post_rows ) ) {
			return array();
		}

		$results = array();

		foreach ( $post_rows as $post_row ) {
			$post = get_post( (int) $post_row->ID );

			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$results[] = self::build_search_result_item( $post );
		}

		return $results;
	}

	private static function unicode_length( string $value ): int {
		if ( '' === $value ) {
			return 0;
		}

		return preg_match_all( '/./u', $value );
	}

	private static function build_search_result_item( WP_Post $post ): array {
		$excerpt = get_the_excerpt( $post );

		if ( '' === trim( $excerpt ) ) {
			$excerpt = wp_strip_all_tags( wp_trim_words( $post->post_content, 36 ) );
		}

		return array(
			'id'      => (int) $post->ID,
			'title'   => get_the_title( $post ),
			'url'     => get_permalink( $post ),
			'excerpt' => wp_strip_all_tags( $excerpt ),
		);
	}

	private static function generate_answer( string $message, array $results, array $route = array() ): array {
		$settings = self::get_settings();
		$sources = self::build_sources( $results, (int) $settings['max_sources'] );
		$intent = isset( $route['intent'] ) ? (string) $route['intent'] : 'site-search';

		if ( empty( $settings['api_key'] ) ) {
			return array(
				'answer'   => self::build_fallback_answer( $message, $results ),
				'used_ai'  => false,
				'sources'  => $sources,
				'log_status' => 'fallback-no-config',
			);
		}

		if ( 'site-guidance' === $intent ) {
			if ( self::is_ai_usage_limited() ) {
				return array(
					'answer'      => self::build_ai_limited_fallback_answer( $message, $results ),
					'used_ai'     => false,
					'sources'     => $sources,
					'log_status'  => 'ai-limited-site-guidance',
				);
			}

			$response_data = self::request_ai_answer(
				$settings,
				$message,
				$results,
				array(
					'system_prompt' => self::get_site_guidance_system_prompt(),
					'user_prompt'   => self::build_site_guidance_prompt( $message, $results ),
				)
			);

			if ( is_wp_error( $response_data ) || ! $response_data['success'] ) {
				return array(
					'answer'      => self::build_fallback_answer( $message, $results ),
					'used_ai'     => false,
					'sources'     => $sources,
					'log_status'  => 'fallback-site-guidance-provider-error',
				);
			}

			self::register_ai_usage();

			return array(
				'answer'      => $response_data['content'],
				'used_ai'     => true,
				'sources'     => $sources,
				'log_status'  => 'ai-site-guidance',
			);
		}

		if ( empty( $results ) ) {
			return array(
				'answer'   => self::build_fallback_answer( $message, $results ),
				'used_ai'  => false,
				'sources'  => $sources,
				'log_status' => 'fallback-no-results',
			);
		}

		$cached_answer = self::get_cached_ai_answer( $settings, $message, $results );

		if ( '' !== $cached_answer ) {
			return array(
				'answer'  => $cached_answer,
				'used_ai' => true,
				'sources' => $sources,
				'log_status' => 'ai-cached',
			);
		}

		if ( self::is_ai_usage_limited() ) {
			return array(
				'answer'  => self::build_ai_limited_fallback_answer( $message, $results ),
				'used_ai' => false,
				'sources' => $sources,
				'log_status' => 'ai-limited',
			);
		}

		$response_data = self::request_ai_answer( $settings, $message, $results );

		if ( is_wp_error( $response_data ) || ! $response_data['success'] ) {
			return array(
				'answer'  => self::build_fallback_answer( $message, $results ),
				'used_ai' => false,
				'sources' => $sources,
				'log_status' => 'fallback-provider-error',
			);
		}

		self::store_cached_ai_answer( $settings, $message, $results, (string) $response_data['content'] );
		self::register_ai_usage();

		return array(
			'answer'  => $response_data['content'],
			'used_ai' => true,
			'sources' => $sources,
			'log_status' => 'ai-generated',
		);
	}

	public static function get_chat_logs(): array {
		$logs = get_option( self::CHAT_LOG_OPTION, array() );

		if ( ! is_array( $logs ) ) {
			return array();
		}

		return array_values( $logs );
	}

	private static function append_chat_log( array $entry ): void {
		$question = isset( $entry['question'] ) ? self::trim_chat_log_text( (string) $entry['question'], 700 ) : '';

		if ( '' === $question ) {
			return;
		}

		$logs = self::get_chat_logs();
		array_unshift(
			$logs,
			array(
				'time'         => time(),
				'question'     => $question,
				'answer'       => isset( $entry['answer'] ) ? self::trim_chat_log_text( (string) $entry['answer'], 4000 ) : '',
				'status'       => isset( $entry['status'] ) ? sanitize_key( (string) $entry['status'] ) : 'unknown',
				'used_ai'      => ! empty( $entry['used_ai'] ),
				'source_count' => isset( $entry['source_count'] ) ? absint( $entry['source_count'] ) : 0,
				'ip'           => self::get_client_ip_address(),
			)
		);

		if ( count( $logs ) > self::CHAT_LOG_LIMIT ) {
			$logs = array_slice( $logs, 0, self::CHAT_LOG_LIMIT );
		}

		update_option( self::CHAT_LOG_OPTION, $logs, false );
	}

	private static function trim_chat_log_text( string $text, int $limit ): string {
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );

		if ( '' === $text ) {
			return '';
		}

		if ( strlen( $text ) > $limit ) {
			$text = substr( $text, 0, $limit ) . '...';
		}

		return $text;
	}

	private static function request_ai_answer( array $settings, string $message, array $results, array $options = array() ): array {
		$provider = $settings['ai_provider'] ?? 'openai';

		switch ( $provider ) {
			case 'claude':
				return self::call_claude_api( $settings, $message, $results, $options );
			case 'github-copilot':
				return self::call_github_copilot_api( $settings, $message, $results, $options );
			case 'gemini':
				return self::call_gemini_api( $settings, $message, $results, $options );
			case 'openai':
			default:
				return self::call_openai_api( $settings, $message, $results, $options );
		}
	}

	private static function get_site_guidance_system_prompt(): string {
		return __( 'You are a site assistant for this WordPress website. Answer only from the provided site context and optional site search results. You may handle light site-related conversation such as who you are, what kind of information exists on this site, how the visitor can use the site, and what they can ask here. Do not use external facts, world knowledge, or invented details. If the site context is insufficient, say that briefly and suggest a relevant page or keyword to explore.', 'ai-site-search-chatbot' );
	}

	private static function build_site_guidance_prompt( string $message, array $results ): string {
		$lines = array(
			'Site context:',
			self::build_site_context_summary(),
			'',
		);

		if ( ! empty( $results ) ) {
			$lines[] = 'Optional related site search results:';

			foreach ( array_slice( $results, 0, 5 ) as $result ) {
				$lines[] = sprintf( '- %s', $result['title'] );
				$lines[] = sprintf( '  Excerpt: %s', $result['excerpt'] );
			}

			$lines[] = '';
		}

		$lines[] = 'Visitor question:';
		$lines[] = $message;
		$lines[] = '';
		$lines[] = 'Instructions: answer naturally in the visitor language. Keep the answer concise. Stay within the site context and optional related results above. Do not output raw URLs, domains, permalink strings, or markdown links.';

		return implode( "\n", $lines );
	}

	private static function build_site_context_summary(): string {
		$cache_key = 'aiscb_site_context_summary';
		$cached = get_transient( $cache_key );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$lines = array();
		$site_name = wp_strip_all_tags( get_bloginfo( 'name' ) );
		$site_description = wp_strip_all_tags( get_bloginfo( 'description' ) );

		if ( '' !== $site_name ) {
			$lines[] = 'Site name: ' . $site_name;
		}

		if ( '' !== $site_description ) {
			$lines[] = 'Site description: ' . $site_description;
		}

		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );

		$query = new WP_Query(
			array(
				'post_type'           => array_values( $post_types ),
				'post_status'         => 'publish',
				'posts_per_page'      => 10,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'orderby'             => array(
					'menu_order' => 'ASC',
					'date'       => 'DESC',
				),
			)
		);

		if ( ! empty( $query->posts ) ) {
			$lines[] = 'Public content examples:';

			foreach ( $query->posts as $post ) {
				if ( ! $post instanceof WP_Post ) {
					continue;
				}

				$title = wp_strip_all_tags( get_the_title( $post ) );
				$excerpt = trim( wp_strip_all_tags( get_the_excerpt( $post ) ) );
				$line = '- ' . $title;

				if ( '' !== $excerpt ) {
					$line .= ': ' . self::trim_text_for_prompt( $excerpt, 120 );
				}

				$lines[] = $line;
			}
		}

		wp_reset_postdata();

		$summary = implode( "\n", $lines );
		set_transient( $cache_key, $summary, 10 * MINUTE_IN_SECONDS );

		return $summary;
	}

	private static function trim_text_for_prompt( string $text, int $limit ): string {
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );

		if ( '' === $text ) {
			return '';
		}

		if ( strlen( $text ) > $limit ) {
			$text = substr( $text, 0, $limit ) . '...';
		}

		return $text;
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
					$result = self::call_github_copilot_api(
						$settings,
						$probe_message,
						$probe_results,
						array(
							'system_prompt' => '',
							'user_prompt'   => $probe_message,
						)
					);
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

	private static function call_openai_api( array $settings, string $message, array $results, array $options = array() ): array {
		$prompt = isset( $options['user_prompt'] ) ? (string) $options['user_prompt'] : self::build_ai_prompt( $message, $results, (int) $settings['max_sources'] );
		$system_prompt = array_key_exists( 'system_prompt', $options ) ? (string) $options['system_prompt'] : (string) $settings['system_prompt'];
		$payload = array(
			'model'       => $settings['model'],
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
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

	private static function call_claude_api( array $settings, string $message, array $results, array $options = array() ): array {
		$prompt        = isset( $options['user_prompt'] ) ? (string) $options['user_prompt'] : self::build_ai_prompt( $message, $results, (int) $settings['max_sources'] );
		$system_prompt = array_key_exists( 'system_prompt', $options ) ? (string) $options['system_prompt'] : (string) $settings['system_prompt'];

		if ( ! class_exists( '\Anthropic\Client' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Anthropic PHP SDK is not installed. Run composer install in the plugin directory.', 'ai-site-search-chatbot' ),
			);
		}

		try {
			if ( 'bearer_token' === ( $settings['claude_auth_mode'] ?? 'api_key' ) && ! empty( $settings['claude_bearer_token'] ) ) {
				$client = new \Anthropic\Client( authToken: $settings['claude_bearer_token'] );
			} else {
				$client = new \Anthropic\Client( apiKey: $settings['api_key'] );
			}

			$response = $client->messages->create(
				model: $settings['model'],
				maxTokens: 4096,
				system: array(
					array(
						'type'         => 'text',
						'text'         => $system_prompt,
						'cacheControl' => array( 'type' => 'ephemeral' ),
					),
				),
				messages: array(
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
			);
		} catch ( \Anthropic\Core\Exceptions\APIStatusException $e ) {
			return array(
				'success' => false,
				/* translators: %s: API error message */
				'message' => sprintf( __( 'Claude API error (%s): %s', 'ai-site-search-chatbot' ), $e->getCode(), $e->getMessage() ),
			);
		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				/* translators: %s: error message */
				'message' => sprintf( __( 'Claude request failed: %s', 'ai-site-search-chatbot' ), $e->getMessage() ),
			);
		}

		$content = '';
		foreach ( $response->content as $block ) {
			if ( 'text' === $block->type ) {
				$content = trim( (string) $block->text );
				break;
			}
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

	private static function call_github_copilot_api( array $settings, string $message, array $results, array $options = array() ): array {
		$prompt = isset( $options['user_prompt'] ) ? (string) $options['user_prompt'] : self::build_ai_prompt( $message, $results, (int) $settings['max_sources'] );
		$system_prompt = array_key_exists( 'system_prompt', $options ) ? (string) $options['system_prompt'] : (string) $settings['system_prompt'];
		$messages = array(
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		);

		if ( '' !== trim( $system_prompt ) ) {
			array_unshift(
				$messages,
				array(
					'role'    => 'system',
					'content' => $system_prompt,
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

	private static function call_gemini_api( array $settings, string $message, array $results, array $options = array() ): array {
		$prompt = isset( $options['user_prompt'] ) ? (string) $options['user_prompt'] : self::build_ai_prompt( $message, $results, (int) $settings['max_sources'] );
		$system_prompt = array_key_exists( 'system_prompt', $options ) ? (string) $options['system_prompt'] : (string) $settings['system_prompt'];
		$model = str_replace( '/', '%2F', $settings['model'] );

		$payload = array(
			'contents' => array(
				array(
					'parts' => array(
						array(
							'text' => $system_prompt . "\n\n" . $prompt,
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
			$lines[] = sprintf( '  Excerpt: %s', $result['excerpt'] );
		}

		$lines[] = '';
		$lines[] = 'Instructions: answer in a helpful, concise tone. Use only the site results above. Do not output raw URLs, domains, permalink strings, or markdown links in the answer. If you need to mention a source, refer to it only by its page title. If no result is relevant, say that the site does not currently contain a clear answer and suggest a related page or keyword.';

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

	private static function build_ai_limited_fallback_answer( string $message, array $results ): string {
		$answer = self::build_fallback_answer( $message, $results );
		$notice = __( 'To keep the service stable, detailed AI replies are temporarily paused for this connection. Please wait a bit and try again.', 'ai-site-search-chatbot' );

		return $answer . "\n" . $notice;
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
