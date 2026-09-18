<?php
/**
 * LOGITIX v7.7.2 - mise a niveau NON DESTRUCTIVE des fonctions admin.
 *
 * Ce service ne connait aucun mot de passe et n'ouvre aucune connexion.
 * L'appelant fournit un PDO DBA. La migration est relancable et ne supprime
 * aucune donnee metier. Elle normalise egalement les privileges des comptes
 * applicatifs dedies afin d'eviter une derive de droits entre versions.
 */

function logitixQuoteIdentifier(string $name): string {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new InvalidArgumentException('Nom SQL invalide.');
    }
    return '`' . $name . '`';
}

function logitixRequireTable(PDO $pdo, string $db, string $table): void {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=:db AND TABLE_NAME=:t AND TABLE_TYPE='BASE TABLE'");
    $stmt->execute(['db' => $db, 't' => $table]);
    if ((int) $stmt->fetchColumn() !== 1) {
        throw new RuntimeException("Table requise absente : {$table}. Importez le SQL complet LOGITIX pour une installation neuve.");
    }
}

function logitixColumnExists(PDO $pdo, string $db, string $table, string $column): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=:db AND TABLE_NAME=:t AND COLUMN_NAME=:c');
    $stmt->execute(['db' => $db, 't' => $table, 'c' => $column]);
    return (int) $stmt->fetchColumn() === 1;
}

function logitixConstraintExists(PDO $pdo, string $db, string $table, string $constraint): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=:db AND TABLE_NAME=:t AND CONSTRAINT_NAME=:c');
    $stmt->execute(['db' => $db, 't' => $table, 'c' => $constraint]);
    return (int) $stmt->fetchColumn() === 1;
}

function logitixCheckClause(PDO $pdo, string $db, string $table, string $constraint): ?string {
    $stmt = $pdo->prepare(
        'SELECT cc.CHECK_CLAUSE
         FROM information_schema.TABLE_CONSTRAINTS tc
         JOIN information_schema.CHECK_CONSTRAINTS cc
           ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA
          AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME
         WHERE tc.CONSTRAINT_SCHEMA=:db
           AND tc.TABLE_NAME=:t
           AND tc.CONSTRAINT_NAME=:c
           AND tc.CONSTRAINT_TYPE=\'CHECK\'
         LIMIT 1'
    );
    $stmt->execute(['db' => $db, 't' => $table, 'c' => $constraint]);
    $value = $stmt->fetchColumn();
    return $value === false ? null : (string) $value;
}

function logitixIndexExists(PDO $pdo, string $db, string $table, string $index): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=:db AND TABLE_NAME=:t AND INDEX_NAME=:i');
    $stmt->execute(['db' => $db, 't' => $table, 'i' => $index]);
    return (int) $stmt->fetchColumn() > 0;
}

function logitixAccountExists(PDO $pdo, string $user, string $host): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM mysql.user WHERE User=:u AND Host=:h');
    $stmt->execute(['u' => $user, 'h' => $host]);
    return (int) $stmt->fetchColumn() === 1;
}

