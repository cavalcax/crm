<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireAdmin();
$pageTitle = 'Usuários';

$currentUserId = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle Actions: Toggle Status & Quick Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Toggle Active/Inactive Status
    if (isset($_POST['toggle_status'])) {
        $targetUserId = intval($_POST['user_id'] ?? 0);
        if ($targetUserId === $currentUserId) {
            $error = "Você não pode desativar seu próprio usuário.";
        } else {
            $stmt = $pdo->prepare("SELECT is_active, name FROM " . TABLE_NAME . "users WHERE id = ?");
            $stmt->execute([$targetUserId]);
            $targetUser = $stmt->fetch();

            if ($targetUser) {
                $newStatus = (isset($targetUser['is_active']) && (int)$targetUser['is_active'] === 1) ? 0 : 1;
                $updateStmt = $pdo->prepare("UPDATE " . TABLE_NAME . "users SET is_active = ? WHERE id = ?");
                $updateStmt->execute([$newStatus, $targetUserId]);
                $message = "Status do usuário '{$targetUser['name']}' alterado para " . ($newStatus ? "Ativo" : "Inativo") . ".";
            }
        }
    }

    // 2. Quick Password Reset
    if (isset($_POST['reset_password'])) {
        $targetUserId = intval($_POST['user_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, name, email, whatsapp FROM " . TABLE_NAME . "users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        $targetUser = $stmt->fetch();

        if ($targetUser) {
            // Generate random 8-character secure password
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$';
            $newPassword = '';
            for ($i = 0; $i < 8; $i++) {
                $newPassword .= $chars[rand(0, strlen($chars) - 1)];
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE " . TABLE_NAME . "users SET password = ? WHERE id = ?");
            $updateStmt->execute([$hashedPassword, $targetUserId]);

            // Prepare WhatsApp link
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scriptDir = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/\\');
            $loginUrl = $protocol . '://' . $host . $scriptDir . '/login.php';

            $waMsg = buildPasswordResetWhatsAppMessage($targetUser['name'], $targetUser['email'], $newPassword, $loginUrl);
            $cleanPhone = preg_replace('/[^0-9]/', '', $targetUser['whatsapp'] ?? '');
            $waUrl = !empty($cleanPhone) ? "https://wa.me/+55{$cleanPhone}?text=" . rawurlencode($waMsg) : '';

            $_SESSION['user_reset_name'] = $targetUser['name'];
            $_SESSION['user_reset_pwd'] = $newPassword;
            $_SESSION['user_reset_wa_url'] = $waUrl;

            header("Location: users.php?pwd_reset=1");
            exit;
        }
    }
}

// Fetch all users with client count
$sql = "
    SELECT u.*, COUNT(c.id) as client_count 
    FROM " . TABLE_NAME . "users u 
    LEFT JOIN " . TABLE_NAME . "clients c ON u.id = c.user_id 
    GROUP BY u.id 
    ORDER BY u.is_active DESC, u.name ASC
";
$stmt = $pdo->query($sql);
$users = $stmt->fetchAll();

// Check for session flash WhatsApp modals
$createdWaUrl = $_SESSION['user_created_wa_url'] ?? '';
$createdUserName = $_SESSION['user_created_name'] ?? '';
unset($_SESSION['user_created_wa_url'], $_SESSION['user_created_name']);

$resetWaUrl = $_SESSION['user_reset_wa_url'] ?? '';
$resetUserName = $_SESSION['user_reset_name'] ?? '';
$resetUserPwd = $_SESSION['user_reset_pwd'] ?? '';
unset($_SESSION['user_reset_wa_url'], $_SESSION['user_reset_name'], $_SESSION['user_reset_pwd']);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - CRM Vitor Müller</title>
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
    <style>
        #usersTable tbody {
            visibility: hidden;
        }
        #usersTable.ready tbody {
            visibility: visible;
        }
    </style>
</head>

