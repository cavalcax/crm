<?php
function isLoggedIn()
{
    global $pdo;
    if (!empty($_SESSION['user_id'])) {
        return true;
    }

    // Auto-login from persistent cookie if session is expired
    if (!empty($_COOKIE['remember_token']) && isset($pdo)) {
        $token = $_COOKIE['remember_token'];
        $parts = explode(':', $token, 2);
        if (count($parts) === 2) {
            $userId = intval($parts[0]);
            $tokenHash = hash('sha256', $parts[1]);

            try {
                $stmt = $pdo->prepare("SELECT * FROM " . TABLE_NAME . "users WHERE id = ? AND remember_token = ?");
                $stmt->execute([$userId, $tokenHash]);
                $user = $stmt->fetch();

                if ($user) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    return true;
                }
            } catch (Exception $e) {
                // Ignore query error
            }
        }
        // If token is invalid, clear expired cookie
        if (!headers_sent()) {
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
    }

    return false;
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header("Location: ../login.php");
        exit;
    }
    if (!headers_sent()) {
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: 0");
    }
}

function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

// Format phone number (XX) XXXXX-XXXX or (XX) XXXX-XXXX
function formatPhone($phone)
{
    $phone = preg_replace("/[^0-9]/", "", $phone);
    $len = strlen($phone);

    if ($len == 11) {
        return sprintf("(%s) %s-%s", substr($phone, 0, 2), substr($phone, 2, 5), substr($phone, 7));
    } elseif ($len == 10) {
        return sprintf("(%s) %s-%s", substr($phone, 0, 2), substr($phone, 2, 4), substr($phone, 6));
    }

    return $phone;
}

