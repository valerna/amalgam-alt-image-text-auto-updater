<?php
/**
 * Plugin Name: Amalgam Image Alt Text Auto-Updater
 * Description: Automatically updates the alt text of uploaded images (.png, .jpg, .svg) based on their file names, removing hyphens and file extensions. Includes an admin page to process previously uploaded images within a selected date range, with error handling.
 * Version: 1.8
 * Author: RJ Buchanan
 * Author URI: https://amalgam.design
 */

add_action('add_attachment', 'ipp_auto_update_image_alt_text');

function ipp_auto_update_image_alt_text($post_ID) {
    // Check if the uploaded file is an image
    if (!wp_attachment_is_image($post_ID)) {
        return;
    }

    // Check if alt text already exists
    $existing_alt = get_post_meta($post_ID, '_wp_attachment_image_alt', true);
    if (!empty($existing_alt)) {
        // Alt text already exists, so we don't overwrite it
        return;
    }

    // Get the image's file name
    $file = get_attached_file($post_ID);
    if (!$file) {
        // Unable to get the file, possibly an error in file retrieval
        return;
    }

    $file_info = pathinfo($file);
    $filename = $file_info['filename'];

    // Remove hyphens and get the file name without extension
    $alt_text = str_replace('-', ' ', $filename);

    // Update the image's alt text
    if (!update_post_meta($post_ID, '_wp_attachment_image_alt', sanitize_text_field($alt_text))) {
        // Error handling if update fails
        error_log('Failed to update alt text for image ID: ' . $post_ID);
    }
}

// Add a menu item in the admin dashboard
add_action('admin_menu', 'ipp_image_alt_text_updater_menu');

function ipp_image_alt_text_updater_menu() {
    add_menu_page('Image Alt Text Updater', 'IPP Alt Text Updater', 'manage_options', 'ipp-image-alt-text-updater', 'ipp_image_alt_text_updater_init_page');
}

// Admin page content
function ipp_image_alt_text_updater_init_page() {
    ?>
    <div class="wrap">
        <h1>Amalgam Image Alt Text Updater</h1>
        <form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
            <input type="hidden" name="action" value="ipp_image_alt_text_updater_process">
            <p>
                <label for="start_date">Start Date:</label>
                <input type="date" id="start_date" name="start_date">
            </p>
            <p>
                <label for="end_date">End Date:</label>
                <input type="date" id="end_date" name="end_date">
            </p>
            <?php submit_button('PROCESS'); ?>
        </form>
    </div>
    <?php
}

// Handle the form submission
add_action('admin_post_ipp_image_alt_text_updater_process', 'ipp_image_alt_text_updater_process');

function ipp_image_alt_text_updater_process() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : '';
    $date_query = array();

    if (!empty($start_date)) {
        $date_query['after'] = $start_date . ' 00:00:00';
    }

    if (!empty($end_date)) {
        $date_query['before'] = $end_date . ' 23:59:59';
    }

    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => array('image/png', 'image/jpeg', 'image/svg+xml'),
        'post_status' => 'inherit',
        'posts_per_page' => -1,
        'date_query' => $date_query,
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_ID = get_the_ID();
            ipp_auto_update_image_alt_text($post_ID);
        }
    } else {
        // Error handling if no images are found
        error_log('No images found for the specified date range.');
    }

    // Redirect back to the admin page
    wp_redirect(admin_url('admin.php?page=ipp-image-alt-text-updater'));
    exit;
}

?>
