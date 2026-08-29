<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();
$user_id = $_SESSION['user_id'];
$pageTitle = 'Relatórios';

// Filters
$uf_filter = isset($_GET['uf']) ? $_GET['uf'] : '';
$category_id = isset($_GET['category_id']) ? $_GET['category_id'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all'; // 'buy' or 'sell'
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$potential_filter = isset($_GET['is_potential']) ? $_GET['is_potential'] : '';
$breed_filter = isset($_GET['breed']) ? $_GET['breed'] : '';
$payment_filter = isset($_GET['payment']) ? $_GET['payment'] : '';
$producer_filter = isset($_GET['producer']) ? $_GET['producer'] : '';

$query = "
    SELECT c.id as client_id, c.name as client_name, c.phone, c.email, c.uf, c.city, c.status, c.is_potential,
           c.payment_condition, c.breed_interests, c.is_milk_producer, c.acquisition_reason,
           i.id as intention_id, i.type, i.description, i.value, cat.name as category_name 
    FROM " . TABLE_NAME . "clients c
    LEFT JOIN " . TABLE_NAME . "intentions i ON i.client_id = c.id
    LEFT JOIN " . TABLE_NAME . "categories cat ON i.category_id = cat.id 
    WHERE c.user_id = :user_id
";

$params = [':user_id' => $user_id];

if ($uf_filter) {
    $query .= " AND c.uf = :uf";
    $params[':uf'] = $uf_filter;
}
if ($category_id) {
    $query .= " AND i.category_id = :category_id";
    $params[':category_id'] = $category_id;
}
if ($type_filter !== 'all') {
    $query .= " AND i.type = :type";
    $params[':type'] = $type_filter;
}
if ($status_filter) {
    $query .= " AND c.status = :status";
    $params[':status'] = $status_filter;
}
if ($potential_filter !== '') {
    $query .= " AND c.is_potential = :is_potential";
    $params[':is_potential'] = intval($potential_filter);
}
if ($breed_filter) {
    $query .= " AND c.breed_interests LIKE :breed";
    $params[':breed'] = '%' . $breed_filter . '%';
}
if ($payment_filter) {
    $query .= " AND c.payment_condition = :payment";
    $params[':payment'] = $payment_filter;
}
if ($producer_filter) {
    $query .= " AND c.is_milk_producer = :producer";
    $params[':producer'] = $producer_filter;
}

$query .= " ORDER BY c.is_potential DESC, c.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$results = $stmt->fetchAll();

// Fetch Options
$states = getBrazilianStates();

$stmt = $pdo->prepare("SELECT * FROM " . TABLE_NAME . "categories WHERE user_id = ?");
$stmt->execute([$user_id]);
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - CRM Vitor Müller</title>
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

                <!-- Filters -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-lg font-bold text-gray-700 mb-4">Filtrar Clientes e Interesses</h2>
                    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Status do Cliente</label>
                            <select name="status" class="w-full border p-2 rounded text-sm">
                                <option value="">Todos Status</option>
                                <option value="Ativo" <?php echo $status_filter === 'Ativo' ? 'selected' : ''; ?>>Ativo
                                </option>
                                <option value="Atendido" <?php echo $status_filter === 'Atendido' ? 'selected' : ''; ?>>
                                    Atendido</option>
                                <option value="Embral" <?php echo $status_filter === 'Embral' ? 'selected' : ''; ?>>Embral
                                </option>
                                <option value="Novo" <?php echo in_array($status_filter, ['Novo', 'Pré-cadastro']) ? 'selected' : ''; ?>>Novo</option>
                                <option value="Inativo" <?php echo $status_filter === 'Inativo' ? 'selected' : ''; ?>>
                                    Inativo</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Cliente em Potencial</label>
                            <select name="is_potential" class="w-full border p-2 rounded text-sm">
                                <option value="">Todos</option>
                                <option value="1" <?php echo $potential_filter === '1' ? 'selected' : ''; ?>>⭐ Somente em
                                    Potencial</option>
                                <option value="0" <?php echo $potential_filter === '0' ? 'selected' : ''; ?>>Outros
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Raça de Interesse</label>
                            <select name="breed" class="w-full border p-2 rounded text-sm">
                                <option value="">Todas as Raças</option>
                                <?php foreach (['Jersey', 'Holandês', 'Jersolando', 'Girolando', 'Gir', 'Máquinas'] as $b): ?>
                                    <option value="<?php echo $b; ?>" <?php echo $breed_filter === $b ? 'selected' : ''; ?>>
                                        <?php echo $b; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Condição de Pagamento</label>
                            <select name="payment" class="w-full border p-2 rounded text-sm">
                                <option value="">Todas as Condições</option>
                                <?php foreach (['10 pagamentos', '12 pagamentos', '15 pagamentos', 'À vista'] as $p): ?>
                                    <option value="<?php echo $p; ?>" <?php echo $payment_filter === $p ? 'selected' : ''; ?>>
                                        <?php echo $p; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">UF (Estado)</label>
                            <select name="uf" class="w-full border p-2 rounded text-sm">
                                <option value="">Todos os Estados</option>
                                <?php foreach ($states as $code => $stateName): ?>
                                    <option value="<?php echo $code; ?>" <?php echo $uf_filter == $code ? 'selected' : ''; ?>>
                                        <?php echo $code . ' - ' . htmlspecialchars($stateName); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Categoria de Interesse</label>
                            <select name="category_id" class="w-full border p-2 rounded text-sm">
                                <option value="">Todas</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo $category_id == $c['id'] ? 'selected' : ''; ?>>
                                        <?php echo $c['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Tipo de Intenção</label>
                            <select name="type" class="w-full border p-2 rounded text-sm">
                                <option value="all" <?php echo $type_filter == 'all' ? 'selected' : ''; ?>>Todos</option>
                                <option value="buy" <?php echo $type_filter == 'buy' ? 'selected' : ''; ?>>Compra
                                </option>
                                <option value="sell" <?php echo $type_filter == 'sell' ? 'selected' : ''; ?>>Venda
                                </option>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <button type="submit"
                                class="w-full bg-brand-500 hover:bg-brand-600 text-white font-bold py-2 px-4 rounded transition">
                                Aplicar Filtros
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Results -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h2 class="text-xl font-semibold text-gray-800">Resultados Encontrados</h2>
                        <span
                            class="bg-brand-100 text-brand-800 px-3 py-1 rounded-full text-sm font-bold border border-brand-200">
                            <?php echo count($results); ?> registros
                        </span>
                    </div>

                    <?php if (count($results) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full leading-normal">
                                <thead>
                                    <tr>
                                        <th
                                            class="px-5 py-3 border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Cliente</th>
                                        <th
                                            class="px-5 py-3 border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Status</th>
                                        <th
                                            class="px-5 py-3 border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Cidade / UF</th>
                                        <th
                                            class="px-5 py-3 border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Raça / Pagamento</th>
                                        <th
                                            class="px-5 py-3 border-b-2 border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Intenções de Compra/Venda</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $item): ?>
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <a href="client-details.php?id=<?php echo $item['client_id']; ?>"
                                                    class="font-bold text-brand-600 hover:underline flex items-center">
                                                    <?php echo htmlspecialchars($item['client_name']); ?>
                                                    <?php if (!empty($item['is_potential'])): ?>
                                                        <span class="ml-1 text-amber-500" title="Cliente em Potencial">⭐</span>
                                                    <?php endif; ?>
                                                </a>
                                                <div class="text-gray-500 text-xs mt-1">
                                                    <?php echo htmlspecialchars(formatPhone($item['phone'])); ?>
                                                    <?php if (!empty($item['email'])): ?>
                                                        <span
                                                            class="block text-gray-400 text-xs"><?php echo htmlspecialchars($item['email']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <?php if (($item['status'] ?? '') === 'Embral'): ?>
                                                    <span
                                                        class="bg-blue-100 text-blue-800 text-xs px-2.5 py-1 rounded-full font-bold border border-blue-300">
                                                        Embral
                                                    </span>
                                                <?php elseif (($item['status'] ?? '') === 'Atendido'): ?>
                                                    <span
                                                        class="bg-purple-100 text-purple-800 text-xs px-2.5 py-1 rounded-full font-bold border border-purple-300">
                                                        Atendido
                                                    </span>
                                                <?php elseif (($item['status'] ?? '') === 'Inativo'): ?>
                                                    <span
                                                        class="bg-gray-100 text-gray-700 text-xs px-2.5 py-1 rounded-full font-bold border border-gray-300">
                                                        Inativo
                                                    </span>
                                                <?php elseif (in_array($item['status'] ?? '', ['Novo', 'Pré-cadastro'])): ?>
                                                    <span
                                                        class="bg-amber-100 text-amber-800 text-xs px-2.5 py-1 rounded-full font-bold border border-amber-300">
                                                        Novo
                                                    </span>
                                                <?php else: ?>
                                                    <span
                                                        class="bg-green-100 text-green-800 text-xs px-2.5 py-1 rounded-full font-bold border border-green-300">
                                                        Ativo
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <?php
                                                $loc = array_filter([$item['city'] ?? '', $item['uf'] ?? '']);
                                                echo htmlspecialchars(!empty($loc) ? implode(' / ', $loc) : '-');
                                                ?>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <div class="font-semibold text-gray-800 text-xs">
                                                    <?php echo htmlspecialchars($item['breed_interests'] ?: 'N/A'); ?>
                                                </div>
                                                <div class="text-gray-500 text-xs mt-0.5">
                                                    <?php echo htmlspecialchars($item['payment_condition'] ?: 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <?php if (!empty($item['intention_id'])): ?>
                                                    <div class="flex items-center gap-2">
                                                        <?php if ($item['type'] == 'buy'): ?>
                                                            <span
                                                                class="bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded font-bold">Compra</span>
                                                        <?php else: ?>
                                                            <span
                                                                class="bg-red-100 text-red-800 text-xs px-2 py-0.5 rounded font-bold">Venda</span>
                                                        <?php endif; ?>

                                                        <span class="text-gray-700 text-xs font-medium">
                                                            <?php echo htmlspecialchars($item['category_name'] ?: 'Sem cat.'); ?>
                                                        </span>
                                                    </div>
                                                    <div class="text-xs text-gray-500 mt-1 max-w-xs truncate"
                                                        title="<?php echo htmlspecialchars($item['description']); ?>">
                                                        <?php echo htmlspecialchars($item['description']); ?>
                                                    </div>
                                                    <?php if ($item['value']): ?>
                                                        <div class="text-green-600 font-semibold text-xs mt-0.5">
                                                            R$ <?php echo number_format($item['value'], 2, ',', '.'); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-xs">Sem intenção cadastrada</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-10 text-center text-gray-500">
                            Nenhum registro encontrado para os filtros selecionados.
                        </div>
                    <?php endif; ?>
                </div>

            </main>
        </div>
    </div>
</body>

</html>