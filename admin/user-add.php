<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireAdmin();
$pageTitle = 'Novo Usuário';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $whatsapp = sanitize($_POST['whatsapp'] ?? '');
    $role = $_POST['role'] ?? 'operator';
    $password = $_POST['password'] ?? '';

    // Allowed roles
    if (!in_array($role, ['operator', 'admin'])) {
        $role = 'operator';
    }

    if (empty($name) || empty($email) || empty($password)) {
        $error = "Por favor, preencha todos os campos obrigatórios (Nome, E-mail e Senha).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Por favor, insira um e-mail válido.";
    } elseif (strlen($password) < 6) {
        $error = "A senha deve ter no mínimo 6 caracteres.";
    } else {
        // Check if email is unique
        $stmt = $pdo->prepare("SELECT id FROM " . TABLE_NAME . "users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Já existe um usuário cadastrado com este e-mail.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO " . TABLE_NAME . "users (name, email, whatsapp, role, password, is_active, notifications_enabled) 
                VALUES (?, ?, ?, ?, ?, 1, 1)
            ");
            $stmt->execute([$name, $email, $whatsapp, $role, $hashedPassword]);

            // Prepare WhatsApp link
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scriptDir = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/\\');
            $loginUrl = $protocol . '://' . $host . $scriptDir . '/login.php';

            $waMsg = buildNewUserWelcomeWhatsAppMessage($name, $email, $password, $loginUrl);
            $cleanPhone = preg_replace('/[^0-9]/', '', $whatsapp);
            $waUrl = !empty($cleanPhone) ? "https://wa.me/+55{$cleanPhone}?text=" . rawurlencode($waMsg) : '';

            // Store in session for redirect modal
            $_SESSION['user_created_name'] = $name;
            $_SESSION['user_created_wa_url'] = $waUrl;

            header("Location: users.php?created=1");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Usuário - CRM Vitor Müller</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                <!-- Breadcrumbs & Header -->
                <div class="max-w-4xl mx-auto flex items-center justify-between mb-6">
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <a href="users.php" class="hover:text-brand-600 font-medium">Usuários</a>
                        <span>/</span>
                        <span class="text-gray-800 font-bold">Novo Usuário</span>
                    </div>
                    <a href="users.php"
                        class="text-gray-600 hover:text-gray-900 text-sm font-semibold flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Voltar
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-r-lg shadow-sm">
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-red-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <p class="text-sm font-semibold text-red-700"><?php echo $error; ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- User Form Card -->
                <div class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-brand-800 to-brand-600 px-6 py-5 text-white flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-bold">Cadastrar Novo Usuário</h2>
                            <p class="text-brand-200 text-xs mt-0.5">Preencha os dados de acesso para o novo operador ou administrador.</p>
                        </div>
                        <span class="text-3xl">👤</span>
                    </div>

                    <form method="POST" class="p-6 md:p-8 space-y-6">
                        <!-- Personal Info -->
                        <div>
                            <h3 class="text-base font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100 flex items-center gap-2">
                                <span class="p-1.5 bg-brand-50 text-brand-700 rounded-lg">📋</span>
                                1. Dados Básicos
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nome Completo *</label>
                                    <input type="text" name="name" required placeholder="Ex: João da Silva"
                                        value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-gray-800 text-sm">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">E-mail (Login) *</label>
                                    <input type="email" name="email" required placeholder="joao@viformuller.com.br"
                                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-gray-800 text-sm">
                                    <p class="text-[11px] text-gray-400 mt-1">Será usado como nome de usuário para login.</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-1.5 flex items-center justify-between">
                                        <span>WhatsApp</span>
                                        <span class="text-xs text-green-600 font-bold flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                            Envio de Acesso
                                        </span>
                                    </label>
                                    <input type="text" name="whatsapp" id="whatsappInput" placeholder="(00) 00000-0000"
                                        value="<?php echo htmlspecialchars($_POST['whatsapp'] ?? ''); ?>"
                                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-green-500 text-gray-800 text-sm">
                                    <p class="text-[11px] text-gray-400 mt-1">Ao finalizar, o sistema permitirá enviar os dados de login direto no WhatsApp.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Role & Access -->
                        <div>
                            <h3 class="text-base font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100 flex items-center gap-2">
                                <span class="p-1.5 bg-brand-50 text-brand-700 rounded-lg">🛡️</span>
                                2. Perfil & Permissões
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <label class="relative flex items-start p-4 border rounded-xl cursor-pointer transition select-none hover:border-brand-300 bg-white has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/50 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/20">
                                    <div class="flex items-center h-5">
                                        <input type="radio" name="role" value="operator" checked
                                            class="h-4 w-4 text-brand-600 border-gray-300 focus:ring-brand-500 cursor-pointer">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <span class="font-bold text-gray-900 flex items-center gap-1.5">
                                            <span>👤</span> Operador
                                        </span>
                                        <span class="text-xs text-gray-500 block mt-1 leading-relaxed">
                                            Visualiza todos os clientes, mas só edita os seus. Acessa WhatsApp, mapa e agenda de seus compromissos.
                                        </span>
                                    </div>
                                </label>

                                <label class="relative flex items-start p-4 border rounded-xl cursor-pointer transition select-none hover:border-brand-300 bg-white has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/50 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/20">
                                    <div class="flex items-center h-5">
                                        <input type="radio" name="role" value="admin"
                                            class="h-4 w-4 text-brand-600 border-gray-300 focus:ring-brand-500 cursor-pointer">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <span class="font-bold text-gray-900 flex items-center gap-1.5">
                                            <span>⭐</span> Administrador
                                        </span>
                                        <span class="text-xs text-gray-500 block mt-1 leading-relaxed">
                                            Acesso total a todos os dados, clientes, interações, relatórios e gestão de usuários.
                                        </span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Password Section -->
                        <div>
                            <h3 class="text-base font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100 flex items-center gap-2">
                                <span class="p-1.5 bg-brand-50 text-brand-700 rounded-lg">🔑</span>
                                3. Senha de Acesso
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Senha Inicial *</label>
                                    <div class="relative">
                                        <input type="text" name="password" id="passwordInput" required placeholder="Digite ou gere uma senha..."
                                            value="<?php echo htmlspecialchars($_POST['password'] ?? ''); ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-gray-800 text-sm font-mono">
                                    </div>
                                    <p class="text-[11px] text-gray-400 mt-1">Mínimo de 6 caracteres. Esta senha será enviada ao usuário.</p>
                                </div>

                                <div class="pt-6">
                                    <button type="button" onclick="generateRandomPassword()"
                                        class="w-full py-2.5 px-3 bg-brand-50 hover:bg-brand-100 text-brand-800 font-bold rounded-lg border border-brand-200 text-xs flex items-center justify-center gap-2 transition cursor-pointer">
                                        <span>🎲</span> Gerar Senha Aleatória
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="pt-6 border-t border-gray-100 flex items-center justify-end gap-3">
                            <a href="users.php" class="px-5 py-2.5 rounded-lg text-gray-700 bg-gray-100 hover:bg-gray-200 font-semibold text-sm transition">
                                Cancelar
                            </a>
                            <button type="submit"
                                class="px-6 py-2.5 rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-bold text-sm shadow transition hover:shadow-md transform hover:-translate-y-0.5 flex items-center gap-2 cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Salvar e Criar Usuário
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script>
        function generateRandomPassword() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$';
            let pass = '';
            for (let i = 0; i < 8; i++) {
                pass += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('passwordInput').value = pass;
        }

        // Generate one by default if empty
        if (!document.getElementById('passwordInput').value) {
            generateRandomPassword();
        }
    </script>
</body>

</html>
