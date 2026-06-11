<?php
$test_users = [
    'student'     => 'revathiadulla@gmail.com',
    'hr'          => 'revathiadulla24@gmail.com',
    'coordinator' => 'jaya@gmail.com',
    'mentor'      => 'mentor.rajesh@example.com',
    'admin'       => 'imp.webportal2026@gmail.com'
];

foreach ($test_users as $role => $email) {
    echo "Testing login for role: $role ($email)...\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/IMP/login.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'email' => $email,
        'password' => 'password123'
    ]));
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    // Parse redirect location
    $redirect = '';
    if (preg_match('/^Location:\s*(.*?)$/mi', $response, $matches)) {
        $redirect = trim($matches[1]);
    }
    
    echo "  HTTP Code: {$info['http_code']}\n";
    echo "  Redirect Location: $redirect\n";
    
    $expected_dashboard = "{$role}_dashboard.php";
    if (strpos($redirect, $expected_dashboard) !== false) {
        echo "  [SUCCESS]: Redirected to expected dashboard ($expected_dashboard)\n\n";
    } else {
        echo "  [FAILED]: Expected redirect to $expected_dashboard, got: $redirect\n";
        // Show first 200 chars of body if error
        $body = substr($response, $info['header_size']);
        echo "  Body excerpt: " . substr(strip_tags($body), 0, 150) . "...\n\n";
    }
}
