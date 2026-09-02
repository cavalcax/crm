<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();
$user_id = $_SESSION['user_id'];
$pageTitle = 'Calculadora de Frete';

// Fetch Clients with or without coordinates for Origin/Destination selection
$stmtClients = $pdo->prepare("
    SELECT id, name, farm_name, city, uf, latitude, longitude, phone 
    FROM " . TABLE_NAME . "clients 
    WHERE user_id = ? 
    ORDER BY name ASC
");
$stmtClients->execute([$user_id]);
$clientsList = $stmtClients->fetchAll(PDO::FETCH_ASSOC);

// Fetch Auctions with coordinates for Origin/Destination selection
$stmtAuctions = $pdo->prepare("
    SELECT s.id, s.title, s.start_time, s.latitude, s.longitude, c.name as client_name, c.city, c.uf
    FROM " . TABLE_NAME . "schedule s 
    LEFT JOIN " . TABLE_NAME . "clients c ON s.client_id = c.id 
    WHERE s.user_id = ? 
      AND s.type = 'auction'
    ORDER BY s.start_time DESC
");
$stmtAuctions->execute([$user_id]);
$auctionsList = $stmtAuctions->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Vitor Müller</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        /* Select2 Custom Styling */
        .select2-container {
            width: 100% !important;
        }
        .select2-container--default .select2-selection--single {
            height: 38px;
            padding: 4px 8px;
            border-color: #cbd5e1;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            font-size: 0.75rem;
            font-weight: 500;
            background-color: #ffffff;
            transition: all 0.15s ease-in-out;
        }
        .select2-container--default .select2-selection--single:focus,
        .select2-container--default.select2-container--open .select2-selection--single,
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #16a34a;
            box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.2);
            outline: none;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #1f2937;
            line-height: normal;
            padding-left: 2px;
            padding-right: 20px;
            font-size: 0.75rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #9ca3af;
            font-size: 0.75rem;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
            position: absolute;
            top: 1px;
            right: 8px;
            width: 20px;
        }
        .select2-container--default .select2-selection--single .select2-selection__clear {
            cursor: pointer;
            float: right;
            font-weight: bold;
            height: 26px;
            margin-right: 18px;
            padding: 0;
            color: #ef4444;
            font-size: 14px;
        }
        .select2-dropdown {
            border-color: #cbd5e1;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            font-size: 0.75rem;
            z-index: 9999;
        }
        .select2-container--default .select2-search--dropdown {
            padding: 6px;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field {
            border-color: #e2e8f0;
            border-radius: 0.375rem;
            padding: 6px 10px;
            font-size: 0.75rem;
            outline: none;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 1px #16a34a;
        }
        .select2-results__option {
            padding: 8px 10px;
            font-size: 0.75rem;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #16a34a;
            color: white;
        }
        .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: #dcfce7;
            color: #166534;
            font-weight: 600;
        }

        #freightMap {
            min-height: 520px;
            height: calc(100vh - 220px);
            width: 100%;
            border-radius: 0.75rem;
        }

        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 99999;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2);
            max-height: 220px;
            overflow-y: auto;
            margin-top: 2px;
        }

        .autocomplete-item {
            padding: 8px 12px;
            font-size: 12px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s;
        }

        .autocomplete-item:last-child {
            border-bottom: none;
        }

        .autocomplete-item:hover {
            background: #f0fdf4;
        }
    </style>
</head>

