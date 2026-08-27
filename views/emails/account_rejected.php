<?php
$userFirstname = htmlspecialchars($user['firstname'] ?? '', ENT_QUOTES, 'UTF-8');
$appName = 'CKS GO';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Demande d’inscription <?= $appName ?></title>
</head>
<body style="margin:0;padding:0;background:#f7f1ea;font-family:Arial,Helvetica,sans-serif;color:#2f241d;">
<div style="max-width:640px;margin:0 auto;padding:32px 16px;">
    <div style="background:#ffffff;border:1px solid #e7d7c7;border-radius:18px;overflow:hidden;">
        <div style="padding:24px 28px;background:linear-gradient(135deg,#fff1ef 0%,#fbe2de 100%);border-bottom:1px solid #efcfc8;">
            <h1 style="margin:0;font-size:24px;">CKS GO</h1>
            <p style="margin:8px 0 0 0;">Statut de votre demande</p>
        </div>

        <div style="padding:28px;">
            <p>Bonjour <?= $userFirstname ?>,</p>

            <p>
                Après vérification, ta demande d’inscription à CKS GO n’a pas été retenue.
            </p>

            <p>
                Si nécessaire, tu peux te rapprocher de l’administrateur de l’application
                pour obtenir plus d’informations.
            </p>

            <p style="margin-top:24px;">
                Cordialement,<br>
                L’équipe CKS GO
            </p>
        </div>
    </div>
</div>
</body>
</html>