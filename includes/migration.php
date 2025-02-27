<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles migrations for WP Repo Manager
 */
class WPRM_Migration {
    /**
     * Initialize migration hooks
     */
    public static function init() {
        // Run migration on plugin update
        add_action('plugins_loaded', array(__CLASS__, 'run_migrations'));
    }
    
    /**
     * Run all migrations if needed
     */
    public static function run_migrations() {
        $plugin_version = get_option('wprm_version', '0.0.0');
        $current_version = defined('WPRM_VERSION') ? WPRM_VERSION : '1.0.0';
        
        // Only run migrations if the plugin version has changed
        if (version_compare($plugin_version, $current_version, '<')) {
            // Encrypt tokens migration
            self::migrate_to_encrypted_tokens();
            
            // Update version
            update_option('wprm_version', $current_version);
        }
    }
    
    /**
     * Encrypt existing repository tokens
     */
    public static function migrate_to_encrypted_tokens() {
        // Include encryption utilities if not already loaded
        if (!class_exists('WPRM_Encryption')) {
            require_once(plugin_dir_path(__FILE__) . 'encryption.php');
        }
        
        $repositories = get_option('wprm_repositories', array());
        $updated = false;
        
        foreach ($repositories as $key => $repo) {
            // Check if token exists and needs to be encrypted
            if (!empty($repo['token'])) {
                // Try to decrypt first to see if it's already encrypted
                $test_decrypt = WPRM_Encryption::decrypt($repo['token']);
                
                // If decryption returns an empty string when the token isn't empty,
                // it's likely not encrypted yet
                if (empty($test_decrypt) || $test_decrypt === $repo['token']) {
                    // Not encrypted yet, so encrypt it
                    $repositories[$key]['token'] = WPRM_Encryption::encrypt($repo['token']);
                    $updated = true;
                }
            }
        }
        
        // Only update if tokens were encrypted
        if ($updated) {
            update_option('wprm_repositories', $repositories);
            error_log('WPRM: Successfully migrated tokens to encrypted format');
        }
    }
    
    /**
     * Run a specific migration manually
     * 
     * @param string $migration_name The name of the migration function to run
     */
    public static function run_manual_migration($migration_name) {
        if (method_exists(__CLASS__, $migration_name)) {
            call_user_func(array(__CLASS__, $migration_name));
            return true;
        }
        return false;
    }
}
