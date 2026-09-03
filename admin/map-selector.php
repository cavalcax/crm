<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();
$user_id = $_SESSION['user_id'];
$pageTitle = 'Mapa de Clientes';

// Handle Toggle Potential Lead
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_potential'])) {
    $client_id_to_toggle = $_POST['client_id'];
    $stmt = $pdo->prepare("UPDATE " . TABLE_NAME . "clients SET is_potential = IF(is_potential=1, 0, 1) WHERE id = ? AND user_id = ?");
    $stmt->execute([$client_id_to_toggle, $user_id]);
    $redirectUrl = 'map-selector.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
    header("Location: " . $redirectUrl);
    exit;
}

// Multi-select and scalar filters parsing
$status_filters = isset($_GET['status']) ? (is_array($_GET['status']) ? array_filter(array_map('sanitize', $_GET['status'])) : (trim($_GET['status']) !== '' ? [sanitize($_GET['status'])] : [])) : [];
$uf_filters = isset($_GET['uf']) ? (is_array($_GET['uf']) ? array_filter(array_map('sanitize', $_GET['uf'])) : (trim($_GET['uf']) !== '' ? [sanitize($_GET['uf'])] : [])) : [];
$breed_filters = isset($_GET['breed']) ? (is_array($_GET['breed']) ? array_filter(array_map('sanitize', $_GET['breed'])) : (trim($_GET['breed']) !== '' ? [sanitize($_GET['breed'])] : [])) : [];
$animal_cat_filters = isset($_GET['animal_categories']) ? (is_array($_GET['animal_categories']) ? array_filter(array_map('sanitize', $_GET['animal_categories'])) : (trim($_GET['animal_categories']) !== '' ? [sanitize($_GET['animal_categories'])] : [])) : [];
$prod_system_filters = isset($_GET['production_system']) ? (is_array($_GET['production_system']) ? array_filter(array_map('sanitize', $_GET['production_system'])) : (trim($_GET['production_system']) !== '' ? [sanitize($_GET['production_system'])] : [])) : [];
$payment_filters = isset($_GET['payment']) ? (is_array($_GET['payment']) ? array_filter(array_map('sanitize', $_GET['payment'])) : (trim($_GET['payment']) !== '' ? [sanitize($_GET['payment'])] : [])) : [];
$category_id_filters = isset($_GET['category_id']) ? (is_array($_GET['category_id']) ? array_filter(array_map('intval', $_GET['category_id'])) : (!empty($_GET['category_id']) ? [intval($_GET['category_id'])] : [])) : [];

$potential_filter = isset($_GET['is_potential']) ? sanitize($_GET['is_potential']) : '';
$type_filter = isset($_GET['type']) ? sanitize($_GET['type']) : 'all'; // 'buy' or 'sell'
$producer_filter = isset($_GET['producer']) ? sanitize($_GET['producer']) : '';
$search_filter = isset($_GET['q']) ? sanitize($_GET['q']) : '';

$raw_milk_min = sanitize($_GET['milk_min'] ?? '');
$raw_milk_max = sanitize($_GET['milk_max'] ?? '');
$milk_min_num = (int)preg_replace('/\D/', '', $raw_milk_min);
$milk_max_num = (int)preg_replace('/\D/', '', $raw_milk_max);

// Build dynamic query for clients
$query = "
    SELECT DISTINCT c.*, u.name as operator_name 
    FROM " . TABLE_NAME . "clients c 
    LEFT JOIN " . TABLE_NAME . "users u ON c.user_id = u.id
    LEFT JOIN " . TABLE_NAME . "intentions i ON i.client_id = c.id AND (i.status = 'active' OR i.status IS NULL)
    WHERE c.user_id = :user_id
";

$params = [':user_id' => $user_id];

// Search filter (text)
if (!empty($search_filter)) {
    $query .= " AND (c.name LIKE :search OR c.farm_name LIKE :search OR c.phone LIKE :search OR c.email LIKE :search OR c.city LIKE :search)";
    $params[':search'] = '%' . $search_filter . '%';
}

// Status multi-filter
if (!empty($status_filters)) {
    $statusConditions = [];
    $sIdx = 0;
    foreach ($status_filters as $st) {
        if ($st === 'Novo') {
            $statusConditions[] = "(c.status = 'Novo' OR c.status = 'Pré-cadastro')";
        } else {
            $pKey = ":status_" . $sIdx++;
            $statusConditions[] = "c.status = " . $pKey;
            $params[$pKey] = $st;
        }
    }
    if (!empty($statusConditions)) {
        $query .= " AND (" . implode(" OR ", $statusConditions) . ")";
    }
}

// Potential filter
if ($potential_filter !== '') {
    $query .= " AND c.is_potential = :is_potential";
    $params[':is_potential'] = intval($potential_filter);
}

// UF multi-filter
if (!empty($uf_filters)) {
    $ufPlaceholders = [];
    $ufIdx = 0;
    foreach ($uf_filters as $uf) {
        $pKey = ":uf_" . $ufIdx++;
        $ufPlaceholders[] = $pKey;
        $params[$pKey] = $uf;
    }
    $query .= " AND c.uf IN (" . implode(", ", $ufPlaceholders) . ")";
}

// Breed multi-filter
if (!empty($breed_filters)) {
    $breedConditions = [];
    $bIdx = 0;
    foreach ($breed_filters as $b) {
        $pKey = ":breed_" . $bIdx++;
        $breedConditions[] = "c.breed_interests LIKE " . $pKey;
        $params[$pKey] = '%' . $b . '%';
    }
    $query .= " AND (" . implode(" OR ", $breedConditions) . ")";
}

// Animal Categories multi-filter
if (!empty($animal_cat_filters)) {
    $catConditions = [];
    $acIdx = 0;
    foreach ($animal_cat_filters as $ac) {
        $pKey = ":anim_cat_" . $acIdx++;
        $catConditions[] = "c.animal_categories LIKE " . $pKey;
        $params[$pKey] = '%' . $ac . '%';
    }
    $query .= " AND (" . implode(" OR ", $catConditions) . ")";
}

