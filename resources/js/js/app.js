import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initDropdowns();
    initMobileNav();
    initDataTables();
    initStickyTables();
    initDateFilterClears();
    initDateFilterValidation();
});

function initDropdowns() {
    const dropdowns = document.querySelectorAll('[data-dropdown]');

    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('[data-dropdown-toggle]');
        if (!toggle) return;

        let hoverTimeout = null;

        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            closeAllDropdowns(dropdown);
            dropdown.classList.toggle('open');
        });

        dropdown.addEventListener('mouseenter', () => {
            clearTimeout(hoverTimeout);
            closeAllDropdowns(dropdown);
            dropdown.classList.add('open');
        });

        dropdown.addEventListener('mouseleave', () => {
            hoverTimeout = setTimeout(() => {
                dropdown.classList.remove('open');
            }, 150);
        });
    });

    document.addEventListener('click', () => {
        closeAllDropdowns();
    });
}

function closeAllDropdowns(except = null) {
    document.querySelectorAll('[data-dropdown].open').forEach(dropdown => {
        if (dropdown !== except) {
            dropdown.classList.remove('open');
        }
    });
}

function initMobileNav() {
    const root = document.querySelector('[data-mobile-nav-root]');
    const toggleBtn = document.querySelector('[data-mobile-nav-toggle]');
    if (!root || !toggleBtn) return;

    const backdrop = root.querySelector('[data-mobile-nav-backdrop]');
    const closeBtn = root.querySelector('[data-mobile-nav-close]');
    const iconOpen = toggleBtn.querySelector('[data-mobile-nav-icon-open]');
    const iconClose = toggleBtn.querySelector('[data-mobile-nav-icon-close]');
    const panel = document.getElementById('mobile-nav-panel');

    let previousBodyOverflow = '';

    const setOpen = (open) => {
        root.classList.toggle('hidden', !open);
        root.setAttribute('aria-hidden', open ? 'false' : 'true');
        toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (iconOpen && iconClose) {
            iconOpen.classList.toggle('hidden', open);
            iconClose.classList.toggle('hidden', !open);
        }

        if (open) {
            previousBodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            closeAllDropdowns();
            requestAnimationFrame(() => {
                panel?.querySelector('a[href]')?.focus({ preventScroll: true });
            });
        } else {
            document.body.style.overflow = previousBodyOverflow || '';
            toggleBtn.focus({ preventScroll: true });
        }
    };

    toggleBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const willOpen = root.classList.contains('hidden');
        setOpen(willOpen);
    });

    backdrop?.addEventListener('click', () => setOpen(false));
    closeBtn?.addEventListener('click', () => setOpen(false));

    root.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !root.classList.contains('hidden')) {
            e.preventDefault();
            setOpen(false);
        }
    });

    const desktopMq = window.matchMedia('(min-width: 1024px)');
    const onViewportChange = () => {
        if (desktopMq.matches && !root.classList.contains('hidden')) {
            setOpen(false);
        }
    };
    desktopMq.addEventListener('change', onViewportChange);
}

function initDataTables() {
    document.querySelectorAll('[data-table]').forEach(container => {
        const table = container.querySelector('table');
        if (!table) return;

        const headers = table.querySelectorAll('[data-sort-key]');
        const filterInputs = table.querySelectorAll('.table-filter');

        headers.forEach(th => {
            th.addEventListener('click', () => {
                const key = th.dataset.sortKey;
                const currentDir = th.dataset.sortDir || '';
                const newDir = currentDir === 'asc' ? 'desc' : 'asc';

                headers.forEach(h => {
                    h.dataset.sortDir = '';
                    const icon = h.querySelector('[data-sort-icon]');
                    if (icon) {
                        icon.classList.remove('text-primary');
                        icon.classList.add('text-muted-foreground');
                    }
                });

                th.dataset.sortDir = newDir;
                const icon = th.querySelector('[data-sort-icon]');
                if (icon) {
                    icon.classList.remove('text-muted-foreground');
                    icon.classList.add('text-primary');
                }

                sortTable(table, key, newDir);
            });
        });

        filterInputs.forEach(input => {
            const event = input.tagName === 'SELECT' ? 'change' : 'input';
            input.addEventListener(event, () => filterTable(table));
        });
    });
}

