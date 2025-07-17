<?php
/**
 * Token encryption utilities for secure storage.
 * Set a unique 32+ char key in wp-config.php as WPRM_TOKEN_KEY.
 * Fallbacks to WordPress AUTH_KEY if not defined.
 */
if ( ! defined( 'WPRM_TOKEN_KEY' ) ) {
    define( 'WPRM_TOKEN_KEY', AUTH_KEY );
}
if ( ! function_exists( 'wprm_encrypt_token' ) ) {
    function wprm_encrypt_token( $plain ) {
        if ( $plain === '' ) {
            return '';
        }
        $key = hash( 'sha256', WPRM_TOKEN_KEY, true ); // 32-byte key
        $iv  = random_bytes( 16 );
        $cipher = openssl_encrypt( $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return base64_encode( $iv . $cipher );
    }
    function wprm_decrypt_token( $encoded ) {
        if ( $encoded === '' ) {
            return '';
        }
        $data = base64_decode( $encoded, true );
        if ( $data === false || strlen( $data ) <= 16 ) {
            return false; // Not encrypted with our helper.
        }
        $iv      = substr( $data, 0, 16 );
        $cipher  = substr( $data, 16 );
        $key     = hash( 'sha256', WPRM_TOKEN_KEY, true );
        $plain = openssl_decrypt( $cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return $plain === false ? false : $plain;
    }
}

// Handle getting branches
add_action('wp_ajax_wprm_get_branches', 'wprm_get_branches');
add_action('wp_ajax_wprm_save_repository', 'wprm_save_repository');
add_action('wp_ajax_wprm_pull_repository', 'wprm_pull_repository');
add_action('wp_ajax_wprm_update_branch', 'wprm_update_branch');
add_action('wp_ajax_wprm_get_pull_history', 'wprm_get_pull_history');
add_action('wp_ajax_wprm_delete_repository', 'wprm_delete_repository');

function wprm_get_branches() {
    check_ajax_referer('wprm_admin_nonce', '_ajax_nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $repo_url = isset($_POST['repo_url']) ? sanitize_text_field($_POST['repo_url']) : '';
    $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
    $enc_token = ! empty( $token ) ? wprm_encrypt_token( $token ) : '';
    
    if (empty($repo_url)) {
        wp_send_json_error('Repository URL is required');
    }

    // Extract owner and repo name from URL
    preg_match('/github\.com\/([^\/]+)\/([^\/\s]+)/', $repo_url, $matches);
    
    if (count($matches) !== 3) {
        wp_send_json_error('Invalid repository URL format');
    }

    $owner = $matches[1];
    $repo_name = $matches[2];
    
    // Create API URL
    $api_url = "https://api.github.com/repos/{$owner}/{$repo_name}/branches";
    
    $args = array(
        'headers' => array(
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version')
        ),
        'timeout' => 15
    );

    if (!empty($token)) {
        $args['headers']['Authorization'] = 'Bearer ' . $token;
    }

    error_log('WPRM Debug - Fetching branches from: ' . $api_url);
    
    // Get branches from GitHub API
    $response = wp_remote_get($api_url, $args);
    
    if (is_wp_error($response)) {
        error_log('WPRM Error - Failed to fetch branches: ' . $response->get_error_message());
        wp_send_json_error('Failed to fetch branches: ' . $response->get_error_message());
    }

    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    error_log('WPRM Debug - API Response status: ' . $status);
    error_log('WPRM Debug - API Response body: ' . $body);

    if ($status === 404) {
        wp_send_json_error('Repository not found. Please check the URL and access token.');
    }
    
    if ($status !== 200) {
        $message = isset($data->message) ? $data->message : 'Failed to fetch branches';
        
        // Check for SAML SSO error
        if (strpos($message, 'Resource protected by organization SAML enforcement') !== false) {
            wp_send_json_error(
                'This repository belongs to an organization with SAML SSO enabled. ' .
                'Please follow these steps to access it:' . "\n" .
                '1. Go to GitHub.com and sign in' . "\n" .
                '2. Click your profile photo > Settings > Developer settings > Personal access tokens' . "\n" .
                '3. Find your token and click "Configure SSO"' . "\n" .
                '4. Enable SSO for the organization that owns this repository' . "\n" .
                '5. Try again after completing these steps'
            );
        }
        
        wp_send_json_error('GitHub API Error: ' . $message);
    }

    if (!is_array($data)) {
        wp_send_json_error('Invalid response from GitHub API');
    }

    $branches = array();
    foreach ($data as $branch) {
        if (isset($branch->name)) {
            $branches[] = $branch->name;
        }
    }

    if (empty($branches)) {
        wp_send_json_error('No branches found in repository');
    }

    wp_send_json_success(array(
        'branches' => $branches
    ));
}

function wprm_save_repository() {
    check_ajax_referer('wprm_admin_nonce', '_ajax_nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $repo_url = isset($_POST['repo_url']) ? sanitize_text_field($_POST['repo_url']) : '';
    $branch = isset($_POST['branch']) ? sanitize_text_field($_POST['branch']) : '';
    $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
    $enc_token = ! empty( $token ) ? wprm_encrypt_token( $token ) : '';
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'plugin';
    
    if (empty($repo_url) || empty($branch)) {
        wp_send_json_error('Repository URL and branch are required');
    }

    if (!in_array($type, array('plugin', 'theme'))) {
        wp_send_json_error('Invalid repository type');
    }

    $repositories = get_option('wprm_repositories', array());
    
    // Check if repository already exists
    $existing_index = -1;
    foreach ($repositories as $index => $repo) {
        if ($repo['url'] === $repo_url) {
            $existing_index = $index;
            break;
        }
    }

    if ($existing_index >= 0) {
        // Update existing repository
        $old_token_enc = isset($repositories[$existing_index]['token']) ? $repositories[$existing_index]['token'] : '';
        $old_token_plain = wprm_decrypt_token( $old_token_enc );
        if ( $old_token_plain === false ) { $old_token_plain = $old_token_enc; }
        $repositories[$existing_index] = array(
            'url' => $repo_url,
            'branch' => $branch,
            'token' => $enc_token,
            'type' => $type,
            'added' => $repositories[$existing_index]['added']
        );
        $message = 'Repository updated successfully';
        $token_changed = ( $old_token_plain !== $token );
    } else {
        // Add new repository
        $repositories[] = array(
            'url' => $repo_url,
            'branch' => $branch,
            'token' => $enc_token,
            'type' => $type,
            'added' => current_time('mysql')
        );
        $message = 'Repository added successfully';
        $token_changed = false;
    }
    
    if (update_option('wprm_repositories', $repositories)) {
        wp_send_json_success(array(
            'message' => $message,
            'token_changed' => $token_changed
        ));
    } else {
        wp_send_json_error('Failed to save repository');
    }
}

function wprm_pull_repository() {
    check_ajax_referer('wprm_admin_nonce', '_ajax_nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $repo_id = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : -1;
    
    if ($repo_id < 0) {
        wp_send_json_error('Invalid repository ID');
    }

    $repositories = get_option('wprm_repositories', array());
    
    if (!isset($repositories[$repo_id])) {
        wp_send_json_error('Repository not found');
    }

    $repo = $repositories[$repo_id];
    
    // Extract owner and repo name from URL
    preg_match('/github\.com\/([^\/]+)\/([^\/\s]+)/', $repo['url'], $matches);
    
    if (count($matches) !== 3) {
        wp_send_json_error('Invalid repository URL format');
    }

    $owner = $matches[1];
    $repo_name = $matches[2];
    
    // Create API URL for the specific branch
    $api_url = "https://api.github.com/repos/{$owner}/{$repo_name}/zipball/{$repo['branch']}";
    
    $args = array(
        'headers' => array(
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress/' . get_bloginfo('version')
        ),
        'timeout' => 60
    );

    $token_dec = wprm_decrypt_token( isset( $repo['token'] ) ? $repo['token'] : '' );
    if ( $token_dec === false ) { $token_dec = isset( $repo['token'] ) ? $repo['token'] : ''; }

    if (!empty($token_dec)) {
        $args['headers']['Authorization'] = 'Bearer ' . $token_dec;
    }

    // Download the ZIP file
    $response = wp_remote_get( $api_url, $args );
    if (is_wp_error($response)) {
        wp_send_json_error('Failed to download repository: ' . $response->get_error_message());
    }

    $status = wp_remote_retrieve_response_code($response);
    
    if ($status !== 200) {
        wp_send_json_error('Failed to download repository. Status code: ' . $status);
    }

    // Create temporary file
    $temp_file = wp_tempnam('wprm_download_');
    if (!$temp_file) {
        wp_send_json_error('Failed to create temporary file');
    }

    // Save ZIP content to temporary file
    file_put_contents($temp_file, wp_remote_retrieve_body($response));

    // Extract ZIP file
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    WP_Filesystem();
    global $wp_filesystem;

    $upload_dir = wp_upload_dir();
    $extract_path = $upload_dir['basedir'] . '/wprm-repos/' . $owner . '-' . $repo_name;

    // Create extraction directory if it doesn't exist
    if (!file_exists($extract_path)) {
        wp_mkdir_p($extract_path);
    }

    // Clear existing files
    $wp_filesystem->delete($extract_path, true);
    wp_mkdir_p($extract_path);

    // Unzip file
    $unzipped = unzip_file($temp_file, $extract_path);
    unlink($temp_file);

    if (is_wp_error($unzipped)) {
        wp_send_json_error('Failed to extract repository: ' . $unzipped->get_error_message());
    }

    // Get the extracted directory (GitHub adds a random suffix)
    $extracted_dirs = glob($extract_path . '/*', GLOB_ONLYDIR);
    if (empty($extracted_dirs)) {
        wp_send_json_error('No files found in extracted archive');
    }

    $extracted_dir = $extracted_dirs[0];
    error_log('WPRM - Extracted dir: ' . $extracted_dir);
    
    // Determine target directory based on type
    $target_dir = $repo['type'] === 'theme' 
        ? WP_CONTENT_DIR . '/themes/' . $repo_name
        : WP_PLUGIN_DIR . '/' . $repo_name;
    
    error_log('WPRM - Target dir: ' . $target_dir);

    // Create parent directory if it doesn't exist
    $parent_dir = dirname($target_dir);
    if (!file_exists($parent_dir)) {
        if (!wp_mkdir_p($parent_dir)) {
            error_log('WPRM - Failed to create parent dir: ' . $parent_dir);
            wp_send_json_error('Failed to create parent directory');
        }
    }

    // Remove existing directory if it exists
    if (file_exists($target_dir)) {
        if (!$wp_filesystem->delete($target_dir, true)) {
            error_log('WPRM - Failed to delete existing dir: ' . $target_dir);
            wp_send_json_error('Failed to remove existing directory');
        }
    }

    // Use WordPress helper to copy everything recursively.
    require_once ABSPATH . 'wp-admin/includes/file.php';

    // Ensure target directory exists (parent dir created earlier).
    if ( ! wp_mkdir_p( $target_dir ) ) {
        error_log( 'WPRM - Failed to create target dir: ' . $target_dir );
        wp_send_json_error( 'Failed to create target directory' );
    }

    $copy_result = copy_dir( $extracted_dir, $target_dir );

    if ( is_wp_error( $copy_result ) ) {
        error_log( 'WPRM - copy_dir error: ' . $copy_result->get_error_message() );
        wp_send_json_error( 'Failed to copy files: ' . $copy_result->get_error_message() );
    }

    // Clean up extraction directory
    if (!$wp_filesystem->delete($extract_path, true)) {
        error_log('WPRM - Failed to clean up extraction dir: ' . $extract_path);
        // Don't fail on cleanup error, just log it
    }

    // Record pull history
    $pull_history = get_option('wprm_pull_history', array());
    $history_item = array(
        'repo_url' => $repo['url'],
        'type' => $repo['type'],
        'branch' => $repo['branch'],
        'timestamp' => current_time('mysql'),
        'status' => true,
        'target_dir' => $target_dir
    );
    array_unshift($pull_history, $history_item);
    $pull_history = array_slice($pull_history, 0, 50); // Keep last 50 entries
    update_option('wprm_pull_history', $pull_history);

    // Update last pull time in repository data
    $repositories[$repo_id]['last_pull'] = current_time('mysql');
    update_option('wprm_repositories', $repositories);

    wp_send_json_success(array(
        'message' => 'Repository pulled successfully',
        'last_pull' => current_time('mysql'),
        'history_item' => $history_item
    ));
}

function wprm_update_branch() {
    check_ajax_referer('wprm_admin_nonce', '_ajax_nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $repo_id = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : -1;
    $new_branch = isset($_POST['branch']) ? sanitize_text_field($_POST['branch']) : '';
    
    if ($repo_id < 0) {
        wp_send_json_error('Invalid repository ID');
    }

    if (empty($new_branch)) {
        wp_send_json_error('Branch name cannot be empty');
    }

    $repositories = get_option('wprm_repositories', array());
    
    if (!isset($repositories[$repo_id])) {
        wp_send_json_error('Repository not found');
    }

    // Store the old branch in case we need to verify the new one
    $old_branch = $repositories[$repo_id]['branch'];
    
    // Update the branch
    $repositories[$repo_id]['branch'] = $new_branch;
    
    // Try to update the option
    $updated = update_option('wprm_repositories', $repositories);
    
    if ($updated) {
        wp_send_json_success(array(
            'message' => 'Branch updated successfully',
            'branch' => $new_branch
        ));
    } else {
        // If update failed, check if the value was actually changed
        $current_repositories = get_option('wprm_repositories', array());
        if (isset($current_repositories[$repo_id]) && 
            $current_repositories[$repo_id]['branch'] === $new_branch) {
            // The value was the same, so no update was needed
            wp_send_json_success(array(
                'message' => 'Branch updated successfully',
                'branch' => $new_branch
            ));
        } else {
            // Actual update failure
            wp_send_json_error('Failed to update branch. Please try again.');
        }
    }
}

function wprm_get_pull_history() {
    check_ajax_referer('wprm_admin_nonce', '_ajax_nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;
    
    $pull_history = get_option('wprm_pull_history', array());
    
    // Calculate start and end indices
    $start = ($page - 1) * $per_page;
    $history_slice = array_slice($pull_history, $start, $per_page);
    
    $has_more = count($pull_history) > ($start + $per_page);
    
    // Format the history items for display
    $formatted_history = array_map(function($item) {
        return array(
            'repo_url' => esc_html($item['repo_url']),
            'branch' => esc_html($item['branch']),
            'timestamp' => esc_html($item['timestamp']),
            'target_dir' => esc_html($item['target_dir'])
        );
    }, $history_slice);
    
    wp_send_json_success(array(
        'history' => $formatted_history,
        'has_more' => $has_more,
        'total' => count($pull_history)
    ));
}

function wprm_delete_repository() {
    check_ajax_referer('wprm_admin_nonce', '_ajax_nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $repo_id = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : -1;
    
    if ($repo_id < 0) {
        wp_send_json_error('Invalid repository ID');
    }

    $repositories = get_option('wprm_repositories', array());
    
    if (!isset($repositories[$repo_id])) {
        wp_send_json_error('Repository not found');
    }

    // Store repository info for response
    $deleted_repo = $repositories[$repo_id];

    // Remove repository from array
    unset($repositories[$repo_id]);
    
    // Reindex array to ensure sequential keys
    $repositories = array_values($repositories);

    if (update_option('wprm_repositories', $repositories)) {
        wp_send_json_success(array(
            'message' => 'Repository deleted successfully',
            'deleted_repo' => $deleted_repo['url']
        ));
    } else {
        wp_send_json_error('Failed to delete repository');
    }
}
