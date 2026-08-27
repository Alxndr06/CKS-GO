<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class Mailer
{
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        if (!MAIL_ENABLED) {
            return false;
        }

        $toEmail = trim($toEmail);
        $toName = trim($toName);

        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            error_log('Mailer::send / email destinataire invalide : ' . $toEmail);
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();

            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->Port = MAIL_PORT;
            $mail->Timeout = 20;

            if (MAIL_ENCRYPTION === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif (MAIL_ENCRYPTION === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
            $mail->addReplyTo(MAIL_REPLY_TO, MAIL_REPLY_TO_NAME);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody ?: self::htmlToText($htmlBody);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Erreur mailing CKS GO / ErrorInfo : ' . $mail->ErrorInfo);
            error_log('Erreur mailing CKS GO / Exception : ' . $e->getMessage());
            return false;
        }
    }

    public static function renderTemplate(string $template, array $data = []): string
    {
        $templatePath = __DIR__ . '/../views/emails/' . $template . '.php';

        if (!file_exists($templatePath)) {
            throw new RuntimeException('Template e-mail introuvable : ' . $template);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $templatePath;
        return (string)ob_get_clean();
    }

    public static function sendRegistrationReceived(array $user): bool
    {
        $subject = 'Confirmez votre adresse e-mail CKS GO';

        $html = self::renderTemplate('registration_received', [
            'user' => $user,
        ]);

        $text = "Bonjour {$user['firstname']},

Merci pour votre inscription sur CKS GO.
Veuillez confirmer votre adresse e-mail pour activer votre accès.";

        return self::send(
            (string)$user['email'],
            trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')),
            $subject,
            $html,
            $text
        );
    }

    public static function sendAccountApproved(array $user): bool
    {
        $email = trim((string)($user['email'] ?? ''));
        $firstname = trim((string)($user['firstname'] ?? ''));
        $lastname = trim((string)($user['lastname'] ?? ''));
        $username = trim((string)($user['username'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log('Mailer::sendAccountApproved / email invalide : ' . $email);
            return false;
        }

        $html = self::renderTemplate('account_approved', [
            'user' => [
                'email' => $email,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'username' => $username,
            ],
        ]);

        $text = "Bonjour {$firstname},

Votre compte CKS GO a été validé par un administrateur.
Vous pouvez maintenant vous connecter si votre adresse e-mail a déjà été confirmée.";

        return self::send(
            $email,
            trim($firstname . ' ' . $lastname),
            'Votre compte CKS GO a été validé',
            $html,
            $text
        );
    }

    public static function sendAccountRejected(array $user): bool
    {
        $html = self::renderTemplate('account_rejected', [
            'user' => $user,
        ]);

        $text = "Bonjour {$user['firstname']},

Votre demande d'inscription à CKS GO n'a pas été retenue.
Si besoin, vous pouvez contacter l'administrateur de l'application.";

        return self::send(
            (string)$user['email'],
            trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')),
            'Votre demande d’inscription CKS GO',
            $html,
            $text
        );
    }

    public static function sendPasswordResetLink(array $user): bool
    {
        $email = trim((string)($user['email'] ?? ''));
        $firstname = trim((string)($user['firstname'] ?? ''));
        $lastname = trim((string)($user['lastname'] ?? ''));
        $username = trim((string)($user['username'] ?? ''));
        $resetUrl = trim((string)($user['reset_url'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log('Mailer::sendPasswordResetLink / email invalide : ' . $email);
            return false;
        }

        if ($resetUrl === '') {
            error_log('Mailer::sendPasswordResetLink / URL manquante pour : ' . $email);
            return false;
        }

        $html = self::renderTemplate('password_reset', [
            'user' => [
                'email' => $email,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'username' => $username,
                'reset_url' => $resetUrl,
            ],
        ]);

        $text = "Bonjour {$firstname},

"
            . "Une demande de réinitialisation de mot de passe a été reçue pour votre compte CKS GO.
"
            . "Si vous êtes à l’origine de cette demande, utilisez ce lien : {$resetUrl}

"
            . "Si vous n’êtes pas à l’origine de cette demande, vous pouvez ignorer cet e-mail.";

        return self::send(
            $email,
            trim($firstname . ' ' . $lastname),
            'Réinitialisation de votre mot de passe CKS GO',
            $html,
            $text
        );
    }

    public static function sendPasswordResetConfirmation(array $user): bool
    {
        $email = trim((string)($user['email'] ?? ''));
        $firstname = trim((string)($user['firstname'] ?? ''));
        $lastname = trim((string)($user['lastname'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log('Mailer::sendPasswordResetConfirmation / email invalide : ' . $email);
            return false;
        }

        $html = '
            <!DOCTYPE html>
            <html lang="fr">
            <head><meta charset="UTF-8"><title>Mot de passe modifié</title></head>
            <body style="margin:0;padding:0;background:#f7f1ea;font-family:Arial,Helvetica,sans-serif;color:#2f241d;">
                <div style="max-width:640px;margin:0 auto;padding:32px 16px;">
                    <div style="background:#ffffff;border:1px solid #e7d7c7;border-radius:18px;overflow:hidden;">
                        <div style="padding:24px 28px;background:linear-gradient(135deg,#f6eadf 0%,#efe1d2 100%);border-bottom:1px solid #e7d7c7;">
                            <h1 style="margin:0;font-size:24px;">CKS GO</h1>
                            <p style="margin:8px 0 0 0;">Votre mot de passe a été modifié</p>
                        </div>
                        <div style="padding:28px;">
                            <p>Bonjour ' . htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') . ',</p>
                            <p>Votre mot de passe CKS GO vient d’être réinitialisé avec succès.</p>
                            <p>Si vous n’êtes pas à l’origine de cette action, contactez rapidement un administrateur.</p>
                            <p style="margin-top:24px;">À bientôt,<br>L’équipe CKS GO</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>';

        $text = "Bonjour {$firstname},

Votre mot de passe CKS GO vient d’être réinitialisé avec succès.
Si vous n’êtes pas à l’origine de cette action, contactez rapidement un administrateur.";

        return self::send(
            $email,
            trim($firstname . ' ' . $lastname),
            'Votre mot de passe CKS GO a été modifié',
            $html,
            $text
        );
    }

    private static function htmlToText(string $html): string
    {
        $text = html_entity_decode(
            strip_tags(str_replace(['<br>', '<br/>', '<br />'], "
", $html)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $text = preg_replace("/
{3,}/", "

", $text);

        return trim((string)$text);
    }
}
