<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';
require_once '../helpers/push-helper.php';

requireLogin();
$currentUserId = $_SESSION['user_id'];
$pageTitle = 'Enviar Notificações';

$success = '';
$error = '';
$sentStats = null;

// Handle Notification Dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_broadcast'])) {
    $title = sanitize($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $link = sanitize($_POST['link'] ?? '');
    $type = sanitize($_POST['type'] ?? 'broadcast');
    $targetMode = $_POST['target_mode'] ?? 'all'; // 'all' or 'specific'
    $selectedUsers = $_POST['selected_users'] ?? [];
    $sendPush = isset($_POST['send_push']) && $_POST['send_push'] === '1';

    if (empty($title) || empty($message)) {
        $error = "O título e a mensagem da notificação são obrigatórios.";
    } else {
        // Obter destinatários aptos (com notificações habilitadas)
        if ($targetMode === 'specific') {
            if (empty($selectedUsers) || !is_array($selectedUsers)) {
                $error = "Selecione ao menos um usuário destinatário.";
            } else {
                $placeholders = implode(',', array_fill(0, count($selectedUsers), '?'));
                $userStmt = $pdo->prepare("
                    SELECT id, name, email 
                    FROM " . TABLE_NAME . "users 
                    WHERE id IN ($placeholders) 
                      AND (notifications_enabled IS NULL OR notifications_enabled = 1)
                ");
                $userStmt->execute(array_map('intval', $selectedUsers));
                $recipients = $userStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            // Todos os usuários com notificações ativas
            $userStmt = $pdo->query("
                SELECT id, name, email 
                FROM " . TABLE_NAME . "users 
                WHERE (notifications_enabled IS NULL OR notifications_enabled = 1)
                ORDER BY name ASC
            ");
            $recipients = $userStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (empty($error)) {
            if (empty($recipients)) {
                $error = "Nenhum usuário com notificações ativadas foi encontrado para este envio.";
            } else {
                $totalInternal = 0;
                $totalPush = 0;
                $totalRecipients = count($recipients);

                foreach ($recipients as $rec) {
                    $uId = (int)$rec['id'];

                    // 1. Cria notificação interna no CRM
                    $notifId = createNotification($pdo, $uId, $title, $message, $link ?: 'index.php', null, $type);
                    if ($notifId) {
                        $totalInternal++;
                    }

                    // 2. Dispara Web Push se habilitado
                    if ($sendPush && $notifId) {
                        $pushPayload = [
                            'id' => $notifId,
                            'title' => $title,
                            'body' => $message,
                            'icon' => '/assets/images/logo.png',
                            'badge' => '/assets/images/logo.png',
                            'data' => [
                                'url' => $link ?: 'admin/index.php',
                                'id' => $notifId
                            ]
                        ];
                        $pushSent = sendWebPushToUser($pdo, $uId, $pushPayload);
                        $totalPush += $pushSent;
                    }
                }

                $sentStats = [
                    'recipients' => $totalRecipients,
                    'internal' => $totalInternal,
                    'push' => $totalPush
                ];

                $success = "Notificação disparada com sucesso para {$totalRecipients} usuário(s)! ({$totalInternal} no CRM, {$totalPush} Web Pushes em dispositivos).";
            }
        }
    }
}

// Fetch stats
$totalUsersCount = (int)$pdo->query("SELECT COUNT(*) FROM " . TABLE_NAME . "users")->fetchColumn();
$enabledUsersCount = (int)$pdo->query("SELECT COUNT(*) FROM " . TABLE_NAME . "users WHERE notifications_enabled IS NULL OR notifications_enabled = 1")->fetchColumn();
$totalPushDevices = (int)$pdo->query("SELECT COUNT(*) FROM " . TABLE_NAME . "push_subscriptions")->fetchColumn();
$totalSentNotifs = (int)$pdo->query("SELECT COUNT(*) FROM " . TABLE_NAME . "notifications")->fetchColumn();

// Fetch eligible users with device count
$eligibleUsers = $pdo->query("
    SELECT 
        u.id, 
        u.name, 
        u.email, 
        u.notifications_enabled,
        (SELECT COUNT(*) FROM " . TABLE_NAME . "push_subscriptions ps WHERE ps.user_id = u.id) as device_count,
        (SELECT MAX(created_at) FROM " . TABLE_NAME . "notifications n WHERE n.user_id = u.id) as last_notif_at
    FROM " . TABLE_NAME . "users u
    WHERE (u.notifications_enabled IS NULL OR u.notifications_enabled = 1)
    ORDER BY u.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent notifications history
$recentNotifs = $pdo->query("
    SELECT 
        n.id,
        n.title,
        n.message,
        n.type,
        n.link,
        n.is_read,
        n.created_at,
        u.name as recipient_name,
        u.email as recipient_email
    FROM " . TABLE_NAME . "notifications n
    JOIN " . TABLE_NAME . "users u ON n.user_id = u.id
    ORDER BY n.created_at DESC
    LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - CRM Vitor Müller</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#FAF7F2',
                            100: '#F3E9D7',
                            200: '#E5D1AA',
                            300: '#D6B778',
                            400: '#C99E47',
                            500: '#B8860B',
                            600: '#9E7005',
                            700: '#7A5400',
                            800: '#4A340C',
                            900: '#2A1D06',
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-brand-50 font-sans leading-normal tracking-normal">
    <div class="relative min-h-screen md:flex">
        <?php include '../components/sidebar.php'; ?>

        <div class="flex-1 flex flex-col min-h-screen overflow-hidden">
            <?php include '../components/header.php'; ?>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-brand-50 p-4 md:p-6">
                <div class="max-w-7xl mx-auto space-y-6">

                    <!-- Top Breadcrumb & Actions -->
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <div class="flex items-center space-x-2 text-xs font-semibold text-brand-700 uppercase tracking-wider mb-1">
                                <a href="index.php" class="hover:underline">Dashboard</a>
                                <span>/</span>
                                <span class="text-gray-500">Comunicação</span>
                            </div>
                            <h1 class="text-2xl md:text-3xl font-extrabold text-brand-900 flex items-center gap-2.5">
                                <span class="p-2 bg-brand-500 text-white rounded-xl shadow-md">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9">
                                        </path>
                                    </svg>
                                </span>
                                Enviar Notificações
                            </h1>
                        </div>
                    </div>

                    <!-- Metrics Stats -->
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-white p-5 rounded-2xl shadow-sm border border-brand-100 flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">Usuários Totais</p>
                                <h3 class="text-2xl font-extrabold text-gray-900 mt-1"><?php echo $totalUsersCount; ?></h3>
                            </div>
                            <div class="p-3 bg-gray-100 text-gray-700 rounded-xl">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </div>
                        </div>

                        <div class="bg-white p-5 rounded-2xl shadow-sm border border-brand-100 flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-emerald-600 uppercase tracking-wider">Aptos a Receber</p>
                                <h3 class="text-2xl font-extrabold text-emerald-700 mt-1"><?php echo $enabledUsersCount; ?></h3>
                            </div>
                            <div class="p-3 bg-emerald-100 text-emerald-700 rounded-xl">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>

                        <div class="bg-white p-5 rounded-2xl shadow-sm border border-brand-100 flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-blue-600 uppercase tracking-wider">Aparelhos Conectados</p>
                                <h3 class="text-2xl font-extrabold text-blue-700 mt-1"><?php echo $totalPushDevices; ?></h3>
                            </div>
                            <div class="p-3 bg-blue-100 text-blue-700 rounded-xl">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        </div>

                        <div class="bg-white p-5 rounded-2xl shadow-sm border border-brand-100 flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-amber-600 uppercase tracking-wider">Total Disparadas</p>
                                <h3 class="text-2xl font-extrabold text-amber-700 mt-1"><?php echo $totalSentNotifs; ?></h3>
                            </div>
                            <div class="p-3 bg-amber-100 text-amber-700 rounded-xl">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Alerts -->
                    <?php if (!empty($success)): ?>
                        <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-900 p-4 rounded-xl shadow-sm flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="p-1 bg-emerald-100 text-emerald-700 rounded-full">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </span>
                                <div>
                                    <p class="font-bold text-sm"><?php echo htmlspecialchars($success); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 text-red-900 p-4 rounded-xl shadow-sm flex items-center gap-3">
                            <span class="p-1 bg-red-100 text-red-700 rounded-full">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </span>
                            <p class="font-bold text-sm"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- 1. Card: Pré-visualização em Tempo Real (Abaixo do formulário) -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-5">
                        <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                            <h3 class="font-bold text-gray-800 text-sm flex items-center gap-2">
                                <span>👁️</span> Pré-visualização em Tempo Real
                            </h3>
                            <span class="text-[10px] uppercase font-bold text-gray-400">Como o usuário verá a notificação</span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Preview 1: In-App Bell Dropdown Item -->
                            <div class="space-y-2">
                                <p class="text-xs font-bold text-gray-600">1. No Sino do CRM (Cabeçalho)</p>
                                <div class="bg-amber-50/70 border border-amber-200 rounded-xl p-4 flex items-start space-x-3 shadow-xs">
                                    <div class="w-8 h-8 rounded-full bg-brand-500 text-white flex items-center justify-center flex-shrink-0 mt-0.5 shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-1 mb-1">
                                            <h4 id="previewBellTitle" class="text-xs font-bold text-gray-900 truncate">Título da Notificação</h4>
                                            <span class="text-[10px] text-gray-400 flex-shrink-0">Agora mesmo</span>
                                        </div>
                                        <p id="previewBellMessage" class="text-[11px] text-gray-600 leading-snug break-words">
                                            Sua mensagem aparecerá aqui formatada para o usuário.
                                        </p>
                                    </div>
                                    <span class="w-2 h-2 bg-brand-600 rounded-full flex-shrink-0 mt-2"></span>
                                </div>
                            </div>

                            <!-- Preview 2: Mobile / Native Push Notification Card -->
                            <div class="space-y-2">
                                <p class="text-xs font-bold text-gray-600">2. Notificação Push Nativa (Celular/PC)</p>
                                <div class="bg-gray-900 text-white rounded-2xl p-4 shadow-xl border border-gray-800 space-y-2">
                                    <div class="flex items-center justify-between text-[11px] text-gray-400">
                                        <div class="flex items-center space-x-1.5">
                                            <span class="font-semibold text-gray-200">CRM Vitor Müller</span>
                                        </div>
                                        <span>agora</span>
                                    </div>
                                    <div>
                                        <h4 id="previewPushTitle" class="text-xs font-bold text-white mb-0.5">Título da Notificação</h4>
                                        <p id="previewPushMessage" class="text-[11px] text-gray-300 leading-relaxed line-clamp-3">
                                            Sua mensagem aparecerá aqui formatada para o usuário.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Card: Criar Nova Notificação (Largura Total) -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-brand-900 to-brand-800 text-white flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="text-xl">✍️</span>
                                <h2 class="font-bold text-base">Criar Nova Notificação</h2>
                            </div>
                            <span class="text-xs bg-brand-700/80 px-2.5 py-1 rounded-full text-brand-100 font-medium">Disparo Imediato</span>
                        </div>

                        <form method="POST" id="broadcastForm" class="p-6 space-y-5" onsubmit="return confirmNotificationSend(event)">
                            <input type="hidden" name="send_broadcast" value="1">

                            <!-- Target Audience -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">Público Destinatário</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="relative flex items-start p-3.5 border rounded-xl cursor-pointer transition select-none target-label border-brand-500 bg-brand-50/60 shadow-sm ring-2 ring-brand-500/20">
                                        <div class="flex items-center h-5">
                                            <input type="radio" name="target_mode" value="all" checked
                                                class="h-4 w-4 text-brand-600 border-gray-300 focus:ring-brand-500 cursor-pointer">
                                        </div>
                                        <div class="ml-3 text-xs">
                                            <span class="font-bold text-gray-900 block">Todos os Aptos</span>
                                            <span class="text-gray-500 text-[11px] mt-0.5 block"><?php echo $enabledUsersCount; ?> usuários com notificações ativas</span>
                                        </div>
                                    </label>

                                    <label class="relative flex items-start p-3.5 border rounded-xl cursor-pointer transition select-none target-label border-gray-200 hover:border-gray-300 bg-white">
                                        <div class="flex items-center h-5">
                                            <input type="radio" name="target_mode" value="specific"
                                                class="h-4 w-4 text-brand-600 border-gray-300 focus:ring-brand-500 cursor-pointer">
                                        </div>
                                        <div class="ml-3 text-xs">
                                            <span class="font-bold text-gray-900 block">Selecionar Usuários</span>
                                            <span class="text-gray-500 text-[11px] mt-0.5 block">Escolher destinatários da lista</span>
                                        </div>
                                    </label>
                                </div>

                                <!-- Specific Users Select Box (Hidden by default) -->
                                <div id="specificUsersContainer" class="hidden mt-3 p-4 bg-gray-50 rounded-xl border border-gray-200 space-y-2">
                                    <div class="flex items-center justify-between pb-2 border-b border-gray-200 text-xs font-semibold text-gray-600">
                                        <span>Selecione os usuários:</span>
                                        <div class="space-x-2">
                                            <button type="button" onclick="selectAllUsers(true)" class="text-brand-600 hover:underline">Todos</button>
                                            <span>•</span>
                                            <button type="button" onclick="selectAllUsers(false)" class="text-gray-500 hover:underline">Nenhum</button>
                                        </div>
                                    </div>
                                    <div class="max-h-48 overflow-y-auto space-y-1.5 pr-1 divide-y divide-gray-100">
                                        <?php foreach ($eligibleUsers as $eu): ?>
                                            <label class="flex items-center justify-between p-2 rounded-lg hover:bg-white transition cursor-pointer text-xs">
                                                <div class="flex items-center space-x-2.5">
                                                    <input type="checkbox" name="selected_users[]" value="<?php echo $eu['id']; ?>"
                                                        class="user-checkbox rounded text-brand-600 focus:ring-brand-500 border-gray-300">
                                                    <span class="font-bold text-gray-800"><?php echo htmlspecialchars($eu['name']); ?></span>
                                                    <span class="text-gray-400 text-[11px]">(<?php echo htmlspecialchars($eu['email']); ?>)</span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <?php if ($eu['device_count'] > 0): ?>
                                                        <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full text-[10px] font-semibold flex items-center gap-1" title="<?php echo $eu['device_count']; ?> dispositivo(s) push cadastrado(s)">
                                                            📱 <?php echo $eu['device_count']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Title & Link Grid -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <!-- Title -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Título da Notificação *</label>
                                    <input type="text" id="inputTitle" name="title" required placeholder="Ex: Reunião Geral de Equipe às 17h"
                                        class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-gray-800 text-sm transition">
                                </div>

                                <!-- Destination Link -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Link de Destino (Opcional)</label>
                                    <input type="text" id="inputLink" name="link" placeholder="Ex: schedule.php ou clients.php"
                                        class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-gray-800 text-sm transition">
                                    <div class="flex flex-wrap gap-1.5 mt-1.5">
                                        <button type="button" onclick="document.getElementById('inputLink').value='schedule.php'; updatePreview();" class="text-[11px] text-brand-700 hover:underline font-semibold">📅 Agenda</button>
                                        <span class="text-gray-300">•</span>
                                        <button type="button" onclick="document.getElementById('inputLink').value='clients.php'; updatePreview();" class="text-[11px] text-brand-700 hover:underline font-semibold">👥 Clientes</button>
                                        <span class="text-gray-300">•</span>
                                        <button type="button" onclick="document.getElementById('inputLink').value='index.php'; updatePreview();" class="text-[11px] text-brand-700 hover:underline font-semibold">📊 Dashboard</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Message -->
                            <div>
                                <div class="flex justify-between items-center mb-1">
                                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">Mensagem *</label>
                                    <span id="charCounter" class="text-[11px] text-gray-400 font-medium">0 / 250 caracteres</span>
                                </div>
                                <textarea id="inputMessage" name="message" rows="3" required placeholder="Escreva a mensagem que aparecerá no sino do CRM e no popup do celular/computador..."
                                    class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-gray-800 text-sm transition leading-relaxed"></textarea>
                            </div>

                            <!-- Type & Push Option -->
                            <div class="p-4 bg-brand-50/60 rounded-xl border border-brand-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                <div class="flex items-center space-x-2.5">
                                    <input type="checkbox" id="sendPushCheckbox" name="send_push" value="1" checked
                                        class="rounded text-brand-600 focus:ring-brand-500 h-4 w-4 border-gray-300">
                                    <label for="sendPushCheckbox" class="text-xs font-bold text-gray-900 cursor-pointer select-none">
                                        📱 Disparar também Web Push (Celular & Navegador)
                                    </label>
                                </div>
                                <span class="text-[11px] bg-blue-100 text-blue-800 font-semibold px-2.5 py-1 rounded-full">
                                    <?php echo $totalPushDevices; ?> dispositivo(s) conectado(s)
                                </span>
                            </div>

                            <!-- Submit Button -->
                            <div class="pt-3 border-t border-gray-100 flex items-center justify-end">
                                <button type="submit"
                                    class="w-full sm:w-auto px-8 py-3 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-bold text-sm shadow-md hover:shadow-lg transition transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2 cursor-pointer">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                    </svg>
                                    <span>Disparar Notificação Agora</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- History of Sent Notifications -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                            <h3 class="font-bold text-gray-900 text-base flex items-center gap-2">
                                <span>📜</span> Histórico Recente de Notificações
                            </h3>
                            <span class="text-xs text-gray-500">Últimos registros</span>
                        </div>

                        <?php if (!empty($recentNotifs)): ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs text-gray-600">
                                    <thead class="bg-gray-50 text-gray-700 font-bold uppercase text-[10px] tracking-wider border-b border-gray-200">
                                        <tr>
                                            <th class="py-3 px-4">Data / Hora</th>
                                            <th class="py-3 px-4">Destinatário</th>
                                            <th class="py-3 px-4">Título</th>
                                            <th class="py-3 px-4">Mensagem</th>
                                            <th class="py-3 px-4">Tipo</th>
                                            <th class="py-3 px-4">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php foreach ($recentNotifs as $rn): 
                                            $dt = new DateTime($rn['created_at']);
                                            $isRead = ((int)$rn['is_read'] === 1);
                                        ?>
                                            <tr class="hover:bg-brand-50/50 transition">
                                                <td class="py-3 px-4 whitespace-nowrap text-gray-500 font-medium">
                                                    <?php echo $dt->format('d/m/Y H:i'); ?>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap">
                                                    <span class="font-bold text-gray-800"><?php echo htmlspecialchars($rn['recipient_name']); ?></span>
                                                    <span class="block text-[10px] text-gray-400"><?php echo htmlspecialchars($rn['recipient_email']); ?></span>
                                                </td>
                                                <td class="py-3 px-4 font-bold text-gray-900">
                                                    <?php echo htmlspecialchars($rn['title']); ?>
                                                </td>
                                                <td class="py-3 px-4 max-w-xs truncate text-gray-600">
                                                    <?php echo htmlspecialchars($rn['message']); ?>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap">
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-700">
                                                        <?php echo htmlspecialchars($rn['type']); ?>
                                                    </span>
                                                </td>
                                                <td class="py-3 px-4 whitespace-nowrap">
                                                    <?php if ($isRead): ?>
                                                        <span class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full text-[10px] font-bold border border-emerald-200">
                                                            ✓ Lido
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full text-[10px] font-bold border border-amber-200">
                                                            • Não lido
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="p-8 text-center text-gray-400 text-xs">
                                Nenhuma notificação disparada recentemente.
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <script>
        const inputTitle = document.getElementById('inputTitle');
        const inputMessage = document.getElementById('inputMessage');
        const previewBellTitle = document.getElementById('previewBellTitle');
        const previewBellMessage = document.getElementById('previewBellMessage');
        const previewPushTitle = document.getElementById('previewPushTitle');
        const previewPushMessage = document.getElementById('previewPushMessage');
        const charCounter = document.getElementById('charCounter');
        const specificUsersContainer = document.getElementById('specificUsersContainer');

        function updatePreview() {
            const title = inputTitle.value.trim() || 'Título da Notificação';
            const msg = inputMessage.value.trim() || 'Sua mensagem aparecerá aqui formatada para o usuário.';

            previewBellTitle.textContent = title;
            previewBellMessage.innerHTML = msg.replace(/\n/g, '<br>');

            previewPushTitle.textContent = title;
            previewPushMessage.textContent = msg;

            const len = inputMessage.value.length;
            charCounter.textContent = `${len} / 250 caracteres`;
            if (len > 250) {
                charCounter.classList.add('text-red-500');
                charCounter.classList.remove('text-gray-400');
            } else {
                charCounter.classList.remove('text-red-500');
                charCounter.classList.add('text-gray-400');
            }
        }

        inputTitle.addEventListener('input', updatePreview);
        inputMessage.addEventListener('input', updatePreview);

        // Toggle target mode
        document.querySelectorAll('input[name="target_mode"]').forEach(radio => {
            radio.addEventListener('change', () => {
                document.querySelectorAll('.target-label').forEach(label => {
                    label.classList.remove('border-brand-500', 'bg-brand-50/60', 'ring-2', 'ring-brand-500/20');
                    label.classList.add('border-gray-200', 'bg-white');
                });
                const curLabel = radio.closest('.target-label');
                if (curLabel) {
                    curLabel.classList.add('border-brand-500', 'bg-brand-50/60', 'ring-2', 'ring-brand-500/20');
                    curLabel.classList.remove('border-gray-200', 'bg-white');
                }

                if (radio.value === 'specific') {
                    specificUsersContainer.classList.remove('hidden');
                } else {
                    specificUsersContainer.classList.add('hidden');
                }
            });
        });

        function selectAllUsers(checked) {
            document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = checked);
        }

        function confirmNotificationSend(event) {
            event.preventDefault();
            const form = document.getElementById('broadcastForm');
            const targetMode = document.querySelector('input[name="target_mode"]:checked').value;
            const checkedCount = document.querySelectorAll('.user-checkbox:checked').length;

            if (targetMode === 'specific' && checkedCount === 0) {
                Swal.fire('Atenção', 'Selecione ao menos um usuário na lista de destinatários.', 'warning');
                return false;
            }

            const title = inputTitle.value.trim();
            if (!title) {
                Swal.fire('Atenção', 'Preencha o título da notificação.', 'warning');
                return false;
            }

            const msg = inputMessage.value.trim();
            if (!msg) {
                Swal.fire('Atenção', 'Preencha a mensagem da notificação.', 'warning');
                return false;
            }

            const recipientText = (targetMode === 'all') 
                ? 'todos os <?php echo $enabledUsersCount; ?> usuários aptos' 
                : `${checkedCount} usuário(s) selecionado(s)`;

            Swal.fire({
                title: 'Confirmar Disparo?',
                text: `Deseja enviar esta notificação para ${recipientText}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#9E7005',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Sim, Disparar!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
            return false;
        }

        // Initial preview run
        updatePreview();
    </script>
</body>

</html>
