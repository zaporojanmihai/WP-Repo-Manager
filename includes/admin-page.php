<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function wprm_add_admin_page() {
    add_menu_page(
        'Repo Manager',
        'Repo Manager',
        'manage_options',
        'wprm-admin',
        'wprm_admin_page',
        'dashicons-admin-plugins',
        100
    );
}

function wprm_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="wprm-add-repository">
            <h2>Add New Repository</h2>
            <form id="wprm-add-repo-form" method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="repo_url">Repository URL</label></th>
                        <td>
                            <input type="url" id="repo_url" name="repo_url" class="regular-text" 
                                placeholder="https://github.com/owner/repository" required>
                            <p class="description">Enter the GitHub repository URL</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="token">Access Token</label></th>
                        <td>
                            <input type="password" id="token" name="token" class="regular-text" 
                                placeholder="ghp_xxxxxxxxxxxxxxxxxxxx">
                            <p class="description">Required for private repositories. Token must have 'repo' scope.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="type">Repository Type</label></th>
                        <td>
                            <select id="type" name="type" required>
                                <option value="">Select Type</option>
                                <option value="plugin">Plugin</option>
                                <option value="theme">Theme</option>
                            </select>
                            <p class="description">Select whether this is a WordPress plugin or theme</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="branch">Branch</label></th>
                        <td>
                            <select id="branch" name="branch" required>
                                <option value="">Select a branch</option>
                            </select>
                            <p class="description">Select the branch to track</p>
                        </td>
                    </tr>
                </table>
                
                <div class="wprm-message"></div>
                
                <?php submit_button('Add Repository'); ?>
            </form>
        </div>

        <div class="wprm-repositories">
            <h2>Managed Repositories</h2>
            <?php
            $repositories = get_option('wprm_repositories', array());
            if (empty($repositories)) {
                echo '<p>No repositories added yet.</p>';
            } else {
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr>';
                echo '<th>Repository URL</th>';
                echo '<th>Type</th>';
                echo '<th>Branch</th>';
                echo '<th>Last Pull</th>';
                echo '<th>Actions</th>';
                echo '</tr></thead>';
                echo '<tbody>';
                foreach ($repositories as $index => $repo) {
                    echo '<tr data-repo-url="' . esc_attr($repo['url']) . '" data-repo-token="' . esc_attr( ( ( $dec = ( function_exists('wprm_decrypt_token') ? wprm_decrypt_token( $repo['token'] ) : $repo['token'] ) ) !== false ? $dec : $repo['token'] ) ) . '">';
                    echo '<td>' . esc_html($repo['url']) . '<div class="wprm-repo-message"></div></td>';
                    echo '<td>' . esc_html(isset($repo['type']) ? ucfirst($repo['type']) : 'Plugin') . '</td>';
                    echo '<td class="branch-cell" data-branch="' . esc_attr($repo['branch']) . '">' 
                        . esc_html($repo['branch']) 
                        . ' <button type="button" class="button button-small wprm-change-branch">Change</button></td>';
                    echo '<td>' . (isset($repo['last_pull']) ? esc_html($repo['last_pull']) : 'Never') . '</td>';
                    echo '<td class="wprm-actions">';
                    echo '<button type="button" class="button button-primary wprm-pull-repo" data-repo-id="' . esc_attr($index) . '">Pull</button>';
                    echo '<button type="button" class="button button-link-delete wprm-delete-repo" data-repo-id="' . esc_attr($index) . '">Delete</button>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
            ?>
        </div>

        <div class="wprm-pull-history">
            <h2>Recent Pull History</h2>
            <?php
            // Show pull history
            $pull_history = get_option('wprm_pull_history', array());
            if (empty($pull_history)) {
                echo '<p class="wprm-no-history">No pull history available.</p>';
            } else {
                echo '<table class="wp-list-table widefat fixed striped wprm-pull-history">';
                echo '<thead><tr>';
                echo '<th>Repository</th>';
                echo '<th>Type</th>';
                echo '<th>Branch</th>';
                echo '<th>Timestamp</th>';
                echo '<th>Status</th>';
                echo '</tr></thead>';
                echo '<tbody>';
                foreach ($pull_history as $pull) {
                    echo '<tr>';
                    echo '<td>' . esc_html($pull['repo_url']) . '</td>';
                    echo '<td>' . esc_html(isset($pull['type']) ? ucfirst($pull['type']) : 'Plugin') . '</td>';
                    echo '<td>' . esc_html($pull['branch']) . '</td>';
                    echo '<td>' . esc_html($pull['timestamp']) . '</td>';
                    echo '<td>' . (isset($pull['status']) && $pull['status'] ? '<span style="color: #46b450;">Success</span>' : '<span style="color: #dc3232;">Failed</span>') . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
            ?>
        </div>
    </div>

    <style>
    .wprm-actions {
        display: flex;
        gap: 10px;
    }
    .wprm-repo-message {
        margin-top: 5px;
        font-size: 13px;
    }
    .wprm-repo-message .notice-success {
        color: #00a32a;
    }
    .wprm-repo-message .notice-error {
        color: #d63638;
    }
    .wprm-message {
        margin: 20px 0;
    }
    .wprm-pull-history {
        margin-top: 30px;
    }
    .wprm-pull-history code {
        background: #f0f0f1;
        padding: 3px 5px;
        border-radius: 3px;
    }
    .wp-core-ui .button-link-delete {
        border-color: #dc3232;
        color: #dc3232;
    }
    .wp-core-ui .button-link-delete:hover {
        border-color: #dc3232;
    }
    .branch-cell .branch-actions {
        margin-top: 5px;
        display: flex;
        gap: 5px;
    }
    .branch-cell select {
        max-width: 200px;
    }
    .pull-history-container {
        position: relative;
    }
    .pull-history-actions {
        text-align: center;
        padding: 15px;
        background: #f0f0f1;
        border-top: 1px solid #c3c4c7;
    }
    .pull-history-loading {
        text-align: center;
        padding: 10px;
        display: none;
    }
    .pull-history-loading .spinner {
        float: none;
        margin: 0 10px 0 0;
    }
    </style>
    <?php
}
