<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();
$user_id = $_SESSION['user_id'];
$pageTitle = 'Relatórios';

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

// Dynamic query
$query = "
    SELECT c.id as client_id, c.name as client_name, c.farm_name, c.phone, c.email, c.uf, c.city, c.status, c.is_potential,
           c.payment_condition, c.breed_interests, c.animal_categories, c.production_system, c.is_milk_producer, c.acquisition_reason,
           c.animal_count_range, c.milk_production_range, c.purchase_animal_count,
           i.id as intention_id, i.type, i.description, i.value, i.status as intention_status, cat.name as category_name 
    FROM " . TABLE_NAME . "clients c
    LEFT JOIN " . TABLE_NAME . "intentions i ON i.client_id = c.id AND (i.status = 'active' OR i.status IS NULL)
    LEFT JOIN " . TABLE_NAME . "categories cat ON i.category_id = cat.id 
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
$results = $stmt->fetchAll();

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

// Check if any filter is active
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
    <title>Relatórios - CRM Vitor Müller</title>
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
    <!-- Modal de Aguarde / Loading Overlay -->
    <div id="reportsLoadingOverlay" class="fixed inset-0 bg-slate-900/30 backdrop-blur-xs z-50 flex items-center justify-center transition-opacity duration-200 opacity-0 pointer-events-none">
        <div class="bg-white px-6 py-5 rounded-2xl shadow-2xl flex items-center space-x-4 border border-gray-100 max-w-xs sm:max-w-sm">
            <div class="w-8 h-8 border-4 border-brand-200 border-t-brand-600 rounded-full animate-spin flex-shrink-0"></div>
            <div>
                <p class="font-bold text-gray-800 text-sm">Carregando relatório...</p>
                <p class="text-xs text-gray-500">Processando filtros e registros</p>
            </div>
        </div>
    </div>

    <div class="relative min-h-screen md:flex">
        <?php include '../components/sidebar.php'; ?>
        <div class="flex-1 flex flex-col min-h-screen overflow-hidden">
            <?php include '../components/header.php'; ?>
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-brand-50 p-4 sm:p-6">

                <!-- Page Title & Header Actions -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-3">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-brand-900">Relatórios</h1>
                        <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Filtragem detalhada e consulta de clientes e intenções</p>
                    </div>
                    <?php if ($hasActiveFilters): ?>
                        <a href="reports.php"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-200 hover:bg-gray-300 text-gray-700 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Limpar Filtros (<?php echo $activeFilterCount; ?>)
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Filters Section -->
                <div class="bg-white rounded-xl shadow-md border border-brand-100 p-5 sm:p-6 mb-6 relative z-20 overflow-visible">
                    <div class="flex items-center justify-between pb-3 mb-4 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">🔍</span>
                            <h2 class="text-base sm:text-lg font-bold text-gray-800">Filtrar Clientes e Interesses</h2>
                            <?php if ($activeFilterCount > 0): ?>
                                <span class="bg-brand-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                                    <?php echo $activeFilterCount; ?> ativo(s)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form method="GET" action="reports.php" id="reportFilterForm" class="space-y-4">
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
                                    <a href="reports.php"
                                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-lg transition text-xs sm:text-sm flex items-center justify-center h-[38px]">
                                        Limpar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Results Section -->
                <div class="bg-white shadow-md rounded-xl overflow-hidden border border-gray-200">
                    <div class="px-5 py-4 border-b border-gray-200 flex flex-wrap justify-between items-center gap-2 bg-gray-50/70">
                        <div class="flex items-center gap-2">
                            <h2 class="text-base sm:text-lg font-bold text-gray-800">Resultados Encontrados</h2>
                            <span class="bg-brand-100 text-brand-800 px-3 py-0.5 rounded-full text-xs font-extrabold border border-brand-200">
                                <?php echo count($results); ?> registros
                            </span>
                        </div>
                        <a href="map-selector.php<?php echo !empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : ''; ?>" 
                           class="text-xs font-bold text-brand-600 hover:text-brand-800 hover:underline flex items-center gap-1">
                            <span>🗺️ Ver clientes filtrados no mapa</span>
                        </a>
                    </div>

                    <?php if (count($results) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full leading-normal">
                                <thead>
                                    <tr class="bg-gray-100/80 text-gray-700 text-left text-xs font-bold uppercase tracking-wider border-b border-gray-200">
                                        <th class="px-5 py-3">Cliente</th>
                                        <th class="px-5 py-3">Status</th>
                                        <th class="px-5 py-3">Cidade / UF</th>
                                        <th class="px-5 py-3">Perfil Comercial & Raças</th>
                                        <th class="px-5 py-3">Categorias & Produção</th>
                                        <th class="px-5 py-3">Intenções Registradas</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($results as $item): ?>
                                        <tr class="hover:bg-brand-50/30 transition">
                                            <!-- Cliente -->
                                            <td class="px-5 py-4 bg-white text-sm">
                                                <a href="client-details.php?id=<?php echo $item['client_id']; ?>"
                                                    class="font-bold text-brand-700 hover:underline flex items-center text-sm">
                                                    <?php echo htmlspecialchars($item['client_name']); ?>
                                                    <?php if (!empty($item['is_potential'])): ?>
                                                        <span class="ml-1.5 text-amber-500 text-xs" title="Cliente em Potencial">⭐</span>
                                                    <?php endif; ?>
                                                </a>
                                                <?php if (!empty($item['farm_name'])): ?>
                                                    <p class="text-xs text-brand-900 font-medium mt-0.5">🏡 <?php echo htmlspecialchars($item['farm_name']); ?></p>
                                                <?php endif; ?>
                                                <div class="text-gray-500 text-xs mt-1">
                                                    <?php if (!empty($item['phone'])): ?>
                                                        <span class="block">📞 <?php echo htmlspecialchars(formatPhone($item['phone'])); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['email'])): ?>
                                                        <span class="block text-gray-400 text-xs truncate max-w-xs">✉️ <?php echo htmlspecialchars($item['email']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <!-- Status -->
                                            <td class="px-5 py-4 bg-white text-sm whitespace-nowrap">
                                                <?php if (($item['status'] ?? '') === 'Embral'): ?>
                                                    <span class="bg-blue-100 text-blue-800 text-xs px-2.5 py-1 rounded-full font-bold border border-blue-300">
                                                        Embral
                                                    </span>
                                                <?php elseif (($item['status'] ?? '') === 'Atendido'): ?>
                                                    <span class="bg-purple-100 text-purple-800 text-xs px-2.5 py-1 rounded-full font-bold border border-purple-300">
                                                        Atendido
                                                    </span>
                                                <?php elseif (($item['status'] ?? '') === 'Inativo'): ?>
                                                    <span class="bg-gray-100 text-gray-700 text-xs px-2.5 py-1 rounded-full font-bold border border-gray-300">
                                                        Inativo
                                                    </span>
                                                <?php elseif (in_array($item['status'] ?? '', ['Novo', 'Pré-cadastro'])): ?>
                                                    <span class="bg-amber-100 text-amber-800 text-xs px-2.5 py-1 rounded-full font-bold border border-amber-300">
                                                        Novo
                                                    </span>
                                                <?php else: ?>
                                                    <span class="bg-green-100 text-green-800 text-xs px-2.5 py-1 rounded-full font-bold border border-green-300">
                                                        Ativo
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Cidade / UF -->
                                            <td class="px-5 py-4 bg-white text-sm whitespace-nowrap">
                                                <?php
                                                $loc = array_filter([$item['city'] ?? '', $item['uf'] ?? '']);
                                                echo htmlspecialchars(!empty($loc) ? implode(' / ', $loc) : '-');
                                                ?>
                                            </td>

                                            <!-- Perfil & Raças -->
                                            <td class="px-5 py-4 bg-white text-sm">
                                                <div class="font-semibold text-gray-800 text-xs">
                                                    🐄 <?php echo htmlspecialchars($item['breed_interests'] ?: 'Sem raças informadas'); ?>
                                                </div>
                                                <div class="text-gray-500 text-xs mt-1">
                                                    💳 <?php echo htmlspecialchars($item['payment_condition'] ?: '-'); ?>
                                                </div>
                                                <?php if (!empty($item['is_milk_producer'])): ?>
                                                    <div class="text-[11px] text-gray-400 mt-0.5">
                                                        Produtor Leite: <span class="font-medium text-gray-700"><?php echo htmlspecialchars($item['is_milk_producer']); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Categorias & Sistema de Produção -->
                                            <td class="px-5 py-4 bg-white text-sm max-w-xs">
                                                <?php if (!empty($item['animal_categories'])): ?>
                                                    <div class="text-xs text-gray-800 font-medium">
                                                        <span class="font-bold text-brand-800 text-[11px] block">Categorias:</span>
                                                        <?php echo htmlspecialchars($item['animal_categories']); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-xs block">Sem categorias</span>
                                                <?php endif; ?>

                                                <?php if (!empty($item['production_system'])): ?>
                                                    <div class="text-xs text-gray-600 mt-1">
                                                        <span class="font-bold text-gray-700 text-[11px]">Sistema:</span>
                                                        <?php echo htmlspecialchars($item['production_system']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Intenções -->
                                            <td class="px-5 py-4 bg-white text-sm">
                                                <?php if (!empty($item['intention_id'])): ?>
                                                    <div class="flex items-center gap-1.5">
                                                        <?php if ($item['type'] == 'buy'): ?>
                                                            <span class="bg-blue-100 text-blue-800 text-[11px] px-2 py-0.5 rounded font-bold">🛒 Compra</span>
                                                        <?php else: ?>
                                                            <span class="bg-red-100 text-red-800 text-[11px] px-2 py-0.5 rounded font-bold">💰 Venda</span>
                                                        <?php endif; ?>

                                                        <span class="text-gray-700 text-xs font-semibold">
                                                            <?php echo htmlspecialchars($item['category_name'] ?: 'Sem categoria'); ?>
                                                        </span>
                                                    </div>
                                                    <div class="text-xs text-gray-500 mt-1 max-w-xs truncate"
                                                        title="<?php echo htmlspecialchars($item['description']); ?>">
                                                        <?php echo htmlspecialchars($item['description']); ?>
                                                    </div>
                                                    <?php if ($item['value']): ?>
                                                        <div class="text-green-600 font-bold text-xs mt-0.5">
                                                            R$ <?php echo number_format($item['value'], 2, ',', '.'); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-xs italic">Sem intenção vinculada</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-12 text-center text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="text-base font-bold text-gray-700">Nenhum registro encontrado</p>
                            <p class="text-xs text-gray-400 mt-1">Tente ajustar ou limpar os filtros selecionados para expandir sua busca.</p>
                            <?php if ($hasActiveFilters): ?>
                                <a href="reports.php" class="inline-block mt-3 bg-brand-500 hover:bg-brand-600 text-white font-bold py-1.5 px-4 rounded-lg text-xs transition">
                                    Limpar Filtros
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </main>
        </div>
    </div>

    <!-- Custom Multi-Select JavaScript Handler -->
    <script>
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

            // Show loading modal on form submit + validate interval
            const filterForm = document.getElementById('reportFilterForm') || document.querySelector('form[method="GET"]');
            if (filterForm) {
                filterForm.addEventListener('submit', (e) => {
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

                    const overlay = document.getElementById('reportsLoadingOverlay');
                    if (overlay) {
                        overlay.classList.remove('opacity-0', 'pointer-events-none');
                    }
                });
            }
        }

        document.addEventListener('DOMContentLoaded', initCustomMultiselects);
    </script>
</body>

</html>