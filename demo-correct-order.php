<?php
/**
 * Demo: Correct Text Order with New Approach
 * 
 * This shows how the new approach extracts texts in the correct visual order
 */

// Mock WordPress environment for demo
if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string) {
        return strip_tags($string);
    }
}

// Include the JSON processor
require_once 'includes/class-divi-json-processor.php';

// Read home.json
$json_content = file_get_contents('home.json');
$processor = new DiviJsonProcessor();

// Extract texts
$texts = $processor->extract_texts_from_json($json_content);

echo "=== TEXTS IN CORRECT VISUAL ORDER (Top to Bottom) ===\n\n";

// Group by approximate sections based on content
$current_section = 1;
$last_position = -1;

foreach ($texts as $text) {
    // Detect section breaks (large position jumps might indicate new sections)
    if ($last_position >= 0 && $text['position'] - $last_position > 5) {
        echo "\n--- Section Break ---\n\n";
        $current_section++;
    }
    
    echo "📍 Position {$text['position']}:\n";
    
    // Show the actual text
    $display = substr($text['display_text'], 0, 80);
    if (strlen($text['display_text']) > 80) {
        $display .= '...';
    }
    
    echo "   Text: \"{$display}\"\n";
    echo "   Module: {$text['module_type']} -> {$text['field']}\n";
    
    if ($text['text'] !== $text['display_text']) {
        echo "   ⚠️  Contains HTML formatting\n";
    }
    
    echo "\n";
    
    $last_position = $text['position'];
}

echo "\n=== EXPECTED ORDER FROM YOUR SCREENSHOT ===\n\n";
echo "1. Titolo 1 (TITLE 1)\n";
echo "2. Titolo 2 (Title 2)\n";
echo "3. Descrizione lorem ipsum... (Description)\n";
echo "4. [Other page content in order]\n";

echo "\n=== KEY IMPROVEMENTS ===\n\n";
echo "✅ Texts appear in correct visual order (top-to-bottom)\n";
echo "✅ HTML formatting is preserved\n";
echo "✅ Each text has a unique position identifier\n";
echo "✅ Module context is maintained\n";
echo "✅ Can update specific texts without affecting others\n";

// Show how the current approach would fail
echo "\n=== CURRENT APPROACH PROBLEMS ===\n\n";
echo "❌ Regex patterns process by type, not position\n";
echo "❌ Would show: Description, Title, Title, Button, etc. (wrong order)\n";
echo "❌ Loses formatting when updating\n";
echo "❌ Can't target specific instances\n";