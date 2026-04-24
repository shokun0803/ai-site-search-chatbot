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
		load_plugin_textdomain( 'ai-site-search-chatbot', false, dirname( plugin_basename( AISCB_FILE ) ) . '/languages' );
	}

	public static function default_settings(): array {
		return array(
			'ai_provider'   => 'openai',
			'api_key'       => '',
			'model'         => 'gpt-4o-mini',
			'system_prompt' => 'You are a public website assistant. Answer only from the provided site search results. If the answer is not present, say so clearly and suggest related pages.',
			'max_sources'   => 5,
		);
	}

	public static function get_settings(): array {
		$settings = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, self::default_settings() );
	}

	public static function sanitize_settings( $input ): array {
		$defaults = self::default_settings();
		$input = is_array( $input ) ? $input : array();

		$provider = isset( $input['ai_provider'] ) ? sanitize_text_field( wp_unslash( $input['ai_provider'] ) ) : $defaults['ai_provider'];
		$valid_providers = array( 'openai', 'claude', 'github-copilot' );
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
		$providers = array(
			'openai'         => array(
				'label'        => 'OpenAI',
				'description'  => __( 'GPT-4, GPT-3.5 Turbo など', 'ai-site-search-chatbot' ),
				'models'       => array( 'gpt-4o', 'gpt-4o-mini', 'gpt-4', 'gpt-3.5-turbo' ),
				'default_model' => 'gpt-4o-mini',
			),
			'claude'         => array(
				'label'        => 'Claude (Anthropic)',
				'description'  => __( 'Claude 3 Opus, Sonnet, Haiku など', 'ai-site-search-chatbot' ),
				'models'       => array( 'claude-3-5-sonnet-20241022', 'claude-3-opus-20240229', 'claude-3-sonnet-20240229', 'claude-3-haiku-20240307' ),
				'default_model' => 'claude-3-5-sonnet-20241022',
			),
			'github-copilot' => array(
				'label'        => 'GitHub Copilot',
				'description'  => __( 'GitHub Copilot API を使用', 'ai-site-search-chatbot' ),
				'models'       => array( 'gpt-4', 'gpt-3.5-turbo' ),
				'default_model' => 'gpt-4',
			),
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'AI Site Search Chatbot', 'ai-site-search-chatbot' ); ?></h1>
			<p><?php echo esc_html__( 'Configure the AI provider and the prompt used when answering visitors with site search results.', 'ai-site-search-chatbot' ); ?></p>
			<form method="post" action="options.php" id="aiscb-settings-form">
				<?php settings_fields( self::OPTION_GROUP ); ?>
				
				<!-- AI Provider Selection -->
				<div class="aiscb-provider-selector" style="margin-bottom: 2rem; padding: 1.5rem; background: #f5f5f5; border-radius: 8px;">
					<h2 style="margin-top: 0; margin-bottom: 1rem; color: #333;"><?php echo esc_html__( 'AI Provider', 'ai-site-search-chatbot' ); ?></h2>
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

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aiscb_api_key"><?php echo esc_html__( 'API Key', 'ai-site-search-chatbot' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="aiscb_api_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>" autocomplete="off" />
							<p class="description" id="aiscb_api_key_help"></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiscb_model"><?php echo esc_html__( 'Model', 'ai-site-search-chatbot' ); ?></label></th>
						<td>
							<div id="aiscb_model_container">
								<select id="aiscb_model" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[model]" class="regular-text">
									<option><?php echo esc_html__( 'Loading models...', 'ai-site-search-chatbot' ); ?></option>
								</select>
								<p class="description"><?php echo esc_html__( 'The AI model to use for generating answers.', 'ai-site-search-chatbot' ); ?></p>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiscb_system_prompt"><?php echo esc_html__( 'System Prompt', 'ai-site-search-chatbot' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="7" id="aiscb_system_prompt" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[system_prompt]"><?php echo esc_textarea( $settings['system_prompt'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="aiscb_max_sources"><?php echo esc_html__( 'Maximum Sources', 'ai-site-search-chatbot' ); ?></label></th>
						<td>
							<input type="number" min="1" max="10" id="aiscb_max_sources" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_sources]" value="<?php echo esc_attr( (string) absint( $settings['max_sources'] ) ); ?>" />
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
			.aiscb-provider-option input[type="radio"]:checked + div,
			.aiscb-provider-option input:checked ~ div {
				color: #0073aa;
			}
			.aiscb-provider-option input[type="radio"]:checked ~ * {
				border-color: #0073aa;
				box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
			}
		</style>

		<script>
		( function() {
			const providers = <?php echo wp_json_encode( $providers ); ?>;
			const currentProvider = '<?php echo esc_js( $settings['ai_provider'] ); ?>';
			const currentModel = '<?php echo esc_js( $settings['model'] ); ?>';

			function updateModelOptions() {
				const selectedProvider = document.querySelector( 'input[name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ai_provider]"]:checked' ).value;
				const modelSelect = document.getElementById( 'aiscb_model' );
				const providerInfo = providers[ selectedProvider ];

				if ( providerInfo && Array.isArray( providerInfo.models ) ) {
					modelSelect.innerHTML = '';
					providerInfo.models.forEach( function( model ) {
						const option = document.createElement( 'option' );
						option.value = model;
						option.textContent = model;
						if ( model === currentModel || ( model === providerInfo.default_model && ! currentModel ) ) {
							option.selected = true;
						}
						modelSelect.appendChild( option );
					} );
				}

				// Update API key help text
				const helpText = {
					'openai': '<?php echo esc_js( __( 'Get your API key from https://platform.openai.com/api-keys', 'ai-site-search-chatbot' ) ); ?>',
					'claude': '<?php echo esc_js( __( 'Get your API key from https://console.anthropic.com/', 'ai-site-search-chatbot' ) ); ?>',
					'github-copilot': '<?php echo esc_js( __( 'Use your GitHub personal access token with copilot scope', 'ai-site-search-chatbot' ) ); ?>'
				};
				document.getElementById( 'aiscb_api_key_help' ).textContent = helpText[ selectedProvider ] || '';
			}

			// Update models on provider change
			document.querySelectorAll( 'input[name="<?php echo esc_attr( self::OPTION_KEY ); ?>[ai_provider]"]' ).forEach( function( radio ) {
				radio.addEventListener( 'change', updateModelOptions );
			} );

			// Initialize on page load
			updateModelOptions();
		} )();
		</script>
		<?php
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
			'temperature' => 0.2,
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
			return array( 'success' => false );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return array( 'success' => false );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$content = '';

		if ( isset( $body['choices'][0]['message']['content'] ) ) {
			$content = trim( (string) $body['choices'][0]['message']['content'] );
		}

		if ( '' === $content ) {
			return array( 'success' => false );
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
			return array( 'success' => false );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return array( 'success' => false );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$content = '';

		if ( isset( $body['content'][0]['text'] ) ) {
			$content = trim( (string) $body['content'][0]['text'] );
		}

		if ( '' === $content ) {
			return array( 'success' => false );
		}

		return array(
			'success' => true,
			'content' => $content,
		);
	}

	private static function call_github_copilot_api( array $settings, string $message, array $results ): array {
		$prompt = self::build_ai_prompt( $message, $results, (int) $settings['max_sources'] );
		$payload = array(
			'model'    => $settings['model'],
			'messages' => array(
				array(
					'role'    => 'system',
					'content' => $settings['system_prompt'],
				),
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'temperature' => 0.2,
		);

		$response = wp_remote_post(
			'https://api.githubcopilot.com/chat/completions',
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
			return array( 'success' => false );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return array( 'success' => false );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$content = '';

		if ( isset( $body['choices'][0]['message']['content'] ) ) {
			$content = trim( (string) $body['choices'][0]['message']['content'] );
		}

		if ( '' === $content ) {
			return array( 'success' => false );
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
