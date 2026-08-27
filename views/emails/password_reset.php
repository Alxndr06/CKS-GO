<?php
$userFirstname = htmlspecialchars($user['firstname'] ?? '', ENT_QUOTES, 'UTF-8');
$resetUrl = htmlspecialchars($user['reset_url'] ?? '', ENT_QUOTES, 'UTF-8');
$appName = 'CKS GO';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réinitialisation mot de passe <?= $appName ?></title>
</head>
<body style="margin:0;padding:0;background:#f7f1ea;font-family:Arial,Helvetica,sans-serif;color:#2f241d;">
<div style="max-width:640px;margin:0 auto;padding:32px 16px;">
    <div style="background:#ffffff;border:1px solid #e7d7c7;border-radius:18px;overflow:hidden;">
        <div style="padding:24px 28px;background:linear-gradient(135deg,#f6eadf 0%,#efe1d2 100%);border-bottom:1px solid #e7d7c7;">
            <h1 style="margin:0;font-size:24px;">CKS GO</h1>
            <p style="margin:8px 0 0 0;">Réinitialisation de votre mot de passe</p>
        </div>

        <div style="padding:28px;">
            <p>Bonjour <?= $userFirstname ?>,</p>

            <p>
                Une demande de réinitialisation de mot de passe vient d’être reçue pour votre compte CKS GO.
                Si vous en êtes à l’origine, utilisez le bouton ci-dessous.
            </p>

            <p style="margin:24px 0;">
                <a
                    href="<?= $resetUrl ?>"
                    style="display:inline-block;padding:12px 18px;background:#7a4f2a;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:bold;"
                >
                    Réinitialiser mon mot de passe
                </a>
            </p>

            <p>Ce lien expire automatiquement au bout d’une heure.</p>

            <p>
                Si le bouton ne fonctionne pas, copiez-collez ce lien dans votre navigateur :
            </p>

            <p style="word-break:break-all;">
                <a href="<?= $resetUrl ?>"><?= $resetUrl ?></a>
            </p>

            <p>
                Si vous n’êtes pas à l’origine de cette demande, vous pouvez ignorer cet e-mail.
            </p>

            <p style="margin-top:24px;">
                À bientôt,<br>
                L’équipe <?= $appName ?>
            </p>
        </div>
    </div>
</div>
</body>
</html>
