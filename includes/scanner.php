<?php
/**
 * Divi Text Scanner functionality
 * Scans all Divi content for static text and makes it editable
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class DiviTextEditorScanner
 * Handles scanning and extracting text from Divi modules
 */
class DiviTextEditorScanner {
    
    /**
     * Store scanned text items
     */
    private $scanned_items = [];
    
    /**
     * Divi shortcode patterns for different module types
     */
    private $shortcode_patterns = [
        // Text modules
        'text' => '/\[et_pb_text[^\]]*\](.*?)\[\/et_pb_text\]/is',
        
        // Blurb modules (title attribute)
        'blurb_title' => '/\[et_pb_blurb[^\]]*title="([^"]*)"[^\]]*\]/i',
        
        // Button modules (button text attribute)
        'button_text' => '/\[et_pb_button[^\]]*button_text="([^"]*)"[^\]]*\]/i',
        
        // Header modules
        'header_title' => '/\[et_pb_fullwidth_header[^\]]*title="([^"]*)"[^\]]*\]/i',
        'header_content' => '/\[et_pb_fullwidth_header[^\]]*content="([^"]*)"[^\]]*\]/i',
        
        // Call to action modules
        'cta_title' => '/\[et_pb_cta[^\]]*title="([^"]*)"[^\]]*\]/i',
        'cta_content' => '/\[et_pb_cta[^\]]*\](.*?)\[\/et_pb_cta\]/is',
        
        // Testimonial modules
        'testimonial_author' => '/\[et_pb_testimonial[^\]]*author="([^"]*)"[^\]]*\]/i',
        'testimonial_content' => '/\[et_pb_testimonial[^\]]*\](.*?)\[\/et_pb_testimonial\]/is',
    ];
    
    /**
     * Exclude patterns for dynamic content
     */
    private $exclude_patterns = [
        // Skip anything that looks like a shortcode
        '/\[[\w\-\/]+.*?\]/',
        
        // Skip Divi dynamic content tags
        '/%[\w_]+%/',
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize empty array
        $this->scanned_items = [];
    }
    
    /**
     * Run the scanner on all Divi-enabled content
     * 
     * @return array Scanned text items
     */
    public function scan_all_content() {
        // Get all post types that might use Divi
        $post_types = [
            'post',
            'page',
            'et_pb_layout',
            'et_body_layout',
            'et_header_layout', 
            'et_footer_layout'
        ];
        
        // Query posts
        $posts = get_posts([
            'post_type' => $post_types,
            'numberposts' => -1,
            'post_status' => 'publish'
        ]);
        
        // Reset scanned items
        $this->scanned_items = [];
        
        // Process each post
        foreach ($posts as $post) {
            $this->scan_post($post);
        }
        
        // Return the found items
        return $this->scanned_items;
    }
    
    /**
     * Scan a single post for Divi content
     * 
     * @param WP_Post $post Post object to scan
     */
    private function scan_post($post) {
        // Check if post has content
        if (empty($post->post_content)) {
            return;
        }
        
        // Check if post has Divi content (contains at least one Divi shortcode)
        if (strpos($post->post_content, '[et_pb_') === false) {
            return;
        }
        
        // ------------------------------------------------------------------
        // NEW LOGIC: collect matches together with their offset so we can sort
        // them in the exact order they appear inside the post content. This
        // prevents the list from being grouped by module type (previous
        // behaviour) and instead follows the top-to-bottom visual flow that
        // the editor expects (see CLAUDE.md requirement #2).
        // ------------------------------------------------------------------
        $post_items = [];
        
        foreach ($this->shortcode_patterns as $type => $pattern) {
            if (preg_match_all($pattern, $post->post_content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    // For every defined pattern the content we want lives in
                    // capture group 1. With PREG_OFFSET_CAPTURE each element is
                    // an array: [ value, offset ].
                    $text   = isset($match[1][0]) ? $match[1][0] : '';
                    $offset = isset($match[1][1]) ? $match[1][1] : 0;

                    // Skip empty strings
                    if (empty(trim($text))) {
                        continue;
                    }
                    
                    // Skip dynamic content
                    if ($this->contains_dynamic_content($text)) {
                        continue;
                    }
                    
                    // Decode entities but keep HTML tags – preserves frontend
                    // formatting when the string contains inline markup.
                    $text_decoded = html_entity_decode($text);
                    
                    // Build the item array exactly as before
                    $key   = $this->generate_key($text_decoded, $type, $post->ID);
                    $item  = [
                        'text'       => $text_decoded,
                        'type'       => $type,
                        'post_id'    => $post->ID,
                        'post_title' => $post->post_title,
                        'post_type'  => $post->post_type,
                        'key'        => $key,
                        'offset'     => $offset
                    ];

                    $post_items[] = $item;
                }
            }
        }
        
        // Sort all found items for this post by their offset in ascending order
        // (i.e. first text that appears in the markup comes first).
        usort($post_items, function ($a, $b) {
            return $a['offset'] <=> $b['offset'];
        });
        
        // Merge the ordered items into the main scanned list while preserving
        // the order of insertion (PHP arrays keep insertion order).
        foreach ($post_items as $item) {
            $this->scanned_items[$item['key']] = $item;
        }
    }
    
