<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();
$user_id = $_SESSION['user_id'];
$isAdmin = isAdmin();
$pageTitle = 'Agenda';

// Handle Delete Event Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_event'])) {
        $id = intval($_POST['id']);

        if ($isAdmin) {
            $stmt = $pdo->prepare("DELETE FROM " . TABLE_NAME . "schedule WHERE id = ?");
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM " . TABLE_NAME . "schedule WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
        }

        header("Location: schedule.php");
        exit;
    }
}

// Fetch Events (Admin sees all, Operator sees own events + all auction events)
if ($isAdmin) {
    $stmt = $pdo->prepare("
        SELECT s.*, c.name as client_name, u.name as operator_name 
        FROM " . TABLE_NAME . "schedule s 
        LEFT JOIN " . TABLE_NAME . "clients c ON s.client_id = c.id 
        LEFT JOIN " . TABLE_NAME . "users u ON s.user_id = u.id 
        ORDER BY s.start_time ASC
    ");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("
        SELECT s.*, c.name as client_name, u.name as operator_name 
        FROM " . TABLE_NAME . "schedule s 
        LEFT JOIN " . TABLE_NAME . "clients c ON s.client_id = c.id 
        LEFT JOIN " . TABLE_NAME . "users u ON s.user_id = u.id 
        WHERE (s.user_id = ? OR s.type = 'auction')
        ORDER BY s.start_time ASC
    ");
    $stmt->execute([$user_id]);
}
$events = $stmt->fetchAll();

$monthsPt = [
    '1' => 'Jan', '2' => 'Fev', '3' => 'Mar', '4' => 'Abr',
    '5' => 'Mai', '6' => 'Jun', '7' => 'Jul', '8' => 'Ago',
    '9' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'
];

$monthsFullPt = [
    '1' => 'Janeiro', '2' => 'Fevereiro', '3' => 'Março', '4' => 'Abril',
    '5' => 'Maio', '6' => 'Junho', '7' => 'Julho', '8' => 'Agosto',
    '9' => 'Setembro', '10' => 'Outubro', '11' => 'Novembro', '12' => 'Dezembro'
];

$initialStartDate = date('Y-m-d');
$initialEndDate = date('Y-m-d', strtotime('+15 days'));

$colors = [
    'meeting' => 'border-purple-500 bg-purple-50 text-purple-700',
    'visit' => 'border-green-500 bg-green-50 text-green-700',
    'auction' => 'border-orange-500 bg-orange-50 text-orange-700',
    'other' => 'border-gray-500 bg-gray-50 text-gray-700',
];

$typeLabels = [
    'meeting' => 'Reunião',
    'visit' => 'Visita',
    'auction' => 'Leilão',
    'other' => 'Outro',
];

// Build events payload for calendar rendering
$calendarEvents = [];
foreach ($events as $e) {
    $d = new DateTime($e['start_time']);
    $locParts = array_filter([$e['city'] ?? '', $e['uf'] ?? '']);
    $displayLoc = !empty($locParts) ? implode(' / ', $locParts) : '';
    if (empty($displayLoc) && !empty($e['address'])) {
        $displayLoc = trim(explode(',', $e['address'])[0]);
    }
    $hasCoords = (!empty($e['latitude']) && !empty($e['longitude']) && floatval($e['latitude']) != 0 && floatval($e['longitude']) != 0);
    $mapUrl = '';
    if ($hasCoords) {
        $mapUrl = "view-map.php?event_id=" . $e['id'];
    } elseif (!empty($e['client_id'])) {
        $mapUrl = "view-map.php?client_id=" . $e['client_id'];
    } elseif (!empty($e['address']) || !empty($displayLoc)) {
        $mapQuery = !empty($e['address']) ? $e['address'] : $displayLoc;
        $mapUrl = "https://www.google.com/maps/search/?api=1&query=" . urlencode($mapQuery);
    }

    $calendarEvents[] = [
        'id' => (int)$e['id'],
        'title' => $e['title'],
        'type' => $e['type'],
        'typeLabel' => $typeLabels[$e['type']] ?? 'Outro',
        'typeClass' => $colors[$e['type']] ?? $colors['other'],
        'start_time' => $e['start_time'],
        'date' => $d->format('Y-m-d'),
        'year' => (int)$d->format('Y'),
        'month' => (int)$d->format('n'),
        'day' => (int)$d->format('j'),
        'time' => $d->format('H:i'),
        'client_id' => $e['client_id'] ? (int)$e['client_id'] : null,
        'client_name' => $e['client_name'] ?? '',
        'city' => $e['city'] ?? '',
        'uf' => $e['uf'] ?? '',
        'displayLoc' => $displayLoc,
        'address' => $e['address'] ?? '',
        'mapUrl' => $mapUrl,
        'banner_image' => $e['banner_image'] ?? '',
        'auction_lots_link' => $e['auction_lots_link'] ?? '',
        'auction_live_link' => $e['auction_live_link'] ?? '',
        'observation' => $e['observation'] ?? '',
        'canEdit' => $isAdmin || ($e['user_id'] == $user_id)
    ];
}
$eventsJson = json_encode($calendarEvents, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda - CRM Vitor Müller</title>
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
                            950: '#170F03',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        #eventsContainer {
            visibility: hidden;
        }
        #eventsContainer.ready {
            visibility: visible;
        }
        .cal-day-cell {
            min-height: 85px;
            transition: all 0.15s ease;
        }
        @media (min-width: 640px) {
            .cal-day-cell {
                min-height: 115px;
            }
        }
    </style>
</head>

