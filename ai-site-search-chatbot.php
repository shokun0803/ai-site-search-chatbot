<?php
/**
 * Plugin Name: AI Site Search Chatbot
 * Description: Provides a public chatbot that searches site content and can answer visitors with an AI provider connected through WordPress Settings > Connectors.
 * Version: 1.0.0
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Author: GitHub Copilot, shokun0803
 * Text Domain: ai-site-search-chatbot
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'AISCB_FILE' ) ) {
	define( 'AISCB_FILE', __FILE__ );
}

if ( ! defined( 'AISCB_DIR' ) ) {
	define( 'AISCB_DIR', plugin_dir_path( AISCB_FILE ) );
}

require_once AISCB_DIR . 'includes/class-ai-site-search-chatbot.php';
require_once AISCB_DIR . 'includes/class-ai-site-search-chatbot-admin.php';
require_once AISCB_DIR . 'includes/class-ai-site-search-chatbot-block.php';
require_once AISCB_DIR . 'includes/class-ai-site-search-chatbot-frontend.php';

register_activation_hook( AISCB_FILE, array( 'AISite_Search_Chatbot', 'activate' ) );
add_action( 'plugins_loaded', array( 'AISite_Search_Chatbot', 'init' ) );
