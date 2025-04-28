<?php
/**
 * Shortcode functionality for Divi Text Editor
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class DiviTextEditorShortcodes
 * Handles shortcode registration and processing
 */
class DiviTextEditorShortcodes {
    
    /**
     * Initialize the shortcodes
     */
    public static function init() {
        add_shortcode('divi_text', array(__CLASS__, 'text_shortcode'));
    }
    
    /**
     * Shortcode for displaying variable text
     * Usage: [divi_text key="home_title1"]
     * 
     * @param array $atts Shortcode attributes
     * @return string The replaced text
     */
    public static function text_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(
            array(
                'key' => '',
                'default' => ''
            ),
            $atts,
            'divi_text'
        );
        
        // No key provided, return default or empty
        if (empty($atts['key'])) {
            return esc_html($atts['default']);
        }
        
        // Get the text value for the key
        $value = get_option('divi_text_editor_' . sanitize_key($atts['key']), $atts['default']);
        
        // Return the value
        return do_shortcode(wp_kses_post($value));
    }
}

// Initialize shortcodes
DiviTextEditorShortcodes::init(); 