function sortTable(table, key, direction) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('[data-row]'));

    rows.sort((a, b) => {
        let valA = a.dataset[`col${capitalize(key)}`] || a.getAttribute(`data-col-${key}`) || '';
        let valB = b.dataset[`col${capitalize(key)}`] || b.getAttribute(`data-col-${key}`) || '';

        const numA = parseFloat(valA.replace(/\s/g, ''));
        const numB = parseFloat(valB.replace(/\s/g, ''));

        if (!isNaN(numA) && !isNaN(numB)) {
            return direction === 'asc' ? numA - numB : numB - numA;
        }

        valA = valA.toLowerCase();
        valB = valB.toLowerCase();

        if (valA < valB) return direction === 'asc' ? -1 : 1;
        if (valA > valB) return direction === 'asc' ? 1 : -1;
        return 0;
    });

    rows.forEach(row => tbody.appendChild(row));
}

function filterTable(table) {
    const filters = {};
    table.querySelectorAll('.table-filter').forEach(input => {
        const key = input.dataset.filterKey;
        const val = input.value.toLowerCase().trim();
        if (val) filters[key] = val;
    });

    table.querySelectorAll('tbody [data-row]').forEach(row => {
        let visible = true;

        for (const [key, filterVal] of Object.entries(filters)) {
            const cellVal = (row.getAttribute(`data-col-${key}`) || '').toLowerCase();
            if (!cellVal.includes(filterVal)) {
                visible = false;
                break;
            }
        }

        row.style.display = visible ? '' : 'none';
    });
}

/**
 * Серверные фильтры в форме таблицы: select — сразу по change,
 * текстовые поля — через debounce после ввода (без кнопки поиска).
 */
function bindFilterFormSubmits(form, { debounceMs = 350 } = {}) {
    if (!form) return;

    form.querySelectorAll('select.table-filter, select.table-filter-select').forEach((select) => {
        select.addEventListener('change', () => form.requestSubmit());
    });

    form.querySelectorAll('input.table-filter').forEach((input) => {
        let timeoutId;
        const schedule = () => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => form.requestSubmit(), debounceMs);
        };
        input.addEventListener('input', schedule);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(timeoutId);
                form.requestSubmit();
            }
        });
    });
}

window.superpartBindFilterForm = bindFilterFormSubmits;

function capitalize(str) {
    return str.replace(/(^|-)(\w)/g, (_, sep, char) =>
        (sep === '-' ? '' : '') + char.toUpperCase()
    );
}

function initTheme() {
    const root = document.documentElement;
    const toggle = document.getElementById('theme-toggle');

    const icons = {
        dark: toggle?.querySelector('[data-theme-icon="dark"]') ?? null,
        light: toggle?.querySelector('[data-theme-icon="light"]') ?? null,
    };

    async function persistTheme(mode) {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!token) return;

        try {
            await window.axios.patch('/settings/theme', { theme: mode }, {
                headers: {
                    'X-CSRF-TOKEN': token,
                },
            });
        } catch (_) {
            // Silent fail: local theme still works even if request fails.
        }
    }

    function applyTheme(mode) {
        const isDark = mode === 'dark';
        root.classList.toggle('dark', isDark);
        localStorage.setItem('theme', isDark ? 'dark' : 'light');

        if (icons.dark && icons.light) {
            icons.dark.classList.toggle('hidden', !isDark);
            icons.light.classList.toggle('hidden', isDark);
        }
    }

    const saved = localStorage.getItem('theme');
    applyTheme(saved === 'light' ? 'light' : 'dark');

    window.superpartSetTheme = async (nextTheme) => {
        if (!['dark', 'light'].includes(nextTheme)) return;
        applyTheme(nextTheme);
        await persistTheme(nextTheme);
    };

    toggle?.addEventListener('click', async () => {
        const isDark = root.classList.contains('dark');
        const nextTheme = isDark ? 'light' : 'dark';
        await window.superpartSetTheme(nextTheme);
    });

    window.addEventListener('superpart:set-theme', async (event) => {
        const nextTheme = event.detail?.theme;
        await window.superpartSetTheme(nextTheme);
    });
}

function initDateFilterClears() {
    document.querySelectorAll('.date-clear-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetName = btn.dataset.target;
            const input = btn.closest('form')?.querySelector(`[name="${targetName}"]`);
            if (input) {
                input.value = '';
                btn.remove();
            }
        });
    });
}

