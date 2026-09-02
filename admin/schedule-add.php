<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();
$user_id = $_SESSION['user_id'];
$pageTitle = 'Novo Compromisso';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);
    $type = $_POST['type'] ?? 'meeting';
    $date = sanitize($_POST['date'] ?? '');
    $time = sanitize($_POST['time'] ?? '');
    $start_time = $date . ' ' . $time;
    $client_id = !empty($_POST['client_id']) ? intval($_POST['client_id']) : null;
    $obs = sanitize($_POST['observation'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $uf = sanitize($_POST['uf'] ?? '');
    $latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;

    // Auction Specific Fields
    $auction_lots_link = sanitize($_POST['auction_lots_link'] ?? '');
    $auction_live_link = sanitize($_POST['auction_live_link'] ?? '');
    $banner_image = null;

    if (!empty($_FILES['banner_image']['name']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/auctions/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileExt = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($fileExt, $allowedExts)) {
            $fileName = 'auction_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
            if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $uploadDir . $fileName)) {
                $banner_image = 'uploads/auctions/' . $fileName;
            }
        }
    }

    if ($title && $date && $time) {
        $stmt = $pdo->prepare("
            INSERT INTO " . TABLE_NAME . "schedule (
                user_id, client_id, title, type, start_time, observation, address, city, uf, latitude, longitude, banner_image, auction_lots_link, auction_live_link
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $client_id, $title, $type, $start_time, $obs, $address, $city, $uf, $latitude, $longitude, $banner_image, $auction_lots_link, $auction_live_link]);

        header("Location: schedule.php");
        exit;
    }
}

// Fetch All Clients for dropdown (Operators can schedule for any client)
$stmt = $pdo->prepare("SELECT id, name, farm_name, city, uf, address, latitude, longitude FROM " . TABLE_NAME . "clients ORDER BY name ASC");
$stmt->execute();
$clients = $stmt->fetchAll();

$states = getBrazilianStates();

$selected_client_id = !empty($_GET['client_id']) ? intval($_GET['client_id']) : (!empty($_POST['client_id']) ? intval($_POST['client_id']) : null);
$preselected_client = null;
if ($selected_client_id) {
    foreach ($clients as $c) {
        if ($c['id'] == $selected_client_id) {
            $preselected_client = $c;
            break;
        }
    }
}

