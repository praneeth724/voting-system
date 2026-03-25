<?php
// ============================================================
// Email Sending Helper (PHPMailer + Gmail SMTP)
// ============================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    error_log('PHPMailer not installed. Run: composer require phpmailer/phpmailer');
}
require_once $autoload;
require_once __DIR__ . '/../config/mail.php';

/**
 * Send OTP email to voter.
 * @param string $to_email  Recipient email
 * @param string $to_name   Recipient full name
 * @param string $otp       6-digit OTP code
 * @param string $purpose   'login' or 'vote_confirm'
 * @return bool             true on success, false on failure
 */
function sendOTPEmail(string $to_email, string $to_name, string $otp, string $purpose): bool
{
    $mail = new PHPMailer(true);

    try {
        // ---- SMTP Settings ----
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        // ---- Sender / Recipient ----
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $to_name);

        // ---- Content ----
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        if ($purpose === 'login') {
            $mail->Subject  = 'Your Login OTP - Sri Lanka e-Vote';
            $purpose_label  = 'complete your login';
        } else {
            $mail->Subject  = 'Vote Confirmation OTP - Sri Lanka e-Vote';
            $purpose_label  = 'confirm your vote';
        }

        $mail->Body    = buildOTPEmailHTML($to_name, $otp, $purpose_label);
        $mail->AltBody = "Hello {$to_name},\n\nYour OTP to {$purpose_label} is: {$otp}\n\nThis code expires in 10 minutes.\nDo NOT share this code with anyone.\n\n-- Sri Lanka e-Vote System";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Mailer Error [' . $to_email . ']: ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Build the HTML email body for OTP.
 */
function buildOTPEmailHTML(string $name, string $otp, string $purpose_label): string
{
    $year = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>OTP Verification</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f6f9;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 20px;">
    <tr>
      <td align="center">
        <table width="560" cellpadding="0" cellspacing="0"
               style="background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.10);max-width:560px;">

          <!-- Header -->
          <tr>
            <td style="background:linear-gradient(135deg,#5c0000,#8B0000);padding:36px 40px;text-align:center;">
              <div style="width:60px;height:60px;background:rgba(255,255,255,0.15);border-radius:50%;
                          display:inline-flex;align-items:center;justify-content:center;
                          font-size:28px;margin-bottom:14px;line-height:60px;">
                &#x1F5F3;
              </div>
              <h1 style="color:#ffffff;font-size:1.4rem;margin:0 0 6px;font-weight:700;">
                Sri Lanka e-Vote
              </h1>
              <p style="color:rgba(255,255,255,0.7);font-size:0.82rem;margin:0;">
                Democratic Republic of Sri Lanka &mdash; Election Commission
              </p>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:40px;text-align:center;">
              <p style="color:#212121;font-size:1rem;margin:0 0 6px;">
                Hello, <strong>{$name}</strong>!
              </p>
              <p style="color:#757575;font-size:0.9rem;margin:0 0 30px;">
                Use the code below to <strong>{$purpose_label}</strong>.
              </p>

              <!-- OTP Code Box -->
              <div style="background:#fff8e1;border:2px dashed #C8960C;border-radius:12px;
                          padding:28px 20px;margin:0 auto 28px;max-width:320px;">
                <p style="color:#9e9e9e;font-size:0.72rem;text-transform:uppercase;
                           letter-spacing:2px;margin:0 0 12px;">
                  One-Time Password (OTP)
                </p>
                <div style="font-size:2.8rem;font-weight:800;letter-spacing:16px;
                             color:#8B0000;font-family:'Courier New',Courier,monospace;
                             line-height:1;">
                  {$otp}
                </div>
              </div>

              <!-- Expiry Notice -->
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
                <tr>
                  <td style="background:#fff3e0;border-left:4px solid #f57f17;
                              border-radius:6px;padding:12px 16px;text-align:left;">
                    <p style="color:#e65100;font-size:0.84rem;margin:0;">
                      &#x23F1; This OTP expires in <strong>10 minutes</strong>.
                    </p>
                  </td>
                </tr>
              </table>

              <!-- Security Notice -->
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="background:#ffebee;border-left:4px solid #c62828;
                              border-radius:6px;padding:12px 16px;text-align:left;">
                    <p style="color:#b71c1c;font-size:0.84rem;margin:0;">
                      &#x1F512; <strong>Security Notice:</strong> Never share this code with anyone.
                      The Election Commission will never call or email asking for your OTP.
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Divider -->
          <tr>
            <td style="padding:0 40px;">
              <div style="height:1px;background:#eeeeee;"></div>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:20px 40px;text-align:center;background:#fafafa;">
              <p style="color:#bdbdbd;font-size:0.75rem;margin:0 0 4px;">
                If you did not request this code, please ignore this email.
              </p>
              <p style="color:#bdbdbd;font-size:0.75rem;margin:0;">
                &copy; {$year} Sri Lanka Online Voting System. All rights reserved.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}
