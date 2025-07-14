<?php
/**
 * Plugin Name: Divi Text Editor
 * Plugin URI: 
 * Description: A plugin to edit Divi theme text content from the WordPress dashboard without using the visual editor.
 * Version: 1.0.0
 * Author: Abrar
 * Author URI: github.com/abrarulhoque
 * License: GPL-2.0+
 * Text Domain: divi-text-editor
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('DIVI_TEXT_EDITOR_VERSION', '1.0.0');
define('DIVI_TEXT_EDITOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DIVI_TEXT_EDITOR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once DIVI_TEXT_EDITOR_PLUGIN_DIR . 'includes/shortcodes.php';
require_once DIVI_TEXT_EDITOR_PLUGIN_DIR . 'includes/scanner.php';

/**
 * Class DiviTextEditor
 * Main plugin class that handles text replacement and admin interface
 */
class DiviTextEditor {
    
    /**
     * The single instance of the class
     */
    protected static $_instance = null;
    
    /**
     * Variable texts storage
     */
    private $text_variables = [
        // Original variables
        'home_subtitle1' => '',
        'home_title1' => '',
        'home_description1' => '',
        'home_description2' => '',
        
        // New variables
        'section_title_main' => '',
        'section_description_main' => '',
        
        'cta_contact_us' => '',
        'contact_section_title' => '',
        
        'features_title' => '',
        'features_description' => '',
        
        'feature_box_one_title' => '',
        'feature_box_one_description' => '',
        'feature_box_one_cta' => '',
        
        'feature_box_two_title' => '',
        'feature_box_two_description' => '',
        'feature_box_two_cta' => '',
        
        'feature_box_three_title' => '',
        'feature_box_three_description' => '',
        'feature_box_three_cta' => '',
        
        'cta_discover_more' => '',
        
        'blog_section_title' => '',
        
        'blog_label' => '',
        'blog_article_three_title' => '',
        'blog_article_three_content' => '',
        
        'blog_label_repeat_one' => '',
        'blog_article_four_title' => '',
        'blog_article_four_content' => '',
        
        'blog_label_repeat_two' => '',
        'blog_article_five_title' => '',
        'blog_article_five_content' => '',
        
        'blog_label_repeat_three' => '',
        'blog_article_one_title' => '',
        'blog_article_one_content' => '',
        
        'blog_label_repeat_four' => '',
        'blog_article_two_title' => '',
        'blog_article_two_content' => '',
        
        'blog_label_repeat_five' => '',
        'blog_article_three_title_repeat' => '',
        'blog_article_three_content_repeat' => '',
        
        'blog_label_repeat_six' => '',
        'blog_article_four_title_repeat' => '',
        'blog_article_four_content_repeat' => '',
        
        'blog_label_repeat_seven' => '',
        'blog_article_five_title_repeat' => '',
        'blog_article_five_content_repeat' => '',
        
        'blog_label_repeat_eight' => '',
        'blog_article_one_title_repeat' => '',
        'blog_article_one_content_repeat' => '',
        
        'blog_label_repeat_nine' => '',
        'blog_article_two_title_repeat' => '',
        'blog_article_two_content_repeat' => '',
        
        'blog_label_repeat_ten' => '',
        'blog_article_three_title_repeat_two' => '',
        'blog_article_three_content_repeat_two' => '',
        
        'short_title_first' => '',
        
        'about_title_first' => '',
        'about_description_first' => '',
        
        'about_us_label' => '',
        'short_title_second' => '',
        
        'about_title_second' => '',
        'about_description_second' => '',
        
        'cta_discover_more_about' => ''
    ];
    
    /**
     * Scanner instance
     */
    private $scanner = null;
    
