<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();
$user_id = $_SESSION['user_id'];
$pageTitle = 'Meus Clientes';

// Handle Delete Client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_client'])) {
    $client_id_to_delete = $_POST['client_id'];
    $stmt = $pdo->prepare("DELETE FROM " . TABLE_NAME . "clients WHERE id = ? AND user_id = ?");
    $stmt->execute([$client_id_to_delete, $user_id]);
    header("Location: clients.php");
    exit;
}

// Handle Attend Client (Novo -> set status to 'Atendido' and open WhatsApp)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['attend_client']) || isset($_POST['approve_client']))) {
    $client_id_to_attend = $_POST['client_id'];
    $stmt = $pdo->prepare("UPDATE " . TABLE_NAME . "clients SET status = 'Atendido' WHERE id = ? AND user_id = ?");
    $stmt->execute([$client_id_to_attend, $user_id]);

    $stmt = $pdo->prepare("SELECT * FROM " . TABLE_NAME . "clients WHERE id = ? AND user_id = ?");
    $stmt->execute([$client_id_to_attend, $user_id]);
    $attendedClient = $stmt->fetch();

    if ($attendedClient && !empty($attendedClient['phone'])) {
        $phoneClean = preg_replace('/[^0-9]/', '', $attendedClient['phone']);
        if (!empty($phoneClean)) {
            $msg = buildClientApprovalWelcomeMessage($attendedClient);
            $waUrl = "https://wa.me/+55" . $phoneClean . "?text=" . rawurlencode($msg);
            header("Location: " . $waUrl);
            exit;
        }
    }
    header("Location: clients.php");
    exit;
}

// Handle Send to Embral (Atendido -> set status to 'Embral' and open PDF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_embral'])) {
    $client_id_to_embral = $_POST['client_id'];
    $stmt = $pdo->prepare("UPDATE " . TABLE_NAME . "clients SET status = 'Embral' WHERE id = ? AND user_id = ?");
    $stmt->execute([$client_id_to_embral, $user_id]);
    header("Location: client-pdf.php?id=" . $client_id_to_embral . "&sent=embral");
    exit;
}

// Handle Toggle Potential Lead
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_potential'])) {
    $client_id_to_toggle = $_POST['client_id'];
    $stmt = $pdo->prepare("UPDATE " . TABLE_NAME . "clients SET is_potential = IF(is_potential=1, 0, 1) WHERE id = ? AND user_id = ?");
    $stmt->execute([$client_id_to_toggle, $user_id]);
    header("Location: clients.php");
    exit;
}

// Fetch Clients with sorting (Default: newest date DESC)
$sort = $_GET['sort'] ?? 'date';
$order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';

switch ($sort) {
    case 'name':
        $orderBy = "c.name {$order}, c.id DESC";
        break;
    case 'city':
        $orderBy = "c.city {$order}, c.uf {$order}, c.id DESC";
        break;
    case 'status':
        $orderBy = "c.status {$order}, c.id DESC";
        break;
    case 'date':
    default:
        $sort = 'date';
        $orderBy = "COALESCE(c.created_at, '1970-01-01 00:00:00') {$order}, c.id {$order}";
        break;
}