function initDateFilterValidation() {
    document.querySelectorAll('[data-date-filter-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const pairs = [
                ['date_from', 'date_to', 'Дата «по» не может быть раньше даты «от».'],
                ['closed_from', 'closed_to', '«Закрыто по» не может быть раньше «Закрыто от».'],
            ];

            for (const [fromName, toName, message] of pairs) {
                const from = form.querySelector(`[name="${fromName}"]`);
                const to = form.querySelector(`[name="${toName}"]`);

                if (!from?.value || !to?.value) {
                    continue;
                }

                if (from.value > to.value) {
                    event.preventDefault();
                    alert(message);
                    return;
                }
            }
        });
    });
}

function initStickyTables() {
    const NAVBAR_H = 48;

    document.querySelectorAll('table.table-sticky').forEach(table => {
        const scrollParent = document.createElement('div');
        scrollParent.className = 'overflow-x-auto';
        table.parentNode.insertBefore(scrollParent, table);
        scrollParent.appendChild(table);

        const thead = table.querySelector('thead');
        if (!thead) return;

        let clone = null;
        let visible = false;

        function buildClone() {
            if (clone) clone.remove();

            clone = document.createElement('div');
            clone.className = 'table-sticky-clone';

            const inner = document.createElement('table');
            inner.className = table.className.replace('table-sticky', '').trim();
            inner.style.width = table.offsetWidth + 'px';

            const clonedThead = thead.cloneNode(true);

            const origCells = thead.querySelectorAll('th, td');
            const cloneCells = clonedThead.querySelectorAll('th, td');
            origCells.forEach((cell, i) => {
                if (cloneCells[i]) {
                    cloneCells[i].style.width = cell.getBoundingClientRect().width + 'px';
                    cloneCells[i].style.minWidth = cell.getBoundingClientRect().width + 'px';
                    cloneCells[i].style.maxWidth = cell.getBoundingClientRect().width + 'px';
                    cloneCells[i].style.boxSizing = 'border-box';
                }
            });

            inner.appendChild(clonedThead);
            clone.appendChild(inner);
            document.body.appendChild(clone);

            syncCloneScroll();
            bindCloneEvents();
        }

        function syncCloneScroll() {
            if (!clone || !scrollParent) return;
            const rect = scrollParent.getBoundingClientRect();
            clone.style.left = rect.left + 'px';
            clone.style.width = rect.width + 'px';
            clone.querySelector('table').style.marginLeft = -scrollParent.scrollLeft + 'px';
        }

        function bindCloneEvents() {
            if (!clone) return;
            clone.querySelectorAll('[data-sort-key]').forEach(th => {
                th.style.cursor = 'pointer';
                th.addEventListener('click', () => {
                    const key = th.dataset.sortKey;
                    const orig = thead.querySelector(`[data-sort-key="${key}"]`);
                    if (orig) orig.click();
                    requestAnimationFrame(() => buildClone());
                });
            });

            clone.querySelectorAll('.table-filter').forEach(input => {
                const key = input.dataset.filterKey;
                const orig = thead.querySelector(`.table-filter[data-filter-key="${key}"]`);
                if (!orig) return;

                input.value = orig.value;

                if (input.tagName === 'SELECT') {
                    input.addEventListener('change', () => {
                        orig.value = input.value;
                        orig.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                } else {
                    input.addEventListener('input', () => {
                        orig.value = input.value;
                        orig.dispatchEvent(new Event('input', { bubbles: true }));
                    });
                    input.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') {
                            orig.value = input.value;
                            orig.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
                        }
                    });
                }
            });
        }

        function show() {
            if (!visible) {
                buildClone();
                visible = true;
            }
            clone.style.display = '';
            syncCloneScroll();
        }

        function hide() {
            if (clone) clone.style.display = 'none';
            visible = false;
        }

        function onScroll() {
            const tableRect = table.getBoundingClientRect();
            const theadRect = thead.getBoundingClientRect();
            const tbodyBottom = table.querySelector('tbody')?.getBoundingClientRect().bottom ?? tableRect.bottom;

            const shouldShow = theadRect.top < NAVBAR_H && tbodyBottom > NAVBAR_H + theadRect.height;
            if (shouldShow) show();
            else hide();
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', () => { if (visible) buildClone(); });
        if (scrollParent) {
            scrollParent.addEventListener('scroll', () => { if (visible) syncCloneScroll(); }, { passive: true });
        }

        onScroll();
    });
}