// Production System multi-filter
if (!empty($prod_system_filters)) {
    $psConditions = [];
    $psIdx = 0;
    foreach ($prod_system_filters as $ps) {
        $pKey = ":prod_sys_" . $psIdx++;
        if ($ps === 'Outro') {
            $psConditions[] = "(c.production_system LIKE 'Outro%' OR c.production_system = 'Outro')";
        } else {
            $psConditions[] = "c.production_system LIKE " . $pKey;
            $params[$pKey] = '%' . $ps . '%';
        }
    }
    $query .= " AND (" . implode(" OR ", $psConditions) . ")";
}

// Payment Condition multi-filter
if (!empty($payment_filters)) {
    $payPlaceholders = [];
    $payIdx = 0;
    foreach ($payment_filters as $pay) {
        $pKey = ":pay_" . $payIdx++;
        $payPlaceholders[] = $pKey;
        $params[$pKey] = $pay;
    }
    $query .= " AND c.payment_condition IN (" . implode(", ", $payPlaceholders) . ")";
}

// Milk Producer filter
if ($producer_filter !== '') {
    $query .= " AND c.is_milk_producer = :producer";
    $params[':producer'] = $producer_filter;
}

// Monthly Milk Production Range filter (when both are 0 / empty, it does not filter)
if ($milk_min_num > 0 && $milk_max_num > 0 && $milk_min_num > $milk_max_num) {
    $temp = $milk_min_num;
    $milk_min_num = $milk_max_num;
    $milk_max_num = $temp;
}

if ($milk_min_num > 0 || $milk_max_num > 0) {
    if ($milk_min_num > 0 && $milk_max_num > 0) {
        $query .= " AND CAST(REPLACE(COALESCE(c.milk_production_range, '0'), '.', '') AS UNSIGNED) BETWEEN :milk_min AND :milk_max";
        $params[':milk_min'] = $milk_min_num;
        $params[':milk_max'] = $milk_max_num;
    } elseif ($milk_min_num > 0) {
        $query .= " AND CAST(REPLACE(COALESCE(c.milk_production_range, '0'), '.', '') AS UNSIGNED) >= :milk_min";
        $params[':milk_min'] = $milk_min_num;
    } elseif ($milk_max_num > 0) {
        $query .= " AND CAST(REPLACE(COALESCE(c.milk_production_range, '0'), '.', '') AS UNSIGNED) <= :milk_max AND CAST(REPLACE(COALESCE(c.milk_production_range, '0'), '.', '') AS UNSIGNED) > 0";
        $params[':milk_max'] = $milk_max_num;
    }
}

// Intention Category multi-filter
if (!empty($category_id_filters)) {
    $catIdPlaceholders = [];
    $cidIdx = 0;
    foreach ($category_id_filters as $cid) {
        $pKey = ":cat_id_" . $cidIdx++;
        $catIdPlaceholders[] = $pKey;
        $params[$pKey] = intval($cid);
    }
    $query .= " AND i.category_id IN (" . implode(", ", $catIdPlaceholders) . ") AND (i.status = 'active' OR i.status IS NULL)";
}

// Intention Type filter (buy/sell)
if ($type_filter !== 'all' && !empty($type_filter)) {
    $query .= " AND i.type = :type AND (i.status = 'active' OR i.status IS NULL)";
    $params[':type'] = $type_filter;
}

