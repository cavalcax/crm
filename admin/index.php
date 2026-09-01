<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$scope = isset($_GET['scope']) && in_array($_GET['scope'], ['mine', 'all']) ? $_GET['scope'] : 'mine';

$params = ($scope === 'mine') ? [$user_id] : [];

// Total Clientes
$totWhere = ($scope === 'mine') ? " WHERE user_id = ?" : "";
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "clients" . $totWhere);
$stmt->execute($params);
$clientCount = $stmt->fetchColumn();

// Novos
$newWhere = ($scope === 'mine') ? " WHERE user_id = ? AND (status = 'Novo' OR status = 'Pré-cadastro')" : " WHERE (status = 'Novo' OR status = 'Pré-cadastro')";
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "clients" . $newWhere);
$stmt->execute($params);
$newClientCount = $stmt->fetchColumn();

// Atendidos
$attWhere = ($scope === 'mine') ? " WHERE user_id = ? AND status = 'Atendido'" : " WHERE status = 'Atendido'";
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "clients" . $attWhere);
$stmt->execute($params);
$attendedClientCount = $stmt->fetchColumn();

// Enviado Embral
$embWhere = ($scope === 'mine') ? " WHERE user_id = ? AND status = 'Embral'" : " WHERE status = 'Embral'";
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "clients" . $embWhere);
$stmt->execute($params);
$embralClientCount = $stmt->fetchColumn();

// Ativos
$actWhere = ($scope === 'mine') ? " WHERE user_id = ? AND (status = 'Ativo' OR status IS NULL OR status = '')" : " WHERE (status = 'Ativo' OR status IS NULL OR status = '')";
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "clients" . $actWhere);
$stmt->execute($params);
$activeClientCount = $stmt->fetchColumn();

// Inativos
$inactWhere = ($scope === 'mine') ? " WHERE user_id = ? AND status = 'Inativo'" : " WHERE status = 'Inativo'";
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "clients" . $inactWhere);
$stmt->execute($params);
$inactiveClientCount = $stmt->fetchColumn();

// Eventos Futuros
$eventWhere = ($scope === 'mine') ? " WHERE user_id = ? AND start_time >= NOW()" : " WHERE start_time >= NOW()";
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "schedule" . $eventWhere);
$stmt->execute($params);
$eventCount = $stmt->fetchColumn();

// Intenções de Compra (Ativas)
$buyWhere = ($scope === 'mine') 
    ? " WHERE client_id IN (SELECT id FROM " . TABLE_NAME . "clients WHERE user_id = ?) AND type = 'buy' AND (status = 'active' OR status IS NULL)"
    : " WHERE type = 'buy' AND (status = 'active' OR status IS NULL)";
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "intentions" . $buyWhere);
$stmt->execute($params);
$buyCount = $stmt->fetchColumn();

// Intenções de Venda (Ativas)
$sellWhere = ($scope === 'mine')
    ? " WHERE client_id IN (SELECT id FROM " . TABLE_NAME . "clients WHERE user_id = ?) AND type = 'sell' AND (status = 'active' OR status IS NULL)"
    : " WHERE type = 'sell' AND (status = 'active' OR status IS NULL)";
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "intentions" . $sellWhere);
$stmt->execute($params);
$sellCount = $stmt->fetchColumn();

$pageTitle = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CRM Vitor Müller</title>
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
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <meta name="theme-color" content="#B8860B">
    <link rel="manifest" href="../manifest.json">
</head>

