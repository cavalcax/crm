<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();
$user_id = $_SESSION['user_id'];
$pageTitle = 'Meu Perfil';

$success = '';
$error = '';

// Fetch current user data
$stmt = $pdo->prepare("SELECT * FROM " . TABLE_NAME . "users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    echo "Usuário não encontrado.";
    exit;
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email)) {
        $error = "Nome e e-mail são obrigatórios.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Por favor, insira um e-mail válido.";
    } else {
        // Check if email is already taken by another user
        $checkStmt = $pdo->prepare("SELECT id FROM " . TABLE_NAME . "users WHERE email = ? AND id != ?");
        $checkStmt->execute([$email, $user_id]);
        if ($checkStmt->fetch()) {
            $error = "Este e-mail já está sendo utilizado por outro usuário.";
        } else {
            // Check password update if filled
            $updatePassword = false;
            if (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                    $error = "A nova senha deve ter no mínimo 6 caracteres.";
                } elseif ($new_password !== $confirm_password) {
                    $error = "A confirmação de senha não confere.";
                } else {
                    $updatePassword = true;
                }
            }

            if (empty($error)) {
                if ($updatePassword) {
                    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE " . TABLE_NAME . "users SET name = ?, email = ?, password = ? WHERE id = ?");
                    $updateStmt->execute([$name, $email, $hashedPassword, $user_id]);
                } else {
                    $updateStmt = $pdo->prepare("UPDATE " . TABLE_NAME . "users SET name = ?, email = ? WHERE id = ?");
                    $updateStmt->execute([$name, $email, $user_id]);
                }

                // Update session name
                $_SESSION['user_name'] = $name;

                // Refresh user object
                $stmt = $pdo->prepare("SELECT * FROM " . TABLE_NAME . "users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                $success = "Perfil atualizado com sucesso!";
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
    <title>Meu Perfil - CRM Vitor Müller</title>
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
                <div class="max-w-4xl mx-auto">
                    <!-- Top Breadcrumb -->
                    <div class="mb-6 flex items-center justify-between">
                        <a href="index.php" class="text-brand-600 hover:text-brand-800 flex items-center font-semibold text-sm">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Voltar ao Dashboard
                        </a>
                    </div>

                    <!-- Alerts -->
                    <?php if (!empty($success)): ?>
                        <div class="bg-green-50 border-l-4 border-green-500 text-green-800 p-4 rounded-lg shadow-sm mb-6 flex items-center">
                            <svg class="w-6 h-6 text-green-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="font-medium"><?php echo htmlspecialchars($success); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="bg-red-50 border-l-4 border-red-500 text-red-800 p-4 rounded-lg shadow-sm mb-6 flex items-center">
                            <svg class="w-6 h-6 text-red-500 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="font-medium"><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Profile Card -->
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                        <!-- Header Banner -->
                        <div class="bg-gradient-to-r from-brand-800 to-brand-600 px-8 py-8 text-white flex flex-col sm:flex-row items-center sm:items-start gap-6">
                            <img class="h-24 w-24 rounded-full object-cover border-4 border-white shadow-lg flex-shrink-0"
                                src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=B8860B&color=fff&size=128&bold=true"
                                alt="Avatar">
                            <div class="text-center sm:text-left">
                                <h2 class="text-2xl font-bold text-white"><?php echo htmlspecialchars($user['name']); ?></h2>
                                <p class="text-brand-200 text-sm mt-1"><?php echo htmlspecialchars($user['email']); ?></p>
                                <span class="inline-block bg-brand-700 bg-opacity-70 text-brand-100 text-xs px-3 py-1 rounded-full font-medium mt-3 border border-brand-500">
                                    Membro desde: <?php echo date('d/m/Y', strtotime($user['created_at'] ?? 'now')); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Edit Form -->
                        <form method="POST" class="p-8 space-y-6">
                            <input type="hidden" name="update_profile" value="1">

                            <div class="border-b border-gray-100 pb-6">
                                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                    <span class="p-2 bg-brand-100 rounded-lg text-brand-700 mr-2.5">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                    </span>
                                    Dados Pessoais
                                </h3>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Nome Completo *</label>
                                        <input type="text" id="name" name="name" required
                                            value="<?php echo htmlspecialchars($user['name']); ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-gray-800 text-sm transition">
                                    </div>

                                    <div>
                                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">E-mail *</label>
                                        <input type="email" id="email" name="email" required
                                            value="<?php echo htmlspecialchars($user['email']); ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-gray-800 text-sm transition">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-lg font-bold text-gray-900 mb-2 flex items-center">
                                    <span class="p-2 bg-brand-100 rounded-lg text-brand-700 mr-2.5">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                        </svg>
                                    </span>
                                    Alterar Senha
                                </h3>
                                <p class="text-xs text-gray-500 mb-4 ml-10">Deixe os campos em branco se não desejar alterar a senha atual.</p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="new_password" class="block text-sm font-semibold text-gray-700 mb-2">Nova Senha</label>
                                        <input type="password" id="new_password" name="new_password" placeholder="Mínimo 6 caracteres"
                                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-gray-800 text-sm transition">
                                    </div>

                                    <div>
                                        <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">Confirmar Nova Senha</label>
                                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Repita a nova senha"
                                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-gray-800 text-sm transition">
                                    </div>
                                </div>
                            </div>

                            <div class="pt-6 border-t border-gray-100 flex items-center justify-end gap-3">
                                <a href="index.php" class="px-5 py-2.5 rounded-lg text-gray-700 bg-gray-100 hover:bg-gray-200 font-semibold text-sm transition">
                                    Cancelar
                                </a>
                                <button type="submit"
                                    class="px-6 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-bold text-sm shadow transition hover:shadow-md transform hover:-translate-y-0.5 active:translate-y-0 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Salvar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>

</html>
