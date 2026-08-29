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
    }
    elseif ($len == 10) {
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
    if (empty($token)) return null;
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

    if (!empty($intentions)) {
        $msg .= "\n*Intenções Registradas:*\n";
        foreach ($intentions as $i) {
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
?>