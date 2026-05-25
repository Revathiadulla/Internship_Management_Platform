<?php
/**
 * sms_helper.php
 * Centralized SMS sending engine with REST API integration and log fallback on failure.
 */

if (!function_exists('sendSMS')) {
    /**
     * Sends an SMS message using a REST API (Spring Edge).
     * Falls back to logging to sms_notifications.log if sending fails or keys are not configured.
     *
     * @param string $toPhone   Recipient's phone number
     * @param string $message   Message text to be sent
     * @return bool             True on success, false on failure (logs to fallback file)
     */
    function sendSMS($toPhone, $message) {
        $apiKey = getenv("SMS_API_KEY");
        $senderId = getenv("SMS_SENDER_ID");

        // Normalize inputs
        $apiKey = trim($apiKey ?: '');
        $senderId = trim($senderId ?: '');
        $toPhone = trim($toPhone ?: '');

        // Define helper function to write to the fallback log
        $writeFallback = function($reason) use ($toPhone, $message) {
            $logFile = __DIR__ . "/../sms_notifications.log";
            $logMsg = "========================================================================\n" .
                      "[" . date('Y-m-d H:i:s') . "] OUTGOING SMS - STATUS: Failed (Falling back to log)\n" .
                      "TO: " . $toPhone . "\n" .
                      "MESSAGE: " . $message . "\n" .
                      "REASON: " . $reason . "\n" .
                      "========================================================================\n\n";
            @file_put_contents($logFile, $logMsg, FILE_APPEND);
        };

        // 1. Check if environment variables are configured
        $isPlaceholder = (
            empty($apiKey) || 
            empty($senderId) || 
            stripos($apiKey, 'your_') !== false || 
            stripos($senderId, 'your_') !== false ||
            stripos($apiKey, 'api_key') !== false ||
            stripos($senderId, 'sender_id') !== false
        );

        if ($isPlaceholder) {
            $writeFallback("SMS credentials (SMS_API_KEY or SMS_SENDER_ID) are missing or set to placeholder values in .env");
            return false;
        }

        // 2. Prepare payload and call the REST API
        $url = 'https://api.springedge.com/v1/sms/send';
        $payload = json_encode([
            'to' => $toPhone,
            'sender_id' => $senderId,
            'message' => $message,
            'type' => 'transactional'
        ]);

        $ch = curl_init($url);
        if (!$ch) {
            $writeFallback("Failed to initialize cURL context.");
            return false;
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // Execute API request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // 3. Handle response and fallback if non-success
        if ($curlError) {
            $writeFallback("cURL Request Error: " . $curlError);
            return false;
        }

        if ($httpCode !== 200) {
            $writeFallback("HTTP Server Error Code: " . $httpCode . ". Response: " . ($response ?: 'Empty Response'));
            return false;
        }

        // API responded with 200. Check response payload structure if relevant,
        // but typically a 200 HTTP code indicates successful transmission to gateway.
        return true;
    }
}
