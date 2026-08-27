<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();
$user_id = $_SESSION['user_id'];
$pageTitle = 'Categorias';

// Handle Add/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = sanitize($_POST['name']);
        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO " . TABLE_NAME . "categories (user_id, name) VALUES (?, ?)");
            $stmt->execute([$user_id, $name]);
        }
    }
    elseif (isset($_POST['delete_category'])) {
        $id = $_POST['category_id'];
        $stmt = $pdo->prepare("DELETE FROM " . TABLE_NAME . "categories WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
    }
    header("Location: categories.php");
    exit;
}

// Fetch Categories
$stmt = $pdo->prepare("SELECT * FROM " . TABLE_NAME . "categories WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorias - CRM Vitor Müller</title>
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
                    <!-- Add Category Form -->
                    <div class="bg-white shadow rounded-lg p-6 mb-6">
                        <h2 class="text-xl font-semibold mb-4 text-brand-800">Adicionar Nova Categoria</h2>
                        <form method="POST" class="flex gap-4">
                            <input type="text" name="name" placeholder="Nome da Categoria (ex: Terrenos, Apartamentos)"
                                class="flex-1 appearance-none border border-gray-300 rounded-lg py-3 px-4 focus:outline-none focus:ring-2 focus:ring-brand-500"
                                required>
                            <button type="submit" name="add_category"
                                class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 px-6 rounded-lg transition shadow-lg flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>

                            </button>
                        </form>
                    </div>

                    <!-- Search Filter -->
                    <div class="mb-6">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </span>
                            <input type="text" id="searchInput"
                                class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-brand-500 shadow-sm"
                                placeholder="Buscar categoria...">
                        </div>
                    </div>

                    <!-- Categories List -->
                    <div class="bg-white shadow rounded-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-brand-800">Categorias Cadastradas</h2>
                        </div>
                        <ul class="divide-y divide-gray-200" id="categoriesList">
                            <?php if (count($categories) > 0): ?>
                            <?php foreach ($categories as $category): ?>
                            <li
                                class="px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition category-row">
                                <span class="text-gray-800 font-medium category-name">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </span>
                                <form method="POST" onsubmit="confirmDelete(event)">
                                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                    <input type="hidden" name="delete_category" value="1">
                                    <button type="submit"
                                        class="text-red-500 hover:text-red-700 p-2 rounded hover:bg-red-50 transition">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                            </path>
                                        </svg>
                                    </button>
                                </form>
                            </li>
                            <?php
    endforeach; ?>
                            <?php
else: ?>
                            <li class="px-6 py-8 text-center text-gray-500">Nenhuma categoria cadastrada ainda.</li>
                            <?php
endif; ?>
                        </ul>
                        <div id="noResults" class="hidden px-6 py-8 text-center text-gray-500">
                            Nenhum resultado encontrado.
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script>
        document.getElementById('searchInput').addEventListener('keyup', function () {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('.category-row');
            let hasVisible = false;

            rows.forEach(row => {
                const name = row.querySelector('.category-name').textContent.toLowerCase();

                if (name.includes(searchText)) {
                    row.style.display = '';
                    hasVisible = true;
                } else {
                    row.style.display = 'none';
                }
            });

            const noResults = document.getElementById('noResults');
            if (!hasVisible && rows.length > 0) { // Only show no results if there were items to begin with
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
        });
    </script>
</body>

</html>