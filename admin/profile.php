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
    $whatsapp = sanitize($_POST['whatsapp'] ?? '');
    $notifications_enabled = (isset($_POST['notifications_enabled']) && $_POST['notifications_enabled'] === '1') ? 1 : 0;
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
                    $updateStmt = $pdo->prepare("UPDATE " . TABLE_NAME . "users SET name = ?, email = ?, whatsapp = ?, password = ?, notifications_enabled = ? WHERE id = ?");
                    $updateStmt->execute([$name, $email, $whatsapp, $hashedPassword, $notifications_enabled, $user_id]);
                } else {
                    $updateStmt = $pdo->prepare("UPDATE " . TABLE_NAME . "users SET name = ?, email = ?, whatsapp = ?, notifications_enabled = ? WHERE id = ?");
                    $updateStmt->execute([$name, $email, $whatsapp, $notifications_enabled, $user_id]);
                }

                // Se o usuário recusar notificações, remove os registros de push subscriptions associados a ele
                if ($notifications_enabled === 0) {
                    $delPushStmt = $pdo->prepare("DELETE FROM " . TABLE_NAME . "push_subscriptions WHERE user_id = ?");
                    $delPushStmt->execute([$user_id]);
                }

                // Update session variables
                $_SESSION['user_name'] = $name;
                $_SESSION['user_whatsapp'] = $whatsapp;

                // Refresh user object
                $stmt = $pdo->prepare("SELECT * FROM " . TABLE_NAME . "users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                $success = "Perfil atualizado com sucesso!";
            }
        }
    }
}
$isNotifEnabled = !isset($user['notifications_enabled']) || (int)$user['notifications_enabled'] === 1;
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
                                <div class="flex flex-wrap items-center gap-2 mt-3 justify-center sm:justify-start">
                                    <span class="inline-block bg-brand-700 bg-opacity-70 text-brand-100 text-xs px-3 py-1 rounded-full font-medium border border-brand-500">
                                        Membro desde: <?php echo date('d/m/Y', strtotime($user['created_at'] ?? 'now')); ?>
                                    </span>
                                    <?php if (!empty($user['whatsapp'])): ?>
                                        <span class="inline-flex items-center gap-1.5 bg-green-700/80 text-white text-xs px-3 py-1 rounded-full font-semibold border border-green-500">
                                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                            <span><?php echo htmlspecialchars(formatPhone($user['whatsapp'])); ?></span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Form -->
                        <form method="POST" class="p-8 space-y-6">
                            <input type="hidden" name="update_profile" value="1">

                            <!-- Section 1: Personal Data -->
                            <div class="border-b border-gray-100 pb-6">
                                <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                    <span class="p-2 bg-brand-100 rounded-lg text-brand-700 mr-2.5">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                    </span>
                                    Dados Pessoais
                                </h3>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
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

                                    <div>
                                        <label for="whatsapp" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center justify-between">
                                            <span>WhatsApp (Atendimento)</span>
                                            <span class="text-xs text-green-600 font-bold flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                                WhatsApp
                                            </span>
                                        </label>
                                        <input type="text" id="whatsapp" name="whatsapp" placeholder="(00) 00000-0000"
                                            value="<?php echo htmlspecialchars($user['whatsapp'] ?? ''); ?>"
                                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:ring-2 focus:ring-green-500 focus:border-green-500 text-gray-800 text-sm transition">
                                        <p class="text-[11px] text-gray-400 mt-1">Número para os clientes entrarem em contato direto.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 2: Notifications Preferences -->
                            <div class="border-b border-gray-100 pb-6">
                                <h3 class="text-lg font-bold text-gray-900 mb-2 flex items-center">
                                    <span class="p-2 bg-brand-100 rounded-lg text-brand-700 mr-2.5">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9">
                                            </path>
                                        </svg>
                                    </span>
                                    Notificações & Lembretes
                                </h3>
                                <p class="text-xs text-gray-500 mb-4 ml-10">Configure se deseja receber lembretes automáticos de reuniões agendadas e avisos do CRM.</p>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <!-- Option: Permitir Notificações -->
                                    <label class="relative flex items-start p-4 border rounded-xl cursor-pointer transition select-none <?php echo $isNotifEnabled ? 'border-brand-500 bg-brand-50/50 shadow-sm ring-2 ring-brand-500/20' : 'border-gray-200 hover:border-gray-300 bg-white'; ?>">
                                        <div class="flex items-center h-5">
                                            <input type="radio" name="notifications_enabled" value="1" <?php echo $isNotifEnabled ? 'checked' : ''; ?>
                                                class="h-4 w-4 text-brand-600 border-gray-300 focus:ring-brand-500 cursor-pointer">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <span class="font-bold text-gray-900 flex items-center gap-1.5">
                                                <span>🔔</span> Permitir Notificações
                                            </span>
                                            <span class="text-xs text-gray-500 block mt-1 leading-relaxed">
                                                Receber lembretes automáticos de reuniões na agenda, popups e avisos no sino.
                                            </span>
                                        </div>
                                    </label>

                                    <!-- Option: Recusar Notificações -->
                                    <label class="relative flex items-start p-4 border rounded-xl cursor-pointer transition select-none <?php echo !$isNotifEnabled ? 'border-red-400 bg-red-50/40 shadow-sm ring-2 ring-red-400/20' : 'border-gray-200 hover:border-gray-300 bg-white'; ?>">
                                        <div class="flex items-center h-5">
                                            <input type="radio" name="notifications_enabled" value="0" <?php echo !$isNotifEnabled ? 'checked' : ''; ?>
                                                class="h-4 w-4 text-red-600 border-gray-300 focus:ring-red-500 cursor-pointer">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <span class="font-bold text-gray-900 flex items-center gap-1.5">
                                                <span>🔕</span> Recusar Notificações
                                            </span>
                                            <span class="text-xs text-gray-500 block mt-1 leading-relaxed">
                                                Silenciar lembretes automáticos e não gerar notificações de compromissos.
                                            </span>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Section 3: Change Password -->
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

    <script>
        // Dynamic styling toggle and push subscription handling for notification radios
        const notifRadios = document.querySelectorAll('input[name="notifications_enabled"]');
        notifRadios.forEach(radio => {
            radio.addEventListener('change', async () => {
                notifRadios.forEach(r => {
                    const label = r.closest('label');
                    if (r.value === '1') {
                        if (r.checked) {
                            label.className = 'relative flex items-start p-4 border rounded-xl cursor-pointer transition select-none border-brand-500 bg-brand-50/50 shadow-sm ring-2 ring-brand-500/20';
                        } else {
                            label.className = 'relative flex items-start p-4 border rounded-xl cursor-pointer transition select-none border-gray-200 hover:border-gray-300 bg-white';
                        }
                    } else if (r.value === '0') {
                        if (r.checked) {
                            label.className = 'relative flex items-start p-4 border rounded-xl cursor-pointer transition select-none border-red-400 bg-red-50/40 shadow-sm ring-2 ring-red-400/20';
                        } else {
                            label.className = 'relative flex items-start p-4 border rounded-xl cursor-pointer transition select-none border-gray-200 hover:border-gray-300 bg-white';
                        }
                    }
                });

                if (radio.checked && radio.value === '1') {
                    // Solicita permissão e gera a inscrição push no dispositivo
                    if ('Notification' in window && 'serviceWorker' in navigator) {
                        try {
                            const perm = await Notification.requestPermission();
                            if (perm === 'granted') {
                                const swUrl = typeof getSwUrl === 'function' ? getSwUrl() : '../service-worker.js';
                                const reg = await navigator.serviceWorker.register(swUrl);
                                await navigator.serviceWorker.ready;
                                if (typeof syncPushSubscription === 'function') {
                                    await syncPushSubscription(reg, true);
                                }
                            }
                        } catch (err) {
                            console.warn('Inscrição push ao selecionar permitir:', err);
                        }
                    }
                } else if (radio.checked && radio.value === '0') {
                    // Remove a inscrição push do navegador e do banco
                    if (typeof unsubscribePushSubscription === 'function') {
                        await unsubscribePushSubscription();
                    }
                }
            });
        });

        // Form submit handler to guarantee push sync on save
        const profileForm = document.querySelector('form');
        if (profileForm) {
            profileForm.addEventListener('submit', async (e) => {
                const selected = document.querySelector('input[name="notifications_enabled"]:checked');
                if (selected && selected.value === '1') {
                    if ('Notification' in window && 'serviceWorker' in navigator) {
                        try {
                            if (Notification.permission === 'default') {
                                await Notification.requestPermission();
                            }
                            if (Notification.permission === 'granted' && typeof syncPushSubscription === 'function') {
                                const swUrl = typeof getSwUrl === 'function' ? getSwUrl() : '../service-worker.js';
                                const reg = await navigator.serviceWorker.register(swUrl);
                                await navigator.serviceWorker.ready;
                                await syncPushSubscription(reg, true);
                            }
                        } catch (err) {
                            console.warn('Sync push on form submit:', err);
                        }
                    }
                } else if (selected && selected.value === '0') {
                    if (typeof unsubscribePushSubscription === 'function') {
                        try {
                            await unsubscribePushSubscription();
                        } catch (err) {
                            console.warn('Unsubscribe push on form submit:', err);
                        }
                    }
                }
            });
        }
    </script>
</body>

</html>
