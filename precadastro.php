<?php
require_once 'config/db.php';
require_once 'helpers/functions.php';

// Public page - decrypt assigned user ID from token
$ref_token = $_GET['ref'] ?? ($_GET['u'] ?? '');
$assigned_user_id = decryptUserId($ref_token);

if (!$assigned_user_id && is_numeric($ref_token)) {
    $assigned_user_id = intval($ref_token);
}

// Fallback if no valid user token is provided
if (!$assigned_user_id) {
    $stmt = $pdo->query("SELECT id FROM " . TABLE_NAME . "users ORDER BY id ASC LIMIT 1");
    $assigned_user_id = $stmt->fetchColumn() ?: 1;
}

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $farm_name = sanitize($_POST['farm_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $uf = sanitize($_POST['uf'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $latitude = !empty($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? $_POST['longitude'] : null;
    $payment_condition = sanitize($_POST['payment_condition'] ?? '');

    // Breed interests (multiselect array)
    $raw_breeds = $_POST['breed_interests'] ?? [];
    $breed_interests = is_array($raw_breeds) ? implode(', ', array_map('sanitize', $raw_breeds)) : sanitize($raw_breeds);

    $is_milk_producer = sanitize($_POST['is_milk_producer'] ?? '');
    $acquisition_reason = sanitize($_POST['acquisition_reason'] ?? '');
    $animal_count_range = sanitize($_POST['animal_count_range'] ?? '');
    $milk_production_range = sanitize($_POST['milk_production_range'] ?? '');

    if (empty($name)) {
        $error = "Por favor, informe seu nome.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO " . TABLE_NAME . "clients (
                    user_id, status, is_potential, name, farm_name, phone, email, city, uf, address,
                    payment_condition, breed_interests, is_milk_producer, acquisition_reason,
                    animal_count_range, milk_production_range, latitude, longitude
                ) VALUES (?, 'Novo', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $assigned_user_id,
                $name,
                $farm_name,
                $phone,
                $email,
                $city,
                $uf,
                $address ?: $city,
                $payment_condition,
                $breed_interests,
                $is_milk_producer,
                $acquisition_reason,
                $animal_count_range,
                $milk_production_range,
                $latitude,
                $longitude
            ]);
            $success = true;
        } catch (PDOException $e) {
            $error = "Erro ao enviar pré-cadastro. Tente novamente mais tarde.";
        }
    }
}

$states = getBrazilianStates();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vitor Müller Pecuária de Leite</title>
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

