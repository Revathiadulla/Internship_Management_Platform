<?php
/**
 * Local Email Configuration
 * 
 * Defines SMTP credentials for local XAMPP environments to send emails via PHPMailer.
 * This overrides BREVO_API_KEY requirements.
 */

if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', '587');
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', 'imp.webportal2026@gmail.com');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', 'GMAIL_APP_PASSWORD');
if (!defined('SMTP_FROM')) define('SMTP_FROM', 'imp.webportal2026@gmail.com');
if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'IMP Portal');