    /**
     * Main DiviTextEditor Instance
     * Ensures only one instance of DiviTextEditor is loaded
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * DiviTextEditor Constructor
     */
    public function __construct() {
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Load text variables from database
        $this->load_text_variables();
        
        // Initialize scanner
        $this->scanner = new DiviTextEditorScanner();
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add content filter to replace variables
        add_filter('the_content', array($this, 'replace_variables'), 999);
        
        // Filter Divi module content
        add_filter('et_builder_render_layout', array($this, 'replace_variables'), 999);
        
        // Filter Divi module title and text content
        add_filter('et_pb_module_content', array($this, 'replace_variables'), 999);
        
        // Filter Divi text module content specifically
        add_filter('et_pb_text_module_content', array($this, 'replace_variables'), 999);
        
        // Import/Export functionality
        add_action('admin_post_divi_text_editor_export', array($this, 'export_settings'));
        add_action('admin_init', array($this, 'process_import'));
        
        // Text Mapping functionality
        add_action('wp_ajax_divi_text_editor_map_text', array($this, 'map_text_ajax'));
        
        // Scanner functionality
        add_action('wp_ajax_divi_text_editor_scan', array($this, 'scan_ajax'));
        add_action('wp_ajax_divi_text_editor_update_scanned_text', array($this, 'update_scanned_text_ajax'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Plugin activation hook
     */
    public function activate() {
        // Initialize default text values if they don't exist
        foreach ($this->text_variables as $key => $value) {
            if (!get_option('divi_text_editor_' . $key)) {
                add_option('divi_text_editor_' . $key, 'Default ' . str_replace('_', ' ', $key));
            }
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Load text variables from database
     */
    public function load_text_variables() {
        foreach ($this->text_variables as $key => $value) {
            $this->text_variables[$key] = get_option('divi_text_editor_' . $key, 'Default ' . str_replace('_', ' ', $key));
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Divi Text Editor', 'divi-text-editor'),
            __('Divi Text Editor', 'divi-text-editor'),
            'manage_options',
            'divi-text-editor',
            array($this, 'render_admin_page'),
            'dashicons-edit',
            30
        );
    }
    
    /**
     * Register settings for the plugin
     */
    public function register_settings() {
        // Register a setting for each text variable
        foreach ($this->text_variables as $key => $value) {
            register_setting(
                'divi_text_editor_settings',
                'divi_text_editor_' . $key,
                array(
                    'sanitize_callback' => 'wp_kses_post'
                )
            );
        }
    }
    
    /**
     * Render admin page content
     */
    public function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check for import success
        if (isset($_GET['import']) && $_GET['import'] === 'success') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings imported successfully!', 'divi-text-editor') . '</p></div>';
        }
        
        // Check if scan is being triggered
        if (isset($_GET['scan']) && $_GET['scan'] === 'start') {
            // Run the scanner
            $items = $this->scanner->scan_all_content();
            
            // Save scanned items
            $count = $this->scanner->save_scanned_items();
            
            // Show success message
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(__('%d text items found and saved.', 'divi-text-editor'), $count) . '</p></div>';
        }
        
        // Save settings if form submitted
        if (isset($_POST['divi_text_editor_submit'])) {
            // Check nonce
            if (!isset($_POST['divi_text_editor_nonce']) || !wp_verify_nonce($_POST['divi_text_editor_nonce'], 'divi_text_editor_save')) {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Security check failed. Please try again.', 'divi-text-editor') . '</p></div>';
            } else {
                // Process scanned items
                $scanned_items = $this->scanner->get_saved_items();
                foreach ($scanned_items as $key => $item) {
                    if (isset($_POST['scanned_item_' . $key])) {
                        $new_text = wp_kses_post($_POST['scanned_item_' . $key]);
                        if ($new_text !== $item['text']) {
                            $this->scanner->update_scanned_item($key, $new_text);
                        }
                    }
                }
                
                // Show success message
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'divi-text-editor') . '</p></div>';
            }
        }
        
        // Admin page output
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p><?php _e('Edit your website text content without using the Divi Visual Editor.', 'divi-text-editor'); ?></p>
            
            <div class="nav-tab-wrapper">
                <a href="#settings" class="nav-tab nav-tab-active"><?php _e('Text Settings', 'divi-text-editor'); ?></a>
                <a href="#import-export" class="nav-tab"><?php _e('Import/Export', 'divi-text-editor'); ?></a>
                <a href="#help" class="nav-tab"><?php _e('Help', 'divi-text-editor'); ?></a>
            </div>
            
            <div id="settings" class="tab-content active">
                <div class="settings-actions">
                    <a href="<?php echo add_query_arg('scan', 'start'); ?>" class="button button-primary"><?php _e('Scan Website for Text', 'divi-text-editor'); ?></a>
                    <span class="settings-actions-info"><?php _e('Click this button to scan your website for all text content. This will find and make editable all text in Divi modules.', 'divi-text-editor'); ?></span>
                </div>
                
                <form method="post" action="">
                    <?php wp_nonce_field('divi_text_editor_save', 'divi_text_editor_nonce'); ?>
                    <table class="form-table">
                        <?php
                        // Get scanned items
                        $scanned_items = $this->scanner->get_saved_items();
                        
                        // Display scanned items if any
                        if (!empty($scanned_items)) :
                            $displayed_items = array();
                            
                            foreach ($scanned_items as $key => $item) :
                                // Get the text content and strip HTML tags
                                $text_content = wp_strip_all_tags($item['text']);
                                
                                // Skip if text is empty after stripping tags
                                if (empty(trim($text_content))) {
                                    continue;
                                }
                                
                                // Skip if we've already displayed an identical text
                                if (in_array(md5($text_content), $displayed_items)) {
                                    continue;
                                }
                                
                                // Add to displayed items tracking
                                $displayed_items[] = md5($text_content);
                                
                                // Get post type and title for location info
                                $location = '';
                                if (isset($item['post_title']) && isset($item['post_type'])) {
                                    $post_type_obj = get_post_type_object($item['post_type']);
                                    $post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $item['post_type'];
                                    $location = sprintf(
                                        '%s: %s',
                                        esc_html($post_type_label),
                                        esc_html($item['post_title'])
                                    );
                                }
                                
                                // Get a user-friendly label for the module type
                                $module_type = isset($item['type']) ? $item['type'] : 'unknown';
                                $module_type_label = '';
                                
                                switch ($module_type) {
                                    case 'text':
                                    case 'text_content':
                                        $module_type_label = __('Text Module', 'divi-text-editor');
                                        break;
                                    case 'blurb_title':
                                        $module_type_label = __('Blurb Title', 'divi-text-editor');
                                        break;
                                    case 'blurb_content':
                                        $module_type_label = __('Blurb Content', 'divi-text-editor');
                                        break;
                                    case 'button_text':
                                        $module_type_label = __('Button Text', 'divi-text-editor');
                                        break;
                                    case 'header_title':
                                        $module_type_label = __('Header Title', 'divi-text-editor');
                                        break;
                                    case 'header_subhead':
                                        $module_type_label = __('Header Subheading', 'divi-text-editor');
                                        break;
                                    case 'header_content':
                                        $module_type_label = __('Header Content', 'divi-text-editor');
                                        break;
                                    case 'cta_title':
                                        $module_type_label = __('CTA Title', 'divi-text-editor');
                                        break;
                                    case 'cta_content':
                                        $module_type_label = __('CTA Content', 'divi-text-editor');
                                        break;
                                    case 'cta_button':
                                        $module_type_label = __('CTA Button', 'divi-text-editor');
                                        break;
                                    case 'testimonial_author':
                                        $module_type_label = __('Testimonial Author', 'divi-text-editor');
                                        break;
                                    case 'testimonial_content':
                                        $module_type_label = __('Testimonial Content', 'divi-text-editor');
                                        break;
                                    case 'typing_text':
                                        $module_type_label = __('Typing Text (Divi Pixel)', 'divi-text-editor');
                                        break;
                                    case 'expanding_title':
                                        $module_type_label = __('Expanding CTA Title (Divi Pixel)', 'divi-text-editor');
                                        break;
                                    case 'expanding_description':
                                        $module_type_label = __('Expanding CTA Description (Divi Pixel)', 'divi-text-editor');
                                        break;
                                    case 'expanding_button':
                                        $module_type_label = __('Expanding CTA Button (Divi Pixel)', 'divi-text-editor');
                                        break;
                                    case 'hover_title':
                                        $module_type_label = __('Hover Box Title (Divi Pixel)', 'divi-text-editor');
                                        break;
                                    case 'hover_content':
                                        $module_type_label = __('Hover Box Content (Divi Pixel)', 'divi-text-editor');
                                        break;
                                    case 'hover_button':
                                        $module_type_label = __('Hover Box Button (Divi Pixel)', 'divi-text-editor');
                                        break;
                                    default:
                                        $module_type_label = ucwords(str_replace('_', ' ', $module_type));
                                }
                                
                                // Combine location and type for the label
                                $label = $location . ' - ' . $module_type_label;
                            ?>
                            <tr valign="top">
                                <th scope="row">
                                    <label for="scanned_item_<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html($label); ?>
                                    </label>
                                </th>
                                <td>
                                    <textarea 
                                        name="scanned_item_<?php echo esc_attr($key); ?>" 
                                        id="scanned_item_<?php echo esc_attr($key); ?>" 
                                        class="large-text scanned-text" 
                                        rows="4"
                                        data-key="<?php echo esc_attr($key); ?>"
                                        data-original="<?php echo esc_attr($item['text']); ?>"
                                    ><?php echo esc_textarea($text_content); ?></textarea>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2">
                                    <div class="notice notice-info inline">
                                        <p><?php _e('No text content found. Click "Scan Website for Text" to find and make editable all text in your Divi modules.', 'divi-text-editor'); ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="divi_text_editor_submit" class="button-primary" value="<?php _e('Save Changes', 'divi-text-editor'); ?>" />
                    </p>
                </form>
            </div>
            
            <div id="import-export" class="tab-content">
                <h2><?php _e('Export Settings', 'divi-text-editor'); ?></h2>
                <p><?php _e('Export your current text settings to a JSON file that you can use to restore these settings later or transfer to another site.', 'divi-text-editor'); ?></p>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="divi_text_editor_export" />
                    <?php wp_nonce_field('divi_text_editor_export', 'divi_text_editor_export_nonce'); ?>
                    <p>
                        <input type="submit" class="button button-secondary" value="<?php _e('Export Settings', 'divi-text-editor'); ?>" />
                    </p>
                </form>
                
                <h2><?php _e('Import Settings', 'divi-text-editor'); ?></h2>
                <p><?php _e('Import text settings from a previously exported JSON file.', 'divi-text-editor'); ?></p>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="divi_text_editor_import" value="1" />
                    <?php wp_nonce_field('divi_text_editor_import', 'divi_text_editor_import_nonce'); ?>
                    <p>
                        <input type="file" name="divi_text_editor_import_file" accept=".json" required />
                    </p>
                    <p>
                        <input type="submit" class="button button-secondary" value="<?php _e('Import Settings', 'divi-text-editor'); ?>" />
                    </p>
                </form>
            </div>
            
            <div id="help" class="tab-content">
                <div class="divi-text-editor-help">
                    <h2><?php _e('How to use', 'divi-text-editor'); ?></h2>
                    <p><?php _e('Using the Divi Text Editor is easy:', 'divi-text-editor'); ?></p>
                    
                    <h3><?php _e('Editing Text Content', 'divi-text-editor'); ?></h3>
                    <ol>
                        <li><?php _e('Go to the "Text Settings" tab', 'divi-text-editor'); ?></li>
                        <li><?php _e('Click the "Scan Website for Text" button to find all static text in your website', 'divi-text-editor'); ?></li>
                        <li><?php _e('Edit any text directly in the textareas', 'divi-text-editor'); ?></li>
                        <li><?php _e('Click "Save Changes" to update your website text', 'divi-text-editor'); ?></li>
                    </ol>
                    <p><?php _e('You can use all the Excel-like features (multi-select, copy/paste multiple texts) with the text fields.', 'divi-text-editor'); ?></p>
                    
                    <h3><?php _e('Excel-like Functionality', 'divi-text-editor'); ?></h3>
                    <ul>
                        <li><?php _e('Multi-select: Hold Ctrl/Cmd and click on textareas to select multiple fields', 'divi-text-editor'); ?></li>
                        <li><?php _e('Drag selection: Click and drag from the small blue square in the bottom-right corner of a textarea to select multiple fields', 'divi-text-editor'); ?></li>
                        <li><?php _e('Multi-copy: Select multiple fields and press Ctrl/Cmd+C to copy their content', 'divi-text-editor'); ?></li>
                        <li><?php _e('Multi-paste: Copy multiple lines of text and paste into a textarea to fill consecutive fields', 'divi-text-editor'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Tab functionality
                $('.nav-tab').on('click', function(e) {
                    e.preventDefault();
                    
                    // Get the tab ID
                    var tabId = $(this).attr('href');
                    
                    // Remove active class from all tabs
                    $('.nav-tab').removeClass('nav-tab-active');
                    $('.tab-content').removeClass('active');
                    
                    // Add active class to current tab
                    $(this).addClass('nav-tab-active');
                    $(tabId).addClass('active');
                });
            });
        </script>
        <?php
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_assets($hook) {
        // Only enqueue on our plugin admin page
        if ('toplevel_page_divi-text-editor' !== $hook) {
            return;
        }
        
        // Add custom CSS for the admin page
        wp_enqueue_style(
            'divi-text-editor-admin',
            DIVI_TEXT_EDITOR_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            DIVI_TEXT_EDITOR_VERSION
        );
        
        // Add jQuery
        wp_enqueue_script('jquery');
        
        // Add custom JS for text mapping
        wp_register_script(
            'divi-text-editor-admin-js',
            DIVI_TEXT_EDITOR_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'), // Add wp-util as dependency
            DIVI_TEXT_EDITOR_VERSION,
            true // Load in footer to ensure DOM is ready
        );
        
        // Pass data to JavaScript
        wp_localize_script(
            'divi-text-editor-admin-js',
            'diviTextEditor',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('divi_text_editor_mapping_nonce'),
                'mapping_success' => __('Text has been successfully mapped to the variable!', 'divi-text-editor'),
                'mapping_error' => __('Error: Could not map text to variable. Please try again.', 'divi-text-editor'),
                'version' => DIVI_TEXT_EDITOR_VERSION,
                'debugMode' => defined('WP_DEBUG') && WP_DEBUG
            )
        );
        
        // Enqueue the script
        wp_enqueue_script('divi-text-editor-admin-js');
    }
    
    /**
     * Replace variables in content
     */
    public function replace_variables($content) {
        // Loop through each variable and replace in content
        foreach ($this->text_variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        
        return $content;
    }
    
    /**
     * Export settings as JSON file
     */
    public function export_settings() {
        // Check nonce
        if (!isset($_POST['divi_text_editor_export_nonce']) || !wp_verify_nonce($_POST['divi_text_editor_export_nonce'], 'divi_text_editor_export')) {
            wp_die(__('Security check failed. Please try again.', 'divi-text-editor'));
        }
        
        // Get scanned items
        $scanned_items = $this->scanner->get_saved_items();
        
        // Prepare the data
        $data = array();
        
        // Add scanned items to export data
        if (!empty($scanned_items)) {
            foreach ($scanned_items as $key => $item) {
                $data[$key] = $item;
            }
        }
        
        // Set headers for JSON download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="divi-text-editor-' . date('Y-m-d') . '.json"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output the JSON
        echo wp_json_encode($data);
        exit;
    }
    
    /**
     * Process settings import
     */
    public function process_import() {
        // Check if import is being processed
        if (!isset($_POST['divi_text_editor_import'])) {
            return;
        }
        
        // Check nonce
        if (!isset($_POST['divi_text_editor_import_nonce']) || !wp_verify_nonce($_POST['divi_text_editor_import_nonce'], 'divi_text_editor_import')) {
            wp_die(__('Security check failed. Please try again.', 'divi-text-editor'));
        }
        
        // Check file upload
        if (!isset($_FILES['divi_text_editor_import_file']) || $_FILES['divi_text_editor_import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_die(__('File upload failed. Please try again.', 'divi-text-editor'));
        }
        
        // Get file content
        $file_content = file_get_contents($_FILES['divi_text_editor_import_file']['tmp_name']);
        if (empty($file_content)) {
            wp_die(__('Uploaded file is empty. Please try again.', 'divi-text-editor'));
        }
        
        // Decode JSON
        $data = json_decode($file_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die(__('Invalid JSON file. Please upload a valid JSON file.', 'divi-text-editor'));
        }
        
        // Update options
        foreach ($this->text_variables as $key => $value) {
            if (isset($data[$key])) {
                update_option('divi_text_editor_' . $key, wp_kses_post($data[$key]));
            }
        }
        
        // Redirect back to the settings page with success message
        wp_redirect(add_query_arg('import', 'success', admin_url('admin.php?page=divi-text-editor')));
        exit;
    }
    
    /**
     * Map text to a variable via AJAX
     */
    public function map_text_ajax() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'divi_text_editor_mapping_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'divi-text-editor')));
            wp_die();
        }
        
        // Check for required data
        if (!isset($_POST['text']) || !isset($_POST['variable']) || empty($_POST['variable'])) {
            wp_send_json_error(array('message' => __('Missing required data.', 'divi-text-editor')));
            wp_die();
        }
        
        $text = sanitize_textarea_field($_POST['text']);
        $variable = sanitize_key($_POST['variable']);
        
        // Check if variable exists
        if (!array_key_exists($variable, $this->text_variables)) {
            wp_send_json_error(array('message' => __('Invalid variable.', 'divi-text-editor')));
            wp_die();
        }
        
        // Update the option with the text
        update_option('divi_text_editor_' . $variable, $text);
        
        // Return success
        wp_send_json_success(array(
            'message' => __('Text successfully mapped to variable.', 'divi-text-editor'),
            'variable' => $variable,
            'text' => $text
        ));
        
        wp_die();
    }
    
    /**
     * Run the text scanner via AJAX
     */
    public function scan_ajax() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'divi_text_editor_mapping_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'divi-text-editor')));
            wp_die();
        }
        
