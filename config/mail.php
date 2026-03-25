<?php
// ============================================================
// Email / SMTP Configuration (Gmail)
// ============================================================
//
// SETUP STEPS:
// 1. Go to your Google Account -> Security
// 2. Enable "2-Step Verification" (required)
// 3. Go to Security -> App Passwords
// 4. Select app: "Mail", device: "Other", name it "evote"
// 5. Google gives you a 16-character password - paste it below
//
// ============================================================

define('MAIL_HOST',       'smtp.gmail.com');
define('MAIL_PORT',       587);
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_USERNAME',   'kavindupraneeth8@gmail.com');      // <-- Your Gmail address
define('MAIL_PASSWORD',   'gwax pzry wbkg kerd');       // <-- 16-char App Password
define('MAIL_FROM_EMAIL', 'kavindupraneeth8@gmail.com');      // <-- Same Gmail address
define('MAIL_FROM_NAME',  'Sri Lanka e-Vote System');
