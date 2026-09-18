<?php
/** Diagnostic lecture seule des prerequis administrateur LOGITIX. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$ok = true;
function checkLine(bool $pass, string $label): void {
    global $ok;
    echo ($pass ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$pass) {
        $ok = false;
    }
}

try {
    checkLine(extension_loaded('pdo_mysql'), 'Extension PDO MySQL');
    checkLine(DB_USER_ADMIN !== '', 'Utilisateur MySQL admin configure');
    checkLine(DB_PASS_ADMIN !== '', 'Mot de passe MySQL admin configure');
    checkLine(!ADMIN_REQUIRE_2FA || TOTP_ENCRYPTION_KEY !== '', 'Cle TOTP presente quand 2FA obligatoire');

    $pdo = Database::getInstance('admin')->getConnection();
    checkLine(true, 'Connexion MySQL logitix_admin');

    // Teste les objets avec les droits reels du compte applicatif. On ne lit
    // pas schema_migrations car le compte PHP admin n'a volontairement aucun
    // droit direct sur cette table technique.
    $checks = [
        'Vue clients' => 'SELECT COUNT(*) FROM admin_client_users',
        'Vue devis' => 'SELECT COUNT(*) FROM admin_devis',
        'Vue expeditions' => 'SELECT COUNT(*) FROM admin_expeditions',
        'Vue vehicules' => 'SELECT COUNT(*) FROM admin_vehicules',
        'Vue chauffeurs' => 'SELECT COUNT(*) FROM admin_chauffeurs',
        'Vue remorques' => 'SELECT COUNT(*) FROM admin_remorques',
        'Vue entrepots' => 'SELECT COUNT(*) FROM admin_entrepots',
        'Journal admin' => 'SELECT COUNT(*) FROM admin_audit_log',
    ];
    foreach ($checks as $label => $sql) {
        try {
            $pdo->query($sql)->fetchColumn();
            checkLine(true, $label . ' accessible');
        } catch (Throwable $e) {
            checkLine(false, $label . ' inaccessible');
        }
    }

    // Verifie aussi que le hash des clients n'est pas lisible par le compte
    // admin applicatif. Un refus MySQL est ici le resultat attendu.
    try {
        $pdo->query('SELECT mot_de_passe FROM admin_client_users LIMIT 1')->fetchColumn();
        checkLine(false, 'Hash mot de passe client non lisible par le compte admin');
    } catch (Throwable $e) {
        checkLine(true, 'Hash mot de passe client non lisible par le compte admin');
    }
} catch (Throwable $e) {
    checkLine(false, 'Diagnostic : ' . $e->getMessage());
}

exit($ok ? 0 : 1);
