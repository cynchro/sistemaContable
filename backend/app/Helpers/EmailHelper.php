<?php

namespace App\Helpers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Support\Config;

class EmailHelper
{
    /**
     * Send an HTML email.
     *
     * @param string $templatePath Absolute path to the HTML template file.
     * @throws Exception When the template is not found or SMTP fails.
     */
    public function sendEmail(
        string $to,
        string $subject,
        string $templatePath,
        ?string $fromEmail = null,
        ?string $fromName = null
    ): void {
        if (!file_exists($templatePath)) {
            throw new Exception("Email template not found: {$templatePath}");
        }

        $fromEmail ??= Config::get('mail.from_address', 'hello@example.com');
        $fromName  ??= Config::get('mail.from_name', 'App');

        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = Config::get('mail.host', 'localhost');
        $mail->SMTPAuth   = true;
        $mail->Username   = Config::get('mail.username', '');
        $mail->Password   = Config::get('mail.password', '');
        $mail->SMTPSecure = Config::get('mail.encryption', PHPMailer::ENCRYPTION_STARTTLS);
        $mail->Port       = Config::get('mail.port', 587);

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);
        $mail->addReplyTo($fromEmail, $fromName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = (string) file_get_contents($templatePath);

        $mail->send();
    }
}
