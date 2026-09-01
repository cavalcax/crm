<?php
/**
 * API para gerenciamento de Notificações e Inscrições Push
 */

require_once '../config/db.php';
require_once '../helpers/functions.php';
require_once '../helpers/push-helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Suporte a body raw JSON se enviado via fetch
$rawInput = file_get_contents('php://input');
$jsonData = json_decode($rawInput, true);
if (is_array($jsonData)) {
    if (empty($action) && isset($jsonData['action'])) {
        $action = $jsonData['action'];
    }
}

switch ($action) {
    case 'vapid_public_key':
        $vapid = getVapidKeys();
        echo json_encode([
            'success' => true,
            'publicKey' => $vapid['publicKey']
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'subscribe_push':
        $endpoint = $jsonData['endpoint'] ?? $_POST['endpoint'] ?? '';
        $p256dh = $jsonData['keys']['p256dh'] ?? $jsonData['p256dh'] ?? $_POST['p256dh'] ?? '';
        $auth = $jsonData['keys']['auth'] ?? $jsonData['auth'] ?? $_POST['auth'] ?? '';

        if (empty($endpoint) || empty($p256dh) || empty($auth)) {
            echo json_encode([
                'success' => false, 
                'error' => 'Dados de inscrição incompletos (endpoint, p256dh ou auth ausentes)',
                'received' => ['has_endpoint' => !empty($endpoint), 'has_p256dh' => !empty($p256dh), 'has_auth' => !empty($auth)]
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $endpointHash = hash('sha256', $endpoint);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO " . TABLE_NAME . "push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), updated_at = NOW()
            ");
            $stmt->execute([$user_id, $endpoint, $endpointHash, $p256dh, $auth]);

            echo json_encode(['success' => true, 'message' => 'Dispositivo inscrito com sucesso!'], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;

    case 'unsubscribe_push':
        $endpoint = $jsonData['endpoint'] ?? $_POST['endpoint'] ?? '';
        if (!empty($endpoint)) {
            $endpointHash = hash('sha256', $endpoint);
            $stmt = $pdo->prepare("DELETE FROM " . TABLE_NAME . "push_subscriptions WHERE user_id = ? AND endpoint_hash = ?");
            $stmt->execute([$user_id, $endpointHash]);
        }
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;

    case 'mark_read':
        $notifId = (int) ($jsonData['id'] ?? $_POST['id'] ?? 0);
        if ($notifId > 0) {
            $stmt = $pdo->prepare("UPDATE " . TABLE_NAME . "notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$notifId, $user_id]);
        }
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;

    case 'mark_all_read':
        $stmt = $pdo->prepare("UPDATE " . TABLE_NAME . "notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;

    case 'test_notification':
        $result = notifyUser(
            $pdo,
            $user_id,
            "Notificação de Teste",
            "O sistema de notificações e lembretes está funcionando perfeitamente!",
            "schedule.php",
            null,
            "test"
        );
        echo json_encode([
            'success' => true,
            'result' => $result
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'list':
    default:
        // Contagem de não lidas
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "notifications WHERE user_id = ? AND is_read = 0");
        $stmtCount->execute([$user_id]);
        $unreadCount = (int) $stmtCount->fetchColumn();

        // Lista das últimas 15 notificações
        $stmtList = $pdo->prepare("
            SELECT id, schedule_id, title, message, type, is_read, link, created_at 
            FROM " . TABLE_NAME . "notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 15
        ");
        $stmtList->execute([$user_id]);
        $items = $stmtList->fetchAll(PDO::FETCH_ASSOC);

        // Formata data amigável
        $formatted = array_map(function ($item) {
            $ts = strtotime($item['created_at']);
            $diff = time() - $ts;

            if ($diff < 60) {
                $timeAgo = "Agora mesmo";
            } elseif ($diff < 3600) {
                $mins = floor($diff / 60);
                $timeAgo = "Há {$mins} min";
            } elseif ($diff < 86400) {
                $hours = floor($diff / 3600);
                $timeAgo = "Há {$hours} " . ($hours == 1 ? "hora" : "horas");
            } else {
                $timeAgo = date('d/m/Y H:i', $ts);
            }
            $item['time_ago'] = $timeAgo;
            return $item;
        }, $items);

        echo json_encode([
            'success' => true,
            'unread_count' => $unreadCount,
            'notifications' => $formatted
        ], JSON_UNESCAPED_UNICODE);
        exit;
}