<body class="bg-brand-50 font-sans leading-normal tracking-normal">
    <div class="relative min-h-screen md:flex">
        <?php include '../components/sidebar.php'; ?>
        <div class="flex-1 flex flex-col min-h-screen overflow-hidden">
            <?php include '../components/header.php'; ?>
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-brand-50 p-3 sm:p-6">

                <!-- Header com Título, Switcher de Visualização e Botão Novo (Lado a Lado no Mobile e Desktop) -->
                <div class="flex items-center justify-between gap-2 mb-4 sm:mb-6">
                    <h1 class="text-xl sm:text-3xl font-bold text-brand-900 tracking-tight">Agenda</h1>

                    <div class="flex items-center gap-1.5 sm:gap-3">
                        <!-- Segmented Switcher: Lista vs Calendário -->
                        <div class="inline-flex bg-brand-100/80 p-0.5 sm:p-1 rounded-xl border border-brand-200 shadow-inner">
                            <button type="button" id="viewListBtn" onclick="switchView('list')"
                                class="px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-lg text-xs sm:text-sm font-bold transition flex items-center gap-1 cursor-pointer bg-white text-brand-900 shadow-xs">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                                </svg>
                                <span>Lista</span>
                            </button>
                            <button type="button" id="viewCalendarBtn" onclick="switchView('calendar')"
                                class="px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-lg text-xs sm:text-sm font-bold transition flex items-center gap-1 cursor-pointer text-brand-700 hover:text-brand-900">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <span>Calendário</span>
                            </button>
                        </div>

                        <!-- Botão Novo (Sempre ao lado do seletor na mesma linha) -->
                        <a href="schedule-add.php"
                            class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-1.5 sm:py-2 px-3 sm:px-4 rounded-xl shadow-md transition transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center cursor-pointer text-xs sm:text-sm whitespace-nowrap">
                            <svg class="w-4 h-4 sm:mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <span>Novo</span>
                        </a>
                    </div>
                </div>

                <!-- ============================================================= -->
                <!-- 1. MODO LISTA (Com Filtro de Busca, Tipo e Período)            -->
                <!-- ============================================================= -->
                <div id="listViewContainer">
                    <!-- Barra de Filtros Profissional (Modo Lista) -->
                    <div class="mb-5 bg-white p-2.5 sm:p-3 rounded-2xl shadow-xs border border-brand-200/80 flex flex-col md:flex-row gap-2.5 items-stretch md:items-center justify-between">
                        <!-- Busca + Seletor de Tipo -->
                        <div class="flex items-center gap-2 w-full md:w-auto flex-1 max-w-2xl">
                            <!-- Search Input -->
                            <div class="relative w-1/2 md:flex-1">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 sm:pl-3 pointer-events-none text-gray-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </span>
                                <input type="text" id="searchInput"
                                    class="w-full pl-8 sm:pl-9 pr-2 sm:pr-3 py-1.5 sm:py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-brand-500 text-xs sm:text-sm bg-gray-50/50 hover:bg-white focus:bg-white transition"
                                    placeholder="Buscar...">
                            </div>

                            <!-- Type Selector -->
                            <div class="w-1/2 md:w-44 flex-shrink-0">
                                <select id="typeFilterInput" onchange="onTypeFilterChange(this.value)"
                                    class="w-full px-2 sm:px-3 py-1.5 sm:py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-brand-500 text-xs sm:text-sm bg-gray-50/50 hover:bg-white focus:bg-white font-medium text-gray-700 cursor-pointer truncate transition">
                                    <option value="">Todos os Tipos</option>
                                    <option value="meeting">🟣 Reunião</option>
                                    <option value="visit">🟢 Visita</option>
                                    <option value="auction">🟠 Leilão</option>
                                    <option value="other">⚪ Outro</option>
                                </select>
                            </div>
                        </div>

                        <!-- Período de Datas -->
                        <div class="flex items-center gap-1.5 w-full md:w-auto flex-shrink-0 bg-gray-50/80 p-1 rounded-xl border border-gray-200 justify-between">
                            <span class="text-[11px] font-bold text-gray-400 uppercase px-1.5 hidden sm:inline">Período</span>
                            <input type="date" id="startDateInput"
                                class="w-[45%] md:w-32 px-2 py-1 rounded-lg border border-transparent hover:border-gray-300 focus:border-brand-500 focus:bg-white focus:outline-none text-xs font-semibold text-gray-700 cursor-pointer text-center bg-transparent"
                                value="<?php echo $initialStartDate; ?>" title="Data Inicial">
                            <span class="text-gray-400 font-bold text-xs">→</span>
                            <input type="date" id="endDateInput"
                                class="w-[45%] md:w-32 px-2 py-1 rounded-lg border border-transparent hover:border-gray-300 focus:border-brand-500 focus:bg-white focus:outline-none text-xs font-semibold text-gray-700 cursor-pointer text-center bg-transparent"
                                value="<?php echo $initialEndDate; ?>" title="Data Final">
                        </div>
                    </div>

                    <!-- Event List (Simple Timeline) -->
                    <div class="space-y-4" id="eventsContainer">
                        <?php if (count($events) > 0): ?>
                        <?php foreach ($events as $event):
                            $date = new DateTime($event['start_time']);
                            $eventDateStr = $date->format('Y-m-d');
                            $isPast = $date < new DateTime();
                            $typeClass = $colors[$event['type']] ?? $colors['other'];
                            $typeLabel = $typeLabels[$event['type']] ?? 'Outro';
                            $monthShort = $monthsPt[$date->format('n')] ?? $date->format('M');

                            $locParts = array_filter([$event['city'] ?? '', $event['uf'] ?? '']);
                            $displayLoc = !empty($locParts) ? implode(' / ', $locParts) : '';
                            if (empty($displayLoc) && !empty($event['address'])) {
                                $displayLoc = trim(explode(',', $event['address'])[0]);
                            }

                            $hasCoords = (!empty($event['latitude']) && !empty($event['longitude']) && floatval($event['latitude']) != 0 && floatval($event['longitude']) != 0);
                            $mapUrl = '';
                            if ($hasCoords) {
                                $mapUrl = "view-map.php?event_id=" . $event['id'];
                            } elseif (!empty($event['client_id'])) {
                                $mapUrl = "view-map.php?client_id=" . $event['client_id'];
                            } elseif (!empty($event['address']) || !empty($displayLoc)) {
                                $mapQuery = !empty($event['address']) ? $event['address'] : $displayLoc;
                                $mapUrl = "https://www.google.com/maps/search/?api=1&query=" . urlencode($mapQuery);
                            }
                            $canEdit = $isAdmin || ($event['user_id'] == $user_id);
                        ?>
                        <div id="event-<?php echo $event['id']; ?>" class="flex items-start <?php echo $isPast ? 'opacity-60' : ''; ?> event-row group transition duration-300 rounded-2xl p-0.5 sm:p-1" data-id="<?php echo $event['id']; ?>" data-date="<?php echo $eventDateStr; ?>" data-type="<?php echo $event['type']; ?>">
                            <div class="flex flex-col items-center mr-2 sm:mr-4 w-11 sm:w-16 flex-shrink-0 text-center select-none pt-0.5">
                                <div class="text-[11px] sm:text-xs font-bold text-gray-500 uppercase tracking-wide">
                                    <?php echo $monthShort; ?>
                                </div>
                                <div class="text-xl sm:text-2xl font-bold text-gray-800 leading-none my-0.5">
                                    <?php echo $date->format('d'); ?>
                                </div>
                                <div class="text-[11px] sm:text-xs text-gray-500 font-semibold">
                                    <?php echo $date->format('H:i'); ?>
                                </div>
                            </div>
                            <div
                                class="flex-1 bg-white rounded-xl shadow-md p-3.5 sm:p-4 border-l-4 <?php echo explode(' ', $typeClass)[0]; ?> hover:shadow-lg transition min-w-0">
                                <!-- Linha 1: Título no canto esquerdo e Tipo no canto direito -->
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="text-base sm:text-lg font-bold text-gray-900 event-title leading-snug">
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </h3>
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-[10px] sm:text-xs font-bold uppercase <?php echo $typeClass; ?> event-type border border-current/20 flex-shrink-0 whitespace-nowrap">
                                        <?php echo $typeLabel; ?>
                                    </span>
                                </div>

                                <!-- Linha 2: Localização (e Cliente se houver) à esquerda / Botões de Ação ou Somente Visualização à direita -->
                                <div class="flex items-center justify-between gap-2 mt-1.5 pt-0.5">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-600 min-w-0">
                                        <?php if ($event['client_name']): ?>
                                        <span class="flex items-center font-medium event-client truncate" title="<?php echo htmlspecialchars($event['client_name']); ?>">
                                            <svg class="w-3.5 h-3.5 mr-1 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                                                </path>
                                            </svg>
                                            <?php if (!empty($event['client_id'])): ?>
                                                <a href="client-details.php?id=<?php echo $event['client_id']; ?>" class="text-brand-700 hover:text-brand-900 font-semibold hover:underline truncate">
                                                    <?php echo htmlspecialchars($event['client_name']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="truncate"><?php echo htmlspecialchars($event['client_name']); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <?php endif; ?>

                                        <?php if (!empty($displayLoc)): ?>
                                        <span class="flex items-center event-location truncate">
                                            <svg class="w-3.5 h-3.5 mr-1 text-brand-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            </svg>
                                            <?php if (!empty($mapUrl)): ?>
                                                <a href="<?php echo htmlspecialchars($mapUrl); ?>"
                                                    <?php echo str_starts_with($mapUrl, 'http') ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
                                                    class="text-brand-700 hover:text-brand-900 font-semibold hover:underline inline-flex items-center gap-1 group/loc truncate"
                                                    title="<?php echo htmlspecialchars(!empty($event['address']) ? $event['address'] . ' • Clique para abrir o mapa' : 'Ver ' . $displayLoc . ' no mapa'); ?>">
                                                    <span class="truncate"><?php echo htmlspecialchars($displayLoc); ?></span>
                                                    <svg class="w-3 h-3 text-brand-500 group-hover/loc:translate-x-0.5 transition flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                                    </svg>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-700 font-medium truncate"><?php echo htmlspecialchars($displayLoc); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Botões de Ação no canto direito da Linha 2 -->
                                    <div class="flex items-center gap-1 flex-shrink-0 ml-auto">
                                        <?php if ($canEdit): ?>
                                            <a href="schedule-edit.php?id=<?php echo $event['id']; ?>"
                                                class="text-blue-500 hover:text-blue-700 p-1 rounded-lg hover:bg-blue-50 transition cursor-pointer" title="Editar Compromisso">
                                                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                    </path>
                                                </svg>
                                            </a>
                                            <form method="POST" onsubmit="confirmDelete(event)" class="inline">
                                                <input type="hidden" name="delete_event" value="1">
                                                <input type="hidden" name="id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" class="text-red-500 hover:text-red-700 p-1 rounded-lg hover:bg-red-50 transition cursor-pointer"
                                                    title="Excluir Compromisso">
                                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                        </path>
                                                    </svg>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <div class="flex items-center gap-1 text-[11px] text-gray-400 italic">
                                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                                <span class="hidden sm:inline">Somente visualização</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Linha 3: Imagem do Banner (se houver) -->
                                <?php if (!empty($event['banner_image'])): ?>
                                    <div class="mt-3 mb-2 rounded-xl overflow-hidden border border-amber-200 shadow-sm max-w-sm">
                                        <a href="../<?php echo htmlspecialchars($event['banner_image']); ?>" target="_blank" title="Clique para ampliar o banner">
                                            <img src="../<?php echo htmlspecialchars($event['banner_image']); ?>" alt="Banner do Leilão" class="w-full h-auto object-cover max-h-48 hover:scale-102 transition duration-200">
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <!-- Linha 4: Botões Vídeo Lotes e Ao Vivo -->
                                <?php if ($event['type'] === 'auction' && (!empty($event['auction_lots_link']) || !empty($event['auction_live_link']))): ?>
                                    <div class="flex flex-wrap items-center gap-2 mt-2.5 pt-2 border-t border-amber-100">
                                        <?php if (!empty($event['auction_lots_link'])): ?>
                                            <a href="<?php echo htmlspecialchars($event['auction_lots_link']); ?>" target="_blank"
                                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg text-xs shadow-sm hover:shadow transition transform hover:-translate-y-0.5"
                                                title="Ver Vídeo dos Lotes">
                                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span>Vídeo Lotes</span>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($event['auction_live_link'])): ?>
                                            <a href="<?php echo htmlspecialchars($event['auction_live_link']); ?>" target="_blank"
                                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg text-xs shadow-sm hover:shadow transition transform hover:-translate-y-0.5"
                                                title="Assistir Transmissão Ao Vivo">
                                                <svg class="w-3.5 h-3.5 text-white animate-pulse" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/>
                                                </svg>
                                                <span>Ao Vivo</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Linha 5: Observações -->
                                <?php if ($event['observation']): ?>
                                <p class="text-xs sm:text-sm text-gray-600 mt-2 italic bg-gray-50 p-2 rounded-lg border border-gray-100">
                                    "<?php echo htmlspecialchars($event['observation']); ?>"
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="text-center text-gray-500 py-12 bg-white rounded-xl shadow-sm">
                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <p class="font-medium text-gray-600">Nenhum compromisso agendado.</p>
                            <a href="schedule-add.php" class="text-brand-600 hover:underline text-sm font-semibold mt-2 inline-block">Criar primeiro compromisso</a>
                        </div>
                        <?php endif; ?>
                        <div id="noResults" class="hidden text-center text-gray-500 py-10 bg-white rounded-xl shadow-sm">
                            Nenhum compromisso encontrado para o período ou termo pesquisado.
                        </div>
                    </div>
                </div>

                <!-- ============================================================= -->
                <!-- 2. MODO CALENDÁRIO (Navegação Mês a Mês)                     -->
                <!-- ============================================================= -->
                <div id="calendarViewContainer" class="hidden space-y-4">
                    <!-- Barra de Controle do Mês (Modo Calendário) -->
                    <div class="bg-white rounded-2xl shadow-xs border border-brand-200/80 p-2.5 sm:p-3 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-2.5 sm:gap-3">
                        <!-- Navegação de Meses (Esquerda) -->
                        <div class="flex items-center justify-between md:justify-start gap-2 sm:gap-3">
                            <div class="flex items-center gap-1 bg-gray-50 p-0.5 rounded-xl border border-gray-200">
                                <button type="button" onclick="prevMonth()" title="Mês Anterior"
                                    class="p-1.5 sm:p-2 rounded-lg hover:bg-white hover:text-brand-800 text-gray-600 transition cursor-pointer active:scale-95">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
                                    </svg>
                                </button>
                                <button type="button" onclick="nextMonth()" title="Próximo Mês"
                                    class="p-1.5 sm:p-2 rounded-lg hover:bg-white hover:text-brand-800 text-gray-600 transition cursor-pointer active:scale-95">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </button>
                            </div>

                            <h2 id="calendarMonthTitle" class="text-base sm:text-lg font-extrabold text-brand-900 tracking-tight min-w-[140px] text-center md:text-left">
                                <!-- Preenchido via JS: Ex: Setembro de 2026 -->
                            </h2>

                            <button type="button" onclick="goToToday()"
                                class="px-2.5 sm:px-3 py-1.5 rounded-xl border border-brand-200 bg-brand-50/80 text-brand-800 hover:bg-brand-100 font-bold text-xs transition cursor-pointer shadow-2xs">
                                Hoje
                            </button>
                        </div>

                        <!-- Seletor de Tipo e Legenda (Direita) -->
                        <div class="flex items-center justify-between md:justify-end gap-2 sm:gap-3">
                            <!-- Seletor de Tipo -->
                            <div class="w-1/2 md:w-44 flex-shrink-0">
                                <select id="calTypeFilterInput" onchange="onTypeFilterChange(this.value)"
                                    class="w-full px-2.5 sm:px-3 py-1.5 sm:py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-brand-500 text-xs sm:text-sm bg-gray-50/50 hover:bg-white focus:bg-white font-medium text-gray-700 cursor-pointer truncate transition">
                                    <option value="">Todos os Tipos</option>
                                    <option value="meeting">🟣 Reunião</option>
                                    <option value="visit">🟢 Visita</option>
                                    <option value="auction">🟠 Leilão</option>
                                    <option value="other">⚪ Outro</option>
                                </select>
                            </div>

                            <!-- Legenda de Cores -->
                            <div class="w-1/2 md:w-auto flex items-center justify-between sm:justify-start gap-1 sm:gap-2.5 flex-wrap text-[10px] sm:text-xs font-semibold text-gray-600 bg-gray-50/80 p-1.5 sm:px-3 rounded-xl border border-gray-200 min-w-0">
                                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-purple-500 flex-shrink-0"></span> Reunião</span>
                                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0"></span> Visita</span>
                                <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-orange-500 flex-shrink-0"></span> Leilão</span>
                                <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-gray-500 flex-shrink-0"></span> Outro</span>
                            </div>
                        </div>
                    </div>

                    <!-- Grade do Calendário Mensal -->
                    <div class="bg-white rounded-2xl shadow-md border border-brand-200 overflow-hidden">
                        <!-- Cabeçalho dos Dias da Semana -->
                        <div class="grid grid-cols-7 bg-brand-100/60 border-b border-brand-200 text-center text-[11px] sm:text-xs font-bold text-brand-900 uppercase tracking-wider py-2 sm:py-2.5 select-none">
                            <div><span class="hidden sm:inline">Domingo</span><span class="sm:hidden">Dom</span></div>
                            <div><span class="hidden sm:inline">Segunda</span><span class="sm:hidden">Seg</span></div>
                            <div><span class="hidden sm:inline">Terça</span><span class="sm:hidden">Ter</span></div>
                            <div><span class="hidden sm:inline">Quarta</span><span class="sm:hidden">Qua</span></div>
                            <div><span class="hidden sm:inline">Quinta</span><span class="sm:hidden">Qui</span></div>
                            <div><span class="hidden sm:inline">Sexta</span><span class="sm:hidden">Sex</span></div>
                            <div><span class="hidden sm:inline">Sábado</span><span class="sm:hidden">Sáb</span></div>
                        </div>

                        <!-- Dias do Mês (Gerados via JS) -->
                        <div id="calendarGrid" class="grid grid-cols-7 divide-x divide-y divide-gray-200 text-xs">
                            <!-- Gerado dinamicamente via JS -->
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- ============================================================= -->
    <!-- MODAL DE DETALHES DO EVENTO (CALENDÁRIO)                      -->
    <!-- ============================================================= -->
    <div id="eventDetailModal" class="fixed inset-0 z-[2000] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 hidden transition-opacity duration-200">
        <div class="bg-white rounded-2xl shadow-2xl border border-brand-200 max-w-lg w-full overflow-hidden transform transition-all duration-200 max-h-[90vh] flex flex-col">
            <!-- Modal Header -->
            <div id="modalHeaderBg" class="p-4 border-b flex items-start justify-between gap-3 bg-brand-50/80">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span id="modalTypeBadge" class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border"></span>
                        <span id="modalDateTime" class="text-xs font-bold text-gray-600"></span>
                    </div>
                    <h3 id="modalTitle" class="text-lg font-bold text-gray-900 leading-snug"></h3>
                </div>
                <button type="button" onclick="closeEventModal()" class="text-gray-400 hover:text-gray-700 p-1.5 rounded-lg hover:bg-gray-100 transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="p-4 overflow-y-auto space-y-3.5 text-xs sm:text-sm">
                <!-- Cliente -->
                <div id="modalClientContainer" class="flex items-center gap-2 text-gray-700">
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <span class="font-semibold text-gray-500">Cliente:</span>
                    <a id="modalClientLink" href="#" class="text-brand-700 hover:text-brand-900 font-bold hover:underline"></a>
                    <span id="modalClientText" class="font-bold text-gray-800"></span>
                </div>

                <!-- Localização -->
                <div id="modalLocationContainer" class="flex items-start gap-2 text-gray-700">
                    <svg class="w-4 h-4 text-brand-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="font-semibold text-gray-500">Local:</span>
                            <a id="modalLocationLink" href="#" class="text-brand-700 hover:text-brand-900 font-bold hover:underline inline-flex items-center gap-1">
                                <span id="modalLocationText"></span>
                                <svg class="w-3 h-3 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                            </a>
                        </div>
                        <p id="modalFullAddress" class="text-[11px] text-gray-500 mt-0.5"></p>
                    </div>
                </div>

                <!-- Banner Imagem -->
                <div id="modalBannerContainer" class="hidden rounded-xl overflow-hidden border border-amber-200 shadow-sm max-w-sm mx-auto">
                    <a id="modalBannerLink" href="#" target="_blank" title="Clique para ampliar o banner">
                        <img id="modalBannerImg" src="" alt="Banner do Leilão" class="w-full h-auto object-cover max-h-48 hover:scale-102 transition duration-200">
                    </a>
                </div>

                <!-- Ações do Leilão (Vídeo Lotes e Ao Vivo) -->
                <div id="modalAuctionLinksContainer" class="hidden flex flex-wrap items-center gap-2 pt-2 border-t border-amber-100">
                    <a id="modalLotsLink" href="#" target="_blank"
                        class="hidden inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg text-xs shadow-sm hover:shadow transition">
                        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Vídeo Lotes</span>
                    </a>
                    <a id="modalLiveLink" href="#" target="_blank"
                        class="hidden inline-flex items-center gap-1.5 px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg text-xs shadow-sm hover:shadow transition">
                        <svg class="w-3.5 h-3.5 text-white animate-pulse" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/>
                        </svg>
                        <span>Ao Vivo</span>
                    </a>
                </div>

                <!-- Observação -->
                <div id="modalObservationContainer" class="hidden bg-gray-50 p-2.5 rounded-lg border border-gray-100 text-gray-700 italic">
                    <p id="modalObservationText"></p>
                </div>
            </div>

            <!-- Modal Footer com Botões de Ação -->
            <div class="p-3 bg-gray-50 border-t border-gray-200 flex items-center justify-between gap-2">
                <div id="modalOwnerActions" class="flex items-center gap-2">
                    <a id="modalEditBtn" href="#"
                        class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg text-xs transition flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Editar
                    </a>
                    <form id="modalDeleteForm" method="POST" onsubmit="confirmDelete(event)" class="inline">
                        <input type="hidden" name="delete_event" value="1">
                        <input type="hidden" name="id" id="modalDeleteId" value="">
                        <button type="submit" class="px-3.5 py-1.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg text-xs transition flex items-center gap-1 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Excluir
                        </button>
                    </form>
                </div>
                <div id="modalViewOnlyMsg" class="hidden text-xs text-gray-400 italic">
                    👁️ Somente visualização
                </div>
                <button type="button" onclick="closeEventModal()" class="ml-auto px-4 py-1.5 bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold rounded-lg text-xs transition cursor-pointer">
                    Fechar
                </button>
            </div>
        </div>
    </div>

    <!-- Dados dos Eventos em JSON para o Calendário -->
    <script>
        const ALL_EVENTS = <?php echo $eventsJson; ?>;
        const MONTH_NAMES = [
            'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
        ];

        let currentCalendarDate = new Date();
        let currentView = 'list';

        // ==========================================
        // SWITCH VIEW (LISTA vs CALENDÁRIO)
        // ==========================================
        function switchView(viewName) {
            currentView = viewName;
            const listBtn = document.getElementById('viewListBtn');
            const calBtn = document.getElementById('viewCalendarBtn');
            const listContainer = document.getElementById('listViewContainer');
            const calContainer = document.getElementById('calendarViewContainer');

            if (viewName === 'calendar') {
                listContainer.classList.add('hidden');
                calContainer.classList.remove('hidden');

                listBtn.className = "px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-lg text-xs sm:text-sm font-bold transition flex items-center gap-1 cursor-pointer text-brand-700 hover:text-brand-900";
                calBtn.className = "px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-lg text-xs sm:text-sm font-bold transition flex items-center gap-1 cursor-pointer bg-white text-brand-900 shadow-xs";

                renderCalendar(currentCalendarDate.getFullYear(), currentCalendarDate.getMonth());
            } else {
                calContainer.classList.add('hidden');
                listContainer.classList.remove('hidden');

                calBtn.className = "px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-lg text-xs sm:text-sm font-bold transition flex items-center gap-1 cursor-pointer text-brand-700 hover:text-brand-900";
                listBtn.className = "px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-lg text-xs sm:text-sm font-bold transition flex items-center gap-1 cursor-pointer bg-white text-brand-900 shadow-xs";

                filterEvents();
            }

            try {
                localStorage.setItem('crm_schedule_view', viewName);
            } catch (e) {}
        }

        // ==========================================
        // SINCRONIZAR SELETOR DE TIPO ENTRE VIEWS
        // ==========================================
        function onTypeFilterChange(selectedType) {
            const listSelect = document.getElementById('typeFilterInput');
            const calSelect = document.getElementById('calTypeFilterInput');
            if (listSelect) listSelect.value = selectedType;
            if (calSelect) calSelect.value = selectedType;

            if (currentView === 'calendar') {
                renderCalendar(currentCalendarDate.getFullYear(), currentCalendarDate.getMonth());
            }
            filterEvents();
        }

        // ==========================================
        // CLICAR NO DIA DO CALENDÁRIO -> MODO LISTA
        // ==========================================
        function selectDayForList(dateStr) {
            const startDateInput = document.getElementById('startDateInput');
            const endDateInput = document.getElementById('endDateInput');
            if (startDateInput) startDateInput.value = dateStr;
            if (endDateInput) endDateInput.value = dateStr;

            switchView('list');
            filterEvents();
        }

        // ==========================================
        // RENDER MONTHLY CALENDAR
        // ==========================================
        function renderCalendar(year, month) {
            const titleEl = document.getElementById('calendarMonthTitle');
            titleEl.textContent = `${MONTH_NAMES[month]} de ${year}`;

            const gridEl = document.getElementById('calendarGrid');
            gridEl.innerHTML = '';

            const selectedType = document.getElementById('calTypeFilterInput') ? document.getElementById('calTypeFilterInput').value : '';

            const today = new Date();
            const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

            // First day of this month
            const firstDayIndex = new Date(year, month, 1).getDay(); // 0 = Sunday
            // Total days in this month
            const totalDays = new Date(year, month + 1, 0).getDate();
            // Total days in previous month
            const prevMonthDays = new Date(year, month, 0).getDate();

            // Previous Month trailing days
            for (let i = firstDayIndex - 1; i >= 0; i--) {
                const dayNum = prevMonthDays - i;
                const prevMonth = month === 0 ? 11 : month - 1;
                const prevYear = month === 0 ? year - 1 : year;
                const dateStr = `${prevYear}-${String(prevMonth + 1).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`;
                
                gridEl.appendChild(createDayCell(dayNum, dateStr, true, false, selectedType));
            }

            // Current Month days
            for (let day = 1; day <= totalDays; day++) {
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const isToday = dateStr === todayStr;

                gridEl.appendChild(createDayCell(day, dateStr, false, isToday, selectedType));
            }

            // Next Month leading days to fill complete 7-day rows
            const totalRendered = firstDayIndex + totalDays;
            const remainingCells = (7 - (totalRendered % 7)) % 7;
            for (let day = 1; day <= remainingCells; day++) {
                const nextMonth = month === 11 ? 0 : month + 1;
                const nextYear = month === 11 ? year + 1 : year;
                const dateStr = `${nextYear}-${String(nextMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

                gridEl.appendChild(createDayCell(day, dateStr, true, false, selectedType));
            }
        }

        function createDayCell(dayNum, dateStr, isOtherMonth, isToday, selectedType) {
            const cell = document.createElement('div');
            cell.className = `cal-day-cell p-1 sm:p-2 flex flex-col justify-between cursor-pointer select-none group/cell ${
                isOtherMonth 
                    ? 'bg-gray-50/70 text-gray-400 hover:bg-gray-100/80' 
                    : isToday 
                        ? 'bg-amber-50/50 hover:bg-amber-100/70' 
                        : 'bg-white hover:bg-brand-50/60'
            }`;

            // Ao clicar no card do dia, muda automaticamente para o modo lista filtrado no dia
            cell.onclick = () => {
                selectDayForList(dateStr);
            };

            // Header do Dia (Número e badge de quantidade)
            const header = document.createElement('div');
            header.className = 'flex items-center justify-between mb-1 pointer-events-none';

            const numSpan = document.createElement('span');
            numSpan.className = `text-xs sm:text-sm font-bold inline-flex items-center justify-center ${
                isToday 
                    ? 'w-6 h-6 rounded-full bg-brand-600 text-white shadow-xs' 
                    : isOtherMonth 
                        ? 'text-gray-400' 
                        : 'text-gray-800 group-hover/cell:text-brand-900'
            }`;
            numSpan.textContent = dayNum;
            header.appendChild(numSpan);

            // Filtrar eventos deste dia (e respeitar filtro de tipo se ativo)
            const dayEvents = ALL_EVENTS.filter(ev => {
                const dateMatches = ev.date === dateStr;
                const typeMatches = !selectedType || ev.type === selectedType;
                return dateMatches && typeMatches;
            });

            if (dayEvents.length > 0 && !isOtherMonth) {
                const countBadge = document.createElement('span');
                countBadge.className = 'text-[10px] font-extrabold text-brand-700 bg-brand-100 px-1.5 py-0.2 rounded-full';
                countBadge.textContent = dayEvents.length;
                header.appendChild(countBadge);
            }
            cell.appendChild(header);

            // Lista de Eventos no dia
            const eventsList = document.createElement('div');
            eventsList.className = 'flex-1 space-y-1 overflow-hidden';

            const maxVisible = 3;
            const visibleEvents = dayEvents.slice(0, maxVisible);

            visibleEvents.forEach(ev => {
                const item = document.createElement('button');
                item.type = 'button';
                item.onclick = (e) => {
                    e.stopPropagation();
                    openEventModal(ev.id);
                };

                let colorClasses = 'bg-slate-100 text-slate-800 border-l-2 border-slate-500 hover:bg-slate-200';
                if (ev.type === 'auction') {
                    colorClasses = 'bg-orange-100 text-orange-900 border-l-2 border-orange-500 hover:bg-orange-200 font-bold';
                } else if (ev.type === 'meeting') {
                    colorClasses = 'bg-purple-100 text-purple-900 border-l-2 border-purple-500 hover:bg-purple-200';
                } else if (ev.type === 'visit') {
                    colorClasses = 'bg-green-100 text-green-900 border-l-2 border-green-500 hover:bg-green-200';
                }

                item.className = `w-full text-left px-1.5 py-0.5 rounded text-[10px] sm:text-xs truncate transition cursor-pointer shadow-2xs block ${colorClasses}`;
                item.title = `${ev.time} - ${ev.title} (${ev.typeLabel}) • Clique para ver detalhes`;
                item.innerHTML = `<span class="font-bold opacity-80">${ev.time}</span> <span class="truncate">${escapeHtml(ev.title)}</span>`;
                eventsList.appendChild(item);
            });

            if (dayEvents.length > maxVisible) {
                const moreBtn = document.createElement('button');
                moreBtn.type = 'button';
                moreBtn.className = 'text-[10px] font-bold text-brand-700 hover:underline cursor-pointer block text-left pl-1';
                moreBtn.textContent = `+${dayEvents.length - maxVisible} mais...`;
                moreBtn.onclick = (e) => {
                    e.stopPropagation();
                    selectDayForList(dateStr);
                };
                eventsList.appendChild(moreBtn);
            }

            cell.appendChild(eventsList);
            return cell;
        }

        // ==========================================
        // NAVEGAÇÃO DE MÊS
        // ==========================================
        function prevMonth() {
            currentCalendarDate.setMonth(currentCalendarDate.getMonth() - 1);
            renderCalendar(currentCalendarDate.getFullYear(), currentCalendarDate.getMonth());
        }

        function nextMonth() {
            currentCalendarDate.setMonth(currentCalendarDate.getMonth() + 1);
            renderCalendar(currentCalendarDate.getFullYear(), currentCalendarDate.getMonth());
        }

        function goToToday() {
            currentCalendarDate = new Date();
            renderCalendar(currentCalendarDate.getFullYear(), currentCalendarDate.getMonth());
        }

        // ==========================================
        // MODAL DE DETALHES DO EVENTO
        // ==========================================
        function openEventModal(eventId) {
            const ev = ALL_EVENTS.find(e => e.id === Number(eventId));
            if (!ev) return;

            const modal = document.getElementById('eventDetailModal');
            document.getElementById('modalTitle').textContent = ev.title;
            
            // Type badge
            const badge = document.getElementById('modalTypeBadge');
            badge.textContent = ev.typeLabel;
            badge.className = `px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border ${ev.typeClass}`;

            // Date & Time
            const [y, m, d] = ev.date.split('-');
            document.getElementById('modalDateTime').textContent = `${d}/${m}/${y} às ${ev.time}`;

            // Cliente
            const clientContainer = document.getElementById('modalClientContainer');
            const clientLink = document.getElementById('modalClientLink');
            const clientText = document.getElementById('modalClientText');
            if (ev.client_name) {
                clientContainer.classList.remove('hidden');
                if (ev.client_id) {
                    clientLink.href = `client-details.php?id=${ev.client_id}`;
                    clientLink.textContent = ev.client_name;
                    clientLink.classList.remove('hidden');
                    clientText.classList.add('hidden');
                } else {
                    clientText.textContent = ev.client_name;
                    clientText.classList.remove('hidden');
                    clientLink.classList.add('hidden');
                }
            } else {
                clientContainer.classList.add('hidden');
            }

            // Localização
            const locContainer = document.getElementById('modalLocationContainer');
            const locLink = document.getElementById('modalLocationLink');
            const locText = document.getElementById('modalLocationText');
            const fullAddress = document.getElementById('modalFullAddress');
            if (ev.displayLoc || ev.address) {
                locContainer.classList.remove('hidden');
                locText.textContent = ev.displayLoc || ev.address;
                locLink.href = ev.mapUrl || '#';
                if (ev.mapUrl && ev.mapUrl.startsWith('http')) {
                    locLink.target = '_blank';
                } else {
                    locLink.removeAttribute('target');
                }
                fullAddress.textContent = ev.address ? ev.address : '';
            } else {
                locContainer.classList.add('hidden');
            }

            // Banner
            const bannerContainer = document.getElementById('modalBannerContainer');
            const bannerLink = document.getElementById('modalBannerLink');
            const bannerImg = document.getElementById('modalBannerImg');
            if (ev.banner_image) {
                bannerContainer.classList.remove('hidden');
                bannerLink.href = `../${ev.banner_image}`;
                bannerImg.src = `../${ev.banner_image}`;
            } else {
                bannerContainer.classList.add('hidden');
            }

            // Links de Leilão
            const auctionLinksContainer = document.getElementById('modalAuctionLinksContainer');
            const lotsLink = document.getElementById('modalLotsLink');
            const liveLink = document.getElementById('modalLiveLink');
            let hasAuctionLinks = false;

            if (ev.type === 'auction') {
                if (ev.auction_lots_link) {
                    lotsLink.href = ev.auction_lots_link;
                    lotsLink.classList.remove('hidden');
                    hasAuctionLinks = true;
                } else {
                    lotsLink.classList.add('hidden');
                }
                if (ev.auction_live_link) {
                    liveLink.href = ev.auction_live_link;
                    liveLink.classList.remove('hidden');
                    hasAuctionLinks = true;
                } else {
                    liveLink.classList.add('hidden');
                }
            }
            if (hasAuctionLinks) {
                auctionLinksContainer.classList.remove('hidden');
            } else {
                auctionLinksContainer.classList.add('hidden');
            }

            // Observação
            const obsContainer = document.getElementById('modalObservationContainer');
            const obsText = document.getElementById('modalObservationText');
            if (ev.observation) {
                obsContainer.classList.remove('hidden');
                obsText.textContent = `"${ev.observation}"`;
            } else {
                obsContainer.classList.add('hidden');
            }

            // Ações de Proprietário / Edição
            const ownerActions = document.getElementById('modalOwnerActions');
            const viewOnlyMsg = document.getElementById('modalViewOnlyMsg');
            const editBtn = document.getElementById('modalEditBtn');
            const deleteId = document.getElementById('modalDeleteId');

            if (ev.canEdit) {
                ownerActions.classList.remove('hidden');
                viewOnlyMsg.classList.add('hidden');
                editBtn.href = `schedule-edit.php?id=${ev.id}`;
                deleteId.value = ev.id;
            } else {
                ownerActions.classList.add('hidden');
                viewOnlyMsg.classList.remove('hidden');
            }

            modal.classList.remove('hidden');
        }

        function closeEventModal() {
            const modal = document.getElementById('eventDetailModal');
            if (modal) modal.classList.add('hidden');
        }

        // Fechar ao clicar no backdrop ou ESC
        document.getElementById('eventDetailModal').addEventListener('click', function(e) {
            if (e.target === this) closeEventModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeEventModal();
        });

        function escapeHtml(text) {
            if (!text) return '';
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // ==========================================
        // FILTROS DA LISTA (LIST VIEW)
        // ==========================================
        function filterEvents() {
            const searchInput = document.getElementById('searchInput');
            const startDateInput = document.getElementById('startDateInput');
            const endDateInput = document.getElementById('endDateInput');
            const typeFilterInput = document.getElementById('typeFilterInput');

            const searchText = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const startDate = startDateInput ? startDateInput.value : '';
            const endDate = endDateInput ? endDateInput.value : '';
            const selectedType = typeFilterInput ? typeFilterInput.value : '';

            // Validate Date Range
            if (startDate && endDate && startDate > endDate) {
                Swal.fire({
                    icon: 'error',
                    title: 'Período Inválido',
                    text: 'A data inicial não pode ser maior que a data final.',
                    confirmButtonColor: '#B8860B'
                });
                if (startDateInput) startDateInput.classList.add('border-red-500', 'bg-red-50');
                if (endDateInput) endDateInput.classList.add('border-red-500', 'bg-red-50');
                return;
            } else {
                if (startDateInput) startDateInput.classList.remove('border-red-500', 'bg-red-50');
                if (endDateInput) endDateInput.classList.remove('border-red-500', 'bg-red-50');
            }

            const rows = document.querySelectorAll('.event-row');
            let hasVisible = false;

            rows.forEach(row => {
                const rowDate = row.getAttribute('data-date');
                const rowType = row.getAttribute('data-type');
                const title = row.querySelector('.event-title') ? row.querySelector('.event-title').textContent.toLowerCase() : '';
                const client = row.querySelector('.event-client') ? row.querySelector('.event-client').textContent.toLowerCase() : '';
                const type = row.querySelector('.event-type') ? row.querySelector('.event-type').textContent.toLowerCase() : '';
                const loc = row.querySelector('.event-location') ? row.querySelector('.event-location').textContent.toLowerCase() : '';

                // Check date bounds
                let dateMatches = true;
                if (startDate && rowDate && rowDate < startDate) {
                    dateMatches = false;
                }
                if (endDate && rowDate && rowDate > endDate) {
                    dateMatches = false;
                }

                // Check type match
                let typeMatches = true;
                if (selectedType && rowType !== selectedType) {
                    typeMatches = false;
                }

                // Check text match
                let textMatches = true;
                if (searchText !== '') {
                    textMatches = title.includes(searchText) || client.includes(searchText) || type.includes(searchText) || loc.includes(searchText);
                }

                if (dateMatches && textMatches && typeMatches) {
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
        }

        document.getElementById('searchInput').addEventListener('input', filterEvents);
        document.getElementById('startDateInput').addEventListener('change', filterEvents);
        document.getElementById('endDateInput').addEventListener('change', filterEvents);

        // Confirmation dialog for delete
        function confirmDelete(e) {
            e.preventDefault();
            const form = e.target;
            Swal.fire({
                title: 'Excluir compromisso?',
                text: 'Esta ação não poderá ser desfeita.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sim, excluir',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }

        // ==========================================
        // INITIALIZATION
        // ==========================================
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const savedView = localStorage.getItem('crm_schedule_view');
            const viewParam = urlParams.get('view') || savedView || 'list';

            if (viewParam === 'calendar') {
                switchView('calendar');
            } else {
                switchView('list');
            }

            // Reveal list container smoothly
            const eventsEl = document.getElementById('eventsContainer');
            if (eventsEl) eventsEl.classList.add('ready');

            // Highlight event if passed in URL
            const highlightId = urlParams.get('highlight');
            if (highlightId) {
                const targetEl = document.getElementById('event-' + highlightId);
                if (targetEl) {
                    targetEl.style.display = '';
                    targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    targetEl.classList.add('ring-4', 'ring-brand-500', 'bg-brand-50/80');
                    setTimeout(() => {
                        targetEl.classList.remove('ring-4', 'ring-brand-500', 'bg-brand-50/80');
                    }, 4000);
                }
            }
        });
    </script>
</body>

</html>