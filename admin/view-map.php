<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();
$user_id = $_SESSION['user_id'];

$client_ids = !empty($_POST['client_ids']) ? array_map('intval', $_POST['client_ids']) : [];
$auction_ids = !empty($_POST['selected_auctions']) ? array_map('intval', $_POST['selected_auctions']) : [];

if (empty($client_ids) && empty($auction_ids)) {
    header("Location: map-selector.php");
    exit;
}

$validClients = [];
if (!empty($client_ids)) {
    $placeholders = str_repeat('?,', count($client_ids) - 1) . '?';

    // Fetch Selected Clients
    $sql = "SELECT * FROM " . TABLE_NAME . "clients WHERE id IN ($placeholders) AND user_id = ? ORDER BY is_potential DESC, name ASC";
    $stmt = $pdo->prepare($sql);
    $params = array_merge($client_ids, [$user_id]);
    $stmt->execute($params);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Active Intentions for these clients
    $sqlIntentions = "SELECT i.*, cat.name as category_name 
                     FROM " . TABLE_NAME . "intentions i 
                     LEFT JOIN " . TABLE_NAME . "categories cat ON i.category_id = cat.id 
                     WHERE i.client_id IN ($placeholders) AND (i.status = 'active' OR i.status IS NULL)
                     ORDER BY i.created_at DESC";
    $stmtInt = $pdo->prepare($sqlIntentions);
    $stmtInt->execute($client_ids);
    $intentions = $stmtInt->fetchAll(PDO::FETCH_ASSOC);

    // Map intentions to clients
    $clientMap = [];
    foreach ($clients as $client) {
        $client['intentions'] = [];
        $clientMap[$client['id']] = $client;
    }

    foreach ($intentions as $intention) {
        if (isset($clientMap[$intention['client_id']])) {
            $clientMap[$intention['client_id']]['intentions'][] = $intention;
        }
    }

    // Filter clients with valid coordinates
    $validClients = array_values(array_filter($clientMap, function ($c) {
        return !empty($c['latitude']) && !empty($c['longitude']);
    }));
}

$validAuctions = [];
if (!empty($auction_ids)) {
    $aucPlaceholders = str_repeat('?,', count($auction_ids) - 1) . '?';
    $sqlAuc = "SELECT s.*, c.name as client_name 
               FROM " . TABLE_NAME . "schedule s 
               LEFT JOIN " . TABLE_NAME . "clients c ON s.client_id = c.id 
               WHERE s.id IN ($aucPlaceholders) AND s.user_id = ? AND s.type = 'auction' AND s.start_time >= NOW()
               ORDER BY s.start_time ASC";
    $stmtAuc = $pdo->prepare($sqlAuc);
    $paramsAuc = array_merge($auction_ids, [$user_id]);
    $stmtAuc->execute($paramsAuc);
    $auctions = $stmtAuc->fetchAll(PDO::FETCH_ASSOC);

    $validAuctions = array_values(array_filter($auctions, function ($a) {
        return !empty($a['latitude']) && !empty($a['longitude']);
    }));
}