<body class="bg-gray-50 flex h-screen overflow-hidden text-gray-800">

    <?php include '../components/sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-h-screen overflow-hidden">
        <?php include '../components/header.php'; ?>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-slate-100 p-3 sm:p-5">
            <!-- Header Title -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
                <div>
                    <h1 class="text-xl sm:text-2xl font-extrabold text-brand-900 flex items-center gap-2.5">
                        <span
                            class="p-2 bg-brand-600 text-white rounded-lg shadow-sm text-base sm:text-lg flex items-center justify-center">
                            <i class="fas fa-truck-moving"></i>
                        </span>
                        Calculadora de Frete
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-0.5">
                        Calcule distâncias ida/volta no mapa interativo com rota arrastável, custo por km e rateio por
                        animal.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="resetCalculator()"
                        class="px-3 py-1.5 bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 text-xs font-bold rounded-lg shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                        <i class="fas fa-undo"></i> Limpar Rota
                    </button>
                    <button type="button" onclick="shareFreightWhatsApp()"
                        class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                        <i class="fab fa-whatsapp text-sm"></i> Compartilhar Cotação
                    </button>
                </div>
            </div>

            <!-- Main Layout Grid (Uses display: contents on mobile for custom order; flex column on desktop for natural collapsing) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">

                <!-- Left Column Group (Card 1 & Card 3) -->
                <div class="contents lg:flex lg:flex-col lg:space-y-4 lg:col-span-5">

                    <!-- 1. PONTO DE ORIGEM E DESTINO CARD -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden order-1">
                        <!-- Accordion Header -->
                        <button type="button" onclick="toggleAccordion('routeAccordionContent', 'routeAccordionArrow')"
                            class="w-full flex items-center justify-between p-3.5 sm:p-4 bg-white hover:bg-gray-50/80 transition cursor-pointer text-left border-b border-gray-100">
                            <span class="text-xs font-bold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                                <i class="fas fa-map-marker-alt text-brand-600 text-sm"></i> Traçado da Rota
                            </span>
                            <div class="flex items-center gap-2">
                                <span id="routeStatusBadge"
                                    class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">
                                    Aguardando pontos
                                </span>
                                <svg id="routeAccordionArrow" class="w-4 h-4 text-gray-400 transition-transform duration-200 transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </button>

                        <!-- Accordion Body -->
                        <div id="routeAccordionContent" class="p-3.5 sm:p-4">
                            <!-- PONTO A: ORIGEM -->
                            <div class="bg-emerald-50/70 border border-emerald-200 rounded-lg p-3 relative mb-2">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-extrabold text-emerald-900 flex items-center gap-1.5">
                                        <span
                                            class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[11px] font-black">A</span>
                                        Origem (Ponto Inicial)
                                    </span>
                                    <!-- Type Selector Tabs -->
                                    <div
                                        class="inline-flex bg-emerald-100/80 p-0.5 rounded-md text-[11px] font-semibold text-emerald-900">
                                        <button type="button" onclick="setPointMode('origin', 'client')"
                                            id="btnOriginClient"
                                            class="px-2 py-0.5 rounded bg-white text-emerald-800 shadow-xs">Cliente</button>
                                        <button type="button" onclick="setPointMode('origin', 'auction')"
                                            id="btnOriginAuction"
                                            class="px-2 py-0.5 rounded text-emerald-700 hover:text-emerald-900">Leilão</button>
                                        <button type="button" onclick="setPointMode('origin', 'custom')"
                                            id="btnOriginCustom"
                                            class="px-2 py-0.5 rounded text-emerald-700 hover:text-emerald-900">Endereço</button>
                                    </div>
                                </div>

                                <!-- Input Mode: Cliente -->
                                <div id="originClientWrapper" class="space-y-1">
                                    <select id="originClientSelect"
                                        class="w-full bg-white border border-emerald-300 rounded-lg text-xs text-gray-800 font-medium">
                                        <option value=""></option>
                                        <?php foreach ($clientsList as $c): ?>
                                            <option value="<?php echo $c['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($c['name']); ?>"
                                                data-farm="<?php echo htmlspecialchars($c['farm_name'] ?? ''); ?>"
                                                data-city="<?php echo htmlspecialchars($c['city'] ?? ''); ?>"
                                                data-uf="<?php echo htmlspecialchars($c['uf'] ?? ''); ?>"
                                                data-phone="<?php echo htmlspecialchars($c['phone'] ?? ''); ?>"
                                                data-lat="<?php echo $c['latitude']; ?>"
                                                data-lng="<?php echo $c['longitude']; ?>">
                                                <?php echo htmlspecialchars($c['name']) . (!empty($c['farm_name']) ? ' (' . htmlspecialchars($c['farm_name']) . ')' : '') . (!empty($c['city']) ? ' - ' . htmlspecialchars($c['city']) . '/' . htmlspecialchars($c['uf']) : '') . (!empty($c['latitude']) ? ' 📍' : ' (Sem GPS)'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Input Mode: Leilao -->
                                <div id="originAuctionWrapper" class="space-y-1 hidden">
                                    <select id="originAuctionSelect"
                                        class="w-full bg-white border border-emerald-300 rounded-lg text-xs text-gray-800 font-medium">
                                        <option value=""></option>
                                        <?php foreach ($auctionsList as $auc): ?>
                                            <option value="<?php echo $auc['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($auc['title']); ?>"
                                                data-city="<?php echo htmlspecialchars($auc['city'] ?? ''); ?>"
                                                data-uf="<?php echo htmlspecialchars($auc['uf'] ?? ''); ?>"
                                                data-lat="<?php echo $auc['latitude']; ?>"
                                                data-lng="<?php echo $auc['longitude']; ?>">
                                                <?php echo htmlspecialchars($auc['title']) . ' (' . date('d/m/Y', strtotime($auc['start_time'])) . ')' . (!empty($auc['city']) ? ' - ' . htmlspecialchars($auc['city']) : '') . (!empty($auc['latitude']) ? ' 📍' : ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Input Mode: Custom Address with Places Autocomplete -->
                                <div id="originCustomWrapper" class="space-y-1 hidden">
                                    <div class="relative">
                                        <input type="text" id="originCustomInput"
                                            placeholder="Digite endereço, cidade, rodovia ou localidade..."
                                            class="w-full bg-white border border-emerald-300 rounded-lg pl-8 pr-3 py-1.5 text-xs text-gray-800 font-medium focus:ring-2 focus:ring-emerald-500 focus:outline-none shadow-xs">
                                        <i class="fas fa-search absolute left-2.5 top-2 text-emerald-500 text-xs"></i>
                                    </div>
                                </div>

                                <!-- Resolved Address Preview -->
                                <div id="originDisplayInfo"
                                    class="mt-1.5 text-[11px] text-emerald-800 font-medium flex items-center justify-between">
                                    <span id="originText" class="truncate">Nenhum local selecionado</span>
                                    <span id="originCoordsBadge"
                                        class="hidden font-mono bg-emerald-200/80 px-1.5 py-0.2 rounded text-[10px]">GPS OK</span>
                                </div>
                            </div>

                            <!-- SWAP BUTTON (INVERTER ORIGEM / DESTINO) -->
                            <div class="flex justify-center -my-2 relative z-10">
                                <button type="button" onclick="swapOriginDestination()" title="Inverter Origem e Destino"
                                    class="bg-white border-2 border-brand-500 hover:bg-brand-50 text-brand-700 w-8 h-8 rounded-full shadow-md flex items-center justify-center transition transform hover:rotate-180 duration-300 cursor-pointer">
                                    <i class="fas fa-arrows-up-down text-xs"></i>
                                </button>
                            </div>

                            <!-- PONTO B: DESTINO -->
                            <div class="bg-rose-50/70 border border-rose-200 rounded-lg p-3 relative mt-2">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-extrabold text-rose-900 flex items-center gap-1.5">
                                        <span
                                            class="w-5 h-5 rounded-full bg-rose-600 text-white flex items-center justify-center text-[11px] font-black">B</span>
                                        Destino (Ponto Final)
                                    </span>
                                    <!-- Type Selector Tabs -->
                                    <div
                                        class="inline-flex bg-rose-100/80 p-0.5 rounded-md text-[11px] font-semibold text-rose-900">
                                        <button type="button" onclick="setPointMode('destination', 'client')"
                                            id="btnDestClient"
                                            class="px-2 py-0.5 rounded bg-white text-rose-800 shadow-xs">Cliente</button>
                                        <button type="button" onclick="setPointMode('destination', 'auction')"
                                            id="btnDestAuction"
                                            class="px-2 py-0.5 rounded text-rose-700 hover:text-rose-900">Leilão</button>
                                        <button type="button" onclick="setPointMode('destination', 'custom')"
                                            id="btnDestCustom"
                                            class="px-2 py-0.5 rounded text-rose-700 hover:text-rose-900">Endereço</button>
                                    </div>
                                </div>

                                <!-- Input Mode: Cliente -->
                                <div id="destClientWrapper" class="space-y-1">
                                    <select id="destClientSelect"
                                        class="w-full bg-white border border-rose-300 rounded-lg text-xs text-gray-800 font-medium">
                                        <option value=""></option>
                                        <?php foreach ($clientsList as $c): ?>
                                            <option value="<?php echo $c['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($c['name']); ?>"
                                                data-farm="<?php echo htmlspecialchars($c['farm_name'] ?? ''); ?>"
                                                data-city="<?php echo htmlspecialchars($c['city'] ?? ''); ?>"
                                                data-uf="<?php echo htmlspecialchars($c['uf'] ?? ''); ?>"
                                                data-phone="<?php echo htmlspecialchars($c['phone'] ?? ''); ?>"
                                                data-lat="<?php echo $c['latitude']; ?>"
                                                data-lng="<?php echo $c['longitude']; ?>">
                                                <?php echo htmlspecialchars($c['name']) . (!empty($c['farm_name']) ? ' (' . htmlspecialchars($c['farm_name']) . ')' : '') . (!empty($c['city']) ? ' - ' . htmlspecialchars($c['city']) . '/' . htmlspecialchars($c['uf']) : '') . (!empty($c['latitude']) ? ' 📍' : ' (Sem GPS)'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Input Mode: Leilao -->
                                <div id="destAuctionWrapper" class="space-y-1 hidden">
                                    <select id="destAuctionSelect"
                                        class="w-full bg-white border border-rose-300 rounded-lg text-xs text-gray-800 font-medium">
                                        <option value=""></option>
                                        <?php foreach ($auctionsList as $auc): ?>
                                            <option value="<?php echo $auc['id']; ?>"
                                                data-title="<?php echo htmlspecialchars($auc['title']); ?>"
                                                data-city="<?php echo htmlspecialchars($auc['city'] ?? ''); ?>"
                                                data-uf="<?php echo htmlspecialchars($auc['uf'] ?? ''); ?>"
                                                data-lat="<?php echo $auc['latitude']; ?>"
                                                data-lng="<?php echo $auc['longitude']; ?>">
                                                <?php echo htmlspecialchars($auc['title']) . ' (' . date('d/m/Y', strtotime($auc['start_time'])) . ')' . (!empty($auc['city']) ? ' - ' . htmlspecialchars($auc['city']) : '') . (!empty($auc['latitude']) ? ' 📍' : ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Input Mode: Custom Address with Places Autocomplete -->
                                <div id="destCustomWrapper" class="space-y-1 hidden">
                                    <div class="relative">
                                        <input type="text" id="destCustomInput"
                                            placeholder="Digite endereço, cidade, rodovia ou localidade..."
                                            class="w-full bg-white border border-rose-300 rounded-lg pl-8 pr-3 py-1.5 text-xs text-gray-800 font-medium focus:ring-2 focus:ring-rose-500 focus:outline-none shadow-xs">
                                        <i class="fas fa-search absolute left-2.5 top-2 text-rose-500 text-xs"></i>
                                    </div>
                                </div>

                                <!-- Resolved Address Preview -->
                                <div id="destDisplayInfo"
                                    class="mt-1.5 text-[11px] text-rose-800 font-medium flex items-center justify-between">
                                    <span id="destText" class="truncate">Nenhum local selecionado</span>
                                    <span id="destCoordsBadge"
                                        class="hidden font-mono bg-rose-200/80 px-1.5 py-0.2 rounded text-[10px]">GPS OK</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. PARÂMETROS DE FRETE E CÁLCULO FINANCEIRO CARD -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden order-3">
                        <!-- Accordion Header -->
                        <button type="button" onclick="toggleAccordion('financialAccordionContent', 'financialAccordionArrow')"
                            class="w-full flex items-center justify-between p-3.5 sm:p-4 bg-white hover:bg-gray-50/80 transition cursor-pointer text-left border-b border-gray-100">
                            <span class="text-xs font-bold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                                <i class="fas fa-calculator text-brand-600 text-sm"></i> Parâmetros Financeiros & Custos
                            </span>
                            <div class="flex items-center gap-2">
                                <span id="routeDurationBadge" class="text-[11px] text-gray-500 font-medium">
                                    Tempo: --
                                </span>
                                <svg id="financialAccordionArrow" class="w-4 h-4 text-gray-400 transition-transform duration-200 transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </button>

                        <!-- Accordion Body -->
                        <div id="financialAccordionContent" class="p-3.5 sm:p-4 space-y-3.5">
                            <!-- Grid de Inputs de Cálculo -->
                            <div class="grid grid-cols-2 gap-3">
                                <!-- 1. Distância Só Ida (km) -->
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-600 mb-1">Distância (Só Ida)</label>
                                    <div class="relative">
                                        <input type="number" step="0.1" id="inputOneWayKm"
                                            oninput="handleManualDistanceChange()" placeholder="0.0"
                                            class="w-full bg-gray-50 border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-bold text-gray-800 focus:bg-white focus:ring-2 focus:ring-brand-500 focus:outline-none">
                                        <span
                                            class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none text-[10px] font-bold text-gray-400 uppercase">KM</span>
                                    </div>
                                </div>

                                <!-- 2. Distância Total Ida e Volta (km x 2) -->
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-[11px] font-bold text-brand-900">Distância Total (x2)</label>
                                        <span
                                            class="text-[9px] font-extrabold px-1.5 py-0.2 bg-brand-100 text-brand-800 rounded">Ida e Volta</span>
                                    </div>
                                    <div class="relative">
                                        <input type="text" id="inputRoundTripKm" readonly placeholder="0.0"
                                            class="w-full bg-brand-50 border border-brand-200 rounded-lg px-2.5 py-1.5 text-xs font-extrabold text-brand-800 cursor-default">
                                        <span
                                            class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none text-[10px] font-bold text-brand-600 uppercase">KM Total</span>
                                    </div>
                                </div>

                                <!-- 3. Valor por KM Rodado (R$/km) -->
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-600 mb-1">Valor por KM Rodado</label>
                                    <div class="relative">
                                        <span
                                            class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none text-xs font-bold text-gray-400">R$</span>
                                        <input type="text" inputmode="decimal" id="inputPricePerKm"
                                            oninput="handleCurrencyInput(this); calculateTotals();" placeholder="0,00"
                                            value="6,00"
                                            class="w-full bg-white border border-gray-300 rounded-lg pl-8 pr-2.5 py-1.5 text-xs font-bold text-gray-800 focus:ring-2 focus:ring-brand-500 focus:outline-none">
                                    </div>
                                </div>

                                <!-- 4. Quantidade de Animais -->
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-600 mb-1">Qtd. Total de Animais</label>
                                    <div class="relative">
                                        <input type="number" min="1" step="1" id="inputAnimalCount"
                                            oninput="calculateTotals()" placeholder="Ex: 30" value=""
                                            class="w-full bg-white border border-gray-300 rounded-lg px-2.5 py-1.5 text-xs font-bold text-gray-800 focus:ring-2 focus:ring-brand-500 focus:outline-none">
                                    </div>
                                </div>
                            </div>

                            <!-- Painel de Resultados em Destaque -->
                            <div
                                class="mt-4 pt-3 border-t border-gray-100 bg-gradient-to-br from-brand-900 to-slate-900 rounded-xl p-4 text-white shadow-md">
                                <div class="grid grid-cols-2 gap-3 divide-x divide-white/10">
                                    <!-- Total Frete -->
                                    <div>
                                        <span
                                            class="text-[10px] font-bold uppercase tracking-wider text-brand-300 block mb-0.5">
                                            <i class="fas fa-money-bill-wave"></i> Total do Frete (Ida e Volta)
                                        </span>
                                        <div id="resultTotalFreight"
                                            class="text-xl sm:text-2xl font-black text-white tracking-tight">
                                            R$ 0,00
                                        </div>
                                        <span id="resultSubcalcFormula"
                                            class="text-[10px] text-gray-300 mt-0.5 block font-mono">
                                            0 km × R$ 0,00
                                        </span>
                                    </div>

                                    <!-- Média por Animal -->
                                    <div class="pl-3">
                                        <span
                                            class="text-[10px] font-bold uppercase tracking-wider text-emerald-300 block mb-0.5">
                                            <i class="fas fa-cow"></i> Rateio por Animal
                                        </span>
                                        <div id="resultCostPerAnimal"
                                            class="text-xl sm:text-2xl font-black text-emerald-400 tracking-tight">
                                            R$ 0,00
                                        </div>
                                        <span id="resultAnimalSubcalc"
                                            class="text-[10px] text-gray-300 mt-0.5 block font-mono">
                                            / cabeça
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Resumo Rápido da Rota -->
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-600 space-y-1">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="text-gray-500 font-semibold">Trajeto:</span>
                                    <span id="summaryRouteDesc"
                                        class="font-bold text-gray-800 truncate max-w-[200px] text-right">Não definido</span>
                                </div>
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="text-gray-500 font-semibold">Custo por KM:</span>
                                    <span id="summaryPricePerKm" class="font-bold text-gray-800">R$ 6,00 / km</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- 2. MAPA INTERATIVO CARD (Mobile: Order 2; Desktop: Coluna Direita 7 colunas) -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden order-2 lg:col-span-7 flex flex-col">
                    <!-- Accordion Header -->
                    <button type="button" onclick="toggleMapAccordion()"
                        class="w-full flex items-center justify-between p-3.5 sm:p-4 bg-white hover:bg-gray-50/80 transition cursor-pointer text-left border-b border-gray-100">
                        <span class="text-xs font-bold text-gray-800 uppercase tracking-wider flex items-center gap-2">
                            <i class="fas fa-map-marked-alt text-brand-600 text-sm"></i> Mapa Interativo da Rota
                        </span>
                        <div class="flex items-center gap-2">
                            <span onclick="event.stopPropagation(); fitRouteBounds();" title="Centralizar Rota Completa"
                                class="px-2 py-0.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-[11px] font-bold transition flex items-center gap-1 cursor-pointer">
                                <i class="fas fa-compress-arrows-alt text-[10px]"></i> Centralizar
                            </span>
                            <svg id="mapAccordionArrow" class="w-4 h-4 text-gray-400 transition-transform duration-200 transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>

                    <!-- Accordion Body -->
                    <div id="mapAccordionContent" class="p-2 sm:p-3 flex-1 flex flex-col space-y-2">
                        <?php if (defined('MAP_PROVIDER') && MAP_PROVIDER === 'google_maps'): ?>
                        <div class="bg-brand-50/80 border border-brand-200/70 rounded-lg px-3 py-2 flex items-center justify-between gap-2">
                            <span class="text-[11px] text-brand-900 font-medium leading-tight">
                                💡 <strong>Dica:</strong> Você pode <strong>clicar e arrastar a linha azul</strong> da rota no mapa para alterar trajetos e simular rodovias alternativas.
                            </span>
                        </div>
                        <?php else: ?>
                        <div class="flex items-center justify-between bg-blue-50/70 border border-blue-200 rounded-lg px-3 py-1.5 gap-2">
                            <span class="text-[11px] text-blue-900 font-medium leading-tight flex items-center gap-1.5">
                                <i class="fas fa-route text-blue-600"></i> Traçado via mapa aberto (OSRM).
                            </span>
                            <button type="button" onclick="openGoogleMapsRouteEditor()"
                                class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-[11px] font-bold shadow-xs transition flex items-center gap-1.5 cursor-pointer shrink-0">
                                <i class="fas fa-external-link-alt text-[10px]"></i> Editar a Rota no Google Maps
                            </button>
                        </div>
                        <?php endif; ?>

                        <div class="relative flex-1 w-full overflow-hidden rounded-lg border border-gray-200">
                            <div id="freightMap" class="w-full h-[400px] lg:h-[calc(100vh-230px)] lg:min-h-[560px]"></div>

                            <!-- Map Loading Overlay -->
                            <div id="mapLoadingBadge"
                                class="hidden absolute top-4 left-1/2 -translate-x-1/2 bg-white/95 backdrop-blur-sm border border-gray-300 shadow-lg rounded-full px-4 py-1.5 text-xs font-bold text-brand-900 items-center gap-2 z-20">
                                <svg class="animate-spin h-3.5 w-3.5 text-brand-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                Calculando rota pelo Google Maps...
                </div>

            </div>
        </main>
    </div>

    <?php if (defined('MAP_PROVIDER') && MAP_PROVIDER === 'google_maps'): ?>
    <!-- Google Maps JS API -->
    <script>
        // Global Map & Route Objects (Google Maps)
        let map = null;
        let directionsService = null;
        let directionsRenderer = null;
        let originMarker = null;
        let destMarker = null;

        // Current Selected Points State
        let currentRouteState = {
            origin: {
                type: 'client',
                title: '',
                lat: null,
                lng: null,
                formattedAddress: '',
                phone: ''
            },
            destination: {
                type: 'client',
                title: '',
                lat: null,
                lng: null,
                formattedAddress: '',
                phone: ''
            },
            oneWayDistanceKm: 0,
            roundTripDistanceKm: 0,
            durationText: '',
            pricePerKm: 6.00,
            animalsCount: 0,
            totalFreight: 0,
            costPerAnimal: 0
        };

        // Initialize Google Map
        function initFreightMap() {
            if (map) return;

            const defaultPos = { lat: -22.9068, lng: -47.0616 };
            map = new google.maps.Map(document.getElementById('freightMap'), {
                zoom: 7,
                center: defaultPos,
                mapTypeControl: true,
                streetViewControl: false
            });

            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer({
                map: map,
                draggable: true,
                panel: null
            });

            directionsRenderer.addListener('directions_changed', () => {
                const directions = directionsRenderer.getDirections();
                if (directions && directions.routes && directions.routes.length > 0) {
                    const route = directions.routes[0];
                    let totalMeters = 0;
                    let totalSeconds = 0;
                    route.legs.forEach(leg => {
                        totalMeters += leg.distance.value;
                        totalSeconds += leg.duration.value;
                    });
                    const km = totalMeters / 1000;
                    const hours = Math.floor(totalSeconds / 3600);
                    const minutes = Math.floor((totalSeconds % 3600) / 60);
                    const durationStr = (hours > 0 ? `${hours}h ` : '') + `${minutes}min`;

                    document.getElementById('routeStatusBadge').className = 'text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800';
                    document.getElementById('routeStatusBadge').textContent = 'Rota Traçada ✓';

                    updateRouteMetrics(km, durationStr, true);
                }
            });

            setupAddressAutocomplete('originCustomInput', 'origin');
            setupAddressAutocomplete('destCustomInput', 'destination');

            calculateTotals();
        }
        window.initFreightMap = initFreightMap;

        function setupAddressAutocomplete(inputId, pointType) {
            const inputEl = document.getElementById(inputId);
            if (!inputEl || !window.google || !google.maps || !google.maps.places) return;

            const autocomplete = new google.maps.places.Autocomplete(inputEl, {
                componentRestrictions: { country: "br" }
            });

            autocomplete.addListener("place_changed", () => {
                const place = autocomplete.getPlace();
                if (place.geometry && place.geometry.location) {
                    const lat = place.geometry.location.lat();
                    const lng = place.geometry.location.lng();
                    setPointData(pointType, place.name || inputEl.value, lat, lng, place.formatted_address || inputEl.value, '');
                    requestDirections();
                }
            });
        }

        // Switch between Client / Auction / Custom mode
        function setPointMode(pointType, mode) {
            currentRouteState[pointType].type = mode;

            const isOrigin = pointType === 'origin';
            const prefix = isOrigin ? 'origin' : 'dest';

            document.getElementById(`${prefix}ClientWrapper`).classList.toggle('hidden', mode !== 'client');
            document.getElementById(`${prefix}AuctionWrapper`).classList.toggle('hidden', mode !== 'auction');
            document.getElementById(`${prefix}CustomWrapper`).classList.toggle('hidden', mode !== 'custom');

            const btnClient = document.getElementById(isOrigin ? 'btnOriginClient' : 'btnDestClient');
            const btnAuction = document.getElementById(isOrigin ? 'btnOriginAuction' : 'btnDestAuction');
            const btnCustom = document.getElementById(isOrigin ? 'btnOriginCustom' : 'btnDestCustom');

            const colorClass = isOrigin ? 'text-emerald-800' : 'text-rose-800';
            const defaultTextClass = isOrigin ? 'text-emerald-700' : 'text-rose-700';

            [btnClient, btnAuction, btnCustom].forEach(btn => {
                btn.className = `px-2 py-0.5 rounded ${defaultTextClass} hover:text-gray-900`;
            });

            if (mode === 'client') btnClient.className = `px-2 py-0.5 rounded bg-white ${colorClass} shadow-xs font-bold`;
            if (mode === 'auction') btnAuction.className = `px-2 py-0.5 rounded bg-white ${colorClass} shadow-xs font-bold`;
            if (mode === 'custom') btnCustom.className = `px-2 py-0.5 rounded bg-white ${colorClass} shadow-xs font-bold`;

            if (mode === 'client') {
                handleClientSelect(pointType);
            } else if (mode === 'auction') {
                handleAuctionSelect(pointType);
            } else if (mode === 'custom') {
                const customInput = document.getElementById(`${prefix}CustomInput`);
                if (!customInput.value) {
                    setPointData(pointType, '', null, null, '', '');
                }
            }
        }

        function handleClientSelect(pointType) {
            const isOrigin = pointType === 'origin';
            const selectEl = document.getElementById(isOrigin ? 'originClientSelect' : 'destClientSelect');
            const opt = selectEl.options[selectEl.selectedIndex];

            if (!opt || !opt.value) {
                setPointData(pointType, '', null, null, '', '');
                return;
            }

            const name = opt.getAttribute('data-name');
            const farm = opt.getAttribute('data-farm');
            const city = opt.getAttribute('data-city');
            const uf = opt.getAttribute('data-uf');
            const phone = opt.getAttribute('data-phone') || '';
            const lat = parseFloat(opt.getAttribute('data-lat'));
            const lng = parseFloat(opt.getAttribute('data-lng'));

            const title = name + (farm ? ` (${farm})` : '') + (city ? ` - ${city}/${uf}` : '');

            if (!lat || !lng) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Cliente sem Coordenadas GPS',
                    text: 'Este cliente não possui latitude/longitude cadastradas. Você pode digitar a cidade no modo "Endereço" ou cadastrar as coordenadas na edição do cliente.',
                    confirmButtonColor: '#16a34a'
                });
                setPointData(pointType, title, null, null, (city ? `${city} - ${uf}, Brasil` : ''), phone);
                return;
            }

            setPointData(pointType, title, lat, lng, `${city || ''} ${uf || ''}`, phone);
            requestDirections();
        }

        function handleAuctionSelect(pointType) {
            const isOrigin = pointType === 'origin';
            const selectEl = document.getElementById(isOrigin ? 'originAuctionSelect' : 'destAuctionSelect');
            const opt = selectEl.options[selectEl.selectedIndex];

            if (!opt || !opt.value) {
                setPointData(pointType, '', null, null, '', '');
                return;
            }

            const title = opt.getAttribute('data-title');
            const city = opt.getAttribute('data-city');
            const uf = opt.getAttribute('data-uf');
            const lat = parseFloat(opt.getAttribute('data-lat'));
            const lng = parseFloat(opt.getAttribute('data-lng'));

            const label = 'Leilão: ' + title + (city ? ` (${city}/${uf})` : '');

            if (!lat || !lng) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Leilão sem GPS',
                    text: 'Este leilão não possui localização geográfica cadastrada.',
                    confirmButtonColor: '#16a34a'
                });
                setPointData(pointType, label, null, null, '', '');
                return;
            }

            setPointData(pointType, label, lat, lng, `${city || ''} ${uf || ''}`, '');
            requestDirections();
        }

        function setPointData(pointType, title, lat, lng, formattedAddress, phone = '') {
            currentRouteState[pointType].title = title || '';
            currentRouteState[pointType].lat = lat;
            currentRouteState[pointType].lng = lng;
            currentRouteState[pointType].formattedAddress = formattedAddress || title || '';
            currentRouteState[pointType].phone = phone || '';

            const isOrigin = pointType === 'origin';
            const textEl = document.getElementById(isOrigin ? 'originText' : 'destText');
            const badgeEl = document.getElementById(isOrigin ? 'originCoordsBadge' : 'destCoordsBadge');

            if (title && (lat || formattedAddress)) {
                textEl.textContent = title;
                if (lat && lng) {
                    badgeEl.classList.remove('hidden');
                } else {
                    badgeEl.classList.add('hidden');
                }
            } else {
                textEl.textContent = 'Nenhum local selecionado';
                badgeEl.classList.add('hidden');
            }

            updateSummaryHeader();
        }

        function initSelect2Dropdowns() {
            $('#originClientSelect').select2({
                placeholder: '-- Selecione ou digite para filtrar Cliente de Origem --',
                allowClear: true,
                width: '100%'
            }).on('select2:select select2:clear change', function() {
                handleClientSelect('origin');
            });

            $('#destClientSelect').select2({
                placeholder: '-- Selecione ou digite para filtrar Cliente de Destino --',
                allowClear: true,
                width: '100%'
            }).on('select2:select select2:clear change', function() {
                handleClientSelect('destination');
            });

            $('#originAuctionSelect').select2({
                placeholder: '-- Selecione ou digite para filtrar Leilão de Origem --',
                allowClear: true,
                width: '100%'
            }).on('select2:select select2:clear change', function() {
                handleAuctionSelect('origin');
            });

            $('#destAuctionSelect').select2({
                placeholder: '-- Selecione ou digite para filtrar Leilão de Destino --',
                allowClear: true,
                width: '100%'
            }).on('select2:select select2:clear change', function() {
                handleAuctionSelect('destination');
            });
        }

        $(document).ready(function() {
            initSelect2Dropdowns();
        });

        function swapOriginDestination() {
            const originStateCopy = { ...currentRouteState.origin };
            const destStateCopy = { ...currentRouteState.destination };

            const origClientVal = $('#originClientSelect').val();
            const destClientVal = $('#destClientSelect').val();
            $('#originClientSelect').val(destClientVal).trigger('change.select2');
            $('#destClientSelect').val(origClientVal).trigger('change.select2');

            const origAucVal = $('#originAuctionSelect').val();
            const destAucVal = $('#destAuctionSelect').val();
            $('#originAuctionSelect').val(destAucVal).trigger('change.select2');
            $('#destAuctionSelect').val(origAucVal).trigger('change.select2');

            const origCustomInput = document.getElementById('originCustomInput');
            const destCustomInput = document.getElementById('destCustomInput');
            const origCustomVal = origCustomInput.value;
            const destCustomVal = destCustomInput.value;
            origCustomInput.value = destCustomVal;
            destCustomInput.value = origCustomVal;

            setPointMode('origin', destStateCopy.type);
            setPointMode('destination', originStateCopy.type);

            currentRouteState.origin = destStateCopy;
            currentRouteState.destination = originStateCopy;

            setPointData('origin', destStateCopy.title, destStateCopy.lat, destStateCopy.lng, destStateCopy.formattedAddress, destStateCopy.phone);
            setPointData('destination', originStateCopy.title, originStateCopy.lat, originStateCopy.lng, originStateCopy.formattedAddress, originStateCopy.phone);

            requestDirections();
        }

        function resetCalculator() {
            $('#originClientSelect').val('').trigger('change.select2');
            $('#destClientSelect').val('').trigger('change.select2');
            $('#originAuctionSelect').val('').trigger('change.select2');
            $('#destAuctionSelect').val('').trigger('change.select2');
            document.getElementById('originCustomInput').value = '';
            document.getElementById('destCustomInput').value = '';
            document.getElementById('inputOneWayKm').value = '';
            document.getElementById('inputRoundTripKm').value = '';

            setPointData('origin', '', null, null, '');
            setPointData('destination', '', null, null, '');

            currentRouteState.oneWayDistanceKm = 0;
            currentRouteState.roundTripDistanceKm = 0;
            currentRouteState.durationText = '';

            if (directionsRenderer) {
                directionsRenderer.set('directions', null);
            }

            document.getElementById('routeStatusBadge').className = 'text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800';
            document.getElementById('routeStatusBadge').textContent = 'Aguardando pontos';
            document.getElementById('routeDurationBadge').textContent = 'Tempo estimado: --';

            calculateTotals();
        }

        function toggleAccordion(contentId, arrowId) {
            const content = document.getElementById(contentId);
            const arrow = document.getElementById(arrowId);
            if (!content) return;

            const isHidden = content.classList.contains('hidden');
            if (isHidden) {
                content.classList.remove('hidden');
                if (arrow) arrow.classList.remove('rotate-180');
            } else {
                content.classList.add('hidden');
                if (arrow) arrow.classList.add('rotate-180');
            }
        }

        function toggleMapAccordion() {
            toggleAccordion('mapAccordionContent', 'mapAccordionArrow');
            const content = document.getElementById('mapAccordionContent');
            if (content && !content.classList.contains('hidden')) {
                setTimeout(() => {
                    if (map && window.google && google.maps) {
                        google.maps.event.trigger(map, 'resize');
                        fitRouteBounds();
                    }
                }, 150);
            }
        }

        function shareFreightWhatsApp() {
            const orig = currentRouteState.origin.title || currentRouteState.origin.formattedAddress || 'Ponto A';
            const dest = currentRouteState.destination.title || currentRouteState.destination.formattedAddress || 'Ponto B';
            const oneWayKm = currentRouteState.oneWayDistanceKm.toLocaleString('pt-BR', { minimumFractionDigits: 1 });
            const roundTripKm = currentRouteState.roundTripDistanceKm.toLocaleString('pt-BR', { minimumFractionDigits: 1 });
            const priceKm = currentRouteState.pricePerKm.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            const animals = currentRouteState.animalsCount;
            const totalFreight = currentRouteState.totalFreight.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            const costAnimal = currentRouteState.costPerAnimal.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            const duration = currentRouteState.durationText || 'Não informado';

            if (!currentRouteState.roundTripDistanceKm || currentRouteState.roundTripDistanceKm <= 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'Calcule a rota primeiro',
                    text: 'Selecione a origem e o destino para gerar os valores de frete antes de compartilhar.',
                    confirmButtonColor: '#16a34a'
                });
                return;
            }

            const msg = `*🚚 COTAÇÃO DE FRETE RODOVIÁRIO - VITOR MÜLLER*\n\n` +
                `📍 *Origem:* ${orig}\n` +
                `🏁 *Destino:* ${dest}\n` +
                `⏱️ *Tempo Estimado (Ida):* ${duration}\n\n` +
                `🛣️ *Distância Só Ida:* ${oneWayKm} km\n` +
                `🔄 *Distância Total (Ida e Volta):* ${roundTripKm} km\n` +
                `💵 *Valor por KM:* ${priceKm} / km\n` +
                `🐄 *Quantidade de Animais:* ${animals} cabeças\n\n` +
                `💰 *VALOR TOTAL DO FRETE:* *${totalFreight}*\n` +
                `📊 *CUSTO MÉDIO POR ANIMAL:* *${costAnimal} / cabeça*\n\n` +
                `_Simulação gerada via CRM Vitor Müller._`;

            const encoded = encodeURIComponent(msg);

            let targetPhone = '';
            if (currentRouteState.destination && currentRouteState.destination.phone) {
                const rawPhone = String(currentRouteState.destination.phone).replace(/\D/g, '');
                if (rawPhone.length >= 10) {
                    targetPhone = (rawPhone.startsWith('55') && rawPhone.length > 11) ? rawPhone : ('55' + rawPhone);
                }
            }

            const whatsappUrl = targetPhone 
                ? `https://api.whatsapp.com/send?phone=${targetPhone}&text=${encoded}`
                : `https://api.whatsapp.com/send?text=${encoded}`;

            window.open(whatsappUrl, '_blank');
        }

        function openGoogleMapsRouteEditor() {
            const orig = currentRouteState.origin;
            const dest = currentRouteState.destination;

            let originParam = '';
            if (orig.lat && orig.lng) {
                originParam = `${orig.lat},${orig.lng}`;
            } else if (orig.formattedAddress || orig.title) {
                originParam = encodeURIComponent(orig.formattedAddress || orig.title);
            }

            let destParam = '';
            if (dest.lat && dest.lng) {
                destParam = `${dest.lat},${dest.lng}`;
            } else if (dest.formattedAddress || dest.title) {
                destParam = encodeURIComponent(dest.formattedAddress || dest.title);
            }

            if (!originParam && !destParam) {
                Swal.fire({
                    icon: 'info',
                    title: 'Selecione os Pontos',
                    text: 'Defina ao menos um ponto de origem ou destino para abrir no Google Maps.',
                    confirmButtonColor: '#16a34a'
                });
                return;
            }

            let url = 'https://www.google.com/maps/dir/?api=1';
            if (originParam) url += `&origin=${originParam}`;
            if (destParam) url += `&destination=${destParam}`;

            window.open(url, '_blank');
        }
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&libraries=places&loading=async&callback=initFreightMap" async defer></script>
    <?php else: ?>
    <!-- Leaflet Map Library (100% Gratuito & Open-Source) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        // Global Map & Route Objects
        let map = null;
        let originMarker = null;
        let destMarker = null;
        let routeLayer = null;

        // Custom Pin Icons for Leaflet
        function createPinIcon(letter, bgColor) {
            return L.divIcon({
                className: 'custom-leaflet-pin',
                html: `<div style="
                    background-color: ${bgColor};
                    width: 32px;
                    height: 32px;
                    border-radius: 50% 50% 50% 0;
                    transform: rotate(-45deg);
                    border: 2.5px solid #ffffff;
                    box-shadow: 0 4px 10px rgba(0,0,0,0.35);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                ">
                    <span style="
                        transform: rotate(45deg);
                        color: #ffffff;
                        font-weight: 900;
                        font-size: 13px;
                        font-family: 'Inter', sans-serif;
                    ">${letter}</span>
                </div>`,
                iconSize: [32, 32],
                iconAnchor: [16, 32],
                popupAnchor: [0, -32]
            });
        }

        const iconA = createPinIcon('A', '#16a34a'); // Verde esmeralda
        const iconB = createPinIcon('B', '#e11d48'); // Vermelho rosa

        // Current Selected Points State
        let currentRouteState = {
            origin: {
                type: 'client',
                title: '',
                lat: null,
                lng: null,
                formattedAddress: '',
                phone: ''
            },
            destination: {
                type: 'client',
                title: '',
                lat: null,
                lng: null,
                formattedAddress: '',
                phone: ''
            },
            oneWayDistanceKm: 0,
            roundTripDistanceKm: 0,
            durationText: '',
            pricePerKm: 6.00,
            animalsCount: 0,
            totalFreight: 0,
            costPerAnimal: 0
        };

        // Initialize Leaflet Map
        function initFreightMap() {
            if (map) return;

            // Default center in Brazil (Center / SP region)
            map = L.map('freightMap', {
                zoomControl: true
            }).setView([-22.9068, -47.0616], 7);

            // OpenStreetMap Standard Tiles (100% Free)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            // Setup Free Autocomplete on custom text inputs
            setupAddressAutocomplete('originCustomInput', 'originCustomWrapper', 'origin');
            setupAddressAutocomplete('destCustomInput', 'destCustomWrapper', 'destination');

            // Initial calculation
            calculateTotals();
        }

        // Setup Free Address Autocomplete (Nominatim / OpenStreetMap)
        function setupAddressAutocomplete(inputId, wrapperId, pointType) {
            const inputEl = document.getElementById(inputId);
            const wrapperEl = document.getElementById(wrapperId);
            if (!inputEl || !wrapperEl) return;

            // Create dropdown container
            let dropdown = document.createElement('div');
            dropdown.className = 'autocomplete-dropdown hidden';
            wrapperEl.style.position = 'relative';
            wrapperEl.appendChild(dropdown);

            let debounceTimer = null;

            inputEl.addEventListener('input', () => {
                const query = inputEl.value.trim();
                clearTimeout(debounceTimer);
                if (query.length < 3) {
                    dropdown.classList.add('hidden');
                    dropdown.innerHTML = '';
                    return;
                }

                debounceTimer = setTimeout(() => {
                    fetch(`https://nominatim.openstreetmap.org/search?format=json&countrycodes=br&limit=6&q=${encodeURIComponent(query)}`)
                        .then(res => res.json())
                        .then(data => {
                            dropdown.innerHTML = '';
                            if (!data || data.length === 0) {
                                dropdown.innerHTML = '<div class="px-3 py-2 text-xs text-gray-500">Nenhum endereço encontrado</div>';
                                dropdown.classList.remove('hidden');
                                return;
                            }

                            data.forEach(item => {
                                const opt = document.createElement('div');
                                opt.className = 'autocomplete-item';
                                opt.innerHTML = `<span class="font-bold text-gray-800">${item.display_name.split(',')[0]}</span><span class="text-gray-500 text-[11px] block truncate">${item.display_name}</span>`;
                                opt.addEventListener('click', () => {
                                    inputEl.value = item.display_name;
                                    dropdown.classList.add('hidden');
                                    const lat = parseFloat(item.lat);
                                    const lng = parseFloat(item.lon);
                                    setPointData(pointType, item.display_name.split(',')[0], lat, lng, item.display_name, '');
                                    requestDirections();
                                });
                                dropdown.appendChild(opt);
                            });
                            dropdown.classList.remove('hidden');
                        })
                        .catch(() => {
                            dropdown.classList.add('hidden');
                        });
                }, 350);
            });

            // Close dropdown on outside click
            document.addEventListener('click', (e) => {
                if (!wrapperEl.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });
        }

        // Switch between Client / Auction / Custom mode
        function setPointMode(pointType, mode) {
            currentRouteState[pointType].type = mode;

            const isOrigin = pointType === 'origin';
            const prefix = isOrigin ? 'origin' : 'dest';

            // Toggle wrapper visibility
            document.getElementById(`${prefix}ClientWrapper`).classList.toggle('hidden', mode !== 'client');
            document.getElementById(`${prefix}AuctionWrapper`).classList.toggle('hidden', mode !== 'auction');
            document.getElementById(`${prefix}CustomWrapper`).classList.toggle('hidden', mode !== 'custom');

            // Toggle tab button active styles
            const btnClient = document.getElementById(isOrigin ? 'btnOriginClient' : 'btnDestClient');
            const btnAuction = document.getElementById(isOrigin ? 'btnOriginAuction' : 'btnDestAuction');
            const btnCustom = document.getElementById(isOrigin ? 'btnOriginCustom' : 'btnDestCustom');

            const colorClass = isOrigin ? 'text-emerald-800' : 'text-rose-800';
            const defaultTextClass = isOrigin ? 'text-emerald-700' : 'text-rose-700';

            [btnClient, btnAuction, btnCustom].forEach(btn => {
                btn.className = `px-2 py-0.5 rounded ${defaultTextClass} hover:text-gray-900`;
            });

            if (mode === 'client') btnClient.className = `px-2 py-0.5 rounded bg-white ${colorClass} shadow-xs font-bold`;
            if (mode === 'auction') btnAuction.className = `px-2 py-0.5 rounded bg-white ${colorClass} shadow-xs font-bold`;
            if (mode === 'custom') btnCustom.className = `px-2 py-0.5 rounded bg-white ${colorClass} shadow-xs font-bold`;

            // Clear current point data on mode switch
            if (mode === 'client') {
                handleClientSelect(pointType);
            } else if (mode === 'auction') {
                handleAuctionSelect(pointType);
            } else if (mode === 'custom') {
                const customInput = document.getElementById(`${prefix}CustomInput`);
                if (!customInput.value) {
                    setPointData(pointType, '', null, null, '', '');
                }
            }
        }

        // Handle Client Dropdown Selection
        function handleClientSelect(pointType) {
            const isOrigin = pointType === 'origin';
            const selectEl = document.getElementById(isOrigin ? 'originClientSelect' : 'destClientSelect');
            const opt = selectEl.options[selectEl.selectedIndex];

            if (!opt || !opt.value) {
                setPointData(pointType, '', null, null, '', '');
                return;
            }

            const name = opt.getAttribute('data-name');
            const farm = opt.getAttribute('data-farm');
            const city = opt.getAttribute('data-city');
            const uf = opt.getAttribute('data-uf');
            const phone = opt.getAttribute('data-phone') || '';
            const lat = parseFloat(opt.getAttribute('data-lat'));
            const lng = parseFloat(opt.getAttribute('data-lng'));

            const title = name + (farm ? ` (${farm})` : '') + (city ? ` - ${city}/${uf}` : '');

            if (!lat || !lng) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Cliente sem Coordenadas GPS',
                    text: 'Este cliente não possui latitude/longitude cadastradas. Você pode digitar a cidade no modo "Endereço" ou cadastrar as coordenadas na edição do cliente.',
                    confirmButtonColor: '#16a34a'
                });
                setPointData(pointType, title, null, null, (city ? `${city} - ${uf}, Brasil` : ''), phone);
                return;
            }

            setPointData(pointType, title, lat, lng, `${city || ''} ${uf || ''}`, phone);
            requestDirections();
        }

        // Handle Auction Dropdown Selection
        function handleAuctionSelect(pointType) {
            const isOrigin = pointType === 'origin';
            const selectEl = document.getElementById(isOrigin ? 'originAuctionSelect' : 'destAuctionSelect');
            const opt = selectEl.options[selectEl.selectedIndex];

            if (!opt || !opt.value) {
                setPointData(pointType, '', null, null, '', '');
                return;
            }

            const title = opt.getAttribute('data-title');
            const city = opt.getAttribute('data-city');
            const uf = opt.getAttribute('data-uf');
            const lat = parseFloat(opt.getAttribute('data-lat'));
            const lng = parseFloat(opt.getAttribute('data-lng'));

            const label = 'Leilão: ' + title + (city ? ` (${city}/${uf})` : '');

            if (!lat || !lng) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Leilão sem GPS',
                    text: 'Este leilão não possui localização geográfica cadastrada.',
                    confirmButtonColor: '#16a34a'
                });
                setPointData(pointType, label, null, null, '', '');
                return;
            }

            setPointData(pointType, label, lat, lng, `${city || ''} ${uf || ''}`, '');
            requestDirections();
        }

        // Set Point Data State & Update UI Labels + Leaflet Markers
        function setPointData(pointType, title, lat, lng, formattedAddress, phone = '') {
            currentRouteState[pointType].title = title || '';
            currentRouteState[pointType].lat = lat;
            currentRouteState[pointType].lng = lng;
            currentRouteState[pointType].formattedAddress = formattedAddress || title || '';
            currentRouteState[pointType].phone = phone || '';

            const isOrigin = pointType === 'origin';
            const textEl = document.getElementById(isOrigin ? 'originText' : 'destText');
            const badgeEl = document.getElementById(isOrigin ? 'originCoordsBadge' : 'destCoordsBadge');

            if (title && (lat || formattedAddress)) {
                textEl.textContent = title;
                if (lat && lng) {
                    badgeEl.classList.remove('hidden');
                } else {
                    badgeEl.classList.add('hidden');
                }
            } else {
                textEl.textContent = 'Nenhum local selecionado';
                badgeEl.classList.add('hidden');
            }

            // Update Leaflet Marker
            if (map && lat && lng) {
                if (isOrigin) {
                    if (originMarker) {
                        originMarker.setLatLng([lat, lng]);
                    } else {
                        originMarker = L.marker([lat, lng], { icon: iconA, draggable: true }).addTo(map);
                        originMarker.on('dragend', () => {
                            const newPos = originMarker.getLatLng();
                            currentRouteState.origin.lat = newPos.lat;
                            currentRouteState.origin.lng = newPos.lng;
                            requestDirections();
                        });
                    }
                    originMarker.bindPopup(`<b>Ponto A (Origem):</b><br>${title}`);
                } else {
                    if (destMarker) {
                        destMarker.setLatLng([lat, lng]);
                    } else {
                        destMarker = L.marker([lat, lng], { icon: iconB, draggable: true }).addTo(map);
                        destMarker.on('dragend', () => {
                            const newPos = destMarker.getLatLng();
                            currentRouteState.destination.lat = newPos.lat;
                            currentRouteState.destination.lng = newPos.lng;
                            requestDirections();
                        });
                    }
                    destMarker.bindPopup(`<b>Ponto B (Destino):</b><br>${title}`);
                }
            } else {
                if (isOrigin && originMarker && map) {
                    map.removeLayer(originMarker);
                    originMarker = null;
                }
                if (!isOrigin && destMarker && map) {
                    map.removeLayer(destMarker);
                    destMarker = null;
                }
            }

            updateSummaryHeader();
        }

        function initSelect2Dropdowns() {
            $('#originClientSelect').select2({
                placeholder: '-- Selecione ou digite para filtrar Cliente de Origem --',
                allowClear: true,
                width: '100%'
            }).on('select2:select select2:clear change', function() {
                handleClientSelect('origin');
            });

            $('#destClientSelect').select2({
                placeholder: '-- Selecione ou digite para filtrar Cliente de Destino --',
                allowClear: true,
                width: '100%'
            }).on('select2:select select2:clear change', function() {
                handleClientSelect('destination');
            });

            $('#originAuctionSelect').select2({
                placeholder: '-- Selecione ou digite para filtrar Leilão de Origem --',
                allowClear: true,
                width: '100%'
            }).on('select2:select select2:clear change', function() {
                handleAuctionSelect('origin');
            });

            $('#destAuctionSelect').select2({
                placeholder: '-- Selecione ou digite para filtrar Leilão de Destino --',
                allowClear: true,
                width: '100%'
            }).on('select2:select select2:clear change', function() {
                handleAuctionSelect('destination');
            });
        }

        $(document).ready(function() {
            initSelect2Dropdowns();
        });

        // Invert Origin and Destination
        function swapOriginDestination() {
            const originStateCopy = { ...currentRouteState.origin };
            const destStateCopy = { ...currentRouteState.destination };

            // Swap modes & inputs
            const origClientVal = $('#originClientSelect').val();
            const destClientVal = $('#destClientSelect').val();
            $('#originClientSelect').val(destClientVal).trigger('change.select2');
            $('#destClientSelect').val(origClientVal).trigger('change.select2');

            const origAucVal = $('#originAuctionSelect').val();
            const destAucVal = $('#destAuctionSelect').val();
            $('#originAuctionSelect').val(destAucVal).trigger('change.select2');
            $('#destAuctionSelect').val(origAucVal).trigger('change.select2');

            const origCustomInput = document.getElementById('originCustomInput');
            const destCustomInput = document.getElementById('destCustomInput');
            const origCustomVal = origCustomInput.value;
            const destCustomVal = destCustomInput.value;
            origCustomInput.value = destCustomVal;
            destCustomInput.value = origCustomVal;

            // Set modes
            setPointMode('origin', destStateCopy.type);
            setPointMode('destination', originStateCopy.type);

            currentRouteState.origin = destStateCopy;
            currentRouteState.destination = originStateCopy;

            setPointData('origin', destStateCopy.title, destStateCopy.lat, destStateCopy.lng, destStateCopy.formattedAddress, destStateCopy.phone);
            setPointData('destination', originStateCopy.title, originStateCopy.lat, originStateCopy.lng, originStateCopy.formattedAddress, originStateCopy.phone);

            requestDirections();
        }

        // Request Driving Route using Free OSRM Routing API
        function requestDirections() {
            const orig = currentRouteState.origin;
            const dest = currentRouteState.destination;

            if (!orig.lat || !orig.lng || !dest.lat || !dest.lng) {
                document.getElementById('routeStatusBadge').className = 'text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800';
                document.getElementById('routeStatusBadge').textContent = 'Aguardando 2 pontos';
                return;
            }

            const loadingEl = document.getElementById('mapLoadingBadge');
            if (loadingEl) loadingEl.classList.remove('hidden');

            const osrmUrl = `https://router.project-osrm.org/route/v1/driving/${orig.lng},${orig.lat};${dest.lng},${dest.lat}?overview=full&geometries=geojson`;

            fetch(osrmUrl)
                .then(response => response.json())
                .then(data => {
                    if (loadingEl) loadingEl.classList.add('hidden');

                    if (data.code === 'Ok' && data.routes && data.routes.length > 0) {
                        const route = data.routes[0];
                        const totalMeters = route.distance;
                        const durationSeconds = route.duration;

                        const km = totalMeters / 1000;
                        const hours = Math.floor(durationSeconds / 3600);
                        const minutes = Math.floor((durationSeconds % 3600) / 60);
                        const durationStr = (hours > 0 ? `${hours}h ` : '') + `${minutes}min`;

                        // Remove previous route polyline
                        if (routeLayer && map) {
                            map.removeLayer(routeLayer);
                        }

                        // Draw GeoJSON Route on Leaflet Map
                        routeLayer = L.geoJSON(route.geometry, {
                            style: {
                                color: '#2563eb', // Azul vivo
                                weight: 6,
                                opacity: 0.85
                            }
                        }).addTo(map);

                        // Fit map to route bounds
                        map.fitBounds(routeLayer.getBounds(), { padding: [40, 40] });

                        document.getElementById('routeStatusBadge').className = 'text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800';
                        document.getElementById('routeStatusBadge').textContent = 'Rota Traçada ✓';

                        updateRouteMetrics(km, durationStr, true);
                    } else {
                        throw new Error('No route found');
                    }
                })
                .catch(err => {
                    if (loadingEl) loadingEl.classList.add('hidden');
                    console.error('OSRM Route failed:', err);
                    document.getElementById('routeStatusBadge').className = 'text-[11px] font-bold px-2 py-0.5 rounded-full bg-rose-100 text-rose-800';
                    document.getElementById('routeStatusBadge').textContent = 'Erro ao traçar rota';

                    Swal.fire({
                        icon: 'error',
                        title: 'Não foi possível traçar a rota',
                        text: 'Verifique se as coordenadas de origem e destino são acessíveis por rodovias.',
                        confirmButtonColor: '#16a34a'
                    });
                });
        }

        // Update Distance Metrics & Recalculate
        function updateRouteMetrics(km, durationStr, updateOneWayInput = true) {
            currentRouteState.oneWayDistanceKm = Math.round(km * 10) / 10;
            currentRouteState.roundTripDistanceKm = Math.round((km * 2) * 10) / 10;
            currentRouteState.durationText = durationStr || '';

            if (updateOneWayInput) {
                document.getElementById('inputOneWayKm').value = currentRouteState.oneWayDistanceKm.toFixed(1);
            }
            document.getElementById('inputRoundTripKm').value = currentRouteState.roundTripDistanceKm.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' km';
            document.getElementById('routeDurationBadge').textContent = 'Tempo estimado: ' + (durationStr || '--');

            calculateTotals();
        }

        // Manual Distance Edit by User
        function handleManualDistanceChange() {
            const val = parseFloat(document.getElementById('inputOneWayKm').value) || 0;
            currentRouteState.oneWayDistanceKm = val;
            currentRouteState.roundTripDistanceKm = val * 2;
            document.getElementById('inputRoundTripKm').value = currentRouteState.roundTripDistanceKm.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' km';
            calculateTotals();
        }

        // Currency Formatting Helper
        function handleCurrencyInput(input) {
            let clean = input.value.replace(/\D/g, '');
            if (!clean) {
                input.value = '0,00';
                return;
            }
            let num = (parseInt(clean, 10) / 100).toFixed(2);
            input.value = parseFloat(num).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function parseCurrencyValue(str) {
            if (!str) return 0;
            let clean = str.toString().replace(/\./g, '').replace(',', '.').replace(/[^\d.-]/g, '');
            return parseFloat(clean) || 0;
        }

        // Main Financial Calculator
        function calculateTotals() {
            const kmRoundTrip = currentRouteState.roundTripDistanceKm || 0;
            const priceKm = parseCurrencyValue(document.getElementById('inputPricePerKm').value);
            const animals = parseInt(document.getElementById('inputAnimalCount').value, 10) || 0;

            const totalFreight = kmRoundTrip * priceKm;
            const costPerAnimal = animals > 0 ? (totalFreight / animals) : 0;

            currentRouteState.pricePerKm = priceKm;
            currentRouteState.animalsCount = animals;
            currentRouteState.totalFreight = totalFreight;
            currentRouteState.costPerAnimal = costPerAnimal;

            // Render Results
            document.getElementById('resultTotalFreight').textContent = totalFreight.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            document.getElementById('resultCostPerAnimal').textContent = costPerAnimal.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) + ' / cab.';

            document.getElementById('resultSubcalcFormula').textContent = `${kmRoundTrip.toLocaleString('pt-BR', { minimumFractionDigits: 1 })} km × ${priceKm.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}`;
            document.getElementById('resultAnimalSubcalc').textContent = animals > 0 ? `Rateio em ${animals} animais` : 'Informe a quantidade';

            document.getElementById('summaryPricePerKm').textContent = priceKm.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) + ' / km';
        }

        // Update Summary Header
        function updateSummaryHeader() {
            const orig = currentRouteState.origin.title || 'Origem';
            const dest = currentRouteState.destination.title || 'Destino';
            document.getElementById('summaryRouteDesc').textContent = `${orig} ➔ ${dest}`;
        }

        // Fit Bounds
        function fitRouteBounds() {
            if (routeLayer && map) {
                map.fitBounds(routeLayer.getBounds(), { padding: [40, 40] });
            } else if (originMarker && destMarker && map) {
                const group = L.featureGroup([originMarker, destMarker]);
                map.fitBounds(group.getBounds(), { padding: [40, 40] });
            }
        }

        // Reset All Calculator
        function resetCalculator() {
            $('#originClientSelect').val('').trigger('change.select2');
            $('#destClientSelect').val('').trigger('change.select2');
            $('#originAuctionSelect').val('').trigger('change.select2');
            $('#destAuctionSelect').val('').trigger('change.select2');
            document.getElementById('originCustomInput').value = '';
            document.getElementById('destCustomInput').value = '';
            document.getElementById('inputOneWayKm').value = '';
            document.getElementById('inputRoundTripKm').value = '';

            setPointData('origin', '', null, null, '');
            setPointData('destination', '', null, null, '');

            currentRouteState.oneWayDistanceKm = 0;
            currentRouteState.roundTripDistanceKm = 0;
            currentRouteState.durationText = '';

            if (routeLayer && map) {
                map.removeLayer(routeLayer);
                routeLayer = null;
            }

            document.getElementById('routeStatusBadge').className = 'text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800';
            document.getElementById('routeStatusBadge').textContent = 'Aguardando pontos';
            document.getElementById('routeDurationBadge').textContent = 'Tempo estimado: --';

            calculateTotals();
        }

        // Accordion Toggle Helpers
        function toggleAccordion(contentId, arrowId) {
            const content = document.getElementById(contentId);
            const arrow = document.getElementById(arrowId);
            if (!content) return;

            const isHidden = content.classList.contains('hidden');
            if (isHidden) {
                content.classList.remove('hidden');
                if (arrow) arrow.classList.remove('rotate-180');
            } else {
                content.classList.add('hidden');
                if (arrow) arrow.classList.add('rotate-180');
            }
        }

        function toggleMapAccordion() {
            toggleAccordion('mapAccordionContent', 'mapAccordionArrow');
            const content = document.getElementById('mapAccordionContent');
            if (content && !content.classList.contains('hidden')) {
                setTimeout(() => {
                    if (map) {
                        map.invalidateSize();
                        fitRouteBounds();
                    }
                }, 150);
            }
        }

        // Build & Share via WhatsApp
        function shareFreightWhatsApp() {
            const orig = currentRouteState.origin.title || currentRouteState.origin.formattedAddress || 'Ponto A';
            const dest = currentRouteState.destination.title || currentRouteState.destination.formattedAddress || 'Ponto B';
            const oneWayKm = currentRouteState.oneWayDistanceKm.toLocaleString('pt-BR', { minimumFractionDigits: 1 });
            const roundTripKm = currentRouteState.roundTripDistanceKm.toLocaleString('pt-BR', { minimumFractionDigits: 1 });
            const priceKm = currentRouteState.pricePerKm.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            const animals = currentRouteState.animalsCount;
            const totalFreight = currentRouteState.totalFreight.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            const costAnimal = currentRouteState.costPerAnimal.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            const duration = currentRouteState.durationText || 'Não informado';

            if (!currentRouteState.roundTripDistanceKm || currentRouteState.roundTripDistanceKm <= 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'Calcule a rota primeiro',
                    text: 'Selecione a origem e o destino para gerar os valores de frete antes de compartilhar.',
                    confirmButtonColor: '#16a34a'
                });
                return;
            }

            const msg = `*🚚 COTAÇÃO DE FRETE RODOVIÁRIO - VITOR MÜLLER*\n\n` +
                `📍 *Origem:* ${orig}\n` +
                `🏁 *Destino:* ${dest}\n` +
                `⏱️ *Tempo Estimado (Ida):* ${duration}\n\n` +
                `🛣️ *Distância Só Ida:* ${oneWayKm} km\n` +
                `🔄 *Distância Total (Ida e Volta):* ${roundTripKm} km\n` +
                `💵 *Valor por KM:* ${priceKm} / km\n` +
                `🐄 *Quantidade de Animais:* ${animals} cabeças\n\n` +
                `💰 *VALOR TOTAL DO FRETE:* *${totalFreight}*\n` +
                `📊 *CUSTO MÉDIO POR ANIMAL:* *${costAnimal} / cabeça*\n\n` +
                `_Simulação gerada via CRM Vitor Müller._`;

            const encoded = encodeURIComponent(msg);

            // Obter telefone do cliente de destino se existir
            let targetPhone = '';
            if (currentRouteState.destination && currentRouteState.destination.phone) {
                const rawPhone = String(currentRouteState.destination.phone).replace(/\D/g, '');
                if (rawPhone.length >= 10) {
                    targetPhone = (rawPhone.startsWith('55') && rawPhone.length > 11) ? rawPhone : ('55' + rawPhone);
                }
            }

            const whatsappUrl = targetPhone 
                ? `https://api.whatsapp.com/send?phone=${targetPhone}&text=${encoded}`
                : `https://api.whatsapp.com/send?text=${encoded}`;

            window.open(whatsappUrl, '_blank');
        }

        function openGoogleMapsRouteEditor() {
            const orig = currentRouteState.origin;
            const dest = currentRouteState.destination;

            let originParam = '';
            if (orig.lat && orig.lng) {
                originParam = `${orig.lat},${orig.lng}`;
            } else if (orig.formattedAddress || orig.title) {
                originParam = encodeURIComponent(orig.formattedAddress || orig.title);
            }

            let destParam = '';
            if (dest.lat && dest.lng) {
                destParam = `${dest.lat},${dest.lng}`;
            } else if (dest.formattedAddress || dest.title) {
                destParam = encodeURIComponent(dest.formattedAddress || dest.title);
            }

            if (!originParam && !destParam) {
                Swal.fire({
                    icon: 'info',
                    title: 'Selecione os Pontos',
                    text: 'Defina ao menos um ponto de origem ou destino para abrir no Google Maps.',
                    confirmButtonColor: '#16a34a'
                });
                return;
            }

            let url = 'https://www.google.com/maps/dir/?api=1';
            if (originParam) url += `&origin=${originParam}`;
            if (destParam) url += `&destination=${destParam}`;

            window.open(url, '_blank');
        }

        // Initialize Map on Page Load
        document.addEventListener('DOMContentLoaded', () => {
            initFreightMap();
        });
    </script>
    <?php endif; ?>
</body>

</html>