function jsonResponse($data, $status = 200)
{
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function getBrazilianStates()
{
    return [
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahia',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins'
    ];
}

// Token encryption for public pre-registration links
function encryptUserId($user_id)
{
    $secret = 'CTCRM_VITOR_MULLER_SECRET_2026';
    $str = $user_id . '|' . $secret;
    return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
}

function decryptUserId($token)
{
    if (empty($token))
        return null;
    $secret = 'CTCRM_VITOR_MULLER_SECRET_2026';
    $b64 = strtr($token, '-_', '+/');
    $padded = $b64 . str_repeat('=', (4 - strlen($b64) % 4) % 4);
    $data = base64_decode($padded);
    if ($data && strpos($data, '|' . $secret) !== false) {
        $parts = explode('|' . $secret, $data);
        return intval($parts[0]);
    }
    return null;
}

// Build standard welcome and commercial profile questionnaire message for WhatsApp
function buildClientApprovalWelcomeMessage($client)
{
    $name = trim($client['name'] ?? '');
    $farm = trim($client['farm_name'] ?? '');
    $city = trim($client['city'] ?? '');
    $uf = trim($client['uf'] ?? '');
    $loc = array_filter([$city, $uf]);
    $locStr = !empty($loc) ? implode(' / ', $loc) : 'Não informada';

    $payment = trim($client['payment_condition'] ?? '') ?: '-';
    $breeds = trim($client['breed_interests'] ?? '') ?: '-';
    $purchaseCount = trim($client['purchase_animal_count'] ?? '');
    $categories = trim($client['animal_categories'] ?? '');
    $prodSystem = trim($client['production_system'] ?? '');
    $isMilkProducer = trim($client['is_milk_producer'] ?? '') ?: '-';
    $reason = trim($client['acquisition_reason'] ?? '') ?: '-';
    $animalCount = trim($client['animal_count_range'] ?? '') ?: '-';
    $milkProd = trim($client['milk_production_range'] ?? '') ?: '-';

    $msg = "Olá, {$name}!\n\n";
    $msg .= "Recebemos e confirmamos os dados informados\n\n";

    if (!empty($farm)) {
        $msg .= "*Fazenda:* {$farm}\n";
    }
    $msg .= "*Localização:* {$locStr}\n";
    $msg .= "*Condição de Pagamento:* {$payment}\n";
    $msg .= "*Interesse em Adquirir:* {$breeds}\n";
    if (!empty($reason) && $reason !== '-') {
        $msg .= "*Motivo da Aquisição:* {$reason}\n";
    }
    if (!empty($purchaseCount)) {
        $msg .= "*Qtd. Necessária:* {$purchaseCount}\n";
    }
    if (!empty($categories)) {
        $msg .= "*Categorias Desejadas:* {$categories}\n";
    }
    if (!empty($prodSystem)) {
        $msg .= "*Sistema de Produção:* {$prodSystem}\n";
    }
    $msg .= "*Produtor de Leite:* {$isMilkProducer}\n";
    $msg .= "*Quantidade de Animais:* {$animalCount}\n";
    $msg .= "*Produção Diária de Leite:* {$milkProd}\n\n";

    $msg .= "Já estamos selecionando as melhores oportunidades e lotes alinhados ao seu perfil. Em breve entraremos em contato com novidades exclusivas!\n\n";
    $msg .= "Qualquer dúvida ou necessidade, estou à total disposição por aqui!";

    return $msg;
}

// Build standard data summary message to share with Embral via WhatsApp
function buildClientEmbralWhatsAppMessage($client, $intentions = [])
{
    $name = trim($client['name'] ?? '');
    $farm = trim($client['farm_name'] ?? '');
    $phone = trim($client['phone'] ?? '');
    $email = trim($client['email'] ?? '');
    $city = trim($client['city'] ?? '');
    $uf = trim($client['uf'] ?? '');
    $loc = array_filter([$city, $uf]);
    $locStr = !empty($loc) ? implode(' / ', $loc) : 'Não informada';

    $payment = trim($client['payment_condition'] ?? '') ?: '-';
    $breeds = trim($client['breed_interests'] ?? '') ?: '-';
    $purchaseCount = trim($client['purchase_animal_count'] ?? '');
    $categories = trim($client['animal_categories'] ?? '');
    $prodSystem = trim($client['production_system'] ?? '');
    $isMilkProducer = trim($client['is_milk_producer'] ?? '') ?: '-';
    $reason = trim($client['acquisition_reason'] ?? '') ?: '-';
    $animalCount = trim($client['animal_count_range'] ?? '') ?: '-';
    $milkProd = trim($client['milk_production_range'] ?? '') ?: '-';

    $msg = "📋 *FICHA DE CADASTRO DO CLIENTE - EMBRAL*\n\n";
    $msg .= "*Nome:* {$name}\n";
    if (!empty($farm)) {
        $msg .= "*Fazenda:* {$farm}\n";
    }
    if (!empty($phone)) {
        $msg .= "*Telefone/WhatsApp:* " . formatPhone($phone) . "\n";
    }
    if (!empty($email)) {
        $msg .= "*E-mail:* {$email}\n";
    }
    $msg .= "*Localização:* {$locStr}\n";
    $msg .= "*Condição de Pagamento:* {$payment}\n";
    $msg .= "*Interesse em Adquirir:* {$breeds}\n";
    if (!empty($reason) && $reason !== '-') {
        $msg .= "*Motivo da Aquisição:* {$reason}\n";
    }
    if (!empty($purchaseCount)) {
        $msg .= "*Qtd. Necessária:* {$purchaseCount}\n";
    }
    if (!empty($categories)) {
        $msg .= "*Categorias Desejadas:* {$categories}\n";
    }
    if (!empty($prodSystem)) {
        $msg .= "*Sistema de Produção:* {$prodSystem}\n";
    }
    $msg .= "*Produtor de Leite:* {$isMilkProducer}\n";
    $msg .= "*Quantidade de Animais:* {$animalCount}\n";
    $msg .= "*Produção Diária de Leite:* {$milkProd}\n";

    $activeIntentions = array_filter($intentions, function($i) {
        return empty($i['status']) || $i['status'] === 'active';
    });

    if (!empty($activeIntentions)) {
        $msg .= "\n*Intenções Registradas:*\n";
        foreach ($activeIntentions as $i) {
            $type = ($i['type'] === 'buy') ? '🛒 Compra' : '💰 Venda';
            $cat = !empty($i['category_name']) ? " ({$i['category_name']})" : "";
            $val = !empty($i['value']) ? " - R$ " . number_format($i['value'], 2, ',', '.') : "";
            $msg .= "- {$type}{$cat}: " . ($i['description'] ?: '-') . "{$val}\n";
        }
    }

    $msg .= "\n_Enviado via CRM Vitor Müller_";
    return $msg;
}

// Check if a client with the given phone number already exists
function findClientByPhone($pdo, $phone, $excludeId = null)
{
    $phoneClean = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phoneClean) < 8) {
        return null;
    }

    try {
        $sql = "SELECT id, name, phone, user_id FROM " . TABLE_NAME . "clients 
                WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', ''), '+', ''), '.', '') = ?";
        $params = [$phoneClean];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = intval($excludeId);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($found) {
            return $found;
        }

        // Fallback search through records to match exact cleaned phone
        $sqlFallback = "SELECT id, name, phone, user_id FROM " . TABLE_NAME . "clients";
        if ($excludeId) {
            $sqlFallback .= " WHERE id != " . intval($excludeId);
        }
        $stmt = $pdo->query($sqlFallback);
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($all as $client) {
            $dbClean = preg_replace('/[^0-9]/', '', $client['phone'] ?? '');
            if (!empty($dbClean) && $dbClean === $phoneClean) {
                return $client;
            }
        }
    } catch (Exception $e) {
        // Fallback error handling
    }

    return null;
}

// Standard options helpers for filters and forms
function getStandardBreeds()
{
    return ['Jersey', 'Holandês', 'Jersolando', 'Girolando', 'Gir', 'Máquinas'];
}

