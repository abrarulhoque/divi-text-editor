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
 * Handles scanning and extracting text from Divi modules using hierarchical approach
 */
class DiviTextEditorScanner {
    
    /**
     * Store scanned text items
     */
    private $scanned_items = [];
    
    /**
     * Position counter for maintaining order
     */
    private $position_counter = 0;
    
    /**
     * Module types that contain editable text
     */
    private $text_modules = [
        'et_pb_text',
        'et_pb_blurb',
        'et_pb_button',
        'et_pb_cta',
        'et_pb_fullwidth_header',
        'et_pb_testimonial',
        'et_pb_toggle',
        'et_pb_tab',
        'et_pb_pricing_table',
        'et_pb_number_counter',
        'et_pb_circle_counter',
        'et_pb_countdown_timer',
        'dipi_typing_text',
        'dipi_expanding_cta',
        'dipi_hover_box'
    ];
    
    /**
     * Exclude patterns for dynamic content
     */
    private $exclude_patterns = [
        // Skip anything that looks like a shortcode
        '/\[[\w\-\/]+.*?\]/',
        
        // Skip Divi dynamic content tags
        '/%[\w_]+%/',
        
        // Skip dynamic content markers
        '/@ET-DC@/',
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
        // Only scan pages by default, exclude posts (articles)
        $post_types = ['page'];
        
        // Allow filtering of post types
        $post_types = apply_filters('divi_text_editor_scan_post_types', $post_types);
        
        // Query posts with Divi Builder enabled
        $args = [
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_et_pb_use_builder',
                    'value' => 'on',
                    'compare' => '='
                ]
            ]
        ];
        
        $query = new WP_Query($args);
        
        // Reset scanned items
        $this->scanned_items = [];
        $this->position_counter = 0;
        
        // Process each post
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // Skip blog posts (articles)
                if (get_post_type($post_id) === 'post') {
                    continue;
                }
                
                $this->scan_post_hierarchical($post_id);
            }
        }
        
        wp_reset_postdata();
        
        // Sort by position to ensure proper order
        usort($this->scanned_items, function($a, $b) {
            return $a['position'] - $b['position'];
        });
        
        // Convert to keyed array for compatibility
        $keyed_items = [];
        foreach ($this->scanned_items as $item) {
            $keyed_items[$item['key']] = $item;
        }
        
        return $keyed_items;
    }
    
    /**
     * Scan a single post using hierarchical approach
     * 
     * @param int $post_id Post ID to scan
     */
    private function scan_post_hierarchical($post_id) {
        $post = get_post($post_id);
        if (!$post || empty($post->post_content)) {
            return;
        }
        
        // Check if post has Divi content
        if (strpos($post->post_content, '[et_pb_') === false) {
            return;
        }
        
        // Parse the content hierarchically
        $this->parse_content_hierarchical($post->post_content, $post_id, $post->post_type, $post->post_title);
    }
    
    /**
     * Parse content hierarchically (sections -> rows -> columns -> modules)
     */
    private function parse_content_hierarchical($content, $post_id, $post_type, $post_title) {
        // Parse sections
        $section_pattern = '/\[et_pb_section([^\]]*)\](.*?)\[\/et_pb_section\]/s';
        preg_replace_callback($section_pattern, function($matches) use ($post_id, $post_type, $post_title) {
            $section_content = $matches[2];
            
            // Parse rows within section
            $row_pattern = '/\[et_pb_row([^\]]*)\](.*?)\[\/et_pb_row\]/s';
            preg_replace_callback($row_pattern, function($matches) use ($post_id, $post_type, $post_title) {
                $row_content = $matches[2];
                
                // Parse columns within row
                $column_pattern = '/\[et_pb_column([^\]]*)\](.*?)\[\/et_pb_column\]/s';
                preg_replace_callback($column_pattern, function($matches) use ($post_id, $post_type, $post_title) {
                    $column_content = $matches[2];
                    
                    // Parse modules within column
                    $this->parse_modules($column_content, $post_id, $post_type, $post_title);
                    
                    return $matches[0];
                }, $row_content);
                
                return $matches[0];
            }, $section_content);
            
            return $matches[0];
        }, $content);
    }
    
    /**
     * Parse modules and extract text
     */
    private function parse_modules($content, $post_id, $post_type, $post_title) {
        // Pattern for modules with content
        $module_pattern = '/\[([a-z_]+)([^\]]*?)\](?:(.*?)\[\/\1\])?/s';
        
        preg_replace_callback($module_pattern, function($matches) use ($post_id, $post_type, $post_title) {
            $module_type = $matches[1];
            $attributes_string = $matches[2];
            $inner_content = isset($matches[3]) ? $matches[3] : '';
            
            // Only process text modules
            if (in_array($module_type, $this->text_modules)) {
                $this->extract_module_text($module_type, $attributes_string, $inner_content, $post_id, $post_type, $post_title);
            }
            
            return $matches[0];
        }, $content);
    }
    
    /**
     * Extract text from a specific module
     */
    private function extract_module_text($module_type, $attributes_string, $content, $post_id, $post_type, $post_title) {
        // Parse attributes
        $attributes = $this->parse_attributes($attributes_string);
        
        // Extract text based on module type
        switch ($module_type) {
            case 'et_pb_text':
                if (!empty($content) && !$this->contains_dynamic_content($content)) {
                    $this->add_text_item($content, 'text_content', $module_type, $post_id, $post_type, $post_title);
                }
                break;
                
            case 'et_pb_blurb':
                if (!empty($attributes['title']) && !$this->contains_dynamic_content($attributes['title'])) {
                    $this->add_text_item($attributes['title'], 'blurb_title', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($content) && !$this->contains_dynamic_content($content)) {
                    $this->add_text_item($content, 'blurb_content', $module_type, $post_id, $post_type, $post_title);
                }
                break;
                
            case 'et_pb_button':
                if (!empty($attributes['button_text']) && !$this->contains_dynamic_content($attributes['button_text'])) {
                    $this->add_text_item($attributes['button_text'], 'button_text', $module_type, $post_id, $post_type, $post_title);
                }
                break;
                
            case 'et_pb_fullwidth_header':
                if (!empty($attributes['title']) && !$this->contains_dynamic_content($attributes['title'])) {
                    $this->add_text_item($attributes['title'], 'header_title', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($attributes['subhead']) && !$this->contains_dynamic_content($attributes['subhead'])) {
                    $this->add_text_item($attributes['subhead'], 'header_subhead', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($content) && !$this->contains_dynamic_content($content)) {
                    $this->add_text_item($content, 'header_content', $module_type, $post_id, $post_type, $post_title);
                }
                break;
                
            case 'et_pb_cta':
                if (!empty($attributes['title']) && !$this->contains_dynamic_content($attributes['title'])) {
                    $this->add_text_item($attributes['title'], 'cta_title', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($content) && !$this->contains_dynamic_content($content)) {
                    $this->add_text_item($content, 'cta_content', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($attributes['button_text']) && !$this->contains_dynamic_content($attributes['button_text'])) {
                    $this->add_text_item($attributes['button_text'], 'cta_button', $module_type, $post_id, $post_type, $post_title);
                }
                break;
                
            case 'dipi_typing_text':
                if (!empty($attributes['typing_text']) && !$this->contains_dynamic_content($attributes['typing_text'])) {
                    $this->add_text_item($attributes['typing_text'], 'typing_text', $module_type, $post_id, $post_type, $post_title);
                }
                break;
                
            case 'dipi_expanding_cta':
                if (!empty($attributes['content_title']) && !$this->contains_dynamic_content($attributes['content_title'])) {
                    $this->add_text_item($attributes['content_title'], 'expanding_title', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($attributes['content_description']) && !$this->contains_dynamic_content($attributes['content_description'])) {
                    $this->add_text_item($attributes['content_description'], 'expanding_description', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($attributes['content_button_text']) && !$this->contains_dynamic_content($attributes['content_button_text'])) {
                    $this->add_text_item($attributes['content_button_text'], 'expanding_button', $module_type, $post_id, $post_type, $post_title);
                }
                break;
                
            case 'dipi_hover_box':
                if (!empty($attributes['content_hover_title']) && !$this->contains_dynamic_content($attributes['content_hover_title'])) {
                    $this->add_text_item($attributes['content_hover_title'], 'hover_title', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($attributes['content_hover_content']) && !$this->contains_dynamic_content($attributes['content_hover_content'])) {
                    $this->add_text_item($attributes['content_hover_content'], 'hover_content', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($attributes['content_hover_button_text']) && !$this->contains_dynamic_content($attributes['content_hover_button_text'])) {
                    $this->add_text_item($attributes['content_hover_button_text'], 'hover_button', $module_type, $post_id, $post_type, $post_title);
                }
                break;
                
            case 'et_pb_testimonial':
                if (!empty($attributes['author']) && !$this->contains_dynamic_content($attributes['author'])) {
                    $this->add_text_item($attributes['author'], 'testimonial_author', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($content) && !$this->contains_dynamic_content($content)) {
                    $this->add_text_item($content, 'testimonial_content', $module_type, $post_id, $post_type, $post_title);
                }
                break;
        }
    }
    
    /**
     * Parse shortcode attributes
     */
    private function parse_attributes($attributes_string) {
        $attributes = [];
        
        // Match attribute="value" pattern
        preg_match_all('/(\w+)="([^"]*)"/', $attributes_string, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $attributes[$match[1]] = html_entity_decode($match[2]);
        }
        
        return $attributes;
    }
    
    /**
     * Add a text item to the collection
     */
    private function add_text_item($text, $field_type, $module_type, $post_id, $post_type, $post_title) {
        // Skip if text is empty or just whitespace
        $text = trim($text);
        if (empty($text)) {
            return;
        }
        
        // Generate unique key
        $key = $this->generate_key($text, $field_type, $post_id);
        
        // Store with position for proper ordering
        $this->scanned_items[] = [
            'position' => $this->position_counter++,
            'key' => $key,
            'text' => $text,
            'type' => $field_type,
            'module_type' => $module_type,
            'post_id' => $post_id,
            'post_type' => $post_type,
            'post_title' => $post_title,
            'display_text' => wp_strip_all_tags($text),
            'has_html' => ($text !== wp_strip_all_tags($text))
        ];
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
        // Convert array to keyed format for storage
        $keyed_items = [];
        foreach ($this->scanned_items as $item) {
            $keyed_items[$item['key']] = $item;
        }
        
        // Save to database
        update_option('divi_text_editor_scanned_items', $keyed_items);
        
        return count($this->scanned_items);
    }
    
    /**
     * Get all scanned items from the database
     * 
     * @return array All scanned items
     */
    public function get_saved_items() {
        $items = get_option('divi_text_editor_scanned_items', []);
        
        // Sort by position if available
        if (!empty($items)) {
            uasort($items, function($a, $b) {
                $pos_a = isset($a['position']) ? $a['position'] : 999999;
                $pos_b = isset($b['position']) ? $b['position'] : 999999;
                return $pos_a - $pos_b;
            });
        }
        
        return $items;
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
        
        // Get the original text
        $original_text = $item['text'];
        
        // Get the post
        $post = get_post($item['post_id']);
        if (!$post) {
            return false;
        }
        
        // Get post content
        $content = $post->post_content;
        
        // Escape special regex characters in the original text
        $escaped_original = preg_quote($original_text, '/');
        
        // Different handling depending on the module type and field
        $module_type = isset($item['module_type']) ? $item['module_type'] : '';
        $field_type = $item['type'];
        
        // Build replacement pattern based on field type
        if (strpos($field_type, '_content') !== false || $field_type === 'text_content') {
            // Content between tags
            $pattern = '/(\[' . preg_quote($module_type) . '[^\]]*\])' . $escaped_original . '(\[\/' . preg_quote($module_type) . '\])/s';
            $replacement = '$1' . $new_text . '$2';
        } else {
            // Attribute value
            $attr_name = str_replace(['_text', 'expanding_', 'hover_'], ['', 'content_', 'content_hover_'], $field_type);
            $pattern = '/(\[' . preg_quote($module_type) . '[^\]]*' . $attr_name . '=")' . $escaped_original . '("[^\]]*\])/';
            $replacement = '$1' . esc_attr($new_text) . '$2';
        }
        
        // Perform the replacement
        $new_content = preg_replace($pattern, $replacement, $content, 1); // Replace only first occurrence
        
        // Update the post content if it changed
        if ($new_content !== $content && $new_content !== null) {
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