/** @return list<array<string,string>> */
function logitixForeignKeysForColumn(PDO $pdo, string $db, string $table, string $column): array {
    $stmt = $pdo->prepare(
        'SELECT kcu.CONSTRAINT_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME,
                rc.UPDATE_RULE, rc.DELETE_RULE
         FROM information_schema.KEY_COLUMN_USAGE kcu
         JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
           ON rc.CONSTRAINT_SCHEMA=kcu.CONSTRAINT_SCHEMA
          AND rc.TABLE_NAME=kcu.TABLE_NAME
          AND rc.CONSTRAINT_NAME=kcu.CONSTRAINT_NAME
         WHERE kcu.TABLE_SCHEMA=:db
           AND kcu.TABLE_NAME=:t
           AND kcu.COLUMN_NAME=:c
           AND kcu.REFERENCED_TABLE_NAME IS NOT NULL'
    );
    $stmt->execute(['db' => $db, 't' => $table, 'c' => $column]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function logitixEnsureRestrictForeignKey(
    PDO $pdo,
    string $db,
    string $table,
    string $column,
    string $constraint,
    string $referencedTable,
    string $referencedColumn = 'id'
): bool {
    $qdb = logitixQuoteIdentifier($db);
    $qtable = logitixQuoteIdentifier($table);
    $qcolumn = logitixQuoteIdentifier($column);
    $qconstraint = logitixQuoteIdentifier($constraint);
    $qrefTable = logitixQuoteIdentifier($referencedTable);
    $qrefColumn = logitixQuoteIdentifier($referencedColumn);

    $orphan = $pdo->query(
        "SELECT child.`id` FROM {$qdb}.{$qtable} child " .
        "LEFT JOIN {$qdb}.{$qrefTable} parent ON child.{$qcolumn}=parent.{$qrefColumn} " .
        "WHERE child.{$qcolumn} IS NOT NULL AND parent.{$qrefColumn} IS NULL LIMIT 1"
    )->fetch();
    if ($orphan) {
        throw new RuntimeException("Migration bloquee : {$table}.{$column} contient une reference orpheline vers {$referencedTable}.{$referencedColumn}.");
    }

    $foreignKeys = logitixForeignKeysForColumn($pdo, $db, $table, $column);
    if (count($foreignKeys) > 1) {
        throw new RuntimeException("Migration bloquee : plusieurs cles etrangeres utilisent {$table}.{$column}. Corrigez le schema avant la mise a niveau.");
    }

    if ($foreignKeys) {
        $fk = $foreignKeys[0];
        if (($fk['REFERENCED_TABLE_NAME'] ?? '') !== $referencedTable || ($fk['REFERENCED_COLUMN_NAME'] ?? '') !== $referencedColumn) {
            throw new RuntimeException("Migration bloquee : la cle etrangere de {$table}.{$column} ne pointe pas vers {$referencedTable}.{$referencedColumn}.");
        }
        $deleteRule = strtoupper((string) ($fk['DELETE_RULE'] ?? ''));
        $updateRule = strtoupper((string) ($fk['UPDATE_RULE'] ?? ''));
        $safeRules = ['RESTRICT', 'NO ACTION'];
        if (in_array($deleteRule, $safeRules, true) && in_array($updateRule, $safeRules, true)) {
            return false;
        }

        $existingConstraint = logitixQuoteIdentifier((string) $fk['CONSTRAINT_NAME']);
        $pdo->exec("ALTER TABLE {$qdb}.{$qtable} DROP FOREIGN KEY {$existingConstraint}");
    }

    if (logitixConstraintExists($pdo, $db, $table, $constraint)) {
        throw new RuntimeException("Migration bloquee : le nom de contrainte {$constraint} existe deja mais ne correspond pas a {$table}.{$column}.");
    }

    $pdo->exec(
        "ALTER TABLE {$qdb}.{$qtable} ADD CONSTRAINT {$qconstraint} " .
        "FOREIGN KEY ({$qcolumn}) REFERENCES {$qdb}.{$qrefTable} ({$qrefColumn}) " .
        "ON DELETE RESTRICT ON UPDATE RESTRICT"
    );
    return true;
}

function logitixAssertNoMigrationConflict(PDO $pdo, string $db): void {
    $qdb = logitixQuoteIdentifier($db);

    $row = $pdo->query("SELECT `id` FROM {$qdb}.`expeditions` WHERE `statut`='en_cours' AND (`vehicule_id` IS NULL OR `chauffeur_id` IS NULL OR `date_depart` IS NULL) LIMIT 1")->fetch();
    if ($row) {
        throw new RuntimeException('Migration bloquee : une expedition en cours ne possede pas de vehicule, chauffeur et/ou date de depart. Corrigez cette expedition avant la mise a niveau.');
    }

    $row = $pdo->query("SELECT `id` FROM {$qdb}.`expeditions` WHERE `statut`='livree' AND `date_livraison_reelle` IS NULL LIMIT 1")->fetch();
    if ($row) {
        throw new RuntimeException('Migration bloquee : une expedition livree ne possede pas de date de livraison reelle. Corrigez cette expedition avant la mise a niveau.');
    }

    $row = $pdo->query("SELECT `devis_id`, COUNT(*) AS c FROM {$qdb}.`expeditions` WHERE `devis_id` IS NOT NULL GROUP BY `devis_id` HAVING COUNT(*) > 1 LIMIT 1")->fetch();
    if ($row) {
        throw new RuntimeException('Migration bloquee : un meme devis est rattache a plusieurs expeditions. Corrigez ce doublon avant la mise a niveau.');
    }

    foreach ([
        ['vehicule_id', 'vehicule'],
        ['chauffeur_id', 'chauffeur'],
        ['remorque_id', 'remorque'],
    ] as [$field, $label]) {
        $row = $pdo->query("SELECT `{$field}`, COUNT(*) AS c FROM {$qdb}.`expeditions` WHERE `statut`='en_cours' AND `{$field}` IS NOT NULL GROUP BY `{$field}` HAVING COUNT(*) > 1 LIMIT 1")->fetch();
        if ($row) {
            throw new RuntimeException("Migration bloquee : le meme {$label} est affecte a plusieurs expeditions en cours.");
        }
    }
}

/** @return list<string> */
function logitixUpgradeAdminFeatures(PDO $pdo, string $db = 'logitix'): array {
    $messages = [];
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    if (stripos($version, 'MariaDB') !== false) {
        throw new RuntimeException('LOGITIX v7.7.2 cible MySQL, pas MariaDB.');
    }
    if (!preg_match('/^(\d+\.\d+\.\d+)/', $version, $m) || version_compare($m[1], '8.0.18', '<')) {
        throw new RuntimeException('MySQL 8.0.18 minimum requis; MySQL 8.4 LTS recommande.');
    }

    foreach ([
        'users', 'devis', 'expeditions', 'vehicules', 'chauffeurs', 'remorques',
        'entrepots', 'schema_migrations', 'auth_attempts_client', 'auth_attempts_admin',
    ] as $table) {
        logitixRequireTable($pdo, $db, $table);
    }

    foreach ([
        ['logitix_client_view_definer', 'localhost'],
        ['logitix_admin_view_definer', 'localhost'],
        ['logitix_client', '127.0.0.1'],
        ['logitix_admin', '127.0.0.1'],
    ] as [$user, $host]) {
        if (!logitixAccountExists($pdo, $user, $host)) {
            throw new RuntimeException("Compte MySQL requis absent : {$user}@{$host}. Utilisez le SQL complet pour une installation neuve.");
        }
    }

    $qdb = logitixQuoteIdentifier($db);
    $pdo->exec("USE {$qdb}");

    $columns = [
        'montant_propose' => "ALTER TABLE {$qdb}.`devis` ADD COLUMN `montant_propose` DECIMAL(14,2) DEFAULT NULL AFTER `statut`",
        'devise' => "ALTER TABLE {$qdb}.`devis` ADD COLUMN `devise` CHAR(3) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'XOF' AFTER `montant_propose`",
        'reponse_admin' => "ALTER TABLE {$qdb}.`devis` ADD COLUMN `reponse_admin` TEXT DEFAULT NULL AFTER `devise`",
        'date_traitement' => "ALTER TABLE {$qdb}.`devis` ADD COLUMN `date_traitement` DATETIME DEFAULT NULL AFTER `reponse_admin`",
    ];
    foreach ($columns as $name => $sql) {
        if (!logitixColumnExists($pdo, $db, 'devis', $name)) {
            $pdo->exec($sql);
            $messages[] = "Colonne devis ajoutee : {$name}";
        }
    }

    if (!logitixConstraintExists($pdo, $db, 'devis', 'chk_devis_montant')) {
        $pdo->exec("ALTER TABLE {$qdb}.`devis` ADD CONSTRAINT `chk_devis_montant` CHECK (`montant_propose` IS NULL OR `montant_propose` >= 0)");
        $messages[] = 'Contrainte montant devis ajoutee.';
    }
    if (!logitixConstraintExists($pdo, $db, 'devis', 'chk_devis_devise')) {
        $pdo->exec("ALTER TABLE {$qdb}.`devis` ADD CONSTRAINT `chk_devis_devise` CHECK (`devise` REGEXP '^[A-Z]{3}$')");
        $messages[] = 'Contrainte devise devis ajoutee.';
    }

    // MySQL 8.0/8.4 interdit les actions referentielles CASCADE/SET NULL sur
    // les colonnes utilisees par un CHECK et sur les colonnes de base de nos
    // colonnes generees active_*. Les ressources sont donc archivees par leur
    // statut et leurs FK restent en RESTRICT : aucune suppression implicite ne
    // peut rendre une expedition incoherente.
    foreach ([
        ['vehicule_id', 'fk_expeditions_vehicule', 'vehicules'],
        ['chauffeur_id', 'fk_expeditions_chauffeur', 'chauffeurs'],
        ['remorque_id', 'fk_expeditions_remorque', 'remorques'],
    ] as [$column, $constraint, $parentTable]) {
        if (logitixEnsureRestrictForeignKey($pdo, $db, 'expeditions', $column, $constraint, $parentTable)) {
            $messages[] = "Cle etrangere {$column} normalisee en RESTRICT (compatibilite MySQL 8.x).";
        }
    }

    logitixAssertNoMigrationConflict($pdo, $db);

    $generatedColumns = [
        'active_vehicule_id' => "ALTER TABLE {$qdb}.`expeditions` ADD COLUMN `active_vehicule_id` INT GENERATED ALWAYS AS (CASE WHEN `statut`='en_cours' THEN `vehicule_id` ELSE NULL END) STORED",
        'active_chauffeur_id' => "ALTER TABLE {$qdb}.`expeditions` ADD COLUMN `active_chauffeur_id` INT GENERATED ALWAYS AS (CASE WHEN `statut`='en_cours' THEN `chauffeur_id` ELSE NULL END) STORED",
        'active_remorque_id' => "ALTER TABLE {$qdb}.`expeditions` ADD COLUMN `active_remorque_id` INT GENERATED ALWAYS AS (CASE WHEN `statut`='en_cours' THEN `remorque_id` ELSE NULL END) STORED",
    ];
    foreach ($generatedColumns as $name => $sql) {
        if (!logitixColumnExists($pdo, $db, 'expeditions', $name)) {
            $pdo->exec($sql);
            $messages[] = "Protection concurrence ajoutee : {$name}";
        }
    }

    foreach ([
        'uq_expeditions_devis' => '(`devis_id`)',
        'uq_expeditions_active_vehicule' => '(`active_vehicule_id`)',
        'uq_expeditions_active_chauffeur' => '(`active_chauffeur_id`)',
        'uq_expeditions_active_remorque' => '(`active_remorque_id`)',
    ] as $index => $columnsSql) {
        if (!logitixIndexExists($pdo, $db, 'expeditions', $index)) {
            $pdo->exec("ALTER TABLE {$qdb}.`expeditions` ADD UNIQUE KEY `{$index}` {$columnsSql}");
            $messages[] = "Index d'integrite ajoute : {$index}";
        }
    }

    $activeResourceClause = logitixCheckClause($pdo, $db, 'expeditions', 'chk_expeditions_active_resources');
    if ($activeResourceClause !== null && stripos($activeResourceClause, 'date_depart') === false) {
        // v7.7 avait une version plus faible de cette contrainte. On la
        // remplace sans toucher aux donnees apres le controle de conflit.
        $pdo->exec("ALTER TABLE {$qdb}.`expeditions` DROP CHECK `chk_expeditions_active_resources`");
        $activeResourceClause = null;
        $messages[] = 'Contrainte expedition v7.7 renforcee pour la date de depart.';
    }
    if ($activeResourceClause === null) {
        $pdo->exec("ALTER TABLE {$qdb}.`expeditions` ADD CONSTRAINT `chk_expeditions_active_resources` CHECK (`statut` <> 'en_cours' OR (`vehicule_id` IS NOT NULL AND `chauffeur_id` IS NOT NULL AND `date_depart` IS NOT NULL))");
        $messages[] = 'Contrainte ressources/date expedition ajoutee.';
    }
    if (!logitixConstraintExists($pdo, $db, 'expeditions', 'chk_expeditions_delivered_date')) {
        $pdo->exec("ALTER TABLE {$qdb}.`expeditions` ADD CONSTRAINT `chk_expeditions_delivered_date` CHECK (`statut` <> 'livree' OR `date_livraison_reelle` IS NOT NULL)");
        $messages[] = 'Contrainte date de livraison ajoutee.';
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS {$qdb}.`admin_audit_log` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `actor_user_id` INT NOT NULL,
        `action` VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        `entity_type` VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        `entity_id` INT DEFAULT NULL,
        `details` TEXT DEFAULT NULL,
        `ip` VARBINARY(16) NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_admin_audit_actor_time` (`actor_user_id`,`created_at`),
        KEY `idx_admin_audit_entity` (`entity_type`,`entity_id`,`created_at`),
        CONSTRAINT `fk_admin_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES {$qdb}.`users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
        CONSTRAINT `chk_admin_audit_ip` CHECK (OCTET_LENGTH(`ip`) IN (4,16))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Repart d'un jeu de privileges connu sur les comptes dedies. Cela ne
    // change pas leurs mots de passe.
    $accounts = [
        ['logitix_client_view_definer', 'localhost'],
        ['logitix_admin_view_definer', 'localhost'],
        ['logitix_client', '127.0.0.1'],
        ['logitix_admin', '127.0.0.1'],
    ];
    foreach ($accounts as [$accountUser, $accountHost]) {
        $account = $pdo->quote($accountUser) . '@' . $pdo->quote($accountHost);
        $pdo->exec("REVOKE ALL PRIVILEGES, GRANT OPTION FROM {$account}");

        // Les roles eventuellement ajoutes par erreur sont aussi retires.
        $roleStmt = $pdo->prepare('SELECT FROM_USER, FROM_HOST FROM mysql.role_edges WHERE TO_USER=:u AND TO_HOST=:h');
        $roleStmt->execute(['u' => $accountUser, 'h' => $accountHost]);
        foreach ($roleStmt->fetchAll() as $role) {
            $roleAccount = $pdo->quote((string) $role['FROM_USER']) . '@' . $pdo->quote((string) $role['FROM_HOST']);
            $pdo->exec("REVOKE {$roleAccount} FROM {$account}");
        }
    }
    $pdo->exec("ALTER USER 'logitix_client_view_definer'@'localhost' PASSWORD EXPIRE NEVER ACCOUNT LOCK");
    $pdo->exec("ALTER USER 'logitix_admin_view_definer'@'localhost' PASSWORD EXPIRE NEVER ACCOUNT LOCK");
    $pdo->exec("ALTER USER 'logitix_client'@'127.0.0.1' PASSWORD EXPIRE NEVER ACCOUNT UNLOCK");
    $pdo->exec("ALTER USER 'logitix_admin'@'127.0.0.1' PASSWORD EXPIRE NEVER ACCOUNT UNLOCK");
    $pdo->exec("SET DEFAULT ROLE NONE TO 'logitix_client'@'127.0.0.1', 'logitix_admin'@'127.0.0.1'");

    // Definer client : compte, inscription, changement de mot de passe et
    // donnees metier client. Aucun DELETE.
    $pdo->exec("GRANT SELECT (`id`,`nom`,`prenom`,`email`,`telephone`,`status`,`role`,`mot_de_passe`,`password_must_change`,`password_changed_at`,`raison_sociale`,`rccm`,`actif`,`auth_version`,`created_at`,`updated_at`) ON {$qdb}.`users` TO 'logitix_client_view_definer'@'localhost'");
    $pdo->exec("GRANT INSERT (`nom`,`prenom`,`email`,`telephone`,`status`,`mot_de_passe`,`raison_sociale`,`rccm`) ON {$qdb}.`users` TO 'logitix_client_view_definer'@'localhost'");
    $pdo->exec("GRANT UPDATE (`mot_de_passe`,`password_must_change`,`password_changed_at`,`auth_version`) ON {$qdb}.`users` TO 'logitix_client_view_definer'@'localhost'");
    $pdo->exec("GRANT SELECT (`id`,`user_id`,`depart`,`destination`,`type_marchandise`,`poids_estime`,`date_souhaitee`,`message`,`statut`,`montant_propose`,`devise`,`reponse_admin`,`date_traitement`,`date_creation`) ON {$qdb}.`devis` TO 'logitix_client_view_definer'@'localhost'");
    $pdo->exec("GRANT INSERT (`user_id`,`depart`,`destination`,`type_marchandise`,`poids_estime`,`date_souhaitee`,`message`) ON {$qdb}.`devis` TO 'logitix_client_view_definer'@'localhost'");
    $pdo->exec("GRANT SELECT (`id`,`devis_id`,`user_id`,`reference`,`depart`,`destination`,`statut`,`date_depart`,`date_livraison_estimee`,`date_livraison_reelle`,`date_creation`) ON {$qdb}.`expeditions` TO 'logitix_client_view_definer'@'localhost'");
    $pdo->exec("GRANT INSERT (`user_id`,`reference`,`depart`,`destination`,`date_depart`) ON {$qdb}.`expeditions` TO 'logitix_client_view_definer'@'localhost'");

    // Definer admin : colonnes auth/profil + vues metier explicites.
    $pdo->exec("GRANT SELECT (`id`,`nom`,`prenom`,`email`,`telephone`,`status`,`role`,`mot_de_passe`,`password_must_change`,`password_changed_at`,`totp_secret`,`totp_enabled`,`totp_last_counter`,`raison_sociale`,`rccm`,`actif`,`auth_version`,`created_at`,`updated_at`) ON {$qdb}.`users` TO 'logitix_admin_view_definer'@'localhost'");
    $pdo->exec("GRANT UPDATE (`nom`,`prenom`,`email`,`telephone`,`mot_de_passe`,`password_must_change`,`password_changed_at`,`actif`,`auth_version`,`totp_secret`,`totp_enabled`,`totp_last_counter`) ON {$qdb}.`users` TO 'logitix_admin_view_definer'@'localhost'");
    $pdo->exec("GRANT SELECT (`id`,`user_id`,`depart`,`destination`,`type_marchandise`,`poids_estime`,`date_souhaitee`,`message`,`statut`,`montant_propose`,`devise`,`reponse_admin`,`date_traitement`,`date_creation`) ON {$qdb}.`devis` TO 'logitix_admin_view_definer'@'localhost'");
    $pdo->exec("GRANT UPDATE (`statut`,`montant_propose`,`devise`,`reponse_admin`,`date_traitement`) ON {$qdb}.`devis` TO 'logitix_admin_view_definer'@'localhost'");
    $pdo->exec("GRANT SELECT (`id`,`devis_id`,`user_id`,`vehicule_id`,`chauffeur_id`,`remorque_id`,`reference`,`depart`,`destination`,`statut`,`date_depart`,`date_livraison_estimee`,`date_livraison_reelle`,`date_creation`) ON {$qdb}.`expeditions` TO 'logitix_admin_view_definer'@'localhost'");
    $pdo->exec("GRANT INSERT (`devis_id`,`user_id`,`reference`,`depart`,`destination`,`date_depart`,`date_livraison_estimee`) ON {$qdb}.`expeditions` TO 'logitix_admin_view_definer'@'localhost'");
    $pdo->exec("GRANT UPDATE (`vehicule_id`,`chauffeur_id`,`remorque_id`,`statut`,`date_depart`,`date_livraison_estimee`,`date_livraison_reelle`) ON {$qdb}.`expeditions` TO 'logitix_admin_view_definer'@'localhost'");
    $pdo->exec("GRANT SELECT, INSERT, UPDATE ON {$qdb}.`vehicules` TO 'logitix_admin_view_definer'@'localhost'");
    $pdo->exec("GRANT SELECT, INSERT, UPDATE ON {$qdb}.`chauffeurs` TO 'logitix_admin_view_definer'@'localhost'");
    $pdo->exec("GRANT SELECT, INSERT, UPDATE ON {$qdb}.`remorques` TO 'logitix_admin_view_definer'@'localhost'");
    $pdo->exec("GRANT SELECT, INSERT, UPDATE ON {$qdb}.`entrepots` TO 'logitix_admin_view_definer'@'localhost'");

    foreach ([
        'client_users', 'admin_users', 'user_emails', 'user_directory',
        'client_devis', 'client_expeditions', 'admin_client_users', 'admin_devis',
        'admin_expeditions', 'admin_vehicules', 'admin_chauffeurs',
        'admin_remorques', 'admin_entrepots',
    ] as $view) {
        $pdo->exec('DROP VIEW IF EXISTS ' . $qdb . '.' . logitixQuoteIdentifier($view));
    }

    $pdo->exec("CREATE ALGORITHM=MERGE DEFINER='logitix_client_view_definer'@'localhost' SQL SECURITY DEFINER VIEW {$qdb}.`client_users` AS
        SELECT `id`,`nom`,`prenom`,`email`,`telephone`,`status`,`role`,`mot_de_passe`,`password_must_change`,`password_changed_at`,`raison_sociale`,`rccm`,`actif`,`auth_version`,`created_at`,`updated_at`
        FROM {$qdb}.`users` WHERE `role`='client' WITH CASCADED CHECK OPTION");
    $pdo->exec("CREATE ALGORITHM=MERGE DEFINER='logitix_admin_view_definer'@'localhost' SQL SECURITY DEFINER VIEW {$qdb}.`admin_users` AS
        SELECT `id`,`nom`,`prenom`,`email`,`telephone`,`status`,`role`,`mot_de_passe`,`password_must_change`,`password_changed_at`,`totp_secret`,`totp_enabled`,`totp_last_counter`,`actif`,`auth_version`,`created_at`,`updated_at`
        FROM {$qdb}.`users` WHERE `role`='admin' WITH CASCADED CHECK OPTION");
    $pdo->exec("CREATE ALGORITHM=MERGE DEFINER='logitix_admin_view_definer'@'localhost' SQL SECURITY DEFINER VIEW {$qdb}.`user_emails` AS SELECT `id`,`email` FROM {$qdb}.`users`");
    $pdo->exec("CREATE ALGORITHM=MERGE DEFINER='logitix_admin_view_definer'@'localhost' SQL SECURITY DEFINER VIEW {$qdb}.`user_directory` AS
        SELECT `id`,`nom`,`prenom`,`email`,`telephone`,`status`,`role`,`raison_sociale`,`rccm`,`actif`,`created_at`,`updated_at` FROM {$qdb}.`users`");

    $pdo->exec("CREATE ALGORITHM=MERGE DEFINER='logitix_client_view_definer'@'localhost' SQL SECURITY DEFINER VIEW {$qdb}.`client_devis` AS
        SELECT `id`,`user_id`,`depart`,`destination`,`type_marchandise`,`poids_estime`,`date_souhaitee`,`message`,`statut`,`montant_propose`,`devise`,`reponse_admin`,`date_traitement`,`date_creation` FROM {$qdb}.`devis`");
    $pdo->exec("CREATE ALGORITHM=MERGE DEFINER='logitix_client_view_definer'@'localhost' SQL SECURITY DEFINER VIEW {$qdb}.`client_expeditions` AS
        SELECT `id`,`devis_id`,`user_id`,`reference`,`depart`,`destination`,`statut`,`date_depart`,`date_livraison_estimee`,`date_livraison_reelle`,`date_creation` FROM {$qdb}.`expeditions`");

    $pdo->exec("CREATE ALGORITHM=MERGE DEFINER='logitix_admin_view_definer'@'localhost' SQL SECURITY DEFINER VIEW {$qdb}.`admin_client_users` AS
        SELECT `id`,`nom`,`prenom`,`email`,`telephone`,`status`,`mot_de_passe`,`password_must_change`,`password_changed_at`,`raison_sociale`,`rccm`,`actif`,`auth_version`,`created_at`,`updated_at`
        FROM {$qdb}.`users` WHERE `role`='client' WITH CASCADED CHECK OPTION");
    $pdo->exec("CREATE ALGORITHM=MERGE DEFINER='logitix_admin_view_definer'@'localhost' SQL SECURITY DEFINER VIEW {$qdb}.`admin_devis` AS
        SELECT `id`,`user_id`,`depart`,`destination`,`type_marchandise`,`poids_estime`,`date_souhaitee`,`message`,`statut`,`montant_propose`,`devise`,`reponse_admin`,`date_traitement`,`date_creation` FROM {$qdb}.`devis`");
    $pdo->exec("CREATE ALGORITHM=MERGE DEFINER='logitix_admin_view_definer'@'localhost' SQL SECURITY DEFINER VIEW {$qdb}.`admin_expeditions` AS
        SELECT `id`,`devis_id`,`user_id`,`vehicule_id`,`chauffeur_id`,`remorque_id`,`reference`,`depart`,`destination`,`statut`,`date_depart`,`date_livraison_estimee`,`date_livraison_reelle`,`date_creation` FROM {$qdb}.`expeditions`");
    $pdo->exec("CREATE ALGORITHM=MERGE DEFINER='logitix_admin_view_definer'@'localhost' SQL SECURITY DEFINER VIEW {$qdb}.`admin_vehicules` AS SELECT `id`,`nom`,`categorie`,`immatriculation`,`capacite`,`statut` FROM {$qdb}.`vehicules`");
    $pdo->exec("CREATE ALGORITHM=MERGE DEFINER='logitix_admin_view_definer'@'localhost' SQL SECURITY DEFINER VIEW {$qdb}.`admin_chauffeurs` AS SELECT `id`,`nom`,`prenom`,`permis`,`telephone`,`disponible` FROM {$qdb}.`chauffeurs`");
    $pdo->exec("CREATE ALGORITHM=MERGE DEFINER='logitix_admin_view_definer'@'localhost' SQL SECURITY DEFINER VIEW {$qdb}.`admin_remorques` AS SELECT `id`,`nom`,`type`,`capacite`,`statut` FROM {$qdb}.`remorques`");
    $pdo->exec("CREATE ALGORITHM=MERGE DEFINER='logitix_admin_view_definer'@'localhost' SQL SECURITY DEFINER VIEW {$qdb}.`admin_entrepots` AS SELECT `id`,`nom`,`ville`,`pays` FROM {$qdb}.`entrepots`");

    // Compte PHP client : aucun acces direct a users/devis/expeditions.
    $pdo->exec("GRANT SELECT (`id`,`nom`,`prenom`,`email`,`telephone`,`status`,`role`,`mot_de_passe`,`password_must_change`,`password_changed_at`,`actif`,`auth_version`) ON {$qdb}.`client_users` TO 'logitix_client'@'127.0.0.1'");
    $pdo->exec("GRANT INSERT (`nom`,`prenom`,`email`,`telephone`,`status`,`mot_de_passe`,`raison_sociale`,`rccm`) ON {$qdb}.`client_users` TO 'logitix_client'@'127.0.0.1'");
    $pdo->exec("GRANT UPDATE (`mot_de_passe`,`password_must_change`,`password_changed_at`,`auth_version`) ON {$qdb}.`client_users` TO 'logitix_client'@'127.0.0.1'");
    $pdo->exec("GRANT SELECT ON {$qdb}.`auth_attempts_client` TO 'logitix_client'@'127.0.0.1'");
    $pdo->exec("GRANT INSERT (`email`,`ip`,`success`,`context`) ON {$qdb}.`auth_attempts_client` TO 'logitix_client'@'127.0.0.1'");
    $pdo->exec("GRANT SELECT (`id`,`user_id`,`depart`,`destination`,`type_marchandise`,`poids_estime`,`date_souhaitee`,`message`,`statut`,`montant_propose`,`devise`,`reponse_admin`,`date_traitement`,`date_creation`) ON {$qdb}.`client_devis` TO 'logitix_client'@'127.0.0.1'");
    $pdo->exec("GRANT INSERT (`user_id`,`depart`,`destination`,`type_marchandise`,`poids_estime`,`date_souhaitee`,`message`) ON {$qdb}.`client_devis` TO 'logitix_client'@'127.0.0.1'");
    $pdo->exec("GRANT SELECT (`id`,`devis_id`,`user_id`,`reference`,`depart`,`destination`,`statut`,`date_depart`,`date_livraison_estimee`,`date_livraison_reelle`,`date_creation`) ON {$qdb}.`client_expeditions` TO 'logitix_client'@'127.0.0.1'");
    $pdo->exec("GRANT INSERT (`user_id`,`reference`,`depart`,`destination`,`date_depart`) ON {$qdb}.`client_expeditions` TO 'logitix_client'@'127.0.0.1'");

    // Compte PHP admin : auth/profil + vues metier. Aucun DELETE et aucun
    // SELECT du hash mot_de_passe des comptes clients.
    $pdo->exec("GRANT SELECT (`id`,`nom`,`prenom`,`email`,`telephone`,`status`,`role`,`mot_de_passe`,`password_must_change`,`password_changed_at`,`totp_secret`,`totp_enabled`,`totp_last_counter`,`actif`,`auth_version`) ON {$qdb}.`admin_users` TO 'logitix_admin'@'127.0.0.1'");
    $pdo->exec("GRANT UPDATE (`nom`,`prenom`,`email`,`telephone`,`mot_de_passe`,`password_must_change`,`password_changed_at`,`auth_version`,`totp_secret`,`totp_enabled`,`totp_last_counter`) ON {$qdb}.`admin_users` TO 'logitix_admin'@'127.0.0.1'");
    $pdo->exec("GRANT SELECT (`id`,`email`) ON {$qdb}.`user_emails` TO 'logitix_admin'@'127.0.0.1'");
    $pdo->exec("GRANT SELECT ON {$qdb}.`user_directory` TO 'logitix_admin'@'127.0.0.1'");
    $pdo->exec("GRANT SELECT ON {$qdb}.`auth_attempts_admin` TO 'logitix_admin'@'127.0.0.1'");
    $pdo->exec("GRANT INSERT (`email`,`ip`,`success`,`context`) ON {$qdb}.`auth_attempts_admin` TO 'logitix_admin'@'127.0.0.1'");
    $pdo->exec("GRANT SELECT (`id`,`nom`,`prenom`,`email`,`telephone`,`status`,`raison_sociale`,`rccm`,`actif`,`auth_version`,`password_must_change`,`password_changed_at`,`created_at`,`updated_at`) ON {$qdb}.`admin_client_users` TO 'logitix_admin'@'127.0.0.1'");
    $pdo->exec("GRANT UPDATE (`mot_de_passe`,`password_must_change`,`password_changed_at`,`actif`,`auth_version`) ON {$qdb}.`admin_client_users` TO 'logitix_admin'@'127.0.0.1'");
    $pdo->exec("GRANT SELECT ON {$qdb}.`admin_devis` TO 'logitix_admin'@'127.0.0.1'");
    $pdo->exec("GRANT UPDATE (`statut`,`montant_propose`,`devise`,`reponse_admin`,`date_traitement`) ON {$qdb}.`admin_devis` TO 'logitix_admin'@'127.0.0.1'");
    $pdo->exec("GRANT SELECT ON {$qdb}.`admin_expeditions` TO 'logitix_admin'@'127.0.0.1'");
    $pdo->exec("GRANT INSERT (`devis_id`,`user_id`,`reference`,`depart`,`destination`,`date_depart`,`date_livraison_estimee`) ON {$qdb}.`admin_expeditions` TO 'logitix_admin'@'127.0.0.1'");
    $pdo->exec("GRANT UPDATE (`vehicule_id`,`chauffeur_id`,`remorque_id`,`statut`,`date_depart`,`date_livraison_estimee`,`date_livraison_reelle`) ON {$qdb}.`admin_expeditions` TO 'logitix_admin'@'127.0.0.1'");
    foreach (['admin_vehicules', 'admin_chauffeurs', 'admin_remorques', 'admin_entrepots'] as $view) {
        $pdo->exec("GRANT SELECT, INSERT, UPDATE ON {$qdb}." . logitixQuoteIdentifier($view) . " TO 'logitix_admin'@'127.0.0.1'");
    }
    $pdo->exec("GRANT SELECT (`id`,`actor_user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip`,`created_at`) ON {$qdb}.`admin_audit_log` TO 'logitix_admin'@'127.0.0.1'");
    $pdo->exec("GRANT INSERT (`actor_user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip`) ON {$qdb}.`admin_audit_log` TO 'logitix_admin'@'127.0.0.1'");

    $stmt = $pdo->prepare("INSERT INTO {$qdb}.`schema_migrations` (`version`,`description`) VALUES ('7.7.2',:d) ON DUPLICATE KEY UPDATE `description`=VALUES(`description`), `applied_at`=CURRENT_TIMESTAMP");
    $stmt->execute(['d' => 'Admin interface active, local bootstrap, least-privilege normalization, audit log and concurrent resource assignment guards']);

    $messages[] = 'Vues client/admin v7.7.2 creees ou normalisees.';
    $messages[] = 'Privileges minimaux reinitialises et reappliques sans changer les mots de passe MySQL.';
    $messages[] = 'Protections anti-double-affectation actives au niveau MySQL.';
    $messages[] = 'Journal admin actif.';
    $messages[] = 'Aucune donnee utilisateur, devis ou expedition n a ete supprimee.';

    $extra = $pdo->query("SELECT CONCAT(User,'@',Host) AS account_name FROM mysql.user WHERE User IN ('logitix_client','logitix_admin') AND NOT (Host='127.0.0.1') ORDER BY User,Host")->fetchAll(PDO::FETCH_COLUMN);
    if ($extra) {
        $messages[] = 'ATTENTION : comptes MySQL homonymes sur d autres hosts detectes : ' . implode(', ', $extra) . '. Ils ne sont pas utilises par la configuration standard et doivent etre verifies manuellement.';
    }

    return $messages;
}