function getStandardAnimalCategories()
{
    return [
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
}

function getStandardProductionSystems()
{
    return ['Pasto', 'Semi-confinamento', 'Compost Barn', 'Free Stall', 'Outro'];
}

function getStandardPaymentConditions()
{
    return ['10 pagamentos', '12 pagamentos', '15 pagamentos', 'À vista'];
}

function getStandardClientStatuses()
{
    return [
        'Novo' => '🟡 Novo / Pré-cadastro',
        'Atendido' => '🟣 Atendido',
        'Embral' => '🔵 Embral',
        'Ativo' => '🟢 Ativo',
        'Inativo' => '⚫ Inativo'
    ];
}

// Helper to render responsive Multi-Select dropdown component
function renderMultiSelect($name, $label, $options, $selectedValues = [], $placeholder = 'Todos', $enableSearch = false)
{
    // Normalize options if indexed array
    $normalizedOptions = [];
    foreach ($options as $key => $value) {
        if (is_numeric($key)) {
            $normalizedOptions[$value] = $value;
        } else {
            $normalizedOptions[$key] = $value;
        }
    }

    // Normalize selected values as array of strings
    if (!is_array($selectedValues)) {
        $selectedValues = (!empty($selectedValues) || $selectedValues === '0' || $selectedValues === 0) ? [(string) $selectedValues] : [];
    } else {
        $selectedValues = array_map('strval', $selectedValues);
    }

    $selectedCount = count($selectedValues);
    $selectedLabels = [];
    foreach ($selectedValues as $val) {
        if (isset($normalizedOptions[$val])) {
            $selectedLabels[] = $normalizedOptions[$val];
        }
    }

    $displayLabel = $placeholder;
    if ($selectedCount === 1) {
        $displayLabel = $selectedLabels[0] ?? $selectedValues[0];
    } elseif ($selectedCount > 1) {
        if ($selectedCount <= 2) {
            $displayLabel = implode(', ', $selectedLabels);
        } else {
            $displayLabel = $selectedCount . ' selecionados';
        }
    }

    ob_start();
    ?>
    <div class="relative custom-multiselect" data-name="<?php echo htmlspecialchars($name); ?>"
        data-placeholder="<?php echo htmlspecialchars($placeholder); ?>">
        <label class="block text-xs font-bold text-gray-700 mb-1"><?php echo htmlspecialchars($label); ?></label>
        <button type="button"
            class="multiselect-toggle w-full flex items-center justify-between bg-white border border-gray-300 hover:border-brand-500 rounded-lg py-2 px-3 text-xs sm:text-sm text-left shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-500 transition cursor-pointer">
            <span
                class="multiselect-label truncate text-gray-700 font-medium <?php echo $selectedCount > 0 ? 'text-brand-900 font-semibold' : 'text-gray-500 font-normal'; ?>">
                <?php echo htmlspecialchars($displayLabel); ?>
            </span>
            <div class="flex items-center gap-1.5 ml-1.5 flex-shrink-0 text-gray-400">
                <span
                    class="multiselect-badge <?php echo $selectedCount > 0 ? '' : 'hidden'; ?> bg-brand-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none">
                    <?php echo $selectedCount; ?>
                </span>
                <svg class="multiselect-arrow w-4 h-4 transform transition-transform text-gray-400" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </div>
        </button>

        <div
            class="multiselect-menu hidden absolute left-0 right-0 z-[100] mt-1 bg-white border border-gray-200 rounded-xl shadow-2xl max-h-72 min-w-[240px] flex flex-col overflow-hidden text-xs sm:text-sm">
            <div class="p-2 border-b border-gray-100 bg-gray-50 flex items-center justify-between gap-2">
                <?php if ($enableSearch): ?>
                    <input type="text"
                        class="multiselect-search w-full px-2 py-1 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-brand-500 bg-white placeholder-gray-400"
                        placeholder="Buscar opções...">
                <?php endif; ?>
                <div class="flex items-center gap-2 flex-shrink-0 ml-auto">
                    <button type="button"
                        class="multiselect-select-all text-[11px] text-brand-600 hover:text-brand-800 font-bold hover:underline cursor-pointer">Todos</button>
                    <span class="text-gray-300">|</span>
                    <button type="button"
                        class="multiselect-clear text-[11px] text-gray-500 hover:text-red-600 font-bold hover:underline cursor-pointer">Limpar</button>
                </div>
            </div>

            <div class="multiselect-options overflow-y-auto p-1.5 space-y-0.5 flex-1">
                <?php foreach ($normalizedOptions as $val => $optLabel): ?>
                    <?php $isChecked = in_array((string) $val, $selectedValues, true); ?>
                    <label
                        class="multiselect-item flex items-center px-2 py-1.5 hover:bg-brand-50/70 rounded-md cursor-pointer transition select-none">
                        <input type="checkbox" name="<?php echo htmlspecialchars($name); ?>[]"
                            value="<?php echo htmlspecialchars($val); ?>" <?php echo $isChecked ? 'checked' : ''; ?>
                            class="multiselect-checkbox h-4 w-4 text-brand-600 rounded border-gray-300 focus:ring-brand-500 mr-2.5 flex-shrink-0 cursor-pointer">
                        <span
                            class="multiselect-text text-gray-700 text-xs sm:text-sm leading-snug"><?php echo htmlspecialchars($optLabel); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>