$totalMapItems = count($validClients) + count($validAuctions);
$clientsJson = json_encode($validClients);
$auctionsJson = json_encode($validAuctions);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa Interativo - CRM Vitor Müller</title>
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
    <style>
        html, body {
            height: 100% !important;
            max-height: 100% !important;
            width: 100% !important;
            max-width: 100% !important;
            overflow: hidden !important;
            position: fixed !important;
            inset: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            touch-action: manipulation;
            -webkit-overflow-scrolling: auto;
        }

        #map {
            height: 100% !important;
            width: 100% !important;
            position: absolute;
            inset: 0;
        }

        /* Compact modern SweetAlert2 styles without wasted header space */
        .swal2-container {
            position: fixed !important;
            inset: 0 !important;
            height: 100% !important;
            width: 100% !important;
            z-index: 9999 !important;
            overflow-y: auto !important;
            -webkit-overflow-scrolling: touch;
        }

        body.swal2-shown, html.swal2-shown {
            height: 100% !important;
            max-height: 100% !important;
            overflow: hidden !important;
            position: fixed !important;
            padding-right: 0px !important;
        }

        .swal2-popup {
            padding: 1rem 1.15rem 0.85rem !important;
            border-radius: 1.25rem !important;
            max-width: 440px !important;
            width: 92% !important;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.25) !important;
        }

        .swal2-title {
            padding: 0 !important;
            margin: 0 0 0.4rem 0 !important;
            width: 100% !important;
            display: block !important;
            font-size: 1rem !important;
        }

        .swal2-html-container {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            text-align: left !important;
            overflow-x: hidden !important;
        }

        .swal2-actions {
            margin: 0.6rem 0 0 0 !important;
            width: 100% !important;
            gap: 0.5rem !important;
            display: flex !important;
        }

        .swal2-actions button {
            margin: 0 !important;
            flex: 1 1 0% !important;
            padding: 0.5rem 0.75rem !important;
            font-size: 0.8125rem !important;
            border-radius: 0.75rem !important;
            font-weight: 700 !important;
        }

        .swal2-close {
            top: 0.4rem !important;
            right: 0.4rem !important;
            width: 1.85rem !important;
            height: 1.85rem !important;
            font-size: 1.35rem !important;
            color: #9ca3af !important;
            box-shadow: none !important;
        }
        .swal2-close:hover {
            color: #374151 !important;
        }

        .custom-scroll::-webkit-scrollbar {
            width: 5px;
        }
        .custom-scroll::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 9999px;
        }

        .map-marker-label {
            background-color: rgba(255, 255, 255, 0.95);
            color: #1e293b !important;
            font-family: inherit !important;
            font-weight: 700 !important;
            font-size: 11px !important;
            padding: 2px 6px !important;
            border-radius: 6px !important;
            border: 1px solid rgba(0, 0, 0, 0.18) !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15) !important;
            white-space: nowrap !important;
            pointer-events: none !important;
            transform: translateY(-8px);
        }

        .map-auction-label {
            background-color: rgba(254, 242, 242, 0.98);
            color: #991b1b !important;
            font-family: inherit !important;
            font-weight: 700 !important;
            font-size: 11px !important;
            padding: 2px 6px !important;
            border-radius: 6px !important;
            border: 1px solid #f87171 !important;
            box-shadow: 0 2px 5px rgba(220, 38, 38, 0.25) !important;
            white-space: nowrap !important;
            pointer-events: none !important;
            transform: translateY(-8px);
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- MarkerClusterer Library -->
    <script src="https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js"></script>
</head>

<body class="h-screen w-screen overflow-hidden flex flex-col relative font-sans">

    <!-- Bottom Left Controls (Back button) -->
    <div class="absolute bottom-5 left-3 sm:bottom-6 sm:left-4 z-20 flex items-center">
        <a href="map-selector.php"
            class="bg-white/95 hover:bg-white text-gray-800 font-bold py-2.5 px-4 rounded-xl shadow-lg border border-gray-200/80 flex items-center transition hover:-translate-y-0.5 text-xs sm:text-sm backdrop-blur-sm cursor-pointer">
            <svg class="w-4 h-4 mr-1.5 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18">
                </path>
            </svg>
            Voltar
        </a>
    </div>

    <!-- Top Right Controls (Toggle Drawer Button) -->
    <div class="absolute top-2.5 right-2.5 z-20 flex items-center">
        <button id="toggleListBtn" onclick="toggleSidebar()"
            class="bg-white hover:bg-gray-50 text-gray-800 font-bold h-[42px] px-4 rounded-none shadow-md border border-gray-300 flex items-center transition text-xs sm:text-sm cursor-pointer">
            <svg class="w-4 h-4 mr-1.5 text-brand-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path>
            </svg>
            <span id="listBtnLabel">Ver Lista</span>
            <span class="ml-1.5 bg-brand-100 text-brand-800 text-[11px] font-extrabold px-2 py-0.5 rounded-full"><?php echo $totalMapItems; ?></span>
        </button>
    </div>

    <!-- Map Canvas -->
    <div id="map"></div>

    <!-- Collapsible Sidebar / Drawer for Items List -->
    <div id="clientSidebar" 
         class="fixed top-0 right-0 bottom-0 h-full w-full sm:w-96 bg-white shadow-2xl z-30 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col border-l border-gray-200 invisible pointer-events-none">
        <!-- Sidebar Header -->
        <div class="p-4 border-b border-gray-200 bg-brand-50/80 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-brand-900 text-base">Itens no Mapa</h3>
                <p class="text-xs text-gray-500">Clique para aproximar e ver detalhes</p>
            </div>
            <button onclick="toggleSidebar()" class="p-1.5 text-gray-400 hover:text-gray-700 rounded-lg hover:bg-gray-100 transition cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Status Badges / Quantidade por Status (Above Search Input) -->
        <div class="p-3 border-b border-gray-100 bg-brand-50/40">
            <p class="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Filtro por Categoria / Status</p>
            <div class="flex items-center gap-1.5 flex-wrap" id="statusChipsContainer">
                <!-- Dynamically populated via JS -->
            </div>
        </div>

        <!-- Sidebar Search -->
        <div class="p-3 border-b border-gray-100 bg-white">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </span>
                <input type="text" id="sidebarSearch" onkeyup="filterSidebarList()"
                    placeholder="Filtrar por nome, fazenda, título ou cidade..."
                    class="w-full pl-8 pr-3 py-1.5 rounded-lg border border-gray-300 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500 bg-gray-50/50">
            </div>
        </div>

        <!-- Sidebar Items List -->
        <div class="flex-1 overflow-y-auto p-2 space-y-2 custom-scroll" id="sidebarListContainer">
            <!-- Dynamically populated via JS -->
        </div>
    </div>

    <!-- Overlay on Mobile when Sidebar is Open -->
    <div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/40 z-25 hidden sm:hidden backdrop-blur-xs"></div>

    <script>
        const clients = <?php echo $clientsJson; ?>;
        const auctions = <?php echo $auctionsJson; ?>;
        let mapInstance = null;
        let markersList = [];
        let markerMap = {};
        let auctionMarkerMap = {};
        let clustererInstance = null;
        let isSidebarOpen = false;
        let currentStatusFilter = '';

        // Custom SweetAlert instance configured specifically to avoid breaking full-screen mobile app layout
        const MapSwal = Swal.mixin({
            heightAuto: false,
            scrollbarPadding: false,
            returnFocus: false,
            backdrop: 'rgba(0, 0, 0, 0.45)'
        });

        function getStatusInfo(status) {
            const s = (status || '').toLowerCase().trim();
            if (s === 'novo' || s === 'pré-cadastro' || s === 'precadastro') {
                return { label: 'Novo', color: '#F59E0B', bgClass: 'bg-amber-100 text-amber-800 border-amber-300' };
            }
            if (s === 'atendido') {
                return { label: 'Atendido', color: '#9333EA', bgClass: 'bg-purple-100 text-purple-800 border-purple-300' };
            }
            if (s === 'embral') {
                return { label: 'Embral', color: '#2563EB', bgClass: 'bg-blue-100 text-blue-800 border-blue-300' };
            }
            if (s === 'inativo') {
                return { label: 'Inativo', color: '#6B7280', bgClass: 'bg-gray-100 text-gray-700 border-gray-300' };
            }
            return { label: 'Ativo', color: '#16A34A', bgClass: 'bg-green-100 text-green-800 border-green-300' };
        }

        function createPinIcon(client) {
            const statusInfo = getStatusInfo(client.status);
            const isPotential = client.is_potential == 1;

            const innerIcon = isPotential ? `
                <circle cx="16" cy="14" r="7" fill="#FFFFFF" />
                <path d="M16 8.5l1.6 3.5 3.8.5-2.8 2.7.7 3.8-3.3-1.8-3.3 1.8.7-3.8-2.8-2.7 3.8-.5z" fill="#D97706" />
            ` : `
                <circle cx="16" cy="14" r="5" fill="#FFFFFF" />
            `;

            const svg = `
                <svg xmlns="http://www.w3.org/2000/svg" width="34" height="44" viewBox="0 0 32 42">
                    <defs>
                        <filter id="pinShadow" x="-20%" y="-10%" width="140%" height="140%">
                            <feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-opacity="0.35"/>
                        </filter>
                    </defs>
                    <path d="M16 1C8.268 1 2 7.268 2 15c0 11 14 26 14 26s14-15 14-26c0-7.732-6.268-14-14-14z" 
                          fill="${statusInfo.color}" stroke="#FFFFFF" stroke-width="2" filter="url(#pinShadow)" />
                    ${innerIcon}
                </svg>
            `;

            return {
                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
                scaledSize: new google.maps.Size(34, 44),
                anchor: new google.maps.Point(17, 44),
                labelOrigin: new google.maps.Point(17, -10)
            };
        }

        function createAuctionPinIcon(auction) {
            // Distinct Crimson / Ruby Red Pin with white circle (no inner icon)
            const svg = `
                <svg xmlns="http://www.w3.org/2000/svg" width="34" height="44" viewBox="0 0 32 42">
                    <defs>
                        <filter id="auctionShadow" x="-20%" y="-10%" width="140%" height="140%">
                            <feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-opacity="0.35"/>
                        </filter>
                    </defs>
                    <path d="M16 1C8.268 1 2 7.268 2 15c0 11 14 26 14 26s14-15 14-26c0-7.732-6.268-14-14-14z" 
                          fill="#DC2626" stroke="#FFFFFF" stroke-width="2" filter="url(#auctionShadow)" />
                    <circle cx="16" cy="14" r="5" fill="#FFFFFF" />
                </svg>
            `;

            return {
                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
                scaledSize: new google.maps.Size(34, 44),
                anchor: new google.maps.Point(17, 44),
                labelOrigin: new google.maps.Point(17, -10)
            };
        }

        function showAuctionModal(auctionId) {
            const auction = auctions.find(a => a.id == auctionId);
            if (!auction) return;

            const locParts = [auction.city, auction.uf].filter(Boolean);
            const locStr = locParts.join(' / ');
            const dateObj = new Date(auction.start_time.replace(/-/g, '/'));
            const formattedDate = dateObj.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
            const formattedTime = dateObj.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            const routeUrl = `https://www.google.com/maps/dir/?api=1&destination=${auction.latitude},${auction.longitude}`;

            MapSwal.fire({
                html: `
                    <div class="text-left">
                        <!-- Header with Auction Title & Badge -->
                        <div class="flex items-center justify-between gap-3 w-full border-b border-gray-100 pb-2.5 mb-2.5 pr-6">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span class="text-base font-bold text-gray-900 truncate">${auction.title}</span>
                            </div>
                            <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold border flex-shrink-0 leading-tight bg-red-100 text-red-800 border-red-300">
                                🔨 Leilão
                            </span>
                        </div>

                        <div class="space-y-2 text-xs">
                            <!-- Date & Time Box -->
                            <div class="flex items-center justify-between bg-red-50 p-2.5 rounded-xl border border-red-100 text-red-900 font-semibold">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 mr-1.5 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <span>Data: ${formattedDate}</span>
                                </div>
                                <span class="bg-white/80 px-2 py-0.5 rounded-md border border-red-200 text-xs font-bold">⏰ ${formattedTime}</span>
                            </div>

                            <!-- Location Box -->
                            <div class="bg-gray-50/80 p-2.5 rounded-xl border border-gray-200 space-y-1 text-gray-700">
                                ${locStr ? `
                                    <div class="flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-2 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        <span class="font-semibold text-gray-900">${locStr}</span>
                                    </div>
                                ` : ''}

                                ${auction.address ? `
                                    <p class="text-[11px] text-gray-500 italic pl-5.5">${auction.address}</p>
                                ` : ''}
                            </div>

                            ${auction.client_name ? `
                                <div class="flex items-center bg-gray-50 p-2 rounded-lg border border-gray-200 text-gray-800">
                                    <span class="mr-1.5 font-bold text-gray-500">Cliente Vinculado:</span>
                                    <span class="font-semibold text-gray-900">${auction.client_name}</span>
                                </div>
                            ` : ''}

                            ${auction.observation ? `
                                <div class="p-2.5 bg-amber-50/60 rounded-xl border border-amber-200/60 text-gray-700">
                                    <p class="font-bold text-[10px] uppercase text-amber-900 mb-0.5">Observações:</p>
                                    <p class="italic">${auction.observation}</p>
                                </div>
                            ` : ''}

                            <!-- Action Button -->
                            <div class="pt-1">
                                <a href="${routeUrl}" target="_blank" 
                                   class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 px-3 rounded-xl shadow-xs transition text-xs flex items-center justify-center cursor-pointer">
                                   <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                                   Traçar Rota até o Leilão
                                </a>
                            </div>
                        </div>
                    </div>
                `,
                showCloseButton: true,
                showConfirmButton: false
            });
        }

        function showClientModal(clientId) {
            const client = clients.find(c => c.id == clientId);
            if (!client) return;

            const statusInfo = getStatusInfo(client.status);
            const isPotential = client.is_potential == 1;
            const phoneClean = (client.phone || '').replace(/\D/g, '');
            const locationStr = [client.city, client.uf].filter(Boolean).join(' / ');

            // Intentions HTML
            let intentionsHtml = '';
            const buyIntentions = (client.intentions || []).filter(i => i.type === 'buy');
            const sellIntentions = (client.intentions || []).filter(i => i.type === 'sell');

            if (buyIntentions.length > 0) {
                intentionsHtml += `
                    <div class="mt-2 p-2 bg-blue-50/90 rounded-lg border border-blue-200 text-blue-900 text-left">
                        <h4 class="font-bold text-[11px] uppercase tracking-wide flex items-center text-blue-800">
                            <span class="mr-1">🛒</span> Intenções de Compra:
                        </h4>
                        <ul class="text-[11px] list-disc ml-4 mt-0.5 space-y-0.5 text-blue-950">
                            ${buyIntentions.map(i => `<li><span class="font-semibold">${i.category_name || 'Geral'}:</span> ${i.description}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }

            if (sellIntentions.length > 0) {
                intentionsHtml += `
                    <div class="mt-2 p-2 bg-rose-50/90 rounded-lg border border-rose-200 text-rose-900 text-left">
                        <h4 class="font-bold text-[11px] uppercase tracking-wide flex items-center text-rose-800">
                            <span class="mr-1">🏷️</span> Intenções de Venda:
                        </h4>
                        <ul class="text-[11px] list-disc ml-4 mt-0.5 space-y-0.5 text-rose-950">
                            ${sellIntentions.map(i => `<li><span class="font-semibold">${i.category_name || 'Geral'}:</span> ${i.description}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }

            const whatsappBtn = phoneClean ? `
                <a href="https://wa.me/+55${phoneClean}" target="_blank" 
                   class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-3 rounded-xl shadow-xs hover:shadow transition text-xs flex items-center justify-center cursor-pointer">
                   <svg class="w-4 h-4 mr-1.5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                   Conversar no WhatsApp
                </a>
            ` : '';

            const routeUrl = `https://www.google.com/maps/dir/?api=1&destination=${client.latitude},${client.longitude}`;

            MapSwal.fire({
                html: `
                    <div class="text-left">
                        <!-- Header with Client Name & Status Badge -->
                        <div class="flex items-center justify-between gap-3 w-full border-b border-gray-100 pb-2.5 mb-2.5 pr-6">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span class="text-base font-bold text-gray-900 truncate">${client.name}</span>
                                ${isPotential ? '<span class="text-amber-500 text-sm leading-none flex-shrink-0" title="Cliente em Potencial">⭐</span>' : ''}
                            </div>
                            <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold border flex-shrink-0 leading-tight ${statusInfo.bgClass}">
                                ${statusInfo.label}
                            </span>
                        </div>

                        <div class="space-y-2">
                            ${client.farm_name ? `
                                <div class="flex items-center text-xs font-semibold text-brand-800 bg-brand-50 p-2 rounded-lg border border-brand-100">
                                    <span class="mr-1.5">🏡</span> Fazenda: <span class="ml-1 text-gray-900">${client.farm_name}</span>
                                </div>
                            ` : ''}

                            <!-- Contact & Location Box -->
                            <div class="bg-gray-50/80 p-2.5 rounded-xl border border-gray-200 text-xs space-y-1 text-gray-700">
                                ${client.phone ? `
                                    <div class="flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-2 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                        <span class="font-medium text-gray-900">${client.phone}</span>
                                    </div>
                                ` : ''}

                                ${client.email ? `
                                    <div class="flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-2 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                        <a href="mailto:${client.email}" class="text-blue-600 hover:underline truncate">${client.email}</a>
                                    </div>
                                ` : ''}

                                ${locationStr ? `
                                    <div class="flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-2 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        <span class="font-medium text-gray-900">${locationStr}</span>
                                    </div>
                                ` : ''}

                                ${client.address ? `
                                    <p class="text-[11px] text-gray-500 italic pl-5.5">${client.address}</p>
                                ` : ''}
                            </div>

                            ${intentionsHtml}

                            <!-- Action Buttons -->
                            <div class="pt-1 space-y-1.5">
                                ${whatsappBtn}

                                <div class="grid grid-cols-2 gap-1.5">
                                    <button onclick="showClientInterests(${client.id})" 
                                            class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-2 px-2.5 rounded-xl shadow-xs transition text-xs flex items-center justify-center cursor-pointer">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                                        Perfil Comercial
                                    </button>

                                    <a href="${routeUrl}" target="_blank" 
                                       class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-2.5 rounded-xl shadow-xs transition text-xs flex items-center justify-center cursor-pointer">
                                       <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                                       Traçar Rota
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                `,
                showCloseButton: true,
                showConfirmButton: false
            });
        }

        function initMap() {
            const defaultPos = { lat: -23.550520, lng: -46.633308 };

            mapInstance = new google.maps.Map(document.getElementById("map"), {
                zoom: 12,
                center: defaultPos,
                mapTypeId: "roadmap",
                mapTypeControl: true,
                mapTypeControlOptions: {
                    position: google.maps.ControlPosition.TOP_LEFT
                },
                fullscreenControl: false,
                streetViewControl: false,
                gestureHandling: "greedy"
            });

            const hasClients = clients && clients.length > 0;
            const hasAuctions = auctions && auctions.length > 0;

            if (!hasClients && !hasAuctions) {
                MapSwal.fire({
                    icon: 'warning',
                    title: 'Atenção',
                    text: "Nenhum item selecionado possui coordenadas válidas para exibição no mapa."
                });
                return;
            }

            const bounds = new google.maps.LatLngBounds();

            // Group close/identical coordinates across clients and auctions to fan out slightly
            const coordGroups = {};
            const allItems = [...clients, ...auctions];
            allItems.forEach(item => {
                const key = `${parseFloat(item.latitude).toFixed(4)},${parseFloat(item.longitude).toFixed(4)}`;
                if (!coordGroups[key]) coordGroups[key] = [];
                coordGroups[key].push(item);
            });

            markersList = [];
            markerMap = {};
            auctionMarkerMap = {};

            // 1. Render Client Markers
            clients.forEach(client => {
                const key = `${parseFloat(client.latitude).toFixed(4)},${parseFloat(client.longitude).toFixed(4)}`;
                const group = coordGroups[key];
                let lat = parseFloat(client.latitude);
                let lng = parseFloat(client.longitude);

                if (group && group.length > 1) {
                    const index = group.indexOf(client);
                    const angle = (index / group.length) * (2 * Math.PI);
                    const radius = 0.00035;
                    lat += radius * Math.cos(angle);
                    lng += (radius * 1.2) * Math.sin(angle);
                }

                const position = { lat, lng };

                const marker = new google.maps.Marker({
                    position: position,
                    map: mapInstance,
                    title: `${client.name}${client.farm_name ? ' (' + client.farm_name + ')' : ''}`,
                    icon: createPinIcon(client),
                    label: {
                        text: client.name,
                        color: '#1e293b',
                        fontSize: '11px',
                        fontWeight: '700',
                        className: 'map-marker-label'
                    },
                    animation: google.maps.Animation.DROP
                });

                marker.addListener("click", () => {
                    mapInstance.panTo(position);
                    showClientModal(client.id);
                });

                bounds.extend(position);
                markersList.push(marker);
                markerMap[client.id] = { marker, position, client };
            });

            // 2. Render Auction Markers (Distinct Red Pin with Gavel)
            auctions.forEach(auction => {
                const key = `${parseFloat(auction.latitude).toFixed(4)},${parseFloat(auction.longitude).toFixed(4)}`;
                const group = coordGroups[key];
                let lat = parseFloat(auction.latitude);
                let lng = parseFloat(auction.longitude);

                if (group && group.length > 1) {
                    const index = group.indexOf(auction);
                    const angle = (index / group.length) * (2 * Math.PI);
                    const radius = 0.00035;
                    lat += radius * Math.cos(angle);
                    lng += (radius * 1.2) * Math.sin(angle);
                }

                const position = { lat, lng };

                const marker = new google.maps.Marker({
                    position: position,
                    map: mapInstance,
                    title: `Leilão: ${auction.title}`,
                    icon: createAuctionPinIcon(auction),
                    label: {
                        text: `🔨 ${auction.title}`,
                        color: '#991b1b',
                        fontSize: '11px',
                        fontWeight: '700',
                        className: 'map-auction-label'
                    },
                    zIndex: 999, // Ensure auction pins stand on top
                    animation: google.maps.Animation.DROP
                });

                marker.addListener("click", () => {
                    mapInstance.panTo(position);
                    showAuctionModal(auction.id);
                });

                bounds.extend(position);
                markersList.push(marker);
                auctionMarkerMap[auction.id] = { marker, position, auction };
            });

            // Initialize MarkerClusterer with custom renderer
            if (typeof markerClusterer !== 'undefined' && markerClusterer.MarkerClusterer) {
                clustererInstance = new markerClusterer.MarkerClusterer({
                    map: mapInstance,
                    markers: markersList
                });
            }

            // Adjust map view to encompass all markers
            mapInstance.fitBounds(bounds);

            const listener = google.maps.event.addListener(mapInstance, "idle", function () {
                if (mapInstance.getZoom() > 16) mapInstance.setZoom(16);
                google.maps.event.removeListener(listener);
            });

            // Populate Sidebar List & Status Badges
            renderSidebarList();
            renderStatusChips();
        }

        function getStatusKey(status) {
            const s = (status || '').toLowerCase().trim();
            if (s === 'novo' || s === 'pré-cadastro' || s === 'precadastro') return 'novo';
            if (s === 'atendido') return 'atendido';
            if (s === 'embral') return 'embral';
            if (s === 'inativo') return 'inativo';
            return 'ativo';
        }

        function calculateStatusCounts() {
            const counts = {
                all: clients.length + auctions.length,
                novo: 0,
                atendido: 0,
                embral: 0,
                ativo: 0,
                inativo: 0,
                potential: 0,
                auction: auctions.length
            };

            clients.forEach(c => {
                const k = getStatusKey(c.status);
                if (counts[k] !== undefined) {
                    counts[k]++;
                } else {
                    counts.ativo++;
                }

                if (c.is_potential == 1) {
                    counts.potential++;
                }
            });

            return counts;
        }

        function renderStatusChips() {
            const container = document.getElementById('statusChipsContainer');
            if (!container) return;

            const counts = calculateStatusCounts();

            const chips = [
                { key: '', label: 'Todos', count: counts.all, dotColor: null },
                { key: 'auction', label: 'Leilões', count: counts.auction, dotColor: 'bg-red-600', isAuction: true },
                { key: 'novo', label: 'Novos', count: counts.novo, dotColor: 'bg-amber-500' },
                { key: 'atendido', label: 'Atendidos', count: counts.atendido, dotColor: 'bg-purple-600' },
                { key: 'embral', label: 'Embral', count: counts.embral, dotColor: 'bg-blue-600' },
                { key: 'ativo', label: 'Ativos', count: counts.ativo, dotColor: 'bg-green-600' },
                { key: 'inativo', label: 'Inativos', count: counts.inativo, dotColor: 'bg-gray-500' },
                { key: 'potential', label: 'Potenciais', count: counts.potential, isStar: true }
            ];

            let html = '';
            chips.forEach(chip => {
                if (chip.count === 0 && chip.key !== '') return;

                const isActive = currentStatusFilter === chip.key;
                const activeClasses = isActive
                    ? 'bg-brand-800 text-white font-bold border-brand-800 shadow-xs'
                    : 'bg-white text-gray-700 font-medium border-gray-200/90 hover:bg-gray-50 hover:border-gray-300';
                
                const countBadgeClasses = isActive
                    ? 'bg-white/20 text-white'
                    : 'bg-gray-100 text-gray-700';

                let iconHtml = '';
                if (chip.isAuction) {
                    iconHtml = '<span class="mr-1 text-[11px] leading-none">🔨</span>';
                } else if (chip.isStar) {
                    iconHtml = '<span class="mr-1 text-[11px] leading-none">⭐</span>';
                } else if (chip.dotColor) {
                    iconHtml = `<span class="w-2 h-2 rounded-full ${chip.dotColor} mr-1 inline-block flex-shrink-0"></span>`;
                }

                html += `
                    <button onclick="setStatusFilter('${chip.key}')" 
                            class="inline-flex items-center px-2 py-1 rounded-lg text-[11px] border transition cursor-pointer ${activeClasses}">
                        ${iconHtml}
                        <span>${chip.label}</span>
                        <span class="ml-1.5 px-1.5 py-0.2 rounded-full text-[10px] font-bold ${countBadgeClasses}">${chip.count}</span>
                    </button>
                `;
            });

            container.innerHTML = html;
        }

        function setStatusFilter(statusKey) {
            if (currentStatusFilter === statusKey) {
                currentStatusFilter = '';
            } else {
                currentStatusFilter = statusKey;
            }
            renderStatusChips();
            filterSidebarList();
        }

        function renderSidebarList(filteredClients = null, filteredAuctions = null) {
            const container = document.getElementById('sidebarListContainer');
            if (!container) return;

            const cList = filteredClients !== null ? filteredClients : clients;
            const aList = filteredAuctions !== null ? filteredAuctions : auctions;

            if (cList.length === 0 && aList.length === 0) {
                container.innerHTML = `
                    <div class="p-6 text-center text-gray-400 text-xs">
                        Nenhum item encontrado.
                    </div>
                `;
                return;
            }

            let html = '';

            // 1. Render Auctions if present
            if (aList.length > 0) {
                aList.forEach(a => {
                    const loc = [a.city, a.uf].filter(Boolean).join(' / ');
                    const dateObj = new Date(a.start_time.replace(/-/g, '/'));
                    const formattedDate = dateObj.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
                    const formattedTime = dateObj.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

                    html += `
                        <div onclick="focusAuction(${a.id})" 
                             class="p-3 bg-red-50/50 hover:bg-red-100/50 rounded-xl border border-red-200/80 shadow-xs hover:shadow transition cursor-pointer group">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <h4 class="font-bold text-xs text-red-900 group-hover:text-red-700 transition flex items-center">
                                        <span class="mr-1">🔨</span> ${a.title}
                                    </h4>
                                    <p class="text-[11px] text-red-700 font-semibold mt-0.5">📅 ${formattedDate} às ${formattedTime}</p>
                                    ${loc ? `<p class="text-[11px] text-gray-600 mt-0.5">📍 ${loc}</p>` : ''}
                                </div>
                                <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border leading-tight shrink-0 self-center bg-red-100 text-red-800 border-red-300">
                                    Leilão
                                </span>
                            </div>
                        </div>
                    `;
                });
            }

            // 2. Render Clients
            cList.forEach(c => {
                const statusInfo = getStatusInfo(c.status);
                const isPotential = c.is_potential == 1;
                const loc = [c.city, c.uf].filter(Boolean).join(' / ');

                html += `
                    <div onclick="focusClient(${c.id})" 
                         class="p-3 bg-white hover:bg-brand-50/50 rounded-xl border border-gray-200/80 shadow-xs hover:shadow transition cursor-pointer group">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h4 class="font-bold text-xs text-gray-900 group-hover:text-brand-700 transition flex items-center">
                                    ${c.name} ${isPotential ? '<span class="ml-1 text-amber-500 text-xs">⭐</span>' : ''}
                                </h4>
                                ${c.farm_name ? `<p class="text-[11px] text-brand-800 font-medium mt-0.5">🏡 ${c.farm_name}</p>` : ''}
                                ${loc ? `<p class="text-[11px] text-gray-500 mt-0.5">${loc}</p>` : ''}
                            </div>
                            <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border leading-tight shrink-0 self-center ${statusInfo.bgClass}">
                                ${statusInfo.label}
                            </span>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        function filterSidebarList() {
            const q = document.getElementById('sidebarSearch').value.toLowerCase().trim();

            let filteredClients = [];
            let filteredAuctions = [];

            // Filter auctions
            if (!currentStatusFilter || currentStatusFilter === 'auction') {
                filteredAuctions = auctions.filter(a => {
                    const title = (a.title || '').toLowerCase();
                    const city = (a.city || '').toLowerCase();
                    const uf = (a.uf || '').toLowerCase();
                    const client = (a.client_name || '').toLowerCase();
                    return !q || title.includes(q) || city.includes(q) || uf.includes(q) || client.includes(q);
                });
            }

            // Filter clients
            if (currentStatusFilter !== 'auction') {
                filteredClients = clients.filter(c => {
                    const name = (c.name || '').toLowerCase();
                    const farm = (c.farm_name || '').toLowerCase();
                    const city = (c.city || '').toLowerCase();
                    const uf = (c.uf || '').toLowerCase();
                    const matchesQuery = !q || name.includes(q) || farm.includes(q) || city.includes(q) || uf.includes(q);

                    if (!matchesQuery) return false;
                    if (!currentStatusFilter) return true;

                    if (currentStatusFilter === 'potential') {
                        return c.is_potential == 1;
                    }

                    return getStatusKey(c.status) === currentStatusFilter;
                });
            }

            renderSidebarList(filteredClients, filteredAuctions);
        }

        function focusClient(clientId) {
            const data = markerMap[clientId];
            if (!data) return;

            mapInstance.panTo(data.position);
            mapInstance.setZoom(15);

            // On mobile, close sidebar automatically
            if (window.innerWidth < 640) {
                toggleSidebar(false);
            }

            showClientModal(clientId);
        }

        function focusAuction(auctionId) {
            const data = auctionMarkerMap[auctionId];
            if (!data) return;

            mapInstance.panTo(data.position);
            mapInstance.setZoom(15);

            // On mobile, close sidebar automatically
            if (window.innerWidth < 640) {
                toggleSidebar(false);
            }

            showAuctionModal(auctionId);
        }

        function toggleSidebar(forceState) {
            const sidebar = document.getElementById('clientSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const btnLabel = document.getElementById('listBtnLabel');

            isSidebarOpen = forceState !== undefined ? forceState : !isSidebarOpen;

            if (isSidebarOpen) {
                sidebar.classList.remove('translate-x-full', 'invisible', 'pointer-events-none');
                sidebar.classList.add('translate-x-0', 'visible', 'pointer-events-auto');
                overlay.classList.remove('hidden');
                if (btnLabel) btnLabel.innerText = 'Fechar Lista';
            } else {
                sidebar.classList.remove('translate-x-0', 'visible', 'pointer-events-auto');
                sidebar.classList.add('translate-x-full', 'invisible', 'pointer-events-none');
                overlay.classList.add('hidden');
                if (btnLabel) btnLabel.innerText = 'Ver Lista';
            }
        }

        function showClientInterests(clientId) {
            const client = clients.find(c => c.id == clientId);
            if (!client) return;

            const statusInfo = getStatusInfo(client.status);
            const isPotential = client.is_potential == 1;

            MapSwal.fire({
                html: `
                    <div class="text-left">
                        <!-- Header with Client Name & Status Badge -->
                        <div class="flex items-center justify-between gap-3 w-full border-b border-gray-100 pb-2.5 mb-2.5">
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span class="text-base font-bold text-gray-900 truncate">${client.name}</span>
                                ${isPotential ? '<span class="text-amber-500 text-sm leading-none flex-shrink-0" title="Cliente em Potencial">⭐</span>' : ''}
                            </div>
                            <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold border flex-shrink-0 leading-tight ${statusInfo.bgClass}">
                                ${statusInfo.label}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-1.5 sm:gap-2 text-xs">
                            <div class="bg-amber-50/70 p-2 rounded-lg border border-amber-200/80">
                                <p class="font-bold text-amber-900 text-[10px] uppercase">1. Pagamento</p>
                                <p class="text-gray-900 font-semibold text-xs mt-0.5 break-words">${client.payment_condition || '-'}</p>
                            </div>
                            <div class="bg-amber-50/70 p-2 rounded-lg border border-amber-200/80">
                                <p class="font-bold text-amber-900 text-[10px] uppercase">2. Raças / Máquinas</p>
                                <p class="text-gray-900 font-semibold text-xs mt-0.5 break-words">${client.breed_interests || '-'}</p>
                            </div>
                            <div class="bg-amber-50/70 p-2 rounded-lg border border-amber-200/80 col-span-2">
                                <p class="font-bold text-amber-900 text-[10px] uppercase">3. Categorias de Animais</p>
                                <p class="text-gray-900 font-semibold text-xs mt-0.5 break-words">${client.animal_categories || '-'}</p>
                            </div>
                            <div class="bg-amber-50/70 p-2 rounded-lg border border-amber-200/80">
                                <p class="font-bold text-amber-900 text-[10px] uppercase">4. Sistema de Produção</p>
                                <p class="text-gray-900 font-semibold text-xs mt-0.5 break-words">${client.production_system || '-'}</p>
                            </div>
                            <div class="bg-amber-50/70 p-2 rounded-lg border border-amber-200/80">
                                <p class="font-bold text-amber-900 text-[10px] uppercase">5. Produtor Leite</p>
                                <p class="text-gray-900 font-semibold text-xs mt-0.5 break-words">${client.is_milk_producer || '-'}</p>
                            </div>
                            <div class="bg-amber-50/70 p-2 rounded-lg border border-amber-200/80">
                                <p class="font-bold text-amber-900 text-[10px] uppercase">6. Motivo Aquisição</p>
                                <p class="text-gray-900 font-semibold text-xs mt-0.5 break-words">${client.acquisition_reason || '-'}</p>
                            </div>
                            <div class="bg-amber-50/70 p-2 rounded-lg border border-amber-200/80">
                                <p class="font-bold text-amber-900 text-[10px] uppercase">7. Qtd. a Adquirir</p>
                                <p class="text-gray-900 font-semibold text-xs mt-0.5 break-words">${client.purchase_animal_count || '-'}</p>
                            </div>
                            <div class="bg-amber-50/70 p-2 rounded-lg border border-amber-200/80">
                                <p class="font-bold text-amber-900 text-[10px] uppercase">8. Qtd. Animais Possuídos</p>
                                <p class="text-gray-900 font-semibold text-xs mt-0.5 break-words">${client.animal_count_range || '-'}</p>
                            </div>
                            <div class="bg-amber-50/70 p-2 rounded-lg border border-amber-200/80">
                                <p class="font-bold text-amber-900 text-[10px] uppercase">9. Produção Diária</p>
                                <p class="text-gray-900 font-semibold text-xs mt-0.5 break-words">${client.milk_production_range || '-'}</p>
                            </div>
                        </div>
                    </div>
                `,
                showCloseButton: false,
                showCancelButton: true,
                cancelButtonText: 'Voltar ao Cliente',
                cancelButtonColor: '#9E7005',
                showConfirmButton: true,
                confirmButtonText: 'Fechar',
                confirmButtonColor: '#4A340C'
            }).then((result) => {
                if (result.dismiss === Swal.DismissReason.cancel) {
                    showClientModal(clientId);
                }
            });
        }

        // Expose initMap to global scope
        window.initMap = initMap;
    </script>
    <script
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBaWNV6Gc1D-0ZNrGBXxEe2qwbcw4OhDFo&callback=initMap&v=weekly&loading=async"
        async></script>
</body>

</html>