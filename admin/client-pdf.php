<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

requireLogin();
$user_id = $_SESSION['user_id'];

if (!isset($_GET['id'])) {
    header("Location: clients.php");
    exit;
}

$client_id = intval($_GET['id']);

// Fetch Client Info
$stmt = $pdo->prepare("SELECT * FROM " . TABLE_NAME . "clients WHERE id = ? AND user_id = ?");
$stmt->execute([$client_id, $user_id]);
$client = $stmt->fetch();

if (!$client) {
    echo "Cliente não encontrado ou acesso negado.";
    exit;
}

// Fetch Intentions
$stmt = $pdo->prepare("SELECT i.*, cat.name as category_name FROM " . TABLE_NAME . "intentions i LEFT JOIN " . TABLE_NAME . "categories cat ON i.category_id = cat.id WHERE i.client_id = ? ORDER BY i.created_at DESC");
$stmt->execute([$client_id]);
$intentions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Interactions
$stmtInteractions = $pdo->prepare("SELECT * FROM " . TABLE_NAME . "interactions WHERE client_id = ? ORDER BY interaction_date DESC, id DESC");
$stmtInteractions->execute([$client_id]);
$interactions = $stmtInteractions->fetchAll(PDO::FETCH_ASSOC);

$currentDate = date('d/m/Y \à\s H:i');
$waEmbralMsg = buildClientEmbralWhatsAppMessage($client, $intentions);
$waShareUrl = "https://wa.me/?text=" . rawurlencode($waEmbralMsg);
$autoDownload = !empty($_GET['auto_download']) || !empty($_GET['download']);
$isSentEmbral = (isset($_GET['sent']) && $_GET['sent'] === 'embral');

// Helper to parse animal categories into separate lines preserving internal commas in known categories
function parseAnimalCategoriesList($text) {
    if (empty($text) || trim($text) === '-') return [];
    
    $knownCategories = [
        'Novilhas prenhas, com gestação superior a 5 meses',
        'Novilhas prenhas, com gestação de 2 a 5 meses',
        'Bezerras de 0 a 3 meses',
        'Bezerras de 3 a 6 meses',
        'Bezerras de 6 a 12 meses',
        'Bezerras acima de 12 meses inseminadas',
        'Vacas 1ª cria',
        'Vacas 2ª cria',
        'Vacas 3ª cria'
    ];
    
    $found = [];
    $remaining = $text;
    foreach ($knownCategories as $cat) {
        if (stripos($remaining, $cat) !== false) {
            $found[] = $cat;
            $remaining = str_ireplace($cat, '', $remaining);
        }
    }
    
    $remainingClean = trim($remaining, " ,\t\n\r\0\x0B");
    if (!empty($remainingClean)) {
        $parts = array_filter(array_map('trim', explode(',', $remainingClean)));
        foreach ($parts as $p) {
            if (!empty($p) && !in_array($p, $found)) {
                $found[] = $p;
            }
        }
    }
    
    if (empty($found)) {
        return array_filter(array_map('trim', explode(',', $text)));
    }
    
    return $found;
}

// Helper to parse breeds list into separate lines when multiple exist
function parseBreedsList($text) {
    if (empty($text) || trim($text) === '-') return [];
    $parts = array_filter(array_map('trim', explode(',', $text)));
    return array_values($parts);
}

