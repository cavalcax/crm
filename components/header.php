<header
    class="flex justify-between items-center py-4 px-6 bg-white border-b-2 border-gray-200 shadow-sm z-10 w-full relative">
    <div class="flex items-center">
        <button id="sidebarBtn" class="text-gray-500 focus:outline-none md:hidden">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16">
                </path>
            </svg>
        </button>
    </div>

    <div class="flex items-center space-x-3 sm:space-x-4">
        <!-- Notification Bell Dropdown Button -->
        <div class="relative" id="notificationContainer">
            <button id="notifBellBtn" type="button" title="Notificações"
                class="relative p-2.5 text-gray-600 hover:text-brand-700 bg-gray-50 hover:bg-brand-50 rounded-xl transition duration-150 border border-gray-200 hover:border-brand-200 focus:outline-none cursor-pointer">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9">
                    </path>
                </svg>
                <!-- Unread Badge -->
                <span id="notifBadge"
                    class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-extrabold px-1.5 py-0.5 rounded-full min-w-[18px] text-center shadow-md animate-pulse">
                    0
                </span>
            </button>

            <!-- Notifications Dropdown -->
            <div id="notifDropdown"
                class="hidden fixed sm:absolute inset-x-3 sm:inset-x-auto sm:right-0 top-16 sm:top-full sm:mt-3 max-w-sm sm:w-96 mx-auto sm:mx-0 bg-white rounded-2xl shadow-2xl border border-gray-200 z-50 overflow-hidden transform transition-all duration-200">
                <!-- Dropdown Header -->
                <div
                    class="px-4 py-3 bg-gradient-to-r from-brand-900 to-brand-800 text-white flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9">
                            </path>
                        </svg>
                        <span class="font-bold text-sm">Notificações</span>
                        <span id="notifHeaderCount"
                            class="bg-brand-700/80 text-brand-100 text-xs px-2 py-0.5 rounded-full font-medium">0</span>
                    </div>
                    <button id="markAllReadBtn" type="button"
                        class="text-[11px] text-brand-200 hover:text-white font-medium underline cursor-pointer transition">
                        Marcar lidas
                    </button>
                </div>

                <!-- Web Push Status & Activation Banner -->
                <div id="pushPromptBanner"
                    class="hidden px-4 py-2.5 bg-amber-50 border-b border-amber-200 flex items-center justify-between text-xs">
                    <div class="flex items-center space-x-2 text-amber-800">
                        <span class="text-base">🔔</span>
                        <span>Ativar avisos no dispositivo</span>
                    </div>
                    <button id="enablePushBtn" type="button"
                        class="bg-amber-600 hover:bg-amber-700 text-white text-[11px] font-bold px-2.5 py-1 rounded-md shadow-sm transition cursor-pointer">
                        Ativar
                    </button>
                </div>

                <!-- Notification List Content -->
                <div id="notifList" class="max-h-80 overflow-y-auto divide-y divide-gray-100">
                    <div class="p-6 text-center text-gray-400 text-xs">
                        <svg class="w-8 h-8 mx-auto mb-2 text-gray-300 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Carregando avisos...
                    </div>
                </div>

                <!-- Dropdown Footer -->
                <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-100 flex items-center justify-between text-xs">
                    <a href="schedule.php"
                        class="text-brand-600 hover:text-brand-800 font-semibold flex items-center gap-1 transition">
                        Ver toda a agenda
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7">
                            </path>
                        </svg>
                    </a>
                    <button id="testPushBtn" type="button" title="Testar notificação"
                        class="text-[11px] text-gray-500 hover:text-brand-700 cursor-pointer">
                        Testar push
                    </button>
                </div>
            </div>
        </div>

        <!-- User Profile Pill -->
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

