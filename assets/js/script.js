/* =====================================================================
   Bu Ani Shop - App JS
   Vanilla JavaScript glue: navigation, theme, modals, toasts,
   currency inputs, live filtering, and small chart helpers.
   ===================================================================== */
(function () {
    'use strict';

    /* ---------------- Theme ---------------- */
    function setTheme(mode) {
        document.documentElement.classList.toggle('dark', mode === 'dark');
        localStorage.setItem('theme', mode);
    }
    function toggleTheme() {
        setTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
        window.dispatchEvent(new CustomEvent('themechange'));
    }

    /* ---------------- Sidebar ---------------- */
    function initSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        const backdrop = document.getElementById('sidebarBackdrop');

        const setCollapsed = (collapsed) => {
            sidebar.classList.toggle('collapsed', collapsed);
            document.body.classList.toggle('sidebar-collapsed', collapsed);
        };

        const collapseBtn = document.getElementById('sidebarCollapse');
        if (collapseBtn) {
            if (localStorage.getItem('sidebar') === 'collapsed' && window.innerWidth > 1024) {
                setCollapsed(true);
            }
            collapseBtn.addEventListener('click', () => {
                const collapsed = !sidebar.classList.contains('collapsed');
                setCollapsed(collapsed);
                localStorage.setItem('sidebar', collapsed ? 'collapsed' : 'open');
            });
        }

        const closeMobile = () => {
            sidebar.classList.remove('mobile-open');
            if (backdrop) backdrop.classList.remove('show');
        };
        const openMobile = () => {
            sidebar.classList.add('mobile-open');
            if (backdrop) backdrop.classList.add('show');
        };

        const menuBtn = document.getElementById('topbarMenu');
        if (menuBtn) menuBtn.addEventListener('click', () => {
            sidebar.classList.contains('mobile-open') ? closeMobile() : openMobile();
        });
        if (backdrop) backdrop.addEventListener('click', closeMobile);

        // Close the drawer when a navigation link is tapped on mobile
        sidebar.querySelectorAll('.nav-item, .nav-action').forEach(a => a.addEventListener('click', closeMobile));

        // Leaving mobile layout: make sure the drawer is dismissed
        window.addEventListener('resize', () => {
            if (window.innerWidth > 1024) {
                closeMobile();
            } else {
                // Collapse is a desktop-only feature; never shrink the mobile drawer
                setCollapsed(false);
                closeMobile();
            }
        });
    }

    /* ---------------- Toasts ---------------- */
    function toast(type, message, title) {
        let stack = document.querySelector('.toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            document.body.appendChild(stack);
        }
        const icons = { success: 'check_circle', error: 'error', warning: 'warning', info: 'info' };
        const titles = { success: 'Success', error: 'Something went wrong', warning: 'Heads up', info: 'Info' };
        const el = document.createElement('div');
        el.className = 'toast ' + type;
        el.innerHTML =
            '<span class="material-symbols-rounded">' + (icons[type] || 'info') + '</span>' +
            '<div class="toast-body"><strong>' + escapeHtml(title || titles[type]) + '</strong>' +
            '<p>' + escapeHtml(message) + '</p></div>';
        stack.appendChild(el);
        setTimeout(() => {
            el.style.transition = 'opacity .3s ease, transform .3s ease';
            el.style.opacity = '0';
            el.style.transform = 'translateX(24px)';
            setTimeout(() => el.remove(), 300);
        }, 3800);
    }

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    /* ---------------- Modals ---------------- */
    function openModal(id, data) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
            fillSubCategories(form);
            if (data) {
                Object.keys(data).forEach(key => {
                    const input = form.querySelector('[name="' + key + '"]');
                    if (!input) return;
                    if (input.classList.contains('rupiah-input')) {
                        input.value = data[key] ? formatNumberDisplay(data[key]) : '';
                    } else if (input.type === 'date') {
                        input.value = data[key] ? String(data[key]).slice(0, 10) : '';
                    } else if (input.type === 'datetime-local') {
                        input.value = data[key] ? String(data[key]).replace(' ', 'T').slice(0, 16) : '';
                    } else {
                        input.value = data[key] == null ? '' : data[key];
                    }
                });
                // Let dependent selects rebuild their options, then re-apply values
                form.querySelectorAll('select').forEach(sel => sel.dispatchEvent(new Event('change')));
                Object.keys(data).forEach(key => {
                    const input = form.querySelector('[name="' + key + '"]');
                    if (input && input.tagName === 'SELECT' && data[key] != null) input.value = data[key];
                });
            }
        }
    }
    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) modal.classList.remove('open');
        document.body.style.overflow = '';
    }
    function initModalDismiss() {
        document.addEventListener('click', e => {
            if (e.target.classList.contains('modal-overlay')) closeModal(e.target.id);
            if (e.target.closest('[data-close-modal]')) {
                const id = e.target.closest('[data-close-modal]').dataset.closeModal;
                closeModal(id);
            }
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id));
            }
        });
    }

    /* ---------------- Confirm helpers ---------------- */

    function confirmAction(url, message) {
        Swal.fire({
            title: 'Please confirm',
            text: message || 'Continue with this action?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, continue',
            cancelButtonText: 'Cancel'
        });
    }

    /* ---------------- Currency ---------------- */
    function groupDigits(digits) {
        return String(digits).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    function normalizeDigits(value) {
        let digits = String(value == null ? '' : value).replace(/[^0-9]/g, '');
        digits = digits.replace(/^0+(?=\d)/, ''); // drop leading zeros, keep a lone 0
        return digits;
    }
    function formatNumberDisplay(value) {
        const clean = normalizeDigits(value);
        return clean === '' ? '' : groupDigits(clean);
    }
    function cleanNumber(value) {
        return parseInt(String(value).replace(/[^0-9]/g, ''), 10) || 0;
    }
    function maxOf(input) {
        const m = input.dataset.max;
        return (m === undefined || m === '') ? null : parseInt(m, 10);
    }
    function minOf(input) {
        const m = input.dataset.min;
        return (m === undefined || m === '') ? 0 : parseInt(m, 10);
    }
    function enforceMax(input) {
        const max = maxOf(input);
        if (max === null) return;
        if (cleanNumber(input.value) > max) {
            input.value = formatNumberDisplay(max);
            try { input.setSelectionRange(input.value.length, input.value.length); } catch (err) { /* noop */ }
        }
    }
    function setRupiahValue(input, n) {
        const min = minOf(input), max = maxOf(input);
        if (n < min) n = min;
        if (max !== null && n > max) n = max;
        input.value = n > 0 ? formatNumberDisplay(n) : (n === 0 ? '0' : '');
    }
    function initCurrencyInputs() {
        document.body.addEventListener('input', e => {
            if (!e.target.classList.contains('rupiah-input')) return;
            const input = e.target;
            const caret = input.selectionStart;
            const before = input.value.length;
            const digits = normalizeDigits(input.value);
            input.value = digits ? groupDigits(digits) : '';
            const after = input.value.length;
            const pos = caret + (after - before);
            try { input.setSelectionRange(pos, pos); } catch (err) { /* noop */ }
            enforceMax(input);
        });
        // Stepper buttons: increase (+) / decrease (−)
        document.body.addEventListener('click', e => {
            const btn = e.target.closest('.stepper-btn');
            if (!btn) return;
            e.preventDefault();
            const wrap = btn.closest('.stepper');
            const input = wrap ? wrap.querySelector('.rupiah-input') : null;
            if (!input) return;
            const step = parseInt(btn.dataset.step, 10) || 1;
            setRupiahValue(input, cleanNumber(input.value) + step);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }

    /* ---------------- Dependent sub-category dropdown ---------------- */
    function fillSubCategories(form) {
        const catSel = form.querySelector('select[name="kategori"]');
        const subSel = form.querySelector('select[name="sub_kategori"]');
        if (!catSel || !subSel) return;
        const map = window.SUB_CATEGORIES || {};
        const list = map[catSel.value] || [];
        const current = subSel.value;
        subSel.innerHTML = '';
        list.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s;
            opt.textContent = s;
            subSel.appendChild(opt);
        });
        if (list.indexOf(current) !== -1) subSel.value = current;
    }
    function initSubCategoryDropdowns() {
        document.querySelectorAll('select[name="kategori"]').forEach(catSel => {
            const form = catSel.closest('form');
            if (!form || !form.querySelector('select[name="sub_kategori"]')) return;
            fillSubCategories(form);
            catSel.addEventListener('change', () => fillSubCategories(form));
        });
    }

    /* ---------------- Live filter (scope based) ---------------- */
    function initListFilters() {
        document.querySelectorAll('[data-search-scope]').forEach(input => {
            input.addEventListener('input', () => { getState(input.dataset.searchScope).page = 1; applyScopeFilter(input.dataset.searchScope); });
        });
        document.querySelectorAll('[data-cat-scope]').forEach(group => {
            group.querySelectorAll('button').forEach(btn => {
                btn.addEventListener('click', () => {
                    group.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    getState(group.dataset.catScope).page = 1;
                    applyScopeFilter(group.dataset.catScope);
                });
            });
        });
        document.querySelectorAll('[data-status-scope]').forEach(sel => {
            sel.addEventListener('change', () => { getState(sel.dataset.statusScope).page = 1; applyScopeFilter(sel.dataset.statusScope); });
        });
        document.querySelectorAll('[data-scope]').forEach(sc => applyScopeFilter(sc.dataset.scope));

        // Re-apply when the global search is used
        const global = document.getElementById('globalSearch');
        if (global) global.addEventListener('input', () => {
            document.querySelectorAll('[data-scope]').forEach(sc => { getState(sc.dataset.scope).page = 1; applyScopeFilter(sc.dataset.scope); });
        });
    }

    function applyScopeFilter(scope) {
        const containers = document.querySelectorAll('[data-scope="' + scope + '"]');
        const termEl = document.querySelector('[data-search-scope="' + scope + '"]');
        const global = document.getElementById('globalSearch');
        let term = termEl ? termEl.value.trim().toLowerCase() : '';
        if (global && global.value.trim()) term = (term + ' ' + global.value.trim().toLowerCase()).trim();
        const catBtn = document.querySelector('[data-cat-scope="' + scope + '"] button.active');
        const cat = catBtn ? catBtn.dataset.cat : 'all';
        const statusEl = document.querySelector('[data-status-scope="' + scope + '"]');
        const status = statusEl ? statusEl.value : 'all';

        containers.forEach(container => {
            container.querySelectorAll('[data-row]').forEach(row => {
                const hay = (row.dataset.search || '').toLowerCase();
                const okTerm = !term || term.split(/\s+/).every(t => hay.indexOf(t) !== -1);
                const okCat = cat === 'all' || row.dataset.cat === cat;
                const okStatus = status === 'all' || row.dataset.status === status;
                row.dataset.match = (okTerm && okCat && okStatus) ? '1' : '0';
            });
        });
        renderPagination(scope);
    }

    /* ---------------- Pagination (scope based) ---------------- */
    const pagerState = {};

    function pagerFor(scope) {
        return document.querySelector('[data-pager-scope="' + scope + '"]');
    }

    function getState(scope) {
        if (!pagerState[scope]) {
            const pager = pagerFor(scope);
            const sizeEl = pager && pager.querySelector('[data-pager-size]');
            pagerState[scope] = { page: 1, size: sizeEl ? (parseInt(sizeEl.value, 10) || 25) : 100000 };
        }
        return pagerState[scope];
    }

    function renderPagination(scope) {
        const pager = pagerFor(scope);
        const containers = Array.from(document.querySelectorAll('[data-scope="' + scope + '"]'));
        // Each view (list/grid) renders the same items; paginate each one on its own set
        // so the reported total is not doubled.
        const matchedPerContainer = containers.map(c =>
            Array.from(c.querySelectorAll('[data-row]')).filter(r => r.dataset.match !== '0')
        );
        const total = matchedPerContainer.reduce((max, arr) => Math.max(max, arr.length), 0);

        if (!pager) {
            containers.forEach(container => {
                container.querySelectorAll('[data-row]').forEach(r => { r.style.display = r.dataset.match === '0' ? 'none' : ''; });
            });
        } else {
            const st = getState(scope);
            const pages = Math.max(1, Math.ceil(total / st.size));
            st.page = Math.min(Math.max(1, st.page), pages);
            const start = (st.page - 1) * st.size;
            const end = start + st.size;
            containers.forEach((container, ci) => {
                container.querySelectorAll('[data-row]').forEach(r => { r.style.display = 'none'; });
                matchedPerContainer[ci].forEach((r, i) => { if (i >= start && i < end) r.style.display = ''; });
            });
            updatePagerUI(pager, st.page, pages, total, start, end);
        }

        const counter = document.querySelector('[data-count-scope="' + scope + '"]');
        if (counter) counter.textContent = total;
        document.querySelectorAll('[data-empty-scope="' + scope + '"]').forEach(el => {
            el.style.display = total === 0 ? '' : 'none';
        });
    }

    function updatePagerUI(pager, page, pages, total, start, end) {
        const from = total === 0 ? 0 : start + 1;
        const to = Math.min(end, total);
        const set = (sel, val) => { const el = pager.querySelector(sel); if (el) el.textContent = val; };
        set('[data-pager-from]', from);
        set('[data-pager-to]', to);
        set('[data-pager-total]', total);

        const jump = pager.querySelector('[data-pager-jump]');
        if (jump) { jump.max = pages; jump.value = page; }
        const prev = pager.querySelector('[data-pager-prev]');
        const next = pager.querySelector('[data-pager-next]');
        if (prev) prev.disabled = page <= 1;
        if (next) next.disabled = page >= pages;

        const holder = pager.querySelector('[data-pager-pages]');
        if (!holder) return;
        holder.innerHTML = '';
        const addPage = (n, label, active, dots) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'pager-page' + (active ? ' active' : '') + (dots ? ' dots' : '');
            b.textContent = label;
            if (!dots) b.dataset.page = n;
            holder.appendChild(b);
        };
        const win = 2;
        addPage(1, '1', page === 1);
        if (page - win > 2) addPage(0, '…', false, true);
        for (let p = Math.max(2, page - win); p <= Math.min(pages - 1, page + win); p++) addPage(p, p, p === page);
        if (page + win < pages - 1) addPage(0, '…', false, true);
        if (pages > 1) addPage(pages, pages, page === pages);
    }

    function initPagers() {
        document.querySelectorAll('[data-pager-scope]').forEach(pager => {
            const scope = pager.dataset.pagerScope;
            const sizeEl = pager.querySelector('[data-pager-size]');
            if (sizeEl) sizeEl.addEventListener('change', () => {
                getState(scope).size = parseInt(sizeEl.value, 10) || 25;
                getState(scope).page = 1;
                renderPagination(scope);
            });
            const prev = pager.querySelector('[data-pager-prev]');
            if (prev) prev.addEventListener('click', () => { getState(scope).page--; renderPagination(scope); });
            const next = pager.querySelector('[data-pager-next]');
            if (next) next.addEventListener('click', () => { getState(scope).page++; renderPagination(scope); });
            const go = pager.querySelector('[data-pager-go]');
            const jump = pager.querySelector('[data-pager-jump]');
            const doJump = () => {
                if (!jump) return;
                const v = parseInt(jump.value, 10);
                if (v) { getState(scope).page = v; renderPagination(scope); }
            };
            if (go) go.addEventListener('click', doJump);
            if (jump) jump.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); doJump(); } });
            pager.addEventListener('click', e => {
                const btn = e.target.closest('.pager-page');
                if (btn && btn.dataset.page) { getState(scope).page = parseInt(btn.dataset.page, 10); renderPagination(scope); }
            });
        });
    }

    /* ---------------- View toggle (list / grid) ---------------- */
    function applyInventoryView(view) {
        const listEl = document.querySelector('[data-view="list"]');
        const gridEl = document.querySelector('[data-view="grid"]');
        if (listEl) listEl.style.display = view === 'list' ? '' : 'none';
        if (gridEl) gridEl.style.display = view === 'grid' ? '' : 'none';
        document.querySelectorAll('[data-view-toggle]').forEach(b => b.classList.toggle('active', b.dataset.viewToggle === view));
        localStorage.setItem('invView', view);
    }

    function initViewToggles() {
        const buttons = document.querySelectorAll('[data-view-toggle]');
        if (!buttons.length) return;
        buttons.forEach(btn => btn.addEventListener('click', () => applyInventoryView(btn.dataset.viewToggle)));
        applyInventoryView(localStorage.getItem('invView') === 'grid' ? 'grid' : 'list');
    }

    /* ---------------- Theme-aware chart colors ---------------- */
    function chartColors() {
        const dark = document.documentElement.classList.contains('dark');
        return {
            text: dark ? '#94a3b8' : '#64748b',
            grid: dark ? '#22304d' : '#eef1f7',
            tooltipBg: dark ? '#111a2e' : '#0f172a',
            palette: {
                indigo: '#6366f1', green: '#10b981', red: '#ef4444',
                amber: '#f59e0b', blue: '#3b82f6', violet: '#8b5cf6',
                slate: '#94a3b8', teal: '#14b8a6', pink: '#ec4899'
            }
        };
    }

    /* ---------------- Boot ---------------- */
    document.addEventListener('DOMContentLoaded', function () {
        initSidebar();
        initCurrencyInputs();
        initSubCategoryDropdowns();
        initPagers();
        initListFilters();
        initViewToggles();

        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) themeToggle.addEventListener('click', toggleTheme);
        const sidebarTheme = document.getElementById('sidebarTheme');
        if (sidebarTheme) sidebarTheme.addEventListener('click', toggleTheme);

        const userChip = document.getElementById('userChip');
        if (userChip) {
            userChip.addEventListener('click', e => {
                if (e.target.closest('.user-menu')) return;
                userChip.classList.toggle('open');
            });
            document.addEventListener('click', e => {
                if (!userChip.contains(e.target)) userChip.classList.remove('open');
            });
        }

        // Modals: overlay click, close buttons, escape
        initModalDismiss();

        // Flash message from PHP
        if (window.__FLASH__) {
            toast(window.__FLASH__.type, window.__FLASH__.message);
            window.__FLASH__ = null;
        }
    });

    /* ---------------- Public API ---------------- */
    window.openModal = openModal;
    window.closeModal = closeModal;
    window.toast = toast;
    window.formatNumberDisplay = formatNumberDisplay;
    window.cleanNumber = cleanNumber;
    window.confirmDelete = function (url, msg) {
        Swal.fire({
            title: 'Delete this item?',
            text: msg || 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel'
        }).then(res => { if (res.isConfirmed) window.location.href = url; });
    };
    window.confirmAction = function (url, msg) {
        Swal.fire({
            title: 'Please confirm',
            text: msg || 'Continue?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4f46e5',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Yes, continue',
            cancelButtonText: 'Cancel'
        }).then(res => { if (res.isConfirmed) window.location.href = url; });
    };
    window.chartColors = chartColors;
    window.toggleTheme = toggleTheme;
}());