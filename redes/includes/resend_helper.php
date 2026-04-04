<?php
require_once __DIR__ . '/../config/resend.php';

/**
 * Sends an OTP email using Resend API
 * 
 * @param string $to The recipient email address
 * @param string $otp The 6-digit OTP code
 * @return array Response from Resend API
 */
function sendOTP($to, $otp) {
    if (RESEND_API_KEY === 're_your_api_key') {
        return ['error' => 'Resend API Key not configured.'];
    }

    $url = 'https://api.resend.com/emails';
    $data = [
        'from'    => RESEND_SENDER_NAME . ' <' . RESEND_SENDER_EMAIL . '>',
        'to'      => [$to],
        'subject' => 'Your Verification Code - ' . RESEND_SENDER_NAME,
        'html'    => "
            <div style='font-family: sans-serif; padding: 20px; color: #333;'>
                <h2>Verification Code</h2>
                <p>Hello,</p>
                <p>Your one-time password (OTP) for logging in to <strong>" . RESEND_SENDER_NAME . "</strong> is:</p>
                <div style='font-size: 32px; font-weight: bold; letter-spacing: 5px; padding: 15px; background: #f4f4f4; border-radius: 8px; display: inline-block; margin: 10px 0;'>
                    $otp
                </div>
                <p>This code will expire in 5 minutes.</p>
                <p>If you did not request this code, please ignore this email.</p>
                <hr style='border: none; border-top: 1px solid #eee; margin-top: 30px;'>
                <p style='font-size: 12px; color: #888;'>&copy; 2025 " . RESEND_SENDER_NAME . ". All rights reserved.</p>
            </div>
        "
    ];

    $payload = json_encode($data);
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . RESEND_API_KEY,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($error) {
        return ['error' => $error];
    }

    return [
        'success' => $httpCode === 200 || $httpCode === 201,
        'response' => json_decode($response, true),
        'http_code' => $httpCode
    ];
}
?>
