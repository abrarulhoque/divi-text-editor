<?php
/**
 * Divi JSON Layout Processor
 * 
 * This class works with Divi's JSON export format to extract and update text
 * while preserving the exact structure, formatting, and order.
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class DiviJsonProcessor {
    
    /**
     * Process a Divi JSON export file or data
     * 
     * @param mixed $json_data JSON string or decoded array
     * @return array Extracted text items in order
     */
    public function extract_texts_from_json($json_data) {
        // Decode if string
        if (is_string($json_data)) {
            $data = json_decode($json_data, true);
        } else {
            $data = $json_data;
        }
        
        $texts = [];
        $position = 0;
        
        // Process each layout in the data
        if (isset($data['data'])) {
            foreach ($data['data'] as $post_id => $content) {
                $texts = array_merge($texts, $this->extract_from_content($content, $position));
            }
        }
        
        return $texts;
    }
    
    /**
     * Extract texts from shortcode content
     */
    private function extract_from_content($content, &$position) {
        $texts = [];
        
        // Parse shortcodes hierarchically
        $sections = $this->parse_sections($content);
        
        foreach ($sections as $section) {
            // Process section
            $texts = array_merge($texts, $this->process_section($section, $position));
            
            // Process rows within section
            if (isset($section['rows'])) {
                foreach ($section['rows'] as $row) {
                    $texts = array_merge($texts, $this->process_row($row, $position));
                    
                    // Process columns within row
                    if (isset($row['columns'])) {
                        foreach ($row['columns'] as $column) {
                            $texts = array_merge($texts, $this->process_column($column, $position));
                            
                            // Process modules within column
                            if (isset($column['modules'])) {
                                foreach ($column['modules'] as $module) {
                                    $texts = array_merge($texts, $this->process_module($module, $position));
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $texts;
    }
    
    /**
     * Parse sections from content
     */
    private function parse_sections($content) {
        $sections = [];
        
        // Match all et_pb_section shortcodes
        preg_match_all('/\[et_pb_section([^\]]*)\](.*?)\[\/et_pb_section\]/s', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $section = [
                'type' => 'section',
                'attributes' => $this->parse_attributes($match[1]),
                'content' => $match[2],
                'full_match' => $match[0],
                'rows' => $this->parse_rows($match[2])
            ];
            $sections[] = $section;
        }
        
        return $sections;
    }
    
    /**
     * Parse rows from section content
     */
    private function parse_rows($content) {
        $rows = [];
        
        preg_match_all('/\[et_pb_row([^\]]*)\](.*?)\[\/et_pb_row\]/s', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $row = [
                'type' => 'row',
                'attributes' => $this->parse_attributes($match[1]),
                'content' => $match[2],
                'full_match' => $match[0],
                'columns' => $this->parse_columns($match[2])
            ];
            $rows[] = $row;
        }
        
        return $rows;
    }
    
    /**
     * Parse columns from row content
     */
    private function parse_columns($content) {
        $columns = [];
        
        preg_match_all('/\[et_pb_column([^\]]*)\](.*?)\[\/et_pb_column\]/s', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $column = [
                'type' => 'column',
                'attributes' => $this->parse_attributes($match[1]),
                'content' => $match[2],
                'full_match' => $match[0],
                'modules' => $this->parse_modules($match[2])
            ];
            $columns[] = $column;
        }
        
        return $columns;
    }
    
    /**
     * Parse modules from column content
     */
    private function parse_modules($content) {
        $modules = [];
        
        // Match all module shortcodes
        $pattern = '/\[([a-z_]+)([^\]]*?)\](?:(.*?)\[\/\1\])?/s';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $module = [
                'type' => $match[1],
                'attributes' => $this->parse_attributes($match[2]),
                'content' => isset($match[3]) ? $match[3] : '',
                'full_match' => $match[0]
            ];
            $modules[] = $module;
        }
        
        return $modules;
    }
    
    /**
     * Process a module and extract text
     */
    private function process_module($module, &$position) {
        $texts = [];
        $module_type = $module['type'];
        $attributes = $module['attributes'];
        $content = $module['content'];
        
        // Extract based on module type
        switch ($module_type) {
            case 'et_pb_text':
                if (!empty($content)) {
                    $texts[] = [
                        'position' => $position++,
                        'module_type' => $module_type,
                        'field' => 'content',
                        'text' => $content,
                        'path' => 'content',
                        'display_text' => wp_strip_all_tags($content)
                    ];
                }
                break;
                
            case 'dipi_typing_text':
                if (!empty($attributes['typing_text'])) {
                    $texts[] = [
                        'position' => $position++,
                        'module_type' => $module_type,
                        'field' => 'typing_text',
                        'text' => $attributes['typing_text'],
                        'path' => 'attributes.typing_text',
                        'display_text' => wp_strip_all_tags($attributes['typing_text'])
                    ];
                }
                break;
                
            case 'dipi_expanding_cta':
                if (!empty($attributes['content_title'])) {
                    $texts[] = [
                        'position' => $position++,
                        'module_type' => $module_type,
                        'field' => 'content_title',
                        'text' => $attributes['content_title'],
                        'path' => 'attributes.content_title',
                        'display_text' => wp_strip_all_tags($attributes['content_title'])
                    ];
                }
                if (!empty($attributes['content_description'])) {
                    $texts[] = [
                        'position' => $position++,
                        'module_type' => $module_type,
                        'field' => 'content_description',
                        'text' => $attributes['content_description'],
                        'path' => 'attributes.content_description',
                        'display_text' => wp_strip_all_tags($attributes['content_description'])
                    ];
                }
                if (!empty($attributes['content_button_text'])) {
                    $texts[] = [
                        'position' => $position++,
                        'module_type' => $module_type,
                        'field' => 'content_button_text',
                        'text' => $attributes['content_button_text'],
                        'path' => 'attributes.content_button_text',
                        'display_text' => wp_strip_all_tags($attributes['content_button_text'])
                    ];
                }
                break;
                
            case 'et_pb_button':
                if (!empty($attributes['button_text'])) {
                    $texts[] = [
                        'position' => $position++,
                        'module_type' => $module_type,
                        'field' => 'button_text',
                        'text' => $attributes['button_text'],
                        'path' => 'attributes.button_text',
                        'display_text' => wp_strip_all_tags($attributes['button_text'])
                    ];
                }
                break;
                
            case 'dipi_hover_box':
                if (!empty($attributes['content_hover_title'])) {
                    $texts[] = [
                        'position' => $position++,
                        'module_type' => $module_type,
                        'field' => 'content_hover_title',
                        'text' => $attributes['content_hover_title'],
                        'path' => 'attributes.content_hover_title',
                        'display_text' => wp_strip_all_tags($attributes['content_hover_title'])
                    ];
                }
                if (!empty($attributes['content_hover_content'])) {
                    $texts[] = [
                        'position' => $position++,
                        'module_type' => $module_type,
                        'field' => 'content_hover_content',
                        'text' => $attributes['content_hover_content'],
                        'path' => 'attributes.content_hover_content',
                        'display_text' => wp_strip_all_tags($attributes['content_hover_content'])
                    ];
                }
                break;
        }
        
        return $texts;
    }
    
    /**
     * Process section, row, column (for future extensibility)
     */
    private function process_section($section, &$position) {
        return [];
    }
    
    private function process_row($row, &$position) {
        return [];
    }
    
    private function process_column($column, &$position) {
        return [];
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
     * Update JSON data with new text values
     * 
     * @param mixed $json_data Original JSON data
     * @param array $updates Array of updates [position => new_text]
     * @return string Updated JSON
     */
    public function update_json_with_texts($json_data, $updates) {
        // Decode if string
        if (is_string($json_data)) {
            $data = json_decode($json_data, true);
        } else {
            $data = $json_data;
        }
        
        // Extract current texts to build a mapping
        $current_texts = $this->extract_texts_from_json($data);
        
        // Create a mapping of position to text item
        $position_map = [];
        foreach ($current_texts as $text_item) {
            $position_map[$text_item['position']] = $text_item;
        }
        
        // Apply updates
        if (isset($data['data'])) {
            foreach ($data['data'] as $post_id => &$content) {
                foreach ($updates as $position => $new_text) {
                    if (isset($position_map[$position])) {
                        $text_item = $position_map[$position];
                        $content = $this->update_text_in_content($content, $text_item, $new_text);
                    }
                }
            }
        }
        
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Update specific text in content
     */
    private function update_text_in_content($content, $text_item, $new_text) {
        // This is a simplified version
        // In production, you'd need to parse and rebuild the shortcode
        // while preserving all attributes and structure
        
        $module_type = $text_item['module_type'];
        $field = $text_item['field'];
        $old_text = $text_item['text'];
        
        // For content fields (between opening and closing tags)
        if ($field === 'content') {
            $pattern = '/(\[' . preg_quote($module_type) . '[^\]]*\])' . preg_quote($old_text, '/') . '(\[\/' . preg_quote($module_type) . '\])/';
            $content = preg_replace($pattern, '$1' . $new_text . '$2', $content);
        } else {
            // For attribute fields
            $pattern = '/(' . preg_quote($field) . '=")' . preg_quote($old_text, '/') . '(")/';
            $content = preg_replace($pattern, '$1' . htmlspecialchars($new_text, ENT_QUOTES) . '$2', $content);
        }
        
        return $content;
    }
}