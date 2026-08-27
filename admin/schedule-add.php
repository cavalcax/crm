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

    if ($title && $date && $time) {
        $stmt = $pdo->prepare("
            INSERT INTO " . TABLE_NAME . "schedule (
                user_id, client_id, title, type, start_time, observation, address, city, uf, latitude, longitude
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $client_id, $title, $type, $start_time, $obs, $address, $city, $uf, $latitude, $longitude]);

        header("Location: schedule.php");
        exit;
    }
}

// Fetch Clients for dropdown
$stmt = $pdo->prepare("SELECT id, name, farm_name FROM " . TABLE_NAME . "clients WHERE user_id = ? ORDER BY name ASC");
$stmt->execute([$user_id]);
$clients = $stmt->fetchAll();

$states = getBrazilianStates();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Compromisso - CRM Vitor Müller</title>
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

                    <form method="POST" class="space-y-6">
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
                                        <select name="type"
                                            class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
                                            <option value="meeting">Reunião</option>
                                            <option value="visit">Visita</option>
                                            <option value="auction">Leilão</option>
                                            <option value="other">Outro</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Cliente Vinculado (Opcional)</label>
                                    <select name="client_id"
                                        class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
                                        <option value="">Nenhum cliente vinculado</option>
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?php echo $client['id']; ?>">
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
                                        class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">UF (Estado)</label>
                                    <select name="uf" id="ufInput"
                                        class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
                                        <option value="">Selecione...</option>
                                        <?php foreach ($states as $code => $stateName): ?>
                                            <option value="<?php echo $code; ?>">
                                                <?php echo $code . ' - ' . htmlspecialchars($stateName); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Endereço / Local</label>
                                    <input type="text" id="addressInput" name="address"
                                        placeholder="Digite o endereço para buscar ou clique diretamente no mapa..."
                                        class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500">
                                </div>
                            </div>

                            <!-- Map Section -->
                            <div class="mb-4">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Marcar no Mapa (Clique para definir ou ajustar a posição)</label>
                                <div id="map" class="h-80 w-full rounded-xl border border-gray-300 shadow-inner"></div>
                                <input type="hidden" name="latitude" id="lat">
                                <input type="hidden" name="longitude" id="lng">
                                <p class="text-xs text-gray-500 mt-2" id="geo-feedback">Lat: -, Long: -</p>
                            </div>
                        </div>

                        <!-- Observations Section -->
                        <div class="pt-4">
                            <h3 class="text-lg font-bold text-brand-900 mb-4 pb-2 border-b border-gray-100">3. Observações</h3>
                            <div>
                                <textarea name="observation" rows="3"
                                    placeholder="Anotações e detalhes adicionais sobre o compromisso..."
                                    class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
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

    <!-- Google Maps script -->
    <script>
        let map;
        let marker;

        function initMap() {
            const defaultPos = { lat: -23.550520, lng: -46.633308 }; // São Paulo default

            map = new google.maps.Map(document.getElementById("map"), {
                zoom: 12,
                center: defaultPos,
                mapTypeId: "roadmap",
            });

            marker = new google.maps.Marker({
                map: map,
                visible: false,
                draggable: true
            });

            // Try HTML5 geolocation
            if (navigator.geolocation) {
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
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBaWNV6Gc1D-0ZNrGBXxEe2qwbcw4OhDFo&libraries=places&callback=initMap&v=weekly&loading=async"
        async></script>
</body>

</html>
