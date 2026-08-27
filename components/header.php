<header class="flex justify-between items-center py-4 px-6 bg-white border-b-2 border-gray-200 shadow-sm z-10 w-full">
    <div class="flex items-center">
        <button id="sidebarBtn" class="text-gray-500 focus:outline-none md:hidden">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16">
                </path>
            </svg>
        </button>
        <span class="text-gray-800 text-xl font-semibold ml-4 md:ml-0">
            <?php echo isset($pageTitle) ? $pageTitle : 'Dashboard'; ?>
        </span>
    </div>

    <div class="flex items-center space-x-4">
        <a href="profile.php" title="Editar Perfil"
            class="flex items-center space-x-3 p-1.5 rounded-xl hover:bg-brand-50 transition duration-150 group cursor-pointer border border-transparent hover:border-brand-200">
            <div class="text-right hidden sm:block">
                <span class="block text-sm font-semibold text-gray-700 group-hover:text-brand-800 transition">
                    <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuário'); ?>
                </span>
                <span class="block text-xs text-gray-500 group-hover:text-brand-600 transition">
                    Editar Perfil
                </span>
            </div>
            <div class="relative">
                <img class="h-9 w-9 rounded-full object-cover border-2 border-brand-400 group-hover:border-brand-600 transition shadow-sm group-hover:scale-105"
                    src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'U'); ?>&background=B8860B&color=fff&bold=true"
                    alt="Avatar">
            </div>
        </a>
    </div>
</header>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const sidebar = document.getElementById('sidebar');
    const sidebarBtn = document.getElementById('sidebarBtn');

    if (sidebarBtn) {
        sidebarBtn.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
        });
    }

    // Global helper for delete confirmation
    function confirmDelete(event) {
        event.preventDefault();
        const form = event.target;

        Swal.fire({
            title: 'Tem certeza?',
            text: "Esta ação não poderá ser revertida!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444', // red-500
            cancelButtonColor: '#6b7280', // gray-500
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // If the form has a specific submit button with name/value, we might lose it on programmatic submit
                // But typically for deletion we just need the hidden identifiers.
                // Re-submitting the form programmatically bypasses the onsubmit handler? 
                // No, calling form.submit() bypasses onsubmit. Perfect.
                form.submit();
            }
        });
    }
</script>