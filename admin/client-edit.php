<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();
$user_id = $_SESSION['user_id'];
$pageTitle = 'Editar Cliente';

if (!isset($_GET['id'])) {
    header("Location: clients.php");
    exit;
}

$client_id = $_GET['id'];

// Fetch Client Info
$stmt = $pdo->prepare("SELECT * FROM " . TABLE_NAME . "clients WHERE id = ? AND user_id = ?");
$stmt->execute([$client_id, $user_id]);
$client = $stmt->fetch();

if (!$client) {
    echo "Cliente não encontrado ou acesso negado.";
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_client'])) {
        $stmt = $pdo->prepare("DELETE FROM " . TABLE_NAME . "clients WHERE id = ? AND user_id = ?");
        $stmt->execute([$client_id, $user_id]);
        header("Location: clients.php");
        exit;
    }

    $name = sanitize($_POST['name']);
    $farm_name = sanitize($_POST['farm_name'] ?? '');
    $phone = sanitize($_POST['phone']);
    $email = sanitize($_POST['email'] ?? '');
    $uf = sanitize($_POST['uf'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $address = sanitize($_POST['address']);
    $latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;
    $status = sanitize($_POST['status'] ?? 'Ativo');
    $is_potential = isset($_POST['is_potential']) ? 1 : 0;
    $payment_condition = sanitize($_POST['payment_condition'] ?? '');

    $raw_breeds = $_POST['breed_interests'] ?? [];
    $breed_interests = is_array($raw_breeds) ? implode(', ', array_map('sanitize', $raw_breeds)) : sanitize($raw_breeds);

    $purchase_animal_count = sanitize($_POST['purchase_animal_count'] ?? '');
    $raw_categories = $_POST['animal_categories'] ?? [];
    $animal_categories = is_array($raw_categories) ? implode(', ', array_map('sanitize', $raw_categories)) : sanitize($raw_categories);

    $production_system_opt = sanitize($_POST['production_system'] ?? '');
    $production_system_other = sanitize($_POST['production_system_other'] ?? '');
    $production_system = ($production_system_opt === 'Outro') ? ($production_system_other ? 'Outro: ' . $production_system_other : 'Outro') : $production_system_opt;

    $is_milk_producer = sanitize($_POST['is_milk_producer'] ?? '');
    $acquisition_reason = sanitize($_POST['acquisition_reason'] ?? '');
    $animal_count_range = sanitize($_POST['animal_count_range'] ?? '');
    $milk_production_range = sanitize($_POST['milk_production_range'] ?? '');

    if (empty($name)) {
        $error = "Por favor, informe o nome do cliente.";
    } elseif (!empty($phone) && ($existingClient = findClientByPhone($pdo, $phone, $client_id))) {
        $error = "Já existe outro cliente cadastrado com este telefone: " . formatPhone($phone) . " (" . htmlspecialchars($existingClient['name']) . "). Não é possível salvar alterações com telefone duplicado.";
    } else {
        $stmt = $pdo->prepare("
            UPDATE " . TABLE_NAME . "clients 
            SET name = ?, farm_name = ?, phone = ?, email = ?, uf = ?, city = ?, address = ?, latitude = ?, longitude = ?,
                status = ?, is_potential = ?, payment_condition = ?, breed_interests = ?,
                purchase_animal_count = ?, animal_categories = ?, production_system = ?,
                is_milk_producer = ?, acquisition_reason = ?, animal_count_range = ?, milk_production_range = ? 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([
            $name,
            $farm_name,
            $phone,
            $email,
            $uf,
            $city,
            $address,
            $latitude,
            $longitude,
            $status,
            $is_potential,
            $payment_condition,
            $breed_interests,
            $purchase_animal_count,
            $animal_categories,
            $production_system,
            $is_milk_producer,
            $acquisition_reason,
            $animal_count_range,
            $milk_production_range,
            $client_id,
            $user_id
        ]);
        header("Location: clients.php");
        exit;
    }
}

$states = getBrazilianStates();

// Parse current breed interests and animal categories for checkboxes
$current_breeds = !empty($client['breed_interests']) ? array_map('trim', explode(',', $client['breed_interests'])) : [];
$current_categories = !empty($client['animal_categories']) ? array_map('trim', explode(',', $client['animal_categories'])) : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente - CRM Vitor Müller</title>
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
                            <h2 class="text-2xl font-bold text-gray-800">Editar Cliente</h2>
                        </div>
                        <div class="flex items-center gap-3">
                            <form method="POST" onsubmit="confirmDelete(event)" class="inline">
                                <input type="hidden" name="delete_client" value="1">
                                <button type="submit"
                                    class="text-red-600 hover:text-red-800 text-sm font-semibold flex items-center gap-1 cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                        </path>
                                    </svg>
                                    Excluir
                                </button>
                            </form>
                            <span class="text-gray-300">|</span>
                            <a href="clients.php"
                                class="text-gray-600 hover:text-gray-900 text-sm font-semibold flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Voltar
                            </a>
                        </div>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div
                            class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 font-medium rounded shadow-sm">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="clientForm">
                        <h3 class="text-lg font-bold text-brand-900 mb-4 pb-2 border-b border-gray-200">1. Dados
                            Principais</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Status do Cadastro</label>
                                <select name="status"
                                    class="shadow-sm border border-gray-300 rounded w-full py-3 px-4 text-gray-700 bg-white focus:ring-2 focus:ring-brand-500">
                                    <option value="Ativo" <?php echo ($client['status'] ?? '') === 'Ativo' ? 'selected' : ''; ?>>Ativo</option>
                                    <option value="Atendido" <?php echo ($client['status'] ?? '') === 'Atendido' ? 'selected' : ''; ?>>Atendido</option>
                                    <option value="Embral" <?php echo ($client['status'] ?? '') === 'Embral' ? 'selected' : ''; ?>>Embral</option>
                                    <option value="Novo" <?php echo in_array($client['status'] ?? '', ['Novo', 'Pré-cadastro']) ? 'selected' : ''; ?>>Novo</option>
                                    <option value="Inativo" <?php echo ($client['status'] ?? '') === 'Inativo' ? 'selected' : ''; ?>>Inativo</option>
                                </select>
                            </div>
                            <div class="flex items-center pt-6">
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_potential" value="1" <?php echo !empty($client['is_potential']) ? 'checked' : ''; ?>
                                        class="h-5 w-5 text-brand-500 rounded focus:ring-brand-500 border-gray-300">
                                    <span class="ml-3 text-sm font-bold text-amber-800">⭐ Cliente em Potencial</span>
                                </label>
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Nome Completo *</label>
                                <input type="text" name="name"
                                    value="<?php echo htmlspecialchars($client['name'] ?? ''); ?>"
                                    class="shadow-sm appearance-none border border-gray-300 rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500"
                                    required>
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Nome da Fazenda</label>
                                <input type="text" name="farm_name"
                                    value="<?php echo htmlspecialchars($client['farm_name'] ?? ''); ?>"
                                    placeholder="Ex: Fazenda Santa Maria"
                                    class="shadow-sm appearance-none border border-gray-300 rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Telefone / WhatsApp</label>
                                <input type="text" name="phone" id="phoneInput"
                                    value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>"
                                    class="shadow-sm appearance-none border border-gray-300 rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <p id="phoneErrorMsg" class="text-red-600 text-xs font-bold mt-1.5 hidden"></p>
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">E-mail</label>
                                <input type="email" name="email" maxlength="128"
                                    value="<?php echo htmlspecialchars($client['email'] ?? ''); ?>"
                                    placeholder="Ex: produtor@email.com"
                                    class="shadow-sm appearance-none border border-gray-300 rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Cidade</label>
                                <input type="text" name="city"
                                    value="<?php echo htmlspecialchars($client['city'] ?? ''); ?>"
                                    placeholder="Ex: Castro"
                                    class="shadow-sm appearance-none border border-gray-300 rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">UF (Estado)</label>
                                <select name="uf"
                                    class="shadow-sm appearance-none border border-gray-300 rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500 bg-white">
                                    <option value="">Selecione o estado...</option>
                                    <?php foreach ($states as $code => $stateName): ?>
                                        <option value="<?php echo $code; ?>" <?php echo ($client['uf'] ?? '') == $code ? 'selected' : ''; ?>>
                                            <?php echo $code . ' - ' . htmlspecialchars($stateName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Endereço / Propriedade</label>
                                <input type="text" id="addressInput" name="address"
                                    value="<?php echo htmlspecialchars($client['address'] ?? ''); ?>"
                                    class="shadow-sm appearance-none border border-gray-300 rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>
                        </div>

                        <!-- Map Section -->
                        <div class="mb-6">
                            <label class="block text-gray-700 text-sm font-bold mb-2">Localização (Clique no mapa para
                                alterar)</label>
                            <div id="map" class="h-96 w-full rounded-lg border border-gray-300 shadow-inner"></div>
                            <input type="hidden" name="latitude" id="lat"
                                value="<?php echo htmlspecialchars($client['latitude'] ?? ''); ?>">
                            <input type="hidden" name="longitude" id="lng"
                                value="<?php echo htmlspecialchars($client['longitude'] ?? ''); ?>">
                            <p class="text-sm text-gray-500 mt-2" id="geo-feedback">
                                <?php
                                if ($client['latitude'] && $client['longitude']) {
                                    echo "Lat: " . number_format($client['latitude'], 6) . ", Long: " . number_format($client['longitude'], 6);
                                } else {
                                    echo "Lat: -, Long: -";
                                }
                                ?>
                            </p>
                        </div>

                        <!-- Perfil Comercial Section -->
                        <h3 class="text-lg font-bold text-brand-900 mb-4 pb-2 border-b border-gray-200 pt-4">2. Perfil
                            Comercial</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Condição de Pagamento
                                    Desejada</label>
                                <select name="payment_condition"
                                    class="shadow-sm border border-gray-300 rounded w-full py-3 px-4 text-gray-700 bg-white focus:ring-2 focus:ring-brand-500">
                                    <option value="">Selecione...</option>
                                    <?php foreach (['10 pagamentos', '12 pagamentos', '15 pagamentos', 'À vista'] as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo ($client['payment_condition'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Produtor de Leite?</label>
                                <select name="is_milk_producer"
                                    class="shadow-sm border border-gray-300 rounded w-full py-3 px-4 text-gray-700 bg-white focus:ring-2 focus:ring-brand-500">
                                    <option value="">Selecione...</option>
                                    <option value="Sim" <?php echo ($client['is_milk_producer'] ?? '') === 'Sim' ? 'selected' : ''; ?>>Sim</option>
                                    <option value="Não" <?php echo ($client['is_milk_producer'] ?? '') === 'Não' ? 'selected' : ''; ?>>Não</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Motivo da Aquisição</label>
                                <select name="acquisition_reason"
                                    class="shadow-sm border border-gray-300 rounded w-full py-3 px-4 text-gray-700 bg-white focus:ring-2 focus:ring-brand-500">
                                    <option value="">Selecione...</option>
                                    <?php foreach (['Reposição de plantel', 'Aumento do plantel', 'Iniciando a produção de leite'] as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo ($client['acquisition_reason'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Quantidade de Animais
                                    Possuídos</label>
                                <select name="animal_count_range"
                                    class="shadow-sm border border-gray-300 rounded w-full py-3 px-4 text-gray-700 bg-white focus:ring-2 focus:ring-brand-500">
                                    <option value="">Selecione...</option>
                                    <?php foreach (['0 a 50 animais', '51 a 100 animais', '101 a 150 animais', '151 a 200 animais', 'Mais de 200 animais'] as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo ($client['animal_count_range'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Produção Diária de
                                    Leite</label>
                                <select name="milk_production_range"
                                    class="shadow-sm border border-gray-300 rounded w-full py-3 px-4 text-gray-700 bg-white focus:ring-2 focus:ring-brand-500">
                                    <option value="">Selecione...</option>
                                    <?php foreach ([
                                        '0 a 1.000 litros/dia',
                                        '1.000 a 2.000 litros/dia',
                                        '2.000 a 5.000 litros/dia',
                                        '5.000 a 10.000 litros/dia',
                                        '10.000 a 15.000 litros/dia',
                                        'Acima de 15.000 litros/dia'
                                    ] as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo ($client['milk_production_range'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Qtd. de Animais que Necessita Adquirir</label>
                                <input type="text" name="purchase_animal_count"
                                    value="<?php echo htmlspecialchars($client['purchase_animal_count'] ?? ''); ?>"
                                    placeholder="Ex: 10 animais ou 15 a 20 cabeças"
                                    class="shadow-sm appearance-none border border-gray-300 rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>

                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Sistema de Produção</label>
                                <input type="text" name="production_system"
                                    value="<?php echo htmlspecialchars($client['production_system'] ?? ''); ?>"
                                    placeholder="Ex: Pasto, Compost Barn, Free Stall..."
                                    class="shadow-sm appearance-none border border-gray-300 rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Categorias de Animais Desejadas (Múltipla Seleção)</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 bg-brand-50 p-4 rounded-lg border border-brand-100">
                                    <?php
                                    $categories_list = [
                                        'Bezerras de 0 a 3 meses',
                                        'Bezerras de 3 a 6 meses',
                                        'Bezerras de 6 a 12 meses',
                                        'Bezerras acima de 12 meses inseminadas',
                                        'Novilhas prenhas, com gestação de 2 a 5 meses',
                                        'Novilhas prenhas, com gestação superior a 5 meses',
                                        'Vacas primeira cria (primeira numeral)',
                                        'Vacas segunda cria (segunda numeral)',
                                        'Vacas terceira cria (terceira numeral)'
                                    ];
                                    foreach ($categories_list as $cat): ?>
                                        <label class="flex items-center cursor-pointer">
                                            <input type="checkbox" name="animal_categories[]" value="<?php echo $cat; ?>"
                                                <?php echo in_array($cat, $current_categories) ? 'checked' : ''; ?>
                                                class="h-4 w-4 text-brand-500 rounded focus:ring-brand-500 border-gray-300">
                                            <span class="ml-2 text-xs text-gray-700 font-medium"><?php echo $cat; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Raças de Interesse / Máquinas (Múltipla
                                    Seleção)</label>
                                <div
                                    class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-brand-50 p-4 rounded-lg border border-brand-100">
                                    <?php foreach (['Jersey', 'Holandês', 'Jersolando', 'Girolando', 'Gir', 'Máquinas'] as $breed): ?>
                                        <label class="flex items-center cursor-pointer">
                                            <input type="checkbox" name="breed_interests[]" value="<?php echo $breed; ?>"
                                                <?php echo in_array($breed, $current_breeds) ? 'checked' : ''; ?>
                                                class="h-4 w-4 text-brand-500 rounded focus:ring-brand-500 border-gray-300">
                                            <span
                                                class="ml-2 text-sm text-gray-700 font-medium"><?php echo $breed; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-200">
                            <a href="clients.php"
                                class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-3 px-6 rounded-lg transition text-sm">
                                Cancelar
                            </a>
                            <button
                                class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 px-8 rounded-lg focus:outline-none focus:shadow-outline transition transform hover:-translate-y-0.5 active:translate-y-0 shadow-md text-sm cursor-pointer"
                                type="submit">
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
        function initMap() {
            // Default center
            let initialPos = { lat: -23.550520, lng: -46.633308 }; // Sao Paulo default

            // Use existing client coordinates if available
            const existingLat = <?php echo !empty($client['latitude']) ? $client['latitude'] : 'null'; ?>;
            const existingLng = <?php echo !empty($client['longitude']) ? $client['longitude'] : 'null'; ?>;

            if (existingLat && existingLng) {
                initialPos = { lat: existingLat, lng: existingLng };
            }

            const map = new google.maps.Map(document.getElementById("map"), {
                zoom: existingLat ? 16 : 12,
                center: initialPos,
                mapTypeId: "roadmap",
            });

            // Marker
            let marker = new google.maps.Marker({
                map: map,
                anchorPoint: new google.maps.Point(0, -29),
            });

            if (existingLat && existingLng) {
                marker.setPosition(initialPos);
                marker.setVisible(true);
            }

            // --- Autocomplete ---
            const input = document.getElementById("addressInput");
            const autocomplete = new google.maps.places.Autocomplete(input);
            autocomplete.bindTo("bounds", map);

            // Prevent form submit on enter
            input.addEventListener("keydown", (e) => {
                if (e.key === "Enter") e.preventDefault();
            });

            autocomplete.addListener("place_changed", () => {
                marker.setVisible(false);
                const place = autocomplete.getPlace();

                if (!place.geometry || !place.geometry.location) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Endereço não encontrado',
                        text: "Não foi possível encontrar detalhes para o endereço: '" + place.name + "'"
                    });
                    return;
                }

                if (place.geometry.viewport) {
                    map.fitBounds(place.geometry.viewport);
                } else {
                    map.setCenter(place.geometry.location);
                    map.setZoom(17);
                }

                marker.setPosition(place.geometry.location);
                marker.setVisible(true);

                updateInputs(place.geometry.location);
            });

            // --- Click on Map ---
            map.addListener("click", (e) => {
                placeMarkerAndPanTo(e.latLng, map);
            });

            function placeMarkerAndPanTo(latLng, map) {
                if (marker) {
                    marker.setPosition(latLng);
                    marker.setVisible(true);
                } else {
                    marker = new google.maps.Marker({
                        position: latLng,
                        map: map,
                    });
                }
                updateInputs(latLng);
            }

            function updateInputs(latLng) {
                document.getElementById('lat').value = latLng.lat();
                document.getElementById('lng').value = latLng.lng();
                document.getElementById('geo-feedback').innerText = `Lat: ${latLng.lat().toFixed(6)}, Long: ${latLng.lng().toFixed(6)}`;
            }
        }

        // Expose initMap to global scope
        window.initMap = initMap;

        // Phone Mask & Duplicate Check
        document.querySelector('input[name="phone"]').addEventListener('input', function (e) {
            let x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,5})(\d{0,4})/);
            e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
        });

        const currentClientId = <?php echo intval($client_id); ?>;
        const phoneInputEl = document.getElementById('phoneInput');
        const phoneErrorMsgEl = document.getElementById('phoneErrorMsg');
        const clientFormEl = document.getElementById('clientForm');
        const submitBtnEl = clientFormEl ? clientFormEl.querySelector('button[type="submit"]') : null;
        let isPhoneDuplicate = false;

        async function checkPhoneDuplicate() {
            if (!phoneInputEl) return;
            const val = phoneInputEl.value.replace(/\D/g, '');
            if (val.length < 8) {
                if (phoneErrorMsgEl) phoneErrorMsgEl.classList.add('hidden');
                if (submitBtnEl) {
                    submitBtnEl.disabled = false;
                    submitBtnEl.classList.remove('opacity-50', 'cursor-not-allowed');
                }
                isPhoneDuplicate = false;
                return;
            }

            try {
                const res = await fetch(`../api-check-phone.php?phone=${encodeURIComponent(phoneInputEl.value)}&exclude_id=${currentClientId}`);
                const data = await res.json();
                if (data.exists) {
                    isPhoneDuplicate = true;
                    if (phoneErrorMsgEl) {
                        phoneErrorMsgEl.textContent = `⚠️ Este telefone já está cadastrado para: ${data.client.name}`;
                        phoneErrorMsgEl.classList.remove('hidden');
                    }
                    if (submitBtnEl) {
                        submitBtnEl.disabled = true;
                        submitBtnEl.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                } else {
                    isPhoneDuplicate = false;
                    if (phoneErrorMsgEl) phoneErrorMsgEl.classList.add('hidden');
                    if (submitBtnEl) {
                        submitBtnEl.disabled = false;
                        submitBtnEl.classList.remove('opacity-50', 'cursor-not-allowed');
                    }
                }
            } catch (err) {
                console.error(err);
            }
        }

        if (phoneInputEl) {
            phoneInputEl.addEventListener('input', checkPhoneDuplicate);
            phoneInputEl.addEventListener('blur', checkPhoneDuplicate);
        }

        if (clientFormEl) {
            clientFormEl.addEventListener('submit', function (e) {
                if (isPhoneDuplicate) {
                    e.preventDefault();
                    alert('Não é possível salvar. Este número de telefone pertence a outro cliente.');
                }
            });
        }
    </script>
    <script
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBaWNV6Gc1D-0ZNrGBXxEe2qwbcw4OhDFo&callback=initMap&libraries=places&v=weekly&loading=async"
        async></script>
</body>

</html>