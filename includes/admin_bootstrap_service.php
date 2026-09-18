<?php
/**
 * Service commun d'activation/reinitialisation du compte administrateur.
 *
 * Le PDO fourni doit etre un compte DBA temporaire. Aucun identifiant DBA
 * n'est stocke. Le mot de passe temporaire est retourne une seule fois a
 * l'appelant et n'est jamais journalise.
 */

/**
 * @return array{user_id:int,email:string,password:string,created:bool}
 */
function logitixBootstrapAdminAccount(PDO $pdo, string $email, bool $forceReset = false): array {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        throw new InvalidArgumentException('Email administrateur invalide.');
    }

    $startedTransaction = false;
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $startedTransaction = true;
    }

    try {
        $stmt = $pdo->query("SELECT `id`,`email`,`actif` FROM `users` WHERE `role`='admin' ORDER BY `id` ASC LIMIT 1 FOR UPDATE");
        $admin = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($admin && !empty($admin['actif']) && !$forceReset) {
            throw new RuntimeException('Un administrateur actif existe deja. Utilisez la reinitialisation forcee uniquement si vous souhaitez vraiment remplacer son mot de passe et sa 2FA.');
        }

        // Refuse d'ecraser l'email d'un autre utilisateur.
        $emailCheck = $pdo->prepare("SELECT `id`,`role` FROM `users` WHERE `email`=:email LIMIT 1 FOR UPDATE");
        $emailCheck->execute(['email' => $email]);
        $emailOwner = $emailCheck->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($emailOwner && (!$admin || (int) $emailOwner['id'] !== (int) $admin['id'])) {
            throw new RuntimeException('Cet email est deja utilise par un autre compte LOGITIX.');
        }

        $temporaryPassword = 'Tmp!' . bin2hex(random_bytes(12)) . 'A7';
        $hash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('Generation du hash impossible.');
        }

        $created = false;
        if ($admin) {
            $update = $pdo->prepare(
                "UPDATE `users`
                 SET `email`=:email,
                     `mot_de_passe`=:password,
                     `password_must_change`=1,
                     `password_changed_at`=NULL,
                     `actif`=1,
                     `auth_version`=`auth_version`+1,
                     `totp_secret`=NULL,
                     `totp_enabled`=0,
                     `totp_last_counter`=NULL
                 WHERE `id`=:id AND `role`='admin'"
            );
            $update->execute([
                'email' => $email,
                'password' => $hash,
                'id' => (int) $admin['id'],
            ]);
            $userId = (int) $admin['id'];
        } else {
            $insert = $pdo->prepare(
                "INSERT INTO `users`
                 (`nom`,`prenom`,`email`,`telephone`,`status`,`role`,`mot_de_passe`,`password_must_change`,`password_changed_at`,`actif`,`auth_version`)
                 VALUES ('Admin','LOGITIX',:email,'0000000000','particulier','admin',:password,1,NULL,1,1)"
            );
            $insert->execute(['email' => $email, 'password' => $hash]);
            $userId = (int) $pdo->lastInsertId();
            $created = true;
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        return [
            'user_id' => $userId,
            'email' => $email,
            'password' => $temporaryPassword,
            'created' => $created,
        ];
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
