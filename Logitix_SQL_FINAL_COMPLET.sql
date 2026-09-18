-- =====================================================================
-- LOGITIX - MySQL Security v7.7.2 - ALL-IN-ONE / INSTALLATION NEUVE
-- =====================================================================
-- Cible : MySQL Community/Enterprise 8.0.18+ ; recommande : MySQL 8.4 LTS.
-- NE PAS utiliser ce fichier sur MariaDB.
-- Executer avec un compte DBA/root.
--
-- ATTENTION : ce script est destine a une installation neuve. Par defaut,
-- il REFUSE d'ecraser une base `logitix` qui contient deja des objets.
-- Une reinstallation destructive exige une activation explicite du garde-fou.
--
-- Securite v7.7.2 :
--   * aucun secret applicatif n'est stocke dans ce fichier ;
--   * comptes PHP recrees avec caching_sha2_password + mot de passe aleatoire ;
--   * comptes DEFINER separes, verrouilles et non utilisables en connexion ;
--   * vues avec DEFINER explicite (jamais root implicite) ;
--   * aucun droit PHP direct sur la table users ;
--   * privileges par colonne et aucun GRANT OPTION ;
--   * aucune attribution de role aux comptes applicatifs ;
--   * 2FA chiffree, anti-rejeu, auth_version et rate limiting durcis;
--   * contraintes SQL alignees sur les validations PHP;
--   * verification finale BLOQUANTE des invariants critiques.
--
-- IMPORTANT : les deux CREATE USER applicatifs en fin de fichier retournent
-- chacun un mot de passe aleatoire EN CLAIR une seule fois. Copiez-les dans
-- .env.local via tools/setup_local_env.php (stockage base64 local).
-- =====================================================================

SELECT VERSION() AS mysql_version;

-- IMPORTANT - MODE ALL-IN-ONE
-- Ce fichier est la SEULE source SQL active du projet.
-- Il est destine a une INSTALLATION NEUVE et recree le schema LOGITIX.
-- Si vous avez deja des donnees utiles, faites une sauvegarde avant import.
-- Les reinitialisations des mots de passe UTILISATEURS se font ensuite avec
-- `php tools/reset_user_password.php` et non avec un second fichier SQL.
-- Le compte applicatif est cree pour 127.0.0.1. Le PORT MySQL est configurable
-- dans .env.local (LOGITIX_DB_PORT) et n'affecte pas l'identite MySQL du compte.

CREATE DATABASE IF NOT EXISTS `logitix`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `logitix`;

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- =====================================================================
-- 0. GARDE-FOU ANTI-ECRASEMENT
-- =====================================================================
-- Par defaut, ce fichier REFUSE de supprimer une installation LOGITIX deja
-- presente. Pour une reinstallation volontaire apres sauvegarde, remplacez
-- temporairement 0 par 1 sur la ligne suivante, importez, puis remettez 0.
SET @LOGITIX_ALLOW_DESTRUCTIVE_REINSTALL = 0;

DROP PROCEDURE IF EXISTS `__logitix_assert_fresh_install`;
DELIMITER $$
CREATE PROCEDURE `__logitix_assert_fresh_install`()
SQL SECURITY INVOKER
BEGIN
    DECLARE existing_objects INT DEFAULT 0;
    DECLARE mysql_major INT DEFAULT 0;
    DECLARE mysql_minor INT DEFAULT 0;
    DECLARE mysql_patch INT DEFAULT 0;

    IF VERSION() LIKE '%MariaDB%' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'LOGITIX: ce SQL cible MySQL uniquement, pas MariaDB.';
    END IF;

    SET mysql_major = CAST(SUBSTRING_INDEX(VERSION(), '.', 1) AS UNSIGNED);
    SET mysql_minor = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(VERSION(), '.', 2), '.', -1) AS UNSIGNED);
    SET mysql_patch = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(VERSION(), '.', 3), '.', -1) AS UNSIGNED);

    IF mysql_major < 8 OR (mysql_major = 8 AND mysql_minor = 0 AND mysql_patch < 18) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'LOGITIX: MySQL 8.0.18 minimum requis; MySQL 8.4 LTS recommande.';
    END IF;

    SELECT COUNT(*) INTO existing_objects
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = 'logitix';

    IF existing_objects > 0 AND COALESCE(@LOGITIX_ALLOW_DESTRUCTIVE_REINSTALL, 0) <> 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'LOGITIX: installation existante detectee. Sauvegardez la base puis passez @LOGITIX_ALLOW_DESTRUCTIVE_REINSTALL a 1 uniquement pour une reinstallation volontaire.';
    END IF;
END$$
DELIMITER ;
CALL `__logitix_assert_fresh_install`();
DROP PROCEDURE IF EXISTS `__logitix_assert_fresh_install`;

SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- 1. NETTOYAGE CONTROLE
-- =====================================================================
-- Les vues sont supprimees avant les comptes DEFINER. Depuis MySQL 8.0.22,
-- DROP USER peut refuser de supprimer un compte encore utilise comme DEFINER.
DROP VIEW IF EXISTS `client_users`;
DROP VIEW IF EXISTS `admin_users`;
DROP VIEW IF EXISTS `user_emails`;
DROP VIEW IF EXISTS `user_directory`;
DROP VIEW IF EXISTS `client_devis`;
DROP VIEW IF EXISTS `client_expeditions`;
DROP VIEW IF EXISTS `admin_client_users`;
DROP VIEW IF EXISTS `admin_devis`;
DROP VIEW IF EXISTS `admin_expeditions`;
DROP VIEW IF EXISTS `admin_vehicules`;
DROP VIEW IF EXISTS `admin_chauffeurs`;
DROP VIEW IF EXISTS `admin_remorques`;
DROP VIEW IF EXISTS `admin_entrepots`;

-- Supprime toutes les anciennes identites LOGITIX, quel que soit leur Host.
-- DROP USER supprime aussi les privileges et les attributions de roles du
-- compte, contrairement a un simple REVOKE ALL qui ne suffit pas pour les roles.
SET @OLD_GROUP_CONCAT_MAX_LEN = @@SESSION.group_concat_max_len;
SET SESSION group_concat_max_len = 65535;
SELECT GROUP_CONCAT(CONCAT(QUOTE(`User`), '@', QUOTE(`Host`)) ORDER BY `User`,`Host` SEPARATOR ', ')
INTO @logitix_accounts_to_drop
FROM `mysql`.`user`
WHERE `User` IN (
    'logitix_client',
    'logitix_admin',
    'logitix_view_definer',
    'logitix_client_view_definer',
    'logitix_admin_view_definer'
);
SET @logitix_drop_sql = IF(
    @logitix_accounts_to_drop IS NULL,
    'DO 0',
    CONCAT('DROP USER IF EXISTS ', @logitix_accounts_to_drop)
);
PREPARE logitix_drop_stmt FROM @logitix_drop_sql;
EXECUTE logitix_drop_stmt;
DEALLOCATE PREPARE logitix_drop_stmt;
SET SESSION group_concat_max_len = @OLD_GROUP_CONCAT_MAX_LEN;
SET @logitix_accounts_to_drop = NULL;
SET @logitix_drop_sql = NULL;

DROP TABLE IF EXISTS `auth_attempts_client`;
DROP TABLE IF EXISTS `auth_attempts_admin`;
DROP TABLE IF EXISTS `admin_audit_log`;
DROP TABLE IF EXISTS `login_attempts`; -- legacy
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `expeditions`;
DROP TABLE IF EXISTS `devis`;
DROP TABLE IF EXISTS `chauffeurs`;
DROP TABLE IF EXISTS `remorques`;
DROP TABLE IF EXISTS `vehicules`;
DROP TABLE IF EXISTS `entrepots`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `schema_migrations`;

