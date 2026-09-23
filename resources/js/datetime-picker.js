const MONTHS_RU = [
    'январь', 'февраль', 'март', 'апрель', 'май', 'июнь',
    'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь',
];
const WEEKDAYS_RU = ['пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс'];
const MINUTES = Array.from({ length: 12 }, (_, i) => String(i * 5).padStart(2, '0'));
const WHEEL_ITEM_HEIGHT = 36;

function pad2(n) {
    return String(n).padStart(2, '0');
}

function parseDMY(value) {
    const match = String(value || '').trim().match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/);
    if (!match) {
        return null;
    }

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);

    if (month < 1 || month > 12 || day < 1 || day > 31) {
        return null;
    }

    const date = new Date(year, month - 1, day);
    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
        return null;
    }

    return { day, month, year, date };
}

function formatDMY(date) {
    return `${pad2(date.getDate())}.${pad2(date.getMonth() + 1)}.${date.getFullYear()}`;
}

function formatISO(date) {
    return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
}

function parseTime(value) {
    const match = String(value || '').trim().match(/^(\d{1,2}):(\d{2})$/);
    if (!match) {
        return null;
    }

    const hour = Number(match[1]);
    const minute = Number(match[2]);

    if (hour < 0 || hour > 23 || minute < 0 || minute > 59) {
        return null;
    }

    return { hour: pad2(hour), minute: pad2(minute) };
}

