(function () {
    'use strict';

    var MONTHS = [
        'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
        'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'
    ];

    var WEEKDAYS = [
        'domenica', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato'
    ];

    var EMPTY_OPTION = { value: '', label: '—' };

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function getDefaults(wrap) {
        var d = new Date();
        return {
            year: d.getFullYear(),
            month: d.getMonth() + 1,
            day: d.getDate(),
            hour: parseInt(wrap.dataset.cralDefaultHour || '9', 10),
            minute: parseInt(wrap.dataset.cralDefaultMinute || '0', 10)
        };
    }

    function parseStorage(value, wrap) {
        if (!value) {
            return null;
        }

        var defaults = getDefaults(wrap);
        var isDatetime = wrap.dataset.cralDateMode === 'datetime';
        var match = String(value).trim().match(/^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2}))?/);

        if (!match) {
            return null;
        }

        var parts = {
            year: parseInt(match[1], 10),
            month: parseInt(match[2], 10),
            day: parseInt(match[3], 10)
        };

        if (isDatetime) {
            parts.hour = match[4] ? parseInt(match[4], 10) : defaults.hour;
            parts.minute = match[5] ? parseInt(match[5], 10) : defaults.minute;
        }

        return parts;
    }

    function getStorageInput(key) {
        return document.querySelector('input[name="carbon_fields_compact_input[' + key + ']"]');
    }

    function daysInMonth(year, month) {
        return new Date(year, month, 0).getDate();
    }

    function weekdayLabel(year, month, day) {
        var d = new Date(year, month - 1, day);
        var name = WEEKDAYS[d.getDay()];
        return name.charAt(0).toUpperCase() + name.slice(1);
    }

    function dayLabel(year, month, day) {
        return weekdayLabel(year, month, day) + ' ' + day + ' ' + MONTHS[month - 1].toLowerCase();
    }

    function fillSelect(select, options, selectedValue) {
        if (!select) {
            return;
        }

        var html = '';
        options.forEach(function (opt) {
            var sel = String(opt.value) === String(selectedValue) ? ' selected' : '';
            html += '<option value="' + opt.value + '"' + sel + '>' + opt.label + '</option>';
        });
        select.innerHTML = html;
    }

    function buildYearOptions(selectedYear, currentYear, allowEmpty) {
        var options = [];
        var min = currentYear - 2;
        var max = currentYear + 6;

        if (allowEmpty) {
            options.push(EMPTY_OPTION);
        }

        for (var y = min; y <= max; y++) {
            options.push({ value: y, label: String(y) });
        }

        if (selectedYear && (selectedYear < min || selectedYear > max)) {
            options.push({ value: selectedYear, label: String(selectedYear) });
            options.sort(function (a, b) {
                if (a.value === '') {
                    return -1;
                }
                if (b.value === '') {
                    return 1;
                }
                return a.value - b.value;
            });
        }

        return options;
    }

    function buildMonthOptions(allowEmpty) {
        var options = allowEmpty ? [EMPTY_OPTION] : [];
        for (var m = 1; m <= 12; m++) {
            options.push({ value: m, label: MONTHS[m - 1] });
        }
        return options;
    }

    function buildDayOptions(year, month, selectedDay) {
        var total = daysInMonth(year, month);
        var options = [];

        for (var d = 1; d <= total; d++) {
            options.push({
                value: d,
                label: dayLabel(year, month, d)
            });
        }

        if (selectedDay > total) {
            selectedDay = total;
        }

        return { options: options, selectedDay: selectedDay };
    }

    function buildHourOptions() {
        var options = [];
        for (var h = 0; h <= 23; h++) {
            options.push({ value: h, label: pad(h) });
        }
        return options;
    }

    function buildMinuteOptions() {
        var options = [];
        for (var m = 0; m <= 59; m++) {
            options.push({ value: m, label: pad(m) });
        }
        return options;
    }

    function readParts(wrap, isDatetime) {
        var yearVal = wrap.querySelector('[data-cral-evento-year]').value;
        var monthVal = wrap.querySelector('[data-cral-evento-month]').value;
        var dayVal = wrap.querySelector('[data-cral-evento-day]').value;

        var parts = {
            year: yearVal === '' ? '' : parseInt(yearVal, 10),
            month: monthVal === '' ? '' : parseInt(monthVal, 10),
            day: dayVal === '' ? '' : parseInt(dayVal, 10)
        };

        if (isDatetime) {
            parts.hour = parseInt(wrap.querySelector('[data-cral-evento-hour]').value, 10);
            parts.minute = parseInt(wrap.querySelector('[data-cral-evento-minute]').value, 10);
        }

        return parts;
    }

    function isCompleteDate(parts) {
        return parts.year !== '' && parts.month !== '' && parts.day !== '';
    }

    function syncStorage(wrap, storage, isDatetime, allowEmpty) {
        if (!storage || !wrap) {
            return;
        }

        var p = readParts(wrap, isDatetime);

        if (!isCompleteDate(p)) {
            storage.value = allowEmpty ? '' : storage.value;
            if (!allowEmpty) {
                return;
            }
        } else if (isDatetime) {
            if (isNaN(p.hour) || isNaN(p.minute)) {
                storage.value = '';
            } else {
                storage.value = p.year + '-' + pad(p.month) + '-' + pad(p.day) +
                    ' ' + pad(p.hour) + ':' + pad(p.minute) + ':00';
            }
        } else {
            storage.value = p.year + '-' + pad(p.month) + '-' + pad(p.day);
        }

        storage.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function setMonthDayDisabled(wrap, disabled) {
        var monthSel = wrap.querySelector('[data-cral-evento-month]');
        var daySel = wrap.querySelector('[data-cral-evento-day]');
        if (monthSel) {
            monthSel.disabled = disabled;
        }
        if (daySel) {
            daySel.disabled = disabled;
        }
    }

    function refreshMonthDay(wrap, storage, isDatetime, allowEmpty, defaults) {
        var yearSel = wrap.querySelector('[data-cral-evento-year]');
        var monthSel = wrap.querySelector('[data-cral-evento-month]');
        var daySel = wrap.querySelector('[data-cral-evento-day]');

        var yearVal = yearSel.value;
        var selectedMonth = monthSel.value === '' ? '' : parseInt(monthSel.value, 10);
        var selectedDay = daySel.value === '' ? '' : parseInt(daySel.value, 10);

        if (yearVal === '') {
            fillSelect(monthSel, allowEmpty ? [EMPTY_OPTION] : buildMonthOptions(false), '');
            fillSelect(daySel, allowEmpty ? [EMPTY_OPTION] : [], '');
            setMonthDayDisabled(wrap, true);
            syncStorage(wrap, storage, isDatetime, allowEmpty);
            return;
        }

        setMonthDayDisabled(wrap, false);

        var year = parseInt(yearVal, 10);
        if (selectedMonth === '') {
            selectedMonth = defaults.month;
        }

        fillSelect(monthSel, buildMonthOptions(allowEmpty), selectedMonth);

        if (monthSel.value === '') {
            fillSelect(daySel, allowEmpty ? [EMPTY_OPTION] : [], '');
            syncStorage(wrap, storage, isDatetime, allowEmpty);
            return;
        }

        var month = parseInt(monthSel.value, 10);
        if (selectedDay === '') {
            selectedDay = defaults.day;
        }

        var dayData = buildDayOptions(year, month, selectedDay);
        fillSelect(daySel, dayData.options, dayData.selectedDay);
        syncStorage(wrap, storage, isDatetime, allowEmpty);
    }

    function initDatePicker(wrap) {
        if (!wrap || wrap.dataset.cralPickerReady === '1') {
            return;
        }

        var storageKey = wrap.dataset.cralStorageKey;
        var mode = wrap.dataset.cralDateMode || 'date';
        var isDatetime = mode === 'datetime';
        var allowEmpty = wrap.dataset.cralAllowEmpty === '1';

        var storage = getStorageInput(storageKey);
        var yearSel = wrap.querySelector('[data-cral-evento-year]');
        var monthSel = wrap.querySelector('[data-cral-evento-month]');
        var daySel = wrap.querySelector('[data-cral-evento-day]');
        var hourSel = wrap.querySelector('[data-cral-evento-hour]');
        var minuteSel = wrap.querySelector('[data-cral-evento-minute]');

        if (!storage || !yearSel || !monthSel || !daySel) {
            return;
        }

        if (isDatetime && (!hourSel || !minuteSel)) {
            return;
        }

        wrap.dataset.cralPickerReady = '1';

        var defaults = getDefaults(wrap);
        var parsed = parseStorage(storage.value, wrap);
        var currentYear = defaults.year;
        var hasValue = !!parsed;

        if (!hasValue && allowEmpty) {
            fillSelect(yearSel, buildYearOptions('', currentYear, true), '');
            fillSelect(monthSel, [EMPTY_OPTION], '');
            fillSelect(daySel, [EMPTY_OPTION], '');
            setMonthDayDisabled(wrap, true);
        } else {
            parsed = parsed || defaults;
            fillSelect(yearSel, buildYearOptions(parsed.year, currentYear, allowEmpty), parsed.year);
            fillSelect(monthSel, buildMonthOptions(allowEmpty), parsed.month);
            var dayData = buildDayOptions(parsed.year, parsed.month, parsed.day);
            fillSelect(daySel, dayData.options, dayData.selectedDay);
            setMonthDayDisabled(wrap, false);
        }

        if (isDatetime) {
            var timeDefaults = parsed || defaults;
            fillSelect(hourSel, buildHourOptions(), timeDefaults.hour);
            fillSelect(minuteSel, buildMinuteOptions(), timeDefaults.minute);
        }

        syncStorage(wrap, storage, isDatetime, allowEmpty);

        yearSel.addEventListener('change', function () {
            refreshMonthDay(wrap, storage, isDatetime, allowEmpty, defaults);
        });
        monthSel.addEventListener('change', function () {
            refreshMonthDay(wrap, storage, isDatetime, allowEmpty, defaults);
        });
        daySel.addEventListener('change', function () {
            syncStorage(wrap, storage, isDatetime, allowEmpty);
        });

        if (isDatetime) {
            hourSel.addEventListener('change', function () {
                syncStorage(wrap, storage, isDatetime, allowEmpty);
            });
            minuteSel.addEventListener('change', function () {
                syncStorage(wrap, storage, isDatetime, allowEmpty);
            });
        }

        var form = wrap.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                syncStorage(wrap, storage, isDatetime, allowEmpty);
            });
        }
    }

    function boot() {
        document.querySelectorAll('[data-cral-date-picker]').forEach(initDatePicker);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.addEventListener('load', boot);

    if (window.MutationObserver) {
        var observer = new MutationObserver(function () {
            boot();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }
})();
