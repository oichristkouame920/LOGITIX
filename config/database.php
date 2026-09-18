<?php
/**
 * Connexion a la base de donnees LOGITIX.
 *
 * Deux comptes MySQL distincts :
 *   - client : authentification/inscription client avec privileges minimaux
 *   - admin  : authentification/profil admin avec privileges minimaux
 */

require_once __DIR__ . '/config.php';

class Database {
    private static array $instances = [];
    private PDO $pdo;

    private function __construct(string $profil) {
        if (!in_array($profil, ['client', 'admin'], true)) {
            throw new InvalidArgumentException('Profil de base de donnees invalide.');
        }

        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('Extension PHP pdo_mysql absente. Activez-la dans WampServer avant de continuer.');
        }

        $host = DB_HOST;
        $port = DB_PORT;
        $dbname = DB_NAME;
        $charset = DB_CHARSET;

        if ($profil === 'admin') {
            $user = DB_USER_ADMIN;
            $pass = DB_PASS_ADMIN;
        } else {
            $user = DB_USER_CLIENT;
            $pass = DB_PASS_CLIENT;
        }

        if ($user === '' || $pass === '') {
            throw new RuntimeException('Identifiants MySQL LOGITIX non configures pour le profil ' . $profil . '.');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 5,
        ];

        // Interdit les requetes multiples lorsqu'elles sont supportees par le
        // pilote PDO MySQL. C'est une defense supplementaire contre certaines
        // classes d'injection si une requete mal construite apparait plus tard.
        if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
            $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = false;
        }

        if (DB_REQUIRE_TLS) {
            if (DB_SSL_CA === '' || !is_readable(DB_SSL_CA)) {
                throw new RuntimeException('LOGITIX_DB_SSL_CA doit pointer vers un certificat CA MySQL lisible lorsque TLS est requis.');
            }
            if (!defined('PDO::MYSQL_ATTR_SSL_CA')) {
                throw new RuntimeException('Le pilote PDO MySQL ne fournit pas le support TLS attendu.');
            }
            $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
            if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
            }
        }

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log('LOGITIX DB connection failure [' . $profil . ']: ' . $e->getCode());
            if (APP_ENV === 'development') {
                throw $e;
            }
            throw new RuntimeException('Connexion a la base de donnees indisponible.');
        }
    }

    public static function getInstance(string $profil = 'client'): self {
        if (!in_array($profil, ['client', 'admin'], true)) {
            throw new InvalidArgumentException('Profil de base de donnees invalide.');
        }
        if (!isset(self::$instances[$profil])) {
            self::$instances[$profil] = new self($profil);
        }
        return self::$instances[$profil];
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchOne(string $sql, array $params = []): ?array {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = [], int $column = 0) {
        return $this->query($sql, $params)->fetchColumn($column);
    }

    public function lastInsertId(): string {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool {
        return $this->pdo->commit();
    }

    public function rollback(): bool {
        return $this->pdo->rollBack();
    }
}
