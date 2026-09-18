# Securite MySQL LOGITIX v7.7.2

Source SQL unique : `../Logitix_SQL_FINAL_COMPLET.sql`.

Principes : MySQL 8.0.18+ uniquement ; comptes client/admin distincts ; aucun acces PHP direct a `users` ; vues `SQL SECURITY DEFINER` avec definers verrouilles ; privileges au niveau colonne ; aucun `GRANT ALL`, `GRANT OPTION` ou role applicatif residuel ; comptes PHP limites a `127.0.0.1` ; TOTP chiffre/anti-rejeu ; journaux d'authentification separes ; journal d'audit admin ; garde-fou anti-ecrasement ; assertions SQL finales bloquantes.

Pour les expeditions, la v7.7.2 ajoute des colonnes generees et index uniques qui n'existent que lorsque `statut='en_cours'`. MySQL empeche ainsi atomiquement qu'un meme vehicule, chauffeur ou remorque soit affecte simultanement a deux expeditions actives, y compris en cas de requetes concurrentes. Des CHECK imposent aussi vehicule + chauffeur + date de depart pour une expedition en cours, et une date reelle pour une expedition livree.

La migration non destructive `includes/admin_upgrade.php` repart d'un jeu de privileges connu : elle retire les privileges/roles residuels des quatre comptes dedies, puis reaccorde uniquement les droits attendus. Elle ne modifie pas leurs mots de passe.
