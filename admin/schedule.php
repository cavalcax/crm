<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();
$user_id = $_SESSION['user_id'];
$pageTitle = 'Agenda';

// Handle Delete Event Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_event'])) {
        $id = intval($_POST['id']);

        // Ensure event belongs to user
        $stmt = $pdo->prepare("DELETE FROM " . TABLE_NAME . "schedule WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);

        header("Location: schedule.php");
        exit;
    }
}

// Fetch Events
$stmt = $pdo->prepare("
    SELECT s.*, c.name as client_name 
    FROM " . TABLE_NAME . "schedule s 
    LEFT JOIN " . TABLE_NAME . "clients c ON s.client_id = c.id 
    WHERE s.user_id = ? 
    ORDER BY s.start_time ASC
");
$stmt->execute([$user_id]);
$events = $stmt->fetchAll();

$monthsPt = [
    '1' => 'Jan', '2' => 'Fev', '3' => 'Mar', '4' => 'Abr',
    '5' => 'Mai', '6' => 'Jun', '7' => 'Jul', '8' => 'Ago',
    '9' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'
];

$initialStartDate = date('Y-m-d');
$initialEndDate = date('Y-m-d', strtotime('+15 days'));
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda - CRM Vitor Müller</title>
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

                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-3xl font-bold text-brand-900">Agenda</h1>
                    <a href="schedule-add.php"
                        class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-2.5 px-6 rounded-lg shadow-md transition transform hover:-translate-y-0.5 active:translate-y-0 flex items-center cursor-pointer text-sm">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Novo
                    </a>
                </div>

                <!-- Search & Date Range Filter (Mobile: 100% Search on row 1, 50%/50% Dates on row 2 | Desktop: 70% Search, 15% Start Date, 15% End Date on 1 row) -->
                <div class="mb-6 flex flex-col md:flex-row gap-2 sm:gap-3 items-stretch md:items-center">
                    <!-- Search Input (100% on Mobile, 70% on Desktop) -->
                    <div class="relative w-full md:w-[70%]">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </span>
                        <input type="text" id="searchInput"
                            class="w-full pl-9 sm:pl-10 pr-3 sm:pr-4 py-2 sm:py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm text-xs sm:text-sm bg-white"
                            placeholder="Buscar por título, cliente, cidade, endereço ou tipo...">
                    </div>

                    <!-- Date Range Group (100% on Mobile with two 50% inputs, 30% on Desktop with two 15% inputs) -->
                    <div class="flex gap-2 sm:gap-3 w-full md:w-[30%]">
                        <!-- Data Inicial (50% on Mobile, 15% of total on Desktop) -->
                        <div class="w-1/2">
                            <div class="relative">
                                <input type="date" id="startDateInput"
                                    class="w-full px-2.5 py-2 sm:py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm text-xs sm:text-sm bg-white font-medium text-gray-700 cursor-pointer text-center"
                                    value="<?php echo $initialStartDate; ?>" title="Data Inicial">
                            </div>
                        </div>

                        <!-- Data Final (50% on Mobile, 15% of total on Desktop) -->
                        <div class="w-1/2">
                            <div class="relative">
                                <input type="date" id="endDateInput"
                                    class="w-full px-2.5 py-2 sm:py-2.5 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm text-xs sm:text-sm bg-white font-medium text-gray-700 cursor-pointer text-center"
                                    value="<?php echo $initialEndDate; ?>" title="Data Final">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Event List (Simple Timeline) -->
                <div class="space-y-4">
                    <?php if (count($events) > 0): ?>
                    <?php foreach ($events as $event):
        $date = new DateTime($event['start_time']);
        $eventDateStr = $date->format('Y-m-d');
        $isPast = $date < new DateTime();
        $colors = [
            'meeting' => 'border-purple-500 bg-purple-50 text-purple-700',
            'visit' => 'border-green-500 bg-green-50 text-green-700',
            'auction' => 'border-orange-500 bg-orange-50 text-orange-700',
            'other' => 'border-gray-500 bg-gray-50 text-gray-700',
        ];
        $typeClass = $colors[$event['type']] ?? $colors['other'];
        $typeLabels = [
            'meeting' => 'Reunião',
            'visit' => 'Visita',
            'auction' => 'Leilão',
            'other' => 'Outro',
        ];
        $typeLabel = $typeLabels[$event['type']] ?? 'Outro';

        $monthShort = $monthsPt[$date->format('n')] ?? $date->format('M');

        $locParts = array_filter([$event['city'] ?? '', $event['uf'] ?? '']);
        $locStr = !empty($locParts) ? implode(' / ', $locParts) : '';
?>
                    <div class="flex items-start <?php echo $isPast ? 'opacity-60' : ''; ?> event-row group" data-date="<?php echo $eventDateStr; ?>">
                        <div class="flex flex-col items-center mr-4 w-16">
                            <div class="text-sm font-bold text-gray-500 uppercase tracking-wide">
                                <?php echo $monthShort; ?>
                            </div>
                            <div class="text-2xl font-bold text-gray-800">
                                <?php echo $date->format('d'); ?>
                            </div>
                            <div class="text-xs text-gray-500 font-semibold">
                                <?php echo $date->format('H:i'); ?>
                            </div>
                        </div>
                        <div
                            class="flex-1 bg-white rounded-xl shadow-md p-4 border-l-4 <?php echo explode(' ', $typeClass)[0]; ?> hover:shadow-lg transition">
                            <div class="flex justify-between items-start gap-3">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 event-title">
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </h3>
                                    <?php if ($event['client_name']): ?>
                                    <p class="text-sm text-gray-600 flex items-center mt-1 event-client">
                                        <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                                            </path>
                                        </svg>
                                        <?php echo htmlspecialchars($event['client_name']); ?>
                                    </p>
                                    <?php endif; ?>

                                    <?php if ($locStr || !empty($event['address'])): ?>
                                    <p class="text-xs text-gray-500 flex items-center mt-1 event-location">
                                        <svg class="w-3.5 h-3.5 mr-1 text-brand-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <span>
                                            <?php 
                                                echo htmlspecialchars(implode(' • ', array_filter([$locStr, $event['address'] ?? '']))); 
                                            ?>
                                        </span>
                                    </p>
                                    <?php endif; ?>

                                    <?php if ($event['observation']): ?>
                                    <p class="text-sm text-gray-600 mt-2 italic bg-gray-50 p-2 rounded-lg border border-gray-100">
                                        "<?php echo htmlspecialchars($event['observation']); ?>"
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-col items-end gap-2 flex-shrink-0">
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase <?php echo $typeClass; ?> event-type border border-current/20">
                                        <?php echo $typeLabel; ?>
                                    </span>

                                    <div class="flex items-center gap-1.5 mt-1">
                                        <a href="schedule-edit.php?id=<?php echo $event['id']; ?>"
                                            class="text-blue-500 hover:text-blue-700 p-1.5 rounded-lg hover:bg-blue-50 transition cursor-pointer" title="Editar Compromisso">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                </path>
                                            </svg>
                                        </a>
                                        <form method="POST" onsubmit="confirmDelete(event)" class="inline">
                                            <input type="hidden" name="delete_event" value="1">
                                            <input type="hidden" name="id" value="<?php echo $event['id']; ?>">
                                            <button type="submit" class="text-red-500 hover:text-red-700 p-1.5 rounded-lg hover:bg-red-50 transition cursor-pointer"
                                                title="Excluir Compromisso">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                    </path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="text-center text-gray-500 py-12 bg-white rounded-xl shadow-sm">
                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <p class="font-medium text-gray-600">Nenhum compromisso agendado.</p>
                        <a href="schedule-add.php" class="text-brand-600 hover:underline text-sm font-semibold mt-2 inline-block">Criar primeiro compromisso</a>
                    </div>
                    <?php endif; ?>
                    <div id="noResults" class="hidden text-center text-gray-500 py-10 bg-white rounded-xl shadow-sm">
                        Nenhum compromisso encontrado para o período ou termo pesquisado.
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script>
        function filterEvents() {
            const searchInput = document.getElementById('searchInput');
            const startDateInput = document.getElementById('startDateInput');
            const endDateInput = document.getElementById('endDateInput');

            const searchText = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const startDate = startDateInput ? startDateInput.value : '';
            const endDate = endDateInput ? endDateInput.value : '';

            // Validate Date Range
            if (startDate && endDate && startDate > endDate) {
                Swal.fire({
                    icon: 'error',
                    title: 'Período Inválido',
                    text: 'A data inicial não pode ser maior que a data final.',
                    confirmButtonColor: '#B8860B'
                });
                if (startDateInput) startDateInput.classList.add('border-red-500', 'bg-red-50');
                if (endDateInput) endDateInput.classList.add('border-red-500', 'bg-red-50');
                return;
            } else {
                if (startDateInput) startDateInput.classList.remove('border-red-500', 'bg-red-50');
                if (endDateInput) endDateInput.classList.remove('border-red-500', 'bg-red-50');
            }

            const rows = document.querySelectorAll('.event-row');
            let hasVisible = false;

            rows.forEach(row => {
                const rowDate = row.getAttribute('data-date');
                const title = row.querySelector('.event-title') ? row.querySelector('.event-title').textContent.toLowerCase() : '';
                const client = row.querySelector('.event-client') ? row.querySelector('.event-client').textContent.toLowerCase() : '';
                const type = row.querySelector('.event-type') ? row.querySelector('.event-type').textContent.toLowerCase() : '';
                const loc = row.querySelector('.event-location') ? row.querySelector('.event-location').textContent.toLowerCase() : '';

                // Check date bounds
                let dateMatches = true;
                if (startDate && rowDate && rowDate < startDate) {
                    dateMatches = false;
                }
                if (endDate && rowDate && rowDate > endDate) {
                    dateMatches = false;
                }

                // Check text match
                let textMatches = true;
                if (searchText !== '') {
                    textMatches = title.includes(searchText) || client.includes(searchText) || type.includes(searchText) || loc.includes(searchText);
                }

                if (dateMatches && textMatches) {
                    row.style.display = '';
                    hasVisible = true;
                } else {
                    row.style.display = 'none';
                }
            });

            const noResults = document.getElementById('noResults');
            if (noResults) {
                if (!hasVisible && rows.length > 0) {
                    noResults.classList.remove('hidden');
                } else {
                    noResults.classList.add('hidden');
                }
            }
        }

        document.getElementById('searchInput').addEventListener('input', filterEvents);
        document.getElementById('startDateInput').addEventListener('change', filterEvents);
        document.getElementById('endDateInput').addEventListener('change', filterEvents);

        // Run initial filter on page load
        filterEvents();
    </script>
</body>

</html>