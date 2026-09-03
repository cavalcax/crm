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

    if ($is_milk_producer === 'Não') {
        $animal_count_range = '';
        $milk_production_range = '';
    }

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
    } elseif ($is_milk_producer === 'Sim' && empty($animal_count_range)) {
        $error = "Por favor, selecione a quantidade de animais que possui atualmente.";
    } elseif ($is_milk_producer === 'Sim' && (empty($milk_production_range) || $milk_production_range === '0.000' || (int) preg_replace('/\D/', '', $milk_production_range) === 0)) {
        $error = "Por favor, informe quantos litros de leite você entrega por mês atualmente.";
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
            $formattedMilkProd = (strpos($milk_production_range, 'litro') === false)
                ? $milk_production_range
                : $milk_production_range;

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
                $formattedMilkProd,
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
                                        class="is-milk-producer-radio h-4 w-4 text-brand-500 focus:ring-brand-500 border-gray-300">
                                    <span class="ml-3 text-sm text-gray-700 font-medium"><?php echo $opt; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Bloco condicional para quem já é Produtor de Leite -->
                    <div id="milkProducerQuestionsContainer" class="space-y-6">
                        <!-- Quantidade de animais que possui atualmente -->
                        <div class="bg-brand-50 p-5 rounded-xl border border-brand-100">
                            <label class="block text-brand-900 font-bold mb-3 text-base">
                                Quantos animais você possui atualmente?
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <?php foreach (['0 a 50 animais', '51 a 100 animais', '101 a 150 animais', '151 a 200 animais', 'Mais de 200 animais'] as $opt): ?>
                                    <label
                                        class="flex items-center p-3 bg-white border border-gray-200 rounded-lg cursor-pointer hover:border-brand-500 transition">
                                        <input type="radio" name="animal_count_range" value="<?php echo $opt; ?>"
                                            class="animal-count-radio h-4 w-4 text-brand-500 focus:ring-brand-500 border-gray-300">
                                        <span class="ml-3 text-sm text-gray-700 font-medium"><?php echo $opt; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Quantos litros de leite você entrega por mês atualmente? -->
                        <div class="bg-brand-50 p-5 rounded-xl border border-brand-100">
                            <label for="milkProductionInput" class="block text-brand-900 font-bold mb-1 text-base">
                                Quantos litros de leite você entrega por mês atualmente?
                            </label>
                            <p class="text-xs text-gray-500 mb-3">Digite a quantidade mensal aproximada de litros de leite
                                entregues.</p>
                            <div class="relative max-w-xs">
                                <input type="text" inputmode="numeric" name="milk_production_range" id="milkProductionInput"
                                    value="<?php echo htmlspecialchars(!empty($milk_production_range) ? $milk_production_range : '0.000'); ?>"
                                    class="w-full pl-4 pr-24 py-3 border border-gray-300 rounded-lg text-lg font-bold text-gray-800 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 bg-white">
                                <span
                                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 pointer-events-none text-xs font-bold text-gray-400 uppercase">
                                    Litros/Mês
                                </span>
                            </div>
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

                        <div class="relative" id="addressInputWrapper">
                            <label class="block text-sm font-bold text-gray-700 mb-1">Endereço Completo / Localização da Fazenda (Busca com Autocompletar)</label>
                            <input type="text" id="addressInput" name="address" autocomplete="off"
                                placeholder="Digite rua, fazenda, bairro ou cidade para buscar..."
                                class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                            <p class="text-xs text-gray-500 mt-1">Digite o endereço ou selecione no mapa abaixo o local exato da propriedade.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Localização no Mapa (Clique ou arraste o pino para marcar)</label>
                            <div id="map" class="h-80 w-full rounded-xl border border-gray-300 shadow-inner z-0"></div>
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

        // Dynamic Visibility for Milk Producer Questions
        const milkProducerRadios = document.querySelectorAll('.is-milk-producer-radio');
        const milkProducerQuestionsContainer = document.getElementById('milkProducerQuestionsContainer');
        const animalCountRadios = document.querySelectorAll('.animal-count-radio');
        const milkProductionInput = document.getElementById('milkProductionInput');

        function updateMilkProducerQuestionsVisibility() {
            if (!milkProducerQuestionsContainer) return;
            const selectedRadio = document.querySelector('.is-milk-producer-radio:checked');
            const isProducer = selectedRadio && selectedRadio.value === 'Sim';

            if (isProducer) {
                milkProducerQuestionsContainer.classList.remove('hidden');
                milkProducerQuestionsContainer.style.display = 'block';
                animalCountRadios.forEach(r => r.required = true);
                if (milkProductionInput) milkProductionInput.required = true;
            } else {
                milkProducerQuestionsContainer.classList.add('hidden');
                milkProducerQuestionsContainer.style.display = 'none';
                animalCountRadios.forEach(r => {
                    r.required = false;
                    r.checked = false;
                });
                if (milkProductionInput) {
                    milkProductionInput.required = false;
                    milkProductionInput.value = '0.000';
                }
            }
        }

        milkProducerRadios.forEach(r => {
            r.addEventListener('change', updateMilkProducerQuestionsVisibility);
            r.addEventListener('click', updateMilkProducerQuestionsVisibility);
        });

        document.addEventListener('DOMContentLoaded', () => {
            updateAnimalQuestionsVisibility();
            updateMilkProducerQuestionsVisibility();
        });
        updateMilkProducerQuestionsVisibility();

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

        // Milk Production (Thousand Mask: 0.000 -> 0.005 -> 0.050 -> 0.500 -> 5.000 -> 50.000)
        const milkInput = document.getElementById('milkProductionInput');

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

        if (milkInput) {
            if (milkInput.value) {
                milkInput.value = formatMilkLiters(milkInput.value);
            }

            milkInput.addEventListener('input', function () {
                this.value = formatMilkLiters(this.value);
            });

            milkInput.addEventListener('focus', function () {
                if (!this.value || this.value === '0.000') {
                    this.value = '0.000';
                }
                setTimeout(() => this.select(), 50);
            });

            milkInput.addEventListener('blur', function () {
                if (!this.value) {
                    this.value = '0.000';
                } else {
                    this.value = formatMilkLiters(this.value);
                }
            });
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

                // Validate Milk production liters
                if (milkInput) {
                    const rawMilk = milkInput.value.replace(/\D/g, '');
                    if (!rawMilk || parseInt(rawMilk, 10) === 0) {
                        e.preventDefault();
                        alert('Por favor, informe quantos litros de leite você entrega por mês atualmente.');
                        milkInput.focus();
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

    <?php if (defined('MAP_PROVIDER') && MAP_PROVIDER === 'google_maps'): ?>
    <!-- Google Maps JS API -->
    <script>
        let map = null;
        let marker = null;
        let geocoder = null;

        function updateInputs(lat, lng) {
            document.getElementById('lat').value = lat.toFixed(8);
            document.getElementById('lng').value = lng.toFixed(8);
            const feedback = document.getElementById('geo-feedback');
            if (feedback) {
                feedback.innerText = `Lat: ${lat.toFixed(6)}, Long: ${lng.toFixed(6)}`;
            }
        }

        function placeMarker(lat, lng, zoomTo = false) {
            if (!map) return;
            const pos = { lat, lng };
            if (marker) {
                marker.setPosition(pos);
            } else {
                marker = new google.maps.Marker({
                    position: pos,
                    map: map,
                    draggable: true
                });
                marker.addListener('dragend', () => {
                    const p = marker.getPosition();
                    updateInputs(p.lat(), p.lng());
                });
            }
            updateInputs(lat, lng);
            if (zoomTo) {
                map.setCenter(pos);
                map.setZoom(15);
            }
        }

        function geocodeCityState() {
            if (!geocoder) return;
            const cityInput = document.getElementById("cityInput");
            const ufSelect = document.getElementById("ufSelect");
            const cityVal = cityInput ? cityInput.value.trim() : '';
            const ufVal = ufSelect ? ufSelect.value.trim() : '';
            if (!cityVal && !ufVal) return;

            const cleanCity = cityVal.split('/')[0].split('-')[0].trim();
            const address = [cleanCity, ufVal, 'Brasil'].filter(Boolean).join(', ');

            geocoder.geocode({ address, componentRestrictions: { country: 'BR' } }, (results, status) => {
                if (status === 'OK' && results[0] && results[0].geometry) {
                    const lat = results[0].geometry.location.lat();
                    const lng = results[0].geometry.location.lng();
                    placeMarker(lat, lng, true);
                }
            });
        }

        function initMap() {
            const defaultPos = { lat: -25.4284, lng: -49.2733 }; // Curitiba / Paraná default
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 7,
                center: defaultPos,
                mapTypeControl: true,
                streetViewControl: false
            });
            geocoder = new google.maps.Geocoder();

            map.addListener('click', (e) => {
                placeMarker(e.latLng.lat(), e.latLng.lng());
            });

            const cityInput = document.getElementById("cityInput");
            const ufSelect = document.getElementById("ufSelect");
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

            const addressInput = document.getElementById("addressInput");
            if (addressInput && window.google && google.maps && google.maps.places) {
                const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                    componentRestrictions: { country: "br" }
                });
                autocomplete.addListener("place_changed", () => {
                    const place = autocomplete.getPlace();
                    if (place.geometry && place.geometry.location) {
                        const lat = place.geometry.location.lat();
                        const lng = place.geometry.location.lng();
                        placeMarker(lat, lng, true);
                    }
                });
            }

            if (navigator.geolocation && (!cityInput || !cityInput.value.trim())) {
                navigator.geolocation.getCurrentPosition((pos) => {
                    if (!marker) {
                        map.setCenter({ lat: pos.coords.latitude, lng: pos.coords.longitude });
                        map.setZoom(11);
                    }
                }, () => {});
            }
        }
        window.initMap = initMap;
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&libraries=places&loading=async&callback=initMap" async defer></script>
    <?php else: ?>
    <!-- Leaflet CSS & JS (100% Gratuito) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
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

    <script>
        let map = null;
        let marker = null;

        function updateInputs(lat, lng) {
            document.getElementById('lat').value = lat.toFixed(8);
            document.getElementById('lng').value = lng.toFixed(8);
            const feedback = document.getElementById('geo-feedback');
            if (feedback) {
                feedback.innerText = `Lat: ${lat.toFixed(6)}, Long: ${lng.toFixed(6)}`;
            }
        }

        function placeMarker(lat, lng, zoomTo = false) {
            if (!map) return;
            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                marker.on('dragend', () => {
                    const pos = marker.getLatLng();
                    updateInputs(pos.lat, pos.lng);
                });
            }
            updateInputs(lat, lng);
            if (zoomTo) {
                map.setView([lat, lng], 15);
            }
        }

        function geocodeCityState() {
            const cityInput = document.getElementById("cityInput");
            const ufSelect = document.getElementById("ufSelect");
            const cityVal = cityInput ? cityInput.value.trim() : '';
            const ufVal = ufSelect ? ufSelect.value.trim() : '';
            if (!cityVal && !ufVal) return;

            const cleanCity = cityVal.split('/')[0].split('-')[0].trim();
            const query = [cleanCity, ufVal, 'Brasil'].filter(Boolean).join(', ');

            fetch(`https://nominatim.openstreetmap.org/search?format=json&countrycodes=br&limit=1&q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const lat = parseFloat(data[0].lat);
                        const lng = parseFloat(data[0].lon);
                        placeMarker(lat, lng, true);
                    }
                })
                .catch(() => {});
        }

        function initMap() {
            const defaultPos = [-25.4284, -49.2733]; // Curitiba / Paraná default

            map = L.map('map').setView(defaultPos, 7);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            map.on('click', (e) => {
                placeMarker(e.latlng.lat, e.latlng.lng);
            });

            const cityInput = document.getElementById("cityInput");
            const ufSelect = document.getElementById("ufSelect");

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

            // Autocomplete de endereço gratuito via Nominatim
            const addressInput = document.getElementById("addressInput");
            const wrapper = document.getElementById("addressInputWrapper");
            if (addressInput && wrapper) {
                const dropdown = document.createElement("div");
                dropdown.className = "autocomplete-dropdown hidden";
                wrapper.appendChild(dropdown);

                let debounceTimer = null;
                addressInput.addEventListener("input", () => {
                    const query = addressInput.value.trim();
                    clearTimeout(debounceTimer);
                    if (query.length < 3) {
                        dropdown.classList.add("hidden");
                        dropdown.innerHTML = "";
                        return;
                    }

                    debounceTimer = setTimeout(() => {
                        fetch(`https://nominatim.openstreetmap.org/search?format=json&countrycodes=br&limit=5&q=${encodeURIComponent(query)}`)
                            .then(res => res.json())
                            .then(data => {
                                dropdown.innerHTML = "";
                                if (!data || data.length === 0) {
                                    dropdown.innerHTML = '<div class="px-3 py-2 text-xs text-gray-500">Nenhum endereço encontrado</div>';
                                    dropdown.classList.remove("hidden");
                                    return;
                                }
                                data.forEach(item => {
                                    const opt = document.createElement("div");
                                    opt.className = "autocomplete-item";
                                    opt.innerHTML = `<span class="font-bold text-gray-800">${item.display_name.split(',')[0]}</span><span class="text-gray-500 text-[11px] block truncate">${item.display_name}</span>`;
                                    opt.addEventListener("click", () => {
                                        addressInput.value = item.display_name;
                                        dropdown.classList.add("hidden");
                                        const lat = parseFloat(item.lat);
                                        const lng = parseFloat(item.lon);
                                        placeMarker(lat, lng, true);
                                    });
                                    dropdown.appendChild(opt);
                                });
                                dropdown.classList.remove("hidden");
                            })
                            .catch(() => dropdown.classList.add("hidden"));
                    }, 350);
                });

                document.addEventListener("click", (e) => {
                    if (!wrapper.contains(e.target)) dropdown.classList.add("hidden");
                });

                addressInput.addEventListener("keydown", (e) => {
                    if (e.key === "Enter") e.preventDefault();
                });
            }

            // Tenta obter geolocalização se campos estiverem vazios
            if (navigator.geolocation && (!cityInput || !cityInput.value.trim())) {
                navigator.geolocation.getCurrentPosition((pos) => {
                    if (!marker) {
                        map.setView([pos.coords.latitude, pos.coords.longitude], 11);
                    }
                }, () => {});
            }
        }

        document.addEventListener('DOMContentLoaded', initMap);
    </script>
    <?php endif; ?>
</body>

</html>