$init_city = $_POST['city'] ?? ($preselected_client['city'] ?? '');
$init_uf = $_POST['uf'] ?? ($preselected_client['uf'] ?? '');
$init_address = $_POST['address'] ?? ($preselected_client['address'] ?? '');
$init_lat = $_POST['latitude'] ?? ($preselected_client['latitude'] ?? '');
$init_lng = $_POST['longitude'] ?? ($preselected_client['longitude'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Compromisso - CRM Vitor Müller</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
        /* Select2 Custom Theme matching CRM Brand */
        .select2-container {
            width: 100% !important;
        }
        .select2-container--default .select2-selection--single {
            height: 48px;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.625rem 0.875rem;
            display: flex;
            align-items: center;
            background-color: #ffffff;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .select2-container--default .select2-selection--single:focus,
        .select2-container--default.select2-container--open .select2-selection--single,
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #B8860B;
            box-shadow: 0 0 0 2px rgba(184, 134, 11, 0.25);
            outline: none;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #374151;
            line-height: normal;
            padding-left: 0;
            padding-right: 28px;
            font-size: 0.875rem;
            width: 100%;
        }
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #9ca3af;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 46px;
            right: 12px;
            display: flex;
            align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__clear {
            position: absolute;
            right: 32px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.25rem;
            color: #9ca3af;
            line-height: 1;
            margin: 0;
        }
        .select2-container--default .select2-selection--single .select2-selection__clear:hover {
            color: #ef4444;
        }
        .select2-dropdown {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            z-index: 9999;
        }
        .select2-container--default .select2-search--dropdown {
            padding: 8px;
            background-color: #f9fafb;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 6px 10px;
            font-size: 0.875rem;
            outline: none;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field:focus {
            border-color: #B8860B;
            box-shadow: 0 0 0 2px rgba(184, 134, 11, 0.2);
        }
        .select2-results__option {
            padding: 8px 12px;
            font-size: 0.875rem;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #FAF7F2;
            color: #9E7005;
            font-weight: 500;
        }
        .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: #F3E9D7;
            color: #7A5400;
            font-weight: 600;
        }
    </style>
</head>

<body class="bg-brand-50 font-sans leading-normal tracking-normal">
    <div class="relative min-h-screen md:flex">
        <?php include '../components/sidebar.php'; ?>
        <div class="flex-1 flex flex-col min-h-screen overflow-hidden">
            <?php include '../components/header.php'; ?>
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-brand-50 p-6">

                <div class="max-w-4xl mx-auto bg-white shadow-lg rounded-2xl p-6 sm:p-8 mb-10">
                    <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">Novo Compromisso</h2>
                        </div>
                        <a href="schedule.php"
                            class="text-gray-600 hover:text-gray-900 text-sm font-semibold flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                            Voltar
                        </a>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="space-y-6">
                        <!-- Basic Info Section -->
                        <div>
                            <h3 class="text-lg font-bold text-brand-900 mb-4 pb-2 border-b border-gray-100">1. Informações do Compromisso</h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Título do Evento *</label>
                                    <input type="text" name="title"
                                        class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500"
                                        placeholder="Ex: Leilão Primavera, Reunião Técnica..." required>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-2">Data *</label>
                                        <input type="date" name="date"
                                            class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500"
                                            value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-2">Hora *</label>
                                        <input type="time" name="time"
                                            class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500"
                                            value="<?php echo date('H:i'); ?>" required>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-2">Tipo de Evento *</label>
                                        <select name="type" id="eventTypeSelect"
                                            class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
                                            <option value="meeting">Reunião</option>
                                            <option value="visit">Visita</option>
                                            <option value="auction">Leilão</option>
                                            <option value="other">Outro</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Auction Specific Fields (Conditional) -->
                                <div id="auctionFieldsSection" class="hidden p-4 bg-amber-50/70 rounded-xl border border-amber-200 space-y-4 transition">
                                    <div class="flex items-center gap-2 text-amber-900 font-bold text-sm">
                                        <span>🏷️</span> Detalhes do Leilão
                                    </div>

                                    <div>
                                        <label class="block text-gray-700 text-xs font-bold mb-1">Banner do Leilão (Imagem / Cartaz)</label>
                                        <input type="file" name="banner_image" accept="image/*"
                                            class="block w-full text-xs text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-600 file:text-white hover:file:bg-amber-700 cursor-pointer bg-white border border-gray-300 rounded-lg p-1.5">
                                        <p class="text-[11px] text-gray-500 mt-1">Formatos aceitos: JPG, PNG, WEBP. Tamanho recomendado: 1200x630px ou proporcional.</p>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-gray-700 text-xs font-bold mb-1">Vídeo dos Lotes (Link do YouTube / Vídeo)</label>
                                            <input type="url" name="auction_lots_link"
                                                placeholder="https://www.youtube.com/watch?v=..."
                                                class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-2.5 px-3 text-xs sm:text-sm text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                                        </div>
                                        <div>
                                            <label class="block text-gray-700 text-xs font-bold mb-1">Transmissão Ao Vivo (Link do YouTube / Live)</label>
                                            <input type="url" name="auction_live_link"
                                                placeholder="https://www.youtube.com/live/..."
                                                class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-2.5 px-3 text-xs sm:text-sm text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Cliente Vinculado (Opcional)</label>
                                    <select name="client_id" id="client_id" class="w-full">
                                        <option value=""></option>
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?php echo $client['id']; ?>"
                                                data-farm="<?php echo htmlspecialchars($client['farm_name'] ?? ''); ?>"
                                                data-city="<?php echo htmlspecialchars($client['city'] ?? ''); ?>"
                                                data-uf="<?php echo htmlspecialchars($client['uf'] ?? ''); ?>"
                                                data-address="<?php echo htmlspecialchars($client['address'] ?? ''); ?>"
                                                data-lat="<?php echo htmlspecialchars($client['latitude'] ?? ''); ?>"
                                                data-lng="<?php echo htmlspecialchars($client['longitude'] ?? ''); ?>"
                                                <?php echo ($selected_client_id == $client['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($client['name']); ?>
                                                <?php echo !empty($client['farm_name']) ? ' (' . htmlspecialchars($client['farm_name']) . ')' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Location & Map Section -->
                        <div class="pt-4">
                            <h3 class="text-lg font-bold text-brand-900 mb-4 pb-2 border-b border-gray-100 flex items-center gap-1.5">
                                <span>📍</span> 2. Localização do Evento (Opcional)
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div class="md:col-span-2">
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Cidade</label>
                                    <input type="text" name="city" id="cityInput" placeholder="Ex: Castro"
                                        value="<?php echo htmlspecialchars($init_city); ?>"
                                        class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">UF (Estado)</label>
                                    <select name="uf" id="ufInput"
                                        class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
                                        <option value="">Selecione...</option>
                                        <?php foreach ($states as $code => $stateName): ?>
                                            <option value="<?php echo $code; ?>" <?php echo $init_uf === $code ? 'selected' : ''; ?>>
                                                <?php echo $code . ' - ' . htmlspecialchars($stateName); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Endereço / Local</label>
                                    <input type="text" id="addressInput" name="address"
                                        placeholder="Digite o endereço para buscar ou clique diretamente no mapa..."
                                        value="<?php echo htmlspecialchars($init_address); ?>"
                                        class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500">
                                </div>
                            </div>

                            <!-- Map Section -->
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Marcar no Mapa (Clique para definir ou ajustar a posição)</label>
                                <div id="map" class="h-80 w-full rounded-xl border border-gray-300 shadow-inner"></div>
                                <input type="hidden" name="latitude" id="lat" value="<?php echo htmlspecialchars($init_lat); ?>">
                                <input type="hidden" name="longitude" id="lng" value="<?php echo htmlspecialchars($init_lng); ?>">
                                <p class="text-xs text-gray-500 mt-2" id="geo-feedback">
                                    <?php if (!empty($init_lat) && !empty($init_lng)): ?>
                                        Lat: <?php echo number_format($init_lat, 6); ?>, Long: <?php echo number_format($init_lng, 6); ?>
                                    <?php else: ?>
                                        Lat: -, Long: -
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <!-- Observations Section -->
                        <div class="pt-4">
                            <h3 class="text-lg font-bold text-brand-900 mb-4 pb-2 border-b border-gray-100">3. Observações</h3>
                            <div>
                                <textarea name="observation" rows="3"
                                    placeholder="Anotações e detalhes adicionais sobre o compromisso..."
                                    class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500"><?php echo htmlspecialchars($_POST['observation'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-200">
                            <a href="schedule.php"
                                class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-3 px-6 rounded-lg transition text-sm">
                                Cancelar
                            </a>
                            <button type="submit"
                                class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 px-8 rounded-lg shadow-md transition transform hover:-translate-y-0.5 active:translate-y-0 text-sm cursor-pointer">
                                Salvar
                            </button>
                        </div>
                    </form>
                </div>

            </main>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Google Maps script -->
    <script>
        let map;
        let marker;
        const initialLat = <?php echo !empty($init_lat) ? $init_lat : 'null'; ?>;
        const initialLng = <?php echo !empty($init_lng) ? $init_lng : 'null'; ?>;

        $(document).ready(function() {
            $('#client_id').select2({
                placeholder: 'Selecione ou busque um cliente...',
                allowClear: true,
                width: '100%',
                language: {
                    noResults: function() {
                        return "Nenhum cliente encontrado";
                    },
                    searching: function() {
                        return "Buscando...";
                    }
                }
            });

            $('#client_id').on('select2:select', function(e) {
                const selectedOpt = $(this).find(':selected');
                if (!selectedOpt.val()) return;

                const city = selectedOpt.data('city') || '';
                const uf = selectedOpt.data('uf') || '';
                const address = selectedOpt.data('address') || '';
                const lat = selectedOpt.data('lat');
                const lng = selectedOpt.data('lng');

                const cityInput = document.getElementById('cityInput');
                const ufInput = document.getElementById('ufInput');
                const addressInput = document.getElementById('addressInput');

                if (city && !cityInput.value) cityInput.value = city;
                if (uf && !ufInput.value) ufInput.value = uf;
                if (address && !addressInput.value) addressInput.value = address;

                if (lat && lng && map && marker) {
                    const pos = { lat: parseFloat(lat), lng: parseFloat(lng) };
                    map.setCenter(pos);
                    map.setZoom(16);
                    setMarkerPosition(pos);
                }
            });

            // Toggle Auction Specific Fields
            const typeSelect = document.getElementById('eventTypeSelect');
            const auctionSection = document.getElementById('auctionFieldsSection');

            function toggleAuctionFields() {
                if (typeSelect && auctionSection) {
                    if (typeSelect.value === 'auction') {
                        auctionSection.classList.remove('hidden');
                    } else {
                        auctionSection.classList.add('hidden');
                    }
                }
            }

            if (typeSelect) {
                typeSelect.addEventListener('change', toggleAuctionFields);
                toggleAuctionFields();
            }
        });

        function initMap() {
            const hasInitialPos = (initialLat && initialLng);
            const defaultPos = hasInitialPos
                ? { lat: parseFloat(initialLat), lng: parseFloat(initialLng) }
                : { lat: -23.550520, lng: -46.633308 }; // São Paulo default

            map = new google.maps.Map(document.getElementById("map"), {
                zoom: hasInitialPos ? 15 : 12,
                center: defaultPos,
                mapTypeId: "roadmap",
            });

            marker = new google.maps.Marker({
                position: hasInitialPos ? defaultPos : null,
                map: map,
                visible: hasInitialPos ? true : false,
                draggable: true
            });

            // Try HTML5 geolocation if no initial position
            if (!hasInitialPos && navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const pos = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                        };
                        map.setCenter(pos);
                    }
                );
            }

            // Places Autocomplete
            const input = document.getElementById("addressInput");
            const autocomplete = new google.maps.places.Autocomplete(input);
            autocomplete.bindTo("bounds", map);

            input.addEventListener("keydown", (e) => {
                if (e.key === "Enter") e.preventDefault();
            });

            autocomplete.addListener("place_changed", () => {
                const place = autocomplete.getPlace();

                if (!place.geometry || !place.geometry.location) {
                    return;
                }

                if (place.geometry.viewport) {
                    map.fitBounds(place.geometry.viewport);
                } else {
                    map.setCenter(place.geometry.location);
                    map.setZoom(16);
                }

                setMarkerPosition(place.geometry.location);

                // Auto fill city and UF if available
                if (place.address_components) {
                    const cityInput = document.getElementById("cityInput");
                    const ufInput = document.getElementById("ufInput");

                    place.address_components.forEach(c => {
                        if (c.types.includes("administrative_area_level_2") && !cityInput.value) {
                            cityInput.value = c.long_name;
                        }
                        if (c.types.includes("administrative_area_level_1") && !ufInput.value) {
                            ufInput.value = c.short_name;
                        }
                    });
                }
            });

            // Click on Map
            map.addListener("click", (e) => {
                setMarkerPosition(e.latLng);
            });

            // Drag Marker
            marker.addListener("dragend", (e) => {
                setMarkerPosition(e.latLng);
            });
        }

        function setMarkerPosition(latLng) {
            const lat = typeof latLng.lat === 'function' ? latLng.lat() : latLng.lat;
            const lng = typeof latLng.lng === 'function' ? latLng.lng() : latLng.lng;

            marker.setPosition({ lat, lng });
            marker.setVisible(true);

            document.getElementById("lat").value = lat.toFixed(8);
            document.getElementById("lng").value = lng.toFixed(8);
            document.getElementById("geo-feedback").innerText = `Lat: ${lat.toFixed(6)}, Long: ${lng.toFixed(6)}`;
        }
    </script>
    <script
        src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&libraries=places&callback=initMap&v=weekly&loading=async"
        async></script>
</body>

</html>
