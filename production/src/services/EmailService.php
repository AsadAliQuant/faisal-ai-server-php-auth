<?php
require_once __DIR__ . '/../../vendor/phpmailer/Exception.php';
require_once __DIR__ . '/../../vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
  public static function sendPasswordChanged(string $to, string $name, string $ip, string $location): void {
    $cfg  = app_config()['smtp'];
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = $cfg['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $cfg['username'];
    $mail->Password   = $cfg['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = (int)$cfg['port'];

    $mail->setFrom($cfg['from'], $cfg['from_name']);
    $mail->addAddress($to, $name ?: $to);

    $time = gmdate('d M Y, H:i') . ' UTC';

    $mail->isHTML(true);
    $mail->Subject = 'Your Password Has Been Changed';
    $mail->Body    = self::passwordChangedHtml($name ?: $to, $time, $ip, $location);
    $mail->AltBody = "Your password was successfully changed on $time.\nApproximate location: $location (IP: $ip)\nIf you did not do this, contact support immediately.";

    $mail->send();
  }

  public static function sendOtp(string $to, string $name, string $otp): void {
    $cfg = app_config()['smtp'];
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = $cfg['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $cfg['username'];
    $mail->Password   = $cfg['password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = (int)$cfg['port'];

    $mail->setFrom($cfg['from'], $cfg['from_name']);
    $mail->addAddress($to, $name ?: $to);

    $mail->isHTML(true);
    $mail->Subject = 'Your Password Reset OTP';
    $mail->Body    = self::otpEmailHtml($name ?: $to, $otp);
    $mail->AltBody = "Your password reset OTP is: $otp\nThis code expires in 10 minutes.";

    $mail->send();
  }

  private static function passwordChangedHtml(string $name, string $time, string $ip, string $location): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:40px 0;">
    <tr><td align="center">
      <table width="480" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.08);">
        <tr><td style="background:#18181b;padding:24px 32px;">
          <p style="margin:0;color:#ffffff;font-size:18px;font-weight:bold;">AI Document Analyzer</p>
        </td></tr>
        <tr><td style="padding:32px;">
          <p style="margin:0 0 8px;font-size:16px;color:#18181b;font-weight:600;">Password Changed Successfully</p>
          <p style="margin:0 0 24px;font-size:14px;color:#71717a;">Hi {$name}, your account password was just changed. Here are the details:</p>
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;border-radius:6px;padding:0;margin-bottom:24px;">
            <tr>
              <td style="padding:12px 16px;font-size:13px;color:#71717a;width:40%;">Time</td>
              <td style="padding:12px 16px;font-size:13px;color:#18181b;font-weight:600;">{$time}</td>
            </tr>
            <tr style="border-top:1px solid #e4e4e7;">
              <td style="padding:12px 16px;font-size:13px;color:#71717a;">Approx. Location</td>
              <td style="padding:12px 16px;font-size:13px;color:#18181b;font-weight:600;">{$location}</td>
            </tr>
          </table>
          <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:14px 16px;">
            <p style="margin:0;font-size:13px;color:#dc2626;font-weight:600;">Not you? Act immediately.</p>
            <p style="margin:4px 0 0;font-size:13px;color:#dc2626;">If you did not make this change, contact your administrator right away as your account may be compromised.</p>
          </div>
        </td></tr>
        <tr><td style="padding:16px 32px;background:#f9f9f9;border-top:1px solid #e4e4e7;">
          <p style="margin:0;font-size:11px;color:#a1a1aa;">This is an automated message from noreply@ssma.sa — please do not reply.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
  }

  private static function otpEmailHtml(string $name, string $otp): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:40px 0;">
    <tr><td align="center">
      <table width="480" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.08);">
        <tr><td style="background:#18181b;padding:24px 32px;">
          <p style="margin:0;color:#ffffff;font-size:18px;font-weight:bold;">AI Document Analyzer</p>
        </td></tr>
        <tr><td style="padding:32px;">
          <p style="margin:0 0 8px;font-size:16px;color:#18181b;font-weight:600;">Password Reset Request</p>
          <p style="margin:0 0 24px;font-size:14px;color:#71717a;">Hi {$name}, use the code below to reset your password. It expires in <strong>10 minutes</strong>.</p>
          <div style="background:#f4f4f5;border-radius:6px;padding:20px;text-align:center;margin-bottom:24px;">
            <span style="font-size:36px;font-weight:bold;letter-spacing:12px;color:#18181b;">{$otp}</span>
          </div>
          <p style="margin:0;font-size:12px;color:#a1a1aa;">If you did not request a password reset, you can ignore this email. Your password will remain unchanged.</p>
        </td></tr>
        <tr><td style="padding:16px 32px;background:#f9f9f9;border-top:1px solid #e4e4e7;">
          <p style="margin:0;font-size:11px;color:#a1a1aa;">This is an automated message from noreply@ssma.sa — please do not reply.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
  }
}
