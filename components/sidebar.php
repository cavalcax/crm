<?php
$currentUserId = $_SESSION['user_id'] ?? 1;
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/\\');
$sidebarPrecadastroUrl = $protocol . "://" . $host . $scriptDir . "/precadastro.php?ref=" . (function_exists('encryptUserId') ? encryptUserId($currentUserId) : $currentUserId);
?>
<aside
    class="bg-brand-900 text-white w-64 space-y-6 py-2 px-2 absolute inset-y-0 left-0 transform -translate-x-full md:relative md:translate-x-0 transition duration-200 ease-in-out z-20 flex flex-col justify-between"
    id="sidebar">
    <div>
        <div class="px-2 py-2 text-center border-b border-brand-800 pb-4 mb-2">
            <div class="bg-white p-1 rounded-lg shadow-md block">
                <img src="../assets/images/logo.png" alt="Vitor Müller" class="w-full h-24 object-contain mx-auto">
            </div>
        </div>

        <nav class="mt-6 space-y-1">
            <a href="index.php"
                class="block py-2.5 px-4 rounded transition duration-200 hover:bg-brand-800 hover:text-white <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-brand-800 font-bold' : ''; ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z">
                        </path>
                    </svg>
                    <span>Dashboard</span>
                </span>
            </a>
            <a href="clients.php"
                class="block py-2.5 px-4 rounded transition duration-200 hover:bg-brand-800 hover:text-white <?php echo in_array(basename($_SERVER['PHP_SELF']), ['clients.php', 'client-add.php', 'client-edit.php', 'client-details.php']) ? 'bg-brand-800 font-bold' : ''; ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                        </path>
                    </svg>
                    <span>Clientes</span>
                </span>
            </a>

            <a href="schedule.php"
                class="block py-2.5 px-4 rounded transition duration-200 hover:bg-brand-800 hover:text-white <?php echo in_array(basename($_SERVER['PHP_SELF']), ['schedule.php', 'schedule-add.php', 'schedule-edit.php']) ? 'bg-brand-800 font-bold' : ''; ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                        </path>
                    </svg>
                    <span>Agenda</span>
                </span>
            </a>
            <a href="categories.php"
                class="block py-2.5 px-4 rounded transition duration-200 hover:bg-brand-800 hover:text-white <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'bg-brand-800 font-bold' : ''; ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z">
                        </path>
                    </svg>
                    <span>Categorias</span>
                </span>
            </a>
            <a href="map-selector.php"
                class="block py-2.5 px-4 rounded transition duration-200 hover:bg-brand-800 hover:text-white <?php echo basename($_SERVER['PHP_SELF']) == 'map-selector.php' ? 'bg-brand-800 font-bold' : ''; ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7">
                        </path>
                    </svg>
                    <span>Mapa de Clientes</span>
                </span>
            </a>
            <a href="reports.php"
                class="block py-2.5 px-4 rounded transition duration-200 hover:bg-brand-800 hover:text-white <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'bg-brand-800 font-bold' : ''; ?>">
                <span class="flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                    <span>Relatórios</span>
                </span>
            </a>

            <!-- Copiar Link de Pré-Cadastro no Menu Principal -->
            <button onclick="copyPrecadastroLinkFromSidebar()" type="button"
                class="w-full text-left py-2.5 px-4 rounded transition duration-200 hover:bg-brand-800 hover:text-white text-brand-200">
                <span class="flex items-center space-x-2">
                    <svg class="w-5 h-5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3">
                        </path>
                    </svg>
                    <span>Link Pré-Cadastro</span>
                </span>
            </button>
            <a href="../logout.php"
            class="block py-2.5 px-4 rounded transition duration-200 hover:bg-brand-800 hover:text-white mb-4">
            <span class="flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                    </path>
                </svg>
                <span>Sair</span>
            </span>
        </a>
        </nav>
    </div>
</aside>

<script>
    function copyPrecadastroLinkFromSidebar() {
        const url = "<?php echo $sidebarPrecadastroUrl; ?>";
        const textToCopy = `Olá! 👋\n\nPara que eu possa entender o que você procura e apresentar as melhores oportunidades em gado de leite e máquinas agrícolas, por favor preencha o formulário no link abaixo:\n\n👇 Acesse o link:\n${url}\n\nÉ bem rápido e vai me ajudar a lhe oferecer um atendimento personalizado! 🤝`;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(textToCopy).then(() => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Link Copiado!',
                        text: 'A mensagem com o link de pré-cadastro foi copiada para a área de transferência.',
                        timer: 2500,
                        showConfirmButton: false
                    });
                } else {
                    alert("Link de Pré-Cadastro copiado!");
                }
            }).catch(() => {
                prompt("Copie o texto abaixo:", textToCopy);
            });
        } else {
            prompt("Copie o texto abaixo:", textToCopy);
        }
    }
</script>