        // Run scanner
        $items = $this->scanner->scan_all_content();
        
        // Save scanned items
        $count = $this->scanner->save_scanned_items();
        
        // Return success
        wp_send_json_success(array(
            'message' => sprintf(__('%d text items found and saved.', 'divi-text-editor'), $count),
            'count' => $count
        ));
        
        wp_die();
    }
    
    /**
     * Update scanned text via AJAX
     */
    public function update_scanned_text_ajax() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'divi_text_editor_mapping_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'divi-text-editor')));
            wp_die();
        }
        
        // Check for required data
        if (!isset($_POST['key']) || !isset($_POST['text'])) {
            wp_send_json_error(array('message' => __('Missing required data.', 'divi-text-editor')));
            wp_die();
        }
        
        $key = sanitize_text_field($_POST['key']);
        $text = wp_kses_post($_POST['text']);
        
        // Update the text
        $success = $this->scanner->update_scanned_item($key, $text);
        
        if ($success) {
            wp_send_json_success(array(
                'message' => __('Text updated successfully.', 'divi-text-editor'),
                'key' => $key,
                'text' => $text
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to update text. The text may not exist or could not be found in the post content.', 'divi-text-editor')));
        }
        
        wp_die();
    }
}

// Initialize the plugin
function divi_text_editor() {
    return DiviTextEditor::instance();
}

// Start the plugin
divi_text_editor(); 