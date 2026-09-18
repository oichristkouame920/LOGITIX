<?php
/**
 * Cree .env.local pour un environnement local LOGITIX et verifie les deux
 * connexions MySQL avant d'ecrire la configuration.
 * CLI uniquement : ce fichier n'est jamais destine a etre servi par HTTP.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function prompt(string $label, string $default = ''): string {
    $suffix = $default !== '' ? " [$default]" : '';
    $value = readline($label . $suffix . ': ');
    $value = trim((string) $value);
    return $value === '' ? $default : $value;
}

function hiddenPrompt(string $label): string {
    if (DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec')) {
        $stty = trim((string) @shell_exec('command -v stty 2>/dev/null'));
        if ($stty !== '') {
            fwrite(STDOUT, $label . ': ');
            @shell_exec('stty -echo');
            $value = trim((string) fgets(STDIN));
            @shell_exec('stty echo');
            fwrite(STDOUT, PHP_EOL);
            return $value;
        }
    }

    // Sous Windows/WampServer, readline() reste visible. Le mot de passe n'est
    // jamais affiche de nouveau ni ecrit en clair dans .env.local.
    return trim((string) readline($label . ' (saisie visible): '));
}

function validatePort(string $value): int {
    $port = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 65535],
    ]);
    if ($port === false) {
        throw new RuntimeException('Port MySQL invalide.');
    }
    return (int) $port;
}

function testConnection(string $host, int $port, string $dbName, string $user, string $password, string $view): string {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $pdo->query('SELECT id FROM ' . $view . ' LIMIT 1')->fetch();
    return (string) $pdo->query('SELECT CURRENT_USER()')->fetchColumn();
}

if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "Erreur : l'extension PHP pdo_mysql n'est pas activee. Activez-la dans WampServer puis relancez cet outil.\n");
    exit(1);
}

$root = dirname(__DIR__);
$target = $root . DIRECTORY_SEPARATOR . '.env.local';
$existingTotpKey = '';
if (is_file($target)) {
    $existingEnv = (string) file_get_contents($target);
    if (preg_match('/^LOGITIX_TOTP_ENCRYPTION_KEY=(.+)$/m', $existingEnv, $match)) {
        $candidate = trim($match[1]);
        $decoded = base64_decode($candidate, true);
        if ($decoded !== false && strlen($decoded) === 32) {
            $existingTotpKey = $candidate;
        }
    }

    $answer = strtolower(prompt('.env.local existe deja. Le remplacer ? (oui/non)', 'non'));
    if (!in_array($answer, ['oui', 'o', 'yes', 'y'], true)) {
        fwrite(STDOUT, "Annule.\n");
        exit(0);
    }
}

fwrite(STDOUT, "LOGITIX - configuration locale MySQL\n");
fwrite(STDOUT, "Utilisez uniquement les mots de passe des comptes logitix_client et logitix_admin affiches par le SQL final.\n\n");

$host = prompt('Hote MySQL', '127.0.0.1');
try {
    $port = validatePort(prompt('Port MySQL WampServer', '3306'));
} catch (RuntimeException $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
$dbName = prompt('Nom de la base', 'logitix');
if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
    fwrite(STDERR, "Erreur : nom de base invalide.\n");
    exit(1);
}

$clientPassword = hiddenPrompt('Mot de passe MySQL logitix_client');
$adminPassword = hiddenPrompt('Mot de passe MySQL logitix_admin');
if ($clientPassword === '' || $adminPassword === '') {
    fwrite(STDERR, "Erreur : les deux mots de passe MySQL sont obligatoires.\n");
    exit(1);
}
if (hash_equals($clientPassword, $adminPassword)) {
    fwrite(STDERR, "Erreur : les comptes client et admin doivent utiliser deux mots de passe differents.\n");
    exit(1);
}

fwrite(STDOUT, "\nVerification des connexions avant ecriture de .env.local...\n");
try {
    $clientIdentity = testConnection($host, $port, $dbName, 'logitix_client', $clientPassword, 'client_users');
    fwrite(STDOUT, "[PASS] logitix_client : {$clientIdentity}\n");

    $adminIdentity = testConnection($host, $port, $dbName, 'logitix_admin', $adminPassword, 'admin_users');
    fwrite(STDOUT, "[PASS] logitix_admin  : {$adminIdentity}\n");
} catch (PDOException $e) {
    $mysqlCode = (int) ($e->errorInfo[1] ?? 0);
    fwrite(STDERR, "[FAIL] Connexion MySQL impossible.\n");
    if ($mysqlCode === 1045) {
        fwrite(STDERR, "Mot de passe incorrect ou compte MySQL non cree. Verifiez les valeurs retournees par le SQL final.\n");
    } elseif (in_array($mysqlCode, [2002, 2003], true)) {
        fwrite(STDERR, "MySQL est injoignable. Verifiez que WampServer est vert et que le port {$port} est le bon.\n");
    } elseif ($mysqlCode === 1049) {
        fwrite(STDERR, "La base {$dbName} n'existe pas. Importez d'abord Logitix_SQL_FINAL_COMPLET.sql.\n");
    } elseif (in_array($mysqlCode, [1142, 1143, 1356], true)) {
        fwrite(STDERR, "Les vues ou privileges LOGITIX sont incomplets. Sur une base existante, utilisez tools/upgrade_admin_features.php ou l assistant local admin/bootstrap.php.\n");
    } else {
        fwrite(STDERR, "Code MySQL/PDO : " . ($mysqlCode ?: $e->getCode()) . "\n");
    }
    exit(1);
}

$totpKey = $existingTotpKey !== '' ? $existingTotpKey : base64_encode(random_bytes(32));
if ($existingTotpKey !== '') {
    fwrite(STDOUT, "[PASS] Cle TOTP existante conservee pour ne pas invalider les enrôlements 2FA.\n");
}
$lines = [
    '# Genere localement par tools/setup_local_env.php',
    'LOGITIX_APP_ENV=development',
    'LOGITIX_APP_URL=auto',
    'LOGITIX_FORCE_HTTPS=false',
    'LOGITIX_TRUST_PROXY_HTTPS=false',
    'LOGITIX_TRUSTED_PROXY_IPS=',
    'LOGITIX_ADMIN_REQUIRE_2FA=true',
    'LOGITIX_ADMIN_BOOTSTRAP_ENABLED=false',
    '',
    'LOGITIX_DB_HOST=' . $host,
    'LOGITIX_DB_PORT=' . $port,
    'LOGITIX_DB_NAME=' . $dbName,
    'LOGITIX_DB_USER_CLIENT=logitix_client',
    'LOGITIX_DB_PASS_CLIENT_B64=' . base64_encode($clientPassword),
    'LOGITIX_DB_USER_ADMIN=logitix_admin',
    'LOGITIX_DB_PASS_ADMIN_B64=' . base64_encode($adminPassword),
    'LOGITIX_DB_REQUIRE_TLS=false',
    'LOGITIX_DB_SSL_CA=',
    '',
    'LOGITIX_TOTP_ENCRYPTION_KEY=' . $totpKey,
    '',
];

if (file_put_contents($target, implode(PHP_EOL, $lines), LOCK_EX) === false) {
    fwrite(STDERR, "Impossible d'ecrire {$target}\n");
    exit(1);
}
@chmod($target, 0600);
fwrite(STDOUT, "\n.env.local cree avec succes. Les mots de passe y sont encodes en base64 pour eviter les problemes de parsing ; ce fichier reste un secret a proteger.\n");
fwrite(STDOUT, "Etapes suivantes : php tools/diagnose_registration.php puis php tools/diagnose_admin.php\n");
