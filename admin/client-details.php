<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();
$user_id = $_SESSION['user_id'];
$isAdmin = isAdmin();
$pageTitle = 'Detalhes do Cliente';

if (!isset($_GET['id'])) {
    header("Location: clients.php");
    exit;
}

$client_id = intval($_GET['id']);

// Fetch Client Info with responsible operator
$stmt = $pdo->prepare("
    SELECT c.*, u.name as operator_name 
    FROM " . TABLE_NAME . "clients c 
    LEFT JOIN " . TABLE_NAME . "users u ON c.user_id = u.id 
    WHERE c.id = ?
");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    echo "Cliente não encontrado.";
    exit;
}

$canEdit = canEditClient($client['user_id']);

// Handle Attend Registration -> set status to 'Atendido' and redirect to WhatsApp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['attend_client']) || isset($_POST['approve_client']))) {
    if ($canEdit) {
        $stmt = $pdo->prepare("UPDATE " . TABLE_NAME . "clients SET status = 'Atendido' WHERE id = ?");
        $stmt->execute([$client_id]);

        $phoneClean = preg_replace('/[^0-9]/', '', $client['phone'] ?? '');
        if (!empty($phoneClean)) {
            $msg = buildClientApprovalWelcomeMessage($client);
            $waUrl = "https://wa.me/+55" . $phoneClean . "?text=" . rawurlencode($msg);
            header("Location: " . $waUrl);
            exit;
        }
    }
    header("Location: client-details.php?id=" . $client_id);
    exit;
}

// Handle Send to Embral -> set status to 'Embral' and redirect to client-pdf.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_embral'])) {
    if ($canEdit) {
        $stmt = $pdo->prepare("UPDATE " . TABLE_NAME . "clients SET status = 'Embral' WHERE id = ?");
        $stmt->execute([$client_id]);
        header("Location: client-pdf.php?id=" . $client_id . "&sent=embral");
        exit;
    }
    header("Location: client-details.php?id=" . $client_id);
    exit;
}

// Handle Toggle Potential Lead
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_potential'])) {
    if ($canEdit) {
        $stmt = $pdo->prepare("UPDATE " . TABLE_NAME . "clients SET is_potential = IF(is_potential=1, 0, 1) WHERE id = ?");
        $stmt->execute([$client_id]);
    }
    header("Location: client-details.php?id=" . $client_id);
    exit;
}

// Handle Add Interaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_interaction'])) {
    $interaction_date = sanitize($_POST['interaction_date'] ?? date('Y-m-d'));
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');

    if (!empty($title) && !empty($description)) {
        $stmt = $pdo->prepare("INSERT INTO " . TABLE_NAME . "interactions (client_id, user_id, interaction_date, title, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$client_id, $user_id, $interaction_date, $title, $description]);
    }
    header("Location: client-details.php?id=" . $client_id);
    exit;
}

// Handle Delete Interaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_interaction'])) {
    $interaction_id = intval($_POST['interaction_id']);
    if ($isAdmin) {
        $stmt = $pdo->prepare("DELETE FROM " . TABLE_NAME . "interactions WHERE id = ? AND client_id = ?");
        $stmt->execute([$interaction_id, $client_id]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM " . TABLE_NAME . "interactions WHERE id = ? AND client_id = ? AND user_id = ?");
        $stmt->execute([$interaction_id, $client_id, $user_id]);
    }
    header("Location: client-details.php?id=" . $client_id);
    exit;
}

// Handle Add Intention
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_intention'])) {
    $type = $_POST['type']; // 'buy' or 'sell'
    $category_id = $_POST['category_id'] ?: null;
    $description = sanitize($_POST['description']);
    $value = !empty($_POST['value']) ? floatval($_POST['value']) : null;

    $stmt = $pdo->prepare("INSERT INTO " . TABLE_NAME . "intentions (client_id, category_id, type, description, value, status) VALUES (?, ?, ?, ?, ?, 'active')");
    $stmt->execute([$client_id, $category_id, $type, $description, $value]);
    header("Location: client-details.php?id=" . $client_id . "#intentionsContainer");
    exit;
}

// Handle Inactivate Intention (with justification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inactivate_intention'])) {
    $intention_id = intval($_POST['intention_id']);
    $inactivation_reason = sanitize($_POST['inactivation_reason'] ?? '');
    $stmt = $pdo->prepare("UPDATE " . TABLE_NAME . "intentions SET status = 'inactive', inactivation_reason = ?, inactivated_at = NOW() WHERE id = ? AND client_id = ?");
    $stmt->execute([$inactivation_reason, $intention_id, $client_id]);
    header("Location: client-details.php?id=" . $client_id . "#intentionsContainer");
    exit;
}

// Handle Reactivate Intention
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivate_intention'])) {
    $intention_id = intval($_POST['intention_id']);
    $stmt = $pdo->prepare("UPDATE " . TABLE_NAME . "intentions SET status = 'active', inactivation_reason = NULL, inactivated_at = NULL WHERE id = ? AND client_id = ?");
    $stmt->execute([$intention_id, $client_id]);
    header("Location: client-details.php?id=" . $client_id . "#intentionsContainer");
    exit;
}

// Handle Delete Intention
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_intention'])) {
    $intention_id = $_POST['intention_id'];
    $stmt = $pdo->prepare("DELETE FROM " . TABLE_NAME . "intentions WHERE id = ? AND client_id = ?");
    $stmt->execute([$intention_id, $client_id]);
    header("Location: client-details.php?id=" . $client_id . "#intentionsContainer");
    exit;
}

