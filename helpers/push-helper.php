<?php
/**
 * Push Notifications & Lembretes Helper
 * Suporte completo a Web Push RFC 8291 / RFC 8292 (VAPID + AES-128-GCM) e notificações internas
 */

function getOpenSslConfigPath()
{
    $env = getenv('OPENSSL_CONF');
    if ($env && file_exists($env)) {
        return $env;
    }
    $candidates = [
        defined('PHP_BINARY') ? dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf' : null,
        defined('PHP_BINARY') ? dirname(PHP_BINARY) . '/openssl.cnf' : null,
        'C:/wamp64/bin/php/php8.2.13/extras/ssl/openssl.cnf',
        'C:/wamp64/bin/apache/apache2.4.58/conf/openssl.cnf',
        'C:/xampp/php/extras/ssl/openssl.cnf',
        'C:/laragon/bin/php/openssl.cnf',
        '/etc/ssl/openssl.cnf',
        '/usr/lib/ssl/openssl.cnf'
    ];
    foreach ($candidates as $c) {
        if ($c && file_exists($c)) {
            return $c;
        }
    }
    return null;
}

function base64UrlEncode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data)
{
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=', STR_PAD_RIGHT));
}

function derToP1363($der)
{
    $pos = 2;
    if (ord($der[1]) & 0x80) {
        $pos += (ord($der[1]) & 0x7f);
    }

    // R
    $pos++;
    $rLen = ord($der[$pos++]);
    $r = substr($der, $pos, $rLen);
    $pos += $rLen;

    // S
    $pos++;
    $sLen = ord($der[$pos++]);
    $s = substr($der, $pos, $sLen);

    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

    return $r . $s;
}

// Retorna ou gera as chaves VAPID do sistema
function getVapidKeys()
{
    $vapidConfigFile = __DIR__ . '/../config/vapid.json';

    if (file_exists($vapidConfigFile)) {
        $data = json_decode(file_get_contents($vapidConfigFile), true);
        if (!empty($data['publicKey']) && !empty($data['privateKey'])) {
            return $data;
        }
    }

    $keys = null;
    if (function_exists('openssl_pkey_new')) {
        $cnf = getOpenSslConfigPath();
        $ecConfig = [
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC
        ];
        if ($cnf) {
            $ecConfig['config'] = $cnf;
        }

        $res = @openssl_pkey_new($ecConfig);

        if ($res) {
            $details = openssl_pkey_get_details($res);
            if (!empty($details['ec']['d']) && !empty($details['ec']['x']) && !empty($details['ec']['y'])) {
                $x = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
                $y = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);
                $d = str_pad($details['ec']['d'], 32, "\0", STR_PAD_LEFT);
                $rawPub = "\x04" . $x . $y;
                $keys = [
                    'publicKey' => rtrim(strtr(base64_encode($rawPub), '+/', '-_'), '='),
                    'privateKey' => rtrim(strtr(base64_encode($d), '+/', '-_'), '='),
                    'subject' => 'mailto:contato@catec.inf.br'
                ];
            }
        }
    }

    if (!$keys) {
        $keys = [
            'publicKey' => 'BKYvM3qMQq0ggoJTh9zKyhlGh_PlJFnAW21E5OfWLRjfjUSrJ4Q9kqd3oRKT3B54f1EvLgsu_yqq41m6X388_Z0',
            'privateKey' => 'Z6DFeTuWJVHY0DEU58CQcS2u1DFnBvr-sIXHCKUduXA',
            'subject' => 'mailto:contato@catec.inf.br'
        ];
    }

    @file_put_contents($vapidConfigFile, json_encode($keys, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $keys;
}

/**
 * Cria o JWT assinado com a chave privada VAPID para autorização junto ao servidor Push (FCM/Apple/Mozilla)
 */
function createVapidJwt($audience, $subject, $publicKey, $privateKey)
{
    $header = json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES);
    $payload = json_encode([
        'aud' => $audience,
        'exp' => time() + 86400,
        'sub' => $subject
    ], JSON_UNESCAPED_SLASHES);

    $jwtData = base64UrlEncode($header) . '.' . base64UrlEncode($payload);

    $d = base64UrlDecode($privateKey);
    $x = substr(base64UrlDecode($publicKey), 1, 32);
    $y = substr(base64UrlDecode($publicKey), 33, 32);

    $sec1 = "\x30\x77\x02\x01\x01\x04\x20" . $d . "\xa0\x0a\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\xa1\x44\x03\x42\x00\x04" . $x . $y;
    $pem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($sec1), 64, "\n") . "-----END EC PRIVATE KEY-----\n";

    $privKeyResource = openssl_pkey_get_private($pem);
    if (!$privKeyResource) {
        throw new Exception("Falha ao carregar chave privada VAPID: " . openssl_error_string());
    }

    $sig = '';
    $signed = openssl_sign($jwtData, $sig, $privKeyResource, OPENSSL_ALGO_SHA256);
    if (!$signed) {
        throw new Exception("Falha ao assinar JWT VAPID: " . openssl_error_string());
    }

    $p1363Sig = derToP1363($sig);
    return $jwtData . '.' . base64UrlEncode($p1363Sig);
}

