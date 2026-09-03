<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();
$user_id = $_SESSION['user_id'];
$isAdmin = isAdmin();
$pageTitle = 'Clientes';

// Helper to keep query parameters on redirect
function getClientRedirectUrl() {
    $allowedParams = ['q', 'search', 'status', 'scope', 'sort', 'order', 'page', 'per_page'];
    $queryParams = [];
    foreach ($allowedParams as $param) {
        if (isset($_GET[$param]) && $_GET[$param] !== '') {
            $queryParams[$param] = $_GET[$param];
        }
    }
    return 'clients.php' . (!empty($queryParams) ? '?' . http_build_query($queryParams) : '');
}

// Handle Delete Client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_client'])) {
    $client_id_to_delete = intval($_POST['client_id']);
    $stmt = $pdo->prepare("SELECT user_id FROM " . TABLE_NAME . "clients WHERE id = ?");
    $stmt->execute([$client_id_to_delete]);
    $targetClient = $stmt->fetch();

    if ($targetClient && canEditClient($targetClient['user_id'])) {
        $stmt = $pdo->prepare("DELETE FROM " . TABLE_NAME . "clients WHERE id = ?");
        $stmt->execute([$client_id_to_delete]);
    }
    header("Location: " . getClientRedirectUrl());
    exit;
}

// Handle Attend Client (Novo -> set status to 'Atendido' and open WhatsApp)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['attend_client']) || isset($_POST['approve_client']))) {
    $client_id_to_attend = intval($_POST['client_id']);
    $stmt = $pdo->prepare("SELECT * FROM " . TABLE_NAME . "clients WHERE id = ?");
    $stmt->execute([$client_id_to_attend]);
    $attendedClient = $stmt->fetch();

    if ($attendedClient && canEditClient($attendedClient['user_id'])) {
        $stmt = $pdo->prepare("UPDATE " . TABLE_NAME . "clients SET status = 'Atendido' WHERE id = ?");
        $stmt->execute([$client_id_to_attend]);

        if (!empty($attendedClient['phone'])) {
            $phoneClean = preg_replace('/[^0-9]/', '', $attendedClient['phone']);
            if (!empty($phoneClean)) {
                $msg = buildClientApprovalWelcomeMessage($attendedClient);
                $waUrl = "https://wa.me/+55" . $phoneClean . "?text=" . rawurlencode($msg);
                header("Location: " . $waUrl);
                exit;
            }
        }
    }
    header("Location: " . getClientRedirectUrl());
    exit;
}

// Handle Send to Embral (Atendido -> set status to 'Embral' and open PDF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_embral'])) {
    $client_id_to_embral = intval($_POST['client_id']);
    $stmt = $pdo->prepare("SELECT user_id FROM " . TABLE_NAME . "clients WHERE id = ?");
    $stmt->execute([$client_id_to_embral]);
    $targetClient = $stmt->fetch();

    if ($targetClient && canEditClient($targetClient['user_id'])) {
        $stmt = $pdo->prepare("UPDATE " . TABLE_NAME . "clients SET status = 'Embral' WHERE id = ?");
        $stmt->execute([$client_id_to_embral]);
        header("Location: client-pdf.php?id=" . $client_id_to_embral . "&sent=embral");
        exit;
    }
    header("Location: " . getClientRedirectUrl());
    exit;
}

// Handle Toggle Potential Lead
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_potential'])) {
    $client_id_to_toggle = intval($_POST['client_id']);
    $stmt = $pdo->prepare("SELECT user_id FROM " . TABLE_NAME . "clients WHERE id = ?");
    $stmt->execute([$client_id_to_toggle]);
    $targetClient = $stmt->fetch();

    if ($targetClient && canEditClient($targetClient['user_id'])) {
        $stmt = $pdo->prepare("UPDATE " . TABLE_NAME . "clients SET is_potential = IF(is_potential=1, 0, 1) WHERE id = ?");
        $stmt->execute([$client_id_to_toggle]);
    }
    header("Location: " . getClientRedirectUrl());
    exit;
}

// -------------------------------------------------------------
// FILTER & PAGINATION PARAMETERS (SERVER-SIDE FOR SPEED)
// -------------------------------------------------------------
$search = trim($_GET['q'] ?? ($_GET['search'] ?? ''));
$statusFilterParam = trim($_GET['status'] ?? '');
$scopeFilterParam = trim($_GET['scope'] ?? 'all');
$sort = trim($_GET['sort'] ?? 'date');
$order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';

