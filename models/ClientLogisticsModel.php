<?php
/**
 * Operations metier exposees au client LOGITIX.
 *
 * Important : toutes les lectures filtrent explicitement sur user_id.
 * Le compte MySQL client n'obtient aucun UPDATE/DELETE sur les donnees metier.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

class ClientLogisticsModel {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance('client')->getConnection();
    }

    public function dashboardStats(int $userId): array {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM client_expeditions WHERE user_id = :uid_total) AS total_expeditions,
                    (SELECT COUNT(*) FROM client_expeditions WHERE user_id = :uid_progress AND statut = 'en_cours') AS en_cours,
                    (SELECT COUNT(*) FROM client_expeditions WHERE user_id = :uid_delivered AND statut = 'livree') AS livrees,
                    (SELECT COUNT(*) FROM client_devis WHERE user_id = :uid_quotes) AS total_devis";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'uid_total' => $userId,
            'uid_progress' => $userId,
            'uid_delivered' => $userId,
            'uid_quotes' => $userId,
        ]);
        $row = $stmt->fetch() ?: [];
        return [
            'total_expeditions' => (int) ($row['total_expeditions'] ?? 0),
            'en_cours' => (int) ($row['en_cours'] ?? 0),
            'livrees' => (int) ($row['livrees'] ?? 0),
            'total_devis' => (int) ($row['total_devis'] ?? 0),
        ];
    }

    public function listExpeditions(int $userId, string $reference = ''): array {
        $sql = 'SELECT id, reference, depart, destination, statut, date_depart, date_livraison_estimee, date_livraison_reelle, date_creation
                FROM client_expeditions
                WHERE user_id = :user_id';
        $params = ['user_id' => $userId];

        if ($reference !== '') {
            $sql .= ' AND reference = :reference';
            $params['reference'] = strtoupper(trim($reference));
        }

        $sql .= ' ORDER BY date_creation DESC, id DESC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function createExpedition(int $userId, array $data): string {
        $sql = 'INSERT INTO client_expeditions (user_id, reference, depart, destination, date_depart)
                VALUES (:user_id, :reference, :depart, :destination, :date_depart)';
        $stmt = $this->pdo->prepare($sql);

        for ($attempt = 0; $attempt < 4; $attempt++) {
            $reference = generateTrackingNumber();
            try {
                $stmt->execute([
                    'user_id' => $userId,
                    'reference' => $reference,
                    'depart' => $data['depart'],
                    'destination' => $data['destination'],
                    'date_depart' => $data['date_depart'] ?: null,
                ]);
                return $reference;
            } catch (PDOException $e) {
                $mysqlCode = (int) ($e->errorInfo[1] ?? 0);
                if ($mysqlCode !== 1062 || $attempt === 3) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('Impossible de generer une reference expedition unique.');
    }

    public function listDevis(int $userId): array {
        $stmt = $this->pdo->prepare(
            'SELECT id, depart, destination, type_marchandise, poids_estime, date_souhaitee, message, statut,
                    montant_propose, devise, reponse_admin, date_traitement, date_creation
             FROM client_devis
             WHERE user_id = :user_id
             ORDER BY date_creation DESC, id DESC
             LIMIT 200'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function createDevis(int $userId, array $data): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO client_devis
             (user_id, depart, destination, type_marchandise, poids_estime, date_souhaitee, message)
             VALUES
             (:user_id, :depart, :destination, :type_marchandise, :poids_estime, :date_souhaitee, :message)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'depart' => $data['depart'],
            'destination' => $data['destination'],
            'type_marchandise' => $data['type_marchandise'] ?: null,
            'poids_estime' => $data['poids_estime'] !== '' ? $data['poids_estime'] : null,
            'date_souhaitee' => $data['date_souhaitee'] ?: null,
            'message' => $data['message'] ?: null,
        ]);

        $id = $this->pdo->lastInsertId();
        if ($id !== '0' && $id !== '') {
            return (int) $id;
        }

        // Fallback pour les INSERT via vue : recupere le dernier devis du client.
        $lookup = $this->pdo->prepare('SELECT id FROM client_devis WHERE user_id = :user_id ORDER BY id DESC LIMIT 1');
        $lookup->execute(['user_id' => $userId]);
        return (int) ($lookup->fetchColumn() ?: 0);
    }
}