$stmt = $pdo->prepare("
    SELECT c.* 
    FROM " . TABLE_NAME . "clients c 
    WHERE c.user_id = ? 
    ORDER BY {$orderBy}
");
$stmt->execute([$user_id]);
$clients = $stmt->fetchAll();

// Build encrypted pre-registration link for current user
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$precadastroUrl = $protocol . "://" . $host . "/precadastro.php?ref=" . encryptUserId($user_id);
$statusFilterParam = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - CRM Vitor Müller</title>
    <script src="https://cdn.tailwindcss.com"></script>
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

                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-3xl font-bold text-brand-900">Clientes</h1>
                    <a href="client-add.php"
                        class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-2.5 px-6 rounded-lg shadow-md transition transform hover:-translate-y-0.5 active:translate-y-0 flex items-center cursor-pointer text-sm">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Novo
                    </a>
                </div>

                <!-- Search & Status Filter (65% Search, 35% Status) -->
                <div class="mb-6 flex gap-2 sm:gap-4 items-center">
                    <div class="relative w-[65%]">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 sm:pl-3 pointer-events-none">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </span>
                        <input type="text" id="searchInput"
                            class="w-full pl-8 sm:pl-10 pr-2.5 sm:pr-4 py-2 sm:py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm bg-white text-xs sm:text-sm"
                            placeholder="Buscar por nome, data, fazenda, telefone, cidade, UF...">
                    </div>

                    <div class="w-[35%]">
                        <div class="relative">
                            <select id="statusFilter"
                                class="w-full pl-2 sm:pl-3 pr-6 sm:pr-8 py-2 sm:py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm bg-white font-medium text-gray-700 text-xs sm:text-sm appearance-none cursor-pointer truncate">
                                <option value="" <?php echo empty($statusFilterParam) ? 'selected' : ''; ?>>Todos Status
                                </option>
                                <option value="Novo" <?php echo in_array($statusFilterParam, ['Novo', 'Pré-cadastro']) ? 'selected' : ''; ?>>🟡 Novos</option>
                                <option value="Atendido" <?php echo $statusFilterParam === 'Atendido' ? 'selected' : ''; ?>>🟣 Atendidos</option>
                                <option value="Embral" <?php echo $statusFilterParam === 'Embral' ? 'selected' : ''; ?>>🔵
                                    Embral</option>
                                <option value="Ativo" <?php echo $statusFilterParam === 'Ativo' ? 'selected' : ''; ?>>🟢
                                    Ativos</option>
                                <option value="Inativo" <?php echo $statusFilterParam === 'Inativo' ? 'selected' : ''; ?>>
                                    ⚫ Inativos</option>
                            </select>
                            <div
                                class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-1.5 sm:px-2.5 text-gray-500">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white shadow-md rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full leading-normal" id="clientsTable">
                            <thead>
                                <tr>
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-200 transition select-none sort-header"
                                        data-sort="name" title="Clique para ordenar por Nome">
                                        <div class="flex items-center space-x-1">
                                            <span>Nome do Cliente</span>
                                            <span class="sort-icon text-gray-400 text-xs">↕</span>
                                        </div>
                                    </th>
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-200 transition select-none sort-header"
                                        data-sort="date" title="Clique para ordenar por Data/Hora de Cadastro">
                                        <div class="flex items-center space-x-1">
                                            <span>Cadastro</span>
                                            <span class="sort-icon text-gray-400 text-xs">↕</span>
                                        </div>
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Contato</th>
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-200 transition select-none sort-header"
                                        data-sort="city" title="Clique para ordenar por Cidade / UF">
                                        <div class="flex items-center space-x-1">
                                            <span>Cidade / UF</span>
                                            <span class="sort-icon text-gray-400 text-xs">↕</span>
                                        </div>
                                    </th>
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-200 transition select-none sort-header"
                                        data-sort="status" title="Clique para ordenar por Status">
                                        <div class="flex items-center space-x-1">
                                            <span>Status</span>
                                            <span class="sort-icon text-gray-400 text-xs">↕</span>
                                        </div>
                                    </th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Mapa</th>
                                    <th
                                        class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Ações</th>
                                </tr>
                            </thead>
                            <tbody id="clientsTableBody">
                                <?php if (count($clients) > 0): ?>
                                    <?php foreach ($clients as $client): ?>
                                        <tr class="hover:bg-gray-50 transition client-row"
                                            data-name="<?php echo htmlspecialchars(mb_strtolower($client['name'], 'UTF-8')); ?>"
                                            data-date="<?php echo !empty($client['created_at']) ? strtotime($client['created_at']) : (int) $client['id']; ?>"
                                            data-city="<?php echo htmlspecialchars(mb_strtolower(($client['city'] ?? '') . ' ' . ($client['uf'] ?? ''), 'UTF-8')); ?>"
                                            data-status="<?php echo htmlspecialchars(mb_strtolower($client['status'] ?? '', 'UTF-8')); ?>">
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 mr-3">
                                                        <!-- Toggle Potential Button -->
                                                        <form method="POST" class="inline flex items-center">
                                                            <input type="hidden" name="client_id"
                                                                value="<?php echo $client['id']; ?>">
                                                            <input type="hidden" name="toggle_potential" value="1">
                                                            <button type="submit"
                                                                class="p-1 rounded-full hover:bg-amber-50 transition <?php echo !empty($client['is_potential']) ? 'text-amber-500 hover:text-amber-600' : 'text-gray-300 hover:text-amber-500'; ?>"
                                                                title="<?php echo !empty($client['is_potential']) ? 'Remover marcação de Potencial' : 'Marcar como Cliente em Potencial'; ?>">
                                                                <svg class="w-6 h-6"
                                                                    fill="<?php echo !empty($client['is_potential']) ? 'currentColor' : 'none'; ?>"
                                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z">
                                                                    </path>
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                    <div>
                                                        <a href="client-details.php?id=<?php echo $client['id']; ?>"
                                                            class="text-gray-900 hover:text-brand-600 font-semibold hover:underline client-name inline-block"
                                                            title="Ver detalhes de <?php echo htmlspecialchars($client['name']); ?>">
                                                            <?php echo htmlspecialchars($client['name']); ?>
                                                        </a>
                                                        <?php if (!empty($client['farm_name'])): ?>
                                                            <p class="text-xs text-brand-700 font-medium client-farm">
                                                                🏡 <?php echo htmlspecialchars($client['farm_name']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td
                                                class="px-5 py-5 border-b border-gray-200 bg-white text-sm whitespace-nowrap client-date">
                                                <?php if (!empty($client['created_at'])): ?>
                                                    <div class="text-gray-900 font-medium">
                                                        <?php echo date('d/m/Y', strtotime($client['created_at'])); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <?php echo date('H:i', strtotime($client['created_at'])); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-gray-400" title="Data não disponível">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <?php if (!empty($client['phone'])): ?>
                                                    <a href="https://wa.me/+55<?php echo preg_replace('/[^0-9]/', '', $client['phone']); ?>"
                                                        target="_blank"
                                                        class="text-green-600 hover:text-green-800 font-semibold hover:underline flex items-center client-phone mb-1"
                                                        title="Abrir conversa no WhatsApp">
                                                        <svg class="w-4 h-4 mr-1.5 fill-current text-green-500 flex-shrink-0"
                                                            viewBox="0 0 24 24">
                                                            <path
                                                                d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                                        </svg>
                                                        <span><?php echo htmlspecialchars(formatPhone($client['phone'])); ?></span>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!empty($client['email'])): ?>
                                                    <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>"
                                                        class="text-blue-600 hover:text-blue-800 text-xs flex items-center client-email hover:underline truncate max-w-xs"
                                                        title="Enviar e-mail para <?php echo htmlspecialchars($client['email']); ?>">
                                                        <svg class="w-3.5 h-3.5 mr-1 text-blue-500 flex-shrink-0" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                                            </path>
                                                        </svg>
                                                        <span><?php echo htmlspecialchars($client['email']); ?></span>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (empty($client['phone']) && empty($client['email'])): ?>
                                                    <span class="text-gray-400 client-phone">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <span class="relative client-location text-gray-700">
                                                    <?php
                                                    $loc = array_filter([$client['city'] ?? '', $client['uf'] ?? '']);
                                                    echo htmlspecialchars(!empty($loc) ? implode(' / ', $loc) : 'N/A');
                                                    ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <span class="client-status">
                                                    <?php if (($client['status'] ?? '') === 'Embral'): ?>
                                                        <span
                                                            class="bg-blue-100 text-blue-800 text-xs px-3 py-1 rounded-full font-bold border border-blue-300 inline-block">
                                                            Embral
                                                        </span>
                                                    <?php elseif (($client['status'] ?? '') === 'Atendido'): ?>
                                                        <span
                                                            class="bg-purple-100 text-purple-800 text-xs px-3 py-1 rounded-full font-bold border border-purple-300 inline-block">
                                                            Atendido
                                                        </span>
                                                    <?php elseif (($client['status'] ?? '') === 'Inativo'): ?>
                                                        <span
                                                            class="bg-gray-100 text-gray-700 text-xs px-3 py-1 rounded-full font-bold border border-gray-300 inline-block">
                                                            Inativo
                                                        </span>
                                                    <?php elseif (in_array($client['status'] ?? '', ['Novo', 'Pré-cadastro'])): ?>
                                                        <span
                                                            class="bg-amber-100 text-amber-800 text-xs px-3 py-1 rounded-full font-bold border border-amber-300 inline-block">
                                                            Novo
                                                        </span>
                                                    <?php else: ?>
                                                        <span
                                                            class="bg-green-100 text-green-800 text-xs px-3 py-1 rounded-full font-bold border border-green-300 inline-block">
                                                            Ativo
                                                        </span>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <?php if (!empty($client['latitude']) && !empty($client['longitude'])): ?>
                                                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $client['latitude']; ?>,<?php echo $client['longitude']; ?>"
                                                        target="_blank" class="text-blue-500 hover:text-blue-800"
                                                        title="Ver no Mapa">
                                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                                            </path>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        </svg>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-gray-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <div class="flex items-center space-x-3">
                                                    <a href="client-edit.php?id=<?php echo $client['id']; ?>"
                                                        class="text-yellow-600 hover:text-yellow-800" title="Editar">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                            </path>
                                                        </svg>
                                                    </a>

                                                    <a href="client-pdf.php?id=<?php echo $client['id']; ?>" target="_blank"
                                                        class="text-red-500 hover:text-red-700"
                                                        title="Gerar PDF / Imprimir Ficha">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                                                            </path>
                                                        </svg>
                                                    </a>

                                                    <!-- Attend Client Button (Novo -> Atendido) -->
                                                    <?php if (in_array($client['status'] ?? '', ['Novo', 'Pré-cadastro'])): ?>
                                                        <?php
                                                        $rowPhoneClean = preg_replace('/[^0-9]/', '', $client['phone'] ?? '');
                                                        $rowApprovalMsg = buildClientApprovalWelcomeMessage($client);
                                                        $rowWaApprovalUrl = !empty($rowPhoneClean) ? "https://wa.me/+55" . $rowPhoneClean . "?text=" . rawurlencode($rowApprovalMsg) : '';
                                                        ?>
                                                        <form method="POST" class="inline"
                                                            onsubmit="if('<?php echo addslashes($rowWaApprovalUrl); ?>'){ window.open('<?php echo addslashes($rowWaApprovalUrl); ?>', '_blank'); }">
                                                            <input type="hidden" name="client_id"
                                                                value="<?php echo $client['id']; ?>">
                                                            <input type="hidden" name="attend_client" value="1">
                                                            <button type="submit" class="text-purple-600 hover:text-purple-800"
                                                                title="Marcar como Atendido (Alterar status para Atendido e abrir WhatsApp)">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <!-- Send to Embral Button (Atendido -> Embral) -->
                                                    <?php if (($client['status'] ?? '') === 'Atendido'): ?>
                                                        <form method="POST" class="inline"
                                                            onsubmit="window.open('client-pdf.php?id=<?php echo $client['id']; ?>&sent=embral', '_blank');">
                                                            <input type="hidden" name="client_id"
                                                                value="<?php echo $client['id']; ?>">
                                                            <input type="hidden" name="send_embral" value="1">
                                                            <button type="submit" class="text-blue-600 hover:text-blue-800"
                                                                title="Enviar dados para Embral (Alterar status para Embral e abrir Ficha/WhatsApp)">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8">
                                                                    </path>
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <form method="POST" onsubmit="confirmDelete(event)" class="inline">
                                                        <input type="hidden" name="client_id"
                                                            value="<?php echo $client['id']; ?>">
                                                        <input type="hidden" name="delete_client" value="1">
                                                        <button type="submit" class="text-red-500 hover:text-red-700"
                                                            title="Excluir">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                                </path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7"
                                            class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center text-gray-500">
                                            Nenhum cliente encontrado.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div id="noResults" class="hidden px-5 py-8 bg-white text-sm text-center text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                </path>
                            </svg>
                            <p class="font-medium text-gray-600">Nenhum cliente encontrado para os critérios de busca.
                            </p>
                            <p class="text-xs text-gray-400 mt-1">Tente ajustar o termo de pesquisa ou o filtro de
                                status.</p>
                        </div>

                        <!-- Pagination Footer -->
                        <div id="paginationContainer"
                            class="px-5 py-4 bg-white border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <!-- Info & Per-Page Selector -->
                            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                                <span id="paginationInfo">
                                    Mostrando <strong class="text-gray-900 font-semibold" id="pageStart">1</strong> a
                                    <strong class="text-gray-900 font-semibold" id="pageEnd">10</strong> de <strong
                                        class="text-gray-900 font-semibold" id="totalItems">0</strong> clientes
                                </span>
                                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                                    <label for="perPageSelect" class="whitespace-nowrap font-medium">Por página:</label>
                                    <select id="perPageSelect"
                                        class="border border-gray-300 rounded-md px-2.5 py-1 bg-white text-gray-700 text-xs focus:ring-2 focus:ring-brand-500 focus:outline-none shadow-sm cursor-pointer">
                                        <option value="10" selected>10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                        <option value="all">Todos</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Page Navigation Buttons -->
                            <div class="flex items-center space-x-1" id="paginationButtons">
                                <!-- Rendered dynamically via JS -->
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- Filter, Sorting & Pagination Script -->
    <script>
        let currentPage = 1;
        let perPage = 10;
        let filteredRows = [];

        let currentSort = '<?php echo htmlspecialchars($sort); ?>';
        let currentOrder = '<?php echo htmlspecialchars($order); ?>';

        function updateSortIcons() {
            const sortHeaders = document.querySelectorAll('.sort-header');
            sortHeaders.forEach(header => {
                const col = header.getAttribute('data-sort');
                const iconSpan = header.querySelector('.sort-icon');
                if (col === currentSort) {
                    header.classList.add('bg-brand-100', 'text-brand-900');
                    iconSpan.className = 'sort-icon text-brand-600 font-bold ml-1';
                    iconSpan.textContent = currentOrder === 'ASC' ? '▲' : '▼';
                } else {
                    header.classList.remove('bg-brand-100', 'text-brand-900');
                    iconSpan.className = 'sort-icon text-gray-400 text-xs ml-1';
                    iconSpan.textContent = '↕';
                }
            });
        }

        function sortTable(col, order) {
            const tableBody = document.getElementById('clientsTableBody');
            const rows = Array.from(tableBody.querySelectorAll('.client-row'));

            rows.sort((a, b) => {
                let valA = a.getAttribute('data-' + col) || '';
                let valB = b.getAttribute('data-' + col) || '';

                if (col === 'date') {
                    valA = parseInt(valA, 10) || 0;
                    valB = parseInt(valB, 10) || 0;
                    return order === 'ASC' ? valA - valB : valB - valA;
                } else {
                    return order === 'ASC'
                        ? valA.localeCompare(valB, 'pt-BR', { sensitivity: 'base' })
                        : valB.localeCompare(valA, 'pt-BR', { sensitivity: 'base' });
                }
            });

            rows.forEach(row => tableBody.appendChild(row));

            currentSort = col;
            currentOrder = order;
            updateSortIcons();

            // Update URL parameter without reload
            const url = new URL(window.location);
            url.searchParams.set('sort', col);
            url.searchParams.set('order', order);
            window.history.replaceState({}, '', url);

            // Re-render pagination with sorted DOM order
            currentPage = 1;
            renderPagination();
        }

        function getMatchingRows() {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');

            const searchText = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const selectedStatus = statusFilter ? statusFilter.value.toLowerCase().trim() : '';
            const rows = Array.from(document.querySelectorAll('.client-row'));

            return rows.filter(row => {
                const name = row.querySelector('.client-name') ? row.querySelector('.client-name').textContent.toLowerCase() : '';
                const farm = row.querySelector('.client-farm') ? row.querySelector('.client-farm').textContent.toLowerCase() : '';
                const date = row.querySelector('.client-date') ? row.querySelector('.client-date').textContent.toLowerCase() : '';
                const phone = row.querySelector('.client-phone') ? row.querySelector('.client-phone').textContent.toLowerCase().replace(/[^0-9]/g, '') : '';
                const phoneFormatted = row.querySelector('.client-phone') ? row.querySelector('.client-phone').textContent.toLowerCase() : '';
                const email = row.querySelector('.client-email') ? row.querySelector('.client-email').textContent.toLowerCase().trim() : '';
                const location = row.querySelector('.client-location') ? row.querySelector('.client-location').textContent.toLowerCase() : '';
                const statusEl = row.querySelector('.client-status');
                const status = statusEl ? statusEl.textContent.toLowerCase().trim() : '';

                // Status match logic
                let statusMatches = true;
                if (selectedStatus !== '') {
                    if (selectedStatus === 'novo') {
                        statusMatches = status.includes('novo') || status.includes('pré-cadastro') || status.includes('precadastro');
                    } else if (selectedStatus === 'atendido') {
                        statusMatches = status.includes('atendido');
                    } else if (selectedStatus === 'embral') {
                        statusMatches = status.includes('embral');
                    } else if (selectedStatus === 'ativo') {
                        statusMatches = status.includes('ativo');
                    } else if (selectedStatus === 'inativo') {
                        statusMatches = status.includes('inativo');
                    } else {
                        statusMatches = status.includes(selectedStatus);
                    }
                }

                // Text search match logic
                let textMatches = true;
                if (searchText !== '') {
                    textMatches = name.includes(searchText) ||
                        farm.includes(searchText) ||
                        date.includes(searchText) ||
                        phone.includes(searchText) ||
                        phoneFormatted.includes(searchText) ||
                        email.includes(searchText) ||
                        location.includes(searchText) ||
                        status.includes(searchText);
                }

                return statusMatches && textMatches;
            });
        }

        function renderPagination() {
            const allRows = Array.from(document.querySelectorAll('.client-row'));
            filteredRows = getMatchingRows();
            const total = filteredRows.length;
            const noResults = document.getElementById('noResults');
            const paginationContainer = document.getElementById('paginationContainer');

            // Hide all rows initially
            allRows.forEach(r => r.style.display = 'none');

            if (total === 0) {
                if (noResults) noResults.classList.remove('hidden');
                if (paginationContainer) paginationContainer.classList.add('hidden');
                return;
            }

            if (noResults) noResults.classList.add('hidden');
            if (paginationContainer) paginationContainer.classList.remove('hidden');

            const limit = perPage === 'all' ? total : parseInt(perPage, 10);
            const totalPages = Math.ceil(total / limit) || 1;

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const startIndex = (currentPage - 1) * limit;
            const endIndex = perPage === 'all' ? total : Math.min(startIndex + limit, total);

            // Display matching page slice
            for (let i = startIndex; i < endIndex; i++) {
                if (filteredRows[i]) {
                    filteredRows[i].style.display = '';
                }
            }

            // Update counter info
            const pageStartEl = document.getElementById('pageStart');
            const pageEndEl = document.getElementById('pageEnd');
            const totalItemsEl = document.getElementById('totalItems');
            if (pageStartEl) pageStartEl.textContent = total > 0 ? (startIndex + 1) : 0;
            if (pageEndEl) pageEndEl.textContent = endIndex;
            if (totalItemsEl) totalItemsEl.textContent = total;

            // Render page buttons
            renderButtons(totalPages);
        }

        function goToPage(page) {
            currentPage = page;
            renderPagination();
            const table = document.getElementById('clientsTable');
            if (table) {
                table.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        function createPageBtn(pageNumber) {
            const btn = document.createElement('button');
            btn.type = 'button';
            const isActive = (pageNumber === currentPage);
            btn.className = `px-3 py-1.5 text-xs font-bold rounded-md border ${isActive ? 'bg-brand-600 text-white border-brand-600 shadow-sm' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100 shadow-sm'} transition cursor-pointer`;
            btn.textContent = pageNumber;
            if (!isActive) {
                btn.onclick = () => goToPage(pageNumber);
            }
            return btn;
        }

        function renderButtons(totalPages) {
            const btnContainer = document.getElementById('paginationButtons');
            if (!btnContainer) return;
            btnContainer.innerHTML = '';

            if (totalPages <= 1 && perPage === 'all') {
                return;
            }

            // Previous Button
            const prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = `px-3 py-1.5 text-xs font-semibold rounded-md border ${currentPage === 1 ? 'border-gray-200 text-gray-400 cursor-not-allowed bg-gray-50' : 'border-gray-300 text-gray-700 bg-white hover:bg-gray-100 cursor-pointer shadow-sm'} transition flex items-center`;
            prevBtn.innerHTML = `
                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Anterior
            `;
            prevBtn.disabled = (currentPage === 1);
            if (currentPage > 1) {
                prevBtn.onclick = () => goToPage(currentPage - 1);
            }
            btnContainer.appendChild(prevBtn);

            // Numbered buttons
            const maxButtons = 5;
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, startPage + maxButtons - 1);

            if (endPage - startPage < maxButtons - 1) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }

            if (startPage > 1) {
                btnContainer.appendChild(createPageBtn(1));
                if (startPage > 2) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'px-1.5 py-1 text-xs text-gray-400';
                    ellipsis.textContent = '...';
                    btnContainer.appendChild(ellipsis);
                }
            }

            for (let p = startPage; p <= endPage; p++) {
                btnContainer.appendChild(createPageBtn(p));
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'px-1.5 py-1 text-xs text-gray-400';
                    ellipsis.textContent = '...';
                    btnContainer.appendChild(ellipsis);
                }
                btnContainer.appendChild(createPageBtn(totalPages));
            }

            // Next Button
            const nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = `px-3 py-1.5 text-xs font-semibold rounded-md border ${currentPage === totalPages ? 'border-gray-200 text-gray-400 cursor-not-allowed bg-gray-50' : 'border-gray-300 text-gray-700 bg-white hover:bg-gray-100 cursor-pointer shadow-sm'} transition flex items-center`;
            nextBtn.innerHTML = `
                Próximo
                <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            `;
            nextBtn.disabled = (currentPage === totalPages);
            if (currentPage < totalPages) {
                nextBtn.onclick = () => goToPage(currentPage + 1);
            }
            btnContainer.appendChild(nextBtn);
        }

        function onFiltersChanged() {
            currentPage = 1; // Reset to first page whenever search or filter changes
            renderPagination();
        }

        document.querySelectorAll('.sort-header').forEach(header => {
            header.addEventListener('click', function () {
                const col = this.getAttribute('data-sort');
                let newOrder = 'ASC';
                if (col === currentSort) {
                    newOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    newOrder = col === 'date' ? 'DESC' : 'ASC';
                }
                sortTable(col, newOrder);
            });
        });

        document.getElementById('searchInput').addEventListener('input', onFiltersChanged);
        document.getElementById('statusFilter').addEventListener('change', onFiltersChanged);
        document.getElementById('perPageSelect').addEventListener('change', function () {
            perPage = this.value;
            currentPage = 1;
            renderPagination();
        });

        // Initialize sort icons and pagination on load
        updateSortIcons();
        renderPagination();
    </script>
</body>

</html>