// Fetch Interactions (Admin views all, Operator only views own interactions with client)
if ($isAdmin) {
    $stmt = $pdo->prepare("
        SELECT i.*, u.name as operator_name 
        FROM " . TABLE_NAME . "interactions i 
        LEFT JOIN " . TABLE_NAME . "users u ON i.user_id = u.id 
        WHERE i.client_id = ? 
        ORDER BY i.interaction_date DESC, i.created_at DESC
    ");
    $stmt->execute([$client_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT i.*, u.name as operator_name 
        FROM " . TABLE_NAME . "interactions i 
        LEFT JOIN " . TABLE_NAME . "users u ON i.user_id = u.id 
        WHERE i.client_id = ? AND i.user_id = ? 
        ORDER BY i.interaction_date DESC, i.created_at DESC
    ");
    $stmt->execute([$client_id, $user_id]);
}
$interactions = $stmt->fetchAll();

// Fetch Intentions
$stmt = $pdo->prepare("SELECT i.*, cat.name as category_name FROM " . TABLE_NAME . "intentions i LEFT JOIN " . TABLE_NAME . "categories cat ON i.category_id = cat.id WHERE i.client_id = ? ORDER BY i.created_at DESC");
$stmt->execute([$client_id]);
$intentions = $stmt->fetchAll();

// Fetch Categories for dropdown
$stmt = $pdo->prepare("SELECT * FROM " . TABLE_NAME . "categories ORDER BY name ASC");
$stmt->execute();
$categories = $stmt->fetchAll();

// Fetch Schedules for this client (Admin views all, Operator views own)
if ($isAdmin) {
    $stmt = $pdo->prepare("
        SELECT s.*, u.name as operator_name 
        FROM " . TABLE_NAME . "schedule s 
        LEFT JOIN " . TABLE_NAME . "users u ON s.user_id = u.id 
        WHERE s.client_id = ? 
        ORDER BY s.start_time DESC
    ");
    $stmt->execute([$client_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT s.*, u.name as operator_name 
        FROM " . TABLE_NAME . "schedule s 
        LEFT JOIN " . TABLE_NAME . "users u ON s.user_id = u.id 
        WHERE s.client_id = ? AND s.user_id = ? 
        ORDER BY s.start_time DESC
    ");
    $stmt->execute([$client_id, $user_id]);
}
$clientSchedules = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes -
        <?php echo htmlspecialchars($client['name']); ?>
    </title>
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
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-brand-50 p-6">

                <div class="mb-6">
                    <a href="clients.php"
                        class="text-brand-600 hover:text-brand-800 flex items-center mb-4 font-semibold">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Voltar para Clientes
                    </a>

                    <!-- Client Header Info -->
                    <div class="bg-white rounded-xl shadow-md p-6 relative overflow-hidden border border-gray-100 mb-6">
                        <div class="absolute top-0 left-0 w-2 h-full bg-brand-500"></div>

                        <div class="flex flex-col xl:flex-row justify-between xl:items-center gap-6 z-10">
                            <!-- Left: Client Data & Potential Star -->
                            <div class="flex items-start gap-4">
                                <!-- Potential Star Toggle -->
                                <div class="flex-shrink-0 pt-0.5">
                                    <?php if ($canEdit): ?>
                                        <form method="POST" class="inline flex items-center">
                                            <input type="hidden" name="toggle_potential" value="1">
                                            <button type="submit"
                                                class="p-1 rounded-full hover:bg-amber-50 focus:outline-none transition duration-150 transform hover:scale-110 cursor-pointer <?php echo !empty($client['is_potential']) ? 'text-amber-400 hover:text-amber-500' : 'text-gray-300 hover:text-amber-400'; ?>"
                                                title="<?php echo !empty($client['is_potential']) ? 'Remover marcação de Potencial' : 'Marcar como Cliente em Potencial'; ?>">
                                                <svg class="w-10 h-10 sm:w-11 sm:h-11"
                                                    fill="<?php echo !empty($client['is_potential']) ? 'currentColor' : 'none'; ?>"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z">
                                                    </path>
                                                </svg>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="p-1 inline-block <?php echo !empty($client['is_potential']) ? 'text-amber-400' : 'text-gray-200'; ?>"
                                            title="<?php echo !empty($client['is_potential']) ? 'Cliente em Potencial' : 'Não marcado como Potencial'; ?>">
                                            <svg class="w-10 h-10 sm:w-11 sm:h-11" fill="<?php echo !empty($client['is_potential']) ? 'currentColor' : 'none'; ?>" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                            </svg>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 leading-tight">
                                            <?php echo htmlspecialchars($client['name']); ?>
                                        </h1>

                                        <!-- Status Badge -->
                                        <?php if (($client['status'] ?? '') === 'Embral'): ?>
                                            <span
                                                class="bg-blue-100 text-blue-800 text-xs px-3 py-1 rounded-full font-bold border border-blue-300">
                                                Embral
                                            </span>
                                        <?php elseif (($client['status'] ?? '') === 'Atendido'): ?>
                                            <span
                                                class="bg-purple-100 text-purple-800 text-xs px-3 py-1 rounded-full font-bold border border-purple-300">
                                                Atendido
                                            </span>
                                        <?php elseif (($client['status'] ?? '') === 'Inativo'): ?>
                                            <span
                                                class="bg-gray-100 text-gray-700 text-xs px-3 py-1 rounded-full font-bold border border-gray-300">
                                                Inativo
                                            </span>
                                        <?php elseif (in_array($client['status'] ?? '', ['Novo', 'Pré-cadastro'])): ?>
                                            <span
                                                class="bg-amber-100 text-amber-800 text-xs px-3 py-1 rounded-full font-bold border border-amber-300">
                                                Novo
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="bg-green-100 text-green-800 text-xs px-3 py-1 rounded-full font-bold border border-green-300">
                                                Ativo
                                            </span>
                                        <?php endif; ?>

                                        <?php if ((int)$client['user_id'] === (int)$user_id): ?>
                                            <span class="bg-emerald-100 text-emerald-800 text-xs px-3 py-1 rounded-full font-bold border border-emerald-300 inline-flex items-center gap-1" title="Cliente sob sua responsabilidade">
                                                👤 Meu Cliente
                                            </span>
                                        <?php else: ?>
                                            <span class="bg-gray-100 text-gray-700 text-xs px-3 py-1 rounded-full font-bold border border-gray-300 inline-flex items-center gap-1" title="Operador Responsável por este cliente">
                                                👤 <?php echo htmlspecialchars($client['operator_name'] ?? 'Outro Usuário'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($client['farm_name'])): ?>
                                        <p class="text-sm font-semibold text-brand-800 flex items-center mt-1">
                                            <span class="mr-1.5">🏡</span> Fazenda:
                                            <?php echo htmlspecialchars($client['farm_name']); ?>
                                        </p>
                                    <?php endif; ?>

                                    <div
                                        class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-gray-600 mt-2">
                                        <?php if (!empty($client['phone'])): ?>
                                            <a href="https://wa.me/+55<?php echo preg_replace('/[^0-9]/', '', $client['phone']); ?>"
                                                target="_blank"
                                                class="flex items-center text-green-600 hover:text-green-800 font-medium hover:underline"
                                                title="Abrir no WhatsApp">
                                                <svg class="w-4 h-4 mr-1 text-green-500 fill-current" viewBox="0 0 24 24">
                                                    <path
                                                        d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                                </svg>
                                                <?php echo htmlspecialchars(formatPhone($client['phone'])); ?>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($client['email'])): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>"
                                                class="flex items-center text-blue-600 hover:text-blue-800 font-medium hover:underline"
                                                title="Enviar e-mail para <?php echo htmlspecialchars($client['email']); ?>">
                                                <svg class="w-4 h-4 mr-1 text-blue-500 flex-shrink-0" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                                    </path>
                                                </svg>
                                                <span><?php echo htmlspecialchars($client['email']); ?></span>
                                            </a>
                                        <?php endif; ?>

                                        <?php
                                        $loc = array_filter([$client['city'] ?? '', $client['uf'] ?? '']);
                                        $locStr = !empty($loc) ? implode(' - ', $loc) : 'Sem Cidade/UF';
                                        $hasCoords = !empty($client['latitude']) && !empty($client['longitude']);
                                        ?>
                                        <?php if ($hasCoords): ?>
                                            <a href="view-map.php?client_id=<?php echo $client['id']; ?>"
                                                class="flex items-center text-emerald-700 hover:text-emerald-900 font-medium hover:underline group"
                                                title="Ver no Mapa de Clientes">
                                                <svg class="w-4 h-4 mr-1 text-emerald-500 group-hover:text-emerald-700 flex-shrink-0 transition"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                                                </svg>
                                                <span><?php echo htmlspecialchars($locStr); ?></span>
                                            </a>
                                        <?php else: ?>
                                            <span class="flex items-center text-gray-600">
                                                <svg class="w-4 h-4 mr-1 text-gray-400 flex-shrink-0" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                                    </path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                </svg>
                                                <span
                                                    class="font-medium text-brand-700"><?php echo htmlspecialchars($locStr); ?></span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Action Buttons -->
                            <div class="flex flex-wrap items-center gap-2 xl:justify-end flex-shrink-0">
                                <!-- Atendido (quando status for Novo / Pré-cadastro e usuário puder editar) -->
                                <?php if ($canEdit && in_array($client['status'] ?? '', ['Novo', 'Pré-cadastro'])): ?>
                                    <?php
                                    $detailPhoneClean = preg_replace('/[^0-9]/', '', $client['phone'] ?? '');
                                    $detailApprovalMsg = buildClientApprovalWelcomeMessage($client);
                                    $detailWaApprovalUrl = !empty($detailPhoneClean) ? "https://wa.me/+55" . $detailPhoneClean . "?text=" . rawurlencode($detailApprovalMsg) : '';
                                    ?>
                                    <form method="POST" class="inline"
                                        onsubmit="if('<?php echo addslashes($detailWaApprovalUrl); ?>'){ window.open('<?php echo addslashes($detailWaApprovalUrl); ?>', '_blank'); }">
                                        <input type="hidden" name="attend_client" value="1">
                                        <button type="submit"
                                            class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-3.5 rounded-lg shadow-sm hover:shadow transition flex items-center text-xs md:text-sm hover:-translate-y-0.5 cursor-pointer"
                                            title="Marcar como Atendido (Alterar para Atendido e abrir WhatsApp)">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Atendido
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Enviar para Embral (quando status for Atendido e usuário puder editar) -->
                                <?php if ($canEdit && ($client['status'] ?? '') === 'Atendido'): ?>
                                    <form method="POST" class="inline"
                                        onsubmit="window.open('client-pdf.php?id=<?php echo $client['id']; ?>&sent=embral', '_blank');">
                                        <input type="hidden" name="send_embral" value="1">
                                        <button type="submit"
                                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-3.5 rounded-lg shadow-sm hover:shadow transition flex items-center text-xs md:text-sm hover:-translate-y-0.5 cursor-pointer"
                                            title="Enviar dados para Embral (Alterar status para Embral e abrir Ficha/WhatsApp)">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                            </svg>
                                            Enviar para Embral
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <!-- Agendar Compromisso -->
                                <a href="schedule-add.php?client_id=<?php echo $client['id']; ?>"
                                    class="bg-amber-600 hover:bg-amber-700 text-white font-bold py-2 px-3.5 rounded-lg shadow-sm hover:shadow transition flex items-center text-xs md:text-sm hover:-translate-y-0.5"
                                    title="Agendar Compromisso">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                        </path>
                                    </svg>
                                    Agendar
                                </a>

                                <!-- WhatsApp -->
                                <?php if (!empty($client['phone'])): ?>
                                    <a href="https://wa.me/+55<?php echo preg_replace('/[^0-9]/', '', $client['phone']); ?>"
                                        target="_blank"
                                        class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-3.5 rounded-lg shadow-sm hover:shadow transition flex items-center text-xs md:text-sm hover:-translate-y-0.5"
                                        title="Abrir conversa no WhatsApp">
                                        <svg class="w-4 h-4 mr-1.5 fill-current" viewBox="0 0 24 24">
                                            <path
                                                d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                        </svg>
                                        WhatsApp
                                    </a>
                                <?php endif; ?>

                                <!-- E-mail -->
                                <?php if (!empty($client['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>"
                                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-3.5 rounded-lg shadow-sm hover:shadow transition flex items-center text-xs md:text-sm hover:-translate-y-0.5"
                                        title="Enviar E-mail">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                            </path>
                                        </svg>
                                        E-mail
                                    </a>
                                <?php endif; ?>

                                <!-- PDF -->
                                <a href="client-pdf.php?id=<?php echo $client['id']; ?>" target="_blank"
                                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-3.5 rounded-lg shadow-sm hover:shadow transition flex items-center text-xs md:text-sm hover:-translate-y-0.5"
                                    title="Gerar PDF / Imprimir Ficha">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                                        </path>
                                    </svg>
                                    PDF
                                </a>

                                <?php if ($canEdit): ?>
                                    <!-- Editar (Somente Dono ou Admin) -->
                                    <a href="client-edit.php?id=<?php echo $client['id']; ?>"
                                        class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-2 px-3.5 rounded-lg shadow-sm hover:shadow transition flex items-center text-xs md:text-sm hover:-translate-y-0.5"
                                        title="Editar Dados do Cliente">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                            </path>
                                        </svg>
                                        Editar
                                    </a>
                                <?php endif; ?>

                                <!-- Mapa -->
                                <a href="view-map.php?client_id=<?php echo $client['id']; ?>"
                                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-3.5 rounded-lg shadow-sm hover:shadow transition flex items-center text-xs md:text-sm hover:-translate-y-0.5"
                                    title="Ver no Mapa de Clientes">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7">
                                        </path>
                                    </svg>
                                    Mapa
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Agenda / Compromissos vinculados -->
                <div class="bg-white rounded-xl shadow-md p-6 mb-6 border-l-4 border-amber-600">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-brand-900 flex items-center">
                            <span class="p-2 bg-amber-100 rounded-full mr-2 text-amber-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                    </path>
                                </svg>
                            </span>
                            Agendas (<?php echo count($clientSchedules); ?>)
                        </h3>
                        <a href="schedule-add.php?client_id=<?php echo $client['id']; ?>"
                            class="bg-amber-600 hover:bg-amber-700 text-white font-bold py-1.5 px-3.5 rounded-lg shadow-sm hover:shadow transition flex items-center text-xs hover:-translate-y-0.5">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4"></path>
                            </svg>
                            Agendar Novo
                        </a>
                    </div>

                    <?php if (count($clientSchedules) > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($clientSchedules as $cs):
                                $csDate = new DateTime($cs['start_time']);
                                $csTypeLabels = ['meeting' => 'Reunião', 'visit' => 'Visita', 'auction' => 'Leilão', 'other' => 'Outro'];
                                $csTypeClass = match ($cs['type']) {
                                    'meeting' => 'bg-blue-100 text-blue-800 border-blue-300',
                                    'visit' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                                    'auction' => 'bg-amber-100 text-amber-800 border-amber-300',
                                    default => 'bg-purple-100 text-purple-800 border-purple-300'
                                };
                                ?>
                                <div class="bg-brand-50 p-4 rounded-xl border border-brand-100 hover:shadow-md transition">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="text-xs font-bold px-2 py-0.5 rounded-full border <?php echo $csTypeClass; ?>">
                                                <?php echo $csTypeLabels[$cs['type']] ?? 'Outro'; ?>
                                            </span>
                                            <?php if ($isAdmin && !empty($cs['operator_name'])): ?>
                                                <span class="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-medium border border-slate-200">
                                                    👤 <?php echo htmlspecialchars($cs['operator_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <a href="schedule-edit.php?id=<?php echo $cs['id']; ?>"
                                            class="text-xs font-semibold text-brand-700 hover:text-brand-900 hover:underline">
                                            Editar
                                        </a>
                                    </div>
                                    <h4 class="font-bold text-gray-900 text-sm mb-1">
                                        <?php echo htmlspecialchars($cs['title']); ?>
                                    </h4>
                                    <p class="text-xs text-gray-600 flex items-center mb-1">
                                        <svg class="w-3.5 h-3.5 mr-1 text-brand-600 flex-shrink-0" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <?php echo $csDate->format('d/m/Y \à\s H:i'); ?>
                                    </p>
                                    <?php if (!empty($cs['city']) || !empty($cs['uf'])): ?>
                                        <p class="text-xs text-gray-500 flex items-center mb-1">
                                            <svg class="w-3.5 h-3.5 mr-1 text-gray-400 flex-shrink-0" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                                </path>
                                            </svg>
                                            <?php echo htmlspecialchars(implode(' - ', array_filter([$cs['city'], $cs['uf']]))); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($cs['banner_image'])): ?>
                                        <div class="mt-2 mb-2 rounded-lg overflow-hidden border border-amber-200 shadow-sm max-h-36">
                                            <a href="../<?php echo htmlspecialchars($cs['banner_image']); ?>" target="_blank">
                                                <img src="../<?php echo htmlspecialchars($cs['banner_image']); ?>" alt="Banner do Leilão" class="w-full h-auto object-cover max-h-36 hover:scale-102 transition duration-150">
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($cs['type'] === 'auction' && (!empty($cs['auction_lots_link']) || !empty($cs['auction_live_link']))): ?>
                                        <div class="flex flex-wrap items-center gap-1.5 mt-2 pt-2 border-t border-amber-100">
                                            <?php if (!empty($cs['auction_lots_link'])): ?>
                                                <a href="<?php echo htmlspecialchars($cs['auction_lots_link']); ?>" target="_blank"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-md text-[11px] shadow-sm transition"
                                                    title="Ver Vídeo dos Lotes">
                                                    📹 Vídeo Lotes
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($cs['auction_live_link'])): ?>
                                                <a href="<?php echo htmlspecialchars($cs['auction_live_link']); ?>" target="_blank"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-600 hover:bg-red-700 text-white font-bold rounded-md text-[11px] shadow-sm transition"
                                                    title="Assistir Transmissão Ao Vivo">
                                                    🔴 Ao Vivo
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($cs['observation'])): ?>
                                        <p class="text-xs text-gray-600 italic mt-2 line-clamp-2">
                                            "<?php echo htmlspecialchars($cs['observation']); ?>"</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-gray-500 italic bg-brand-50 p-4 rounded-lg border border-brand-100">
                            Nenhum compromisso agendado com este cliente ainda.
                            <a href="schedule-add.php?client_id=<?php echo $client['id']; ?>"
                                class="text-brand-700 font-bold hover:underline ml-1">Clique aqui para agendar</a>.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Perfil Comercial -->
                <div class="bg-white rounded-lg shadow-lg p-6 mb-6 border-l-4 border-amber-500">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-brand-900 flex items-center">
                            <span class="p-2 bg-amber-100 rounded-full mr-2 text-amber-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                    </path>
                                </svg>
                            </span>
                            Perfil Comercial
                        </h3>
                        <?php if ($canEdit): ?>
                            <a href="client-edit.php?id=<?php echo $client['id']; ?>"
                                class="text-xs font-bold text-brand-600 hover:text-brand-800 underline">Editar Dados</a>
                        <?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="bg-brand-50 p-4 rounded-lg border border-brand-100">
                            <p class="text-xs font-bold uppercase text-brand-700 tracking-wider">Condição de Pagamento
                            </p>
                            <p class="text-base font-semibold text-gray-800 mt-1">
                                <?php echo htmlspecialchars($client['payment_condition'] ?: '-'); ?>
                            </p>
                        </div>

                        <div class="bg-brand-50 p-4 rounded-lg border border-brand-100">
                            <p class="text-xs font-bold uppercase text-brand-700 tracking-wider">Interesse em Adquirir
                            </p>
                            <p class="text-base font-semibold text-gray-800 mt-1">
                                <?php echo htmlspecialchars($client['breed_interests'] ?: '-'); ?>
                            </p>
                        </div>

                        <div class="bg-brand-50 p-4 rounded-lg border border-brand-100">
                            <p class="text-xs font-bold uppercase text-brand-700 tracking-wider">Motivo da Aquisição</p>
                            <p class="text-base font-semibold text-gray-800 mt-1">
                                <?php echo htmlspecialchars($client['acquisition_reason'] ?: '-'); ?>
                            </p>
                        </div>

                        <div class="bg-brand-50 p-4 rounded-lg border border-brand-100">
                            <p class="text-xs font-bold uppercase text-brand-700 tracking-wider">Qtd. Animais
                                Necessários</p>
                            <p class="text-base font-semibold text-gray-800 mt-1">
                                <?php echo htmlspecialchars($client['purchase_animal_count'] ?? '-') ?: '-'; ?>
                            </p>
                        </div>

                        <div class="bg-brand-50 p-4 rounded-lg border border-brand-100 lg:col-span-2">
                            <p class="text-xs font-bold uppercase text-brand-700 tracking-wider">Categorias de Animais
                                Desejadas</p>
                            <p class="text-base font-semibold text-gray-800 mt-1">
                                <?php echo htmlspecialchars($client['animal_categories'] ?? '-') ?: '-'; ?>
                            </p>
                        </div>

                        <div class="bg-brand-50 p-4 rounded-lg border border-brand-100">
                            <p class="text-xs font-bold uppercase text-brand-700 tracking-wider">Sistema de Produção</p>
                            <p class="text-base font-semibold text-gray-800 mt-1">
                                <?php echo htmlspecialchars($client['production_system'] ?? '-') ?: '-'; ?>
                            </p>
                        </div>

                        <div class="bg-brand-50 p-4 rounded-lg border border-brand-100">
                            <p class="text-xs font-bold uppercase text-brand-700 tracking-wider">Produtor de Leite</p>
                            <p class="text-base font-semibold text-gray-800 mt-1">
                                <?php echo htmlspecialchars($client['is_milk_producer'] ?: '-'); ?>
                            </p>
                        </div>

                        <div class="bg-brand-50 p-4 rounded-lg border border-brand-100">
                            <p class="text-xs font-bold uppercase text-brand-700 tracking-wider">Quantidade de Animais
                                Possuídos</p>
                            <p class="text-base font-semibold text-gray-800 mt-1">
                                <?php echo htmlspecialchars($client['animal_count_range'] ?: '-'); ?>
                            </p>
                        </div>

                        <div class="bg-brand-50 p-4 rounded-lg border border-brand-100">
                            <p class="text-xs font-bold uppercase text-brand-700 tracking-wider">Produção Mensal de Leite</p>
                            <p class="text-base font-semibold text-gray-800 mt-1">
                                <?php echo htmlspecialchars($client['milk_production_range'] ?: '-'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 3 Cards: Interações, Compra, Venda -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Client Interactions -->
                    <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-emerald-600 flex flex-col">
                        <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                            <span class="p-2 bg-emerald-100 rounded-full mr-2">
                                <svg class="w-5 h-5 text-emerald-700" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                            </span>
                            Interações
                        </h3>

                        <!-- Add Form (Top) -->
                        <form method="POST" class="mb-5 pb-5 border-b border-gray-100">
                            <input type="hidden" name="add_interaction" value="1">
                            <div class="mb-2">
                                <label class="block text-[11px] font-bold text-gray-600 mb-1">Data da Interação</label>
                                <input type="date" name="interaction_date" value="<?php echo date('Y-m-d'); ?>"
                                    class="w-full p-2 border rounded text-xs text-gray-700 bg-white" required>
                            </div>
                            <input type="text" name="title" placeholder="Título (ex: Alinhamento WhatsApp, Visita...)"
                                class="w-full mb-2 p-2 border rounded text-xs" required>
                            <textarea name="description" placeholder="Descrição da conversa ou ação..."
                                class="w-full mb-2 p-2 border rounded text-xs" rows="2" required></textarea>
                            <button type="submit"
                                class="w-full bg-emerald-600 text-white font-bold py-2 rounded text-xs hover:bg-emerald-700 transition cursor-pointer">
                                Adicionar Interação
                            </button>
                        </form>

                        <!-- List (Bottom) -->
                        <div class="flex-1">
                            <h4 class="text-xs font-bold uppercase text-gray-400 tracking-wider mb-3">Histórico de
                                Interações</h4>
                            <ul class="space-y-3 max-h-80 overflow-y-auto pr-1">
                                <?php if (!empty($interactions)): ?>
                                    <?php foreach ($interactions as $inter):
                                        $interDate = new DateTime($inter['interaction_date']);
                                        ?>
                                        <li class="bg-emerald-50 rounded-lg p-3 relative group border border-emerald-100">
                                            <div class="flex items-center justify-between pr-6 mb-1">
                                                <p class="font-bold text-emerald-950 text-sm">
                                                    <?php echo htmlspecialchars($inter['title']); ?>
                                                </p>
                                                <span
                                                    class="text-[11px] text-emerald-700 font-semibold bg-emerald-100/70 px-2 py-0.5 rounded-full">
                                                    <?php echo $interDate->format('d/m/Y'); ?>
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-700 leading-relaxed whitespace-pre-line">
                                                <?php echo htmlspecialchars($inter['description']); ?>
                                            </p>

                                            <form method="POST" class="absolute top-2 right-2 transition"
                                                onsubmit="confirmDelete(event)">
                                                <input type="hidden" name="interaction_id" value="<?php echo $inter['id']; ?>">
                                                <input type="hidden" name="delete_interaction" value="1">
                                                <button type="submit"
                                                    class="text-red-400 hover:text-red-700 p-0.5 cursor-pointer"
                                                    title="Excluir interação">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                        </path>
                                                    </svg>
                                                </button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="text-xs text-gray-400 italic py-2 bg-gray-50 p-3 rounded">Nenhuma interação
                                        registrada ainda.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Buy Intentions -->
                    <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-blue-500 flex flex-col">
                        <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center justify-between">
                            <span class="flex items-center">
                                <span class="p-2 bg-blue-100 rounded-full mr-2">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </span>
                                Intenção de COMPRA
                            </span>
                            <?php
                            $activeBuys = array_filter($intentions, fn($i) => $i['type'] === 'buy' && (empty($i['status']) || $i['status'] === 'active'));
                            $inactiveBuys = array_filter($intentions, fn($i) => $i['type'] === 'buy' && $i['status'] === 'inactive');
                            ?>
                            <span class="text-xs bg-blue-100 text-blue-800 font-bold px-2 py-0.5 rounded-full">
                                <?php echo count($activeBuys); ?> ativa(s)
                            </span>
                        </h3>

                        <!-- Add Form (Top) -->
                        <form method="POST" class="mb-5 pb-5 border-b border-gray-100">
                            <input type="hidden" name="type" value="buy">
                            <select name="category_id" class="w-full mb-2 p-2 border rounded text-xs bg-white">
                                <option value="">Selecione Categoria...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <textarea name="description" placeholder="Descrição (ex: procura 10 vacas holandesas...)"
                                class="w-full mb-2 p-2 border rounded text-xs" rows="2" required></textarea>
                            <input type="number" name="value" placeholder="Valor Estimado (opcional)"
                                class="w-full mb-2 p-2 border rounded text-xs" step="0.01">
                            <button type="submit" name="add_intention"
                                class="w-full bg-blue-600 text-white font-bold py-2 rounded text-xs hover:bg-blue-700 transition cursor-pointer">
                                Adicionar Compra
                            </button>
                        </form>

                        <!-- List (Bottom) -->
                        <div class="flex-1">
                            <h4 class="text-xs font-bold uppercase text-gray-400 tracking-wider mb-3">
                                Compras Cadastradas (<?php echo count($activeBuys); ?>
                                ativas<?php echo !empty($inactiveBuys) ? ', ' . count($inactiveBuys) . ' inativas' : ''; ?>)
                            </h4>
                            <ul class="space-y-3 max-h-80 overflow-y-auto pr-1">
                                <?php
                                $allBuys = array_merge($activeBuys, $inactiveBuys);
                                ?>
                                <?php if (!empty($allBuys)): ?>
                                    <?php foreach ($allBuys as $intention):
                                        $isActive = empty($intention['status']) || $intention['status'] === 'active';
                                        ?>
                                        <?php if ($isActive): ?>
                                            <!-- Compra Ativa -->
                                            <li class="bg-blue-50/70 rounded-lg p-3 relative border border-blue-200">
                                                <div class="flex items-center justify-between pr-14">
                                                    <span class="font-bold text-blue-900 text-xs truncate">
                                                        <?php echo htmlspecialchars($intention['category_name'] ?? 'Geral'); ?>
                                                    </span>
                                                    <span
                                                        class="bg-emerald-100 text-emerald-800 text-[10px] font-bold px-1.5 py-0.5 rounded">Ativa</span>
                                                </div>
                                                <p class="text-xs text-gray-700 mt-1 leading-snug">
                                                    <?php echo htmlspecialchars($intention['description']); ?>
                                                </p>
                                                <?php if ($intention['value'] > 0): ?>
                                                    <p class="text-xs font-semibold text-green-700 mt-1">
                                                        R$ <?php echo number_format($intention['value'], 2, ',', '.'); ?>
                                                    </p>
                                                <?php endif; ?>

                                                <div class="absolute top-2.5 right-2.5 flex items-center space-x-1.5">
                                                    <!-- Inactivate Button -->
                                                    <button type="button"
                                                        onclick="inactivateIntention(<?php echo $intention['id']; ?>, '<?php echo addslashes($intention['category_name'] ?? 'Compra'); ?>')"
                                                        class="text-amber-600 hover:text-amber-800 p-1 hover:bg-amber-100 rounded transition cursor-pointer"
                                                        title="Inativar interesse informando justificativa">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z">
                                                            </path>
                                                        </svg>
                                                    </button>
                                                    <!-- Delete Button -->
                                                    <form method="POST" onsubmit="confirmDelete(event)">
                                                        <input type="hidden" name="intention_id"
                                                            value="<?php echo $intention['id']; ?>">
                                                        <input type="hidden" name="delete_intention" value="1">
                                                        <button type="submit"
                                                            class="text-red-400 hover:text-red-700 p-1 hover:bg-red-50 rounded transition cursor-pointer"
                                                            title="Excluir">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                                </path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </li>
                                        <?php else: ?>
                                            <!-- Compra Inativa (com justificativa) -->
                                            <li class="bg-gray-100 rounded-lg p-3 relative border border-gray-300">
                                                <div class="flex items-center justify-between pr-14">
                                                    <span class="font-bold text-gray-700 text-xs truncate">
                                                        <?php echo htmlspecialchars($intention['category_name'] ?? 'Geral'); ?>
                                                    </span>
                                                    <span
                                                        class="bg-gray-200 text-gray-700 text-[10px] font-bold px-1.5 py-0.5 rounded">Inativa</span>
                                                </div>

                                                <!-- Linha 1: Descrição da Intenção -->
                                                <div class="text-xs text-gray-700 mt-1 leading-snug">
                                                    <span class="font-bold text-gray-500 text-[11px]">Descrição:</span>
                                                    <?php echo htmlspecialchars($intention['description']); ?>
                                                    <?php if ($intention['value'] > 0): ?>
                                                        <span class="text-green-700 font-semibold text-[11px] ml-1">(R$
                                                            <?php echo number_format($intention['value'], 2, ',', '.'); ?>)</span>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Linha 2: Justificativa -->
                                                <div
                                                    class="text-xs text-amber-950 bg-amber-50 border border-amber-200 rounded p-2 mt-2 leading-relaxed">
                                                    <span class="font-bold text-amber-800">Justificativa:</span>
                                                    <?php echo htmlspecialchars($intention['inactivation_reason'] ?: 'Não informada'); ?>
                                                    <?php if (!empty($intention['inactivated_at'])): ?>
                                                        <span class="block text-[10px] text-gray-500 mt-0.5">Inativado em:
                                                            <?php echo date('d/m/Y H:i', strtotime($intention['inactivated_at'])); ?></span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="absolute top-2.5 right-2.5 flex items-center space-x-1.5">
                                                    <!-- Reactivate Button -->
                                                    <button type="button"
                                                        onclick="reactivateIntention(<?php echo $intention['id']; ?>)"
                                                        class="text-emerald-600 hover:text-emerald-800 p-1 hover:bg-emerald-50 rounded transition cursor-pointer"
                                                        title="Reativar interesse">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                                            </path>
                                                        </svg>
                                                    </button>
                                                    <!-- Delete Button -->
                                                    <form method="POST" onsubmit="confirmDelete(event)">
                                                        <input type="hidden" name="intention_id"
                                                            value="<?php echo $intention['id']; ?>">
                                                        <input type="hidden" name="delete_intention" value="1">
                                                        <button type="submit"
                                                            class="text-red-400 hover:text-red-700 p-1 hover:bg-red-50 rounded transition cursor-pointer"
                                                            title="Excluir">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                                </path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="text-xs text-gray-400 italic py-2 bg-gray-50 p-3 rounded text-center">Nenhuma
                                        intenção de compra registrada.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Sell Intentions -->
                    <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-red-500 flex flex-col">
                        <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center justify-between">
                            <span class="flex items-center">
                                <span class="p-2 bg-red-100 rounded-full mr-2">
                                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M7 11l5-5m0 0l5 5m-5-5v12" />
                                    </svg>
                                </span>
                                Intenção de VENDA
                            </span>
                            <?php
                            $activeSells = array_filter($intentions, fn($i) => $i['type'] === 'sell' && (empty($i['status']) || $i['status'] === 'active'));
                            $inactiveSells = array_filter($intentions, fn($i) => $i['type'] === 'sell' && $i['status'] === 'inactive');
                            ?>
                            <span class="text-xs bg-red-100 text-red-800 font-bold px-2 py-0.5 rounded-full">
                                <?php echo count($activeSells); ?> ativa(s)
                            </span>
                        </h3>

                        <!-- Add Form (Top) -->
                        <form method="POST" class="mb-5 pb-5 border-b border-gray-100">
                            <input type="hidden" name="type" value="sell">
                            <select name="category_id" class="w-full mb-2 p-2 border rounded text-xs bg-white">
                                <option value="">Selecione Categoria...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <textarea name="description" placeholder="Descrição (ex: vende 15 bezerras...)"
                                class="w-full mb-2 p-2 border rounded text-xs" rows="2" required></textarea>
                            <input type="number" name="value" placeholder="Valor Estimado (opcional)"
                                class="w-full mb-2 p-2 border rounded text-xs" step="0.01">
                            <button type="submit" name="add_intention"
                                class="w-full bg-red-600 text-white font-bold py-2 rounded text-xs hover:bg-red-700 transition cursor-pointer">
                                Adicionar Venda
                            </button>
                        </form>

                        <!-- List (Bottom) -->
                        <div class="flex-1">
                            <h4 class="text-xs font-bold uppercase text-gray-400 tracking-wider mb-3">
                                Vendas Cadastradas (<?php echo count($activeSells); ?>
                                ativas<?php echo !empty($inactiveSells) ? ', ' . count($inactiveSells) . ' inativas' : ''; ?>)
                            </h4>
                            <ul class="space-y-3 max-h-80 overflow-y-auto pr-1">
                                <?php
                                $allSells = array_merge($activeSells, $inactiveSells);
                                ?>
                                <?php if (!empty($allSells)): ?>
                                    <?php foreach ($allSells as $intention):
                                        $isActive = empty($intention['status']) || $intention['status'] === 'active';
                                        ?>
                                        <?php if ($isActive): ?>
                                            <!-- Venda Ativa -->
                                            <li class="bg-red-50/70 rounded-lg p-3 relative border border-red-200">
                                                <div class="flex items-center justify-between pr-14">
                                                    <span class="font-bold text-red-900 text-xs truncate">
                                                        <?php echo htmlspecialchars($intention['category_name'] ?? 'Geral'); ?>
                                                    </span>
                                                    <span
                                                        class="bg-emerald-100 text-emerald-800 text-[10px] font-bold px-1.5 py-0.5 rounded">Ativa</span>
                                                </div>
                                                <p class="text-xs text-gray-700 mt-1 leading-snug">
                                                    <?php echo htmlspecialchars($intention['description']); ?>
                                                </p>
                                                <?php if ($intention['value'] > 0): ?>
                                                    <p class="text-xs font-semibold text-green-700 mt-1">
                                                        R$ <?php echo number_format($intention['value'], 2, ',', '.'); ?>
                                                    </p>
                                                <?php endif; ?>

                                                <div class="absolute top-2.5 right-2.5 flex items-center space-x-1.5">
                                                    <!-- Inactivate Button -->
                                                    <button type="button"
                                                        onclick="inactivateIntention(<?php echo $intention['id']; ?>, '<?php echo addslashes($intention['category_name'] ?? 'Venda'); ?>')"
                                                        class="text-amber-600 hover:text-amber-800 p-1 hover:bg-amber-100 rounded transition cursor-pointer"
                                                        title="Inativar interesse informando justificativa">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z">
                                                            </path>
                                                        </svg>
                                                    </button>
                                                    <!-- Delete Button -->
                                                    <form method="POST" onsubmit="confirmDelete(event)">
                                                        <input type="hidden" name="intention_id"
                                                            value="<?php echo $intention['id']; ?>">
                                                        <input type="hidden" name="delete_intention" value="1">
                                                        <button type="submit"
                                                            class="text-red-400 hover:text-red-700 p-1 hover:bg-red-50 rounded transition cursor-pointer"
                                                            title="Excluir">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                                </path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </li>
                                        <?php else: ?>
                                            <!-- Venda Inativa (com justificativa) -->
                                            <li class="bg-gray-100 rounded-lg p-3 relative border border-gray-300">
                                                <div class="flex items-center justify-between pr-14">
                                                    <span class="font-bold text-gray-700 text-xs truncate">
                                                        <?php echo htmlspecialchars($intention['category_name'] ?? 'Geral'); ?>
                                                    </span>
                                                    <span
                                                        class="bg-gray-200 text-gray-700 text-[10px] font-bold px-1.5 py-0.5 rounded">Inativa</span>
                                                </div>

                                                <!-- Linha 1: Descrição da Intenção -->
                                                <div class="text-xs text-gray-700 mt-1 leading-snug">
                                                    <span class="font-bold text-gray-500 text-[11px]">Descrição:</span>
                                                    <?php echo htmlspecialchars($intention['description']); ?>
                                                    <?php if ($intention['value'] > 0): ?>
                                                        <span class="text-green-700 font-semibold text-[11px] ml-1">(R$
                                                            <?php echo number_format($intention['value'], 2, ',', '.'); ?>)</span>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Linha 2: Justificativa -->
                                                <div
                                                    class="text-xs text-amber-950 bg-amber-50 border border-amber-200 rounded p-2 mt-2 leading-relaxed">
                                                    <span class="font-bold text-amber-800">Justificativa:</span>
                                                    <?php echo htmlspecialchars($intention['inactivation_reason'] ?: 'Não informada'); ?>
                                                    <?php if (!empty($intention['inactivated_at'])): ?>
                                                        <span class="block text-[10px] text-gray-500 mt-0.5">Inativado em:
                                                            <?php echo date('d/m/Y H:i', strtotime($intention['inactivated_at'])); ?></span>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="absolute top-2.5 right-2.5 flex items-center space-x-1.5">
                                                    <!-- Reactivate Button -->
                                                    <button type="button"
                                                        onclick="reactivateIntention(<?php echo $intention['id']; ?>)"
                                                        class="text-emerald-600 hover:text-emerald-800 p-1 hover:bg-emerald-50 rounded transition cursor-pointer"
                                                        title="Reativar interesse">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                                            </path>
                                                        </svg>
                                                    </button>
                                                    <!-- Delete Button -->
                                                    <form method="POST" onsubmit="confirmDelete(event)">
                                                        <input type="hidden" name="intention_id"
                                                            value="<?php echo $intention['id']; ?>">
                                                        <input type="hidden" name="delete_intention" value="1">
                                                        <button type="submit"
                                                            class="text-red-400 hover:text-red-700 p-1 hover:bg-red-50 rounded transition cursor-pointer"
                                                            title="Excluir">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                                </path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="text-xs text-gray-400 italic py-2 bg-gray-50 p-3 rounded text-center">Nenhuma
                                        intenção de venda registrada.</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function inactivateIntention(id, label) {
            Swal.fire({
                title: 'Inativar Interesse?',
                html: `
                    <div class="text-left space-y-2">
                        <p class="text-xs text-gray-600">Informe a <strong>justificativa</strong> para registrar o motivo de não haver mais interesse no momento (ex: comprou de outro fornecedor, desistiu temporariamente, etc.):</p>
                        <textarea id="swal-reason-input" class="w-full p-2.5 border border-gray-300 rounded-lg text-xs focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none" rows="3" placeholder="Ex: O cliente comprou 10 vacas em outro leilão na semana passada..."></textarea>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d97706',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Confirmar Inativação',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const reason = document.getElementById('swal-reason-input').value.trim();
                    if (!reason) {
                        Swal.showValidationMessage('Por favor, informe a justificativa antes de confirmar.');
                        return false;
                    }
                    return reason;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="intention_id" value="${id}">
                        <input type="hidden" name="inactivation_reason" value="${escapeHtml(result.value)}">
                        <input type="hidden" name="inactivate_intention" value="1">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function reactivateIntention(id) {
            Swal.fire({
                title: 'Reativar Intenção?',
                text: 'Esta intenção voltará a constar como ativa nas buscas, relatórios e contagens.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sim, Reativar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="intention_id" value="${id}">
                        <input type="hidden" name="reactivate_intention" value="1">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>

</html>