$perPageParam = trim($_GET['per_page'] ?? '10');
$perPage = ($perPageParam === 'all') ? 'all' : (in_array(intval($perPageParam), [10, 25, 50, 100]) ? intval($perPageParam) : 10);
$page = max(1, intval($_GET['page'] ?? 1));

// Build SQL WHERE Conditions
$where = [];
$params = [];

// 1. Scope (Apenas os Meus vs Todos)
if ($scopeFilterParam === 'mine') {
    $where[] = "c.user_id = :scope_user_id";
    $params[':scope_user_id'] = $user_id;
}

// 2. Status Filter
if (!empty($statusFilterParam)) {
    $stLower = mb_strtolower($statusFilterParam, 'UTF-8');
    if ($stLower === 'novo' || $stLower === 'pré-cadastro' || $stLower === 'pre-cadastro') {
        $where[] = "(c.status = 'Novo' OR c.status = 'Pré-cadastro' OR c.status = 'Pre-cadastro')";
    } elseif ($stLower === 'atendido') {
        $where[] = "c.status = 'Atendido'";
    } elseif ($stLower === 'embral') {
        $where[] = "c.status = 'Embral'";
    } elseif ($stLower === 'ativo') {
        $where[] = "(c.status = 'Ativo' OR c.status IS NULL OR c.status = '')";
    } elseif ($stLower === 'inativo') {
        $where[] = "c.status = 'Inativo'";
    } else {
        $where[] = "c.status = :status_exact";
        $params[':status_exact'] = $statusFilterParam;
    }
}

// 3. Search text
if (!empty($search)) {
    $digits = preg_replace('/\D/', '', $search);
    $searchConds = [
        "c.name LIKE :search_term",
        "c.farm_name LIKE :search_term",
        "c.city LIKE :search_term",
        "c.uf LIKE :search_term",
        "c.email LIKE :search_term",
        "u.name LIKE :search_term"
    ];
    $params[':search_term'] = '%' . $search . '%';

    if (strlen($digits) >= 3) {
        $searchConds[] = "REPLACE(REPLACE(REPLACE(REPLACE(c.phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE :search_phone";
        $params[':search_phone'] = '%' . $digits . '%';
    }

    $where[] = "(" . implode(" OR ", $searchConds) . ")";
}

$whereSql = !empty($where) ? ("WHERE " . implode(" AND ", $where)) : "";

// Sort Order
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

// Count Total Matching Clients
$countSql = "SELECT COUNT(*) FROM " . TABLE_NAME . "clients c LEFT JOIN " . TABLE_NAME . "users u ON c.user_id = u.id {$whereSql}";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalItems = (int) $stmtCount->fetchColumn();

// Compute Pagination Dimensions
$limit = ($perPage === 'all') ? max(1, $totalItems) : (int) $perPage;
$totalPages = ($limit > 0) ? (int) ceil($totalItems / $limit) : 1;
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
if ($page < 1) $page = 1;

$offset = ($page - 1) * $limit;
if ($offset < 0) $offset = 0;

// Fetch Paginated Rows Only
$dataSql = "SELECT c.*, u.name as operator_name 
            FROM " . TABLE_NAME . "clients c 
            LEFT JOIN " . TABLE_NAME . "users u ON c.user_id = u.id 
            {$whereSql} 
            ORDER BY {$orderBy} " . ($perPage === 'all' ? "" : "LIMIT {$limit} OFFSET {$offset}");
$stmtData = $pdo->prepare($dataSql);
$stmtData->execute($params);
$clients = $stmtData->fetchAll();

$pageStart = $totalItems > 0 ? ($offset + 1) : 0;
$pageEnd = ($perPage === 'all') ? $totalItems : min($offset + $limit, $totalItems);

