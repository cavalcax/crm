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
    header("Location: map-selector.php");
    exit;
}

// Fetch Clients
$stmt = $pdo->prepare("
    SELECT c.* 
    FROM " . TABLE_NAME . "clients c 
    WHERE c.user_id = ? 
    ORDER BY c.is_potential DESC, c.name ASC
");
$stmt->execute([$user_id]);
$clients = $stmt->fetchAll();

// Fetch Future Auctions for Map Selection with valid location coordinates
$stmtAuc = $pdo->prepare("
    SELECT s.*, c.name as client_name 
    FROM " . TABLE_NAME . "schedule s 
    LEFT JOIN " . TABLE_NAME . "clients c ON s.client_id = c.id 
    WHERE s.user_id = ? 
      AND s.type = 'auction' 
      AND s.start_time >= NOW()
      AND s.latitude IS NOT NULL 
      AND s.longitude IS NOT NULL
      AND s.latitude != 0 
      AND s.longitude != 0
    ORDER BY s.start_time ASC
");
$stmtAuc->execute([$user_id]);
$auctions = $stmtAuc->fetchAll();

$statusFilterParam = $_GET['status'] ?? '';
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
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-brand-50 p-6 pb-28">

                <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-brand-900">Mapa de Clientes</h1>
                    </div>
                </div>

                <form action="view-map.php" method="POST" id="mapForm">

                    <?php if (!empty($auctions)): ?>
                        <!-- Future Auctions Selection Section with Accordion -->
                        <div class="mb-8 bg-white shadow-md rounded-xl overflow-hidden border border-red-200">
                            <button type="button" onclick="toggleAccordion('auctionsAccordionContent', 'auctionsChevron')"
                                class="w-full bg-gradient-to-r from-red-700 to-red-800 px-5 py-3.5 flex items-center justify-between text-white hover:from-red-800 hover:to-red-900 transition text-left cursor-pointer select-none">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">🔨</span>
                                    <h2 class="text-base font-bold tracking-wide">Leilões Programados (Futuros com Localização)</h2>
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
                                                <td class="px-5 py-4 text-sm">
                                                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $auc['latitude']; ?>,<?php echo $auc['longitude']; ?>"
                                                        target="_blank" class="text-red-600 hover:text-red-800 inline-flex items-center font-bold text-xs"
                                                        title="Ver no Google Maps">
                                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
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
                                <h2 class="text-base font-bold tracking-wide">Clientes Cadastrados</h2>
                                <span class="bg-white/20 text-white text-xs font-extrabold px-2.5 py-0.5 rounded-full ml-1">
                                    <?php echo count($clients); ?>
                                </span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="hidden sm:inline text-xs text-brand-100 font-medium">Selecione os clientes para exibir no mapa</span>
                                <svg id="clientsChevron" class="w-5 h-5 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </button>

                        <div id="clientsAccordionContent" class="p-5">
                            <!-- Search & Status Filter (65% Search, 35% Status) -->
                            <div class="mb-6 flex gap-2 sm:gap-4 items-center">
                                <div class="relative w-[65%]">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 sm:pl-3 pointer-events-none">
                                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </span>
                                    <input type="text" id="searchInput"
                                        class="w-full pl-8 sm:pl-10 pr-2.5 sm:pr-4 py-2 sm:py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm bg-white text-xs sm:text-sm"
                                        placeholder="Buscar por nome, fazenda, telefone, cidade, UF...">
                                </div>

                                <div class="w-[35%]">
                                    <div class="relative">
                                        <select id="statusFilter"
                                            class="w-full pl-2 sm:pl-3 pr-6 sm:pr-8 py-2 sm:py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm bg-white font-medium text-gray-700 text-xs sm:text-sm appearance-none cursor-pointer truncate">
                                            <option value="" <?php echo empty($statusFilterParam) ? 'selected' : ''; ?>>Todos Status</option>
                                            <option value="Novo" <?php echo in_array($statusFilterParam, ['Novo', 'Pré-cadastro']) ? 'selected' : ''; ?>>🟡 Novos</option>
                                            <option value="Atendido" <?php echo $statusFilterParam === 'Atendido' ? 'selected' : ''; ?>>🟣 Atendidos</option>
                                            <option value="Embral" <?php echo $statusFilterParam === 'Embral' ? 'selected' : ''; ?>>🔵 Embral</option>
                                            <option value="Ativo" <?php echo $statusFilterParam === 'Ativo' ? 'selected' : ''; ?>>🟢 Ativos</option>
                                            <option value="Inativo" <?php echo $statusFilterParam === 'Inativo' ? 'selected' : ''; ?>>⚫ Inativos</option>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-1.5 sm:px-2.5 text-gray-500">
                                            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="overflow-x-auto rounded-lg border border-gray-200">
                                <table class="min-w-full leading-normal" id="clientsTable">
                                    <thead>
                                        <tr>
                                            <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-center w-12">
                                                <input type="checkbox" id="selectAll"
                                                    class="form-checkbox h-5 w-5 text-brand-600 rounded focus:ring-brand-500 border-gray-300 cursor-pointer"
                                                    title="Selecionar todos os clientes visíveis">
                                            </th>
                                            <th
                                                class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Nome</th>
                                            <th
                                                class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Cidade / UF</th>
                                            <th
                                                class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Status</th>
                                            <th
                                                class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Contato</th>
                                            <th
                                                class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                                Mapa</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (count($clients) > 0): ?>
                                        <?php foreach ($clients as $client): ?>
                                            <?php
                                            $hasCoords = !empty($client['latitude']) && !empty($client['longitude']);
                                            $isPotential = !empty($client['is_potential']) && $client['is_potential'] == 1;
                                            ?>
                                            <tr
                                                class="client-row hover:bg-gray-50 transition <?php echo !$hasCoords ? 'bg-gray-50/60 opacity-75' : ''; ?>">
                                                <!-- Checkbox Column -->
                                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center">
                                                    <input type="checkbox" name="client_ids[]"
                                                        value="<?php echo $client['id']; ?>"
                                                        class="client-checkbox form-checkbox h-5 w-5 text-brand-600 rounded focus:ring-brand-500 border-gray-300 cursor-pointer"
                                                        <?php echo !$hasCoords ? 'disabled title="Cliente sem coordenadas para o mapa"' : ''; ?>>
                                                </td>

                                                <!-- Nome Column with Star and Farm -->
                                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                    <div class="flex items-center">
                                                        <!-- Potential Star Toggle Button -->
                                                        <button type="submit" form="potentialForm_<?php echo $client['id']; ?>"
                                                            class="mr-2 text-xl focus:outline-none transition transform hover:scale-125 cursor-pointer"
                                                            title="<?php echo $isPotential ? 'Remover dos clientes em potencial' : 'Marcar como cliente em potencial'; ?>">
                                                            <?php if ($isPotential): ?>
                                                                <span class="text-amber-500">⭐</span>
                                                            <?php else: ?>
                                                                <span
                                                                    class="text-gray-300 hover:text-amber-400 grayscale opacity-40 hover:opacity-100">⭐</span>
                                                            <?php endif; ?>
                                                        </button>

                                                        <div>
                                                            <p
                                                                class="text-gray-900 font-bold whitespace-no-wrap client-name">
                                                                <?php echo htmlspecialchars($client['name']); ?>
                                                            </p>
                                                            <?php if (!empty($client['farm_name'])): ?>
                                                                <p class="text-xs text-brand-800 font-medium client-farm">
                                                                    🏡 <?php echo htmlspecialchars($client['farm_name']); ?>
                                                                </p>
                                                            <?php endif; ?>
                                                            <?php if (!$hasCoords): ?>
                                                                <span class="text-[11px] text-amber-600 block mt-0.5">⚠️ Sem coordenadas para mapa</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>

                                                <!-- Cidade / UF Column -->
                                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                    <span class="relative client-location text-gray-700">
                                                        <?php
                                                        $loc = array_filter([$client['city'] ?? '', $client['uf'] ?? '']);
                                                        echo htmlspecialchars(!empty($loc) ? implode(' / ', $loc) : 'N/A');
                                                        ?>
                                                    </span>
                                                </td>

                                                <!-- Status Column -->
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

                                                <!-- Contato Column (Phone with WhatsApp + Email) -->
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
                                                            <svg class="w-3.5 h-3.5 mr-1 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

                                                <!-- Mapa Column -->
                                                <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                    <?php if ($hasCoords): ?>
                                                        <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $client['latitude']; ?>,<?php echo $client['longitude']; ?>"
                                                            target="_blank" class="text-blue-500 hover:text-blue-800"
                                                            title="Ver no Google Maps">
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
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6"
                                                class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center text-gray-500">
                                                Nenhum cliente cadastrado.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            <div id="noResults" class="hidden px-5 py-8 bg-white text-sm text-center text-gray-500">
                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <p class="font-medium text-gray-600">Nenhum cliente encontrado para os critérios de busca.</p>
                                <p class="text-xs text-gray-400 mt-1">Tente ajustar o termo de pesquisa ou o filtro de status.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Fixed Footer Action -->
                    <div
                        class="fixed bottom-0 right-0 left-0 md:left-64 bg-white border-t border-gray-200 p-4 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] flex justify-between items-center z-10">
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
                if (chevron) {
                    chevron.classList.remove('-rotate-90');
                }
            } else {
                content.classList.add('hidden');
                if (chevron) {
                    chevron.classList.add('-rotate-90');
                }
            }
        }
        function filterRows() {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');

            const searchText = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const selectedStatus = statusFilter ? statusFilter.value.toLowerCase().trim() : '';
            const rows = document.querySelectorAll('.client-row');
            let hasVisible = false;

            rows.forEach(row => {
                const nameEl = row.querySelector('.client-name');
                const farmEl = row.querySelector('.client-farm');
                const phoneEl = row.querySelector('.client-phone');
                const emailEl = row.querySelector('.client-email');
                const locationEl = row.querySelector('.client-location');
                const statusEl = row.querySelector('.client-status');

                const name = nameEl ? nameEl.textContent.toLowerCase() : '';
                const farm = farmEl ? farmEl.textContent.toLowerCase() : '';
                const phone = phoneEl ? phoneEl.textContent.toLowerCase().replace(/[^0-9]/g, '') : '';
                const phoneFormatted = phoneEl ? phoneEl.textContent.toLowerCase() : '';
                const email = emailEl ? emailEl.textContent.toLowerCase().trim() : '';
                const location = locationEl ? locationEl.textContent.toLowerCase() : '';
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
                        phone.includes(searchText) ||
                        phoneFormatted.includes(searchText) ||
                        email.includes(searchText) ||
                        location.includes(searchText) ||
                        status.includes(searchText);
                }

                if (statusMatches && textMatches) {
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

        document.getElementById('searchInput').addEventListener('input', filterRows);
        document.getElementById('statusFilter').addEventListener('change', filterRows);

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

        // Initialize filter and selection count
        filterRows();
    </script>
</body>

</html>