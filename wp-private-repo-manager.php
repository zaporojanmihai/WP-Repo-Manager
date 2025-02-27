<?php
/**
 * Plugin Name: WP Private Repository Manager
 * Description: Manage private repositories in WordPress with branch and type selection.
 * Version: 1.0.1
 * Author: Your Name
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin version
define('WPRM_VERSION', '1.0.1');

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/encryption.php';
require_once plugin_dir_path(__FILE__) . 'includes/migration.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/repo-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax-handler.php';

// Initialize plugin
function wprm_init() {
    // Add admin menu
    add_action('admin_menu', 'wprm_add_admin_page');
    
    // Initialize migrations
    WPRM_Migration::init();
}

function wprm_enqueue_admin_scripts($hook) {
    if ($hook !== 'toplevel_page_wprm-admin') return;
    
    wp_enqueue_script('wprm-admin', plugins_url('assets/js/admin.js', __FILE__), ['jquery'], '1.0', true);
    
    wp_localize_script('wprm-admin', 'wprm_admin', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wprm_admin_nonce')
    ]);
}

add_action('plugins_loaded', 'wprm_init');
add_action('admin_enqueue_scripts', 'wprm_enqueue_admin_scripts');
