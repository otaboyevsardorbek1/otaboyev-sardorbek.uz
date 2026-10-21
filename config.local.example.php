<?php
// Copy this file to config.local.php and fill in the hosting details.
// config.local.php is gitignored and must never be committed.

return array(
    // MySQL content database.
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'cpaneluser_portfolio',
    'user' => 'cpaneluser_portfolio',
    'pass' => 'change-me',
    'socket' => '',

    // Admin panel authentication.
    'admin' => array(
        'site_url' => 'https://otaboyev-sardorbek.uz',
        'app_env' => 'production',
        // Generate with: php -r "echo bin2hex(random_bytes(32));"
        'app_secret' => 'change-to-a-64-character-random-secret',
        'telegram_bot_token' => '123456789:replace-with-botfather-token',
        'telegram_bot_username' => 'YourAdminBot',
        'telegram_id' => '123456789',
        'email' => 'admin@otaboyev-sardorbek.uz',
        'session_ttl' => 43200,
        'challenge_ttl' => 600,
        'otp_max_attempts' => 5,
        'telegram_auth_window' => 300,
        'trust_proxy' => false,
    ),

    // Email delivery: mail, smtp, resend or brevo.
    'mail' => array(
        'driver' => 'mail',
        'from' => 'no-reply@otaboyev-sardorbek.uz',
        'from_name' => 'Sardorbek Otaboyev',
        'smtp_host' => 'localhost',
        'smtp_port' => 587,
        'smtp_secure' => 'tls',
        'smtp_user' => '',
        'smtp_pass' => '',
        'resend_api_key' => '',
        'brevo_api_key' => '',
        'log_file' => '',
    ),
);