// Encrypted pre-registration link for current user
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$precadastroUrl = $protocol . "://" . $host . "/precadastro.php?ref=" . encryptUserId($user_id);
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
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-brand-50 p-4 sm:p-6">

                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-brand-900">Clientes</h1>
                        <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Gerenciamento e relacionamento com clientes.</p>
                    </div>
                    <a href="client-add.php"
                        class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-2 sm:py-2.5 px-4 sm:px-6 rounded-lg shadow-md transition transform hover:-translate-y-0.5 active:translate-y-0 flex items-center cursor-pointer text-xs sm:text-sm">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-1.5 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Novo Cliente
                    </a>
                </div>

                <!-- Form de Busca e Filtros Rápidos (Server-Side) -->
                <form method="GET" id="clientsFilterForm" class="mb-6 space-y-3">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
                    <input type="hidden" name="page" id="pageInput" value="1">
                    <input type="hidden" name="per_page" id="perPageHidden" value="<?php echo htmlspecialchars($perPageParam); ?>">

                    <div class="flex flex-wrap sm:flex-nowrap gap-2 sm:gap-3 items-center">
                        <!-- Campo de Busca -->
                        <div class="relative flex-1 min-w-[200px]">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </span>
                            <input type="text" name="q" id="searchInput"
                                value="<?php echo htmlspecialchars($search); ?>"
                                class="w-full pl-9 pr-8 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm bg-white text-xs sm:text-sm"
                                placeholder="Buscar por nome, fazenda, telefone, cidade, UF...">
                            <?php if (!empty($search)): ?>
                                <button type="button" onclick="clearSearch()"
                                    class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 hover:text-gray-600"
                                    title="Limpar busca">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Filtro de Escopo (Apenas os Meus vs Todos) -->
                        <div class="w-full sm:w-44">
                            <div class="relative">
                                <select name="scope" id="scopeFilter" onchange="submitFilterForm()"
                                    class="w-full pl-3 pr-8 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm bg-white font-medium text-gray-700 text-xs sm:text-sm appearance-none cursor-pointer truncate">
                                    <option value="all" <?php echo $scopeFilterParam === 'all' ? 'selected' : ''; ?>>🌐 Todos do Sistema</option>
                                    <option value="mine" <?php echo $scopeFilterParam === 'mine' ? 'selected' : ''; ?>>👤 Apenas os Meus</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-500">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Filtro de Status -->
                        <div class="w-full sm:w-40">
                            <div class="relative">
                                <select name="status" id="statusFilter" onchange="submitFilterForm()"
                                    class="w-full pl-3 pr-8 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm bg-white font-medium text-gray-700 text-xs sm:text-sm appearance-none cursor-pointer truncate">
                                    <option value="" <?php echo empty($statusFilterParam) ? 'selected' : ''; ?>>Todos Status</option>
                                    <option value="Novo" <?php echo in_array($statusFilterParam, ['Novo', 'Pré-cadastro']) ? 'selected' : ''; ?>>🟡 Novos</option>
                                    <option value="Atendido" <?php echo $statusFilterParam === 'Atendido' ? 'selected' : ''; ?>>🟣 Atendidos</option>
                                    <option value="Embral" <?php echo $statusFilterParam === 'Embral' ? 'selected' : ''; ?>>🔵 Embral</option>
                                    <option value="Ativo" <?php echo $statusFilterParam === 'Ativo' ? 'selected' : ''; ?>>🟢 Ativos</option>
                                    <option value="Inativo" <?php echo $statusFilterParam === 'Inativo' ? 'selected' : ''; ?>>⚫ Inativos</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-500">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Botão Limpar Filtros -->
                        <?php if (!empty($search) || !empty($statusFilterParam) || $scopeFilterParam === 'mine'): ?>
                            <a href="clients.php"
                                class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-lg transition shadow-xs flex items-center gap-1 cursor-pointer whitespace-nowrap"
                                title="Limpar todos os filtros">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Limpar
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Tabela de Clientes -->
                <div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full leading-normal" id="clientsTable">
                            <thead>
                                <tr>
                                    <!-- Coluna Nome -->
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-200 transition select-none"
                                        onclick="applySort('name')">
                                        <div class="flex items-center space-x-1">
                                            <span>Nome do Cliente</span>
                                            <span class="text-xs <?php echo $sort === 'name' ? 'text-brand-600 font-bold' : 'text-gray-400'; ?>">
                                                <?php echo $sort === 'name' ? ($order === 'ASC' ? '▲' : '▼') : '↕'; ?>
                                            </span>
                                        </div>
                                    </th>

                                    <!-- Coluna Cadastro -->
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-200 transition select-none"
                                        onclick="applySort('date')">
                                        <div class="flex items-center space-x-1">
                                            <span>Cadastro</span>
                                            <span class="text-xs <?php echo $sort === 'date' ? 'text-brand-600 font-bold' : 'text-gray-400'; ?>">
                                                <?php echo $sort === 'date' ? ($order === 'ASC' ? '▲' : '▼') : '↕'; ?>
                                            </span>
                                        </div>
                                    </th>

                                    <!-- Coluna Contato -->
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Contato
                                    </th>

                                    <!-- Coluna Cidade/UF -->
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-200 transition select-none"
                                        onclick="applySort('city')">
                                        <div class="flex items-center space-x-1">
                                            <span>Cidade / UF</span>
                                            <span class="text-xs <?php echo $sort === 'city' ? 'text-brand-600 font-bold' : 'text-gray-400'; ?>">
                                                <?php echo $sort === 'city' ? ($order === 'ASC' ? '▲' : '▼') : '↕'; ?>
                                            </span>
                                        </div>
                                    </th>

                                    <!-- Coluna Status -->
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-200 transition select-none"
                                        onclick="applySort('status')">
                                        <div class="flex items-center space-x-1">
                                            <span>Status</span>
                                            <span class="text-xs <?php echo $sort === 'status' ? 'text-brand-600 font-bold' : 'text-gray-400'; ?>">
                                                <?php echo $sort === 'status' ? ($order === 'ASC' ? '▲' : '▼') : '↕'; ?>
                                            </span>
                                        </div>
                                    </th>

                                    <!-- Coluna Ações -->
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Ações
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($clients) > 0): ?>
                                    <?php foreach ($clients as $client): 
                                        $canEdit = canEditClient($client['user_id']);
                                        $isMine = ((int)$client['user_id'] === (int)$user_id);
                                    ?>
                                        <tr class="hover:bg-gray-50 transition border-b border-gray-100">
                                            <!-- 1. Nome / Potencial / Responsável -->
                                            <td class="px-5 py-4 bg-white text-sm">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 mr-3">
                                                        <?php if ($canEdit): ?>
                                                            <form method="POST" class="inline flex items-center">
                                                                <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
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
                                                        <?php else: ?>
                                                            <span class="p-1 inline-block <?php echo !empty($client['is_potential']) ? 'text-amber-500' : 'text-gray-200'; ?>"
                                                                title="<?php echo !empty($client['is_potential']) ? 'Cliente em Potencial' : 'Não marcado como Potencial'; ?>">
                                                                <svg class="w-6 h-6" fill="<?php echo !empty($client['is_potential']) ? 'currentColor' : 'none'; ?>" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                                                </svg>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <div class="flex items-center flex-wrap gap-1.5">
                                                            <a href="client-details.php?id=<?php echo $client['id']; ?>"
                                                                class="text-gray-900 hover:text-brand-600 font-semibold hover:underline inline-block"
                                                                title="Ver detalhes de <?php echo htmlspecialchars($client['name']); ?>">
                                                                <?php echo htmlspecialchars($client['name']); ?>
                                                            </a>
                                                            <?php if (!$isMine): ?>
                                                                <span class="inline-flex items-center justify-center text-red-500 hover:text-red-700 transition shrink-0 select-none" title="Cliente de outro usuário (Responsável: <?php echo htmlspecialchars($client['operator_name'] ?? 'Outro Usuário'); ?>)">
                                                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                                                        <circle cx="9" cy="7" r="4"></circle>
                                                                        <line x1="3" y1="3" x2="21" y2="21" stroke-width="2.5"></line>
                                                                    </svg>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if (!empty($client['farm_name'])): ?>
                                                            <p class="text-xs text-brand-700 font-medium mt-0.5">
                                                                🏡 <?php echo htmlspecialchars($client['farm_name']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- 2. Data de Cadastro -->
                                            <td class="px-5 py-4 bg-white text-sm whitespace-nowrap">
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

                                            <!-- 3. Contato (WhatsApp / Email) -->
                                            <td class="px-5 py-4 bg-white text-sm">
                                                <?php if (!empty($client['phone'])): ?>
                                                    <a href="https://wa.me/+55<?php echo preg_replace('/[^0-9]/', '', $client['phone']); ?>"
                                                        target="_blank"
                                                        class="text-green-600 hover:text-green-800 font-semibold hover:underline flex items-center mb-1"
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
                                                        class="text-blue-600 hover:text-blue-800 text-xs flex items-center hover:underline truncate max-w-xs"
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
                                                    <span class="text-gray-400">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- 4. Localização -->
                                            <td class="px-5 py-4 bg-white text-sm">
                                                <span class="text-gray-700">
                                                    <?php
                                                    $loc = array_filter([$client['city'] ?? '', $client['uf'] ?? '']);
                                                    echo htmlspecialchars(!empty($loc) ? implode(' / ', $loc) : 'N/A');
                                                    ?>
                                                </span>
                                            </td>

                                            <!-- 5. Status Badge -->
                                            <td class="px-5 py-4 bg-white text-sm">
                                                <?php if (($client['status'] ?? '') === 'Embral'): ?>
                                                    <span class="bg-blue-100 text-blue-800 text-xs px-3 py-1 rounded-full font-bold border border-blue-300 inline-block">
                                                        Embral
                                                    </span>
                                                <?php elseif (($client['status'] ?? '') === 'Atendido'): ?>
                                                    <span class="bg-purple-100 text-purple-800 text-xs px-3 py-1 rounded-full font-bold border border-purple-300 inline-block">
                                                        Atendido
                                                    </span>
                                                <?php elseif (($client['status'] ?? '') === 'Inativo'): ?>
                                                    <span class="bg-gray-100 text-gray-700 text-xs px-3 py-1 rounded-full font-bold border border-gray-300 inline-block">
                                                        Inativo
                                                    </span>
                                                <?php elseif (in_array($client['status'] ?? '', ['Novo', 'Pré-cadastro'])): ?>
                                                    <span class="bg-amber-100 text-amber-800 text-xs px-3 py-1 rounded-full font-bold border border-amber-300 inline-block">
                                                        Novo
                                                    </span>
                                                <?php else: ?>
                                                    <span class="bg-green-100 text-green-800 text-xs px-3 py-1 rounded-full font-bold border border-green-300 inline-block">
                                                        Ativo
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- 6. Ações -->
                                            <td class="px-5 py-4 bg-white text-sm whitespace-nowrap">
                                                <div class="flex items-center space-x-2">
                                                    <!-- Mapa -->
                                                    <a href="view-map.php?client_id=<?php echo $client['id']; ?>"
                                                        class="text-emerald-600 hover:text-emerald-800 p-1 hover:bg-emerald-50 rounded transition" title="Ver no Mapa de Clientes">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                                                        </svg>
                                                    </a>

                                                    <!-- Agendar Compromisso -->
                                                    <a href="schedule-add.php?client_id=<?php echo $client['id']; ?>"
                                                        class="text-amber-600 hover:text-amber-800 p-1 hover:bg-amber-50 rounded transition" title="Agendar Compromisso">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                                            </path>
                                                        </svg>
                                                    </a>

                                                    <!-- Ficha PDF -->
                                                    <a href="client-pdf.php?id=<?php echo $client['id']; ?>" target="_blank"
                                                        class="text-red-500 hover:text-red-700 p-1 hover:bg-red-50 rounded transition"
                                                        title="Gerar PDF / Imprimir Ficha">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                                                            </path>
                                                        </svg>
                                                    </a>

                                                    <?php if ($canEdit): ?>
                                                        <!-- Editar -->
                                                        <a href="client-edit.php?id=<?php echo $client['id']; ?>"
                                                            class="text-yellow-600 hover:text-yellow-800 p-1 hover:bg-yellow-50 rounded transition" title="Editar Informações">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                                </path>
                                                            </svg>
                                                        </a>

                                                        <!-- Marcar Atendido (Novo -> Atendido) -->
                                                        <?php if (in_array($client['status'] ?? '', ['Novo', 'Pré-cadastro'])): ?>
                                                            <?php
                                                            $rowPhoneClean = preg_replace('/[^0-9]/', '', $client['phone'] ?? '');
                                                            $rowApprovalMsg = buildClientApprovalWelcomeMessage($client);
                                                            $rowWaApprovalUrl = !empty($rowPhoneClean) ? "https://wa.me/+55" . $rowPhoneClean . "?text=" . rawurlencode($rowApprovalMsg) : '';
                                                            ?>
                                                            <form method="POST" class="inline"
                                                                onsubmit="if('<?php echo addslashes($rowWaApprovalUrl); ?>'){ window.open('<?php echo addslashes($rowWaApprovalUrl); ?>', '_blank'); }">
                                                                <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                                                <input type="hidden" name="attend_client" value="1">
                                                                <button type="submit" class="text-purple-600 hover:text-purple-800 p-1 hover:bg-purple-50 rounded transition cursor-pointer"
                                                                    title="Marcar como Atendido (Alterar status para Atendido e abrir WhatsApp)">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                                    </svg>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>

                                                        <!-- Enviar Embral (Atendido -> Embral) -->
                                                        <?php if (($client['status'] ?? '') === 'Atendido'): ?>
                                                            <form method="POST" class="inline"
                                                                onsubmit="window.open('client-pdf.php?id=<?php echo $client['id']; ?>&sent=embral', '_blank');">
                                                                <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                                                <input type="hidden" name="send_embral" value="1">
                                                                <button type="submit" class="text-blue-600 hover:text-blue-800 p-1 hover:bg-blue-50 rounded transition cursor-pointer"
                                                                    title="Enviar dados para Embral (Alterar status para Embral e abrir Ficha/WhatsApp)">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8">
                                                                        </path>
                                                                    </svg>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>

                                                        <!-- Excluir -->
                                                        <form method="POST" onsubmit="confirmDelete(event)" class="inline">
                                                            <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                                            <input type="hidden" name="delete_client" value="1">
                                                            <button type="submit" class="text-red-500 hover:text-red-700 p-1 hover:bg-red-50 rounded transition cursor-pointer"
                                                                title="Excluir">
                                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                                    </path>
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="px-5 py-12 bg-white text-center text-gray-500">
                                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                                </path>
                                            </svg>
                                            <p class="font-bold text-gray-700 text-base">Nenhum cliente encontrado</p>
                                            <p class="text-xs text-gray-400 mt-1">Tente ajustar o termo de pesquisa ou o filtro de status.</p>
                                            <?php if (!empty($search) || !empty($statusFilterParam) || $scopeFilterParam === 'mine'): ?>
                                                <a href="clients.php" class="inline-block mt-3 bg-brand-500 hover:bg-brand-600 text-white font-bold py-1.5 px-4 rounded-lg text-xs transition">
                                                    Limpar Filtros
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Rodapé de Paginação Server-Side -->
                    <?php if ($totalItems > 0): ?>
                        <div class="px-5 py-4 bg-white border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <!-- Info & Per-Page Selector -->
                            <div class="flex flex-wrap items-center gap-4 text-xs sm:text-sm text-gray-600">
                                <span>
                                    Mostrando <strong class="text-gray-900 font-semibold"><?php echo $pageStart; ?></strong> a
                                    <strong class="text-gray-900 font-semibold"><?php echo $pageEnd; ?></strong> de
                                    <strong class="text-gray-900 font-semibold"><?php echo $totalItems; ?></strong> clientes
                                </span>
                                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                                    <label for="perPageSelect" class="whitespace-nowrap font-medium">Por página:</label>
                                    <select id="perPageSelect" onchange="changePerPage(this.value)"
                                        class="border border-gray-300 rounded-md px-2 py-1 bg-white text-gray-700 text-xs focus:ring-2 focus:ring-brand-500 focus:outline-none shadow-sm cursor-pointer">
                                        <option value="10" <?php echo $perPageParam === '10' ? 'selected' : ''; ?>>10</option>
                                        <option value="25" <?php echo $perPageParam === '25' ? 'selected' : ''; ?>>25</option>
                                        <option value="50" <?php echo $perPageParam === '50' ? 'selected' : ''; ?>>50</option>
                                        <option value="100" <?php echo $perPageParam === '100' ? 'selected' : ''; ?>>100</option>
                                        <option value="all" <?php echo $perPageParam === 'all' ? 'selected' : ''; ?>>Todos</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Page Navigation Buttons -->
                            <?php if ($totalPages > 1 && $perPage !== 'all'): ?>
                                <div class="flex items-center space-x-1">
                                    <!-- Botão Anterior -->
                                    <?php if ($page > 1): ?>
                                        <button type="button" onclick="goToPage(<?php echo $page - 1; ?>)"
                                            class="px-3 py-1.5 text-xs font-semibold rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-100 cursor-pointer shadow-sm transition flex items-center">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                            </svg>
                                            Anterior
                                        </button>
                                    <?php else: ?>
                                        <span class="px-3 py-1.5 text-xs font-semibold rounded-md border border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed flex items-center">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                            </svg>
                                            Anterior
                                        </span>
                                    <?php endif; ?>

                                    <!-- Botões Numéricos -->
                                    <?php
                                    $maxVisible = 5;
                                    $startP = max(1, $page - 2);
                                    $endP = min($totalPages, $startP + $maxVisible - 1);
                                    if ($endP - $startP < $maxVisible - 1) {
                                        $startP = max(1, $endP - $maxVisible + 1);
                                    }

                                    if ($startP > 1) {
                                        echo '<button type="button" onclick="goToPage(1)" class="px-3 py-1.5 text-xs font-bold rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 shadow-sm transition cursor-pointer">1</button>';
                                        if ($startP > 2) {
                                            echo '<span class="px-1.5 py-1 text-xs text-gray-400">...</span>';
                                        }
                                    }

                                    for ($p = $startP; $p <= $endP; $p++) {
                                        $isActive = ($p === $page);
                                        if ($isActive) {
                                            echo '<span class="px-3 py-1.5 text-xs font-bold rounded-md border bg-brand-600 text-white border-brand-600 shadow-sm">' . $p . '</span>';
                                        } else {
                                            echo '<button type="button" onclick="goToPage(' . $p . ')" class="px-3 py-1.5 text-xs font-bold rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 shadow-sm transition cursor-pointer">' . $p . '</button>';
                                        }
                                    }

                                    if ($endP < $totalPages) {
                                        if ($endP < $totalPages - 1) {
                                            echo '<span class="px-1.5 py-1 text-xs text-gray-400">...</span>';
                                        }
                                        echo '<button type="button" onclick="goToPage(' . $totalPages . ')" class="px-3 py-1.5 text-xs font-bold rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-100 shadow-sm transition cursor-pointer">' . $totalPages . '</button>';
                                    }
                                    ?>

                                    <!-- Botão Próximo -->
                                    <?php if ($page < $totalPages): ?>
                                        <button type="button" onclick="goToPage(<?php echo $page + 1; ?>)"
                                            class="px-3 py-1.5 text-xs font-semibold rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-100 cursor-pointer shadow-sm transition flex items-center">
                                            Próximo
                                            <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </button>
                                    <?php else: ?>
                                        <span class="px-3 py-1.5 text-xs font-semibold rounded-md border border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed flex items-center">
                                            Próximo
                                            <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </main>
        </div>
    </div>

    <!-- Scripts de Interação Ultra Rápidos -->
    <script>
        let searchDebounceTimer = null;

        function submitFilterForm() {
            document.getElementById('pageInput').value = '1';
            document.getElementById('clientsFilterForm').submit();
        }

        function clearSearch() {
            document.getElementById('searchInput').value = '';
            submitFilterForm();
        }

        function applySort(col) {
            const form = document.getElementById('clientsFilterForm');
            const currentSort = '<?php echo htmlspecialchars($sort); ?>';
            const currentOrder = '<?php echo htmlspecialchars($order); ?>';

            let newOrder = 'ASC';
            if (col === currentSort) {
                newOrder = (currentOrder === 'ASC') ? 'DESC' : 'ASC';
            } else {
                newOrder = (col === 'date') ? 'DESC' : 'ASC';
            }

            form.querySelector('input[name="sort"]').value = col;
            form.querySelector('input[name="order"]').value = newOrder;
            form.querySelector('input[name="page"]').value = '1';
            form.submit();
        }

        function goToPage(p) {
            const form = document.getElementById('clientsFilterForm');
            form.querySelector('input[name="page"]').value = p;
            form.submit();
        }

        function changePerPage(val) {
            const form = document.getElementById('clientsFilterForm');
            document.getElementById('perPageHidden').value = val;
            form.querySelector('input[name="page"]').value = '1';
            form.submit();
        }

        // Live Search com Debounce de 450ms
        document.getElementById('searchInput')?.addEventListener('input', function() {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                submitFilterForm();
            }, 450);
        });
    </script>
</body>

</html>