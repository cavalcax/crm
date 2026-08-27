<?php
require_once 'config/db.php';
require_once 'helpers/functions.php';

if (isLoggedIn()) {
    header("Location: admin/index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    $stmt = $pdo->prepare("SELECT * FROM " . TABLE_NAME . "users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];

        // If remember me is active (or default), generate secure persistent token (30 days)
        if ($remember) {
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);

            $updateStmt = $pdo->prepare("UPDATE " . TABLE_NAME . "users SET remember_token = ? WHERE id = ?");
            $updateStmt->execute([$tokenHash, $user['id']]);

            $cookieExpire = time() + (86400 * 30); // 30 days
            setcookie('remember_token', $user['id'] . ':' . $rawToken, [
                'expires' => $cookieExpire,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        header("Location: admin/index.php");
        exit;
    } else {
        $error = "E-mail ou senha inválidos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CRM Vitor Müller</title>
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
    <meta name="theme-color" content="#B8860B">
    <link rel="manifest" href="manifest.json">
</head>

<body class="bg-brand-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md border border-brand-200">
        <div class="text-center mb-6">
            <img src="assets/images/logo.png" alt="Vitor Müller - Pecuária de Leite"
                class="h-28 mx-auto mb-4 object-contain">
            <h1 class="text-2xl font-bold text-brand-900">Bem-vindo</h1>
            <p class="text-gray-500 text-sm mt-1">Acesse sua conta</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">
                    <?php echo $error; ?>
                </span>
            </div>
            <?php
        endif; ?>

        <form method="POST">
            <div class="mb-4">
                <label class="block text-brand-800 text-sm font-bold mb-2" for="email">E-mail</label>
                <input
                    class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                    id="email" name="email" type="email" placeholder="seu@email.com" required>
            </div>
            <div class="mb-6">
                <label class="block text-brand-800 text-sm font-bold mb-2" for="password">Senha</label>
                <input
                    class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 mb-3 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                    id="password" name="password" type="password" placeholder="******************" required>
            </div>
            <div class="flex items-center justify-between mb-6">
                <label class="flex items-center text-sm text-gray-700 cursor-pointer select-none">
                    <input type="checkbox" name="remember" value="1" checked
                        class="rounded border-gray-300 text-brand-600 shadow-sm focus:border-brand-300 focus:ring focus:ring-brand-200 focus:ring-opacity-50 mr-2 h-4 w-4">
                    <span class="font-medium text-gray-700">Permanecer conectado</span>
                </label>
            </div>
            <button
                class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline transition transform hover:-translate-y-0.5 active:translate-y-0 shadow-lg"
                type="submit">
                Entrar
            </button>
        </form>
    </div>
</body>

</html>