<body class="bg-brand-50 font-sans text-gray-800 antialiased min-h-screen py-10 px-4">
    <div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-xl border border-brand-200 overflow-hidden">

        <!-- Header / Logo -->
        <div class="bg-brand-900 text-white p-6 text-center border-b-4 border-brand-500">
            <div class="bg-white p-2 rounded-xl inline-block mb-3 shadow">
                <img src="assets/images/logo.png" alt="Vitor Müller" class="h-20 w-auto object-contain mx-auto">
            </div>
            <h1 class="text-2xl font-bold text-brand-100 mb-4">Vamos encontrar o que você procura</h1>
            <div class="text-brand-100 text-sm space-y-3 text-left leading-relaxed">
                <p>Olá! Sou <strong class="font-bold text-white">Vitor Müller</strong>, corretor especializado em
                    <strong class="font-bold text-white">gado de leite e máquinas agrícolas</strong>, atuando em todo o
                    Brasil.
                </p>
                <p>Meu trabalho atende produtores de todas as regiões do país e com as principais raças leiteiras.</p>
                <p>Para que eu possa entender melhor o que você procura e apresentar <strong
                        class="font-bold text-white">animais, oportunidades e condições que realmente façam sentido para
                        você</strong>, preciso apenas de algumas informações.</p>
                <p>É rápido e vai me ajudar a oferecer um atendimento mais personalizado.</p>
                <p class="font-bold text-white pt-1">Muito obrigado pela confiança!</p>
            </div>
        </div>

        <div class="p-6 md:p-8">
            <?php if ($success): ?>
                <div class="text-center py-10">
                    <div
                        class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-brand-900 mb-2">Dados Enviado com Sucesso!</h2>
                    <p class="text-gray-600 max-w-md mx-auto mb-6">
                        Agradecemos suas respostas! As informações foram recebidas.
                    </p>
                    <a href="precadastro.php?ref=<?php echo htmlspecialchars($ref_token); ?>"
                        class="inline-block bg-brand-500 hover:bg-brand-600 text-white font-bold py-3 px-8 rounded-xl shadow transition">
                        Preencher Novo Formulário
                    </a>
                </div>
            <?php else: ?>

                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-8">

                    <!-- Question 1 -->
                    <div class="bg-brand-50 p-5 rounded-xl border border-brand-100">
                        <label class="block text-brand-900 font-bold mb-3 text-base">
                            1. Qual condição de pagamento você gostaria de comprar?
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php foreach (['10 pagamentos', '12 pagamentos', '15 pagamentos', 'À vista'] as $opt): ?>
                                <label
                                    class="flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-brand-500 transition">
                                    <input type="radio" name="payment_condition" value="<?php echo $opt; ?>"
                                        class="h-4 w-4 text-brand-500 focus:ring-brand-500 border-gray-300">
                                    <span class="ml-3 text-sm text-gray-700 font-medium"><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Question 2 -->
                    <div class="bg-brand-50 p-5 rounded-xl border border-brand-100">
                        <label class="block text-brand-900 font-bold mb-3 text-base">
                            2. Em qual raça você tem interesse em adquirir? <span
                                class="text-xs text-brand-700 font-normal">(Múltipla seleção)</span>
                        </label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <?php foreach (['Jersey', 'Holandês', 'Gersolando', 'Girolando', 'Gir', 'Máquinas'] as $breed): ?>
                                <label
                                    class="flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-brand-500 transition">
                                    <input type="checkbox" name="breed_interests[]" value="<?php echo $breed; ?>"
                                        class="h-4 w-4 text-brand-500 rounded focus:ring-brand-500 border-gray-300">
                                    <span class="ml-3 text-sm text-gray-700 font-medium"><?php echo $breed; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Question 3 -->
                    <div class="bg-brand-50 p-5 rounded-xl border border-brand-100">
                        <label class="block text-brand-900 font-bold mb-3 text-base">
                            3. Você já é produtor de leite?
                        </label>
                        <div class="flex space-x-4">
                            <?php foreach (['Sim', 'Não'] as $opt): ?>
                                <label
                                    class="flex-1 flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-brand-500 transition">
                                    <input type="radio" name="is_milk_producer" value="<?php echo $opt; ?>"
                                        class="h-4 w-4 text-brand-500 focus:ring-brand-500 border-gray-300">
                                    <span class="ml-3 text-sm text-gray-700 font-medium"><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Question 4 -->
                    <div class="bg-brand-50 p-5 rounded-xl border border-brand-100">
                        <label class="block text-brand-900 font-bold mb-3 text-base">
                            4. Você está querendo adquirir animais por qual motivo?
                        </label>
                        <div class="space-y-2">
                            <?php foreach (['Reposição de plantel', 'Aumento do plantel', 'Iniciando a produção de leite'] as $opt): ?>
                                <label
                                    class="flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-brand-500 transition">
                                    <input type="radio" name="acquisition_reason" value="<?php echo $opt; ?>"
                                        class="h-4 w-4 text-brand-500 focus:ring-brand-500 border-gray-300">
                                    <span class="ml-3 text-sm text-gray-700 font-medium"><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Question 5 -->
                    <div class="bg-brand-50 p-5 rounded-xl border border-brand-100">
                        <label class="block text-brand-900 font-bold mb-3 text-base">
                            5. Quantos animais você possui atualmente?
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php foreach (['0 a 50 animais', '51 a 100 animais', '101 a 150 animais', '151 a 200 animais', 'Mais de 200 animais'] as $opt): ?>
                                <label
                                    class="flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-brand-500 transition">
                                    <input type="radio" name="animal_count_range" value="<?php echo $opt; ?>"
                                        class="h-4 w-4 text-brand-500 focus:ring-brand-500 border-gray-300">
                                    <span class="ml-3 text-sm text-gray-700 font-medium"><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Question 6 -->
                    <div class="bg-brand-50 p-5 rounded-xl border border-brand-100">
                        <label class="block text-brand-900 font-bold mb-3 text-base">
                            6. Qual é a sua produção de leite diária atualmente?
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php foreach ([
                                '0 a 1.000 litros/dia',
                                '1.000 a 2.000 litros/dia',
                                '2.000 a 5.000 litros/dia',
                                '5.000 a 10.000 litros/dia',
                                '10.000 a 15.000 litros/dia',
                                'Acima de 15.000 litros/dia'
                            ] as $opt): ?>
                                <label
                                    class="flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-brand-500 transition">
                                    <input type="radio" name="milk_production_range" value="<?php echo $opt; ?>"
                                        class="h-4 w-4 text-brand-500 focus:ring-brand-500 border-gray-300">
                                    <span class="ml-3 text-sm text-gray-700 font-medium"><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Contact Details (Q7, Q8, Q9 + Phone) -->
                    <div class="border-t border-brand-200 pt-6 space-y-5">
                        <h3 class="text-lg font-bold text-brand-900">Seus Dados de Contato</h3>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">7. Qual é o seu nome completo?
                                *</label>
                            <input type="text" name="name" required placeholder="Digite seu nome completo"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Nome da Fazenda / Propriedade</label>
                            <input type="text" name="farm_name" placeholder="Ex: Fazenda Santa Maria"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Telefone / WhatsApp *</label>
                            <input type="text" name="phone" id="phoneInput" required placeholder="(00) 00000-0000"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">E-mail</label>
                            <input type="email" name="email" maxlength="128" placeholder="seuemail@exemplo.com"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">8. Qual é a sua cidade e onde você
                                mora?</label>
                            <input type="text" name="city" placeholder="Ex: Castro / Fazenda Bela Vista"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">9. Qual é o seu estado (UF)?</label>
                            <select name="uf"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 bg-white">
                                <option value="">Selecione seu estado...</option>
                                <?php foreach ($states as $code => $stateName): ?>
                                    <option value="<?php echo $code; ?>">
                                        <?php echo $code . ' - ' . htmlspecialchars($stateName); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">10. Endereço Completo / Localização da
                                Fazenda (Opcional)</label>
                            <input type="text" id="addressInput" name="address"
                                placeholder="Digite o endereço ou nome da propriedade para buscar..."
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                            <p class="text-xs text-gray-500 mt-1">Digite o endereço ou selecione no mapa abaixo o local
                                exato da propriedade.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Localização no Mapa (Clique no mapa
                                para marcar)</label>
                            <div id="map" class="h-80 w-full rounded-xl border border-gray-300 shadow-inner"></div>
                            <input type="hidden" name="latitude" id="lat">
                            <input type="hidden" name="longitude" id="lng">
                            <p class="text-xs text-gray-500 mt-2" id="geo-feedback">Lat: -, Long: -</p>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold py-4 px-6 rounded-xl shadow-lg transition transform hover:-translate-y-0.5 active:translate-y-0 text-lg">
                        Enviar Pré-Cadastro
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Phone Mask Script -->
    <script>
        const phoneInput = document.getElementById('phoneInput');
        if (phoneInput) {
            phoneInput.addEventListener('input', function (e) {
                let x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,5})(\d{0,4})/);
                e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
            });
        }
    </script>

    <!-- Google Maps Script -->
    <script>
        function initMap() {
            const defaultPos = { lat: -25.4284, lng: -49.2733 }; // Default position

            const map = new google.maps.Map(document.getElementById("map"), {
                zoom: 7,
                center: defaultPos,
                mapTypeId: "roadmap",
            });

            let marker = new google.maps.Marker({
                map: map,
                anchorPoint: new google.maps.Point(0, -29),
            });

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const pos = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                        };
                        map.setCenter(pos);
                        map.setZoom(12);
                    }
                );
            }

            const input = document.getElementById("addressInput");
            if (input && window.google && window.google.maps && window.google.maps.places) {
                const autocomplete = new google.maps.places.Autocomplete(input);
                autocomplete.bindTo("bounds", map);

                input.addEventListener("keydown", (e) => {
                    if (e.key === "Enter") e.preventDefault();
                });

                autocomplete.addListener("place_changed", () => {
                    marker.setVisible(false);
                    const place = autocomplete.getPlace();

                    if (!place.geometry || !place.geometry.location) {
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
            }

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
    </script>
    <script
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBaWNV6Gc1D-0ZNrGBXxEe2qwbcw4OhDFo&callback=initMap&libraries=places&v=weekly"
        async></script>
</body>

</html>