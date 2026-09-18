<?php
/**
 * Diagnostic local de l'inscription LOGITIX.
 * CLI uniquement. Aucun compte de test n'est conserve : la transaction finale
 * est toujours annulee.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$envPath = $root . DIRECTORY_SEPARATOR . '.env.local';

function pass(string $label, string $detail = ''): void {
    fwrite(STDOUT, '[PASS] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . PHP_EOL);
}

function fail(string $label, string $detail = ''): void {
    fwrite(STDERR, '[FAIL] ' . $label . ($detail !== '' ? ' - ' . $detail : '') . PHP_EOL);
}

function mysqlHint(Throwable $e): string {
    if (!$e instanceof PDOException) {
        return $e->getMessage();
    }
    $code = (int) ($e->errorInfo[1] ?? 0);
    if ($code === 1045) return 'acces refuse : mot de passe/compte MySQL incorrect';
    if (in_array($code, [2002, 2003], true)) return 'serveur MySQL injoignable : verifiez WampServer, host et port';
    if ($code === 1049) return 'base logitix introuvable';
    if (in_array($code, [1142, 1143], true)) return 'privilege MySQL manquant';
    if ($code === 1356) return 'vue MySQL invalide ou definer/privilege manquant';
    if ($code === 3819) return 'contrainte CHECK refusee';
    return 'code MySQL/PDO ' . ($code ?: $e->getCode());
}

$failed = false;
if (is_file($envPath) && is_readable($envPath)) {
    pass('.env.local lisible');
} else {
    fail('.env.local lisible', 'lancez php tools/setup_local_env.php');
    $failed = true;
}

if (!extension_loaded('pdo_mysql')) {
    fail('extension pdo_mysql', 'activez-la dans WampServer');
    exit(1);
}
pass('extension pdo_mysql');

try {
    require_once $root . '/config/config.php';
    require_once $root . '/models/UserModel.php';
    pass('configuration PHP chargee', 'APP_ENV=' . APP_ENV);
    pass('cible MySQL', DB_HOST . ':' . DB_PORT . '/' . DB_NAME);
} catch (Throwable $e) {
    fail('chargement configuration', $e->getMessage());
    exit(1);
}

if (DB_PASS_CLIENT === '') {
    fail('mot de passe logitix_client charge', 'configuration absente');
    exit(1);
}
pass('mot de passe logitix_client charge');

$model = null;
try {
    $model = new UserModel('client');
    $clientPdo = Database::getInstance('client')->getConnection();
    $serverVersion = (string) $clientPdo->query('SELECT VERSION()')->fetchColumn();
    $clientIdentity = (string) $clientPdo->query('SELECT CURRENT_USER()')->fetchColumn();
    if (stripos($serverVersion, 'MariaDB') !== false) {
        throw new RuntimeException('MariaDB detecte alors que cette version cible MySQL.');
    }
    pass('connexion PDO client', $clientIdentity);
    pass('version MySQL', $serverVersion);

    $adminModel = new UserModel('admin');
    $adminPdo = Database::getInstance('admin')->getConnection();
    $adminIdentity = (string) $adminPdo->query('SELECT CURRENT_USER()')->fetchColumn();
    $adminModel->findByEmail('admin@logitix.ci');
    pass('connexion PDO admin', $adminIdentity);

    $probeEmail = 'diag-' . bin2hex(random_bytes(6)) . '@example.invalid';
    if ($model->emailExists($probeEmail)) {
        throw new RuntimeException('collision inattendue sur l email de diagnostic');
    }
    pass('lecture client_users / emailExists');

    $probeIp = '127.0.0.1';
    $model->isRegistrationRateLimited($probeIp);
    pass('lecture rate limiting inscription');

    $model->beginTransaction();
    $newId = $model->create([
        'nom' => 'Diagnostic',
        'prenom' => 'LOGITIX',
        'email' => $probeEmail,
        'telephone' => '+2250102030405',
        'status' => 'particulier',
        'mot_de_passe' => 'Diag!Secure2026#A',
        'raison_sociale' => null,
        'rccm' => null,
    ]);
    if ($newId < 1) {
        throw new RuntimeException('identifiant utilisateur de diagnostic invalide');
    }
    pass('INSERT test dans client_users', 'id=' . $newId);

    $model->recordRegistrationAttempt($probeEmail, $probeIp, true);
    pass('INSERT journal inscription');

    $created = $model->findByEmail($probeEmail);
    if (!$created || (int) $created['id'] !== $newId) {
        throw new RuntimeException('relecture du compte de diagnostic impossible');
    }
    pass('relecture du compte cree');

    $model->rollBackIfActive();
    pass('rollback du test', 'aucune donnee de diagnostic conservee');
} catch (Throwable $e) {
    if ($model instanceof UserModel) {
        $model->rollBackIfActive();
    }
    fail('parcours inscription', mysqlHint($e));
    $failed = true;
}

if ($failed) {
    fwrite(STDERR, PHP_EOL . "Diagnostic termine avec au moins un echec. Corrigez la ligne FAIL puis relancez.\n");
    exit(1);
}

fwrite(STDOUT, PHP_EOL . "Tous les controles d'inscription sont PASS.\n");
exit(0);