$query .= " ORDER BY c.is_potential DESC, c.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Fetch Future Auctions for Map Selection with valid location coordinates (visible to all users)
$stmtAuc = $pdo->prepare("
    SELECT s.*, c.name as client_name, u.name as operator_name 
    FROM " . TABLE_NAME . "schedule s 
    LEFT JOIN " . TABLE_NAME . "clients c ON s.client_id = c.id 
    LEFT JOIN " . TABLE_NAME . "users u ON s.user_id = u.id
    WHERE s.type = 'auction' 
      AND s.start_time >= NOW()
      AND s.latitude IS NOT NULL 
      AND s.longitude IS NOT NULL
      AND s.latitude != 0 
      AND s.longitude != 0
    ORDER BY s.start_time ASC
");
$stmtAuc->execute();
$auctions = $stmtAuc->fetchAll();

// Fetch Options for multi-selects
$states = getBrazilianStates();
$allBreeds = getStandardBreeds();
$allAnimalCats = getStandardAnimalCategories();
$allProdSystems = getStandardProductionSystems();
$allPayments = getStandardPaymentConditions();
$allStatuses = getStandardClientStatuses();

// Format states as 'UF - Nome'
$ufOptions = [];
foreach ($states as $code => $stateName) {
    $ufOptions[$code] = $code . ' - ' . $stateName;
}

// Fetch user categories for intentions
$stmt = $pdo->prepare("SELECT * FROM " . TABLE_NAME . "categories WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll();
$categoryOptions = [];
foreach ($categories as $c) {
    $categoryOptions[$c['id']] = $c['name'];
}

$isMilkFilterActive = ($milk_min_num > 0 || $milk_max_num > 0);

// Active filters count
$hasActiveFilters = !empty($status_filters) || !empty($uf_filters) || !empty($breed_filters) || 
                     !empty($animal_cat_filters) || !empty($prod_system_filters) || !empty($payment_filters) || 
                     !empty($category_id_filters) || $potential_filter !== '' || ($type_filter !== 'all' && !empty($type_filter)) || 
                     $producer_filter !== '' || !empty($search_filter) || $isMilkFilterActive;

$activeFilterCount = count($status_filters) + count($uf_filters) + count($breed_filters) + 
                     count($animal_cat_filters) + count($prod_system_filters) + count($payment_filters) + 
                     count($category_id_filters) + ($potential_filter !== '' ? 1 : 0) + 
                     (($type_filter !== 'all' && !empty($type_filter)) ? 1 : 0) + 
                     ($producer_filter !== '' ? 1 : 0) + (!empty($search_filter) ? 1 : 0) +
                     ($isMilkFilterActive ? 1 : 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de Clientes e Leilões - CRM Vitor Müller</title>
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
                            950: '#170F03',
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
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-brand-50 p-4 sm:p-6 pb-28">

                <!-- Header Title -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-brand-900">Mapa de Clientes e Leilões</h1>
                        <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Filtre, selecione os clientes e visualize no mapa georreferenciado</p>
                    </div>
                    <?php if ($hasActiveFilters): ?>
                        <a href="map-selector.php"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-200 hover:bg-gray-300 text-gray-700 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Limpar Filtros (<?php echo $activeFilterCount; ?>)
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Complete Filter Section (Accordion / Card) -->
                <div class="mb-6 bg-white rounded-xl shadow-md border border-brand-100 relative z-20 overflow-visible">
                    <button type="button" onclick="toggleAccordion('filterAccordionContent', 'filterChevron')"
                        class="w-full bg-gradient-to-r from-brand-700 to-brand-800 rounded-t-xl px-5 py-3.5 flex items-center justify-between text-white hover:from-brand-800 hover:to-brand-900 transition text-left cursor-pointer select-none">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">🔍</span>
                            <h2 class="text-sm sm:text-base font-bold tracking-wide">Filtros de Clientes</h2>
                            <?php if ($activeFilterCount > 0): ?>
                                <span class="bg-brand-400 text-brand-900 text-xs font-extrabold px-2.5 py-0.5 rounded-full ml-1">
                                    <?php echo $activeFilterCount; ?> ativo(s)
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="hidden sm:inline text-xs text-brand-100 font-medium">Clique para expandir/recolher filtros</span>
                            <svg id="filterChevron" class="w-5 h-5 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>

                    <div id="filterAccordionContent" class="p-5 sm:p-6 border-t border-brand-100 overflow-visible">
                        <form method="GET" action="map-selector.php" id="mapFilterForm" class="space-y-4">
                            <!-- Grid with all filters organized in 4 columns -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <!-- 1. Busca Geral (Ocupa 2 colunas / 50% da largura) -->
                                <div class="sm:col-span-2 lg:col-span-2">
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Busca Geral (Nome, Fazenda, Telefone, Cidade...)</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                            </svg>
                                        </span>
                                        <input type="text" name="q" value="<?php echo htmlspecialchars($search_filter); ?>"
                                            class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 shadow-sm"
                                            placeholder="Ex: João, Fazenda Boa Vista, (19) 99999-9999, Campinas...">
                                    </div>
                                </div>

                                <!-- 2. Status do Cliente (Multi-select) -->
                                <?php echo renderMultiSelect('status', 'Status do Cliente', $allStatuses, $status_filters, 'Todos Status'); ?>

                                <!-- 3. Cliente em Potencial (Single select) -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Cliente em Potencial</label>
                                    <select name="is_potential" class="w-full border border-gray-300 p-2 rounded-lg text-xs sm:text-sm shadow-sm bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                                        <option value="">Todos</option>
                                        <option value="1" <?php echo $potential_filter === '1' ? 'selected' : ''; ?>>⭐ Somente em Potencial</option>
                                        <option value="0" <?php echo $potential_filter === '0' ? 'selected' : ''; ?>>Outros</option>
                                    </select>
                                </div>

                                <!-- 4. UF / Estados (Multi-select with Search) -->
                                <?php echo renderMultiSelect('uf', 'UF (Estado)', $ufOptions, $uf_filters, 'Todos os Estados', true); ?>

                                <!-- 5. Raças / Máquinas (Multi-select) -->
                                <?php echo renderMultiSelect('breed', 'Raça / Máquinas', $allBreeds, $breed_filters, 'Todas as Raças'); ?>

                                <!-- 6. Categorias de Animais (Multi-select with Search) -->
                                <?php echo renderMultiSelect('animal_categories', 'Categoria de Animais', $allAnimalCats, $animal_cat_filters, 'Todas as Categorias', true); ?>

                                <!-- 7. Tipo / Sistema de Produção (Multi-select) -->
                                <?php echo renderMultiSelect('production_system', 'Tipo de Produção', $allProdSystems, $prod_system_filters, 'Todos os Tipos'); ?>

                                <!-- 8. Condição de Pagamento (Multi-select) -->
                                <?php echo renderMultiSelect('payment', 'Condição de Pagamento', $allPayments, $payment_filters, 'Todas as Condições'); ?>

                                <!-- 9. Produtor de Leite (Single select) -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Produtor de Leite?</label>
                                    <select name="producer" class="w-full border border-gray-300 p-2 rounded-lg text-xs sm:text-sm shadow-sm bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                                        <option value="">Todos</option>
                                        <option value="Sim" <?php echo $producer_filter === 'Sim' ? 'selected' : ''; ?>>Sim</option>
                                        <option value="Não" <?php echo $producer_filter === 'Não' ? 'selected' : ''; ?>>Não</option>
                                    </select>
                                </div>

                                <!-- 10. Leite Mensal Inicial (L/mês) -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Leite Mensal Inicial</label>
                                    <div class="relative">
                                        <input type="text" inputmode="numeric" name="milk_min" id="milkMinInput"
                                            value="<?php echo htmlspecialchars(!empty($raw_milk_min) && $raw_milk_min !== '0.000' ? $raw_milk_min : ''); ?>"
                                            placeholder="0.000"
                                            class="w-full border border-gray-300 p-2 pr-16 rounded-lg text-xs sm:text-sm shadow-sm bg-white font-semibold text-gray-800 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 milk-range-mask">
                                        <span class="absolute inset-y-0 right-0 flex items-center pr-2.5 pointer-events-none text-[10px] font-bold text-gray-400 uppercase">
                                            L/mês
                                        </span>
                                    </div>
                                </div>

                                <!-- 11. Leite Mensal Final (L/mês) -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Leite Mensal Final</label>
                                    <div class="relative">
                                        <input type="text" inputmode="numeric" name="milk_max" id="milkMaxInput"
                                            value="<?php echo htmlspecialchars(!empty($raw_milk_max) && $raw_milk_max !== '0.000' ? $raw_milk_max : ''); ?>"
                                            placeholder="0.000"
                                            class="w-full border border-gray-300 p-2 pr-16 rounded-lg text-xs sm:text-sm shadow-sm bg-white font-semibold text-gray-800 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 milk-range-mask">
                                        <span class="absolute inset-y-0 right-0 flex items-center pr-2.5 pointer-events-none text-[10px] font-bold text-gray-400 uppercase">
                                            L/mês
                                        </span>
                                    </div>
                                </div>

                                <!-- 12. Categoria de Intenção (Multi-select) -->
                                <?php echo renderMultiSelect('category_id', 'Categoria de Intenção', $categoryOptions, $category_id_filters, 'Todas as Intenções'); ?>

                                <!-- 13. Tipo de Intenção (Single select) -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Tipo de Intenção</label>
                                    <select name="type" class="w-full border border-gray-300 p-2 rounded-lg text-xs sm:text-sm shadow-sm bg-white focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                                        <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>Todos</option>
                                        <option value="buy" <?php echo $type_filter == 'buy' ? 'selected' : ''; ?>>🛒 Compra</option>
                                        <option value="sell" <?php echo $type_filter == 'sell' ? 'selected' : ''; ?>>💰 Venda</option>
                                    </select>
                                </div>

                                <!-- 14. Botões de Ação (Ocupam 2 colunas / preenchem a linha) -->
                                <div class="sm:col-span-2 lg:col-span-2 flex items-end gap-2 pt-1">
                                    <button type="submit"
                                        class="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-bold py-2 px-5 rounded-lg shadow-md transition transform hover:-translate-y-0.5 active:translate-y-0 text-xs sm:text-sm flex items-center justify-center gap-2 cursor-pointer h-[38px]">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                                        </svg>
                                        Aplicar Filtros
                                    </button>
                                    <?php if ($hasActiveFilters): ?>
                                        <a href="map-selector.php"
                                            class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-lg transition text-xs sm:text-sm flex items-center justify-center h-[38px]">
                                            Limpar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Form to generate map view with selected clients and auctions -->
                <form action="view-map.php" method="POST" id="mapForm">

                    <?php if (!empty($auctions)): ?>
                        <!-- Future Auctions Selection Section with Accordion -->
                        <div class="mb-6 bg-white shadow-md rounded-xl overflow-hidden border border-red-200">
                            <button type="button" onclick="toggleAccordion('auctionsAccordionContent', 'auctionsChevron')"
                                class="w-full bg-gradient-to-r from-red-700 to-red-800 px-5 py-3.5 flex items-center justify-between text-white hover:from-red-800 hover:to-red-900 transition text-left cursor-pointer select-none">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">🔨</span>
                                    <h2 class="text-sm sm:text-base font-bold tracking-wide">Leilões Programados (Futuros com Localização)</h2>
                                    <span class="bg-white/20 text-white text-xs font-extrabold px-2.5 py-0.5 rounded-full ml-1">
                                        <?php echo count($auctions); ?>
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="hidden sm:inline text-xs text-red-100 font-medium">Marcados com pin vermelho no mapa</span>
                                    <svg id="auctionsChevron" class="w-5 h-5 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </button>

                            <div id="auctionsAccordionContent" class="overflow-x-auto">
                                <table class="min-w-full leading-normal">
                                    <thead>
                                        <tr class="bg-red-50/70 text-red-900 border-b border-red-100">
                                            <th class="px-5 py-3 text-center w-12">
                                                <input type="checkbox" id="selectAllAuctions"
                                                    class="form-checkbox h-5 w-5 text-red-600 rounded focus:ring-red-500 border-gray-300 cursor-pointer"
                                                    title="Selecionar todos os leilões com coordenadas">
                                            </th>
                                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider">Título do Leilão</th>
                                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider">Data / Hora</th>
                                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider">Cidade / UF</th>
                                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider">Cliente Vinculado</th>
                                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider">Mapa</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php foreach ($auctions as $auc): 
                                            $aucDate = new DateTime($auc['start_time']);
                                            $aucLoc = array_filter([$auc['city'] ?? '', $auc['uf'] ?? '']);
                                        ?>
                                            <tr class="hover:bg-red-50/30 transition">
                                                <td class="px-5 py-4 text-center">
                                                    <input type="checkbox" name="selected_auctions[]" value="<?php echo $auc['id']; ?>"
                                                        class="auction-checkbox form-checkbox h-5 w-5 text-red-600 rounded focus:ring-red-500 border-gray-300 cursor-pointer">
                                                </td>
                                                <td class="px-5 py-4 text-sm">
                                                    <p class="font-bold text-gray-900"><?php echo htmlspecialchars($auc['title']); ?></p>
                                                    <?php if (!empty($auc['operator_name'])): ?>
                                                        <span class="text-[10px] bg-amber-100 text-amber-800 px-2 py-0.5 rounded font-medium border border-amber-200 inline-block mt-0.5">
                                                            👤 <?php echo htmlspecialchars($auc['operator_name']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($auc['address'])): ?>
                                                        <p class="text-xs text-gray-500 mt-0.5"><?php echo htmlspecialchars($auc['address']); ?></p>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-5 py-4 text-sm text-gray-700 font-semibold whitespace-nowrap">
                                                    📅 <?php echo $aucDate->format('d/m/Y'); ?><br>
                                                    <span class="text-xs text-gray-500 font-normal">⏰ <?php echo $aucDate->format('H:i'); ?></span>
                                                </td>
                                                <td class="px-5 py-4 text-sm text-gray-700">
                                                    <?php echo htmlspecialchars(!empty($aucLoc) ? implode(' / ', $aucLoc) : '-'); ?>
                                                </td>
                                                <td class="px-5 py-4 text-sm text-gray-700">
                                                    <?php echo htmlspecialchars($auc['client_name'] ?? '-'); ?>
                                                </td>
                                                <td class="px-5 py-4 text-sm text-center whitespace-nowrap">
                                                    <a href="view-map.php?auction_id=<?php echo $auc['id']; ?>"
                                                        class="text-emerald-600 hover:text-emerald-800 p-1 hover:bg-emerald-50 rounded transition inline-flex items-center"
                                                        title="Ver no Mapa">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                                                        </svg>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Clients Section with Accordion -->
                    <div class="mb-12 bg-white shadow-md rounded-xl overflow-hidden border border-brand-200">
                        <button type="button" onclick="toggleAccordion('clientsAccordionContent', 'clientsChevron')"
                            class="w-full bg-gradient-to-r from-brand-800 to-brand-900 px-5 py-3.5 flex items-center justify-between text-white hover:from-brand-900 hover:to-brand-950 transition text-left cursor-pointer select-none">
                            <div class="flex items-center gap-2">
                                <span class="text-lg">👥</span>
                                <h2 class="text-sm sm:text-base font-bold tracking-wide">Clientes Encontrados</h2>
                                <span class="bg-white/20 text-white text-xs font-extrabold px-2.5 py-0.5 rounded-full ml-1" id="clientCountBadge">
                                    <?php echo count($clients); ?>
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="hidden sm:inline text-xs text-brand-100 font-medium">Marque os clientes para exibir no mapa</span>
                                <svg id="clientsChevron" class="w-5 h-5 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </button>

                        <div id="clientsAccordionContent" class="p-5">
                            <!-- Instant Filter Bar for loaded table rows -->
                            <div class="mb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-gray-50 p-3 rounded-lg border border-gray-200">
                                <div class="relative w-full sm:w-80">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </span>
                                    <input type="text" id="searchInput"
                                        class="w-full pl-9 pr-3 py-1.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-xs bg-white text-xs sm:text-sm"
                                        placeholder="Filtrar rapidamente nesta lista...">
                                </div>
                                <div class="flex items-center gap-2 text-xs font-semibold text-gray-600">
                                    <span>Legenda:</span>
                                    <span class="inline-flex items-center gap-1 text-amber-600 font-medium">⚠️ Sem coordenadas</span>
                                    <span class="inline-flex items-center gap-1 text-amber-500 font-medium">⭐ Potencial</span>
                                </div>
                            </div>

                            <div class="overflow-x-auto rounded-lg border border-gray-200">
                                <table class="min-w-full leading-normal" id="clientsTable">
                                    <thead>
                                        <tr class="bg-gray-100/90 text-gray-700 border-b border-gray-200">
                                            <th class="px-5 py-3 text-center w-12">
                                                <input type="checkbox" id="selectAll"
                                                    class="form-checkbox h-5 w-5 text-brand-600 rounded focus:ring-brand-500 border-gray-300 cursor-pointer"
                                                    title="Selecionar todos os clientes visíveis com coordenadas">
                                            </th>
                                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider">Cliente</th>
                                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider">Cidade / UF</th>
                                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider">Status</th>
                                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider">Perfil & Raças</th>
                                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider">Categorias & Produção</th>
                                            <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider">Mapa</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                    <?php if (count($clients) > 0): ?>
                                        <?php foreach ($clients as $client): ?>
                                            <?php
                                            $hasCoords = !empty($client['latitude']) && !empty($client['longitude']);
                                            $isPotential = !empty($client['is_potential']) && $client['is_potential'] == 1;
                                            ?>
                                            <tr class="client-row hover:bg-brand-50/30 transition <?php echo !$hasCoords ? 'bg-gray-50/70 opacity-75' : ''; ?>">
                                                <!-- Checkbox Column -->
                                                <td class="px-5 py-4 text-center">
                                                    <input type="checkbox" name="client_ids[]"
                                                        value="<?php echo $client['id']; ?>"
                                                        class="client-checkbox form-checkbox h-5 w-5 text-brand-600 rounded focus:ring-brand-500 border-gray-300 cursor-pointer"
                                                        <?php echo !$hasCoords ? 'disabled title="Cliente sem coordenadas para o mapa"' : ''; ?>>
                                                </td>

                                                <!-- Nome Column with Star and Farm -->
                                                <td class="px-5 py-4 text-sm">
                                                    <div class="flex items-start">
                                                        <!-- Potential Star Toggle Button -->
                                                        <button type="submit" form="potentialForm_<?php echo $client['id']; ?>"
                                                            class="mr-2 text-lg focus:outline-none transition transform hover:scale-125 cursor-pointer mt-0.5"
                                                            title="<?php echo $isPotential ? 'Remover dos clientes em potencial' : 'Marcar como cliente em potencial'; ?>">
                                                            <?php if ($isPotential): ?>
                                                                <span class="text-amber-500">⭐</span>
                                                            <?php else: ?>
                                                                <span class="text-gray-300 hover:text-amber-400 grayscale opacity-40 hover:opacity-100">⭐</span>
                                                            <?php endif; ?>
                                                        </button>

                                                        <div>
                                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                                <a href="client-details.php?id=<?php echo $client['id']; ?>" target="_blank"
                                                                   class="text-gray-900 font-bold hover:text-brand-600 hover:underline client-name">
                                                                    <?php echo htmlspecialchars($client['name']); ?>
                                                                </a>
                                                                <?php if ((int)($client['user_id'] ?? 0) !== (int)$user_id): ?>
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
                                                                <p class="text-xs text-brand-900 font-medium client-farm mt-0.5">
                                                                    🏡 <?php echo htmlspecialchars($client['farm_name']); ?>
                                                                </p>
                                                            <?php endif; ?>
                                                            <?php if (!$hasCoords): ?>
                                                                <span class="text-[11px] text-amber-600 block mt-0.5 font-medium">⚠️ Sem coordenadas no cadastro</span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($client['phone'])): ?>
                                                                <a href="https://wa.me/+55<?php echo preg_replace('/[^0-9]/', '', $client['phone']); ?>"
                                                                    target="_blank"
                                                                    class="text-green-600 hover:text-green-800 font-semibold hover:underline inline-flex items-center client-phone mt-1 text-xs"
                                                                    title="Abrir conversa no WhatsApp">
                                                                    <svg class="w-3.5 h-3.5 mr-1 fill-current text-green-500 flex-shrink-0"
                                                                        viewBox="0 0 24 24">
                                                                        <path
                                                                            d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z" />
                                                                    </svg>
                                                                    <span><?php echo htmlspecialchars(formatPhone($client['phone'])); ?></span>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>

                                                <!-- Cidade / UF Column -->
                                                <td class="px-5 py-4 text-sm whitespace-nowrap">
                                                    <span class="client-location text-gray-700 font-medium">
                                                        <?php
                                                        $loc = array_filter([$client['city'] ?? '', $client['uf'] ?? '']);
                                                        echo htmlspecialchars(!empty($loc) ? implode(' / ', $loc) : 'N/A');
                                                        ?>
                                                    </span>
                                                </td>

                                                <!-- Status Column -->
                                                <td class="px-5 py-4 text-sm whitespace-nowrap">
                                                    <span class="client-status">
                                                        <?php if (($client['status'] ?? '') === 'Embral'): ?>
                                                            <span class="bg-blue-100 text-blue-800 text-xs px-2.5 py-1 rounded-full font-bold border border-blue-300 inline-block">
                                                                Embral
                                                            </span>
                                                        <?php elseif (($client['status'] ?? '') === 'Atendido'): ?>
                                                            <span class="bg-purple-100 text-purple-800 text-xs px-2.5 py-1 rounded-full font-bold border border-purple-300 inline-block">
                                                                Atendido
                                                            </span>
                                                        <?php elseif (($client['status'] ?? '') === 'Inativo'): ?>
                                                            <span class="bg-gray-100 text-gray-700 text-xs px-2.5 py-1 rounded-full font-bold border border-gray-300 inline-block">
                                                                Inativo
                                                            </span>
                                                        <?php elseif (in_array($client['status'] ?? '', ['Novo', 'Pré-cadastro'])): ?>
                                                            <span class="bg-amber-100 text-amber-800 text-xs px-2.5 py-1 rounded-full font-bold border border-amber-300 inline-block">
                                                                Novo
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="bg-green-100 text-green-800 text-xs px-2.5 py-1 rounded-full font-bold border border-green-300 inline-block">
                                                                Ativo
                                                            </span>
                                                        <?php endif; ?>
                                                    </span>
                                                </td>

                                                <!-- Perfil & Raças -->
                                                <td class="px-5 py-4 text-sm">
                                                    <div class="font-semibold text-gray-800 text-xs">
                                                        🐄 <?php echo htmlspecialchars($client['breed_interests'] ?: 'Sem raças'); ?>
                                                    </div>
                                                    <div class="text-gray-500 text-xs mt-1">
                                                        💳 <?php echo htmlspecialchars($client['payment_condition'] ?: '-'); ?>
                                                    </div>
                                                </td>

                                                <!-- Categorias & Sistema de Produção -->
                                                <td class="px-5 py-4 text-sm max-w-xs">
                                                    <?php if (!empty($client['animal_categories'])): ?>
                                                        <div class="text-xs text-gray-800">
                                                            <span class="font-bold text-brand-800 text-[11px] block">Categorias:</span>
                                                            <?php echo htmlspecialchars($client['animal_categories']); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-xs block">Sem categorias</span>
                                                    <?php endif; ?>

                                                    <?php if (!empty($client['production_system'])): ?>
                                                        <div class="text-xs text-gray-600 mt-1">
                                                            <span class="font-bold text-gray-700 text-[11px]">Sistema:</span>
                                                            <?php echo htmlspecialchars($client['production_system']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- Mapa Column -->
                                                <td class="px-5 py-4 text-sm whitespace-nowrap text-center">
                                                    <?php if ($hasCoords): ?>
                                                        <a href="view-map.php?client_id=<?php echo $client['id']; ?>"
                                                            class="text-emerald-600 hover:text-emerald-800 p-1 hover:bg-emerald-50 rounded transition inline-flex items-center"
                                                            title="Ver no Mapa de Clientes">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                                                            </svg>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-xs">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="px-5 py-10 text-sm text-center text-gray-500 bg-white">
                                                Nenhum cliente encontrado para os filtros selecionados.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div id="noResults" class="hidden px-5 py-8 bg-white text-sm text-center text-gray-500">
                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <p class="font-medium text-gray-600">Nenhum cliente visível com os termos digitados.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Fixed Footer Action -->
                    <div class="fixed bottom-0 right-0 left-0 md:left-64 bg-white border-t border-gray-200 p-4 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] flex justify-between items-center z-10">
                        <span class="text-gray-700 font-semibold text-sm" id="selectionCount">0 itens selecionados</span>
                        <button type="submit"
                            class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-2.5 px-6 rounded-lg shadow-md transition transform hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none text-sm flex items-center cursor-pointer"
                            id="generateBtn" disabled>
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7">
                                </path>
                            </svg>
                            Visualizar no Mapa
                        </button>
                    </div>
                </form>

                <!-- Hidden forms for potential toggling without nesting -->
                <?php foreach ($clients as $client): ?>
                    <form id="potentialForm_<?php echo $client['id']; ?>" method="POST" class="hidden">
                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                        <input type="hidden" name="toggle_potential" value="1">
                    </form>
                <?php endforeach; ?>

            </main>
        </div>
    </div>

    <!-- Filter, Accordion & Selection Script -->
    <script>
        function toggleAccordion(contentId, chevronId) {
            const content = document.getElementById(contentId);
            const chevron = document.getElementById(chevronId);
            if (!content) return;

            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                if (chevron) chevron.classList.remove('-rotate-90');
            } else {
                content.classList.add('hidden');
                if (chevron) chevron.classList.add('-rotate-90');
            }
        }

        function normalizeText(str) {
            if (!str) return '';
            return str.toString()
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .trim();
        }

        function filterRows() {
            const searchInput = document.getElementById('searchInput');
            const searchRaw = searchInput ? searchInput.value.trim() : '';
            const searchNorm = normalizeText(searchRaw);
            const rows = document.querySelectorAll('.client-row');
            let hasVisible = false;

            rows.forEach(row => {
                const text = normalizeText(row.textContent);
                const matches = !searchNorm || text.includes(searchNorm);

                if (matches) {
                    row.style.display = '';
                    hasVisible = true;
                } else {
                    row.style.display = 'none';
                }
            });

            const noResults = document.getElementById('noResults');
            if (noResults) {
                if (!hasVisible && rows.length > 0) {
                    noResults.classList.remove('hidden');
                } else {
                    noResults.classList.add('hidden');
                }
            }

            updateSelection();
        }

        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', filterRows);
        }

        // Selection Logic
        const selectAll = document.getElementById('selectAll');
        const selectAllAuctions = document.getElementById('selectAllAuctions');
        const clientCheckboxes = document.querySelectorAll('.client-checkbox:not(:disabled)');
        const auctionCheckboxes = document.querySelectorAll('.auction-checkbox:not(:disabled)');
        const generateBtn = document.getElementById('generateBtn');
        const selectionCount = document.getElementById('selectionCount');

        function updateSelection() {
            const checkedClients = document.querySelectorAll('.client-checkbox:checked').length;
            const checkedAuctions = document.querySelectorAll('.auction-checkbox:checked').length;
            const totalChecked = checkedClients + checkedAuctions;

            if (selectionCount) {
                if (checkedClients > 0 && checkedAuctions > 0) {
                    selectionCount.innerText = `${checkedClients} cliente${checkedClients !== 1 ? 's' : ''} e ${checkedAuctions} leilão${checkedAuctions !== 1 ? 'ões' : ''} selecionados`;
                } else if (checkedAuctions > 0) {
                    selectionCount.innerText = `${checkedAuctions} leilão${checkedAuctions !== 1 ? 'ões' : ''} selecionado${checkedAuctions !== 1 ? 's' : ''}`;
                } else {
                    selectionCount.innerText = `${checkedClients} cliente${checkedClients !== 1 ? 's' : ''} selecionado${checkedClients !== 1 ? 's' : ''}`;
                }
            }

            if (generateBtn) {
                generateBtn.disabled = totalChecked === 0;
            }

            // Visible enabled client checkboxes
            const visibleClientCheckboxes = Array.from(clientCheckboxes).filter(cb => {
                const row = cb.closest('.client-row');
                return row && row.style.display !== 'none';
            });
            const visibleClientChecked = visibleClientCheckboxes.filter(cb => cb.checked);

            if (selectAll) {
                if (visibleClientCheckboxes.length > 0 && visibleClientChecked.length === visibleClientCheckboxes.length) {
                    selectAll.checked = true;
                    selectAll.indeterminate = false;
                } else if (visibleClientChecked.length > 0) {
                    selectAll.checked = false;
                    selectAll.indeterminate = true;
                } else {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                }
            }

            // Auction select all state
            if (selectAllAuctions && auctionCheckboxes.length > 0) {
                const checkedAuc = Array.from(auctionCheckboxes).filter(cb => cb.checked);
                if (checkedAuc.length === auctionCheckboxes.length) {
                    selectAllAuctions.checked = true;
                    selectAllAuctions.indeterminate = false;
                } else if (checkedAuc.length > 0) {
                    selectAllAuctions.checked = false;
                    selectAllAuctions.indeterminate = true;
                } else {
                    selectAllAuctions.checked = false;
                    selectAllAuctions.indeterminate = false;
                }
            }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                const isChecked = this.checked;
                clientCheckboxes.forEach(cb => {
                    const row = cb.closest('.client-row');
                    if (!row || row.style.display !== 'none') {
                        cb.checked = isChecked;
                    }
                });
                updateSelection();
            });
        }

        if (selectAllAuctions) {
            selectAllAuctions.addEventListener('change', function () {
                const isChecked = this.checked;
                auctionCheckboxes.forEach(cb => {
                    cb.checked = isChecked;
                });
                updateSelection();
            });
        }

        clientCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateSelection);
        });

        auctionCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateSelection);
        });

        // Initialize multi-select dropdowns
        function initCustomMultiselects() {
            document.querySelectorAll('.custom-multiselect').forEach(wrapper => {
                const toggleBtn = wrapper.querySelector('.multiselect-toggle');
                const menu = wrapper.querySelector('.multiselect-menu');
                const arrow = wrapper.querySelector('.multiselect-arrow');
                const label = wrapper.querySelector('.multiselect-label');
                const badge = wrapper.querySelector('.multiselect-badge');
                const searchInput = wrapper.querySelector('.multiselect-search');
                const selectAllBtn = wrapper.querySelector('.multiselect-select-all');
                const clearBtn = wrapper.querySelector('.multiselect-clear');
                const checkboxes = wrapper.querySelectorAll('.multiselect-checkbox');
                const defaultPlaceholder = wrapper.dataset.placeholder || 'Todos';

                function updateDisplay() {
                    const checkedBoxes = Array.from(checkboxes).filter(cb => cb.checked);
                    const count = checkedBoxes.length;

                    if (count === 0) {
                        label.textContent = defaultPlaceholder;
                        label.classList.remove('text-brand-900', 'font-semibold');
                        label.classList.add('text-gray-500', 'font-normal');
                        if (badge) badge.classList.add('hidden');
                    } else {
                        const checkedLabels = checkedBoxes.map(cb => {
                            const item = cb.closest('.multiselect-item');
                            return item ? item.querySelector('.multiselect-text').textContent.trim() : cb.value;
                        });

                        if (count <= 2) {
                            label.textContent = checkedLabels.join(', ');
                        } else {
                            label.textContent = count + ' selecionados';
                        }

                        label.classList.remove('text-gray-500', 'font-normal');
                        label.classList.add('text-brand-900', 'font-semibold');

                        if (badge) {
                            badge.textContent = count;
                            badge.classList.remove('hidden');
                        }
                    }
                }

                toggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isOpen = !menu.classList.contains('hidden');

                    // Close all other multiselects and reset z-index
                    document.querySelectorAll('.custom-multiselect').forEach(w => {
                        const m = w.querySelector('.multiselect-menu');
                        const arr = w.querySelector('.multiselect-arrow');
                        if (m && m !== menu) {
                            m.classList.add('hidden');
                            if (arr) arr.classList.remove('rotate-180');
                        }
                        w.classList.remove('z-50');
                    });

                    if (isOpen) {
                        menu.classList.add('hidden');
                        wrapper.classList.remove('z-50');
                        if (arrow) arrow.classList.remove('rotate-180');
                    } else {
                        menu.classList.remove('hidden');
                        wrapper.classList.add('z-50');
                        if (arrow) arrow.classList.add('rotate-180');
                        if (searchInput) searchInput.focus();
                    }
                });

                menu.addEventListener('click', (e) => {
                    e.stopPropagation();
                });

                checkboxes.forEach(cb => {
                    cb.addEventListener('change', () => {
                        updateDisplay();
                    });
                });

                if (selectAllBtn) {
                    selectAllBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        checkboxes.forEach(cb => {
                            const item = cb.closest('.multiselect-item');
                            if (!item || !item.classList.contains('hidden')) {
                                cb.checked = true;
                            }
                        });
                        updateDisplay();
                    });
                }

                if (clearBtn) {
                    clearBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        checkboxes.forEach(cb => cb.checked = false);
                        updateDisplay();
                    });
                }

                if (searchInput) {
                    searchInput.addEventListener('input', (e) => {
                        const term = e.target.value.toLowerCase().trim();
                        wrapper.querySelectorAll('.multiselect-item').forEach(item => {
                            const text = item.querySelector('.multiselect-text').textContent.toLowerCase();
                            if (!term || text.includes(term)) {
                                item.classList.remove('hidden');
                            } else {
                                item.classList.add('hidden');
                            }
                        });
                    });
                }
            });

            document.addEventListener('click', () => {
                document.querySelectorAll('.custom-multiselect').forEach(w => {
                    const m = w.querySelector('.multiselect-menu');
                    const arr = w.querySelector('.multiselect-arrow');
                    if (m) m.classList.add('hidden');
                    if (arr) arr.classList.remove('rotate-180');
                    w.classList.remove('z-50');
                });
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.custom-multiselect').forEach(w => {
                        const m = w.querySelector('.multiselect-menu');
                        const arr = w.querySelector('.multiselect-arrow');
                        if (m) m.classList.add('hidden');
                        if (arr) arr.classList.remove('rotate-180');
                        w.classList.remove('z-50');
                    });
                }
            });
        }

        // Monthly Milk Production Thousand Mask & Interval Validation
        function formatMilkLiters(value) {
            let clean = (value || '').toString().replace(/\D/g, '');
            clean = clean.replace(/^0+/, '');
            if (!clean) {
                return '0.000';
            }
            const padded = clean.padStart(4, '0');
            const mainPart = padded.slice(0, -3);
            const decimalPart = padded.slice(-3);
            const formattedMain = parseInt(mainPart, 10).toLocaleString('pt-BR');
            return `${formattedMain}.${decimalPart}`;
        }

        document.addEventListener('DOMContentLoaded', () => {
            initCustomMultiselects();
            updateSelection();

            document.querySelectorAll('.milk-range-mask').forEach(input => {
                if (input.value && input.value !== '0.000') {
                    input.value = formatMilkLiters(input.value);
                }
                input.addEventListener('input', function () {
                    this.value = formatMilkLiters(this.value);
                });
                input.addEventListener('focus', function () {
                    if (!this.value || this.value === '0.000') {
                        this.value = '0.000';
                    }
                    setTimeout(() => this.select(), 50);
                });
                input.addEventListener('blur', function () {
                    const clean = this.value.replace(/\D/g, '');
                    if (!clean || parseInt(clean, 10) === 0) {
                        this.value = '';
                    } else {
                        this.value = formatMilkLiters(this.value);
                    }
                });
            });

            const mapFilterForm = document.getElementById('mapFilterForm');
            if (mapFilterForm) {
                mapFilterForm.addEventListener('submit', (e) => {
                    const minEl = document.getElementById('milkMinInput');
                    const maxEl = document.getElementById('milkMaxInput');
                    if (minEl && maxEl) {
                        const minNum = parseInt(minEl.value.replace(/\D/g, '') || '0', 10);
                        const maxNum = parseInt(maxEl.value.replace(/\D/g, '') || '0', 10);
                        if (minNum > 0 && maxNum > 0 && minNum > maxNum) {
                            e.preventDefault();
                            alert('Atenção: A quantidade de Leite Mensal Final deve ser maior ou igual à Inicial.');
                            maxEl.focus();
                            return false;
                        }
                    }

                    if (typeof window.showLoading === 'function') {
                        window.showLoading('Carregando mapa...', 'Filtrando clientes e pins geográficos');
                    }
                });
            }
        });
    </script>
</body>

</html>