<?php
function print_all_headers($url) {
    echo "Headers for: $url\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    if ($response === false) {
        echo "  Error: " . curl_error($ch) . "\n\n";
        return;
    }
    
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    echo $headers . "\n";
    curl_close($ch);
}

print_all_headers("https://res.cloudinary.com/dcibodnyh/raw/upload/v1780907742/aadhaar/RESUME.pdf");