<body class="bg-brand-50 font-sans leading-normal tracking-normal">
    <!-- Modal de Aguarde / Loading Overlay -->
    <div id="usersLoadingOverlay" class="fixed inset-0 bg-slate-900/30 backdrop-blur-xs z-50 flex items-center justify-center transition-opacity duration-200">
        <div class="bg-white px-6 py-5 rounded-2xl shadow-2xl flex items-center space-x-4 border border-gray-100 max-w-xs sm:max-w-sm">
            <div class="w-8 h-8 border-4 border-brand-200 border-t-brand-600 rounded-full animate-spin flex-shrink-0"></div>
            <div>
                <p class="font-bold text-gray-800 text-sm">Carregando dados...</p>
                <p class="text-xs text-gray-500">Filtrando e organizando a lista</p>
            </div>
        </div>
    </div>

    <div class="relative min-h-screen md:flex">
        <?php include '../components/sidebar.php'; ?>
        <div class="flex-1 flex flex-col min-h-screen overflow-hidden">
            <?php include '../components/header.php'; ?>
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-brand-50 p-6">

                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-3xl font-bold text-brand-900">Usuários</h1>
                    <a href="user-add.php"
                        class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-2.5 px-6 rounded-lg shadow-md transition transform hover:-translate-y-0.5 active:translate-y-0 flex items-center cursor-pointer text-sm">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Novo
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 mb-6 rounded-r-lg shadow-sm">
                        <div class="flex items-center">
                            <svg class="h-5 w-5 text-emerald-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <p class="text-sm font-semibold text-emerald-800"><?php echo $message; ?></p>
                        </div>
                    </div>
                <?php endif; ?>

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

                <!-- Search & Filters (Exact clients.php layout pattern) -->
                <div class="mb-6 flex flex-wrap sm:flex-nowrap gap-2 sm:gap-4 items-center">
                    <div class="relative flex-1 min-w-[200px]">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 sm:pl-3 pointer-events-none">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </span>
                        <input type="text" id="userSearchInput"
                            class="w-full pl-8 sm:pl-10 pr-2.5 sm:pr-4 py-2 sm:py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm bg-white text-xs sm:text-sm"
                            placeholder="Buscar por nome, e-mail, telefone...">
                    </div>

                    <div class="w-full sm:w-44">
                        <div class="relative">
                            <select id="userRoleSelect"
                                class="w-full pl-2 sm:pl-3 pr-6 sm:pr-8 py-2 sm:py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm bg-white font-medium text-gray-700 text-xs sm:text-sm appearance-none cursor-pointer truncate">
                                <option value="all">Todos os Perfis</option>
                                <option value="operator">👤 Operadores</option>
                                <option value="admin">⭐ Administradores</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-1.5 sm:px-2.5 text-gray-500">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="w-full sm:w-40">
                        <div class="relative">
                            <select id="userStatusSelect"
                                class="w-full pl-2 sm:pl-3 pr-6 sm:pr-8 py-2 sm:py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm bg-white font-medium text-gray-700 text-xs sm:text-sm appearance-none cursor-pointer truncate">
                                <option value="all">Todos os Status</option>
                                <option value="1">🟢 Ativos</option>
                                <option value="0">⚫ Inativos</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-1.5 sm:px-2.5 text-gray-500">
                                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users List Table Container -->
                <div class="bg-white shadow-md rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full leading-normal" id="usersTable">
                            <thead>
                                <tr>
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-200 transition select-none sort-header"
                                        data-sort="name" title="Clique para ordenar por Nome">
                                        <div class="flex items-center space-x-1">
                                            <span>Usuário</span>
                                            <span class="sort-icon text-gray-400 text-xs">↕</span>
                                        </div>
                                    </th>
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-200 transition select-none sort-header"
                                        data-sort="whatsapp" title="Clique para ordenar por Contato">
                                        <div class="flex items-center space-x-1">
                                            <span>Contato</span>
                                            <span class="sort-icon text-gray-400 text-xs">↕</span>
                                        </div>
                                    </th>
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-200 transition select-none sort-header"
                                        data-sort="role" title="Clique para ordenar por Perfil">
                                        <div class="flex items-center space-x-1">
                                            <span>Perfil</span>
                                            <span class="sort-icon text-gray-400 text-xs">↕</span>
                                        </div>
                                    </th>
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-200 transition select-none sort-header"
                                        data-sort="clients" title="Clique para ordenar por Quantidade de Clientes">
                                        <div class="flex items-center justify-center space-x-1">
                                            <span>Clientes</span>
                                            <span class="sort-icon text-gray-400 text-xs">↕</span>
                                        </div>
                                    </th>
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider cursor-pointer hover:bg-gray-200 transition select-none sort-header"
                                        data-sort="active" title="Clique para ordenar por Status">
                                        <div class="flex items-center justify-center space-x-1">
                                            <span>Status</span>
                                            <span class="sort-icon text-gray-400 text-xs">↕</span>
                                        </div>
                                    </th>
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Ações
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $u): 
                                        $isActive = !isset($u['is_active']) || (int)$u['is_active'] === 1;
                                        $cleanPhone = preg_replace('/[^0-9]/', '', $u['whatsapp'] ?? '');
                                    ?>
                                        <tr class="hover:bg-gray-50 transition user-row"
                                            data-name="<?php echo strtolower(htmlspecialchars($u['name'])); ?>"
                                            data-email="<?php echo strtolower(htmlspecialchars($u['email'])); ?>"
                                            data-whatsapp="<?php echo htmlspecialchars($cleanPhone); ?>"
                                            data-role="<?php echo htmlspecialchars($u['role']); ?>"
                                            data-clients="<?php echo (int)($u['client_count'] ?? 0); ?>"
                                            data-active="<?php echo $isActive ? '1' : '0'; ?>">
                                            
                                            <!-- Usuário -->
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 w-10 h-10 mr-3">
                                                        <img class="w-full h-full rounded-full object-cover border border-gray-200 shadow-xs"
                                                            src="https://ui-avatars.com/api/?name=<?php echo urlencode($u['name']); ?>&background=<?php echo $u['role'] === 'admin' ? 'B8860B' : '4A5568'; ?>&color=fff&size=64&bold=true"
                                                            alt="Avatar">
                                                    </div>
                                                    <div>
                                                        <div class="font-bold text-gray-900 flex items-center gap-1.5">
                                                            <span><?php echo htmlspecialchars($u['name']); ?></span>
                                                            <?php if ($u['id'] === $currentUserId): ?>
                                                                <span class="text-[10px] bg-brand-100 text-brand-800 font-bold px-1.5 py-0.2 rounded border border-brand-200">Você</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p class="text-xs text-gray-500 mt-0.5"><?php echo htmlspecialchars($u['email']); ?></p>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Contato -->
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <?php if (!empty($u['whatsapp'])): ?>
                                                    <a href="https://wa.me/+55<?php echo $cleanPhone; ?>" target="_blank"
                                                        class="inline-flex items-center gap-1 text-green-600 hover:text-green-800 font-semibold text-xs hover:underline"
                                                        title="Abrir WhatsApp com este usuário">
                                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                                        <span><?php echo htmlspecialchars(formatPhone($u['whatsapp'])); ?></span>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-xs text-gray-400 italic">Não informado</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Perfil -->
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <?php if ($u['role'] === 'admin'): ?>
                                                    <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 border border-amber-300">
                                                        ⭐ Administrador
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-700 border border-slate-300">
                                                        👤 Operador
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Clientes -->
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center">
                                                <span class="inline-block px-2.5 py-0.5 text-xs font-bold rounded-md bg-brand-50 text-brand-900 border border-brand-200">
                                                    <?php echo (int)($u['client_count'] ?? 0); ?>
                                                </span>
                                            </td>

                                            <!-- Status -->
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center">
                                                <?php if ($isActive): ?>
                                                    <span class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-800 text-xs px-2.5 py-0.5 rounded-full font-bold border border-emerald-300">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Ativo
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-800 text-xs px-2.5 py-0.5 rounded-full font-bold border border-gray-300">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-500"></span> Inativo
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Ações -->
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-right">
                                                <div class="flex items-center justify-end space-x-2">
                                                    <!-- Reset Password -->
                                                    <form method="POST" onsubmit="confirmResetPassword(event, '<?php echo addslashes($u['name']); ?>')" class="inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                        <input type="hidden" name="reset_password" value="1">
                                                        <button type="submit"
                                                            class="text-amber-600 hover:text-amber-800 p-1 hover:bg-amber-50 rounded transition cursor-pointer"
                                                            title="Resetar Senha e Enviar no WhatsApp">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                                            </svg>
                                                        </button>
                                                    </form>

                                                    <!-- Toggle Active/Inactive -->
                                                    <?php if ($u['id'] !== $currentUserId): ?>
                                                        <form method="POST" onsubmit="confirmToggleStatus(event, '<?php echo addslashes($u['name']); ?>', <?php echo $isActive ? 'true' : 'false'; ?>)" class="inline">
                                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                            <input type="hidden" name="toggle_status" value="1">
                                                            <button type="submit"
                                                                class="p-1 <?php echo $isActive ? 'text-red-500 hover:text-red-700 hover:bg-red-50' : 'text-emerald-600 hover:text-emerald-800 hover:bg-emerald-50'; ?> rounded transition cursor-pointer"
                                                                title="<?php echo $isActive ? 'Desativar Usuário' : 'Ativar Usuário'; ?>">
                                                                <?php if ($isActive): ?>
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                                                    </svg>
                                                                <?php else: ?>
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                                    </svg>
                                                                <?php endif; ?>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <!-- Edit User -->
                                                    <a href="user-edit.php?id=<?php echo $u['id']; ?>"
                                                        class="text-brand-600 hover:text-brand-900 p-1 hover:bg-brand-50 rounded transition"
                                                        title="Editar Usuário">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                        </svg>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <!-- Empty State / No Results -->
                        <div id="noUsersMatchRow" class="hidden px-5 py-8 bg-white text-sm text-center text-gray-500">
                            <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                </path>
                            </svg>
                            <p class="font-medium text-gray-600">Nenhum usuário encontrado para os critérios de busca.</p>
                            <p class="text-xs text-gray-400 mt-1">Tente ajustar o termo de pesquisa ou o filtro de perfil e status.</p>
                        </div>

                        <!-- Pagination Footer (Exact clients.php pattern) -->
                        <div id="usersPaginationContainer"
                            class="px-5 py-4 bg-white border-t border-gray-200 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <!-- Info & Per-Page Selector -->
                            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                                <span id="usersPaginationInfo">
                                    Mostrando <strong class="text-gray-900 font-semibold" id="usersPageStart">1</strong> a
                                    <strong class="text-gray-900 font-semibold" id="usersPageEnd">10</strong> de <strong
                                        class="text-gray-900 font-semibold" id="usersTotalItems">0</strong> usuários
                                </span>
                                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                                    <label for="usersPerPageSelect" class="whitespace-nowrap font-medium">Por página:</label>
                                    <select id="usersPerPageSelect"
                                        class="border border-gray-300 rounded-md px-2.5 py-1 bg-white text-gray-700 text-xs focus:ring-2 focus:ring-brand-500 focus:outline-none shadow-sm cursor-pointer">
                                        <option value="10" selected>10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                        <option value="all">Todos</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Page Navigation Buttons -->
                            <div class="flex items-center space-x-1" id="usersPaginationButtons">
                                <!-- Rendered dynamically via JS -->
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- SweetAlert Confirmation & WhatsApp Modals -->
    <script>
        // Real-Time Dynamic Filtering, Sorting & Pagination
        let currentPage = 1;
        let perPage = 10;
        let currentSort = 'active';
        let currentOrder = 'DESC';

        function updateSortIcons() {
            document.querySelectorAll('.sort-header').forEach(header => {
                const col = header.getAttribute('data-sort');
                const icon = header.querySelector('.sort-icon');
                if (!icon) return;

                if (col === currentSort) {
                    icon.textContent = currentOrder === 'ASC' ? '▲' : '▼';
                    icon.className = 'sort-icon text-brand-600 text-xs font-bold';
                } else {
                    icon.textContent = '↕';
                    icon.className = 'sort-icon text-gray-400 text-xs';
                }
            });
        }

        function sortUsersTable(col, order) {
            const tableBody = document.getElementById('usersTableBody');
            if (!tableBody) return;

            const rows = Array.from(tableBody.querySelectorAll('.user-row'));

            rows.sort((a, b) => {
                let valA = a.getAttribute('data-' + col) || '';
                let valB = b.getAttribute('data-' + col) || '';

                if (col === 'clients' || col === 'active') {
                    valA = parseInt(valA, 10) || 0;
                    valB = parseInt(valB, 10) || 0;
                    return order === 'ASC' ? valA - valB : valB - valA;
                } else {
                    return order === 'ASC'
                        ? valA.localeCompare(valB, 'pt-BR', { sensitivity: 'base' })
                        : valB.localeCompare(valA, 'pt-BR', { sensitivity: 'base' });
                }
            });

            // Re-append sorted rows to DOM
            rows.forEach(row => tableBody.appendChild(row));

            currentSort = col;
            currentOrder = order;
            updateSortIcons();

            currentPage = 1;
            renderUserPagination();
        }

        document.querySelectorAll('.sort-header').forEach(header => {
            header.addEventListener('click', function () {
                const col = this.getAttribute('data-sort');
                let newOrder = 'ASC';
                if (col === currentSort) {
                    newOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    newOrder = (col === 'clients' || col === 'active') ? 'DESC' : 'ASC';
                }
                sortUsersTable(col, newOrder);
            });
        });

        function getFilteredUserRows() {
            const searchVal = (document.getElementById('userSearchInput').value || '').toLowerCase().trim();
            const roleVal = document.getElementById('userRoleSelect').value;
            const statusVal = document.getElementById('userStatusSelect').value;

            const allRows = Array.from(document.querySelectorAll('.user-row'));
            return allRows.filter(row => {
                const name = row.getAttribute('data-name') || '';
                const email = row.getAttribute('data-email') || '';
                const whatsapp = row.getAttribute('data-whatsapp') || '';
                const role = row.getAttribute('data-role') || '';
                const active = row.getAttribute('data-active') || '';

                const matchSearch = !searchVal || name.includes(searchVal) || email.includes(searchVal) || whatsapp.includes(searchVal);
                const matchRole = roleVal === 'all' || role === roleVal;
                const matchStatus = statusVal === 'all' || active === statusVal;

                return matchSearch && matchRole && matchStatus;
            });
        }

        function createPageBtn(pageNumber) {
            const btn = document.createElement('button');
            btn.type = 'button';
            const isActive = (pageNumber === currentPage);
            btn.className = `px-3 py-1.5 text-xs font-bold rounded-md border ${isActive ? 'bg-brand-600 text-white border-brand-600 shadow-xs' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100 shadow-xs'} transition cursor-pointer`;
            btn.textContent = pageNumber;
            if (!isActive) {
                btn.onclick = () => goToPage(pageNumber);
            }
            return btn;
        }

        function goToPage(p) {
            currentPage = p;
            renderUserPagination();
            const table = document.getElementById('usersTable');
            if (table) {
                table.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        function renderPaginationButtons(totalPages) {
            const btnContainer = document.getElementById('usersPaginationButtons');
            if (!btnContainer) return;
            btnContainer.innerHTML = '';

            if (totalPages <= 1 && perPage === 'all') return;

            // Previous Button
            const prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = `px-3 py-1.5 text-xs font-semibold rounded-md border ${currentPage === 1 ? 'border-gray-200 text-gray-400 cursor-not-allowed bg-gray-50' : 'border-gray-300 text-gray-700 bg-white hover:bg-gray-100 cursor-pointer shadow-xs'} transition flex items-center`;
            prevBtn.innerHTML = `
                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Anterior
            `;
            prevBtn.disabled = (currentPage === 1);
            if (currentPage > 1) {
                prevBtn.onclick = () => goToPage(currentPage - 1);
            }
            btnContainer.appendChild(prevBtn);

            // Numbered Buttons
            const maxButtons = 5;
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, startPage + maxButtons - 1);

            if (endPage - startPage < maxButtons - 1) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }

            if (startPage > 1) {
                btnContainer.appendChild(createPageBtn(1));
                if (startPage > 2) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'px-1.5 py-1 text-xs text-gray-400';
                    ellipsis.textContent = '...';
                    btnContainer.appendChild(ellipsis);
                }
            }

            for (let p = startPage; p <= endPage; p++) {
                btnContainer.appendChild(createPageBtn(p));
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    const ellipsis = document.createElement('span');
                    ellipsis.className = 'px-1.5 py-1 text-xs text-gray-400';
                    ellipsis.textContent = '...';
                    btnContainer.appendChild(ellipsis);
                }
                btnContainer.appendChild(createPageBtn(totalPages));
            }

            // Next Button
            const nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = `px-3 py-1.5 text-xs font-semibold rounded-md border ${currentPage === totalPages ? 'border-gray-200 text-gray-400 cursor-not-allowed bg-gray-50' : 'border-gray-300 text-gray-700 bg-white hover:bg-gray-100 cursor-pointer shadow-xs'} transition flex items-center`;
            nextBtn.innerHTML = `
                Próximo
                <svg class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            `;
            nextBtn.disabled = (currentPage === totalPages);
            if (currentPage < totalPages) {
                nextBtn.onclick = () => goToPage(currentPage + 1);
            }
            btnContainer.appendChild(nextBtn);
        }

        function renderUserPagination() {
            const allRows = Array.from(document.querySelectorAll('.user-row'));
            const filteredRows = getFilteredUserRows();
            const totalItems = filteredRows.length;
            const noMatchRow = document.getElementById('noUsersMatchRow');
            const paginationContainer = document.getElementById('usersPaginationContainer');

            // Hide all rows first
            allRows.forEach(row => { row.style.display = 'none'; });

            if (totalItems === 0) {
                if (noMatchRow) noMatchRow.classList.remove('hidden');
                if (paginationContainer) paginationContainer.classList.add('hidden');
                return;
            }

            if (noMatchRow) noMatchRow.classList.add('hidden');
            if (paginationContainer) paginationContainer.classList.remove('hidden');

            const numericPerPage = (perPage === 'all') ? totalItems : parseInt(perPage, 10);
            const totalPages = Math.ceil(totalItems / numericPerPage) || 1;

            if (currentPage > totalPages) {
                currentPage = totalPages;
            }

            const startIndex = (currentPage - 1) * numericPerPage;
            const endIndex = (perPage === 'all') ? totalItems : Math.min(startIndex + numericPerPage, totalItems);

            for (let i = startIndex; i < endIndex; i++) {
                if (filteredRows[i]) {
                    filteredRows[i].style.display = '';
                }
            }

            // Update info labels
            document.getElementById('usersPageStart').textContent = (totalItems > 0 ? startIndex + 1 : 0);
            document.getElementById('usersPageEnd').textContent = endIndex;
            document.getElementById('usersTotalItems').textContent = totalItems;

            renderPaginationButtons(totalPages);
        }

        document.getElementById('userSearchInput')?.addEventListener('input', () => {
            currentPage = 1;
            renderUserPagination();
        });
        document.getElementById('userRoleSelect')?.addEventListener('change', () => {
            currentPage = 1;
            renderUserPagination();
        });
        document.getElementById('userStatusSelect')?.addEventListener('change', () => {
            currentPage = 1;
            renderUserPagination();
        });
        document.getElementById('usersPerPageSelect')?.addEventListener('change', function () {
            perPage = this.value;
            currentPage = 1;
            renderUserPagination();
        });

        // Initialize on page load
        updateSortIcons();
        renderUserPagination();

        // Reveal fully-ready table and dismiss loading overlay
        const usersTableEl = document.getElementById('usersTable');
        if (usersTableEl) usersTableEl.classList.add('ready');
        const usersOverlayEl = document.getElementById('usersLoadingOverlay');
        if (usersOverlayEl) {
            usersOverlayEl.classList.add('opacity-0', 'pointer-events-none');
            setTimeout(() => usersOverlayEl.remove(), 250);
        }

        function confirmToggleStatus(e, userName, isActive) {
            e.preventDefault();
            const form = e.target;
            const actionText = isActive ? 'desativar' : 'ativar';

            Swal.fire({
                title: `Deseja ${actionText} o usuário?`,
                text: `Usuário: ${userName}. ${isActive ? 'Ele não conseguirá mais acessar o sistema.' : 'O acesso ao sistema será liberado novamente.'}`,
                icon: isActive ? 'warning' : 'question',
                showCancelButton: true,
                confirmButtonColor: isActive ? '#dc2626' : '#059669',
                cancelButtonColor: '#6b7280',
                confirmButtonText: `Sim, ${actionText}!`,
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }

        function confirmResetPassword(e, userName) {
            e.preventDefault();
            const form = e.target;

            Swal.fire({
                title: 'Resetar Senha?',
                text: `Uma nova senha aleatória será gerada para ${userName} e você poderá enviá-la direto pelo WhatsApp.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d97706',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sim, gerar nova senha!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }

        // Handle User Created WhatsApp Modal
        <?php if (!empty($createdWaUrl)): ?>
            Swal.fire({
                title: '🎉 Usuário Criado!',
                html: `<p class="text-sm text-gray-600 mb-4">O usuário <b><?php echo htmlspecialchars($createdUserName); ?></b> foi criado com sucesso.</p><p class="text-xs text-gray-500">Deseja abrir o WhatsApp agora para enviar o link de acesso, usuário e senha?</p>`,
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '📲 Enviar no WhatsApp',
                cancelButtonText: 'Fechar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.open('<?php echo addslashes($createdWaUrl); ?>', '_blank');
                }
            });
        <?php endif; ?>

        // Handle Password Reset WhatsApp Modal
        <?php if (!empty($resetWaUrl)): ?>
            Swal.fire({
                title: '🔑 Senha Redefinida!',
                html: `
                    <p class="text-sm text-gray-600 mb-2">A nova senha de <b><?php echo htmlspecialchars($resetUserName); ?></b> é:</p>
                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl font-mono text-base font-bold text-amber-900 mb-4 select-all">
                        <?php echo htmlspecialchars($resetUserPwd); ?>
                    </div>
                    <p class="text-xs text-gray-500">Deseja enviar esta nova senha pelo WhatsApp do usuário agora?</p>
                `,
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6b7280',
                confirmButtonText: '📲 Enviar no WhatsApp',
                cancelButtonText: 'Fechar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.open('<?php echo addslashes($resetWaUrl); ?>', '_blank');
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>
