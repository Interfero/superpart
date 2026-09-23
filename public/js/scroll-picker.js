const ITEM_HEIGHT = 36;
const MONTHS = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];
const MINUTES = Array.from({ length: 12 }, (_, i) => String(i * 5).padStart(2, '0'));

function pad2(n) {
    return String(n).padStart(2, '0');
}

function parseDMY(value) {
    const match = String(value || '').match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
    if (!match) {
        return null;
    }

    return {
        day: Number(match[1]),
        month: Number(match[2]),
        year: Number(match[3]),
    };
}

function formatDMY(date) {
    return `${pad2(date.getDate())}.${pad2(date.getMonth() + 1)}.${date.getFullYear()}`;
}

function formatISO(date) {
    return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
}

function parseISO(iso) {
    const parts = String(iso || '').split('-').map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) {
        return null;
    }

    return new Date(parts[0], parts[1] - 1, parts[2]);
}

function roundTimeUpToFiveMinutes(value) {
    if (!value || !value.includes(':')) {
        return value;
    }

    let [hours, minutes] = value.split(':').map(Number);
    if (Number.isNaN(hours) || Number.isNaN(minutes)) {
        return value;
    }

    minutes = Math.ceil(minutes / 5) * 5;
    if (minutes >= 60) {
        minutes = 0;
        hours = (hours + 1) % 24;
    }

    return `${pad2(hours)}:${pad2(minutes)}`;
}

function todayStart() {
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
}

function daysInMonth(year, month) {
    return new Date(year, month, 0).getDate();
}

function compareDates(a, b) {
    return a.getTime() - b.getTime();
}

function buildColumn(container, items, selectedValue) {
    container.replaceChildren();

    const spacer = document.createElement('div');
    spacer.className = 'scroll-picker-spacer';
    spacer.style.height = `${ITEM_HEIGHT * 2}px`;
    container.appendChild(spacer);

    items.forEach((item) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'scroll-picker-item';
        btn.dataset.value = item.value;
        btn.textContent = item.label;
        btn.disabled = Boolean(item.disabled);

        if (item.disabled) {
            btn.classList.add('is-disabled');
        }

        btn.addEventListener('click', () => scrollToValue(container, item.value));
        container.appendChild(btn);
    });

    container.appendChild(spacer.cloneNode(true));
    scrollToValue(container, selectedValue);
}

function scrollToValue(container, value) {
    const item = container.querySelector(`.scroll-picker-item[data-value="${CSS.escape(String(value))}"]:not([disabled])`);
    if (!item) {
        return;
    }

    const top = item.offsetTop - container.clientHeight / 2 + item.clientHeight / 2;
    container.scrollTo({ top, behavior: 'auto' });
}

function getColumnValue(container) {
    const center = container.scrollTop + container.clientHeight / 2;
    let closest = null;
    let minDist = Infinity;

    container.querySelectorAll('.scroll-picker-item:not([disabled])').forEach((item) => {
        const itemCenter = item.offsetTop + item.clientHeight / 2;
        const dist = Math.abs(center - itemCenter);
        if (dist < minDist) {
            minDist = dist;
            closest = item;
        }
    });

    return closest?.dataset.value ?? null;
}

function snapColumn(container) {
    const value = getColumnValue(container);
    if (value) {
        scrollToValue(container, value);
    }
}