function roundTimeUpToFiveMinutes(value) {
    const parsed = parseTime(value);
    if (!parsed) {
        return value;
    }

    let hours = Number(parsed.hour);
    let minutes = Number(parsed.minute);
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

function compareDates(a, b) {
    return a.getTime() - b.getTime();
}

function stopPanelClose(event) {
    event.preventDefault();
    event.stopPropagation();
}

function bindPanelGuard(panelInner) {
    panelInner?.addEventListener('mousedown', stopPanelClose);
    panelInner?.addEventListener('click', stopPanelClose);
}

function scrollWheelToValue(container, value) {
    let item = container.querySelector(`.datetime-wheel-item[data-value="${CSS.escape(String(value))}"]:not(.is-disabled)`);

    if (!item) {
        item = container.querySelector('.datetime-wheel-item:not(.is-disabled)');
    }

    if (!item) {
        return null;
    }

    const top = item.offsetTop - container.clientHeight / 2 + item.clientHeight / 2;
    container.scrollTo({ top, behavior: 'auto' });

    return item.dataset.value ?? null;
}

function getWheelColumnValue(container) {
    const center = container.scrollTop + container.clientHeight / 2;
    let closest = null;
    let minDist = Infinity;

    container.querySelectorAll('.datetime-wheel-item:not(.is-disabled)').forEach((item) => {
        const itemCenter = item.offsetTop + item.clientHeight / 2;
        const dist = Math.abs(center - itemCenter);
        if (dist < minDist) {
            minDist = dist;
            closest = item;
        }
    });

    return closest?.dataset.value ?? null;
}

function snapWheelColumn(container) {
    const value = getWheelColumnValue(container);
    if (value) {
        scrollWheelToValue(container, value);
    }

    return value;
}

function buildWheelColumn(container, items, selectedValue, onResolved) {
    container.replaceChildren();

    const spacer = document.createElement('div');
    spacer.className = 'datetime-wheel-spacer';
    spacer.style.height = `${WHEEL_ITEM_HEIGHT * 2}px`;
    container.appendChild(spacer);

    items.forEach((item) => {
        const el = document.createElement('div');
        el.className = 'datetime-wheel-item';
        el.textContent = item.label;
        el.dataset.value = item.value;

        if (item.disabled) {
            el.classList.add('is-disabled');
        }

        el.addEventListener('click', () => {
            if (!item.disabled) {
                scrollWheelToValue(container, item.value);
            }
        });

        container.appendChild(el);
    });

    container.appendChild(spacer.cloneNode(true));

    const resolved = scrollWheelToValue(container, selectedValue);
    if (resolved && resolved !== selectedValue && typeof onResolved === 'function') {
        onResolved(resolved);
    }
}

function createDateField(root) {
    const input = root.querySelector('[data-datetime-input]');
    const toggle = root.querySelector('[data-datetime-toggle]');
    const panel = root.querySelector('[data-datetime-panel]');

    let viewYear;
    let viewMonth;

    function minDateObj() {
        const iso = root.dataset.minDate;
        if (!iso) {
            return todayStart();
        }

        const parts = iso.split('-').map(Number);
        if (parts.length !== 3 || parts.some(Number.isNaN)) {
            return todayStart();
        }

        return new Date(parts[0], parts[1] - 1, parts[2]);
    }

    function selectedDate() {
        return parseDMY(input.value)?.date ?? null;
    }

    function syncViewToSelection() {
        const selected = selectedDate() ?? minDateObj();
        viewYear = selected.getFullYear();
        viewMonth = selected.getMonth();
    }

    function commitValue(value, dispatch = true) {
        const parsed = parseDMY(value);
        const nextValue = parsed ? formatDMY(parsed.date) : value;
        const changed = input.value !== nextValue;

        input.value = nextValue;

        if (dispatch && changed) {
            root.dispatchEvent(new CustomEvent('datetime-field:change', { detail: { value: nextValue } }));
        }
    }

    function normalizeInput() {
        const parsed = parseDMY(input.value);
        const min = minDateObj();

        if (!parsed) {
            commitValue(formatDMY(min));
            return;
        }

        if (compareDates(parsed.date, min) < 0) {
            commitValue(formatDMY(min));
            return;
        }

        commitValue(formatDMY(parsed.date));
    }

    function shiftMonth(delta) {
        viewMonth += delta;
        if (viewMonth < 0) {
            viewMonth = 11;
            viewYear -= 1;
        } else if (viewMonth > 11) {
            viewMonth = 0;
            viewYear += 1;
        }
        renderCalendar();
    }

    function renderCalendar() {
        const min = minDateObj();
        const selected = selectedDate();
        const firstDay = new Date(viewYear, viewMonth, 1);
        const startOffset = (firstDay.getDay() + 6) % 7;
        const gridStart = new Date(viewYear, viewMonth, 1 - startOffset);

        panel.innerHTML = `
            <div class="datetime-calendar" data-datetime-panel-inner>
                <div class="datetime-calendar-header">
                    <button type="button" class="datetime-nav-btn" data-cal-prev aria-label="Предыдущий месяц">&lsaquo;</button>
                    <div class="datetime-calendar-title">${MONTHS_RU[viewMonth]} ${viewYear}</div>
                    <button type="button" class="datetime-nav-btn" data-cal-next aria-label="Следующий месяц">&rsaquo;</button>
                </div>
                <div class="datetime-weekdays">
                    ${WEEKDAYS_RU.map((day) => `<span>${day}</span>`).join('')}
                </div>
                <div class="datetime-days" data-cal-days></div>
            </div>
        `;

        const panelInner = panel.querySelector('[data-datetime-panel-inner]');
        bindPanelGuard(panelInner);

        panelInner.querySelector('[data-cal-prev]')?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            shiftMonth(-1);
        });

        panelInner.querySelector('[data-cal-next]')?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            shiftMonth(1);
        });

        const daysWrap = panel.querySelector('[data-cal-days]');

        for (let i = 0; i < 42; i += 1) {
            const date = new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + i);
            const inMonth = date.getMonth() === viewMonth;
            const disabled = compareDates(date, min) < 0;
            const isSelected = selected
                && date.getFullYear() === selected.getFullYear()
                && date.getMonth() === selected.getMonth()
                && date.getDate() === selected.getDate();

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'datetime-day';
            btn.textContent = String(date.getDate());
            btn.dataset.date = formatDMY(date);

            if (!inMonth) {
                btn.classList.add('is-outside');
            }
            if (disabled) {
                btn.classList.add('is-disabled');
                btn.disabled = true;
            }
            if (isSelected) {
                btn.classList.add('is-selected');
            }

            btn.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                commitValue(btn.dataset.date);
                closePanel();
            });

            daysWrap.appendChild(btn);
        }
    }

    function openPanel() {
        syncViewToSelection();
        renderCalendar();
        panel.classList.remove('hidden');
        toggle.setAttribute('aria-expanded', 'true');
    }

    function closePanel() {
        panel.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
    }

    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        if (panel.classList.contains('hidden')) {
            openPanel();
        } else {
            closePanel();
        }
    });

    input.addEventListener('blur', () => {
        window.setTimeout(normalizeInput, 0);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            normalizeInput();
            input.blur();
        }
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
            normalizeInput();
        },
        setValue(value) {
            commitValue(value, false);
            normalizeInput();
        },
        getValue() {
            return input.value;
        },
        getIsoDate() {
            const parsed = parseDMY(input.value);
            return parsed ? formatISO(parsed.date) : formatISO(todayStart());
        },
    };
}

