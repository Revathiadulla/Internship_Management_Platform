<?php
$dir = new RecursiveDirectoryIterator("c:/xampp/htdocs/IMP");
$iterator = new RecursiveIteratorIterator($dir);
$regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

foreach ($regex as $file) {
    $path = $file[0];
    if (strpos($path, 'vendor') !== false || strpos($path, 'PHPMailer') !== false || strpos($path, 'scratch') !== false) {
        continue;
    }
    $content = file_get_contents($path);
    if (strpos($content, 'applied_subtype') !== false) {
        echo "Found in: $path\n";
        // print matching lines
        $lines = explode("\n", $content);
        foreach ($lines as $i => $line) {
            if (strpos($line, 'applied_subtype') !== false) {
                echo "  Line " . ($i + 1) . ": " . trim($line) . "\n";
            }
        }
    }
}
?>
