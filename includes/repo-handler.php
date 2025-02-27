<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WPRM_Repo_Handler {
    private $repositories = array();
    private $ssh_key_path;

    public function __construct() {
        $this->ssh_key_path = WP_CONTENT_DIR . '/.ssh/id_rsa';
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_wprm_pull_repo', array($this, 'handle_pull_request'));
        add_action('admin_post_wprm_update_ssh', array($this, 'handle_ssh_update'));
    }

    public function register_settings() {
        register_setting('wprm_options_group', 'wprm_repositories');
        register_setting('wprm_options_group', 'wprm_ssh_key');
    }

    public function add_repository($repo_url, $branch, $type) {
        $this->repositories[] = array(
            'url' => $repo_url,
            'branch' => $branch,
            'type' => $type,
            'path' => $this->get_target_path($type)
        );
        update_option('wprm_repositories', $this->repositories);
    }

    public function get_repositories() {
        return $this->repositories;
    }

    public function handle_pull_request() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $repo_id = intval($_POST['repo_id']);
        $repositories = $this->get_repositories();

        if (!isset($repositories[$repo_id])) {
            wp_send_json_error('Invalid repository', 400);
        }

        $repo = $repositories[$repo_id];
        $result = $this->pull_repository($repo);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 500);
        }

        wp_send_json_success(array(
            'redirect' => admin_url('admin.php?page=wprm-admin&pull_success=1')
        ));
    }

    private function pull_repository($repo) {
        $target_dir = $repo['path'];
        $repo_url = $repo['url'];
        $branch = $repo['branch'];

        // Set up SSH environment
        putenv('GIT_SSH_COMMAND=ssh -i ' . escapeshellarg($this->ssh_key_path) . ' -o IdentitiesOnly=yes');

        if (!is_dir($target_dir)) {
            $command = sprintf('git clone --branch %s --single-branch %s %s',
                escapeshellarg($branch),
                escapeshellarg($repo_url),
                escapeshellarg($target_dir)
            );
        } else {
            $command = sprintf('git -C %s pull origin %s',
                escapeshellarg($target_dir),
                escapeshellarg($branch)
            );
        }

        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            return new WP_Error('git_error', 'Git operation failed: ' . implode("\n", $output));
        }

        return true;
    }

    public function handle_ssh_update() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('wprm_update_ssh');

        if (!empty($_FILES['ssh_key']['tmp_name'])) {
            $ssh_dir = dirname($this->ssh_key_path);
            if (!is_dir($ssh_dir)) {
                mkdir($ssh_dir, 0700, true);
            }
            move_uploaded_file($_FILES['ssh_key']['tmp_name'], $this->ssh_key_path);
            chmod($this->ssh_key_path, 0600);
        }

        wp_redirect(admin_url('admin.php?page=wprm-admin&ssh_success=1'));
        exit;
    }

    private function get_target_path($type) {
        $base_dir = ($type === 'theme') ? get_theme_root() : WP_PLUGIN_DIR;
        return trailingslashit($base_dir) . basename($this->repositories[count($this->repositories) - 1]['url'], '.git');
    }
}

$wprm_repo_handler = new WPRM_Repo_Handler();
