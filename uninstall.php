<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'AISCB_FILE' ) ) {
	define( 'AISCB_FILE', __DIR__ . '/ai-site-search-chatbot.php' );
}

if ( ! defined( 'AISCB_DIR' ) ) {
	define( 'AISCB_DIR', __DIR__ . '/' );
}

require_once AISCB_DIR . 'includes/class-ai-site-search-chatbot.php';

$settings = get_option( AISite_Search_Chatbot::OPTION_KEY, array() );
$cleanup_mode = is_array( $settings ) && isset( $settings['uninstall_cleanup_mode'] )
	? sanitize_key( (string) $settings['uninstall_cleanup_mode'] )
	: 'retain';

if ( 'delete_all' === $cleanup_mode ) {
	AISite_Search_Chatbot::delete_plugin_data();
	AISite_Search_Chatbot::remove_all_knowledge_editor_capabilities();
}