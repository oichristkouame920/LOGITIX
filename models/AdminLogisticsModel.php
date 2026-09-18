<?php
/**
 * Operations metier de l'administration LOGITIX.
 *
 * Toutes les operations passent par les vues admin a privileges minimaux.
 * Aucun DELETE n'est expose au compte MySQL applicatif admin.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

class AdminLogisticsModel {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance('admin')->getConnection();
    }

    public function dashboardStats(): array {
        $sql = "SELECT
            (SELECT COUNT(*) FROM admin_client_users) AS clients,
            (SELECT COUNT(*) FROM admin_expeditions) AS expeditions,
            (SELECT COUNT(*) FROM admin_expeditions WHERE statut = 'en_cours') AS expeditions_en_cours,
            (SELECT COUNT(*) FROM admin_devis WHERE statut = 'en_attente') AS devis_en_attente,
            (SELECT COUNT(*) FROM admin_vehicules WHERE statut = 'disponible') AS vehicules_disponibles,
            (SELECT COUNT(*) FROM admin_chauffeurs WHERE disponible = 1) AS chauffeurs_disponibles";
        $row = $this->pdo->query($sql)->fetch() ?: [];
        return array_map('intval', [
            'clients' => $row['clients'] ?? 0,
            'expeditions' => $row['expeditions'] ?? 0,
            'expeditions_en_cours' => $row['expeditions_en_cours'] ?? 0,
            'devis_en_attente' => $row['devis_en_attente'] ?? 0,
            'vehicules_disponibles' => $row['vehicules_disponibles'] ?? 0,
            'chauffeurs_disponibles' => $row['chauffeurs_disponibles'] ?? 0,
        ]);
    }

    public function recentActivity(int $limit = 8): array {
        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->query(
            "SELECT action, entity_type, entity_id, details, created_at
             FROM admin_audit_log
             ORDER BY id DESC LIMIT {$limit}"
        );
        return $stmt->fetchAll();
    }

    public function listAudit(int $limit = 200): array {
        $limit = max(1, min(500, $limit));
        return $this->pdo->query(
            "SELECT id, actor_user_id, action, entity_type, entity_id, details, INET6_NTOA(ip) AS ip, created_at
             FROM admin_audit_log
             ORDER BY id DESC LIMIT {$limit}"
        )->fetchAll();
    }

    public function listClients(string $search = '', string $state = ''): array {
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(nom LIKE :q1 OR prenom LIKE :q2 OR email LIKE :q3 OR telephone LIKE :q4 OR raison_sociale LIKE :q5)';
            $like = '%' . $search . '%';
            $params += ['q1'=>$like,'q2'=>$like,'q3'=>$like,'q4'=>$like,'q5'=>$like];
        }
        if ($state === 'active') {
            $where[] = 'actif = 1';
        } elseif ($state === 'inactive') {
            $where[] = 'actif = 0';
        }
        $sql = 'SELECT id, nom, prenom, email, telephone, status, raison_sociale, rccm, actif, password_must_change, created_at, updated_at
                FROM admin_client_users';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 300';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getClient(int $id): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT id, nom, prenom, email, telephone, status, raison_sociale, rccm, actif, password_must_change, password_changed_at, created_at, updated_at
             FROM admin_client_users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function setClientActive(int $clientId, bool $active, int $actorId, string $ip): void {
        $this->transactional(function () use ($clientId, $active, $actorId, $ip): void {
            $check = $this->pdo->prepare('SELECT id, actif FROM admin_client_users WHERE id = :id FOR UPDATE');
            $check->execute(['id' => $clientId]);
            $client = $check->fetch();
            if (!$client) {
                throw new RuntimeException('Client introuvable.');
            }
            if ((int) $client['actif'] === ($active ? 1 : 0)) {
                throw new RuntimeException('Cet etat est deja applique au compte client.');
            }

            $stmt = $this->pdo->prepare(
                'UPDATE admin_client_users
                 SET actif = :actif, auth_version = auth_version + 1
                 WHERE id = :id'
            );
            $stmt->execute(['actif' => $active ? 1 : 0, 'id' => $clientId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Modification du compte client impossible.');
            }
            $this->logAudit($actorId, $active ? 'client.activate' : 'client.deactivate', 'user', $clientId, null, $ip);
        });
    }

    public function resetClientPassword(int $clientId, int $actorId, string $ip): string {
        $password = 'Tmp!' . bin2hex(random_bytes(12)) . 'A7';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('Impossible de generer le mot de passe temporaire.');
        }

        return $this->transactional(function () use ($clientId, $actorId, $ip, $password, $hash): string {
            $check = $this->pdo->prepare('SELECT id FROM admin_client_users WHERE id = :id FOR UPDATE');
            $check->execute(['id' => $clientId]);
            if (!$check->fetch()) {
                throw new RuntimeException('Client introuvable.');
            }

            $stmt = $this->pdo->prepare(
                'UPDATE admin_client_users
                 SET mot_de_passe = :password,
                     password_must_change = 1,
                     password_changed_at = NULL,
                     auth_version = auth_version + 1
                 WHERE id = :id'
            );
            $stmt->execute(['password' => $hash, 'id' => $clientId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Reinitialisation du mot de passe impossible.');
            }
            $this->logAudit($actorId, 'client.password_reset', 'user', $clientId, null, $ip);
            return $password;
        });
    }

    public function listDevis(string $status = ''): array {
        $sql = 'SELECT d.id, d.user_id, d.depart, d.destination, d.type_marchandise, d.poids_estime,
                       d.date_souhaitee, d.message, d.statut, d.montant_propose, d.devise, d.reponse_admin,
                       d.date_traitement, d.date_creation,
                       u.nom, u.prenom, u.email, u.raison_sociale
                FROM admin_devis d
                LEFT JOIN user_directory u ON u.id = d.user_id';
        $params = [];
        if (in_array($status, ['en_attente', 'valide', 'refuse', 'converti'], true)) {
            $sql .= ' WHERE d.statut = :statut';
            $params['statut'] = $status;
        }
        $sql .= ' ORDER BY d.date_creation DESC, d.id DESC LIMIT 300';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateDevis(int $id, string $status, ?string $amount, string $currency, string $response, int $actorId, string $ip): void {
        if (!in_array($status, ['en_attente', 'valide', 'refuse'], true)) {
            throw new InvalidArgumentException('Statut de devis invalide.');
        }

        $this->transactional(function () use ($id, $status, $amount, $currency, $response, $actorId, $ip): void {
            $check = $this->pdo->prepare('SELECT id, statut FROM admin_devis WHERE id = :id FOR UPDATE');
            $check->execute(['id' => $id]);
            $existing = $check->fetch();
            if (!$existing) {
                throw new RuntimeException('Devis introuvable.');
            }
            if (($existing['statut'] ?? '') === 'converti') {
                throw new RuntimeException('Ce devis a deja ete converti et ne peut plus etre modifie.');
            }

            $stmt = $this->pdo->prepare(
                'UPDATE admin_devis
                 SET statut = :statut,
                     montant_propose = :montant,
                     devise = :devise,
                     reponse_admin = :reponse,
                     date_traitement = NOW()
                 WHERE id = :id AND statut <> \'converti\''
            );
            $stmt->execute([
                'statut' => $status,
                'montant' => $amount,
                'devise' => $currency,
                'reponse' => $response !== '' ? $response : null,
                'id' => $id,
            ]);

            $this->logAudit(
                $actorId,
                'devis.update',
                'devis',
                $id,
                json_encode(['statut' => $status, 'devise' => $currency], JSON_UNESCAPED_UNICODE),
                $ip
            );
        });
    }

    public function convertDevisToExpedition(int $devisId, int $actorId, string $ip): string {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, user_id, depart, destination, date_souhaitee, statut
                 FROM admin_devis WHERE id = :id FOR UPDATE'
            );
            $stmt->execute(['id' => $devisId]);
            $devis = $stmt->fetch();
            if (!$devis) {
                throw new RuntimeException('Devis introuvable.');
            }
            if ($devis['statut'] === 'converti') {
                throw new RuntimeException('Ce devis a deja ete converti.');
            }
            if ($devis['statut'] !== 'valide') {
                throw new RuntimeException('Le devis doit etre valide avant conversion.');
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO admin_expeditions
                 (devis_id, user_id, reference, depart, destination, date_depart)
                 VALUES (:devis_id, :user_id, :reference, :depart, :destination, :date_depart)'
            );
            $reference = '';
            for ($i = 0; $i < 5; $i++) {
                $reference = generateTrackingNumber();
                try {
                    $insert->execute([
                        'devis_id' => (int) $devis['id'],
                        'user_id' => (int) $devis['user_id'],
                        'reference' => $reference,
                        'depart' => $devis['depart'],
                        'destination' => $devis['destination'],
                        'date_depart' => $devis['date_souhaitee'] ? $devis['date_souhaitee'] . ' 08:00:00' : null,
                    ]);
                    break;
                } catch (PDOException $e) {
                    if ((int) ($e->errorInfo[1] ?? 0) !== 1062 || $i === 4) {
                        throw $e;
                    }
                }
            }

            $update = $this->pdo->prepare("UPDATE admin_devis SET statut = 'converti', date_traitement = NOW() WHERE id = :id");
            $update->execute(['id' => $devisId]);
            $this->logAudit($actorId, 'devis.convert', 'devis', $devisId, json_encode(['reference' => $reference]), $ip);
            $this->pdo->commit();
            return $reference;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function listExpeditions(string $status = '', string $search = ''): array {
        $where = [];
        $params = [];
        if (in_array($status, ['planifiee', 'en_cours', 'livree', 'annulee'], true)) {
            $where[] = 'e.statut = :statut';
            $params['statut'] = $status;
        }
        if ($search !== '') {
            $where[] = '(e.reference LIKE :q1 OR e.depart LIKE :q2 OR e.destination LIKE :q3 OR u.email LIKE :q4)';
            $like = '%' . $search . '%';
            $params += ['q1'=>$like,'q2'=>$like,'q3'=>$like,'q4'=>$like];
        }
        $sql = 'SELECT e.id, e.devis_id, e.user_id, e.reference, e.depart, e.destination, e.statut,
                       e.vehicule_id, e.chauffeur_id, e.remorque_id, e.date_depart,
                       e.date_livraison_estimee, e.date_livraison_reelle, e.date_creation,
                       u.nom, u.prenom, u.email, v.nom AS vehicule_nom, c.nom AS chauffeur_nom,
                       c.prenom AS chauffeur_prenom, r.nom AS remorque_nom
                FROM admin_expeditions e
                LEFT JOIN user_directory u ON u.id = e.user_id
                LEFT JOIN admin_vehicules v ON v.id = e.vehicule_id
                LEFT JOIN admin_chauffeurs c ON c.id = e.chauffeur_id
                LEFT JOIN admin_remorques r ON r.id = e.remorque_id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY e.date_creation DESC, e.id DESC LIMIT 300';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateExpedition(int $id, array $data, int $actorId, string $ip): void {
        $status = (string) ($data['statut'] ?? '');
        if (!in_array($status, ['planifiee', 'en_cours', 'livree', 'annulee'], true)) {
            throw new InvalidArgumentException('Statut expedition invalide.');
        }
        if ($status === 'en_cours' && (!$data['vehicule_id'] || !$data['chauffeur_id'])) {
            throw new InvalidArgumentException('Une expedition en cours doit avoir un vehicule et un chauffeur.');
        }
        if ($status === 'en_cours' && empty($data['date_depart'])) {
            throw new InvalidArgumentException('Une expedition en cours doit avoir une date de depart.');
        }
        if ($status === 'livree' && empty($data['date_livraison_reelle'])) {
            throw new InvalidArgumentException('Une expedition livree doit avoir une date de livraison reelle.');
        }

        $this->pdo->beginTransaction();
        try {
            $currentStmt = $this->pdo->prepare(
                'SELECT id, statut, vehicule_id, chauffeur_id, remorque_id
                 FROM admin_expeditions WHERE id = :id FOR UPDATE'
            );
            $currentStmt->execute(['id' => $id]);
            $current = $currentStmt->fetch();
            if (!$current) {
                throw new RuntimeException('Expedition introuvable.');
            }

            $transitions = [
                'planifiee' => ['planifiee', 'en_cours', 'annulee'],
                'en_cours' => ['en_cours', 'livree', 'annulee'],
                'livree' => ['livree'],
                'annulee' => ['annulee'],
            ];
            $currentStatus = (string) $current['statut'];
            if (!in_array($status, $transitions[$currentStatus] ?? [], true)) {
                throw new RuntimeException("Transition de statut interdite : {$currentStatus} -> {$status}.");
            }

            if ($data['vehicule_id']) {
                $this->assertVehicleAssignable((int) $data['vehicule_id'], $id, $status === 'en_cours');
            }
            if ($data['chauffeur_id']) {
                $this->assertDriverAssignable((int) $data['chauffeur_id'], $id, $status === 'en_cours');
            }
            if ($data['remorque_id']) {
                $this->assertTrailerAssignable((int) $data['remorque_id'], $id, $status === 'en_cours');
            }

            $stmt = $this->pdo->prepare(
                'UPDATE admin_expeditions
                 SET statut = :statut,
                     vehicule_id = :vehicule_id,
                     chauffeur_id = :chauffeur_id,
                     remorque_id = :remorque_id,
                     date_depart = :date_depart,
                     date_livraison_estimee = :date_estimee,
                     date_livraison_reelle = :date_reelle
                 WHERE id = :id'
            );
            $stmt->execute([
                'statut' => $status,
                'vehicule_id' => $data['vehicule_id'],
                'chauffeur_id' => $data['chauffeur_id'],
                'remorque_id' => $data['remorque_id'],
                'date_depart' => $data['date_depart'],
                'date_estimee' => $data['date_livraison_estimee'],
                'date_reelle' => $data['date_livraison_reelle'],
                'id' => $id,
            ]);

            // Synchronise la disponibilite des ressources avec les expeditions
            // reellement en cours. Les index uniques en base protegent aussi
            // contre une double affectation concurrente entre deux requetes.
            if ($status === 'en_cours') {
                if ($data['vehicule_id']) {
                    $st = $this->pdo->prepare("UPDATE admin_vehicules SET statut='en_service' WHERE id=:id");
                    $st->execute(['id' => $data['vehicule_id']]);
                }
                if ($data['chauffeur_id']) {
                    $st = $this->pdo->prepare('UPDATE admin_chauffeurs SET disponible=0 WHERE id=:id');
                    $st->execute(['id' => $data['chauffeur_id']]);
                }
                if ($data['remorque_id']) {
                    $st = $this->pdo->prepare("UPDATE admin_remorques SET statut='en_service' WHERE id=:id");
                    $st->execute(['id' => $data['remorque_id']]);
                }
            }

            $this->releaseOldResources($id, $current, $data, $status);
            $this->logAudit(
                $actorId,
                'expedition.update',
                'expedition',
                $id,
                json_encode(['ancien_statut' => $currentStatus, 'statut' => $status], JSON_UNESCAPED_UNICODE),
                $ip
            );
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new RuntimeException('Une ressource vient d etre affectee a une autre expedition. Rechargez la page puis choisissez une ressource disponible.', 0, $e);
            }
            throw $e;
        }
    }

    private function assertVehicleAssignable(int $vehicleId, int $expeditionId, bool $mustBeAvailable): void {
        $st = $this->pdo->prepare('SELECT id, statut FROM admin_vehicules WHERE id=:id FOR UPDATE');
        $st->execute(['id'=>$vehicleId]);
        $row=$st->fetch();
        if(!$row){throw new InvalidArgumentException('Vehicule introuvable.');}
        if($row['statut']==='maintenance'){throw new RuntimeException('Ce vehicule est en maintenance.');}
        if($mustBeAvailable){
            $busy=$this->pdo->prepare("SELECT COUNT(*) FROM admin_expeditions WHERE vehicule_id=:rid AND statut='en_cours' AND id<>:eid");
            $busy->execute(['rid'=>$vehicleId,'eid'=>$expeditionId]);
            if((int)$busy->fetchColumn()>0){throw new RuntimeException('Ce vehicule est deja affecte a une expedition en cours.');}
            if($row['statut']==='en_service' && !$this->resourceAlreadyAssignedToExpedition('vehicule_id',$vehicleId,$expeditionId)){
                throw new RuntimeException('Ce vehicule est deja marque en service.');
            }
        }
    }

    private function assertDriverAssignable(int $driverId, int $expeditionId, bool $mustBeAvailable): void {
        $st=$this->pdo->prepare('SELECT id, disponible FROM admin_chauffeurs WHERE id=:id FOR UPDATE');
        $st->execute(['id'=>$driverId]);
        $row=$st->fetch();
        if(!$row){throw new InvalidArgumentException('Chauffeur introuvable.');}
        if($mustBeAvailable){
            $busy=$this->pdo->prepare("SELECT COUNT(*) FROM admin_expeditions WHERE chauffeur_id=:rid AND statut='en_cours' AND id<>:eid");
            $busy->execute(['rid'=>$driverId,'eid'=>$expeditionId]);
            if((int)$busy->fetchColumn()>0){throw new RuntimeException('Ce chauffeur est deja affecte a une expedition en cours.');}
            if((int)$row['disponible']!==1 && !$this->resourceAlreadyAssignedToExpedition('chauffeur_id',$driverId,$expeditionId)){
                throw new RuntimeException('Ce chauffeur est marque indisponible.');
            }
        }
    }

    private function assertTrailerAssignable(int $trailerId, int $expeditionId, bool $mustBeAvailable): void {
        $st=$this->pdo->prepare('SELECT id, statut FROM admin_remorques WHERE id=:id FOR UPDATE');
        $st->execute(['id'=>$trailerId]);
        $row=$st->fetch();
        if(!$row){throw new InvalidArgumentException('Remorque introuvable.');}
        if($row['statut']==='maintenance'){throw new RuntimeException('Cette remorque est en maintenance.');}
        if($mustBeAvailable){
            $busy=$this->pdo->prepare("SELECT COUNT(*) FROM admin_expeditions WHERE remorque_id=:rid AND statut='en_cours' AND id<>:eid");
            $busy->execute(['rid'=>$trailerId,'eid'=>$expeditionId]);
            if((int)$busy->fetchColumn()>0){throw new RuntimeException('Cette remorque est deja affectee a une expedition en cours.');}
            if($row['statut']==='en_service' && !$this->resourceAlreadyAssignedToExpedition('remorque_id',$trailerId,$expeditionId)){
                throw new RuntimeException('Cette remorque est deja marquee en service.');
            }
        }
    }

    private function resourceAlreadyAssignedToExpedition(string $field, int $resourceId, int $expeditionId): bool {
        if (!in_array($field, ['vehicule_id','chauffeur_id','remorque_id'], true)) {
            throw new InvalidArgumentException('Ressource invalide.');
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_expeditions WHERE id=:eid AND {$field}=:rid");
        $stmt->execute(['eid'=>$expeditionId,'rid'=>$resourceId]);
        return (int)$stmt->fetchColumn() === 1;
    }

    private function releaseOldResources(int $expeditionId, array $old, array $new, string $newStatus): void {
        $oldWasActive = $old['statut'] === 'en_cours';
        if (!$oldWasActive) { return; }

        $pairs = [
            ['vehicule_id','admin_vehicules','statut',"'disponible'"],
            ['chauffeur_id','admin_chauffeurs','disponible','1'],
            ['remorque_id','admin_remorques','statut',"'disponible'"],
        ];
        foreach($pairs as [$field,$table,$column,$valueSql]){
            $oldId=(int)($old[$field]??0);
            if($oldId<=0){continue;}
            $newId=(int)($new[$field]??0);
            if($newStatus==='en_cours' && $oldId===$newId){continue;}
            $busy=$this->pdo->prepare("SELECT COUNT(*) FROM admin_expeditions WHERE {$field}=:rid AND statut='en_cours' AND id<>:eid");
            $busy->execute(['rid'=>$oldId,'eid'=>$expeditionId]);
            if((int)$busy->fetchColumn()===0){
                $rel=$this->pdo->prepare("UPDATE {$table} SET {$column}={$valueSql} WHERE id=:id");
                $rel->execute(['id'=>$oldId]);
            }
        }
    }

    public function listVehicules(): array {
        return $this->pdo->query('SELECT id, nom, categorie, immatriculation, capacite, statut FROM admin_vehicules ORDER BY nom, id')->fetchAll();
    }

    public function listRemorques(): array {
        return $this->pdo->query('SELECT id, nom, type, capacite, statut FROM admin_remorques ORDER BY nom, id')->fetchAll();
    }

    public function listChauffeurs(): array {
        return $this->pdo->query('SELECT id, nom, prenom, permis, telephone, disponible FROM admin_chauffeurs ORDER BY nom, prenom, id')->fetchAll();
    }

    public function listEntrepots(): array {
        return $this->pdo->query('SELECT id, nom, ville, pays FROM admin_entrepots ORDER BY pays, ville, nom, id')->fetchAll();
    }

    public function saveVehicule(?int $id, array $data, int $actorId, string $ip): void {
        if (!in_array($data['statut'], ['disponible', 'en_service', 'maintenance'], true)) {
            throw new InvalidArgumentException('Statut vehicule invalide.');
        }
        $this->transactional(function () use ($id, $data, $actorId, $ip): void {
            if ($id && $data['statut'] !== 'en_service' && $this->resourceBusy('vehicule_id', $id)) {
                throw new RuntimeException('Ce vehicule est affecte a une expedition en cours.');
            }
            if ($id) {
                $stmt = $this->pdo->prepare('UPDATE admin_vehicules SET nom=:nom, categorie=:categorie, immatriculation=:immatriculation, capacite=:capacite, statut=:statut WHERE id=:id');
                $params = $data + ['id' => $id];
                $action = 'vehicule.update';
            } else {
                $stmt = $this->pdo->prepare('INSERT INTO admin_vehicules (nom,categorie,immatriculation,capacite,statut) VALUES (:nom,:categorie,:immatriculation,:capacite,:statut)');
                $params = $data;
                $action = 'vehicule.create';
            }
            $stmt->execute($params);
            if ($id && $stmt->rowCount() === 0 && !$this->rowExists('admin_vehicules', $id)) {
                throw new RuntimeException('Vehicule introuvable.');
            }
            $entityId = $id ?: (int) $this->pdo->lastInsertId();
            $this->logAudit($actorId, $action, 'vehicule', $entityId ?: null, null, $ip);
        });
    }

    public function saveRemorque(?int $id, array $data, int $actorId, string $ip): void {
        if (!in_array($data['statut'], ['disponible', 'en_service', 'maintenance'], true)) {
            throw new InvalidArgumentException('Statut remorque invalide.');
        }
        $this->transactional(function () use ($id, $data, $actorId, $ip): void {
            if ($id && $data['statut'] !== 'en_service' && $this->resourceBusy('remorque_id', $id)) {
                throw new RuntimeException('Cette remorque est affectee a une expedition en cours.');
            }
            if ($id) {
                $stmt = $this->pdo->prepare('UPDATE admin_remorques SET nom=:nom, type=:type, capacite=:capacite, statut=:statut WHERE id=:id');
                $params = $data + ['id' => $id];
                $action = 'remorque.update';
            } else {
                $stmt = $this->pdo->prepare('INSERT INTO admin_remorques (nom,type,capacite,statut) VALUES (:nom,:type,:capacite,:statut)');
                $params = $data;
                $action = 'remorque.create';
            }
            $stmt->execute($params);
            if ($id && $stmt->rowCount() === 0 && !$this->rowExists('admin_remorques', $id)) {
                throw new RuntimeException('Remorque introuvable.');
            }
            $entityId = $id ?: (int) $this->pdo->lastInsertId();
            $this->logAudit($actorId, $action, 'remorque', $entityId ?: null, null, $ip);
        });
    }

    public function saveChauffeur(?int $id, array $data, int $actorId, string $ip): void {
        $this->transactional(function () use ($id, $data, $actorId, $ip): void {
            if ($id && (int) $data['disponible'] === 1 && $this->resourceBusy('chauffeur_id', $id)) {
                throw new RuntimeException('Ce chauffeur est affecte a une expedition en cours.');
            }
            if ($id) {
                $stmt = $this->pdo->prepare('UPDATE admin_chauffeurs SET nom=:nom, prenom=:prenom, permis=:permis, telephone=:telephone, disponible=:disponible WHERE id=:id');
                $params = $data + ['id' => $id];
                $action = 'chauffeur.update';
            } else {
                $stmt = $this->pdo->prepare('INSERT INTO admin_chauffeurs (nom,prenom,permis,telephone,disponible) VALUES (:nom,:prenom,:permis,:telephone,:disponible)');
                $params = $data;
                $action = 'chauffeur.create';
            }
            $stmt->execute($params);
            if ($id && $stmt->rowCount() === 0 && !$this->rowExists('admin_chauffeurs', $id)) {
                throw new RuntimeException('Chauffeur introuvable.');
            }
            $entityId = $id ?: (int) $this->pdo->lastInsertId();
            $this->logAudit($actorId, $action, 'chauffeur', $entityId ?: null, null, $ip);
        });
    }

    public function saveEntrepot(?int $id, array $data, int $actorId, string $ip): void {
        $this->transactional(function () use ($id, $data, $actorId, $ip): void {
            if ($id) {
                $stmt = $this->pdo->prepare('UPDATE admin_entrepots SET nom=:nom, ville=:ville, pays=:pays WHERE id=:id');
                $params = $data + ['id' => $id];
                $action = 'entrepot.update';
            } else {
                $stmt = $this->pdo->prepare('INSERT INTO admin_entrepots (nom,ville,pays) VALUES (:nom,:ville,:pays)');
                $params = $data;
                $action = 'entrepot.create';
            }
            $stmt->execute($params);
            if ($id && $stmt->rowCount() === 0 && !$this->rowExists('admin_entrepots', $id)) {
                throw new RuntimeException('Entrepot introuvable.');
            }
            $entityId = $id ?: (int) $this->pdo->lastInsertId();
            $this->logAudit($actorId, $action, 'entrepot', $entityId ?: null, null, $ip);
        });
    }

    private function resourceBusy(string $field, int $resourceId): bool {
        if (!in_array($field, ['vehicule_id','chauffeur_id','remorque_id'], true)) {
            throw new InvalidArgumentException('Ressource invalide.');
        }
        $stmt=$this->pdo->prepare("SELECT COUNT(*) FROM admin_expeditions WHERE {$field}=:id AND statut='en_cours'");
        $stmt->execute(['id'=>$resourceId]);
        return (int)$stmt->fetchColumn()>0;
    }

    private function rowExists(string $view, int $id): bool {
        if (!in_array($view, ['admin_vehicules', 'admin_remorques', 'admin_chauffeurs', 'admin_entrepots'], true)) {
            throw new InvalidArgumentException('Vue de ressource invalide.');
        }
        $stmt = $this->pdo->prepare("SELECT id FROM {$view} WHERE id=:id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    private function transactional(callable $callback): mixed {
        $started = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $started = true;
        }
        try {
            $result = $callback();
            if ($started) {
                $this->pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($started && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function logAudit(int $actorId, string $action, string $entityType, ?int $entityId, ?string $details, string $ip): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO admin_audit_log (actor_user_id, action, entity_type, entity_id, details, ip)
             VALUES (:actor, :action, :type, :entity, :details, INET6_ATON(:ip))'
        );
        $stmt->execute([
            'actor' => $actorId,
            'action' => $action,
            'type' => $entityType,
            'entity' => $entityId,
            'details' => $details,
            'ip' => $ip,
        ]);
    }
}
