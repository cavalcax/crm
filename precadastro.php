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
    $breed_interests_array = is_array($raw_breeds) ? array_map('sanitize', $raw_breeds) : [];
    $breed_interests = implode(', ', $breed_interests_array);

    $has_animal_breed = false;
    foreach ($breed_interests_array as $b) {
        if ($b !== 'Máquinas') {
            $has_animal_breed = true;
            break;
        }
    }

    $acquisition_reason = sanitize($_POST['acquisition_reason'] ?? '');
    $purchase_animal_count = sanitize($_POST['purchase_animal_count'] ?? '');
    $raw_categories = $_POST['animal_categories'] ?? [];
    $animal_categories_array = is_array($raw_categories) ? array_map('sanitize', $raw_categories) : [];
    $animal_categories = implode(', ', $animal_categories_array);

    $production_system_opt = sanitize($_POST['production_system'] ?? '');
    $production_system_other = sanitize($_POST['production_system_other'] ?? '');
    $production_system = ($production_system_opt === 'Outro') ? ($production_system_other ? 'Outro: ' . $production_system_other : 'Outro') : $production_system_opt;

    if (!$has_animal_breed) {
        $acquisition_reason = '';
        $purchase_animal_count = '';
        $animal_categories = '';
        $production_system = '';
    }

    $is_milk_producer = sanitize($_POST['is_milk_producer'] ?? '');
    $animal_count_range = sanitize($_POST['animal_count_range'] ?? '');
    $milk_production_range = sanitize($_POST['milk_production_range'] ?? '');

    if (empty($payment_condition)) {
        $error = "Por favor, selecione a condição de pagamento.";
    } elseif (empty($breed_interests_array)) {
        $error = "Por favor, selecione ao menos uma opção no campo 'O que você tem interesse em adquirir'.";
    } elseif ($has_animal_breed && empty($acquisition_reason)) {
        $error = "Por favor, selecione o motivo da aquisição dos animais.";
    } elseif ($has_animal_breed && empty($purchase_animal_count)) {
        $error = "Por favor, informe a quantidade de animais que você necessita adquirir.";
    } elseif ($has_animal_breed && empty($animal_categories_array)) {
        $error = "Por favor, selecione ao menos uma categoria de animais desejada.";
    } elseif ($has_animal_breed && empty($production_system_opt)) {
        $error = "Por favor, selecione o sistema da sua produção.";
    } elseif ($has_animal_breed && $production_system_opt === 'Outro' && empty($production_system_other)) {
        $error = "Por favor, especifique o sistema da sua produção no campo Outro.";
    } elseif (empty($is_milk_producer)) {
        $error = "Por favor, responda se você já é produtor de leite.";
    } elseif (empty($animal_count_range)) {
        $error = "Por favor, selecione a quantidade de animais que possui atualmente.";
    } elseif (empty($milk_production_range)) {
        $error = "Por favor, selecione a sua produção de leite diária atual.";
    } elseif (empty($name)) {
        $error = "Por favor, informe seu nome completo.";
    } elseif (empty($phone)) {
        $error = "Por favor, informe seu telefone / WhatsApp.";
    } elseif (empty($city)) {
        $error = "Por favor, informe sua cidade.";
    } elseif (empty($uf)) {
        $error = "Por favor, selecione seu estado (UF).";
    } elseif ($existingClient = findClientByPhone($pdo, $phone)) {
        $error = "Já existe um cadastro com este número de telefone em nosso sistema. Se você já se cadastrou ou precisa atualizar seus dados, entre em contato conosco.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO " . TABLE_NAME . "clients (
                    user_id, status, is_potential, name, farm_name, phone, email, city, uf, address,
                    payment_condition, breed_interests, purchase_animal_count, animal_categories, production_system,
                    is_milk_producer, acquisition_reason, animal_count_range, milk_production_range, latitude, longitude
                ) VALUES (?, 'Novo', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                $purchase_animal_count,
                $animal_categories,
                $production_system,
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
                    <h2 class="text-2xl font-bold text-brand-900 mb-2">Dados Enviados com Sucesso!</h2>
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

                <form method="POST" class="space-y-8" id="preCadastroForm">

                    <!-- Condição de Pagamento -->
                    <div class="bg-brand-50 p-5 rounded-xl border border-brand-100">
                        <label class="block text-brand-900 font-bold mb-3 text-base">
                            Qual condição de pagamento você gostaria de comprar?
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php foreach (['10 pagamentos', '12 pagamentos', '15 pagamentos', 'À vista'] as $opt): ?>
                                <label
                                    class="flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-brand-500 transition">
                                    <input type="radio" name="payment_condition" value="<?php echo $opt; ?>" required
                                        class="h-4 w-4 text-brand-500 focus:ring-brand-500 border-gray-300">
                                    <span class="ml-3 text-sm text-gray-700 font-medium"><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- O que tem interesse em adquirir -->
                    <div class="bg-brand-50 p-5 rounded-xl border border-brand-100">
                        <label class="block text-brand-900 font-bold mb-3 text-base">
                            O que você tem interesse em adquirir? <span class="text-xs text-brand-700 font-normal">(Múltipla
                                seleção)</span>
                        </label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <?php foreach (['Jersey', 'Holandês', 'Jersolando', 'Girolando', 'Gir', 'Máquinas'] as $breed): ?>
                                <label
                                    class="flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-brand-500 transition">
                                    <input type="checkbox" name="breed_interests[]" value="<?php echo $breed; ?>"
                                        class="breed-checkbox h-4 w-4 text-brand-500 rounded focus:ring-brand-500 border-gray-300">
                                    <span class="ml-3 text-sm text-gray-700 font-medium"><?php echo $breed; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Questões condicionais quando raça de animais for selecionada -->
                    <div id="animalQuestionsContainer" class="space-y-8 hidden">
                        <!-- Motivo da Aquisição -->
                        <div class="bg-brand-50 p-5 rounded-xl border border-brand-100">
                            <label class="block text-brand-900 font-bold mb-3 text-base">
                                Você está querendo adquirir animais por qual motivo?
                            </label>
                            <div class="space-y-2">
                                <?php foreach (['Reposição de plantel', 'Aumento do plantel', 'Iniciando a produção de leite'] as $opt): ?>
                                    <label
                                        class="flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-brand-500 transition">
                                        <input type="radio" name="acquisition_reason" value="<?php echo $opt; ?>"
                                            class="acquisition-reason-radio h-4 w-4 text-brand-500 focus:ring-brand-500 border-gray-300">
                                        <span class="ml-3 text-sm text-gray-700 font-medium"><?php echo $opt; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Quantidade de Animais que necessita adquirir -->
                        <div class="bg-brand-50 p-5 rounded-xl border border-brand-100">
                            <label class="block text-brand-900 font-bold mb-3 text-base">
                                Qual a quantidade de animais que você necessita adquirir?
                            </label>
                            <input type="text" name="purchase_animal_count" id="purchaseAnimalCountInput"
                                placeholder="Ex: 10 animais, 15 a 20 cabeças, etc."
                                class="w-full p-3 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-gray-700 font-medium">
                        </div>

                        <!-- Categorias de animais -->
                        <div class="bg-brand-50 p-5 rounded-xl border border-brand-100">
                            <label class="block text-brand-900 font-bold mb-3 text-base">
                                Qual a categoria de animais que você deseja ou tem necessidade de adquirir? <span
                                    class="text-xs text-brand-700 font-normal">(Múltipla seleção)</span>
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <?php
                                $categories_list = [
                                    'Bezerras de 0 a 3 meses',
                                    'Bezerras de 3 a 6 meses',
                                    'Bezerras de 6 a 12 meses',
                                    'Bezerras acima de 12 meses inseminadas',
                                    'Novilhas prenhas, com gestação de 2 a 5 meses',
                                    'Novilhas prenhas, com gestação superior a 5 meses',
                                    'Vacas 1ª cria',
                                    'Vacas 2ª cria',
                                    'Vacas 3ª cria'
                                ];
                                foreach ($categories_list as $cat): ?>
                                    <label
                                        class="flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-brand-500 transition">
                                        <input type="checkbox" name="animal_categories[]" value="<?php echo $cat; ?>"
                                            class="category-checkbox h-4 w-4 text-brand-500 rounded focus:ring-brand-500 border-gray-300">
                                        <span
                                            class="ml-3 text-xs sm:text-sm text-gray-700 font-medium"><?php echo $cat; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Sistema da Produção -->
                        <div class="bg-brand-50 p-5 rounded-xl border border-brand-100">
                            <label class="block text-brand-900 font-bold mb-3 text-base">
                                Qual o sistema da sua produção?
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <?php foreach (['Pasto', 'Semi-confinamento', 'Compost Barn', 'Free Stall', 'Outro'] as $opt): ?>
                                    <label
                                        class="flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-brand-500 transition">
                                        <input type="radio" name="production_system" value="<?php echo $opt; ?>"
                                            class="production-system-radio h-4 w-4 text-brand-500 focus:ring-brand-500 border-gray-300">
                                        <span class="ml-3 text-sm text-gray-700 font-medium"><?php echo $opt; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div id="productionSystemOtherContainer" class="mt-3 hidden">
                                <label class="block text-xs font-bold text-gray-700 mb-1">Especifique o sistema de
                                    produção:</label>
                                <input type="text" name="production_system_other" id="productionSystemOtherInput"
                                    placeholder="Ex: Confinamento a céu aberto..."
                                    class="w-full p-3 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-gray-700">
                            </div>
                        </div>
                    </div>

                    <!-- Produtor de Leite -->
                    <div class="bg-brand-50 p-5 rounded-xl border border-brand-100">
                        <label class="block text-brand-900 font-bold mb-3 text-base">
                            Você já é produtor de leite?
                        </label>
                        <div class="flex space-x-4">
                            <?php foreach (['Sim', 'Não'] as $opt): ?>
                                <label
                                    class="flex-1 flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-brand-500 transition">
                                    <input type="radio" name="is_milk_producer" value="<?php echo $opt; ?>" required
                                        class="h-4 w-4 text-brand-500 focus:ring-brand-500 border-gray-300">
                                    <span class="ml-3 text-sm text-gray-700 font-medium"><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Quantidade de animais que possui atualmente -->
                    <div class="bg-brand-50 p-5 rounded-xl border border-brand-100">
                        <label class="block text-brand-900 font-bold mb-3 text-base">
                            Quantos animais você possui atualmente?
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php foreach (['0 a 50 animais', '51 a 100 animais', '101 a 150 animais', '151 a 200 animais', 'Mais de 200 animais'] as $opt): ?>
                                <label
                                    class="flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-brand-500 transition">
                                    <input type="radio" name="animal_count_range" value="<?php echo $opt; ?>" required
                                        class="h-4 w-4 text-brand-500 focus:ring-brand-500 border-gray-300">
                                    <span class="ml-3 text-sm text-gray-700 font-medium"><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Produção de leite diária atualmente -->
                    <div class="bg-brand-50 p-5 rounded-xl border border-brand-100">
                        <label class="block text-brand-900 font-bold mb-3 text-base">
                            Qual é a sua produção de leite diária atualmente?
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
                                    <input type="radio" name="milk_production_range" value="<?php echo $opt; ?>" required
                                        class="h-4 w-4 text-brand-500 focus:ring-brand-500 border-gray-300">
                                    <span class="ml-3 text-sm text-gray-700 font-medium"><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Contact Details -->
                    <div class="border-t border-brand-200 pt-6 space-y-5">
                        <h3 class="text-lg font-bold text-brand-900">Seus Dados de Contato</h3>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Qual é o seu nome completo?</label>
                            <input type="text" name="name" required placeholder="Digite seu nome completo"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Nome da Fazenda / Propriedade</label>
                            <input type="text" name="farm_name" placeholder="Ex: Fazenda Santa Maria"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Telefone / WhatsApp</label>
                            <input type="text" name="phone" id="phoneInput" required placeholder="(00) 00000-0000"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                            <p id="phoneErrorMsg" class="text-red-600 text-xs font-bold mt-1.5 hidden"></p>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">E-mail</label>
                            <input type="email" name="email" maxlength="128" placeholder="seuemail@exemplo.com"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Qual é a sua cidade e onde você
                                mora?</label>
                            <input type="text" name="city" id="cityInput" required
                                placeholder="Ex: Castro / Fazenda Bela Vista"
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Qual é o seu estado (UF)?</label>
                            <select name="uf" id="ufSelect" required
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
                            <label class="block text-sm font-bold text-gray-700 mb-1">Endereço Completo / Localização da
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

    <!-- Phone Mask Script, Visibility Toggle & Validation -->
    <script>
        const phoneInput = document.getElementById('phoneInput');
        const phoneErrorMsgEl = document.getElementById('phoneErrorMsg');
        const preCadastroForm = document.getElementById('preCadastroForm');
        const submitBtnEl = preCadastroForm ? preCadastroForm.querySelector('button[type="submit"]') : null;
        let isPhoneDuplicate = false;

        // Dynamic Visibility for Animal Questions
        const breedCheckboxes = document.querySelectorAll('.breed-checkbox');
        const animalQuestionsContainer = document.getElementById('animalQuestionsContainer');
        const acquisitionRadios = document.querySelectorAll('.acquisition-reason-radio');
        const purchaseAnimalCountInput = document.getElementById('purchaseAnimalCountInput');
        const categoryCheckboxes = document.querySelectorAll('.category-checkbox');
        const prodSystemRadios = document.querySelectorAll('.production-system-radio');
        const prodSystemOtherContainer = document.getElementById('productionSystemOtherContainer');
        const prodSystemOtherInput = document.getElementById('productionSystemOtherInput');

        function updateAnimalQuestionsVisibility() {
            if (!animalQuestionsContainer) return;
            let hasAnimal = false;
            breedCheckboxes.forEach(cb => {
                const val = (cb.value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                if (cb.checked && !val.includes('maquina')) {
                    hasAnimal = true;
                }
            });

            if (hasAnimal) {
                animalQuestionsContainer.classList.remove('hidden');
                animalQuestionsContainer.style.display = 'block';
                acquisitionRadios.forEach(r => r.required = true);
                if (purchaseAnimalCountInput) purchaseAnimalCountInput.required = true;
                prodSystemRadios.forEach(r => r.required = true);
                if (document.querySelector('.production-system-radio[value="Outro"]:checked') && prodSystemOtherInput) {
                    prodSystemOtherInput.required = true;
                }
            } else {
                animalQuestionsContainer.classList.add('hidden');
                animalQuestionsContainer.style.display = 'none';
                acquisitionRadios.forEach(r => {
                    r.required = false;
                    r.checked = false;
                });
                if (purchaseAnimalCountInput) {
                    purchaseAnimalCountInput.required = false;
                    purchaseAnimalCountInput.value = '';
                }
                categoryCheckboxes.forEach(cb => cb.checked = false);
                prodSystemRadios.forEach(r => {
                    r.required = false;
                    r.checked = false;
                });
                if (prodSystemOtherContainer) {
                    prodSystemOtherContainer.classList.add('hidden');
                    prodSystemOtherContainer.style.display = 'none';
                }
                if (prodSystemOtherInput) {
                    prodSystemOtherInput.required = false;
                    prodSystemOtherInput.value = '';
                }
            }
        }

        breedCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateAnimalQuestionsVisibility);
            cb.addEventListener('click', updateAnimalQuestionsVisibility);
        });

        // Run on load in case of browser-restored state
        document.addEventListener('DOMContentLoaded', updateAnimalQuestionsVisibility);
        updateAnimalQuestionsVisibility();

        prodSystemRadios.forEach(radio => {
            radio.addEventListener('change', function () {
                if (this.value === 'Outro' && this.checked) {
                    if (prodSystemOtherContainer) {
                        prodSystemOtherContainer.classList.remove('hidden');
                        prodSystemOtherContainer.style.display = 'block';
                    }
                    if (prodSystemOtherInput) {
                        prodSystemOtherInput.required = true;
                        prodSystemOtherInput.focus();
                    }
                } else {
                    if (prodSystemOtherContainer) {
                        prodSystemOtherContainer.classList.add('hidden');
                        prodSystemOtherContainer.style.display = 'none';
                    }
                    if (prodSystemOtherInput) {
                        prodSystemOtherInput.required = false;
                        prodSystemOtherInput.value = '';
                    }
                }
            });
        });

        async function checkPhoneDuplicate() {
            if (!phoneInput) return;
            const val = phoneInput.value.replace(/\D/g, '');
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
                const res = await fetch('api-check-phone.php?phone=' + encodeURIComponent(phoneInput.value));
                const data = await res.json();
                if (data.exists) {
                    isPhoneDuplicate = true;
                    if (phoneErrorMsgEl) {
                        phoneErrorMsgEl.textContent = 'Este número de telefone já está cadastrado em nosso sistema.';
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

        if (phoneInput) {
            phoneInput.addEventListener('input', function (e) {
                let x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,5})(\d{0,4})/);
                e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
                checkPhoneDuplicate();
            });
            phoneInput.addEventListener('blur', checkPhoneDuplicate);
        }

        if (preCadastroForm) {
            preCadastroForm.addEventListener('submit', function (e) {
                // Check if interest question has at least 1 option selected
                const checkedBreeds = Array.from(breedCheckboxes).filter(cb => cb.checked);
                if (checkedBreeds.length === 0) {
                    e.preventDefault();
                    alert('Por favor, selecione ao menos uma opção no campo "O que você tem interesse em adquirir".');
                    if (breedCheckboxes[0]) breedCheckboxes[0].focus();
                    return false;
                }

                // If animal breeds selected, validate animal-specific questions
                const hasAnimal = checkedBreeds.some(cb => {
                    const val = (cb.value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                    return !val.includes('maquina');
                });

                if (hasAnimal) {
                    const selectedAcquisition = document.querySelector('.acquisition-reason-radio:checked');
                    if (!selectedAcquisition) {
                        e.preventDefault();
                        alert('Por favor, selecione o motivo da aquisição dos animais.');
                        if (acquisitionRadios[0]) acquisitionRadios[0].focus();
                        return false;
                    }

                    if (purchaseAnimalCountInput && !purchaseAnimalCountInput.value.trim()) {
                        e.preventDefault();
                        alert('Por favor, informe a quantidade de animais que você necessita adquirir.');
                        purchaseAnimalCountInput.focus();
                        return false;
                    }

                    const checkedCategories = Array.from(categoryCheckboxes).filter(cb => cb.checked);
                    if (checkedCategories.length === 0) {
                        e.preventDefault();
                        alert('Por favor, selecione ao menos uma categoria de animais desejada.');
                        if (categoryCheckboxes[0]) categoryCheckboxes[0].focus();
                        return false;
                    }

                    const selectedProdSystem = document.querySelector('.production-system-radio:checked');
                    if (!selectedProdSystem) {
                        e.preventDefault();
                        alert('Por favor, selecione o sistema da sua produção.');
                        if (prodSystemRadios[0]) prodSystemRadios[0].focus();
                        return false;
                    }

                    if (selectedProdSystem.value === 'Outro' && (!prodSystemOtherInput || !prodSystemOtherInput.value.trim())) {
                        e.preventDefault();
                        alert('Por favor, especifique o sistema da sua produção no campo Outro.');
                        if (prodSystemOtherInput) prodSystemOtherInput.focus();
                        return false;
                    }
                }

                if (isPhoneDuplicate) {
                    e.preventDefault();
                    alert('Não é possível enviar. Este número de telefone já possui cadastro em nosso sistema.');
                    return false;
                }
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

            const geocoder = (window.google && window.google.maps) ? new google.maps.Geocoder() : null;
            let hasExactAddress = false;

            const cityInput = document.getElementById("cityInput");
            const ufSelect = document.getElementById("ufSelect");
            const addressInput = document.getElementById("addressInput");

            function updateInputs(latLng) {
                if (!latLng) return;
                const latVal = typeof latLng.lat === 'function' ? latLng.lat() : latLng.lat;
                const lngVal = typeof latLng.lng === 'function' ? latLng.lng() : latLng.lng;
                document.getElementById('lat').value = latVal;
                document.getElementById('lng').value = lngVal;
                const feedback = document.getElementById('geo-feedback');
                if (feedback) {
                    feedback.innerText = 'Lat: ' + Number(latVal).toFixed(6) + ', Long: ' + Number(lngVal).toFixed(6);
                }
            }

            function placeMarkerAndPanTo(latLng, zoomLevel = null) {
                if (!latLng) return;
                if (marker) {
                    marker.setPosition(latLng);
                    marker.setVisible(true);
                } else {
                    marker = new google.maps.Marker({
                        position: latLng,
                        map: map,
                    });
                }
                map.panTo(latLng);
                if (zoomLevel) map.setZoom(zoomLevel);
                updateInputs(latLng);
            }

            function geocodeCityState(force = false) {
                if (!geocoder) return;
                if (hasExactAddress && !force) return;

                const cityVal = cityInput ? cityInput.value.trim() : '';
                const ufVal = ufSelect ? ufSelect.value.trim() : '';
                if (!cityVal && !ufVal) return;

                // Extract clean city in case user typed "Castro / Fazenda Bela Vista"
                const cleanCity = cityVal.split('/')[0].split('-')[0].trim();
                const addressQuery = [cleanCity, ufVal, 'Brasil'].filter(Boolean).join(', ');
                if (!addressQuery || addressQuery === 'Brasil') return;

                geocoder.geocode({ address: addressQuery, componentRestrictions: { country: 'BR' } }, (results, status) => {
                    if (status === 'OK' && results[0] && results[0].geometry) {
                        const loc = results[0].geometry.location;
                        map.setCenter(loc);
                        if (results[0].geometry.viewport) {
                            map.fitBounds(results[0].geometry.viewport);
                        } else {
                            map.setZoom(12);
                        }
                        if (marker) {
                            marker.setPosition(loc);
                            marker.setVisible(true);
                        }
                        updateInputs(loc);
                    }
                });
            }

            let cityDebounceTimer = null;
            if (cityInput) {
                cityInput.addEventListener('blur', () => geocodeCityState());
                cityInput.addEventListener('input', () => {
                    clearTimeout(cityDebounceTimer);
                    cityDebounceTimer = setTimeout(() => {
                        if (cityInput.value.trim().length >= 3) {
                            geocodeCityState();
                        }
                    }, 800);
                });
            }

            if (ufSelect) {
                ufSelect.addEventListener('change', () => geocodeCityState());
            }

            if (addressInput && window.google && window.google.maps && window.google.maps.places) {
                const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                    componentRestrictions: { country: 'BR' }
                });
                autocomplete.bindTo("bounds", map);

                addressInput.addEventListener("keydown", (e) => {
                    if (e.key === "Enter") e.preventDefault();
                });

                autocomplete.addListener("place_changed", () => {
                    const place = autocomplete.getPlace();

                    if (!place.geometry || !place.geometry.location) {
                        return;
                    }

                    hasExactAddress = true;
                    if (place.geometry.viewport) {
                        map.fitBounds(place.geometry.viewport);
                    } else {
                        map.setCenter(place.geometry.location);
                        map.setZoom(17);
                    }

                    placeMarkerAndPanTo(place.geometry.location, 17);
                });

                addressInput.addEventListener('blur', () => {
                    const addr = addressInput.value.trim();
                    if (!addr) {
                        hasExactAddress = false;
                        geocodeCityState(true);
                        return;
                    }

                    if (!hasExactAddress && addr.length > 5 && geocoder) {
                        const cityVal = cityInput ? cityInput.value.trim().split('/')[0].trim() : '';
                        const ufVal = ufSelect ? ufSelect.value.trim() : '';
                        const fullAddr = [addr, cityVal, ufVal, 'Brasil'].filter(Boolean).join(', ');

                        geocoder.geocode({ address: fullAddr, componentRestrictions: { country: 'BR' } }, (results, status) => {
                            if (status === 'OK' && results[0] && results[0].geometry) {
                                hasExactAddress = true;
                                const loc = results[0].geometry.location;
                                placeMarkerAndPanTo(loc, 16);
                            }
                        });
                    }
                });
            }

            // Click on map
            map.addListener("click", (e) => {
                hasExactAddress = true;
                placeMarkerAndPanTo(e.latLng);
            });

            // Try Geolocation as initial hint if inputs are empty
            if (navigator.geolocation && (!cityInput || !cityInput.value.trim())) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        if (!hasExactAddress && (!cityInput || !cityInput.value.trim())) {
                            const pos = {
                                lat: position.coords.latitude,
                                lng: position.coords.longitude,
                            };
                            map.setCenter(pos);
                            map.setZoom(11);
                        }
                    }
                );
            }
        }
    </script>
    <script
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBaWNV6Gc1D-0ZNrGBXxEe2qwbcw4OhDFo&callback=initMap&libraries=places&v=weekly"
        async></script>
</body>

</html>