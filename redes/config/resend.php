<?php
// Resend API Configuration via environment variables
require_once __DIR__ . '/env_loader.php';

define('RESEND_API_KEY',    getenv('RESEND_API_KEY')    ?: 're_your_api_key');
define('RESEND_SENDER_EMAIL', getenv('RESEND_SENDER_EMAIL') ?: 'onboarding@resend.dev');
define('RESEND_SENDER_NAME',  getenv('RESEND_SENDER_NAME')  ?: 'ClassSync');
?>
