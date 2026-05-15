<?php
/*
Plugin Name: Easy Background Removal
Description: Remove the background from images with the power of remove.gr API
Version: 1.0
Author: Christos Kakoulakis
Authors site: www.christos-kakoulakis.gr
Powered from remove.bg
*/


if (!defined('ABSPATH')) exit;

// Register settings page
// add_action('admin_menu', 'rbg_add_admin_menu');
// add_action('admin_init', 'rbg_settings_init');

if (!defined('ABSPATH')) exit;

class Remove_BG_Plugin {
    private $option_key = 'rbg_api_key';
    private $option_format = 'rbg_output_format';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_rbg_remove_bg', [$this, 'ajax_remove_bg']);
        add_filter('media_row_actions', [$this, 'add_media_button'], 10, 2);
        add_shortcode('remove_bg_upload_form', [$this, 'render_upload_form_shortcode']);
        add_action('init', [$this, 'handle_frontend_submission']);
    }

    public function add_admin_menu() {
        add_options_page('Remove.bg Settings', 'Remove.bg', 'manage_options', 'remove-bg-settings', [$this, 'settings_page_html']);
    }

    public function register_settings() {
        register_setting('rbg_settings_group', $this->option_key);
        register_setting('rbg_settings_group', $this->option_format);

        add_settings_section('rbg_settings_section', __('API Settings', 'remove-bg'), null, 'rbg_settings_group');

        add_settings_field(
            $this->option_key,
            __('API Key', 'remove-bg'),
            [$this, 'api_key_field_html'],
            'rbg_settings_group',
            'rbg_settings_section'
        );

        add_settings_field(
            $this->option_format,
            __('Output Image', 'remove-bg'),
            [$this, 'output_format_field_html'],
            'rbg_settings_group',
            'rbg_settings_section'
        );
    }

    public function api_key_field_html() {
        $value = get_option($this->option_key);
        echo '<input type="text" name="' . esc_attr($this->option_key) . '" value="' . esc_attr($value) . '" class="regular-text">';
    }

    public function output_format_field_html() {
        $value = get_option($this->option_format, 'png');
        echo '<select name="' . esc_attr($this->option_format) . '">
                <option value="png"' . selected($value, 'png', false) . '>PNG</option>
                <option value="webp"' . selected($value, 'webp', false) . '>WEBP</option>
                <option value="jpeg"' . selected($value, 'jpeg', false) . '>JPEG</option>
              </select>';
    }

public function settings_page_html() {
    echo '<div class="wrap">
            <h1>' . esc_html(get_admin_page_title()) . '</h1>
            <form method="post" action="options.php">';
    settings_fields('rbg_settings_group');
    do_settings_sections('rbg_settings_group');
    submit_button();
    echo '  </form>
          </div>';
    echo 'This plug-in is powered by remove.bg API. You can find your API key at https://www.remove.bg/api. You can use the shortcode [remove_bg_upload_form] to use the front-end form for easy background removal.';
}


    public function enqueue_scripts($hook) {
        if (!in_array($hook, ['upload.php', 'post.php'], true)) return;
        wp_enqueue_script('rbg-script', plugin_dir_url(__FILE__) . 'rbg-script.js', ['jquery'], null, true);
        wp_localize_script('rbg-script', 'rbg_ajax_obj', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rbg_nonce'),
            'removing_text' => __('Removing...', 'remove-bg'),
            'done_text' => __('Done', 'remove-bg'),
            'button_text' => __('Remove BG', 'remove-bg'),
            'edit_button_text' => __('Remove BG', 'remove-bg'),
            'error_text' => __('Error', 'remove-bg'),
            'new_image_text' => __('New image', 'remove-bg'),
        ]);
    }

    public function add_media_button($actions, $post) {
        if (strpos($post->post_mime_type, 'image') === 0) {
            $actions['remove_bg'] = '<a href="#" class="rbg-remove-bg" data-id="' . esc_attr($post->ID) . '">' . __('Remove BG', 'remove-bg') . '</a>';
        }
        return $actions;
    }

