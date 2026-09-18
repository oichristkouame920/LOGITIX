# Ordre d'installation LOGITIX v7.7.2

## Installation neuve

1. Demarrer MySQL dans WampServer et noter son port.
2. Importer `../Logitix_SQL_FINAL_COMPLET.sql` avec root/DBA.
3. Verifier que les controles SQL finaux ne signalent aucune erreur.
4. Conserver uniquement les mots de passe de `logitix_client@127.0.0.1` et `logitix_admin@127.0.0.1`.
5. Creer `.env.local` avec `tools/setup_local_env.php` ou a partir de `.env.example`.
6. Diagnostiquer le client et l'admin.
7. Activer l'administrateur avec `tools/activate_admin.php` ou, en local, `admin/bootstrap.php` apres activation temporaire de `LOGITIX_ADMIN_BOOTSTRAP_ENABLED=true`.

## Base existante v7.6/v7.7

Ne pas reimporter le SQL complet. Utiliser `tools/upgrade_admin_features.php` ou l'assistant local `admin/bootstrap.php`. La migration v7.7.2 est non destructive et refuse de poursuivre si des donnees existantes violent les nouvelles contraintes.

Le SQL complet contient `SET @LOGITIX_ALLOW_DESTRUCTIVE_REINSTALL = 0;`. Cette valeur doit rester a `0` sauf reinstallation volontaire apres sauvegarde.
