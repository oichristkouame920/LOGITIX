<?php
/**
 * Modele utilisateurs LOGITIX avec separation stricte client/admin.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

class UserModel {
    private PDO $pdo;
    private string $profile;
    private string $userSource;
    private string $emailSource;
    private string $attemptTable;

    public function __construct(string $profile = 'client') {
        if (!in_array($profile, ['client', 'admin'], true)) {
            throw new InvalidArgumentException('Invalid database profile.');
        }

        $this->profile = $profile;
        $this->userSource = $profile === 'client' ? 'client_users' : 'admin_users';
        $this->emailSource = $profile === 'client' ? 'client_users' : 'user_emails';
        $this->attemptTable = $profile === 'client' ? 'auth_attempts_client' : 'auth_attempts_admin';
        $this->pdo = Database::getInstance($profile)->getConnection();
    }

    private function selectedColumns(): string {
        $columns = 'id, nom, prenom, email, telephone, status, role, mot_de_passe, password_must_change, password_changed_at, actif, auth_version';
        if ($this->profile === 'admin') {
            $columns .= ', totp_secret, totp_enabled, totp_last_counter';
        }
        return $columns;
    }

    private function hydrate(?array $user): ?array {
        if (!$user) {
            return null;
        }
        if ($this->profile === 'admin' && array_key_exists('totp_secret', $user) && $user['totp_secret'] !== null) {
            $user['totp_secret'] = decryptSensitiveValue((string) $user['totp_secret']);
        }
        return $user;
    }

    public function findByEmail(string $email): ?array {
        $stmt = $this->pdo->prepare('SELECT ' . $this->selectedColumns() . ' FROM ' . $this->userSource . ' WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $this->hydrate($stmt->fetch() ?: null);
    }

    public function findById(int $id): ?array {
        $stmt = $this->pdo->prepare('SELECT ' . $this->selectedColumns() . ' FROM ' . $this->userSource . ' WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $this->hydrate($stmt->fetch() ?: null);
    }

    public function create(array $data): int {
        if ($this->profile !== 'client') {
            throw new LogicException('Client creation must use the client database profile.');
        }

        $sql = 'INSERT INTO client_users
                (nom, prenom, email, telephone, status, mot_de_passe, raison_sociale, rccm)
                VALUES
                (:nom, :prenom, :email, :telephone, :status, :mot_de_passe, :raison_sociale, :rccm)';

        $stmt = $this->pdo->prepare($sql);
        $hash = password_hash($data['mot_de_passe'], PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('Password hashing failed.');
        }

        $stmt->execute([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'telephone' => $data['telephone'],
            'status' => $data['status'],
            'mot_de_passe' => $hash,
            'raison_sociale' => $data['raison_sociale'] ?? null,
            'rccm' => $data['rccm'] ?? null,
        ]);

        // MySQL ne garantit pas LAST_INSERT_ID() lors d'un INSERT dans une
        // vue qui n'expose pas explicitement la colonne AUTO_INCREMENT.
        // L'email est UNIQUE : on relit donc l'identifiant via la vue client.
        $idStmt = $this->pdo->prepare('SELECT id FROM client_users WHERE email = :email LIMIT 1');
        $idStmt->execute(['email' => $data['email']]);
        $id = $idStmt->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Unable to retrieve the newly created user id.');
        }
        return (int) $id;
    }

    public function authenticate(string $email, string $password): ?array {
        $user = $this->findByEmail($email);
        if (!$user || !(bool) $user['actif']) {
            // Calcul factice pour reduire la difference de temps entre compte
            // existant et inexistant sans exposer un hash reel.
            password_verify($password, '$2y$12$8jTg6g7uew5WQFevE3EZ5OimBXBU5Kf3ddzQ3GMp2fDxVo6TBduau');
            return null;
        }
        if (!password_verify($password, (string) $user['mot_de_passe'])) {
            return null;
        }
        return $user;
    }

    public function emailExists(string $email, ?int $excludeId = null): bool {
        if ($excludeId === null) {
            $stmt = $this->pdo->prepare('SELECT id FROM ' . $this->emailSource . ' WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
        } else {
            $stmt = $this->pdo->prepare('SELECT id FROM ' . $this->emailSource . ' WHERE email = :email AND id <> :id LIMIT 1');
            $stmt->execute(['email' => $email, 'id' => $excludeId]);
        }
        return $stmt->fetchColumn() !== false;
    }

    public function isLoginRateLimited(string $email, string $ip, string $context = 'password'): bool {
        if (!in_array($context, ['password', 'totp'], true)) {
            throw new InvalidArgumentException('Invalid authentication context.');
        }

        $windowMinutes = $context === 'totp' ? 10 : 15;
        $pairLimit = $context === 'totp' ? 6 : 5;
        $ipLimit = $context === 'totp' ? 20 : 30;

        // Toute la fenetre temporelle est calculee par MySQL afin d'eviter les
        // ecarts de fuseau horaire entre PHP et le serveur de base de donnees.
        // Une authentification reussie remet a zero le compteur du couple
        // email/IP, mais pas le compteur global de l'IP.
        $pairSql = "SELECT COUNT(*)
                    FROM {$this->attemptTable} a
                    WHERE a.success = 0
                      AND a.context = :context_fail
                      AND a.email = :email_fail
                      AND a.ip = INET6_ATON(:ip_fail)
                      AND a.attempted_at >= (NOW() - INTERVAL {$windowMinutes} MINUTE)
                      AND a.attempted_at > COALESCE(
                          (SELECT MAX(s.attempted_at)
                           FROM {$this->attemptTable} s
                           WHERE s.success = 1
                             AND s.context = :context_success
                             AND s.email = :email_success
                             AND s.ip = INET6_ATON(:ip_success)
                             AND s.attempted_at >= (NOW() - INTERVAL {$windowMinutes} MINUTE)),
                          '1970-01-01 00:00:00'
                      )";
        $pairStmt = $this->pdo->prepare($pairSql);
        $pairStmt->execute([
            'context_fail' => $context,
            'email_fail' => $email,
            'ip_fail' => $ip,
            'context_success' => $context,
            'email_success' => $email,
            'ip_success' => $ip,
        ]);

        $ipSql = "SELECT COUNT(*) FROM {$this->attemptTable}
                  WHERE success = 0 AND context = :context
                    AND ip = INET6_ATON(:ip)
                    AND attempted_at >= (NOW() - INTERVAL {$windowMinutes} MINUTE)";
        $ipStmt = $this->pdo->prepare($ipSql);
        $ipStmt->execute(['context' => $context, 'ip' => $ip]);

        return (int) $pairStmt->fetchColumn() >= $pairLimit
            || (int) $ipStmt->fetchColumn() >= $ipLimit;
    }

    public function recordLoginAttempt(string $email, string $ip, bool $success, string $context = 'password'): void {
        if (!in_array($context, ['password', 'totp'], true)) {
            throw new InvalidArgumentException('Invalid authentication context.');
        }
        $stmt = $this->pdo->prepare('INSERT INTO ' . $this->attemptTable . ' (email, ip, success, context) VALUES (:email, INET6_ATON(:ip), :success, :context)');
        $stmt->execute([
            'email' => $email,
            'ip' => $ip,
            'success' => $success ? 1 : 0,
            'context' => $context,
        ]);
    }

    public function isRegistrationRateLimited(string $ip): bool {
        if ($this->profile !== 'client') {
            throw new LogicException('Registration rate limiting is client-only.');
        }
        $sql = "SELECT COUNT(*) FROM {$this->attemptTable} WHERE context = 'registration' AND ip = INET6_ATON(:ip) AND attempted_at >= (NOW() - INTERVAL 1 HOUR)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['ip' => $ip]);
        return (int) $stmt->fetchColumn() >= 5;
    }

    public function recordRegistrationAttempt(string $email, string $ip, bool $success): void {
        if ($this->profile !== 'client') {
            throw new LogicException('Registration logging is client-only.');
        }
        $sql = "INSERT INTO {$this->attemptTable} (email, ip, success, context) VALUES (:email, INET6_ATON(:ip), :success, 'registration')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['email' => $email, 'ip' => $ip, 'success' => $success ? 1 : 0]);
    }

    public function beginTransaction(): void {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    public function commit(): void {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollBackIfActive(): void {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    /** Retourne seulement les champs de repertoire, jamais les secrets auth. */
    public function getAll(int $limit = 100, int $offset = 0): array {
        if ($this->profile !== 'admin') {
            throw new LogicException('User listing is restricted to the admin profile.');
        }
        $stmt = $this->pdo->prepare('SELECT id, nom, prenom, email, telephone, status, role, actif, raison_sociale, rccm, created_at, updated_at FROM user_directory ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue('limit', max(1, min($limit, 500)), PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countAll(): int {
        if ($this->profile !== 'admin') {
            throw new LogicException('User counting is restricted to the admin profile.');
        }
        return (int) $this->pdo->query('SELECT COUNT(*) FROM user_directory')->fetchColumn();
    }

    public function updateProfile(int $id, array $data): void {
        $this->requireAdminProfile();
        $stmt = $this->pdo->prepare('UPDATE admin_users SET nom = :nom, prenom = :prenom, email = :email, telephone = :telephone WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'telephone' => $data['telephone'],
        ]);
    }

    public function updatePassword(int $id, string $password): int {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('Password hashing failed.');
        }
        $stmt = $this->pdo->prepare('UPDATE ' . $this->userSource . ' SET mot_de_passe = :password, password_must_change = 0, password_changed_at = NOW(), auth_version = auth_version + 1 WHERE id = :id');
        $stmt->execute(['password' => $hash, 'id' => $id]);
        return $this->getAuthVersion($id);
    }

    public function bumpAuthVersion(int $id): int {
        $this->requireAdminProfile();
        $stmt = $this->pdo->prepare('UPDATE admin_users SET auth_version = auth_version + 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $this->getAuthVersion($id);
    }

    public function getAuthVersion(int $id): int {
        $stmt = $this->pdo->prepare('SELECT auth_version FROM ' . $this->userSource . ' WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn();
    }

    public function setPendingTotpSecret(int $id, string $secret): void {
        $this->requireAdminProfile();
        $encrypted = encryptSensitiveValue($secret);
        $stmt = $this->pdo->prepare('UPDATE admin_users SET totp_secret = :secret, totp_enabled = 0, totp_last_counter = NULL WHERE id = :id');
        $stmt->execute(['id' => $id, 'secret' => $encrypted]);
    }

    public function enableTotp(int $id, int $counter): int {
        $this->requireAdminProfile();
        $stmt = $this->pdo->prepare('UPDATE admin_users SET totp_enabled = 1, totp_last_counter = :counter, auth_version = auth_version + 1 WHERE id = :id');
        $stmt->execute(['counter' => $counter, 'id' => $id]);
        return $this->getAuthVersion($id);
    }

    /** Accepte un compteur une seule fois, de facon atomique. */
    public function acceptTotpCounter(int $id, int $counter): bool {
        $this->requireAdminProfile();
        $stmt = $this->pdo->prepare('UPDATE admin_users SET totp_last_counter = :new_counter WHERE id = :id AND (totp_last_counter IS NULL OR totp_last_counter < :compare_counter)');
        $stmt->execute(['new_counter' => $counter, 'compare_counter' => $counter, 'id' => $id]);
        return $stmt->rowCount() === 1;
    }

    public function disableTotp(int $id): int {
        $this->requireAdminProfile();
        $stmt = $this->pdo->prepare('UPDATE admin_users SET totp_enabled = 0, totp_secret = NULL, totp_last_counter = NULL, auth_version = auth_version + 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $this->getAuthVersion($id);
    }

    private function requireAdminProfile(): void {
        if ($this->profile !== 'admin') {
            throw new LogicException('This operation is restricted to the admin profile.');
        }
    }
}
