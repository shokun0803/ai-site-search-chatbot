<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AISite_Search_Chatbot_Block {
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
	}

	public static function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'aiscb-block-editor',
			plugins_url( 'assets/js/aiscb-block.js', AISCB_FILE ),
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor', 'wp-components' ),
			AISite_Search_Chatbot::VERSION,
			true
		);

		wp_localize_script(
			'aiscb-block-editor',
			'AISCBBlock',
			array(
				'i18n' => array(
					'title'        => __( 'AI Site Search Chatbot', 'ai-site-search-chatbot' ),
					'description'  => __( 'Place the site search chatbot without typing the shortcode manually.', 'ai-site-search-chatbot' ),
					'instructions' => __( 'This block displays the chatbot on the public site. Enable the chatbot and choose the shortcode display mode in the plugin settings before publishing.', 'ai-site-search-chatbot' ),
				),
			)
		);

		register_block_type(
			'ai-site-search-chatbot/widget',
			array(
				'api_version'     => 2,
				'editor_script'   => 'aiscb-block-editor',
				'render_callback' => array( __CLASS__, 'render_block' ),
				'attributes'      => array(),
			)
		);
	}

	public static function render_block( array $attributes = array(), string $content = '', $block = null ): string {
		unset( $attributes, $content, $block );

		return AISite_Search_Chatbot_Frontend::render_shortcode();
	}
}