<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/TOTP.php';
require_once __DIR__ . '/../models/UserModel.php';

$userModel = new UserModel('admin');
$user = $userModel->findById((int) $_SESSION['user_id']);

$error = getFlash('error');
$success = getFlash('success');

$provisioningUri = null;
if (empty($user['totp_enabled'])) {
    // La configuration 2FA est une operation sensible : elle n'est autorisee
    // que juste apres une authentification par mot de passe.
    if (!hasRecentPasswordAuthentication()) {
        logout();
        redirect('admin/connexion.php');
    }

    if (!isTotpEncryptionConfigured()) {
        error_log('LOGITIX 2FA: LOGITIX_TOTP_ENCRYPTION_KEY is missing or invalid.');
        logout();
        redirect('admin/connexion.php');
    }

    // Un secret en attente est reutilise pendant la courte session de setup ;
    // cela evite qu'un simple rafraichissement invalide le QR deja scanne.
    $secret = !empty($user['totp_secret']) ? (string) $user['totp_secret'] : TOTP::generateSecret();
    if (empty($user['totp_secret'])) {
        $userModel->setPendingTotpSecret((int) $user['id'], $secret);
    }
    $provisioningUri = TOTP::getProvisioningUri($secret, $user['email'], APP_NAME);
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Double authentification - LOGITIX Admin</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/fonts.css" rel="stylesheet">
    <style>
        body { font-family: 'Jost', sans-serif; background:#f4f6f9; }
        .card-2fa { max-width: 480px; margin: 3rem auto; }
        #qrcode { display:flex; justify-content:center; margin: 1.5rem 0; }
        .secret-key { font-family: monospace; letter-spacing: 0.1em; background:#f0f0f0; padding:0.6rem 1rem; border-radius:6px; word-break: break-all; }
    </style>
</head>
<body>
<div class="container">
    <div class="card card-2fa shadow-sm">
        <div class="card-body p-4">
            <h4 class="mb-3"><i class="bi bi-shield-lock"></i> Double authentification (2FA)</h4>

            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

            <?php if (!empty($user['totp_enabled'])): ?>
                <p class="text-success"><i class="bi bi-check-circle-fill"></i> La double authentification est activée sur ce compte.</p>
                <form action="traitement_2fa.php" method="post" onsubmit="return confirm('Désactiver la double authentification réduit la sécurité de ce compte. Confirmer ?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="disable">
                    <div class="mb-3">
                        <label class="form-label">Confirmez avec votre code actuel pour désactiver</label>
                        <input type="text" name="code" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                    </div>
                    <button type="submit" class="btn btn-outline-danger">Désactiver la 2FA</button>
                </form>
            <?php else: ?>
                <p>1. Scannez ce QR code avec Google Authenticator, Authy, ou une application équivalente.</p>
                <div id="qrcode"></div>
                <p class="small text-muted">Ou entrez cette clé manuellement :</p>
                <p class="secret-key"><?= e($secret) ?></p>

                <p class="mt-4">2. Entrez le code à 6 chiffres généré par l'application pour confirmer l'activation.</p>
                <form action="traitement_2fa.php" method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="enable">
                    <div class="mb-3">
                        <input type="text" name="code" class="form-control" placeholder="123456" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary">Activer la 2FA</button>
                </form>
            <?php endif; ?>

            <div class="mt-3">
                <a href="profil.php" class="text-muted small"><i class="bi bi-arrow-left"></i> Retour au profil</a>
            </div>
        </div>
    </div>
</div>

<?php if ($provisioningUri): ?>
<script src="../js/qrcode.js"></script>
<script>
    // Rendu du QR code entièrement côté navigateur : le secret ne transite
    // jamais vers un service tiers (contrairement à un appel à une API
    // externe de génération de QR code).
    var qr = qrcode(0, 'M');
    qr.addData(<?= json_encode($provisioningUri, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
    qr.make();
    document.getElementById('qrcode').innerHTML = qr.createSvgTag(4);
</script>
<?php endif; ?>
</body>
</html>
