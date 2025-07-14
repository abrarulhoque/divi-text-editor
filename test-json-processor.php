<?php
/**
 * Test script for Divi JSON Processor
 * 
 * This demonstrates how the new approach would work with your home.json file
 */

// Include the processor class
require_once 'includes/class-divi-json-processor.php';

// Read the home.json file
$json_content = file_get_contents('home.json');

// Create processor instance
$processor = new DiviJsonProcessor();

// Extract texts in proper order
echo "Extracting texts from home.json in visual order:\n";
echo "================================================\n\n";

$texts = $processor->extract_texts_from_json($json_content);

// Display extracted texts in order
foreach ($texts as $index => $text_item) {
    echo "Position: {$text_item['position']}\n";
    echo "Module: {$text_item['module_type']}\n";
    echo "Field: {$text_item['field']}\n";
    echo "Text: " . substr($text_item['display_text'], 0, 100) . "...\n";
    echo "Has HTML: " . (($text_item['text'] !== $text_item['display_text']) ? 'Yes' : 'No') . "\n";
    echo "---\n\n";
}

// Example of updating texts
echo "\n\nExample of updating texts:\n";
echo "=========================\n\n";

// Let's say we want to update position 1 and 2
$updates = [
    1 => 'Updated Title 2 with new text',
    2 => '<p>Updated description with HTML formatting preserved</p>'
];

// Update the JSON
$updated_json = $processor->update_json_with_texts($json_content, $updates);

// Show a snippet of the updated JSON
echo "Updated JSON (first 500 chars):\n";
echo substr($updated_json, 0, 500) . "...\n";

// Comparison with current approach
echo "\n\nComparison with current regex-based approach:\n";
echo "============================================\n\n";

echo "Current approach problems:\n";
echo "1. Scans by pattern type, not position - wrong order\n";
echo "2. Loses HTML formatting when updating\n";
echo "3. Can't distinguish between different instances of same module type\n";
echo "4. Doesn't respect visual hierarchy\n\n";

echo "New JSON-based approach benefits:\n";
echo "1. Maintains exact visual order (top-to-bottom, left-to-right)\n";
echo "2. Preserves all HTML formatting and attributes\n";
echo "3. Can target specific modules precisely\n";
echo "4. Works with Divi's import/export system\n";
echo "5. Supports all module types including custom modules\n";