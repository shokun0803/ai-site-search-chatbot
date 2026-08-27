<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AISite_Search_Chatbot_Frontend {
	private static $assets_enqueued = false;
	private static $floating_widget_rendered = false;

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( AISite_Search_Chatbot::SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_floating_widget' ) );
	}

	public static function register_assets(): void {
		wp_register_style(
			'aiscb-frontend',
			plugins_url( 'assets/css/aiscb-frontend.css', AISCB_FILE ),
			array(),
			AISite_Search_Chatbot::VERSION
		);

		wp_register_script(
			'aiscb-frontend',
			plugins_url( 'assets/js/aiscb-frontend.js', AISCB_FILE ),
			array(),
			AISite_Search_Chatbot::VERSION,
			true
		);
	}

	public static function render_shortcode( $atts = array() ): string {
		if ( ! self::should_render_shortcode() ) {
			return '';
		}

		self::enqueue_assets();
		self::$floating_widget_rendered = true;

		$settings = AISite_Search_Chatbot::get_settings();

		$atts = shortcode_atts(
			array(
				'title'    => __( 'Ask about this site', 'ai-site-search-chatbot' ),
				'greeting' => __( 'Hi, ask me about this site and I will search the content for you.', 'ai-site-search-chatbot' ),
				'theme'    => $settings['widget_theme'],
			),
			$atts,
			AISite_Search_Chatbot::SHORTCODE
		);

		return self::get_widget_markup( $atts );
	}

	public static function render_floating_widget(): void {
		if ( is_admin() || self::$floating_widget_rendered || ! self::should_render_automatic_widget() ) {
			return;
		}

		self::enqueue_assets();
		self::$floating_widget_rendered = true;

		$markup = self::get_widget_markup(
			array(
				'title'    => __( 'Ask about this site', 'ai-site-search-chatbot' ),
				'greeting' => __( 'Hi, ask me about this site and I will search the content for you.', 'ai-site-search-chatbot' ),
				'theme'    => AISite_Search_Chatbot::get_settings()['widget_theme'],
			)
		);

		echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_widget_markup() escapes every dynamic value internally.
	}

	private static function enqueue_assets(): void {
		if ( self::$assets_enqueued ) {
			return;
		}

		self::$assets_enqueued = true;

		wp_enqueue_style( 'aiscb-frontend' );
		wp_enqueue_script( 'aiscb-frontend' );
	}

	private static function should_render_shortcode(): bool {
		$settings = AISite_Search_Chatbot::get_settings();

		if ( empty( $settings['widget_enabled'] ) ) {
			return false;
		}

		return 'shortcode' === (string) $settings['widget_display_mode'];
	}

	private static function should_render_automatic_widget(): bool {
		$settings = AISite_Search_Chatbot::get_settings();

		if ( empty( $settings['widget_enabled'] ) ) {
			return false;
		}

		$display_mode = isset( $settings['widget_display_mode'] ) ? (string) $settings['widget_display_mode'] : 'all-pages';

		if ( 'all-pages' === $display_mode ) {
			return true;
		}

		if ( 'front-page' === $display_mode ) {
			return is_front_page();
		}

		return false;
	}

	private static function get_widget_markup( array $args ): string {
		$themes = AISite_Search_Chatbot::get_widget_themes();
		$theme = isset( $args['theme'] ) ? sanitize_key( (string) $args['theme'] ) : 'business';

		if ( ! isset( $themes[ $theme ] ) ) {
			$theme = 'business';
		}

		$panel_id = wp_unique_id( 'aiscb-widget-panel-' );
		$input_id = wp_unique_id( 'aiscb-message-' );

		ob_start();
		?>
		<div
			class="aiscb-widget aiscb-widget--floating aiscb-widget--theme-<?php echo esc_attr( $theme ); ?>"
			data-endpoint="<?php echo esc_url( rest_url( AISite_Search_Chatbot::REST_NAMESPACE . '/chat' ) ); ?>"
			data-greeting="<?php echo esc_attr( (string) $args['greeting'] ); ?>"
			data-thinking-label="<?php echo esc_attr__( 'Thinking...', 'ai-site-search-chatbot' ); ?>"
			data-source-label="<?php echo esc_attr__( 'Sources', 'ai-site-search-chatbot' ); ?>"
			data-error-label="<?php echo esc_attr__( 'The chatbot is temporarily unavailable. Please try again later.', 'ai-site-search-chatbot' ); ?>"
		>
			<button type="button" class="aiscb-widget__launcher" aria-label="<?php echo esc_attr__( 'Open chat', 'ai-site-search-chatbot' ); ?>" aria-expanded="false" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
				<span class="aiscb-widget__launcher-badge" aria-hidden="true">
					<svg class="aiscb-widget__launcher-icon" viewBox="0 0 24 24" focusable="false" aria-hidden="true">
						<path d="M4 6.75C4 5.23122 5.23122 4 6.75 4H17.25C18.7688 4 20 5.23122 20 6.75V13.25C20 14.7688 18.7688 16 17.25 16H10.6178L7.07143 19.1031C6.17754 19.8852 4.75 19.2504 4.75 18.0615V16.75C4.33277 16.3563 4 15.7982 4 15.1615V6.75Z" fill="currentColor"/>
						<path d="M8 9H16" stroke="rgba(255,255,255,0.92)" stroke-width="1.8" stroke-linecap="round"/>
						<path d="M8 12.5H13.5" stroke="rgba(255,255,255,0.92)" stroke-width="1.8" stroke-linecap="round"/>
					</svg>
				</span>
				<span class="aiscb-widget__launcher-texts">
					<span class="aiscb-widget__launcher-title"><?php echo esc_html__( 'Chat with us', 'ai-site-search-chatbot' ); ?></span>
					<span class="aiscb-widget__launcher-subtitle"><?php echo esc_html__( 'Quick site help', 'ai-site-search-chatbot' ); ?></span>
				</span>
			</button>
			<div id="<?php echo esc_attr( $panel_id ); ?>" class="aiscb-widget__panel" hidden>
				<div class="aiscb-widget__shell">
					<div class="aiscb-widget__header">
						<div class="aiscb-widget__header-copy">
							<div class="aiscb-widget__eyebrow"><?php echo esc_html__( 'Site Assistant', 'ai-site-search-chatbot' ); ?></div>
							<p class="aiscb-widget__title"><?php echo esc_html( (string) $args['title'] ); ?></p>
						</div>
						<button type="button" class="aiscb-widget__close" aria-label="<?php echo esc_attr__( 'Close chat', 'ai-site-search-chatbot' ); ?>">x</button>
					</div>
					<div class="aiscb-widget__messages" aria-live="polite" aria-label="<?php echo esc_attr__( 'Chat messages', 'ai-site-search-chatbot' ); ?>"></div>
					<form class="aiscb-widget__form">
						<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html__( 'Your question', 'ai-site-search-chatbot' ); ?></label>
						<textarea id="<?php echo esc_attr( $input_id ); ?>" class="aiscb-widget__input" rows="3" placeholder="<?php echo esc_attr__( 'Ask a question about products, services, or help pages...', 'ai-site-search-chatbot' ); ?>"></textarea>
						<button type="submit" class="aiscb-widget__submit"><?php echo esc_html__( 'Send', 'ai-site-search-chatbot' ); ?></button>
					</form>
				</div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
