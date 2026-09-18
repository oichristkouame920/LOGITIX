# LOGITIX - changements et ameliorations cumules jusqu'a v7.7.2

## Socle securite

- PDO MySQL avec requetes preparees natives et multi-statements desactives lorsque disponibles.
- Comptes MySQL applicatifs distincts `logitix_client` / `logitix_admin`.
- Vues `SQL SECURITY DEFINER` avec comptes DEFINER dedies, explicites et verrouilles.
- Privileges SQL normalises et minimaux ; aucun `GRANT ALL`, aucun `GRANT OPTION`, aucun DELETE metier pour les comptes PHP.
- `.env.local` hors ZIP/Git et bloque depuis le Web ; mots de passe MySQL encodes en base64 uniquement pour un parsing robuste de l'environnement, pas comme mecanisme de chiffrement.
- HTTPS/proxy durci, TLS MySQL obligatoire pour une base distante en production.
- Blocage Web des fichiers internes, SQL, sauvegardes, outils CLI et documents sensibles.

## Authentification et sessions

- `password_hash()` / `password_verify()`.
- Mots de passe temporaires et `password_must_change`.
- `auth_version` pour invalider les anciennes sessions apres changement sensible.
- Regeneration d'identifiant de session et cookies client/admin separes.
- CSRF sur les actions d'ecriture.
- Rate limiting client/admin/contextes separes.
- Re-authentification recente avant reinitialisation du mot de passe d'un client.

## 2FA admin

- TOTP configurable/obligatoire pour l'admin.
- Secret TOTP chiffre cote application avec `LOGITIX_TOTP_ENCRYPTION_KEY`.
- Compteur anti-rejeu `totp_last_counter`.
- `tools/setup_local_env.php` conserve une cle TOTP existante valide lors d'un remplacement de `.env.local`.
- Reinitialisation admin forcee invalide explicitement l'ancien enrôlement 2FA et les anciennes sessions.

## Fonctionnalites client v7.6+

- `Mes expeditions`, `Nouvelle expedition`, `Suivre un colis`, `Devis`, `Demander un devis` actifs.
- Dashboard client alimente par MySQL.
- Chaque lecture metier client est limitee au `user_id` de la session.
- Vues client limitees aux colonnes necessaires ; aucun UPDATE/DELETE metier accorde au compte client.
- Le client voit maintenant le montant, la devise et la reponse admin sur ses devis.

## Interface admin v7.7.2

- Dashboard admin avec statistiques et activite recente.
- Gestion des clients : recherche, activation/desactivation, mot de passe temporaire.
- Gestion des devis : reponse, prix, devise, validation/refus, conversion en expedition.
- Gestion des expeditions : statut, vehicule, chauffeur, remorque, dates.
- Gestion de la flotte, des chauffeurs et des entrepots sans suppression destructive.
- Journal `admin_audit_log` pour les actions metier sensibles ; les mots de passe et secrets 2FA ne sont jamais journalises.
- Formulaires d'administration corriges en HTML5 valide sans modifier les fichiers CSS/JS/images existants.

## Integrite metier / concurrence

- Une expedition `en_cours` exige vehicule + chauffeur + date de depart.
- Une expedition `livree` exige une date de livraison reelle.
- Machine d'etat cote application : `planifiee -> en_cours -> livree`, avec annulation possible avant la livraison ; `livree` et `annulee` sont finales.
- Index uniques sur les colonnes generees `active_vehicule_id`, `active_chauffeur_id`, `active_remorque_id` : protection atomique contre la double affectation concurrente.
- Un devis ne peut etre rattache qu'a une expedition (`uq_expeditions_devis`).
- Les mises a jour metier sensibles et leur ecriture dans le journal d'audit sont transactionnelles.
- Les ressources sont automatiquement marquees en service/indisponibles et liberees lors de la fin ou du changement d'affectation.

## Migration / bootstrap v7.7.2

- `includes/admin_upgrade.php` : migration non destructive, relancable, avec controles de conflits avant ajout des contraintes.
- La migration normalise les privileges existants sans changer les mots de passe MySQL applicatifs.
- L'ancien `tools/upgrade_client_features.php` redirige vers la migration complete pour eviter de recreer des vues obsoletes.
- `admin/bootstrap.php` fournit une alternative Web au CLI/Xdebug, strictement limitee au mode development + localhost + MySQL local + activation explicite dans `.env.local` + CSRF.
- Aucun identifiant DBA n'est stocke dans le projet ou dans `.env.local` par l'assistant bootstrap.
- Le bootstrap refuse par defaut d'ecraser un administrateur deja actif.

## Installation SQL unique

- `Logitix_SQL_FINAL_COMPLET.sql` cible MySQL 8.0.18+ et refuse MariaDB.
- 12 tables, 13 vues securisees, contraintes/index, comptes et privileges dans un seul script.
- Garde-fou anti-reinstallation destructive.
- Assertions SQL finales bloquantes sur les objets et protections de securite/integrite.

Les fichiers visuels historiques (CSS, JavaScript, images et polices) n'ont pas ete modifies par cette evolution ; les nouveaux ecrans admin reutilisent l'identite LOGITIX existante.