<body class="bg-brand-50 font-sans leading-normal tracking-normal">

    <div class="relative min-h-screen md:flex">
        <!-- Sidebar -->
        <?php include '../components/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col min-h-screen overflow-hidden">
            <!-- Header -->
            <?php include '../components/header.php'; ?>

            <!-- Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-brand-50 p-6">

                <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-brand-900">Dashboard</h1>
                        <p class="text-gray-500 text-xs sm:text-sm mt-0.5">Visão geral e indicadores em tempo real.</p>
                    </div>
                </div>

                <!-- Status dos Clientes (Grid 2 colunas compacto com seletor de escopo) -->
                <div class="mb-8">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                        <h2 class="text-base font-bold uppercase tracking-wider text-brand-800 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                </path>
                            </svg>
                            Clientes por Status
                        </h2>

                        <!-- Seletor Clientes por Status: Apenas os Meus vs Todos do Sistema -->
                        <div class="inline-flex p-1 bg-white border border-gray-200 rounded-xl shadow-xs text-xs font-semibold self-start sm:self-auto">
                            <a href="index.php?scope=mine"
                                class="px-3.5 py-1.5 rounded-lg transition flex items-center gap-1.5 <?php echo $scope === 'mine' ? 'bg-brand-600 text-white shadow-xs font-bold' : 'text-gray-600 hover:text-brand-800 hover:bg-gray-50'; ?>"
                                title="Filtrar dados apenas dos clientes vinculados a você">
                                <span>👤</span> Apenas os Meus
                            </a>
                            <a href="index.php?scope=all"
                                class="px-3.5 py-1.5 rounded-lg transition flex items-center gap-1.5 <?php echo $scope === 'all' ? 'bg-brand-600 text-white shadow-xs font-bold' : 'text-gray-600 hover:text-brand-800 hover:bg-gray-50'; ?>"
                                title="Filtrar dados de todos os clientes do CRM">
                                <span>🌐</span> Todos do Sistema
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:gap-4">
                        <!-- Card Novos -->
                        <a href="clients.php?status=Novo&scope=<?php echo $scope; ?>"
                            class="block bg-white rounded-lg shadow-sm hover:shadow p-3.5 sm:p-4 border-l-4 border-amber-500 hover:-translate-y-0.5 transition duration-150 transform cursor-pointer group">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-amber-700 mb-0.5">Novos</p>
                                    <p class="text-xl sm:text-2xl font-extrabold text-gray-800 group-hover:text-amber-600 transition">
                                        <?php echo $newClientCount; ?>
                                    </p>
                                </div>
                                <div class="p-2 sm:p-2.5 rounded-full bg-amber-100 text-amber-600 group-hover:bg-amber-500 group-hover:text-white transition flex-shrink-0 ml-2">
                                    <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                        </a>

                        <!-- Card Atendidos -->
                        <a href="clients.php?status=Atendido&scope=<?php echo $scope; ?>"
                            class="block bg-white rounded-lg shadow-sm hover:shadow p-3.5 sm:p-4 border-l-4 border-purple-500 hover:-translate-y-0.5 transition duration-150 transform cursor-pointer group">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-purple-700 mb-0.5">Atendidos</p>
                                    <p class="text-xl sm:text-2xl font-extrabold text-gray-800 group-hover:text-purple-600 transition">
                                        <?php echo $attendedClientCount; ?>
                                    </p>
                                </div>
                                <div class="p-2 sm:p-2.5 rounded-full bg-purple-100 text-purple-600 group-hover:bg-purple-500 group-hover:text-white transition flex-shrink-0 ml-2">
                                    <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                        </a>

                        <!-- Card Enviado Embral -->
                        <a href="clients.php?status=Embral&scope=<?php echo $scope; ?>"
                            class="block bg-white rounded-lg shadow-sm hover:shadow p-3.5 sm:p-4 border-l-4 border-blue-500 hover:-translate-y-0.5 transition duration-150 transform cursor-pointer group">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-blue-700 mb-0.5">Embral</p>
                                    <p class="text-xl sm:text-2xl font-extrabold text-gray-800 group-hover:text-blue-600 transition">
                                        <?php echo $embralClientCount; ?>
                                    </p>
                                </div>
                                <div class="p-2 sm:p-2.5 rounded-full bg-blue-100 text-blue-600 group-hover:bg-blue-500 group-hover:text-white transition flex-shrink-0 ml-2">
                                    <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                        </a>

                        <!-- Card Ativos -->
                        <a href="clients.php?status=Ativo&scope=<?php echo $scope; ?>"
                            class="block bg-white rounded-lg shadow-sm hover:shadow p-3.5 sm:p-4 border-l-4 border-green-500 hover:-translate-y-0.5 transition duration-150 transform cursor-pointer group">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-green-700 mb-0.5">Ativos</p>
                                    <p class="text-xl sm:text-2xl font-extrabold text-gray-800 group-hover:text-green-600 transition">
                                        <?php echo $activeClientCount; ?>
                                    </p>
                                </div>
                                <div class="p-2 sm:p-2.5 rounded-full bg-green-100 text-green-600 group-hover:bg-green-500 group-hover:text-white transition flex-shrink-0 ml-2">
                                    <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                        </a>

                        <!-- Card Inativos -->
                        <a href="clients.php?status=Inativo&scope=<?php echo $scope; ?>"
                            class="block bg-white rounded-lg shadow-sm hover:shadow p-3.5 sm:p-4 border-l-4 border-gray-400 hover:-translate-y-0.5 transition duration-150 transform cursor-pointer group">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-gray-700 mb-0.5">Inativos</p>
                                    <p class="text-xl sm:text-2xl font-extrabold text-gray-800 group-hover:text-gray-600 transition">
                                        <?php echo $inactiveClientCount; ?>
                                    </p>
                                </div>
                                <div class="p-2 sm:p-2.5 rounded-full bg-gray-100 text-gray-600 group-hover:bg-gray-500 group-hover:text-white transition flex-shrink-0 ml-2">
                                    <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                        </a>

                        <!-- Card Total Geral -->
                        <a href="clients.php?scope=<?php echo $scope; ?>"
                            class="block bg-white rounded-lg shadow-sm hover:shadow p-3.5 sm:p-4 border-l-4 border-brand-500 hover:-translate-y-0.5 transition duration-150 transform cursor-pointer group">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-brand-700 mb-0.5">Total</p>
                                    <p class="text-xl sm:text-2xl font-extrabold text-gray-800 group-hover:text-brand-600 transition">
                                        <?php echo $clientCount; ?>
                                    </p>
                                </div>
                                <div class="p-2 sm:p-2.5 rounded-full bg-brand-100 text-brand-600 group-hover:bg-brand-500 group-hover:text-white transition flex-shrink-0 ml-2">
                                    <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Atividades e Oportunidades -->
                <div>
                    <h2 class="text-base font-bold uppercase tracking-wider text-brand-800 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                            </path>
                        </svg>
                        Atividades & Oportunidades
                    </h2>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4">
                        <!-- Eventos Futuros -->
                        <a href="schedule.php"
                            class="block bg-white rounded-lg shadow-sm hover:shadow p-3.5 sm:p-4 border-l-4 border-purple-500 hover:-translate-y-0.5 transition duration-150 transform cursor-pointer group">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-purple-700 mb-0.5">Agenda</p>
                                    <p class="text-xl sm:text-2xl font-extrabold text-gray-800 group-hover:text-purple-600 transition">
                                        <?php echo $eventCount; ?>
                                    </p>
                                </div>
                                <div class="p-2 sm:p-2.5 rounded-full bg-purple-100 text-purple-600 group-hover:bg-purple-500 group-hover:text-white transition flex-shrink-0 ml-2">
                                    <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                        </a>

                        <!-- Interesse Compra -->
                        <a href="reports.php?type=buy"
                            class="block bg-white rounded-lg shadow-sm hover:shadow p-3.5 sm:p-4 border-l-4 border-cyan-500 hover:-translate-y-0.5 transition duration-150 transform cursor-pointer group">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-cyan-700 mb-0.5">Interesse Compra</p>
                                    <p class="text-xl sm:text-2xl font-extrabold text-gray-800 group-hover:text-cyan-600 transition">
                                        <?php echo $buyCount; ?>
                                    </p>
                                </div>
                                <div class="p-2 sm:p-2.5 rounded-full bg-cyan-100 text-cyan-600 group-hover:bg-cyan-500 group-hover:text-white transition flex-shrink-0 ml-2">
                                    <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 11l3-3m0 0l3 3m-3-3v8m0-13a9 9 0 110 18 9 9 0 010-18z">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                        </a>

                        <!-- Interesse Venda -->
                        <a href="reports.php?type=sell"
                            class="block bg-white rounded-lg shadow-sm hover:shadow p-3.5 sm:p-4 border-l-4 border-rose-500 hover:-translate-y-0.5 transition duration-150 transform cursor-pointer group">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-[11px] sm:text-xs font-bold uppercase tracking-wider text-rose-700 mb-0.5">Interesse Venda</p>
                                    <p class="text-xl sm:text-2xl font-extrabold text-gray-800 group-hover:text-rose-600 transition">
                                        <?php echo $sellCount; ?>
                                    </p>
                                </div>
                                <div class="p-2 sm:p-2.5 rounded-full bg-rose-100 text-rose-600 group-hover:bg-rose-500 group-hover:text-white transition flex-shrink-0 ml-2">
                                    <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 13l-3 3m0 0l-3-3m3 3V8m0 13a9 9 0 110-18 9 9 0 010 18z">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

            </main>
        </div>
    </div>
</body>

</html>