$animalCategoriesList = parseAnimalCategoriesList($client['animal_categories'] ?? '');
$breedsList = parseBreedsList($client['breed_interests'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Cliente - <?php echo htmlspecialchars($client['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        /* Estilos globais de impressão e controle de quebra de página */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background-color: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .pdf-container {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            .pdf-section, .info-card, .intention-item, .interaction-item, tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }

        /* Regras de quebra para html2pdf (html2canvas) */
        .pdf-section, .info-card, .intention-item, .interaction-item {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
    </style>
</head>

<body class="bg-gray-100 font-sans p-4 md:p-8 min-h-screen">

    <?php if ($isSentEmbral): ?>
        <!-- Success Alert for Embral Status Change -->
        <div class="max-w-4xl mx-auto mb-4 bg-blue-50 border-l-4 border-blue-600 p-4 rounded-r-lg shadow-sm no-print flex items-center justify-between">
            <div class="flex items-center">
                <svg class="w-6 h-6 text-blue-600 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <p class="text-sm font-bold text-blue-900">Status atualizado para Embral!</p>
                    <p class="text-xs text-blue-700">A ficha está pronta. Você pode compartilhar pelo WhatsApp clicando em imprimir ou baixar PDF.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Action Bar (Hidden on print) -->
    <div class="max-w-4xl mx-auto mb-6 flex flex-wrap justify-between items-center gap-3 no-print">
        <a href="clients.php" class="text-gray-600 hover:text-gray-900 font-bold flex items-center text-sm">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Voltar para Clientes
        </a>

        <div class="flex flex-wrap items-center gap-2">
            <button onclick="downloadPDF()" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg shadow transition flex items-center text-sm cursor-pointer">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Baixar PDF
            </button>

            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg shadow transition flex items-center text-sm cursor-pointer">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                Imprimir
            </button>
        </div>
    </div>

    <!-- Printable Content Container -->
    <div id="pdfContent" class="max-w-4xl mx-auto bg-white shadow-xl rounded-xl p-8 border border-gray-200 pdf-container space-y-6">
        
        <!-- Header / Logo -->
        <div class="flex justify-between items-center border-b-2 border-amber-600 pb-4 pdf-section">
            <div class="flex items-center space-x-4">
                <img src="../assets/images/logo.png" alt="Vitor Müller Pecuária" class="h-14 w-auto object-contain">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 uppercase tracking-wide">Ficha de Cadastro do Cliente</h1>
                    <p class="text-xs text-gray-500 font-medium">Vitor Müller - Pecuária de Leite</p>
                </div>
            </div>
            <div class="text-right text-xs text-gray-500">
                <p>Gerado em: <strong><?php echo $currentDate; ?></strong></p>
                <p>Status: <span class="font-bold text-amber-700"><?php echo htmlspecialchars($client['status'] ?? 'Ativo'); ?></span></p>
            </div>
        </div>

        <!-- Section 1: Principal Client Info -->
        <div class="pdf-section">
            <h2 class="text-xs font-bold text-amber-900 uppercase tracking-wider bg-amber-50 py-1.5 px-3 rounded border-l-4 border-amber-600 mb-3">
                1. Dados Principais do Cliente
            </h2>
            
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="col-span-2 sm:col-span-1 info-card bg-gray-50/70 p-3 rounded-lg border border-gray-200">
                    <span class="block text-[11px] font-bold text-gray-500 uppercase">Nome Completo</span>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="font-bold text-gray-900 text-sm"><?php echo htmlspecialchars($client['name']); ?></span>
                        <?php if (!empty($client['is_potential'])): ?>
                            <span class="bg-amber-100 text-amber-800 text-[10px] px-2 py-0.5 rounded font-bold">⭐ Potencial</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-span-2 sm:col-span-1 info-card bg-gray-50/70 p-3 rounded-lg border border-gray-200">
                    <span class="block text-[11px] font-bold text-gray-500 uppercase">Nome da Fazenda / Propriedade</span>
                    <span class="font-bold text-gray-900 text-sm mt-0.5 block"><?php echo htmlspecialchars($client['farm_name'] ?: '-'); ?></span>
                </div>

                <div class="info-card bg-gray-50/70 p-3 rounded-lg border border-gray-200">
                    <span class="block text-[11px] font-bold text-gray-500 uppercase">Telefone / WhatsApp</span>
                    <span class="font-semibold text-gray-900 text-xs mt-0.5 block"><?php echo htmlspecialchars(formatPhone($client['phone'])); ?></span>
                </div>

                <div class="info-card bg-gray-50/70 p-3 rounded-lg border border-gray-200">
                    <span class="block text-[11px] font-bold text-gray-500 uppercase">E-mail</span>
                    <span class="font-semibold text-gray-900 text-xs mt-0.5 block truncate"><?php echo htmlspecialchars($client['email'] ?: '-'); ?></span>
                </div>

                <div class="col-span-2 sm:col-span-1 info-card bg-gray-50/70 p-3 rounded-lg border border-gray-200">
                    <span class="block text-[11px] font-bold text-gray-500 uppercase">Cidade / UF</span>
                    <span class="font-semibold text-gray-900 text-xs mt-0.5 block">
                        <?php 
                        $loc = array_filter([$client['city'] ?? '', $client['uf'] ?? '']);
                        echo htmlspecialchars(!empty($loc) ? implode(' / ', $loc) : '-');
                        ?>
                    </span>
                </div>

                <div class="col-span-2 sm:col-span-1 info-card bg-gray-50/70 p-3 rounded-lg border border-gray-200">
                    <span class="block text-[11px] font-bold text-gray-500 uppercase">Endereço Completo</span>
                    <span class="font-semibold text-gray-900 text-xs mt-0.5 block"><?php echo htmlspecialchars($client['address'] ?: '-'); ?></span>
                </div>
            </div>
        </div>

        <!-- Section 2: Questionnaire / Commercial Profile -->
        <div class="pdf-section">
            <h2 class="text-xs font-bold text-amber-900 uppercase tracking-wider bg-amber-50 py-1.5 px-3 rounded border-l-4 border-amber-600 mb-3">
                2. Perfil Comercial
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <!-- Condição de Pagamento -->
                <div class="border border-gray-200 p-3 rounded-lg bg-gray-50 info-card">
                    <p class="text-[11px] font-bold text-amber-800 uppercase">Condição de Pagamento Desejada</p>
                    <p class="font-semibold text-gray-900 text-xs mt-1"><?php echo htmlspecialchars($client['payment_condition'] ?: '-'); ?></p>
                </div>

                <!-- Raças / Interesse em Adquirir -->
                <div class="border border-gray-200 p-3 rounded-lg bg-gray-50 info-card">
                    <p class="text-[11px] font-bold text-amber-800 uppercase">Interesse em Adquirir (Raças)</p>
                    <?php if (!empty($breedsList)): ?>
                        <?php if (count($breedsList) > 1): ?>
                            <ul class="mt-1 space-y-0.5">
                                <?php foreach ($breedsList as $breed): ?>
                                    <li class="flex items-center text-xs font-semibold text-gray-900">
                                        <span class="text-amber-600 mr-1.5 font-bold">•</span>
                                        <span><?php echo htmlspecialchars($breed); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="font-semibold text-gray-900 text-xs mt-1"><?php echo htmlspecialchars($breedsList[0]); ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="font-semibold text-gray-900 text-xs mt-1">-</p>
                    <?php endif; ?>
                </div>

                <!-- Motivo da Aquisição -->
                <div class="border border-gray-200 p-3 rounded-lg bg-gray-50 info-card">
                    <p class="text-[11px] font-bold text-amber-800 uppercase">Motivo da Aquisição</p>
                    <p class="font-semibold text-gray-900 text-xs mt-1"><?php echo htmlspecialchars($client['acquisition_reason'] ?: '-'); ?></p>
                </div>

                <!-- Qtd. Animais Necessários -->
                <div class="border border-gray-200 p-3 rounded-lg bg-gray-50 info-card">
                    <p class="text-[11px] font-bold text-amber-800 uppercase">Qtd. Animais Necessários</p>
                    <p class="font-semibold text-gray-900 text-xs mt-1"><?php echo htmlspecialchars(($client['purchase_animal_count'] ?? '') ?: '-'); ?></p>
                </div>

                <!-- Categorias de Animais Desejadas (Uma por linha) -->
                <div class="border border-gray-200 p-3 rounded-lg bg-gray-50 sm:col-span-2 info-card">
                    <p class="text-[11px] font-bold text-amber-800 uppercase">Categorias de Animais Desejadas</p>
                    <?php if (!empty($animalCategoriesList)): ?>
                        <ul class="mt-1.5 space-y-1">
                            <?php foreach ($animalCategoriesList as $cat): ?>
                                <li class="flex items-start text-xs font-semibold text-gray-900 bg-white p-1.5 rounded border border-gray-100 shadow-2xs">
                                    <span class="text-amber-600 mr-2 font-bold">•</span>
                                    <span><?php echo htmlspecialchars($cat); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="font-semibold text-gray-900 text-xs mt-1">-</p>
                    <?php endif; ?>
                </div>

                <!-- Sistema de Produção -->
                <div class="border border-gray-200 p-3 rounded-lg bg-gray-50 info-card">
                    <p class="text-[11px] font-bold text-amber-800 uppercase">Sistema de Produção</p>
                    <p class="font-semibold text-gray-900 text-xs mt-1"><?php echo htmlspecialchars(($client['production_system'] ?? '') ?: '-'); ?></p>
                </div>

                <!-- Produtor de Leite -->
                <div class="border border-gray-200 p-3 rounded-lg bg-gray-50 info-card">
                    <p class="text-[11px] font-bold text-amber-800 uppercase">Produtor de Leite?</p>
                    <p class="font-semibold text-gray-900 text-xs mt-1"><?php echo htmlspecialchars($client['is_milk_producer'] ?: '-'); ?></p>
                </div>

                <!-- Quantidade de Animais Possuídos -->
                <div class="border border-gray-200 p-3 rounded-lg bg-gray-50 info-card">
                    <p class="text-[11px] font-bold text-amber-800 uppercase">Quantidade de Animais Possuídos</p>
                    <p class="font-semibold text-gray-900 text-xs mt-1"><?php echo htmlspecialchars($client['animal_count_range'] ?: '-'); ?></p>
                </div>

                <!-- Produção Diária de Leite -->
                <div class="border border-gray-200 p-3 rounded-lg bg-gray-50 info-card">
                    <p class="text-[11px] font-bold text-amber-800 uppercase">Produção Diária de Leite</p>
                    <p class="font-semibold text-gray-900 text-xs mt-1"><?php echo htmlspecialchars($client['milk_production_range'] ?: '-'); ?></p>
                </div>
            </div>
        </div>

        <!-- Section 3: Intentions (if any) -->
        <?php 
        $activePdfInts = array_filter($intentions, fn($i) => empty($i['status']) || $i['status'] === 'active');
        $inactivePdfInts = array_filter($intentions, fn($i) => ($i['status'] ?? '') === 'inactive');
        ?>
        <?php if (!empty($intentions)): ?>
            <div class="pdf-section">
                <h2 class="text-xs font-bold text-amber-900 uppercase tracking-wider bg-amber-50 py-1.5 px-3 rounded border-l-4 border-amber-600 mb-3">
                    3. Intenções Comerciais (<?php echo count($activePdfInts); ?> ativas<?php echo !empty($inactivePdfInts) ? ', ' . count($inactivePdfInts) . ' inativas' : ''; ?>)
                </h2>

                <div class="space-y-2.5">
                    <?php foreach ($activePdfInts as $intention): ?>
                        <div class="intention-item border p-3 rounded-lg text-xs <?php echo $intention['type'] === 'buy' ? 'bg-blue-50/70 border-blue-200' : 'bg-red-50/70 border-red-200'; ?>">
                            <div class="flex justify-between items-center">
                                <span class="font-bold uppercase text-xs <?php echo $intention['type'] === 'buy' ? 'text-blue-800' : 'text-red-800'; ?>">
                                    <?php echo $intention['type'] === 'buy' ? '🛒 Intenção de Compra' : '💰 Intenção de Venda'; ?> - <?php echo htmlspecialchars($intention['category_name'] ?? 'Geral'); ?>
                                </span>
                                <?php if ($intention['value'] > 0): ?>
                                    <span class="font-bold text-green-700 text-xs">R$ <?php echo number_format($intention['value'], 2, ',', '.'); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="text-gray-800 mt-1 leading-relaxed"><?php echo htmlspecialchars($intention['description']); ?></p>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!empty($inactivePdfInts)): ?>
                        <div class="mt-2 pt-2">
                            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1.5">Intenções Finalizadas / Histórico:</p>
                            <div class="space-y-2">
                                <?php foreach ($inactivePdfInts as $intention): ?>
                                    <div class="intention-item border border-gray-200 p-2.5 rounded-lg bg-gray-50 text-xs opacity-80">
                                        <div class="flex justify-between items-center">
                                            <span class="font-bold text-gray-700 text-xs">
                                                <?php echo $intention['type'] === 'buy' ? '🛒 Compra' : '💰 Venda'; ?> - <?php echo htmlspecialchars($intention['category_name'] ?? 'Geral'); ?>
                                                <span class="ml-1 text-[10px] bg-gray-200 text-gray-700 font-bold px-1.5 py-0.2 rounded">Inativo</span>
                                            </span>
                                            <?php if (!empty($intention['inactivated_at'])): ?>
                                                <span class="text-[10px] text-gray-400">Encerrado em <?php echo date('d/m/Y', strtotime($intention['inactivated_at'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-gray-600 mt-0.5"><?php echo htmlspecialchars($intention['description']); ?></p>
                                        <?php if (!empty($intention['inactivation_reason'])): ?>
                                            <p class="text-[11px] text-amber-800 font-medium mt-1 bg-amber-50/80 p-1.5 rounded border border-amber-200/60">
                                                <strong>Motivo:</strong> <?php echo htmlspecialchars($intention['inactivation_reason']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Section 4: Interactions (if any) -->
        <?php if (!empty($interactions)): ?>
            <div class="pdf-section">
                <h2 class="text-xs font-bold text-amber-900 uppercase tracking-wider bg-amber-50 py-1.5 px-3 rounded border-l-4 border-amber-600 mb-3">
                    4. Histórico de Interações (<?php echo count($interactions); ?>)
                </h2>

                <div class="space-y-2">
                    <?php foreach ($interactions as $inter): 
                        $iDate = !empty($inter['interaction_date']) ? date('d/m/Y', strtotime($inter['interaction_date'])) : '-';
                    ?>
                        <div class="interaction-item border border-gray-200 p-2.5 rounded-lg bg-gray-50/70 text-xs">
                            <div class="flex justify-between items-center mb-1">
                                <span class="font-bold text-gray-900"><?php echo htmlspecialchars($inter['title']); ?></span>
                                <span class="text-[10px] text-gray-500 font-semibold bg-white px-2 py-0.5 rounded border border-gray-200"><?php echo $iDate; ?></span>
                            </div>
                            <p class="text-gray-700 text-[11px] leading-relaxed"><?php echo nl2br(htmlspecialchars($inter['description'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="border-t border-gray-200 pt-3 mt-6 text-center text-[11px] text-gray-400 pdf-section">
            <p>CRM Vitor Müller - Documento gerado automaticamente para controle interno e envio.</p>
        </div>

    </div>

    <script>
        function downloadPDF() {
            const element = document.getElementById('pdfContent');
            const opt = {
                margin:       [8, 8, 8, 8],
                filename:     'ficha_cliente_<?php echo preg_replace('/[^a-z0-9]/i', '_', strtolower($client['name'])); ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, logging: false },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
            };
            html2pdf().set(opt).from(element).save();
        }

        <?php if ($autoDownload): ?>
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(downloadPDF, 600);
        });
        <?php endif; ?>
    </script>
</body>

</html>
