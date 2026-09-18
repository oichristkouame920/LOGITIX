<?php
/**
 * Compatibilite v7.7.2.
 *
 * L'ancien upgrade client v7.6 est volontairement redirige vers la migration
 * v7.7.2 afin de ne jamais recreer d'anciennes vues qui supprimeraient les
 * colonnes de reponse aux devis ou les privileges administrateur.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

fwrite(STDOUT, "[INFO] LOGITIX v7.7.2 remplace l'ancien upgrade client v7.6.\n");
fwrite(STDOUT, "[INFO] La migration complete non destructive v7.7.2 va etre utilisee.\n\n");
require __DIR__ . '/upgrade_admin_features.php';
