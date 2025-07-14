<?php
/**
 * Divi Layout Integration
 * 
 * This class provides WordPress integration for the new layout-based approach
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class DiviLayoutIntegration {
    
    /**
     * Instance of the JSON processor
     */
    private $json_processor;
    
    /**
     * Instance of the layout scanner
     */
    private $layout_scanner;
    
    /**
     * Constructor
     */
    public function __construct() {
        require_once plugin_dir_path(__FILE__) . 'class-divi-json-processor.php';
        require_once plugin_dir_path(__FILE__) . 'class-divi-layout-scanner.php';
        
        $this->json_processor = new DiviJsonProcessor();
        $this->layout_scanner = new DiviLayoutScanner();
    }
    
    /**
     * Scan all pages and posts for Divi content
     * 
     * @param array $post_types Post types to scan (default: page only)
     * @return array Scanned items organized by page
     */
    public function scan_all_layouts($post_types = ['page']) {
        $all_texts = [];
        
        // Query for posts
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
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                // Skip if it's a blog post (article)
                if (get_post_type($post_id) === 'post') {
                    continue;
                }
                
                // Use the layout scanner for direct scanning
                $page_texts = $this->layout_scanner->scan_layout($post_id);
                
                if (!empty($page_texts)) {
                    $all_texts[$post_id] = [
                        'post_title' => get_the_title(),
                        'post_type' => get_post_type($post_id),
                        'permalink' => get_permalink($post_id),
                        'texts' => $page_texts
                    ];
                }
            }
        }
        
        wp_reset_postdata();
        
        return $all_texts;
    }
    
    /**
     * Export a page layout as JSON (like Divi's export)
     * 
     * @param int $post_id Post ID
     * @return array Export data
     */
    public function export_layout($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }
        
        // Create export format similar to Divi's
        $export_data = [
            'context' => 'et_builder',
            'data' => [
                $post_id => $post->post_content
            ],
            'post_title' => $post->post_title,
            'post_type' => $post->post_type
        ];
        
        return $export_data;
    }
    
    /**
     * Process layout and extract texts using JSON approach
     * 
     * @param int $post_id Post ID
     * @return array Extracted texts
     */
    public function extract_texts_from_post($post_id) {
        // Export the layout
        $export_data = $this->export_layout($post_id);
        
        if (!$export_data) {
            return [];
        }
        
        // Process with JSON processor
        $texts = $this->json_processor->extract_texts_from_json($export_data);
        
        // Add post metadata to each text item
        foreach ($texts as &$text) {
            $text['post_id'] = $post_id;
            $text['post_title'] = $export_data['post_title'];
            $text['post_type'] = $export_data['post_type'];
        }
        
        return $texts;
    }
    
    /**
     * Update texts in a post
     * 
     * @param int $post_id Post ID
     * @param array $updates Position => new text mapping
     * @return bool Success
     */
    public function update_post_texts($post_id, $updates) {
        // Export current layout
        $export_data = $this->export_layout($post_id);
        
        if (!$export_data) {
            return false;
        }
        
        // Update using JSON processor
        $updated_json = $this->json_processor->update_json_with_texts($export_data, $updates);
        $updated_data = json_decode($updated_json, true);
        
        // Extract the updated content
        if (isset($updated_data['data'][$post_id])) {
            $new_content = $updated_data['data'][$post_id];
            
            // Update the post
            $result = wp_update_post([
                'ID' => $post_id,
                'post_content' => $new_content
            ]);
            
            return !is_wp_error($result);
        }
        
        return false;
    }
    
    /**
     * Get texts for display in the editor interface
     * 
     * @return array Formatted for display
     */
    public function get_texts_for_editor() {
        $all_layouts = $this->scan_all_layouts();
        $editor_data = [];
        
        foreach ($all_layouts as $post_id => $layout_data) {
            foreach ($layout_data['texts'] as $text) {
                $editor_data[] = [
                    'id' => $post_id . '_' . $text['position'],
                    'post_id' => $post_id,
                    'position' => $text['position'],
                    'page_title' => $layout_data['post_title'],
                    'module_type' => $text['module_type'],
                    'field_type' => $text['field_type'],
                    'original_text' => $text['original_text'],
                    'display_text' => $text['display_text'],
                    'has_html' => $text['has_html'],
                    'permalink' => $layout_data['permalink']
                ];
            }
        }
        
        return $editor_data;
    }
    
    /**
     * Save bulk updates from the editor
     * 
     * @param array $updates Array of updates grouped by post_id
     * @return array Results
     */
    public function save_bulk_updates($updates) {
        $results = [];
        
        foreach ($updates as $post_id => $post_updates) {
            $success = $this->update_post_texts($post_id, $post_updates);
            $results[$post_id] = $success;
        }
        
        return $results;
    }
}