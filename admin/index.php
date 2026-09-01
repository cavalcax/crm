<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];

// Get client counts by status
// Total Clientes
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "clients WHERE user_id = ?");
$stmt->execute([$user_id]);
$clientCount = $stmt->fetchColumn();

// Novos
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "clients WHERE user_id = ? AND (status = 'Novo' OR status = 'Pré-cadastro')");
$stmt->execute([$user_id]);
$newClientCount = $stmt->fetchColumn();

// Atendidos
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "clients WHERE user_id = ? AND status = 'Atendido'");
$stmt->execute([$user_id]);
$attendedClientCount = $stmt->fetchColumn();

// Enviado Embral
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "clients WHERE user_id = ? AND status = 'Embral'");
$stmt->execute([$user_id]);
$embralClientCount = $stmt->fetchColumn();

// Ativos
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "clients WHERE user_id = ? AND (status = 'Ativo' OR status IS NULL OR status = '')");
$stmt->execute([$user_id]);
$activeClientCount = $stmt->fetchColumn();

// Inativos
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "clients WHERE user_id = ? AND status = 'Inativo'");
$stmt->execute([$user_id]);
$inactiveClientCount = $stmt->fetchColumn();

// Eventos Futuros
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "schedule WHERE user_id = ? AND start_time >= NOW()");
$stmt->execute([$user_id]);
$eventCount = $stmt->fetchColumn();

// Intenções de Compra (Ativas)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "intentions WHERE client_id IN (SELECT id FROM " . TABLE_NAME . "clients WHERE user_id = ?) AND type = 'buy' AND (status = 'active' OR status IS NULL)");
$stmt->execute([$user_id]);
$buyCount = $stmt->fetchColumn();

// Intenções de Venda (Ativas)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM " . TABLE_NAME . "intentions WHERE client_id IN (SELECT id FROM " . TABLE_NAME . "clients WHERE user_id = ?) AND type = 'sell' AND (status = 'active' OR status IS NULL)");
$stmt->execute([$user_id]);
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

                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-brand-900">Dashboard</h1>
                </div>

                <!-- Status dos Clientes (Grid 2 colunas compacto) -->
                <div class="mb-8">
                    <h2 class="text-base font-bold uppercase tracking-wider text-brand-800 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                            </path>
                        </svg>
                        Clientes por Status
                    </h2>

                    <div class="grid grid-cols-2 gap-3 sm:gap-4">
                        <!-- Card Novos -->
                        <a href="clients.php?status=Novo"
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
                        <a href="clients.php?status=Atendido"
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
                        <a href="clients.php?status=Embral"
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
                        <a href="clients.php?status=Ativo"
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
                        <a href="clients.php?status=Inativo"
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
                        <a href="clients.php"
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