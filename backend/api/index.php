<?php
require_once '../config/cors.php';

echo json_encode([
    'status' => 'success',
    'message' => 'Internship Management API',
    'version' => '1.0.0'
]);
?>
