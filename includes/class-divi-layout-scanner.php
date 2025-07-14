<?php
/**
 * Divi Layout Scanner - Hierarchical approach
 * 
 * This class provides a better way to scan and update Divi layouts
 * by parsing the shortcode structure hierarchically, maintaining
 * proper order and preserving formatting.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class DiviLayoutScanner {
    
    /**
     * Stores scanned text items with their positions
     */
    private $scanned_items = [];
    
    /**
     * Counter for maintaining order
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
     * Parse a Divi layout and extract text in hierarchical order
     * 
     * @param int $post_id The post ID to scan
     * @return array Array of text items in proper order
     */
    public function scan_layout($post_id) {
        // Reset for new scan
        $this->scanned_items = [];
        $this->position_counter = 0;
        
        // Get post content
        $post = get_post($post_id);
        if (!$post || empty($post->post_content)) {
            return [];
        }
        
        // Check if it's a Divi layout
        if (strpos($post->post_content, '[et_pb_') === false) {
            return [];
        }
        
        // Parse the shortcode structure
        $this->parse_content($post->post_content, $post_id, $post->post_type, $post->post_title);
        
        // Sort by position to ensure proper order
        usort($this->scanned_items, function($a, $b) {
            return $a['position'] - $b['position'];
        });
        
        return $this->scanned_items;
    }
    
    /**
     * Parse content recursively
     */
    private function parse_content($content, $post_id, $post_type, $post_title) {
        // Parse all shortcodes in the content
        $pattern = '/\[(\w+)([^\]]*)\](.*?)(\[\/\1\])/s';
        
        // Use callback to process each shortcode
        preg_replace_callback($pattern, function($matches) use ($post_id, $post_type, $post_title) {
            $tag = $matches[1];
            $attributes = $matches[2];
            $inner_content = $matches[3];
            
            // Process this module if it's a text module
            if (in_array($tag, $this->text_modules)) {
                $this->extract_module_text($tag, $attributes, $inner_content, $post_id, $post_type, $post_title);
            }
            
            // Recursively parse inner content
            if (!empty($inner_content)) {
                $this->parse_content($inner_content, $post_id, $post_type, $post_title);
            }
            
            return $matches[0];
        }, $content);
        
        // Also handle self-closing shortcodes
        $pattern_self_closing = '/\[(\w+)([^\]]*?)\/\]/';
        preg_replace_callback($pattern_self_closing, function($matches) use ($post_id, $post_type, $post_title) {
            $tag = $matches[1];
            $attributes = $matches[2];
            
            if (in_array($tag, $this->text_modules)) {
                $this->extract_module_text($tag, $attributes, '', $post_id, $post_type, $post_title);
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
                if (!empty($content)) {
                    $this->add_text_item($content, 'text_content', $module_type, $post_id, $post_type, $post_title);
                }
                break;
                
            case 'et_pb_blurb':
                if (!empty($attributes['title'])) {
                    $this->add_text_item($attributes['title'], 'blurb_title', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($content)) {
                    $this->add_text_item($content, 'blurb_content', $module_type, $post_id, $post_type, $post_title);
                }
                break;
                
            case 'et_pb_button':
                if (!empty($attributes['button_text'])) {
                    $this->add_text_item($attributes['button_text'], 'button_text', $module_type, $post_id, $post_type, $post_title);
                }
                break;
                
            case 'et_pb_fullwidth_header':
                if (!empty($attributes['title'])) {
                    $this->add_text_item($attributes['title'], 'header_title', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($attributes['subhead'])) {
                    $this->add_text_item($attributes['subhead'], 'header_subhead', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($content)) {
                    $this->add_text_item($content, 'header_content', $module_type, $post_id, $post_type, $post_title);
                }
                break;
                
            case 'et_pb_cta':
                if (!empty($attributes['title'])) {
                    $this->add_text_item($attributes['title'], 'cta_title', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($content)) {
                    $this->add_text_item($content, 'cta_content', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($attributes['button_text'])) {
                    $this->add_text_item($attributes['button_text'], 'cta_button', $module_type, $post_id, $post_type, $post_title);
                }
                break;
                
            case 'dipi_typing_text':
                if (!empty($attributes['typing_text'])) {
                    $this->add_text_item($attributes['typing_text'], 'typing_text', $module_type, $post_id, $post_type, $post_title);
                }
                break;
                
            case 'dipi_expanding_cta':
                if (!empty($attributes['content_title'])) {
                    $this->add_text_item($attributes['content_title'], 'expanding_title', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($attributes['content_description'])) {
                    $this->add_text_item($attributes['content_description'], 'expanding_description', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($attributes['content_button_text'])) {
                    $this->add_text_item($attributes['content_button_text'], 'expanding_button', $module_type, $post_id, $post_type, $post_title);
                }
                break;
                
            case 'dipi_hover_box':
                if (!empty($attributes['content_hover_title'])) {
                    $this->add_text_item($attributes['content_hover_title'], 'hover_title', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($attributes['content_hover_content'])) {
                    $this->add_text_item($attributes['content_hover_content'], 'hover_content', $module_type, $post_id, $post_type, $post_title);
                }
                if (!empty($attributes['content_hover_button_text'])) {
                    $this->add_text_item($attributes['content_hover_button_text'], 'hover_button', $module_type, $post_id, $post_type, $post_title);
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
        
        // Skip dynamic content
        if (preg_match('/%[\w_]+%/', $text) || strpos($text, '@ET-DC@') !== false) {
            return;
        }
        
        // Store with position for proper ordering
        $this->scanned_items[] = [
            'position' => $this->position_counter++,
            'post_id' => $post_id,
            'post_type' => $post_type,
            'post_title' => $post_title,
            'module_type' => $module_type,
            'field_type' => $field_type,
            'original_text' => $text,
            'display_text' => wp_strip_all_tags($text),
            'has_html' => ($text !== wp_strip_all_tags($text))
        ];
    }
    
    /**
     * Update layout with new text values
     * 
     * @param int $post_id Post ID
     * @param array $updates Array of updates with position => new_text
     * @return bool Success status
     */
    public function update_layout($post_id, $updates) {
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }
        
        // Get current content
        $content = $post->post_content;
        
        // Apply updates based on position
        // This is a simplified version - in production, you'd want to
        // track exact positions during scanning and update accordingly
        
        // For now, let's update the post content
        $updated_content = $this->apply_updates_to_content($content, $updates);
        
        // Update the post
        $result = wp_update_post([
            'ID' => $post_id,
            'post_content' => $updated_content
        ]);
        
        return !is_wp_error($result);
    }
    
    /**
     * Apply updates to content (simplified version)
     */
    private function apply_updates_to_content($content, $updates) {
        // This is where you would implement the actual update logic
        // For now, returning original content
        return $content;
    }
}