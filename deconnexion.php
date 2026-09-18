<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
if (!isPost()) {
    http_response_code(405);
    exit('Méthode non autorisée.');
}
requireCsrf();
logout();
redirect('se_connecter.php');