<!-- Universal Modal de Aguarde / Loading Overlay -->
<div id="globalLoadingOverlay"
    class="fixed inset-0 bg-slate-900/30 backdrop-blur-xs z-50 flex items-center justify-center transition-opacity duration-200 opacity-0 pointer-events-none">
    <div
        class="bg-white px-6 py-5 rounded-2xl shadow-2xl flex items-center space-x-4 border border-gray-100 max-w-xs sm:max-w-sm">
        <div class="w-8 h-8 border-4 border-brand-200 border-t-brand-600 rounded-full animate-spin flex-shrink-0"></div>
        <div>
            <p class="font-bold text-gray-800 text-sm" id="globalLoadingTitle">Carregando dados...</p>
            <p class="text-xs text-gray-500" id="globalLoadingSubtitle">Por favor, aguarde um instante</p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // ==========================================
    // GLOBAL LOADING OVERLAY CONTROLLER
    // ==========================================
    let globalLoadingTimeout = null;

    window.showLoading = function (title = "Carregando dados...", subtitle = "Por favor, aguarde um instante", maxWaitMs = 6000) {
        const overlay = document.getElementById('globalLoadingOverlay');
        if (!overlay) return;
        const titleEl = document.getElementById('globalLoadingTitle');
        const subEl = document.getElementById('globalLoadingSubtitle');
        if (titleEl) titleEl.textContent = title;
        if (subEl) subEl.textContent = subtitle;
        overlay.classList.remove('opacity-0', 'pointer-events-none');

        // Limpa timeout anterior se houver
        if (globalLoadingTimeout) clearTimeout(globalLoadingTimeout);

        // Trava de segurança: fecha automaticamente caso a página demore ou seja cancelada
        globalLoadingTimeout = setTimeout(() => {
            window.hideLoading();
        }, maxWaitMs);
    };

    window.hideLoading = function () {
        if (globalLoadingTimeout) clearTimeout(globalLoadingTimeout);
        const overlay = document.getElementById('globalLoadingOverlay');
        if (!overlay) return;
        overlay.classList.add('opacity-0', 'pointer-events-none');
    };

    // Auto-oculta o loading quando a página é restaurada do cache (bfcache) ou ao voltar no navegador
    window.addEventListener('pageshow', () => window.hideLoading());
    window.addEventListener('popstate', () => window.hideLoading());
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') window.hideLoading();
    });

    // Auto-attach loading indicator apenas a formulários de filtro reais
    document.addEventListener('DOMContentLoaded', () => {
        window.hideLoading();
        document.querySelectorAll('form[data-loading="true"]').forEach(form => {
            form.addEventListener('submit', () => {
                window.showLoading('Aplicando filtros...', 'Atualizando dados da lista');
            });
        });
    });

    // ==========================================
    // SIDEBAR & MOBILE CONTROLLER
    // ==========================================
    const sidebar = document.getElementById('sidebar');
    const sidebarBtn = document.getElementById('sidebarBtn');
    let sidebarBackdrop = document.getElementById('sidebarBackdrop');

    if (!sidebarBackdrop && sidebar) {
        sidebarBackdrop = document.createElement('div');
        sidebarBackdrop.id = 'sidebarBackdrop';
        sidebarBackdrop.className = 'fixed inset-0 bg-black/50 z-40 md:hidden hidden transition-opacity duration-200';
        document.body.appendChild(sidebarBackdrop);
        sidebarBackdrop.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            sidebarBackdrop.classList.add('hidden');
        });
    }

    if (sidebarBtn) {
        sidebarBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (sidebar) {
                sidebar.classList.toggle('-translate-x-full');
                if (sidebarBackdrop) {
                    sidebarBackdrop.classList.toggle('hidden', sidebar.classList.contains('-translate-x-full'));
                }
            }
        });
    }

    // Close mobile sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
        if (sidebar && !sidebar.classList.contains('-translate-x-full') && window.innerWidth < 768) {
            if (!sidebar.contains(e.target) && sidebarBtn && !sidebarBtn.contains(e.target)) {
                sidebar.classList.add('-translate-x-full');
                if (sidebarBackdrop) {
                    sidebarBackdrop.classList.add('hidden');
                }
            }
        }
    });

    // Close open menus/modals on popstate without trapping history
    window.addEventListener('popstate', function (event) {
        if (typeof Swal !== 'undefined' && Swal.isVisible()) {
            Swal.close();
        }
        if (sidebar && !sidebar.classList.contains('-translate-x-full') && window.innerWidth < 768) {
            sidebar.classList.add('-translate-x-full');
            if (sidebarBackdrop) {
                sidebarBackdrop.classList.add('hidden');
            }
        }
        if (typeof notifDropdown !== 'undefined' && notifDropdown && !notifDropdown.classList.contains('hidden')) {
            notifDropdown.classList.add('hidden');
        }
        window.hideLoading();
    });

    // Global helper for delete confirmation
    function confirmDelete(event) {
        event.preventDefault();
        const form = event.target;

        Swal.fire({
            title: 'Tem certeza?',
            text: "Esta ação não poderá ser revertida!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }

    // ==========================================
    // NOTIFICATIONS & PUSH SYSTEM JAVASCRIPT
    // ==========================================
    const notifContainer = document.getElementById('notificationContainer');
    const notifBellBtn = document.getElementById('notifBellBtn');
    const notifDropdown = document.getElementById('notifDropdown');
    const notifBadge = document.getElementById('notifBadge');
    const notifHeaderCount = document.getElementById('notifHeaderCount');
    const notifList = document.getElementById('notifList');
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    const pushPromptBanner = document.getElementById('pushPromptBanner');
    const enablePushBtn = document.getElementById('enablePushBtn');
    const testPushBtn = document.getElementById('testPushBtn');

    // Toggle Dropdown
    if (notifBellBtn && notifDropdown) {
        notifBellBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isHidden = notifDropdown.classList.contains('hidden');
            if (isHidden) {
                notifDropdown.classList.remove('hidden');
                loadNotifications();
            } else {
                notifDropdown.classList.add('hidden');
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (notifContainer && !notifContainer.contains(e.target)) {
                notifDropdown.classList.add('hidden');
            }
        });
    }

    // Convert Base64 URL for VAPID Key
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    // Get correct Service Worker Path
    function getSwUrl() {
        if (window.location.pathname.includes('/admin/')) {
            return '../service-worker.js';
        }
        return 'service-worker.js';
    }

    // Check Push Permission & Register SW
    async function checkPushSupport() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            if (pushPromptBanner) pushPromptBanner.classList.add('hidden');
            return;
        }

        try {
            const reg = await navigator.serviceWorker.register(getSwUrl());

            if (Notification.permission === 'granted') {
                if (pushPromptBanner) pushPromptBanner.classList.add('hidden');
                // Ensure subscription exists
                syncPushSubscription(reg).catch(e => console.warn('Silent sync push:', e));
            } else if (Notification.permission !== 'denied') {
                if (pushPromptBanner) pushPromptBanner.classList.remove('hidden');
            }
        } catch (err) {
            console.error('Erro ao verificar Service Worker:', err);
        }
    }

    // Subscribe to Web Push
    async function syncPushSubscription(reg, force = false) {
        try {
            if (!reg) {
                reg = await navigator.serviceWorker.ready;
            }
            let sub = await reg.pushManager.getSubscription();
            if (!sub) {
                const res = await fetch('api-notifications.php?action=vapid_public_key');
                const data = await res.json();
                if (data.success && data.publicKey) {
                    const convertedKey = urlBase64ToUint8Array(data.publicKey);
                    sub = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: convertedKey
                    });
                } else {
                    throw new Error(data.error || 'Não foi possível obter a chave VAPID.');
                }
            }

            if (sub) {
                const rawSub = sub.toJSON ? sub.toJSON() : {};
                const p256dh = rawSub.keys ? rawSub.keys.p256dh : '';
                const auth = rawSub.keys ? rawSub.keys.auth : '';
                const cacheKey = 'crm_push_synced_' + btoa(sub.endpoint).slice(0, 32);

                // Se já sincronizado no servidor e não é um envio forçado, evita requisição repetida
                if (!force && localStorage.getItem(cacheKey) === 'synced') {
                    return { success: true, cached: true };
                }

                const payload = {
                    action: 'subscribe_push',
                    endpoint: sub.endpoint,
                    keys: {
                        p256dh: p256dh,
                        auth: auth
                    }
                };

                // Fallback manual key conversion
                if (!payload.keys.p256dh && sub.getKey) {
                    const p2Key = sub.getKey('p256dh');
                    const authKey = sub.getKey('auth');
                    if (p2Key) {
                        payload.keys.p256dh = btoa(String.fromCharCode.apply(null, new Uint8Array(p2Key)));
                    }
                    if (authKey) {
                        payload.keys.auth = btoa(String.fromCharCode.apply(null, new Uint8Array(authKey)));
                    }
                }

                const postRes = await fetch('api-notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const postJson = await postRes.json();
                if (postJson && postJson.success) {
                    localStorage.setItem(cacheKey, 'synced');
                }
                return postJson;
            }
        } catch (e) {
            console.error('Erro na Inscrição Web Push:', e);
            throw e;
        }
    }

    // Expose helpers globally
    window.syncPushSubscription = syncPushSubscription;
    window.getSwUrl = getSwUrl;

    window.unsubscribePushSubscription = async function () {
        try {
            if ('serviceWorker' in navigator && 'PushManager' in window) {
                const reg = await navigator.serviceWorker.ready;
                const sub = await reg.pushManager.getSubscription();
                if (sub) {
                    const cacheKey = 'crm_push_synced_' + btoa(sub.endpoint).slice(0, 32);
                    localStorage.removeItem(cacheKey);
                    await fetch('api-notifications.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'unsubscribe_push',
                            endpoint: sub.endpoint
                        })
                    }).catch(err => console.warn(err));
                    await sub.unsubscribe().catch(err => console.warn(err));
                }
            }
        } catch (e) {
            console.warn('Erro ao cancelar inscrição push:', e);
        }
    };

    if (enablePushBtn) {
        enablePushBtn.addEventListener('click', async () => {
            if (!('Notification' in window) || !('serviceWorker' in navigator)) {
                Swal.fire('Não suportado', 'Seu navegador ou conexão atual não suporta notificações Push.', 'info');
                return;
            }

            try {
                const permission = await Notification.requestPermission();
                if (permission === 'granted') {
                    if (pushPromptBanner) pushPromptBanner.classList.add('hidden');
                    const reg = await navigator.serviceWorker.register(getSwUrl());
                    await navigator.serviceWorker.ready;
                    const result = await syncPushSubscription(reg);

                    if (result && result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Notificações Ativadas!',
                            text: 'Seu dispositivo foi inscrito com sucesso e receberá os lembretes de reunião.',
                            timer: 3000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'success',
                            title: 'Notificações Ativadas!',
                            text: 'Você agora receberá avisos no seu dispositivo.',
                            timer: 3000,
                            showConfirmButton: false
                        });
                    }
                } else if (permission === 'denied') {
                    Swal.fire('Permissão Negada', 'Você bloqueou as notificações no navegador. Altere nas configurações do seu navegador para receber avisos.', 'warning');
                }
            } catch (err) {
                console.error('Erro ao ativar push:', err);
                Swal.fire('Atenção', err.message || 'Não foi possível registrar o dispositivo no servidor.', 'warning');
            }
        });
    }

    if (testPushBtn) {
        testPushBtn.addEventListener('click', async () => {
            try {
                const res = await fetch('api-notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'test_notification' })
                });
                const data = await res.json();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Notificação Enviada!',
                        text: 'Verifique a lista de notificações e a notificação do seu navegador.',
                        timer: 2500,
                        showConfirmButton: false
                    });
                    loadNotifications();
                }
            } catch (e) {
                console.error(e);
            }
        });
    }

    // Load Notifications from API
    async function loadNotifications() {
        try {
            const res = await fetch('api-notifications.php?action=list');
            const data = await res.json();

            if (!data.success) return;

            const count = data.unread_count || 0;
            if (notifBadge) {
                if (count > 0) {
                    notifBadge.textContent = count > 99 ? '99+' : count;
                    notifBadge.classList.remove('hidden');
                } else {
                    notifBadge.classList.add('hidden');
                }
            }

            if (notifHeaderCount) {
                notifHeaderCount.textContent = count + ' ' + (count === 1 ? 'não lida' : 'não lidas');
            }

            if (notifList) {
                if (!data.notifications || data.notifications.length === 0) {
                    notifList.innerHTML = `
                        <div class="p-8 text-center text-gray-400">
                            <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <p class="text-xs font-medium text-gray-500">Nenhuma notificação no momento</p>
                            <p class="text-[11px] text-gray-400 mt-0.5">Seus lembretes de reuniões aparecerão aqui.</p>
                        </div>
                    `;
                    return;
                }

                let html = '';
                data.notifications.forEach(item => {
                    const isUnread = (parseInt(item.is_read) === 0);
                    const bgClass = isUnread ? 'bg-amber-50/60 hover:bg-amber-100/60 font-medium' : 'bg-white hover:bg-gray-50';
                    const iconBg = isUnread ? 'bg-brand-500 text-white' : 'bg-gray-200 text-gray-600';
                    const formattedMessage = (item.message || '').replace(/\n/g, '<br>');

                    html += `
                        <div class="p-3.5 ${bgClass} transition flex items-start space-x-3 cursor-pointer group notif-item" data-id="${item.id}" data-link="${item.link || 'schedule.php'}">
                            <div class="w-8 h-8 rounded-full ${iconBg} flex items-center justify-center flex-shrink-0 mt-0.5 shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-1 mb-1">
                                    <h4 class="text-xs font-bold text-gray-900 truncate group-hover:text-brand-800">${escapeHtml(item.title)}</h4>
                                    <span class="text-[10px] text-gray-400 flex-shrink-0 font-normal">${item.time_ago}</span>
                                </div>
                                <div class="text-[11px] text-gray-600 leading-snug break-words">
                                    ${formattedMessage}
                                </div>
                            </div>
                            ${isUnread ? '<span class="w-2 h-2 bg-brand-600 rounded-full flex-shrink-0 mt-2"></span>' : ''}
                        </div>
                    `;
                });

                notifList.innerHTML = html;

                // Bind click events on notification items
                document.querySelectorAll('.notif-item').forEach(el => {
                    el.addEventListener('click', async () => {
                        const id = el.getAttribute('data-id');
                        const link = el.getAttribute('data-link');
                        try {
                            await fetch('api-notifications.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ action: 'mark_read', id: id })
                            });
                        } catch (e) { }
                        window.location.href = link;
                    });
                });
            }
        } catch (e) {
            console.error('Erro ao carregar notificações:', e);
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            try {
                await fetch('api-notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'mark_all_read' })
                });
                loadNotifications();
            } catch (e) {
                console.error(e);
            }
        });
    }

    // Initial check and periodic polling every 45s
    document.addEventListener('DOMContentLoaded', () => {
        loadNotifications();
        checkPushSupport();
        setInterval(loadNotifications, 45000);
    });
</script>