/**
 * Criptografa o payload no formato RFC 8291 (aes128gcm) para entrega segura ao navegador/celular
 */
function encryptWebPushPayload($payloadText, $userPublicKeyBase64, $userAuthSecretBase64)
{
    $userPublicKey = base64UrlDecode($userPublicKeyBase64); // 65 bytes
    $userAuth = base64UrlDecode($userAuthSecretBase64); // 16 bytes

    $cnf = getOpenSslConfigPath();
    $ecConfig = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
    if ($cnf) {
        $ecConfig['config'] = $cnf;
    }

    $localKeyRes = openssl_pkey_new($ecConfig);
    if (!$localKeyRes) {
        throw new Exception("Falha ao gerar chave efêmera de criptografia: " . openssl_error_string());
    }

    $localDetails = openssl_pkey_get_details($localKeyRes);
    $localX = str_pad($localDetails['ec']['x'], 32, "\0", STR_PAD_LEFT);
    $localY = str_pad($localDetails['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $localPub = "\x04" . $localX . $localY; // 65 bytes

    // Chave pública do usuário em formato PKCS#8 DER
    $userPubDer = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00" . $userPublicKey;
    $userPubPem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($userPubDer), 64, "\n") . "-----END PUBLIC KEY-----\n";
    $userKeyRes = openssl_pkey_get_public($userPubPem);
    if (!$userKeyRes) {
        throw new Exception("Falha ao carregar chave pública do usuário: " . openssl_error_string());
    }

    // Segredo compartilhado ECDH (32 bytes)
    $sharedSecret = openssl_pkey_derive($userKeyRes, $localKeyRes);
    if (!$sharedSecret) {
        throw new Exception("Falha na derivação ECDH: " . openssl_error_string());
    }

    // Derivação de chaves HKDF (RFC 8291 aes128gcm)
    $authInfo = "WebPush: info\0" . $userPublicKey . $localPub;
    $ikm = hash_hkdf('sha256', $sharedSecret, 32, $authInfo, $userAuth);

    $salt = random_bytes(16);

    $cekInfo = "Content-Encoding: aes128gcm\0";
    $cek = hash_hkdf('sha256', $ikm, 16, $cekInfo, $salt);

    $nonceInfo = "Content-Encoding: nonce\0";
    $nonce = hash_hkdf('sha256', $ikm, 12, $nonceInfo, $salt);

    // Padding com delimitador de registro (\x02 para último bloco)
    $paddedPayload = $payloadText . "\x02";

    // Criptografia AES-128-GCM
    $tag = '';
    $ciphertext = openssl_encrypt($paddedPayload, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

    // Cabeçalho RFC 8188: salt (16) || rs (4) || idlen (1) || keyid (65)
    $recordSize = 4096;
    $rsHeader = pack('N', $recordSize);
    $keyIdLen = chr(65);
    $header = $salt . $rsHeader . $keyIdLen . $localPub;

    return $header . $ciphertext . $tag;
}

/**
 * Cria uma notificação no banco com verificação estrita contra duplicidade
 */
function createNotification($pdo, $userId, $title, $message, $link = null, $scheduleId = null, $type = 'meeting_reminder')
{
    if (!empty($scheduleId)) {
        $stmt = $pdo->prepare("SELECT id FROM " . TABLE_NAME . "notifications WHERE user_id = ? AND schedule_id = ? AND type = ? LIMIT 1");
        $stmt->execute([$userId, $scheduleId, $type]);
        $existing = $stmt->fetch();
        if ($existing) {
            return false;
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO " . TABLE_NAME . "notifications (user_id, schedule_id, title, message, type, link, is_read, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$userId, $scheduleId, $title, $message, $type, $link]);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Dispara Web Push para todos os dispositivos inscritos do usuário
 */
function sendWebPushToUser($pdo, $userId, array $payload)
{
    $stmt = $pdo->prepare("SELECT id, endpoint, p256dh, auth FROM " . TABLE_NAME . "push_subscriptions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($subs)) {
        return 0;
    }

    $vapid = getVapidKeys();
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $sentCount = 0;
    $staleIds = [];

    foreach ($subs as $sub) {
        $endpoint = $sub['endpoint'];
        $result = sendWebPushPayload($endpoint, $payloadJson, $vapid, $sub['p256dh'], $sub['auth']);

        if ($result['success']) {
            $sentCount++;
        } elseif ($result['status'] === 404 || $result['status'] === 410) {
            // Inscrição expirada ou cancelada pelo navegador
            $staleIds[] = $sub['id'];
        }
    }

    if (!empty($staleIds)) {
        $inQuery = implode(',', array_map('intval', $staleIds));
        $pdo->exec("DELETE FROM " . TABLE_NAME . "push_subscriptions WHERE id IN ($inQuery)");
    }

    return $sentCount;
}

/**
 * Envia notificação Web Push criptografada e autenticada via VAPID RFC 8291 / RFC 8292
 */
function sendWebPushPayload($endpoint, $payload, $vapid, $userPublicKey = null, $userAuth = null)
{
    if (empty($endpoint)) {
        return ['success' => false, 'status' => 0, 'error' => 'Endpoint vazio'];
    }

    // Extrai a audiência do endpoint (ex: https://fcm.googleapis.com)
    $parsedUrl = parse_url($endpoint);
    $audience = ($parsedUrl['scheme'] ?? 'https') . '://' . ($parsedUrl['host'] ?? '');
    if (!empty($parsedUrl['port']) && !in_array($parsedUrl['port'], [80, 443])) {
        $audience .= ':' . $parsedUrl['port'];
    }

    try {
        $jwt = createVapidJwt($audience, $vapid['subject'], $vapid['publicKey'], $vapid['privateKey']);
    } catch (Exception $e) {
        return ['success' => false, 'status' => 0, 'error' => 'VAPID JWT Error: ' . $e->getMessage()];
    }

    $headers = [
        'TTL: 86400',
        'Urgency: high',
        'Authorization: vapid t=' . $jwt . ', k=' . $vapid['publicKey']
    ];

    $postBody = '';

    if (!empty($payload) && !empty($userPublicKey) && !empty($userAuth)) {
        try {
            $postBody = encryptWebPushPayload($payload, $userPublicKey, $userAuth);
            $headers[] = 'Content-Type: application/octet-stream';
            $headers[] = 'Content-Encoding: aes128gcm';
        } catch (Exception $e) {
            // Fallback para envio simples se a criptografia local falhar
            $postBody = $payload;
            $headers[] = 'Content-Type: application/json; charset=utf-8';
        }
    } else {
        $headers[] = 'Content-Length: 0';
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Códigos 200, 201 (Created), 202 (Accepted) são considerados entrega com sucesso
    $isSuccess = ($httpCode >= 200 && $httpCode < 300);

    return [
        'success' => $isSuccess,
        'status' => $httpCode,
        'response' => $response,
        'error' => $curlError
    ];
}

/**
 * Cria a notificação interna e faz o disparo Web Push para o usuário
 */
function notifyUser($pdo, $userId, $title, $message, $link = null, $scheduleId = null, $type = 'meeting_reminder')
{
    if ($type !== 'test') {
        try {
            $stmtUser = $pdo->prepare("SELECT notifications_enabled FROM " . TABLE_NAME . "users WHERE id = ?");
            $stmtUser->execute([$userId]);
            $pref = $stmtUser->fetchColumn();
            if ($pref !== false && $pref !== null && (int)$pref === 0) {
                return false;
            }
        } catch (Exception $e) {}
    }

    $notifId = createNotification($pdo, $userId, $title, $message, $link, $scheduleId, $type);
    
    if (!$notifId) {
        return false;
    }

    $pushPayload = [
        'id' => $notifId,
        'title' => $title,
        'body' => $message,
        'icon' => '/assets/images/logo.png',
        'badge' => '/assets/images/logo.png',
        'data' => [
            'url' => $link ?: 'admin/schedule.php',
            'id' => $notifId,
            'schedule_id' => $scheduleId
        ]
    ];

    $pushCount = sendWebPushToUser($pdo, $userId, $pushPayload);

    return [
        'notification_id' => $notifId,
        'push_sent' => $pushCount
    ];
}
