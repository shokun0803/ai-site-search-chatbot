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
			'ai_provider'   => 'openai',
			'api_key'       => '',
			'model'         => '',
			'system_prompt' => self::get_default_system_prompt(),
			'max_sources'   => 5,
			'widget_theme'  => 'business',
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

		return array(
			'ai_provider'   => $provider,
			'api_key'       => isset( $input['api_key'] ) ? sanitize_text_field( wp_unslash( $input['api_key'] ) ) : $defaults['api_key'],
			'model'         => isset( $input['model'] ) ? sanitize_text_field( wp_unslash( $input['model'] ) ) : $defaults['model'],
			'system_prompt' => isset( $input['system_prompt'] ) ? sanitize_textarea_field( wp_unslash( $input['system_prompt'] ) ) : $defaults['system_prompt'],
			'max_sources'   => isset( $input['max_sources'] ) ? max( 1, min( 10, absint( $input['max_sources'] ) ) ) : $defaults['max_sources'],
			'widget_theme'  => $widget_theme,
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

	public static function handle_test_chat_request( WP_REST_Request $request ) {
		$settings = array(
			'ai_provider'   => sanitize_text_field( (string) $request->get_param( 'ai_provider' ) ),
			'api_key'       => sanitize_text_field( (string) $request->get_param( 'api_key' ) ),
			'model'         => sanitize_text_field( (string) $request->get_param( 'model' ) ),
			'system_prompt' => sanitize_textarea_field( (string) $request->get_param( 'system_prompt' ) ),
			'max_sources'   => max( 1, min( 10, absint( $request->get_param( 'max_sources' ) ) ) ),
		);

		$settings = wp_parse_args( $settings, self::default_settings() );
		$message = self::sanitize_message( (string) $request->get_param( 'message' ) );

		if ( '' === trim( $settings['api_key'] ) || '' === trim( $settings['model'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Enter an API key and model ID before running the admin chat test.', 'ai-site-search-chatbot' ),
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

		$results = self::search_site_content( $message, $settings );
		$response_data = self::request_ai_answer( $settings, $message, $results );

		if ( ! empty( $response_data['success'] ) ) {
			return new WP_REST_Response(
				array(
					'success'  => true,
					'answer'   => $response_data['content'],
					'used_ai'  => true,
					'sources'  => self::build_sources( $results, (int) $settings['max_sources'] ),
					'searches' => $results,
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success'  => false,
				'message'  => ! empty( $response_data['message'] ) ? $response_data['message'] : __( 'The admin chat test failed.', 'ai-site-search-chatbot' ),
				'searches' => $results,
			),
			400
		);
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

		$settings = self::get_settings();
		$results = self::search_site_content( $message, $settings );
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

	private static function search_site_content( string $message, array $settings = array() ): array {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );
		$post_types = array_values( $post_types );
		$queries = self::build_search_queries( $message, $settings );
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

		$response = self::request_ai_search_queries( $settings, $message );

		if ( empty( $response['success'] ) || empty( $response['content'] ) ) {
			return array();
		}

		return self::parse_ai_search_queries( (string) $response['content'], $message );
	}

	private static function request_ai_search_queries( array $settings, string $message ): array {
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

	private static function get_search_query_system_prompt(): string {
		return __( 'You convert a visitor question into WordPress site-search queries. Return only a JSON array of 3 to 8 short search phrases. Keep the phrases in the same language as the visitor question. Prefer words likely to appear in page titles, menu labels, headings, form labels, and short content snippets. Order the array from the most specific phrase to broader fallback phrases. Do not include explanations, markdown, or any text outside the JSON array.', 'ai-site-search-chatbot' );
	}

	private static function build_search_query_prompt( string $message ): string {
		return sprintf(
			/* translators: %s: visitor question */
			__( "Visitor question:\n%s\n\nReturn JSON only. Example output: [\"contact\", \"contact page\", \"inquiry\"]", 'ai-site-search-chatbot' ),
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

	private static function generate_answer( string $message, array $results ): array {
		$settings = self::get_settings();

		if ( empty( $settings['api_key'] ) ) {
			return array(
				'answer'   => self::build_fallback_answer( $message, $results ),
				'used_ai'  => false,
				'sources'  => self::build_sources( $results, (int) $settings['max_sources'] ),
			);
		}

		$response_data = self::request_ai_answer( $settings, $message, $results );

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

	private static function request_ai_answer( array $settings, string $message, array $results ): array {
		$provider = $settings['ai_provider'] ?? 'openai';

		switch ( $provider ) {
			case 'claude':
				return self::call_claude_api( $settings, $message, $results );
			case 'github-copilot':
				return self::call_github_copilot_api( $settings, $message, $results );
			case 'gemini':
				return self::call_gemini_api( $settings, $message, $results );
			case 'openai':
			default:
				return self::call_openai_api( $settings, $message, $results );
		}
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
		$prompt = isset( $options['user_prompt'] ) ? (string) $options['user_prompt'] : self::build_ai_prompt( $message, $results, (int) $settings['max_sources'] );
		$system_prompt = array_key_exists( 'system_prompt', $options ) ? (string) $options['system_prompt'] : (string) $settings['system_prompt'];
		$payload = array(
			'model'       => $settings['model'],
			'max_tokens'  => 1024,
			'system'      => $system_prompt,
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
