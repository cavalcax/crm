<?php
/**
 * Cron Job / Executor de Lembretes de Reuniões
 * Execução recomendada via Cron do servidor a cada 5, 10 ou 15 minutos:
 * Exemplo de Cron no Servidor:
 * *\/10 * * * * php /caminho/para/crm/cron-reminders.php > /dev/null 2>&1
 */

// Define timezone padrão
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/push-helper.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

$startTime = microtime(true);
$nowStr = date('Y-m-d H:i:s');

// Antecedência para o lembrete (em minutos) - padrão: 60 minutos (1 hora antes)
$reminderMinutesBefore = isset($_GET['minutes_before']) ? (int)$_GET['minutes_before'] : 60;

echo "====================================================\n";
echo " CRM Vitor Müller - Verificação de Lembretes\n";
echo " Data/Hora: {$nowStr}\n";
echo " Modo: " . ($isCli ? "CLI (Cron Server)" : "HTTP Web") . "\n";
echo " Janela de Disparo: 1 hora antes ({$reminderMinutesBefore} min)\n";
echo "====================================================\n\n";

try {
    // 1. Busca compromissos do tipo 'meeting' (Reunião) que estejam a 1 hora de acontecer (até 60 minutos à frente)
    // ou que tenham acabado de iniciar (últimos 15 min), e que ainda NÃO foram notificados (anti-duplicidade)
    $sql = "
        SELECT 
            s.id,
            s.user_id,
            s.title,
            s.type,
            s.start_time,
            s.observation,
            s.address,
            s.city,
            s.uf,
            c.name as client_name,
            c.farm_name,
            c.phone as client_phone,
            u.name as user_name
        FROM " . TABLE_NAME . "schedule s
        LEFT JOIN " . TABLE_NAME . "clients c ON s.client_id = c.id
        LEFT JOIN " . TABLE_NAME . "users u ON s.user_id = u.id
        WHERE (s.type = 'meeting' OR s.type = 'reuniao' OR s.type = 'reunião')
          AND (u.notifications_enabled IS NULL OR u.notifications_enabled = 1)
          AND s.start_time >= (NOW() - INTERVAL 15 MINUTE)
          AND s.start_time <= (NOW() + INTERVAL {$reminderMinutesBefore} MINUTE)
          AND s.id NOT IN (
              SELECT schedule_id 
              FROM " . TABLE_NAME . "notifications 
              WHERE type = 'meeting_reminder' 
                AND schedule_id IS NOT NULL
          )
        ORDER BY s.start_time ASC
    ";

    $stmt = $pdo->query($sql);
    $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalFound = count($meetings);

    echo "Reuniões encontradas para notificação (próximas de iniciar): {$totalFound}\n\n";

    $notifiedCount = 0;
    $pushCount = 0;
    $skippedCount = 0;

    foreach ($meetings as $m) {
        $eventId = (int) $m['id'];
        $userId = (int) $m['user_id'];
        $meetingTitle = trim($m['title']);
        $clientName = trim($m['client_name'] ?? '');
        $farmName = trim($m['farm_name'] ?? '');
        $city = trim($m['city'] ?? '');
        $uf = trim($m['uf'] ?? '');

        // Formatação de data/hora amigável com cálculo de tempo restante
        $dt = new DateTime($m['start_time']);
        $today = new DateTime('today');
        $tomorrow = new DateTime('tomorrow');
        $eventDate = new DateTime($dt->format('Y-m-d'));

        $diffMinutes = round(($dt->getTimestamp() - time()) / 60);

        if ($diffMinutes > 0 && $diffMinutes <= 60) {
            $timePrefix = "Hoje às " . $dt->format('H:i') . " (em ~{$diffMinutes} min)";
        } elseif ($diffMinutes <= 0 && $diffMinutes >= -15) {
            $timePrefix = "Hoje às " . $dt->format('H:i') . " (iniciando agora)";
        } elseif ($eventDate == $today) {
            $timePrefix = "Hoje às " . $dt->format('H:i');
        } elseif ($eventDate == $tomorrow) {
            $timePrefix = "Amanhã às " . $dt->format('H:i');
        } else {
            $timePrefix = $dt->format('d/m/Y \à\s H:i');
        }

        // Montagem do Título e Mensagem da Notificação
        $notifTitle = "Lembrete de Reunião: " . ($meetingTitle ?: "Reunião Agendada");

        $messageParts = [];
        $messageParts[] = "Horário: {$timePrefix}";

        if (!empty($clientName)) {
            $messageParts[] = "Cliente: {$clientName}" . (!empty($farmName) ? " ({$farmName})" : "");
        }

        $loc = array_filter([$city, $uf]);
        if (!empty($loc)) {
            $messageParts[] = "Local: " . implode('/', $loc);
        }

        $notifMessage = implode("\n", $messageParts);
        $link = "schedule.php?highlight=" . $eventId;

        // Dispara a criação e o push com controle anti-duplicidade
        $result = notifyUser($pdo, $userId, $notifTitle, $notifMessage, $link, $eventId, 'meeting_reminder');

        if ($result !== false) {
            $notifiedCount++;
            $pushCount += $result['push_sent'];
            echo "  [OK] Agendamento #{$eventId} '{$meetingTitle}' -> Notificação #{$result['notification_id']} gerada para Usuário #{$userId} ({$m['user_name']}). Push enviados: {$result['push_sent']}\n";
        } else {
            $skippedCount++;
            echo "  [IGNORADO] Agendamento #{$eventId} '{$meetingTitle}' já possuía notificação gravada.\n";
        }
    }

    $elapsed = round(microtime(true) - $startTime, 3);

    echo "\n----------------------------------------------------\n";
    echo " Resumo da Execução:\n";
    echo " - Total analisado: {$totalFound}\n";
    echo " - Novas notificações criadas: {$notifiedCount}\n";
    echo " - Disparos Web Push: {$pushCount}\n";
    echo " - Ignorados (já notificados): {$skippedCount}\n";
    echo " - Tempo de processamento: {$elapsed}s\n";
    echo "====================================================\n";

} catch (Exception $e) {
    echo "ERRO durante a execução do cron: " . $e->getMessage() . "\n";
}