function createScrollPicker(root) {
    const type = root.dataset.scrollPickerType;
    const hidden = root.querySelector('input[type="hidden"]');
    const trigger = root.querySelector('[data-scroll-picker-trigger]');
    const panel = root.querySelector('[data-scroll-picker-panel]');
    const columnsWrap = root.querySelector('[data-scroll-picker-columns]');
    const display = root.querySelector('[data-scroll-picker-display]');
    const preview = root.querySelector('[data-scroll-picker-preview]');
    const cancelBtn = root.querySelector('[data-scroll-picker-cancel]');
    const confirmBtn = root.querySelector('[data-scroll-picker-confirm]');

    let draft = null;
    let columnEls = [];
    let scrollTimer = null;

    function minDateObj() {
        const iso = root.dataset.minDate || formatISO(todayStart());
        return parseISO(iso) || todayStart();
    }

    function linkedDateRoot() {
        const selector = root.dataset.linkedDate;
        return selector ? document.querySelector(selector) : null;
    }

    function linkedDateObj() {
        const linked = linkedDateRoot();
        if (!linked) {
            return todayStart();
        }

        const parsed = parseDMY(linked.querySelector('input[type="hidden"]')?.value);
        if (!parsed) {
            return todayStart();
        }

        return new Date(parsed.year, parsed.month - 1, parsed.day);
    }

    function isLinkedToday() {
        return compareDates(linkedDateObj(), todayStart()) === 0;
    }

    function minTimeToday() {
        return roundTimeUpToFiveMinutes(
            `${pad2(new Date().getHours())}:${pad2(new Date().getMinutes())}`,
        );
    }

    function yearRange() {
        const min = minDateObj().getFullYear();
        return [min, min + 2];
    }

    function buildDateDraft(fromValue) {
        const parsed = parseDMY(fromValue) || parseDMY(hidden.value);
        const min = minDateObj();
        const base = parsed
            ? new Date(parsed.year, parsed.month - 1, parsed.day)
            : new Date(min);

        if (compareDates(base, min) < 0) {
            return { day: pad2(min.getDate()), month: pad2(min.getMonth() + 1), year: String(min.getFullYear()) };
        }

        return { day: pad2(base.getDate()), month: pad2(base.getMonth() + 1), year: String(base.getFullYear()) };
    }

    function buildTimeDraft(fromValue) {
        const raw = roundTimeUpToFiveMinutes(fromValue || hidden.value || minTimeToday());
        const [h, m] = raw.split(':');
        return { hour: h, minute: m };
    }

    function dateItems(draftState) {
        const year = Number(draftState.year);
        const month = Number(draftState.month);
        const min = minDateObj();
        const maxDay = daysInMonth(year, month);

        const years = [];
        const [from, to] = yearRange();
        for (let y = from; y <= to; y += 1) {
            years.push({ value: String(y), label: String(y), disabled: false });
        }

        const months = MONTHS.map((m) => {
            const monthNum = Number(m);
            const monthStart = new Date(year, monthNum - 1, 1);
            const monthEnd = new Date(year, monthNum, 0);
            const disabled = compareDates(monthEnd, min) < 0 || compareDates(monthStart, new Date(to, 11, 31)) > 0;
            return { value: m, label: m, disabled };
        });

        const days = [];
        for (let d = 1; d <= maxDay; d += 1) {
            const date = new Date(year, month - 1, d);
            days.push({
                value: pad2(d),
                label: pad2(d),
                disabled: compareDates(date, min) < 0,
            });
        }

        return { days, months, years };
    }

    function timeItems(draftState) {
        const minTime = isLinkedToday() ? minTimeToday() : '00:00';
        const [minH, minM] = minTime.split(':').map(Number);

        const hours = [];
        for (let h = 0; h < 24; h += 1) {
            const disabled = isLinkedToday() && h < minH;
            hours.push({ value: pad2(h), label: pad2(h), disabled });
        }

        const minutes = MINUTES.map((m) => {
            const minute = Number(m);
            const hour = Number(draftState.hour);
            const disabled = isLinkedToday() && (hour < minH || (hour === minH && minute < minM));
            return { value: m, label: m, disabled };
        });

        return { hours, minutes, minTime };
    }

    function renderColumns() {
        columnsWrap.replaceChildren();
        columnEls = [];

        if (type === 'date') {
            const { days, months, years } = dateItems(draft);
            [
                { key: 'day', items: days },
                { key: 'month', items: months },
                { key: 'year', items: years },
            ].forEach((spec) => {
                const col = document.createElement('div');
                col.className = 'scroll-picker-column';
                col.dataset.column = spec.key;
                buildColumn(col, spec.items, draft[spec.key]);
                col.addEventListener('scroll', onColumnScroll);
                columnsWrap.appendChild(col);
                columnEls.push(col);
            });
        } else {
            const { hours, minutes } = timeItems(draft);
            [
                { key: 'hour', items: hours },
                { key: 'minute', items: minutes },
            ].forEach((spec) => {
                const col = document.createElement('div');
                col.className = 'scroll-picker-column';
                col.dataset.column = spec.key;
                buildColumn(col, spec.items, draft[spec.key]);
                col.addEventListener('scroll', onColumnScroll);
                columnsWrap.appendChild(col);
                columnEls.push(col);
            });
        }

        updatePreview();
    }

    function readDraftFromColumns() {
        columnEls.forEach((col) => {
            const key = col.dataset.column;
            const value = getColumnValue(col);
            if (value) {
                draft[key] = value;
            }
        });
    }

    function normalizeDraft() {
        if (type === 'date') {
            const min = minDateObj();
            const date = new Date(Number(draft.year), Number(draft.month) - 1, Number(draft.day));
            if (compareDates(date, min) < 0) {
                draft = buildDateDraft(formatDMY(min));
                renderColumns();
                return;
            }

            const maxDay = daysInMonth(Number(draft.year), Number(draft.month));
            if (Number(draft.day) > maxDay) {
                draft.day = pad2(maxDay);
                renderColumns();
            }
            return;
        }

        const { minTime } = timeItems(draft);
        const candidate = `${draft.hour}:${draft.minute}`;
        if (isLinkedToday() && candidate < minTime) {
            const [h, m] = minTime.split(':');
            draft = { hour: h, minute: m };
            renderColumns();
        }
    }

    function onColumnScroll() {
        clearTimeout(scrollTimer);
        scrollTimer = setTimeout(() => {
            const monthKeyBefore = type === 'date' ? `${draft.year}-${draft.month}` : null;
            readDraftFromColumns();
            normalizeDraft();

            if (type === 'date' && monthKeyBefore !== `${draft.year}-${draft.month}`) {
                renderColumns();
            }

            updatePreview();
            columnEls.forEach(snapColumn);
        }, 80);
    }

    function draftToValue() {
        if (type === 'date') {
            return `${draft.day}.${draft.month}.${draft.year}`;
        }

        return roundTimeUpToFiveMinutes(`${draft.hour}:${draft.minute}`);
    }

    function updatePreview() {
        if (preview) {
            preview.textContent = draftToValue();
        }
    }

    function updateDisplay(value) {
        if (display) {
            display.textContent = value || '—';
        }
    }

    function commitValue(value) {
        const nextValue = type === 'time' ? roundTimeUpToFiveMinutes(value) : value;
        const changed = hidden.value !== nextValue;

        hidden.value = nextValue;
        updateDisplay(nextValue);

        if (changed) {
            root.dispatchEvent(new CustomEvent('scroll-picker:change', { detail: { value: nextValue } }));
        }
    }

    function openPanel() {
        draft = type === 'date' ? buildDateDraft(hidden.value) : buildTimeDraft(hidden.value);
        panel.classList.remove('hidden');
        trigger.setAttribute('aria-expanded', 'true');

        requestAnimationFrame(() => {
            renderColumns();
            columnEls.forEach(snapColumn);
        });
    }

    function closePanel() {
        panel.classList.add('hidden');
        trigger.setAttribute('aria-expanded', 'false');
    }

    function confirmSelection() {
        clearTimeout(scrollTimer);
        columnEls.forEach(snapColumn);
        readDraftFromColumns();
        normalizeDraft();
        columnEls.forEach(snapColumn);
        readDraftFromColumns();
        commitValue(draftToValue());
        closePanel();
    }

    trigger.addEventListener('click', (event) => {
        event.stopPropagation();
        if (panel.classList.contains('hidden')) {
            openPanel();
        } else {
            closePanel();
        }
    });

    cancelBtn?.addEventListener('click', (event) => {
        event.stopPropagation();
        closePanel();
    });

    confirmBtn?.addEventListener('click', (event) => {
        event.stopPropagation();
        confirmSelection();
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            closePanel();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.classList.contains('hidden')) {
            closePanel();
        }
    });

    return {
        refresh() {
            if (type === 'date') {
                const min = minDateObj();
                const parsed = parseDMY(hidden.value);
                if (!parsed) {
                    commitValue(formatDMY(min));
                    return;
                }

                const date = new Date(parsed.year, parsed.month - 1, parsed.day);
                if (compareDates(date, min) < 0) {
                    commitValue(formatDMY(min));
                }
                return;
            }

            const minTime = isLinkedToday() ? minTimeToday() : '00:00';
            let value = roundTimeUpToFiveMinutes(hidden.value || minTime);
            if (isLinkedToday() && value < minTime) {
                value = minTime;
            }

            if (hidden.value !== value) {
                commitValue(value);
            } else {
                updateDisplay(value);
            }
        },
        getIsoDate() {
            const parsed = parseDMY(hidden.value);
            if (!parsed) {
                return formatISO(todayStart());
            }

            return formatISO(new Date(parsed.year, parsed.month - 1, parsed.day));
        },
        setValue(value) {
            commitValue(type === 'time' ? roundTimeUpToFiveMinutes(value) : value);
        },
    };
}

function initScrollPickers() {
    if (window.superpartScrollPicker?.__ready) {
        return;
    }

    const instances = new Map();

    document.querySelectorAll('[data-scroll-picker]').forEach((root) => {
        instances.set(root, createScrollPicker(root));
    });

    window.superpartScrollPicker = {
        __ready: true,
        get(root) {
            return instances.get(typeof root === 'string' ? document.querySelector(root) : root);
        },
        refresh(root) {
            instances.get(typeof root === 'string' ? document.querySelector(root) : root)?.refresh();
        },
        setValue(root, value) {
            instances.get(typeof root === 'string' ? document.querySelector(root) : root)?.setValue(value);
        },
        getIsoDate(root) {
            return instances.get(typeof root === 'string' ? document.querySelector(root) : root)?.getIsoDate();
        },
    };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initScrollPickers);
} else {
    initScrollPickers();
}
