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
$intentions = $stmt->fetchAll();

$currentDate = date('d/m/Y \à\s H:i');
$waEmbralMsg = buildClientEmbralWhatsAppMessage($client, $intentions);
$waShareUrl = "https://wa.me/?text=" . rawurlencode($waEmbralMsg);
$autoDownload = !empty($_GET['auto_download']) || !empty($_GET['download']);
$isSentEmbral = (isset($_GET['sent']) && $_GET['sent'] === 'embral');
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
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background-color: #ffffff !important;
                padding: 0 !important;
            }
            .pdf-container {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
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
                    <p class="text-xs text-blue-700">A ficha está pronta. Você pode compartilhar pelo WhatsApp clicando em imprimir.</p>
                </div>
            </div>
            <!--
            <a href="https://wa.me/?text=<?php echo rawurlencode($waEmbralMsg); ?>" target="_blank"
                class="bg-green-600 hover:bg-green-700 text-white font-bold py-1.5 px-3 rounded-lg text-xs flex items-center shadow transition ml-3 flex-shrink-0">
                <svg class="w-4 h-4 mr-1.5 fill-current" viewBox="0 0 24 24">
                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                </svg>
                Enviar no WhatsApp
            </a>
    -->
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
            <!--
            <a href="<?php echo $waShareUrl; ?>" target="_blank"
                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2.5 px-4 rounded-lg shadow transition flex items-center text-sm"
                title="Compartilhar resumo cadastral no WhatsApp">
                <svg class="w-4 h-4 mr-2 fill-current" viewBox="0 0 24 24">
                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.017-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                </svg>
                WhatsApp
            </a>
    -->

            <button onclick="downloadPDF()" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 px-4 rounded-lg shadow transition flex items-center text-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Baixar PDF
            </button>

            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-lg shadow transition flex items-center text-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                Imprimir
            </button>
        </div>
    </div>

    <!-- Printable Content Container -->
    <div id="pdfContent" class="max-w-4xl mx-auto bg-white shadow-xl rounded-xl p-8 border border-gray-200 pdf-container">
        
        <!-- Header / Logo -->
        <div class="flex justify-between items-center border-b-2 border-amber-600 pb-4 mb-6">
            <div class="flex items-center space-x-4">
                <img src="../assets/images/logo.png" alt="Vitor Müller Pecuária" class="h-16 w-auto object-contain">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 uppercase tracking-wide">Ficha de Cadastro do Cliente</h1>
                    <p class="text-xs text-gray-500">Vitor Müller - Pecuária de Leite</p>
                </div>
            </div>
            <div class="text-right text-xs text-gray-500">
                <p>Gerado em: <strong><?php echo $currentDate; ?></strong></p>
                <p>Status: <span class="font-bold text-amber-700"><?php echo htmlspecialchars($client['status'] ?? 'Ativo'); ?></span></p>
            </div>
        </div>

        <!-- Section 1: Principal Client Info -->
        <div class="mb-6">
            <h2 class="text-sm font-bold text-amber-900 uppercase tracking-wider bg-amber-50 p-2 rounded border-l-4 border-amber-600 mb-4">
                1. Dados Principais do Cliente
            </h2>
            
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="col-span-2 sm:col-span-1">
                    <span class="block text-xs font-bold text-gray-500 uppercase">Nome Completo</span>
                    <span class="font-semibold text-gray-900 text-base"><?php echo htmlspecialchars($client['name']); ?></span>
                    <?php if (!empty($client['is_potential'])): ?>
                        <span class="inline-block bg-amber-100 text-amber-800 text-xs px-2 py-0.5 rounded font-bold ml-2">⭐ Potencial</span>
                    <?php endif; ?>
                </div>

                <div class="col-span-2 sm:col-span-1">
                    <span class="block text-xs font-bold text-gray-500 uppercase">Nome da Fazenda / Propriedade</span>
                    <span class="font-semibold text-gray-900 text-base"><?php echo htmlspecialchars($client['farm_name'] ?: '-'); ?></span>
                </div>

                <div>
                    <span class="block text-xs font-bold text-gray-500 uppercase">Telefone / WhatsApp</span>
                    <span class="font-semibold text-gray-900"><?php echo htmlspecialchars(formatPhone($client['phone'])); ?></span>
                </div>

                <div>
                    <span class="block text-xs font-bold text-gray-500 uppercase">E-mail</span>
                    <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($client['email'] ?: '-'); ?></span>
                </div>

                <div class="col-span-2 sm:col-span-1">
                    <span class="block text-xs font-bold text-gray-500 uppercase">Cidade / UF</span>
                    <span class="font-semibold text-gray-900">
                        <?php 
                        $loc = array_filter([$client['city'] ?? '', $client['uf'] ?? '']);
                        echo htmlspecialchars(!empty($loc) ? implode(' / ', $loc) : '-');
                        ?>
                    </span>
                </div>

                <div class="col-span-2">
                    <span class="block text-xs font-bold text-gray-500 uppercase">Endereço Completo</span>
                    <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($client['address'] ?: '-'); ?></span>
                </div>
            </div>
        </div>

        <!-- Section 2: Questionnaire / Commercial Profile -->
        <div class="mb-6">
            <h2 class="text-sm font-bold text-amber-900 uppercase tracking-wider bg-amber-50 p-2 rounded border-l-4 border-amber-600 mb-4">
                2. Perfil Comercial
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div class="border border-gray-200 p-3 rounded-lg bg-gray-50">
                    <p class="text-xs font-bold text-amber-800 uppercase">1. Condição de Pagamento Desejada</p>
                    <p class="font-semibold text-gray-900 mt-1"><?php echo htmlspecialchars($client['payment_condition'] ?: '-'); ?></p>
                </div>

                <div class="border border-gray-200 p-3 rounded-lg bg-gray-50">
                    <p class="text-xs font-bold text-amber-800 uppercase">2. Raças de Interesse</p>
                    <p class="font-semibold text-gray-900 mt-1"><?php echo htmlspecialchars($client['breed_interests'] ?: '-'); ?></p>
                </div>

                <div class="border border-gray-200 p-3 rounded-lg bg-gray-50">
                    <p class="text-xs font-bold text-amber-800 uppercase">3. Produtor de Leite?</p>
                    <p class="font-semibold text-gray-900 mt-1"><?php echo htmlspecialchars($client['is_milk_producer'] ?: '-'); ?></p>
                </div>

                <div class="border border-gray-200 p-3 rounded-lg bg-gray-50">
                    <p class="text-xs font-bold text-amber-800 uppercase">4. Motivo da Aquisição</p>
                    <p class="font-semibold text-gray-900 mt-1"><?php echo htmlspecialchars($client['acquisition_reason'] ?: '-'); ?></p>
                </div>

                <div class="border border-gray-200 p-3 rounded-lg bg-gray-50">
                    <p class="text-xs font-bold text-amber-800 uppercase">5. Quantidade de Animais Possuídos</p>
                    <p class="font-semibold text-gray-900 mt-1"><?php echo htmlspecialchars($client['animal_count_range'] ?: '-'); ?></p>
                </div>

                <div class="border border-gray-200 p-3 rounded-lg bg-gray-50">
                    <p class="text-xs font-bold text-amber-800 uppercase">6. Produção Diária de Leite</p>
                    <p class="font-semibold text-gray-900 mt-1"><?php echo htmlspecialchars($client['milk_production_range'] ?: '-'); ?></p>
                </div>
            </div>
        </div>

        <!-- Section 3: Intentions (if any) -->
        <?php if (!empty($intentions)): ?>
            <div class="mb-6">
                <h2 class="text-sm font-bold text-amber-900 uppercase tracking-wider bg-amber-50 p-2 rounded border-l-4 border-amber-600 mb-4">
                    3. Intenções Registradas
                </h2>

                <div class="space-y-2">
                    <?php foreach ($intentions as $intention): ?>
                        <div class="border p-3 rounded-lg text-sm <?php echo $intention['type'] === 'buy' ? 'bg-blue-50 border-blue-200' : 'bg-red-50 border-red-200'; ?>">
                            <div class="flex justify-between items-center">
                                <span class="font-bold uppercase text-xs <?php echo $intention['type'] === 'buy' ? 'text-blue-800' : 'text-red-800'; ?>">
                                    <?php echo $intention['type'] === 'buy' ? '🛒 Compra' : '💰 Venda'; ?> - <?php echo htmlspecialchars($intention['category_name'] ?? 'Geral'); ?>
                                </span>
                                <?php if ($intention['value'] > 0): ?>
                                    <span class="font-bold text-green-700 text-xs">R$ <?php echo number_format($intention['value'], 2, ',', '.'); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="text-gray-800 mt-1"><?php echo htmlspecialchars($intention['description']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="border-t border-gray-200 pt-4 mt-8 text-center text-xs text-gray-400">
            <p>CRM Vitor Müller - Documento gerado automaticamente para controle interno e envio.</p>
        </div>

    </div>

    <script>
        function downloadPDF() {
            const element = document.getElementById('pdfContent');
            const opt = {
                margin:       10,
                filename:     'ficha_cliente_<?php echo preg_replace('/[^a-z0-9]/i', '_', strtolower($client['name'])); ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
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