    /**
     * Check if text contains dynamic content that should be skipped
     * 
     * @param string $text Text to check
     * @return bool True if contains dynamic content
     */
    private function contains_dynamic_content($text) {
        foreach ($this->exclude_patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate a unique key for a text item
     * 
     * @param string $text Original text
     * @param string $type Module type
     * @param int $post_id Post ID
     * @return string Unique key
     */
    private function generate_key($text, $type, $post_id) {
        // Create a base that combines the module type and post ID
        $base = sanitize_key($type . '_' . $post_id . '_');
        
        // Generate a partial hash of the text content to make it unique
        $text_hash = substr(md5($text), 0, 8);
        
        // Combine them
        return $base . $text_hash;
    }
    
    /**
     * Save all scanned items to the database
     */
    public function save_scanned_items() {
        // Get existing items
        $existing_items = get_option('divi_text_editor_scanned_items', []);
        
        // Merge new items with existing ones
        $merged_items = array_merge($existing_items, $this->scanned_items);
        
        // Save to database
        update_option('divi_text_editor_scanned_items', $merged_items);
        
        return count($this->scanned_items);
    }
    
    /**
     * Get all scanned items from the database
     * 
     * @return array All scanned items
     */
    public function get_saved_items() {
        return get_option('divi_text_editor_scanned_items', []);
    }
    
    /**
     * Update a scanned item with new text
     * 
     * @param string $key Item key
     * @param string $new_text New text value
     * @return bool Success
     */
    public function update_scanned_item($key, $new_text) {
        $items = $this->get_saved_items();
        
        // Check if item exists
        if (!isset($items[$key])) {
            return false;
        }
        
        // Get the item data
        $item = $items[$key];
        
        // Get the original text with HTML tags
        $original_text = isset($item['text']) ? $item['text'] : '';
        
        // Check if we received POST data with original HTML
        if (isset($_POST['scanned_item_' . $key . '_original'])) {
            $original_text = wp_kses_post($_POST['scanned_item_' . $key . '_original']);
        }
        
        // Get the post
        $post = get_post($item['post_id']);
        if (!$post) {
            return false;
        }
        
        // Get post content
        $content = $post->post_content;
        
        // Different handling depending on the module type
        switch ($item['type']) {
            case 'text':
                // Text module - replace content
                $pattern = '/(\[et_pb_text[^\]]*\])' . preg_quote($original_text, '/') . '(\[\/et_pb_text\])/is';
                $replacement = '$1' . $new_text . '$2';
                break;
                
            case 'blurb_title':
                // Blurb module - replace title attribute
                $pattern = '/(\[et_pb_blurb[^\]]*title=")' . preg_quote($original_text, '/') . '("[^\]]*\])/i';
                $replacement = '$1' . $new_text . '$2';
                break;
                
            case 'button_text':
                // Button module - replace button_text attribute
                $pattern = '/(\[et_pb_button[^\]]*button_text=")' . preg_quote($original_text, '/') . '("[^\]]*\])/i';
                $replacement = '$1' . $new_text . '$2';
                break;
                
            case 'header_title':
                // Header module - replace title attribute
                $pattern = '/(\[et_pb_fullwidth_header[^\]]*title=")' . preg_quote($original_text, '/') . '("[^\]]*\])/i';
                $replacement = '$1' . $new_text . '$2';
                break;
                
            case 'header_content':
                // Header module - replace content attribute
                $pattern = '/(\[et_pb_fullwidth_header[^\]]*content=")' . preg_quote($original_text, '/') . '("[^\]]*\])/i';
                $replacement = '$1' . $new_text . '$2';
                break;
                
            case 'cta_title':
                // CTA module - replace title attribute
                $pattern = '/(\[et_pb_cta[^\]]*title=")' . preg_quote($original_text, '/') . '("[^\]]*\])/i';
                $replacement = '$1' . $new_text . '$2';
                break;
                
            case 'cta_content':
                // CTA module - replace content
                $pattern = '/(\[et_pb_cta[^\]]*\])' . preg_quote($original_text, '/') . '(\[\/et_pb_cta\])/is';
                $replacement = '$1' . $new_text . '$2';
                break;
                
            case 'testimonial_author':
                // Testimonial module - replace author attribute
                $pattern = '/(\[et_pb_testimonial[^\]]*author=")' . preg_quote($original_text, '/') . '("[^\]]*\])/i';
                $replacement = '$1' . $new_text . '$2';
                break;
                
            case 'testimonial_content':
                // Testimonial module - replace content
                $pattern = '/(\[et_pb_testimonial[^\]]*\])' . preg_quote($original_text, '/') . '(\[\/et_pb_testimonial\])/is';
                $replacement = '$1' . $new_text . '$2';
                break;
                
            default:
                return false;
        }
        
        // Perform the replacement
        $new_content = preg_replace($pattern, $replacement, $content);
        
        // Update the post content if it changed
        if ($new_content !== $content) {
            // Update post
            wp_update_post([
                'ID' => $post->ID,
                'post_content' => $new_content
            ]);
            
            // Update the stored item with new text
            $items[$key]['text'] = $new_text;
            update_option('divi_text_editor_scanned_items', $items);
            
            // Clear Divi cache if function exists
            if (function_exists('et_core_clear_cache')) {
                et_core_clear_cache();
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete saved scanned items
     * 
     * @return bool Success
     */
    public function delete_all_items() {
        return delete_option('divi_text_editor_scanned_items');
    }
} 