public function ajax_remove_bg() {
    check_ajax_referer('rbg_nonce');

    if (!current_user_can('upload_files')) {
        wp_send_json_error('You do not have permission.');
    }

    $image_id = intval($_POST['image_id']);
    $image_path = get_attached_file($image_id);

    if (!$image_path || !file_exists($image_path)) {
        wp_send_json_error('Image file not found.');
    }

    $api_key = trim((string) get_option($this->option_key));
    if ($api_key === '') {
        wp_send_json_error('Missing Remove.bg API key. Add it in Settings > Remove.bg.');
    }

    $output_format = get_option($this->option_format, 'png');
    if (!in_array($output_format, ['png', 'webp', 'jpeg'], true)) {
        $output_format = 'png';
    }

    $response = $this->send_image_to_api($image_path, $api_key, $output_format);

    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }

    $upload_dir = wp_upload_dir();
    $ext = ($output_format === 'jpeg') ? 'jpg' : $output_format;
    $base_filename = pathinfo($image_path, PATHINFO_FILENAME);
    $filename = wp_unique_filename($upload_dir['path'], 'no-bg-' . $base_filename . '.' . $ext);
    $filepath = $upload_dir['path'] . '/' . $filename;

    if (false === file_put_contents($filepath, $response)) {
        wp_send_json_error('Could not save processed image.');
    }

    // === Insert in media library ===
    $wp_filetype = wp_check_filetype($filename, null);

    $attachment = [
        'guid'           => $upload_dir['url'] . '/' . $filename,
        'post_mime_type' => $wp_filetype['type'],
        'post_title'     => sanitize_file_name($base_filename),
        'post_content'   => '',
        'post_status'    => 'inherit'
    ];

    $attach_id = wp_insert_attachment($attachment, $filepath);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
    wp_update_attachment_metadata($attach_id, $attach_data);

    $url = wp_get_attachment_url($attach_id);
    wp_send_json_success([
        'id' => $attach_id,
        'url' => $url,
        'editUrl' => get_edit_post_link($attach_id, 'raw'),
    ]);
}


//cUrl
private function send_image_to_api($image_path, $api_key, $output_format = 'webp') {
    $api_url = 'https://api.remove.bg/v1.0/removebg';

    if (!file_exists($image_path)) {
        return new WP_Error('no_file', 'Image file not found');
    }

    $file = curl_file_create($image_path, mime_content_type($image_path), basename($image_path));

    $data = [
        'image_file' => $file,
        'output_format' => $output_format,
    ];

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Api-Key: ' . $api_key
    ]);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return new WP_Error('curl_error', $error_msg);
    }

    curl_close($ch);

    if ($http_code !== 200) {
        return new WP_Error('api_error', 'API Error: ' . $result);
    }

    return $result;
}



public function render_upload_form_shortcode() {
    ob_start();
    ?>
    <form method="post" enctype="multipart/form-data">
        <label>Upload Photo:</label><br>
        <input type="file" name="remove_bg_image" accept="image/*" required>
        <br><br>
        <input type="submit" name="remove_bg_submit" value="Remove Background">
    </form>
    <?php
    if (isset($_SESSION['remove_bg_result_url'])) {
        echo "<p><strong>New Image:</strong><br><img src='" . esc_url($_SESSION['remove_bg_result_url']) . "' style='max-width:100%;'></p>";
        unset($_SESSION['remove_bg_result_url']);
    }
    return ob_get_clean();
}

public function handle_frontend_submission() {
    if (!isset($_POST['remove_bg_submit']) || empty($_FILES['remove_bg_image']['tmp_name'])) {
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $image_path = $_FILES['remove_bg_image']['tmp_name'];
    $api_key = get_option($this->option_key);
    $output_format = get_option($this->option_format, 'webp');

    $response = $this->send_image_to_api($image_path, $api_key, $output_format);

    if (is_wp_error($response)) {
        $_SESSION['remove_bg_result_url'] = '';
        return;
    }
    file_put_contents($filepath, $response);
    // $body = wp_remote_retrieve_body($response);
    if (!$body) return;

    $upload_dir = wp_upload_dir();
    $ext = ($output_format === 'jpeg') ? 'jpg' : 'webp';
    $filename = 'no-bg-frontend-' . time() . '.' . $ext;
    $filepath = $upload_dir['path'] . '/' . $filename;
    file_put_contents($filepath, $body);
    // Insert into media library
$wp_filetype = wp_check_filetype($filename, null);
$attachment = [
    'guid'           => $upload_dir['url'] . '/' . $filename,
    'post_mime_type' => $wp_filetype['type'],
    'post_title'     => sanitize_file_name($filename),
    'post_content'   => '',
    'post_status'    => 'inherit'
];


$attach_id = wp_insert_attachment($attachment, $filepath);


require_once ABSPATH . 'wp-admin/includes/image.php';


$attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
wp_update_attachment_metadata($attach_id, $attach_data);

// Save URL for display
    $_SESSION['remove_bg_result_url'] = wp_get_attachment_url($attach_id);
    // $_SESSION['remove_bg_result_url'] = $upload_dir['url'] . '/' . $filename; This save the new image at uploads not in the media library.
}



}

new Remove_BG_Plugin();

