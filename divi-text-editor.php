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
        
        // Save settings if form submitted
        if (isset($_POST['divi_text_editor_submit'])) {
            // Check nonce
            if (!isset($_POST['divi_text_editor_nonce']) || !wp_verify_nonce($_POST['divi_text_editor_nonce'], 'divi_text_editor_save')) {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Security check failed. Please try again.', 'divi-text-editor') . '</p></div>';
            } else {
                foreach ($this->text_variables as $key => $value) {
                    if (isset($_POST['divi_text_editor_' . $key])) {
                        update_option('divi_text_editor_' . $key, wp_kses_post($_POST['divi_text_editor_' . $key]));
                    }
                }
                
                // Reload variables after save
                $this->load_text_variables();
                
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
                <a href="#mapping" class="nav-tab"><?php _e('Text Mapping', 'divi-text-editor'); ?></a>
                <a href="#import-export" class="nav-tab"><?php _e('Import/Export', 'divi-text-editor'); ?></a>
                <a href="#help" class="nav-tab"><?php _e('Help', 'divi-text-editor'); ?></a>
            </div>
            
            <div id="settings" class="tab-content active">
                <form method="post" action="">
                    <?php wp_nonce_field('divi_text_editor_save', 'divi_text_editor_nonce'); ?>
                    <table class="form-table">
                        <?php foreach ($this->text_variables as $key => $value) : ?>
                            <tr valign="top">
                                <th scope="row">
                                    <label for="divi_text_editor_<?php echo esc_attr($key); ?>">
                                        {{<?php echo esc_html($key); ?>}}
                                    </label>
                                </th>
                                <td>
                                    <textarea 
                                        name="divi_text_editor_<?php echo esc_attr($key); ?>" 
                                        id="divi_text_editor_<?php echo esc_attr($key); ?>" 
                                        class="large-text" 
                                        rows="4"
                                    ><?php echo esc_textarea($value); ?></textarea>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="divi_text_editor_submit" class="button-primary" value="<?php _e('Save Changes', 'divi-text-editor'); ?>" />
                    </p>
                </form>
            </div>
            
            <div id="mapping" class="tab-content">
                <h2><?php _e('Text Mapping Tool', 'divi-text-editor'); ?></h2>
                <p><?php _e('Use this tool to map text from your website directly to variables. Simply paste the text you want to map and select the variable to assign it to.', 'divi-text-editor'); ?></p>
                
                <div class="text-mapping-tool">
                    <div class="text-mapping-input">
                        <label for="text_to_map"><?php _e('Text to Map:', 'divi-text-editor'); ?></label>
                        <textarea id="text_to_map" class="large-text" rows="6" placeholder="<?php _e('Paste the text from your website here...', 'divi-text-editor'); ?>"></textarea>
                    </div>
                    
                    <div class="text-mapping-variable">
                        <label for="variable_to_map"><?php _e('Map to Variable:', 'divi-text-editor'); ?></label>
                        <select id="variable_to_map" class="regular-text">
                            <option value=""><?php _e('-- Select Variable --', 'divi-text-editor'); ?></option>
                            <?php foreach ($this->text_variables as $key => $value) : ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($key); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="text-mapping-actions">
                        <button id="map_text_button" class="button button-primary"><?php _e('Map Text to Variable', 'divi-text-editor'); ?></button>
                        <span class="spinner"></span>
                        <div id="mapping_result" class="mapping-result"></div>
                    </div>
                </div>
                
                <div class="text-mapping-help">
                    <h3><?php _e('How to use the Text Mapping Tool', 'divi-text-editor'); ?></h3>
                    <ol>
                        <li><?php _e('Find the text on your website that you want to replace with a variable', 'divi-text-editor'); ?></li>
                        <li><?php _e('Copy the exact text and paste it in the "Text to Map" field above', 'divi-text-editor'); ?></li>
                        <li><?php _e('Select the variable you want to associate with this text', 'divi-text-editor'); ?></li>
                        <li><?php _e('Click "Map Text to Variable" button', 'divi-text-editor'); ?></li>
                        <li><?php _e('The text will now be mapped to the selected variable', 'divi-text-editor'); ?></li>
                        <li><?php _e('You can then edit the variable\'s content on the "Text Settings" tab', 'divi-text-editor'); ?></li>
                    </ol>
                </div>
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
                    <p><?php _e('There are two ways to use the text variables in your Divi content:', 'divi-text-editor'); ?></p>
                    
                    <h3><?php _e('Method 1: Variable Placeholders', 'divi-text-editor'); ?></h3>
                    <p><?php _e('Use these variables in your Divi content by adding them in double curly braces. For example:', 'divi-text-editor'); ?></p>
                    <ul>
                        <li><code>{{home_title1}}</code> - <?php _e('Will be replaced with your home title content', 'divi-text-editor'); ?></li>
                        <li><code>{{home_subtitle1}}</code> - <?php _e('Will be replaced with your home subtitle content', 'divi-text-editor'); ?></li>
                    </ul>
                    
                    <h3><?php _e('Method 2: Shortcodes', 'divi-text-editor'); ?></h3>
                    <p><?php _e('You can also use shortcodes to display the text variables:', 'divi-text-editor'); ?></p>
                    <ul>
                        <li><code>[divi_text key="home_title1"]</code> - <?php _e('Will display the home title content', 'divi-text-editor'); ?></li>
                        <li><code>[divi_text key="home_subtitle1" default="Fallback text"]</code> - <?php _e('Will display the home subtitle content, or the default text if no value is set', 'divi-text-editor'); ?></li>
                    </ul>
                    
                    <h3><?php _e('Adding variables to Divi', 'divi-text-editor'); ?></h3>
                    <p><?php _e('To add these variables to your Divi content:', 'divi-text-editor'); ?></p>
                    <ol>
                        <li><?php _e('Edit your page with Divi Builder', 'divi-text-editor'); ?></li>
                        <li><?php _e('Add or edit a text module', 'divi-text-editor'); ?></li>
                        <li><?php _e('Insert the variable using double curly braces, like {{home_title1}} or use the shortcode [divi_text key="home_title1"]', 'divi-text-editor'); ?></li>
                        <li><?php _e('Save your changes', 'divi-text-editor'); ?></li>
                    </ol>
                    
                    <h3><?php _e('Editing variables', 'divi-text-editor'); ?></h3>
                    <p><?php _e('Simply edit the values in the Text Settings tab and save changes. Your website will immediately update with the new content.', 'divi-text-editor'); ?></p>
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
        
        // Load current settings
        $this->load_text_variables();
        
        // Prepare the data
        $data = array();
        foreach ($this->text_variables as $key => $value) {
            $data[$key] = $value;
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
}

// Initialize the plugin
function divi_text_editor() {
    return DiviTextEditor::instance();
}

// Start the plugin
divi_text_editor(); 