<?php
function download_body($url) {
    echo "Downloading body for: $url\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    if ($response === false) {
        echo "  Error: " . curl_error($ch) . "\n\n";
        return;
    }
    
    echo "  Response Length: " . strlen($response) . " bytes\n";
    echo "  First 500 chars:\n" . substr($response, 0, 500) . "\n\n";
    curl_close($ch);
}

download_body("https://res.cloudinary.com/dcibodnyh/image/upload/v1780887014/aadhaar/wmvs7clm71r3xa0cgfey.pdf");
download_body("https://res.cloudinary.com/dcibodnyh/raw/upload/v1780907742/aadhaar/RESUME.pdf");