function createTimeField(root) {
    const input = root.querySelector('[data-datetime-input]');
    const toggle = root.querySelector('[data-datetime-toggle]');
    const panel = root.querySelector('[data-datetime-panel]');

    let selectedHour;
    let selectedMinute;
    let hourColumn;
    let minuteColumn;
    let scrollTimer;

    function linkedDateRoot() {
        const selector = root.dataset.linkedDate;
        return selector ? document.querySelector(selector) : null;
    }

    function linkedDateParsed() {
        const linked = linkedDateRoot();
        const linkedInput = linked?.querySelector('[data-datetime-input]');
        return parseDMY(linkedInput?.value);
    }

    function linkedDateObj() {
        return linkedDateParsed()?.date ?? todayStart();
    }

    function cityTodayParsed() {
        const raw = root.dataset.cityToday;
        if (!raw) {
            return null;
        }

        return parseDMY(raw);
    }

    function isLinkedToday() {
        const parsed = linkedDateParsed();
        if (!parsed) {
            return false;
        }

        const cityToday = cityTodayParsed();
        if (cityToday) {
            return compareDates(parsed.date, cityToday.date) === 0;
        }

        return compareDates(parsed.date, todayStart()) === 0;
    }

    function minTimeToday() {
        if (root.dataset.cityMinTime) {
            return roundTimeUpToFiveMinutes(root.dataset.cityMinTime);
        }

        return roundTimeUpToFiveMinutes(`${pad2(new Date().getHours())}:${pad2(new Date().getMinutes())}`);
    }

    function minTime() {
        return isLinkedToday() ? minTimeToday() : '00:00';
    }

    function minParts() {
        return minTime().split(':').map(Number);
    }

    function isHourDisabled(hour) {
        const [minH] = minParts();
        return isLinkedToday() && hour < minH;
    }

    function isMinuteDisabled(hour, minute) {
        const [minH, minM] = minParts();
        const hourNum = Number(hour);

        return isLinkedToday()
            && (hourNum < minH || (hourNum === minH && Number(minute) < minM));
    }

    function firstValidHour() {
        for (let h = 0; h < 24; h += 1) {
            if (!isHourDisabled(h)) {
                return pad2(h);
            }
        }

        return pad2(new Date().getHours());
    }

    function firstValidMinuteForHour(hour) {
        const found = MINUTES.find((minute) => !isMinuteDisabled(hour, minute));
        if (found) {
            return found;
        }

        return isLinkedToday() ? minTime().split(':')[1] : MINUTES[0];
    }

    function ensureValidSelection() {
        const parsed = parseTime(`${selectedHour}:${selectedMinute}`);

        if (!parsed) {
            const min = parseTime(minTime());
            selectedHour = min.hour;
            selectedMinute = min.minute;
            return;
        }

        if (isLinkedToday()) {
            const min = minTime();
            const current = `${parsed.hour}:${parsed.minute}`;

            if (current < min || isHourDisabled(Number(parsed.hour))) {
                const minParsed = parseTime(min);
                selectedHour = minParsed.hour;
                selectedMinute = minParsed.minute;
                return;
            }
        }

        selectedHour = parsed.hour;
        selectedMinute = parsed.minute;

        if (isMinuteDisabled(selectedHour, selectedMinute)) {
            selectedMinute = firstValidMinuteForHour(selectedHour);
        }
    }

    function hourItems() {
        return Array.from({ length: 24 }, (_, h) => {
            const value = pad2(h);
            return { value, label: value, disabled: isHourDisabled(h) };
        });
    }

    function minuteItems(hour) {
        return MINUTES.map((minute) => ({
            value: minute,
            label: minute,
            disabled: isMinuteDisabled(hour, minute),
        }));
    }

    function commitValue(value, dispatch = true) {
        const nextValue = roundTimeUpToFiveMinutes(value);
        const changed = input.value !== nextValue;

        input.value = nextValue;

        if (dispatch && changed) {
            root.dispatchEvent(new CustomEvent('datetime-field:change', { detail: { value: nextValue } }));
        }
    }

    function normalizeInput() {
        const parsed = parseTime(input.value);
        const min = minTime();

        if (!parsed) {
            if (input.value.trim() === '') {
                commitValue(min, false);
                return;
            }

            commitValue(min);
            return;
        }

        let value = `${parsed.hour}:${parsed.minute}`;
        value = roundTimeUpToFiveMinutes(value);

        if (isLinkedToday() && value < min) {
            commitValue(min);
            return;
        }

        commitValue(value);
    }

    function syncSelectionFromInput() {
        const parsed = parseTime(input.value) ?? parseTime(minTime());
        selectedHour = parsed.hour;
        selectedMinute = parsed.minute;
        ensureValidSelection();
    }

    function isPanelOpen() {
        return !panel.classList.contains('hidden');
    }

    function rebuildTimePanelIfOpen() {
        if (!isPanelOpen()) {
            return;
        }

        syncSelectionFromInput();
        renderTimePanel();
    }

    function onLinkedDateChange() {
        normalizeInput();
        syncSelectionFromInput();
        rebuildTimePanelIfOpen();
    }

    function updatePreview() {
        const preview = panel.querySelector('[data-time-preview]');
        if (preview) {
            preview.textContent = `${selectedHour}:${selectedMinute}`;
        }
    }

    function renderMinuteWheel() {
        if (!minuteColumn) {
            return;
        }

        buildWheelColumn(minuteColumn, minuteItems(selectedHour), selectedMinute, (value) => {
            selectedMinute = value;
            updatePreview();
        });
    }

    function renderHourWheel() {
        if (!hourColumn) {
            return;
        }

        buildWheelColumn(hourColumn, hourItems(), selectedHour, (value) => {
            selectedHour = value;
            if (isMinuteDisabled(selectedHour, selectedMinute)) {
                selectedMinute = firstValidMinuteForHour(selectedHour);
            }
            renderMinuteWheel();
            updatePreview();
        });
    }

    function readWheels() {
        const hour = snapWheelColumn(hourColumn);
        if (hour) {
            selectedHour = hour;
        }

        if (isMinuteDisabled(selectedHour, selectedMinute)) {
            selectedMinute = firstValidMinuteForHour(selectedHour);
            renderMinuteWheel();
        }

        const minute = snapWheelColumn(minuteColumn);
        if (minute) {
            selectedMinute = minute;
        }

        updatePreview();
    }

    function onWheelScroll(changedColumn) {
        clearTimeout(scrollTimer);
        scrollTimer = setTimeout(() => {
            const hourBefore = selectedHour;

            readWheels();

            if (changedColumn === 'hour' && hourBefore !== selectedHour) {
                renderMinuteWheel();
                readWheels();
            }
        }, 80);
    }

    function mountWheels() {
        hourColumn = panel.querySelector('[data-time-hours]');
        minuteColumn = panel.querySelector('[data-time-minutes]');

        renderHourWheel();
        renderMinuteWheel();

        hourColumn.addEventListener('scroll', () => onWheelScroll('hour'));
        minuteColumn.addEventListener('scroll', () => onWheelScroll('minute'));

        readWheels();
    }

    function renderTimePanel() {
        panel.innerHTML = `
            <div class="datetime-time-wheel" data-datetime-panel-inner>
                <div class="datetime-time-wheel-header">
                    <span>Часы</span>
                    <span>Минуты</span>
                </div>
                <div class="datetime-wheel-frame">
                    <div class="datetime-wheel-highlight" aria-hidden="true"></div>
                    <div class="datetime-wheel-columns">
                        <div class="datetime-wheel-column" data-time-hours></div>
                        <div class="datetime-wheel-column" data-time-minutes></div>
                    </div>
                </div>
                <div class="datetime-time-wheel-footer">
                    <span class="datetime-time-preview" data-time-preview">${selectedHour}:${selectedMinute}</span>
                    <div class="datetime-time-wheel-actions">
                        <button type="button" class="datetime-time-cancel" data-time-cancel>Отмена</button>
                        <button type="button" class="datetime-time-confirm" data-time-confirm>Выбрать</button>
                    </div>
                </div>
            </div>
        `;

        const panelInner = panel.querySelector('[data-datetime-panel-inner]');
        bindPanelGuard(panelInner);

        panelInner.querySelector('[data-time-cancel]')?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            closePanel();
        });

        panelInner.querySelector('[data-time-confirm]')?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            readWheels();
            commitValue(`${selectedHour}:${selectedMinute}`);
            closePanel();
        });

        requestAnimationFrame(() => {
            mountWheels();
        });
    }

    function openPanel() {
        normalizeInput();
        syncSelectionFromInput();
        panel.classList.remove('hidden');
        toggle.setAttribute('aria-expanded', 'true');
        renderTimePanel();
    }

    function closePanel() {
        panel.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
        hourColumn = null;
        minuteColumn = null;
    }

    linkedDateRoot()?.addEventListener('datetime-field:change', onLinkedDateChange);

    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        if (panel.classList.contains('hidden')) {
            openPanel();
        } else {
            closePanel();
        }
    });

    input.addEventListener('blur', () => {
        window.setTimeout(normalizeInput, 0);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            normalizeInput();
            input.blur();
        }
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
            normalizeInput();
            syncSelectionFromInput();
            rebuildTimePanelIfOpen();
        },
        setValue(value) {
            commitValue(value, false);
            normalizeInput();
        },
        getValue() {
            return input.value;
        },
        getIsoDate() {
            return formatISO(linkedDateObj());
        },
    };
}

function createField(root) {
    const type = root.dataset.datetimeType;

    return type === 'time' ? createTimeField(root) : createDateField(root);
}

function initDateTimeFields() {
    if (window.superpartDateTimePicker?.__ready) {
        return;
    }

    const instances = new Map();

    document.querySelectorAll('[data-datetime-field]').forEach((root) => {
        instances.set(root, createField(root));
    });

    window.superpartDateTimePicker = {
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
        getValue(root) {
            return instances.get(typeof root === 'string' ? document.querySelector(root) : root)?.getValue();
        },
    };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDateTimeFields);
} else {
    initDateTimeFields();
}
