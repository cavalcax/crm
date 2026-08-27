<?php
require_once 'config/db.php';
require_once 'helpers/functions.php';

if (isLoggedIn()) {
    header("Location: admin/index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "As senhas não coincidem.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM " . TABLE_NAME . "users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Este e-mail já está em uso.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO " . TABLE_NAME . "users (name, email, password) VALUES (?, ?, ?)");
            if ($stmt->execute([$name, $email, $hashed_password])) {
                header("Location: login.php?registered=1");
                exit;
            } else {
                $error = "Erro ao cadastrar. Tente novamente.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - CRM Vitor Müller</title>
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

<body class="bg-brand-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md border border-brand-200">
        <div class="text-center mb-6">
            <img src="assets/images/logo.png" alt="Vitor Müller - Pecuária de Leite"
                class="h-24 mx-auto mb-4 object-contain">
            <h1 class="text-2xl font-bold text-brand-900">Criar Conta</h1>
            <p class="text-gray-500 text-sm mt-1">Cadastre-ser</p>
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
                <label class="block text-brand-800 text-sm font-bold mb-2" for="name">Nome Completo</label>
                <input
                    class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                    id="name" name="name" type="text" placeholder="Seu Nome" required>
            </div>
            <div class="mb-4">
                <label class="block text-brand-800 text-sm font-bold mb-2" for="email">E-mail</label>
                <input
                    class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                    id="email" name="email" type="email" placeholder="seu@email.com" required>
            </div>
            <div class="mb-4">
                <label class="block text-brand-800 text-sm font-bold mb-2" for="password">Senha</label>
                <input
                    class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 mb-3 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                    id="password" name="password" type="password" placeholder="******************" required>
            </div>
            <div class="mb-6">
                <label class="block text-brand-800 text-sm font-bold mb-2" for="confirm_password">Confirmar
                    Senha</label>
                <input
                    class="shadow-sm appearance-none border border-gray-300 rounded-lg w-full py-3 px-4 text-gray-700 mb-3 leading-tight focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                    id="confirm_password" name="confirm_password" type="password" placeholder="******************"
                    required>
            </div>
            <div class="flex items-center justify-between mb-6">
                <a class="inline-block align-baseline font-bold text-sm text-brand-600 hover:text-brand-800"
                    href="login.php">
                    Já tem conta? Login
                </a>
            </div>
            <button
                class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline transition transform hover:-translate-y-0.5 active:translate-y-0 shadow-lg"
                type="submit">
                Cadastrar
            </button>
        </form>
    </div>
</body>

</html>