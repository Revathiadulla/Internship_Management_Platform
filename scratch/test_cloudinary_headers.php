<?php

function check_url_headers($url) {
    echo "Requesting URL: $url\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    if ($response === false) {
        echo "  Error: " . curl_error($ch) . "\n\n";
        return;
    }
    
    $info = curl_getinfo($ch);
    echo "  HTTP Code: " . $info['http_code'] . "\n";
    echo "  Content-Type: " . $info['content_type'] . "\n";
    echo "  Download Size: " . $info['download_content_length'] . " bytes\n\n";
    curl_close($ch);
}

$pdf_url = "https://res.cloudinary.com/dcibodnyh/image/upload/v1780887014/aadhaar/wmvs7clm71r3xa0cgfey.pdf";
$png_url = "https://res.cloudinary.com/dcibodnyh/image/upload/v1780887014/aadhaar/wmvs7clm71r3xa0cgfey.png";

check_url_headers($pdf_url);
check_url_headers($png_url);