-- =====================================================================
-- 2. VERSION DU SCHEMA
-- =====================================================================
CREATE TABLE `schema_migrations` (
    `version` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. USERS / AUTHENTIFICATION
-- =====================================================================
CREATE TABLE `users` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nom` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `prenom` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
    `email` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
    `telephone` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL,
    `status` ENUM('particulier','entreprise','cooperative') NOT NULL DEFAULT 'particulier',
    `role` ENUM('client','admin') NOT NULL DEFAULT 'client',
    `mot_de_passe` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `password_must_change` TINYINT(1) NOT NULL DEFAULT 0,
    `password_changed_at` DATETIME DEFAULT NULL,
    `totp_secret` VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `totp_last_counter` BIGINT UNSIGNED DEFAULT NULL,
    `raison_sociale` VARCHAR(150) DEFAULT NULL,
    `rccm` VARCHAR(50) DEFAULT NULL,
    `actif` TINYINT(1) NOT NULL DEFAULT 1,
    `auth_version` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role_actif` (`role`,`actif`),
    CONSTRAINT `chk_users_actif` CHECK (`actif` IN (0,1)),
    CONSTRAINT `chk_users_totp_enabled` CHECK (`totp_enabled` IN (0,1)),
    CONSTRAINT `chk_users_password_must_change` CHECK (`password_must_change` IN (0,1)),
    CONSTRAINT `chk_users_auth_version` CHECK (`auth_version` >= 1),
    CONSTRAINT `chk_users_totp_requires_secret`
        CHECK (`totp_enabled` = 0 OR `totp_secret` IS NOT NULL),
    CONSTRAINT `chk_users_totp_counter_requires_2fa`
        CHECK (`totp_last_counter` IS NULL OR `totp_enabled` = 1),
    CONSTRAINT `chk_users_totp_ciphertext`
        CHECK (`totp_secret` IS NULL OR LEFT(`totp_secret`,3) IN ('s1:','o1:')),
    CONSTRAINT `chk_users_client_without_totp`
        CHECK (`role` = 'admin' OR (`totp_secret` IS NULL AND `totp_enabled` = 0 AND `totp_last_counter` IS NULL)),
    CONSTRAINT `chk_users_identity_nonempty`
        CHECK (CHAR_LENGTH(TRIM(`nom`)) BETWEEN 1 AND 100
           AND CHAR_LENGTH(TRIM(`prenom`)) BETWEEN 1 AND 100
           AND CHAR_LENGTH(TRIM(`email`)) BETWEEN 3 AND 150
           AND CHAR_LENGTH(TRIM(`telephone`)) BETWEEN 1 AND 30),
    CONSTRAINT `chk_users_company_name`
        CHECK (`status` = 'particulier' OR CHAR_LENGTH(TRIM(COALESCE(`raison_sociale`, ''))) BETWEEN 1 AND 150),
    CONSTRAINT `chk_users_password_stored`
        CHECK (CHAR_LENGTH(`mot_de_passe`) BETWEEN 20 AND 255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compte admin bootstrap volontairement DESACTIVE.
-- Remplacer mot_de_passe par un password_hash() PHP puis passer actif=1.
INSERT INTO `users`
(`id`,`nom`,`prenom`,`email`,`telephone`,`status`,`role`,`mot_de_passe`,
 `password_must_change`,`password_changed_at`,`totp_secret`,`totp_enabled`,`totp_last_counter`,`raison_sociale`,`rccm`,
 `actif`,`auth_version`,`created_at`,`updated_at`)
VALUES
(1,'Kouame','Christ','admin@logitix.ci','0503231625','particulier','admin',
 '!BOOTSTRAP_DISABLED_CHANGE_PASSWORD!',1,NULL,NULL,0,NULL,NULL,NULL,0,1,CURRENT_TIMESTAMP,NULL);

-- =====================================================================
-- 4. COMPTES DEFINER VERROUILLES
-- =====================================================================
-- Ces comptes ne sont JAMAIS utilises par PHP. Ils servent uniquement au
-- contexte d'execution des vues. Leur verrouillage interdit les connexions
-- directes sans empecher les vues SQL SECURITY DEFINER de fonctionner.
-- MySQL affichera deux mots de passe aleatoires pour ces comptes DEFINER :
-- IGNOREZ-LES. Ils ne doivent pas etre copies dans .env.local.
CREATE USER 'logitix_client_view_definer'@'localhost'
    IDENTIFIED WITH caching_sha2_password BY RANDOM PASSWORD
    PASSWORD EXPIRE NEVER
    ACCOUNT LOCK;

CREATE USER 'logitix_admin_view_definer'@'localhost'
    IDENTIFIED WITH caching_sha2_password BY RANDOM PASSWORD
    PASSWORD EXPIRE NEVER
    ACCOUNT LOCK;

-- Definer CLIENT : lecture strictement necessaire a client_users et ecriture
-- sur les colonnes d'inscription et sur les quatre colonnes necessaires au
-- remplacement du mot de passe temporaire. Aucun DELETE.
GRANT SELECT
    (`id`,`nom`,`prenom`,`email`,`telephone`,`status`,`role`,`mot_de_passe`,`password_must_change`,`password_changed_at`,
     `raison_sociale`,`rccm`,`actif`,`auth_version`,`created_at`,`updated_at`)
    ON `logitix`.`users`
    TO 'logitix_client_view_definer'@'localhost';
GRANT INSERT
    (`nom`,`prenom`,`email`,`telephone`,`status`,`mot_de_passe`,`raison_sociale`,`rccm`)
    ON `logitix`.`users`
    TO 'logitix_client_view_definer'@'localhost';
GRANT UPDATE
    (`mot_de_passe`,`password_must_change`,`password_changed_at`,`auth_version`)
    ON `logitix`.`users`
    TO 'logitix_client_view_definer'@'localhost';

-- Definer ADMIN : lecture des colonnes necessaires aux vues admin/annuaire,
-- et UPDATE limite au profil, au mot de passe et a la 2FA admin.
GRANT SELECT
    (`id`,`nom`,`prenom`,`email`,`telephone`,`status`,`role`,`mot_de_passe`,`password_must_change`,`password_changed_at`,
     `totp_secret`,`totp_enabled`,`totp_last_counter`,`raison_sociale`,`rccm`,
     `actif`,`auth_version`,`created_at`,`updated_at`)
    ON `logitix`.`users`
    TO 'logitix_admin_view_definer'@'localhost';
GRANT UPDATE
    (`nom`,`prenom`,`email`,`telephone`,`mot_de_passe`,`password_must_change`,`password_changed_at`,`actif`,`auth_version`,
     `totp_secret`,`totp_enabled`,`totp_last_counter`)
    ON `logitix`.`users`
    TO 'logitix_admin_view_definer'@'localhost';

-- =====================================================================
-- 5. VUES DE SECURITE AVEC DEFINER EXPLICITE
-- =====================================================================
CREATE ALGORITHM=MERGE
DEFINER='logitix_client_view_definer'@'localhost'
SQL SECURITY DEFINER VIEW `client_users` AS
SELECT
    `id`,`nom`,`prenom`,`email`,`telephone`,`status`,`role`,`mot_de_passe`,`password_must_change`,`password_changed_at`,
    `raison_sociale`,`rccm`,`actif`,`auth_version`,`created_at`,`updated_at`
FROM `users`
WHERE `role` = 'client'
WITH CASCADED CHECK OPTION;

CREATE ALGORITHM=MERGE
DEFINER='logitix_admin_view_definer'@'localhost'
SQL SECURITY DEFINER VIEW `admin_users` AS
SELECT
    `id`,`nom`,`prenom`,`email`,`telephone`,`status`,`role`,`mot_de_passe`,`password_must_change`,`password_changed_at`,
    `totp_secret`,`totp_enabled`,`totp_last_counter`,`actif`,`auth_version`,
    `created_at`,`updated_at`
FROM `users`
WHERE `role` = 'admin'
WITH CASCADED CHECK OPTION;

CREATE ALGORITHM=MERGE
DEFINER='logitix_admin_view_definer'@'localhost'
SQL SECURITY DEFINER VIEW `user_emails` AS
SELECT `id`,`email`
FROM `users`;

CREATE ALGORITHM=MERGE
DEFINER='logitix_admin_view_definer'@'localhost'
SQL SECURITY DEFINER VIEW `user_directory` AS
SELECT
    `id`,`nom`,`prenom`,`email`,`telephone`,`status`,`role`,`raison_sociale`,
    `rccm`,`actif`,`created_at`,`updated_at`
FROM `users`;

-- =====================================================================
-- 6. CHAUFFEURS
-- =====================================================================
CREATE TABLE `chauffeurs` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nom` VARCHAR(100) NOT NULL,
    `prenom` VARCHAR(100) NOT NULL,
    `permis` VARCHAR(30) DEFAULT NULL,
    `telephone` VARCHAR(30) DEFAULT NULL,
    `disponible` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_chauffeurs_disponible` (`disponible`),
    CONSTRAINT `chk_chauffeurs_disponible` CHECK (`disponible` IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `chauffeurs`
(`id`,`nom`,`prenom`,`permis`,`telephone`,`disponible`) VALUES
(1,'Koffi','Jean','CE','0700000001',1),
(2,'Bamba','Awa','CE','0700000002',1),
(3,'Traore','Moussa','CE','0700000003',1);

-- =====================================================================
-- 7. ENTREPOTS
-- =====================================================================
CREATE TABLE `entrepots` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nom` VARCHAR(100) NOT NULL,
    `ville` VARCHAR(100) NOT NULL,
    `pays` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_entrepots_ville_pays` (`ville`,`pays`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `entrepots` (`id`,`nom`,`ville`,`pays`) VALUES
(1,'Entrepot Vridi','Abidjan','Cote d''Ivoire'),
(2,'Entrepot Brest','Brest','France'),
(3,'Entrepot Dakar','Dakar','Senegal'),
(4,'Entrepot San-Pedro','San-Pedro','Cote d''Ivoire'),
(5,'Entrepot Shanghai','Shanghai','Chine');

-- =====================================================================
-- 8. VEHICULES
-- =====================================================================
CREATE TABLE `vehicules` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nom` VARCHAR(100) NOT NULL,
    `categorie` VARCHAR(50) NOT NULL,
    `immatriculation` VARCHAR(30) DEFAULT NULL,
    `capacite` VARCHAR(50) DEFAULT NULL,
    `statut` ENUM('disponible','en_service','maintenance') NOT NULL DEFAULT 'disponible',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_vehicules_immatriculation` (`immatriculation`),
    KEY `idx_vehicules_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `vehicules`
(`id`,`nom`,`categorie`,`immatriculation`,`capacite`,`statut`) VALUES
(1,'Mercedes Actros','tracteur','CI-1001-AB','40 tonnes','disponible'),
(2,'Scania S','tracteur','CI-1002-AB','44 tonnes','disponible'),
(3,'Volvo FH','tracteur','CI-1003-AB','40 tonnes','disponible'),
(4,'MAN TGX','tracteur','CI-1004-AB','44 tonnes','maintenance');

-- =====================================================================
-- 9. REMORQUES
-- =====================================================================
CREATE TABLE `remorques` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nom` VARCHAR(100) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `capacite` VARCHAR(50) DEFAULT NULL,
    `statut` ENUM('disponible','en_service','maintenance') NOT NULL DEFAULT 'disponible',
    PRIMARY KEY (`id`),
    KEY `idx_remorques_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `remorques`
(`id`,`nom`,`type`,`capacite`,`statut`) VALUES
(1,'Bachee (Tautliner)','bachee','33 palettes / 24 tonnes','disponible'),
(2,'Frigorifique (Reefer)','frigo','33 palettes','disponible'),
(3,'Plateau (Flatbed)','plateau','24 a 32 tonnes','disponible'),
(4,'Benne (Tipper)','benne','30 m3','maintenance'),
(5,'Citerne (Tanker)','citerne','jusqu''a 38 000 L','disponible'),
(6,'Porte-engins (Lowboy)','porte_engins','jusqu''a 60 tonnes','disponible');

-- =====================================================================
-- 10. DEVIS
-- =====================================================================
CREATE TABLE `devis` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `depart` VARCHAR(150) NOT NULL,
    `destination` VARCHAR(150) NOT NULL,
    `type_marchandise` VARCHAR(150) DEFAULT NULL,
    `poids_estime` DECIMAL(10,2) DEFAULT NULL,
    `date_souhaitee` DATE DEFAULT NULL,
    `message` TEXT,
    `statut` ENUM('en_attente','valide','refuse','converti') NOT NULL DEFAULT 'en_attente',
    `montant_propose` DECIMAL(14,2) DEFAULT NULL,
    `devise` CHAR(3) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'XOF',
    `reponse_admin` TEXT DEFAULT NULL,
    `date_traitement` DATETIME DEFAULT NULL,
    `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_devis_id_user` (`id`,`user_id`),
    KEY `idx_devis_user` (`user_id`),
    KEY `idx_devis_statut` (`statut`),
    KEY `idx_devis_user_statut` (`user_id`,`statut`),
    CONSTRAINT `chk_devis_poids`
        CHECK (`poids_estime` IS NULL OR `poids_estime` >= 0),
    CONSTRAINT `chk_devis_montant`
        CHECK (`montant_propose` IS NULL OR `montant_propose` >= 0),
    CONSTRAINT `chk_devis_devise`
        CHECK (`devise` REGEXP '^[A-Z]{3}$'),
    CONSTRAINT `chk_devis_route_nonempty`
        CHECK (CHAR_LENGTH(TRIM(`depart`)) BETWEEN 1 AND 150
           AND CHAR_LENGTH(TRIM(`destination`)) BETWEEN 1 AND 150),
    CONSTRAINT `fk_devis_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 11. EXPEDITIONS
-- =====================================================================
CREATE TABLE `expeditions` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `devis_id` INT DEFAULT NULL,
    `user_id` INT NOT NULL,
    `vehicule_id` INT DEFAULT NULL,
    `chauffeur_id` INT DEFAULT NULL,
    `remorque_id` INT DEFAULT NULL,
    `reference` VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `depart` VARCHAR(150) NOT NULL,
    `destination` VARCHAR(150) NOT NULL,
    `statut` ENUM('planifiee','en_cours','livree','annulee') NOT NULL DEFAULT 'planifiee',
    `date_depart` DATETIME DEFAULT NULL,
    `date_livraison_estimee` DATETIME DEFAULT NULL,
    `date_livraison_reelle` DATETIME DEFAULT NULL,
    `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Colonnes techniques generees : elles rendent impossible une double
    -- affectation concurrente d'une ressource a deux expeditions en cours.
    `active_vehicule_id` INT GENERATED ALWAYS AS (CASE WHEN `statut`='en_cours' THEN `vehicule_id` ELSE NULL END) STORED,
    `active_chauffeur_id` INT GENERATED ALWAYS AS (CASE WHEN `statut`='en_cours' THEN `chauffeur_id` ELSE NULL END) STORED,
    `active_remorque_id` INT GENERATED ALWAYS AS (CASE WHEN `statut`='en_cours' THEN `remorque_id` ELSE NULL END) STORED,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_expeditions_reference` (`reference`),
    UNIQUE KEY `uq_expeditions_devis` (`devis_id`),
    UNIQUE KEY `uq_expeditions_active_vehicule` (`active_vehicule_id`),
    UNIQUE KEY `uq_expeditions_active_chauffeur` (`active_chauffeur_id`),
    UNIQUE KEY `uq_expeditions_active_remorque` (`active_remorque_id`),
    KEY `idx_expeditions_devis_user` (`devis_id`,`user_id`),
    KEY `idx_expeditions_user` (`user_id`),
    KEY `idx_expeditions_statut` (`statut`),
    KEY `idx_expeditions_vehicule` (`vehicule_id`),
    KEY `idx_expeditions_chauffeur` (`chauffeur_id`),
    KEY `idx_expeditions_remorque` (`remorque_id`),
    CONSTRAINT `fk_expeditions_devis_user`
        FOREIGN KEY (`devis_id`,`user_id`) REFERENCES `devis` (`id`,`user_id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_expeditions_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    -- IMPORTANT MySQL 8.x : ces colonnes sont utilisees par un CHECK et/ou
    -- par des colonnes generees STORED. Les actions CASCADE/SET NULL y sont
    -- interdites. LOGITIX archive les ressources par statut au lieu de les
    -- supprimer, donc RESTRICT est aussi le comportement metier le plus sur.
    CONSTRAINT `fk_expeditions_vehicule`
        FOREIGN KEY (`vehicule_id`) REFERENCES `vehicules` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_expeditions_chauffeur`
        FOREIGN KEY (`chauffeur_id`) REFERENCES `chauffeurs` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_expeditions_remorque`
        FOREIGN KEY (`remorque_id`) REFERENCES `remorques` (`id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `chk_expeditions_reference_nonempty`
        CHECK (CHAR_LENGTH(TRIM(`reference`)) BETWEEN 6 AND 30),
    CONSTRAINT `chk_expeditions_route_nonempty`
        CHECK (CHAR_LENGTH(TRIM(`depart`)) BETWEEN 1 AND 150
           AND CHAR_LENGTH(TRIM(`destination`)) BETWEEN 1 AND 150),
    CONSTRAINT `chk_expeditions_dates`
        CHECK (`date_livraison_estimee` IS NULL OR `date_depart` IS NULL OR `date_livraison_estimee` >= `date_depart`),
    CONSTRAINT `chk_expeditions_livraison`
        CHECK (`date_livraison_reelle` IS NULL OR `date_depart` IS NULL OR `date_livraison_reelle` >= `date_depart`),
    CONSTRAINT `chk_expeditions_active_resources`
        CHECK (`statut` <> 'en_cours' OR (`vehicule_id` IS NOT NULL AND `chauffeur_id` IS NOT NULL AND `date_depart` IS NOT NULL)),
    CONSTRAINT `chk_expeditions_delivered_date`
        CHECK (`statut` <> 'livree' OR `date_livraison_reelle` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 11B. VUES METIER CLIENT - COLONNES MINIMALES
-- =====================================================================
-- MySQL ne fournit pas de row-level security native pour un compte PHP
-- partage. Les vues ci-dessous reduisent la surface de colonnes exposees ;
-- le PHP applique en plus un filtre user_id = session user_id sur chaque
-- lecture et fixe lui-meme user_id sur chaque INSERT. Aucun UPDATE/DELETE
-- metier n'est accorde au compte client.
GRANT SELECT
    (`id`,`user_id`,`depart`,`destination`,`type_marchandise`,`poids_estime`,`date_souhaitee`,`message`,`statut`,`montant_propose`,`devise`,`reponse_admin`,`date_traitement`,`date_creation`)
    ON `logitix`.`devis`
    TO 'logitix_client_view_definer'@'localhost';
GRANT INSERT
    (`user_id`,`depart`,`destination`,`type_marchandise`,`poids_estime`,`date_souhaitee`,`message`)
    ON `logitix`.`devis`
    TO 'logitix_client_view_definer'@'localhost';

GRANT SELECT
    (`id`,`devis_id`,`user_id`,`reference`,`depart`,`destination`,`statut`,`date_depart`,`date_livraison_estimee`,`date_livraison_reelle`,`date_creation`)
    ON `logitix`.`expeditions`
    TO 'logitix_client_view_definer'@'localhost';
GRANT INSERT
    (`user_id`,`reference`,`depart`,`destination`,`date_depart`)
    ON `logitix`.`expeditions`
    TO 'logitix_client_view_definer'@'localhost';

CREATE ALGORITHM=MERGE
DEFINER='logitix_client_view_definer'@'localhost'
SQL SECURITY DEFINER VIEW `client_devis` AS
SELECT
    `id`,`user_id`,`depart`,`destination`,`type_marchandise`,`poids_estime`,`date_souhaitee`,`message`,`statut`,`montant_propose`,`devise`,`reponse_admin`,`date_traitement`,`date_creation`
FROM `devis`;

CREATE ALGORITHM=MERGE
DEFINER='logitix_client_view_definer'@'localhost'
SQL SECURITY DEFINER VIEW `client_expeditions` AS
SELECT
    `id`,`devis_id`,`user_id`,`reference`,`depart`,`destination`,`statut`,`date_depart`,`date_livraison_estimee`,`date_livraison_reelle`,`date_creation`
FROM `expeditions`;

-- =====================================================================
-- 11C. VUES METIER ADMIN - PRIVILEGES MINIMAUX
-- =====================================================================
-- Le compte PHP admin ne recoit toujours aucun acces direct aux tables
-- metier. Les vues ci-dessous permettent les operations necessaires sans
-- exposer DELETE. Les utilisateurs clients sont filtres par role.
GRANT SELECT
    (`id`,`user_id`,`depart`,`destination`,`type_marchandise`,`poids_estime`,`date_souhaitee`,`message`,`statut`,`montant_propose`,`devise`,`reponse_admin`,`date_traitement`,`date_creation`)
    ON `logitix`.`devis` TO 'logitix_admin_view_definer'@'localhost';
GRANT UPDATE (`statut`,`montant_propose`,`devise`,`reponse_admin`,`date_traitement`)
    ON `logitix`.`devis` TO 'logitix_admin_view_definer'@'localhost';
GRANT SELECT
    (`id`,`devis_id`,`user_id`,`vehicule_id`,`chauffeur_id`,`remorque_id`,`reference`,`depart`,`destination`,`statut`,`date_depart`,`date_livraison_estimee`,`date_livraison_reelle`,`date_creation`)
    ON `logitix`.`expeditions` TO 'logitix_admin_view_definer'@'localhost';
GRANT INSERT (`devis_id`,`user_id`,`reference`,`depart`,`destination`,`date_depart`,`date_livraison_estimee`)
    ON `logitix`.`expeditions` TO 'logitix_admin_view_definer'@'localhost';
GRANT UPDATE (`vehicule_id`,`chauffeur_id`,`remorque_id`,`statut`,`date_depart`,`date_livraison_estimee`,`date_livraison_reelle`)
    ON `logitix`.`expeditions` TO 'logitix_admin_view_definer'@'localhost';
GRANT SELECT, INSERT, UPDATE ON `logitix`.`vehicules`
    TO 'logitix_admin_view_definer'@'localhost';
GRANT SELECT, INSERT, UPDATE ON `logitix`.`chauffeurs`
    TO 'logitix_admin_view_definer'@'localhost';
GRANT SELECT, INSERT, UPDATE ON `logitix`.`remorques`
    TO 'logitix_admin_view_definer'@'localhost';
GRANT SELECT, INSERT, UPDATE ON `logitix`.`entrepots`
    TO 'logitix_admin_view_definer'@'localhost';

CREATE ALGORITHM=MERGE
DEFINER='logitix_admin_view_definer'@'localhost'
SQL SECURITY DEFINER VIEW `admin_client_users` AS
SELECT
    `id`,`nom`,`prenom`,`email`,`telephone`,`status`,`mot_de_passe`,`password_must_change`,`password_changed_at`,
    `raison_sociale`,`rccm`,`actif`,`auth_version`,`created_at`,`updated_at`
FROM `users`
WHERE `role`='client'
WITH CASCADED CHECK OPTION;

CREATE ALGORITHM=MERGE
DEFINER='logitix_admin_view_definer'@'localhost'
SQL SECURITY DEFINER VIEW `admin_devis` AS
SELECT
    `id`,`user_id`,`depart`,`destination`,`type_marchandise`,`poids_estime`,`date_souhaitee`,`message`,`statut`,
    `montant_propose`,`devise`,`reponse_admin`,`date_traitement`,`date_creation`
FROM `devis`;

CREATE ALGORITHM=MERGE
DEFINER='logitix_admin_view_definer'@'localhost'
SQL SECURITY DEFINER VIEW `admin_expeditions` AS
SELECT
    `id`,`devis_id`,`user_id`,`vehicule_id`,`chauffeur_id`,`remorque_id`,`reference`,`depart`,`destination`,`statut`,
    `date_depart`,`date_livraison_estimee`,`date_livraison_reelle`,`date_creation`
FROM `expeditions`;

CREATE ALGORITHM=MERGE
DEFINER='logitix_admin_view_definer'@'localhost'
SQL SECURITY DEFINER VIEW `admin_vehicules` AS
SELECT `id`,`nom`,`categorie`,`immatriculation`,`capacite`,`statut` FROM `vehicules`;

CREATE ALGORITHM=MERGE
DEFINER='logitix_admin_view_definer'@'localhost'
SQL SECURITY DEFINER VIEW `admin_chauffeurs` AS
SELECT `id`,`nom`,`prenom`,`permis`,`telephone`,`disponible` FROM `chauffeurs`;

CREATE ALGORITHM=MERGE
DEFINER='logitix_admin_view_definer'@'localhost'
SQL SECURITY DEFINER VIEW `admin_remorques` AS
SELECT `id`,`nom`,`type`,`capacite`,`statut` FROM `remorques`;

CREATE ALGORITHM=MERGE
DEFINER='logitix_admin_view_definer'@'localhost'
SQL SECURITY DEFINER VIEW `admin_entrepots` AS
SELECT `id`,`nom`,`ville`,`pays` FROM `entrepots`;

-- =====================================================================
-- 12. NOTIFICATIONS
-- =====================================================================
CREATE TABLE `notifications` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `message` VARCHAR(255) NOT NULL,
    `lien` VARCHAR(255) DEFAULT NULL,
    `lu` TINYINT(1) NOT NULL DEFAULT 0,
    `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notifications_user_lu` (`user_id`,`lu`),
    KEY `idx_notifications_user_date` (`user_id`,`date_creation`),
    CONSTRAINT `chk_notifications_message`
        CHECK (CHAR_LENGTH(TRIM(`message`)) BETWEEN 1 AND 255),
    CONSTRAINT `fk_notifications_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_notifications_lu` CHECK (`lu` IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 12B. JOURNAL DES ACTIONS ADMINISTRATEUR
-- =====================================================================
CREATE TABLE `admin_audit_log` (
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
    CONSTRAINT `fk_admin_audit_actor`
        FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_admin_audit_ip` CHECK (OCTET_LENGTH(`ip`) IN (4,16))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 13. JOURNAUX D'AUTHENTIFICATION / RATE LIMITING
-- =====================================================================
CREATE TABLE `auth_attempts_client` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
    `ip` VARBINARY(16) NOT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `context` ENUM('password','totp','registration') NOT NULL DEFAULT 'password',
    PRIMARY KEY (`id`),
    KEY `idx_auth_client_pair` (`context`,`email`,`ip`,`success`,`attempted_at`),
    KEY `idx_auth_client_ip` (`context`,`ip`,`success`,`attempted_at`),
    KEY `idx_auth_client_time` (`attempted_at`),
    CONSTRAINT `chk_auth_client_success` CHECK (`success` IN (0,1)),
    CONSTRAINT `chk_auth_client_email` CHECK (CHAR_LENGTH(TRIM(`email`)) BETWEEN 1 AND 150),
    CONSTRAINT `chk_auth_client_ip` CHECK (OCTET_LENGTH(`ip`) IN (4,16))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `auth_attempts_admin` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
    `ip` VARBINARY(16) NOT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `context` ENUM('password','totp','registration') NOT NULL DEFAULT 'password',
    PRIMARY KEY (`id`),
    KEY `idx_auth_admin_pair` (`context`,`email`,`ip`,`success`,`attempted_at`),
    KEY `idx_auth_admin_ip` (`context`,`ip`,`success`,`attempted_at`),
    KEY `idx_auth_admin_time` (`attempted_at`),
    CONSTRAINT `chk_auth_admin_success` CHECK (`success` IN (0,1)),
    CONSTRAINT `chk_auth_admin_email` CHECK (CHAR_LENGTH(TRIM(`email`)) BETWEEN 1 AND 150),
    CONSTRAINT `chk_auth_admin_ip` CHECK (OCTET_LENGTH(`ip`) IN (4,16))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `schema_migrations` (`version`,`description`)
VALUES ('7.7.2','Admin interface active, local bootstrap, audit log, least-privilege views and concurrent resource assignment guards');

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;

-- =====================================================================
-- 14. COMPTES PHP - CREATION PROPRE / ROTATION DES CREDENTIALS
-- =====================================================================
-- Les anciennes identites ont ete supprimees en section 1, donc leurs roles,
-- privileges, mots de passe et options historiques ne survivent pas.
--
-- Les DEUX prochains CREATE USER sont les seuls mots de passe a conserver.
-- Copiez uniquement ceux associes a :
--   1) logitix_client@127.0.0.1
--   2) logitix_admin@127.0.0.1
-- tools/setup_local_env.php verifiera les deux valeurs avant d'ecrire .env.local.
SELECT 'COPIEZ UNIQUEMENT LES DEUX PROCHAINS MOTS DE PASSE : logitix_client et logitix_admin' AS credentials_notice;
CREATE USER 'logitix_client'@'127.0.0.1'
    IDENTIFIED WITH caching_sha2_password BY RANDOM PASSWORD
    PASSWORD EXPIRE NEVER
    ACCOUNT UNLOCK;

CREATE USER 'logitix_admin'@'127.0.0.1'
    IDENTIFIED WITH caching_sha2_password BY RANDOM PASSWORD
    PASSWORD EXPIRE NEVER
    ACCOUNT UNLOCK;

SET DEFAULT ROLE NONE TO
    'logitix_client'@'127.0.0.1',
    'logitix_admin'@'127.0.0.1';

-- CLIENT ---------------------------------------------------------------
GRANT SELECT
    (`id`,`nom`,`prenom`,`email`,`telephone`,`status`,`role`,`mot_de_passe`,`password_must_change`,`password_changed_at`,`actif`,`auth_version`)
    ON `logitix`.`client_users`
    TO 'logitix_client'@'127.0.0.1';
GRANT INSERT
    (`nom`,`prenom`,`email`,`telephone`,`status`,`mot_de_passe`,`raison_sociale`,`rccm`)
    ON `logitix`.`client_users`
    TO 'logitix_client'@'127.0.0.1';
GRANT UPDATE
    (`mot_de_passe`,`password_must_change`,`password_changed_at`,`auth_version`)
    ON `logitix`.`client_users`
    TO 'logitix_client'@'127.0.0.1';
GRANT SELECT
    ON `logitix`.`auth_attempts_client`
    TO 'logitix_client'@'127.0.0.1';
GRANT INSERT (`email`,`ip`,`success`,`context`)
    ON `logitix`.`auth_attempts_client`
    TO 'logitix_client'@'127.0.0.1';

-- Donnees metier client : lecture/insertion par colonnes via vues.
-- Le code PHP impose toujours WHERE user_id = session user_id.
GRANT SELECT
    (`id`,`user_id`,`depart`,`destination`,`type_marchandise`,`poids_estime`,`date_souhaitee`,`message`,`statut`,`montant_propose`,`devise`,`reponse_admin`,`date_traitement`,`date_creation`)
    ON `logitix`.`client_devis`
    TO 'logitix_client'@'127.0.0.1';
GRANT INSERT
    (`user_id`,`depart`,`destination`,`type_marchandise`,`poids_estime`,`date_souhaitee`,`message`)
    ON `logitix`.`client_devis`
    TO 'logitix_client'@'127.0.0.1';
GRANT SELECT
    (`id`,`devis_id`,`user_id`,`reference`,`depart`,`destination`,`statut`,`date_depart`,`date_livraison_estimee`,`date_livraison_reelle`,`date_creation`)
    ON `logitix`.`client_expeditions`
    TO 'logitix_client'@'127.0.0.1';
GRANT INSERT
    (`user_id`,`reference`,`depart`,`destination`,`date_depart`)
    ON `logitix`.`client_expeditions`
    TO 'logitix_client'@'127.0.0.1';

-- ADMIN ----------------------------------------------------------------
GRANT SELECT
    (`id`,`nom`,`prenom`,`email`,`telephone`,`status`,`role`,`mot_de_passe`,`password_must_change`,`password_changed_at`,
     `totp_secret`,`totp_enabled`,`totp_last_counter`,`actif`,`auth_version`)
    ON `logitix`.`admin_users`
    TO 'logitix_admin'@'127.0.0.1';
GRANT UPDATE
    (`nom`,`prenom`,`email`,`telephone`,`mot_de_passe`,`password_must_change`,`password_changed_at`,`auth_version`,
     `totp_secret`,`totp_enabled`,`totp_last_counter`)
    ON `logitix`.`admin_users`
    TO 'logitix_admin'@'127.0.0.1';
GRANT SELECT (`id`,`email`)
    ON `logitix`.`user_emails`
    TO 'logitix_admin'@'127.0.0.1';
GRANT SELECT
    ON `logitix`.`user_directory`
    TO 'logitix_admin'@'127.0.0.1';
GRANT SELECT
    ON `logitix`.`auth_attempts_admin`
    TO 'logitix_admin'@'127.0.0.1';
GRANT INSERT (`email`,`ip`,`success`,`context`)
    ON `logitix`.`auth_attempts_admin`
    TO 'logitix_admin'@'127.0.0.1';

-- Fonctions metier admin v7.7. Aucun DELETE n'est accorde.
GRANT SELECT
    (`id`,`nom`,`prenom`,`email`,`telephone`,`status`,`raison_sociale`,`rccm`,`actif`,`auth_version`,`password_must_change`,`password_changed_at`,`created_at`,`updated_at`)
    ON `logitix`.`admin_client_users`
    TO 'logitix_admin'@'127.0.0.1';
GRANT UPDATE
    (`mot_de_passe`,`password_must_change`,`password_changed_at`,`actif`,`auth_version`)
    ON `logitix`.`admin_client_users`
    TO 'logitix_admin'@'127.0.0.1';

GRANT SELECT ON `logitix`.`admin_devis`
    TO 'logitix_admin'@'127.0.0.1';
GRANT UPDATE
    (`statut`,`montant_propose`,`devise`,`reponse_admin`,`date_traitement`)
    ON `logitix`.`admin_devis`
    TO 'logitix_admin'@'127.0.0.1';

GRANT SELECT ON `logitix`.`admin_expeditions`
    TO 'logitix_admin'@'127.0.0.1';
GRANT INSERT
    (`devis_id`,`user_id`,`reference`,`depart`,`destination`,`date_depart`,`date_livraison_estimee`)
    ON `logitix`.`admin_expeditions`
    TO 'logitix_admin'@'127.0.0.1';
GRANT UPDATE
    (`vehicule_id`,`chauffeur_id`,`remorque_id`,`statut`,`date_depart`,`date_livraison_estimee`,`date_livraison_reelle`)
    ON `logitix`.`admin_expeditions`
    TO 'logitix_admin'@'127.0.0.1';

GRANT SELECT, INSERT, UPDATE ON `logitix`.`admin_vehicules`
    TO 'logitix_admin'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON `logitix`.`admin_chauffeurs`
    TO 'logitix_admin'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON `logitix`.`admin_remorques`
    TO 'logitix_admin'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON `logitix`.`admin_entrepots`
    TO 'logitix_admin'@'127.0.0.1';

GRANT SELECT
    (`id`,`actor_user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip`,`created_at`)
    ON `logitix`.`admin_audit_log`
    TO 'logitix_admin'@'127.0.0.1';
GRANT INSERT
    (`actor_user_id`,`action`,`entity_type`,`entity_id`,`details`,`ip`)
    ON `logitix`.`admin_audit_log`
    TO 'logitix_admin'@'127.0.0.1';

-- Aucun FLUSH PRIVILEGES n'est necessaire apres CREATE USER / GRANT.

SELECT 'LOGITIX MySQL v7.7.2 installe. Copiez les mots de passe aleatoires retournes par les deux CREATE USER applicatifs dans .env.local. Les controles de securite vont maintenant s executer automatiquement.' AS next_step;
SHOW GRANTS FOR 'logitix_client'@'127.0.0.1';
SHOW GRANTS FOR 'logitix_admin'@'127.0.0.1';

-- =====================================================================
-- 15. VERIFICATION AUTOMATIQUE DE SECURITE v7.7.2
-- =====================================================================
USE `logitix`;

SELECT 'mysql_version' AS check_name, VERSION() AS result;
SELECT 'schema_version_7_7' AS check_name,
       CASE WHEN EXISTS (SELECT 1 FROM `schema_migrations` WHERE `version`='7.7.2') THEN 'PASS' ELSE 'FAIL' END AS result;

SELECT 'required_tables' AS check_name,
       CASE WHEN COUNT(*)=12 THEN 'PASS' ELSE 'FAIL' END AS result
FROM information_schema.TABLES
WHERE TABLE_SCHEMA='logitix' AND TABLE_TYPE='BASE TABLE'
  AND TABLE_NAME IN ('schema_migrations','users','chauffeurs','entrepots','vehicules','remorques','devis','expeditions','notifications','auth_attempts_client','auth_attempts_admin','admin_audit_log');

SELECT 'security_views' AS check_name,
       CASE WHEN COUNT(*)=13 THEN 'PASS' ELSE 'FAIL' END AS result
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA='logitix' AND SECURITY_TYPE='DEFINER'
  AND TABLE_NAME IN ('client_users','admin_users','user_emails','user_directory','client_devis','client_expeditions','admin_client_users','admin_devis','admin_expeditions','admin_vehicules','admin_chauffeurs','admin_remorques','admin_entrepots');

SELECT 'no_php_direct_users' AS check_name,
       CASE WHEN COUNT(*)=0 THEN 'PASS' ELSE 'FAIL' END AS result
FROM (
    SELECT GRANTEE FROM information_schema.TABLE_PRIVILEGES
    WHERE TABLE_SCHEMA='logitix' AND TABLE_NAME='users'
      AND GRANTEE IN ('''logitix_client''@''127.0.0.1''','''logitix_admin''@''127.0.0.1''')
    UNION ALL
    SELECT GRANTEE FROM information_schema.COLUMN_PRIVILEGES
    WHERE TABLE_SCHEMA='logitix' AND TABLE_NAME='users'
      AND GRANTEE IN ('''logitix_client''@''127.0.0.1''','''logitix_admin''@''127.0.0.1''')
) x;

SELECT 'no_app_delete_privilege' AS check_name,
       CASE WHEN COUNT(*)=0 THEN 'PASS' ELSE 'FAIL' END AS result
FROM information_schema.TABLE_PRIVILEGES
WHERE TABLE_SCHEMA='logitix'
  AND GRANTEE IN ('''logitix_client''@''127.0.0.1''','''logitix_admin''@''127.0.0.1''')
  AND PRIVILEGE_TYPE IN ('DELETE','DROP','ALTER','CREATE','INDEX','REFERENCES','TRIGGER');

SELECT 'no_php_direct_business_tables' AS check_name,
       CASE WHEN COUNT(*)=0 THEN 'PASS' ELSE 'FAIL' END AS result
FROM (
    SELECT GRANTEE,TABLE_NAME FROM information_schema.TABLE_PRIVILEGES
    WHERE TABLE_SCHEMA='logitix'
      AND TABLE_NAME IN ('users','devis','expeditions','vehicules','chauffeurs','remorques','entrepots','notifications')
      AND GRANTEE IN ('''logitix_client''@''127.0.0.1''','''logitix_admin''@''127.0.0.1''')
    UNION ALL
    SELECT GRANTEE,TABLE_NAME FROM information_schema.COLUMN_PRIVILEGES
    WHERE TABLE_SCHEMA='logitix'
      AND TABLE_NAME IN ('users','devis','expeditions','vehicules','chauffeurs','remorques','entrepots','notifications')
      AND GRANTEE IN ('''logitix_client''@''127.0.0.1''','''logitix_admin''@''127.0.0.1''')
) b;

SELECT 'no_app_grant_option' AS check_name,
       CASE WHEN COUNT(*)=0 THEN 'PASS' ELSE 'FAIL' END AS result
FROM information_schema.USER_PRIVILEGES
WHERE GRANTEE IN ('''logitix_client''@''127.0.0.1''','''logitix_admin''@''127.0.0.1''')
  AND PRIVILEGE_TYPE='GRANT OPTION';

SELECT 'definers_locked' AS check_name,
       CASE WHEN COUNT(*)=2 AND SUM(account_locked='Y')=2 THEN 'PASS' ELSE 'FAIL' END AS result
FROM mysql.user
WHERE (User='logitix_client_view_definer' AND Host='localhost')
   OR (User='logitix_admin_view_definer' AND Host='localhost');

SELECT 'app_accounts_local_only' AS check_name,
       CASE WHEN COUNT(*)=2 THEN 'PASS' ELSE 'FAIL' END AS result
FROM mysql.user
WHERE (User='logitix_client' AND Host='127.0.0.1')
   OR (User='logitix_admin' AND Host='127.0.0.1');

SELECT 'client_totp_hidden' AS check_name,
       CASE WHEN COUNT(*)=0 THEN 'PASS' ELSE 'FAIL' END AS result
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA='logitix' AND TABLE_NAME='client_users'
  AND COLUMN_NAME IN ('totp_secret','totp_enabled','totp_last_counter');

SELECT 'admin_client_password_not_selectable' AS check_name,
       CASE WHEN COUNT(*)=0 THEN 'PASS' ELSE 'FAIL' END AS result
FROM information_schema.COLUMN_PRIVILEGES
WHERE TABLE_SCHEMA='logitix' AND TABLE_NAME='admin_client_users'
  AND GRANTEE='''logitix_admin''@''127.0.0.1'''
  AND PRIVILEGE_TYPE='SELECT' AND COLUMN_NAME='mot_de_passe';

SELECT 'expedition_concurrency_guards' AS check_name,
       CASE WHEN COUNT(DISTINCT INDEX_NAME)=4 THEN 'PASS' ELSE 'FAIL' END AS result
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA='logitix' AND TABLE_NAME='expeditions' AND NON_UNIQUE=0
  AND INDEX_NAME IN ('uq_expeditions_devis','uq_expeditions_active_vehicule','uq_expeditions_active_chauffeur','uq_expeditions_active_remorque');

SELECT 'expedition_resource_fk_rules' AS check_name,
       CASE WHEN COUNT(*)=3 THEN 'PASS' ELSE 'FAIL' END AS result
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA='logitix' AND TABLE_NAME='expeditions'
  AND CONSTRAINT_NAME IN ('fk_expeditions_vehicule','fk_expeditions_chauffeur','fk_expeditions_remorque')
  AND DELETE_RULE IN ('RESTRICT','NO ACTION')
  AND UPDATE_RULE IN ('RESTRICT','NO ACTION');

SELECT 'expedition_business_checks' AS check_name,
       CASE WHEN COUNT(*)=2 THEN 'PASS' ELSE 'FAIL' END AS result
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA='logitix' AND TABLE_NAME='expeditions' AND CONSTRAINT_TYPE='CHECK'
  AND CONSTRAINT_NAME IN ('chk_expeditions_active_resources','chk_expeditions_delivered_date');

DROP PROCEDURE IF EXISTS `__logitix_assert_secure_install`;
DELIMITER $$
CREATE PROCEDURE `__logitix_assert_secure_install`()
SQL SECURITY INVOKER
BEGIN
    DECLARE v INT DEFAULT 0;

    SELECT COUNT(*) INTO v FROM `schema_migrations` WHERE `version`='7.7.2';
    IF v<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LOGITIX: schema 7.7.2 absent.'; END IF;

    SELECT COUNT(*) INTO v FROM information_schema.TABLES
    WHERE TABLE_SCHEMA='logitix' AND TABLE_TYPE='BASE TABLE'
      AND TABLE_NAME IN ('schema_migrations','users','chauffeurs','entrepots','vehicules','remorques','devis','expeditions','notifications','auth_attempts_client','auth_attempts_admin','admin_audit_log');
    IF v<>12 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LOGITIX: tables v7.7.2 manquantes.'; END IF;

    SELECT COUNT(*) INTO v FROM information_schema.VIEWS
    WHERE TABLE_SCHEMA='logitix' AND SECURITY_TYPE='DEFINER'
      AND TABLE_NAME IN ('client_users','admin_users','user_emails','user_directory','client_devis','client_expeditions','admin_client_users','admin_devis','admin_expeditions','admin_vehicules','admin_chauffeurs','admin_remorques','admin_entrepots');
    IF v<>13 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LOGITIX: vues securisees v7.7.2 manquantes.'; END IF;

    SELECT COUNT(*) INTO v FROM mysql.user
    WHERE (User IN ('logitix_client','logitix_admin') AND Host<>'127.0.0.1')
       OR (User IN ('logitix_client_view_definer','logitix_admin_view_definer') AND Host<>'localhost');
    IF v<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LOGITIX: comptes MySQL alternatifs detectes.'; END IF;

    SELECT COUNT(*) INTO v FROM (
        SELECT GRANTEE FROM information_schema.TABLE_PRIVILEGES
        WHERE TABLE_SCHEMA='logitix' AND TABLE_NAME='users'
          AND GRANTEE IN ('''logitix_client''@''127.0.0.1''','''logitix_admin''@''127.0.0.1''')
        UNION ALL
        SELECT GRANTEE FROM information_schema.COLUMN_PRIVILEGES
        WHERE TABLE_SCHEMA='logitix' AND TABLE_NAME='users'
          AND GRANTEE IN ('''logitix_client''@''127.0.0.1''','''logitix_admin''@''127.0.0.1''')
    ) x;
    IF v<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LOGITIX: acces PHP direct a users detecte.'; END IF;

    SELECT COUNT(*) INTO v FROM information_schema.TABLE_PRIVILEGES
    WHERE TABLE_SCHEMA='logitix'
      AND GRANTEE IN ('''logitix_client''@''127.0.0.1''','''logitix_admin''@''127.0.0.1''')
      AND PRIVILEGE_TYPE IN ('DELETE','DROP','ALTER','CREATE','INDEX','REFERENCES','TRIGGER');
    IF v<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LOGITIX: privilege destructif applicatif detecte.'; END IF;

    SELECT COUNT(*) INTO v FROM (
        SELECT GRANTEE,TABLE_NAME FROM information_schema.TABLE_PRIVILEGES
        WHERE TABLE_SCHEMA='logitix'
          AND TABLE_NAME IN ('users','devis','expeditions','vehicules','chauffeurs','remorques','entrepots','notifications')
          AND GRANTEE IN ('''logitix_client''@''127.0.0.1''','''logitix_admin''@''127.0.0.1''')
        UNION ALL
        SELECT GRANTEE,TABLE_NAME FROM information_schema.COLUMN_PRIVILEGES
        WHERE TABLE_SCHEMA='logitix'
          AND TABLE_NAME IN ('users','devis','expeditions','vehicules','chauffeurs','remorques','entrepots','notifications')
          AND GRANTEE IN ('''logitix_client''@''127.0.0.1''','''logitix_admin''@''127.0.0.1''')
    ) b;
    IF v<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LOGITIX: acces direct applicatif a une table metier detecte.'; END IF;

    SELECT COUNT(*) INTO v FROM information_schema.USER_PRIVILEGES
    WHERE GRANTEE IN ('''logitix_client''@''127.0.0.1''','''logitix_admin''@''127.0.0.1''')
      AND PRIVILEGE_TYPE='GRANT OPTION';
    IF v<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LOGITIX: GRANT OPTION applicatif detecte.'; END IF;

    SELECT COUNT(*) INTO v FROM mysql.user
    WHERE ((User='logitix_client_view_definer' AND Host='localhost' AND account_locked='Y')
        OR (User='logitix_admin_view_definer' AND Host='localhost' AND account_locked='Y'));
    IF v<>2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LOGITIX: comptes DEFINER non verrouilles.'; END IF;

    SELECT COUNT(*) INTO v FROM information_schema.COLUMN_PRIVILEGES
    WHERE TABLE_SCHEMA='logitix' AND TABLE_NAME='admin_client_users'
      AND GRANTEE='''logitix_admin''@''127.0.0.1'''
      AND PRIVILEGE_TYPE='SELECT' AND COLUMN_NAME='mot_de_passe';
    IF v<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LOGITIX: hash mot de passe client lisible par PHP admin.'; END IF;

    SELECT COUNT(DISTINCT INDEX_NAME) INTO v FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA='logitix' AND TABLE_NAME='expeditions' AND NON_UNIQUE=0
      AND INDEX_NAME IN ('uq_expeditions_devis','uq_expeditions_active_vehicule','uq_expeditions_active_chauffeur','uq_expeditions_active_remorque');
    IF v<>4 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LOGITIX: protections anti double-affectation manquantes.'; END IF;

    SELECT COUNT(*) INTO v FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA='logitix' AND TABLE_NAME='expeditions'
      AND CONSTRAINT_NAME IN ('fk_expeditions_vehicule','fk_expeditions_chauffeur','fk_expeditions_remorque')
      AND DELETE_RULE IN ('RESTRICT','NO ACTION')
      AND UPDATE_RULE IN ('RESTRICT','NO ACTION');
    IF v<>3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LOGITIX: actions FK ressources incompatibles avec CHECK/colonnes generees.'; END IF;

    SELECT COUNT(*) INTO v FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA='logitix' AND TABLE_NAME='expeditions' AND CONSTRAINT_TYPE='CHECK'
      AND CONSTRAINT_NAME IN ('chk_expeditions_active_resources','chk_expeditions_delivered_date');
    IF v<>2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LOGITIX: contraintes metier expedition manquantes.'; END IF;
END$$
DELIMITER ;
CALL `__logitix_assert_secure_install`();
DROP PROCEDURE IF EXISTS `__logitix_assert_secure_install`;

-- Inspection finale ----------------------------------------------------
SELECT TABLE_NAME, DEFINER, SECURITY_TYPE, CHECK_OPTION, IS_UPDATABLE
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA='logitix'
ORDER BY TABLE_NAME;
SHOW GRANTS FOR 'logitix_client_view_definer'@'localhost';
SHOW GRANTS FOR 'logitix_admin_view_definer'@'localhost';
SHOW GRANTS FOR 'logitix_client'@'127.0.0.1';
SHOW GRANTS FOR 'logitix_admin'@'127.0.0.1';

-- =====================================================================
-- 16. MAINTENANCE OPTIONNELLE
-- =====================================================================
-- Rotation mots de passe MySQL : utiliser ALTER USER ... BY RANDOM PASSWORD,
-- puis mettre immediatement .env.local a jour.
-- Purge recommandee des journaux (>90 jours) via une tache DBA planifiee :
-- DELETE FROM `logitix`.`auth_attempts_client` WHERE `attempted_at` < (NOW() - INTERVAL 90 DAY);
-- DELETE FROM `logitix`.`auth_attempts_admin`  WHERE `attempted_at` < (NOW() - INTERVAL 90 DAY);
-- DELETE FROM `logitix`.`admin_audit_log`      WHERE `created_at`   < (NOW() - INTERVAL 365 DAY);
-- Ne jamais modifier un hash utilisateur a la main. Utiliser les outils PHP.

SELECT 'FIN LOGITIX MySQL v7.7.2 ALL-IN-ONE - installation + verification critiques PASS.' AS final_status;
