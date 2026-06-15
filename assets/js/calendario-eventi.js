(function () {
    'use strict';

    var cfg = window.cralCalendario || {};
    var i18n = cfg.i18n || {};

    function parseEventsJson(root) {
        var node = root.querySelector('[data-cal-events-json]');
        if (!node) {
            return { byDay: {}, flat: [] };
        }
        try {
            return JSON.parse(node.textContent || '{}');
        } catch (e) {
            return { byDay: {}, flat: [] };
        }
    }

    function indexEvents(flat) {
        var map = {};
        (flat || []).forEach(function (ev) {
            if (ev && ev.id) {
                map[String(ev.id)] = ev;
            }
        });
        return map;
    }

    function escapeHtml(str) {
        if (!str) {
            return '';
        }
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getDayEvents(root, day) {
        var data = parseEventsJson(root);
        var byDay = data.byDay || {};
        var key = String(day);
        return byDay[key] || byDay[day] || [];
    }

    function setLoading(root, loading) {
        root.classList.toggle('is-loading', !!loading);
    }

    function fetchMonth(root, year, month) {
        var body = new FormData();
        body.append('action', 'cral_calendario_mese');
        body.append('nonce', cfg.nonce || '');
        body.append('year', String(year));
        body.append('month', String(month));

        setLoading(root, true);

        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json || !json.success) {
                    throw new Error((json && json.data && json.data.message) || 'Errore caricamento');
                }
                return json.data;
            })
            .finally(function () {
                setLoading(root, false);
            });
    }

    function applyMonthData(root, data) {
        root.dataset.year = String(data.year);
        root.dataset.month = String(data.month);

        if (data.navHtml) {
            var nav = root.querySelector('.cral-cal__nav');
            if (nav) {
                nav.outerHTML = data.navHtml;
            }
        } else {
            var label = root.querySelector('[data-cal-month-label]');
            if (label && data.monthLabel) {
                label.textContent = data.monthLabel;
            }
        }

        var grid = root.querySelector('[data-cal-grid]');
        if (grid) {
            grid.innerHTML = data.calendarHtml || '';
        }

        var list = root.querySelector('[data-cal-list]');
        if (list) {
            list.innerHTML = data.listHtml || '';
        }

        var listTitle = root.querySelector('[data-cal-list-title]');
        if (listTitle && data.listTitleHtml) {
            listTitle.innerHTML = data.listTitleHtml;
        }

        var jsonNode = root.querySelector('[data-cal-events-json]');
        if (jsonNode) {
            jsonNode.textContent = JSON.stringify({
                byDay: data.eventsByDay || {},
                flat: data.eventsFlat || []
            });
        }

        root._cralEventsMap = indexEvents(data.eventsFlat || []);
        clearActiveDay(root);
    }

    function clearActiveDay(root) {
        root.querySelectorAll('.cral-cal__cell.is-active-day').forEach(function (el) {
            el.classList.remove('is-active-day');
        });
    }

    function setActiveDay(root, day) {
        clearActiveDay(root);
        var cell = root.querySelector('.cral-cal__cell[data-cal-day="' + day + '"]');
        if (cell) {
            cell.classList.add('is-active-day');
        }
    }

    function renderDayEventItem(ev) {
        var thumb = ev.thumb
            ? '<img src="' + escapeHtml(ev.thumb) + '" alt="" class="cral-cal-day-item__thumb" loading="lazy">'
            : '<span class="cral-cal-thumb cral-cal-thumb--placeholder cral-cal-day-item__thumb">&#127917;</span>';

        var metaParts = [];
        if (ev.ora) {
            metaParts.push(ev.ora);
        }
        if (ev.luogo) {
            metaParts.push(ev.luogo);
        }

        var socioHtml = '';
        if (ev.socio_stato_label) {
            socioHtml = '<span class="cral-cal-day-item__socio cral-cal-day-item__socio--' + escapeHtml(ev.socio_stato || 'default') + '">' +
                escapeHtml(ev.socio_stato_label) + '</span>';
        }

        return '<article class="cral-cal-day-item">' +
            '<div class="cral-cal-day-item__head">' +
                '<span class="cral-cal-day-item__thumb-wrap">' + thumb + '</span>' +
                '<div class="cral-cal-day-item__body">' +
                    '<h4 class="cral-cal-day-item__title">' + escapeHtml(ev.title) + '</h4>' +
                    (metaParts.length ? '<p class="cral-cal-day-item__meta">' + escapeHtml(metaParts.join(' · ')) + '</p>' : '') +
                    (ev.badge_html ? '<div class="cral-cal-day-item__badge">' + ev.badge_html + '</div>' : '') +
                    socioHtml +
                '</div>' +
            '</div>' +
            '<div class="cral-cal-day-item__footer">' +
                '<a href="' + escapeHtml(ev.url || '#') + '" class="cral-cal-day-item__cta">' +
                    escapeHtml(i18n.goEvent || 'Vai all\'evento') +
                    '<span class="cral-cal-day-item__cta-arrow" aria-hidden="true">&#8594;</span>' +
                '</a>' +
            '</div>' +
        '</article>';
    }

    function openDayModal(root, day) {
        var events = getDayEvents(root, day);
        if (!events.length) {
            return;
        }

        var modal = root.querySelector('[data-cal-modal]');
        if (!modal) {
            return;
        }

        var titleEl = modal.querySelector('[data-cal-modal-day-title]');
        var listEl = modal.querySelector('[data-cal-modal-day-list]');

        var dayLabel = events[0].data_estesa || ((i18n.eventiGiorno || 'Eventi del giorno') + ' ' + day);

        if (titleEl) {
            titleEl.textContent = dayLabel;
        }

        if (listEl) {
            listEl.innerHTML = events.map(renderDayEventItem).join('');
        }

        setActiveDay(root, day);
        modal.hidden = false;
        document.body.classList.add('cral-cal-modal-open');

        var closeBtn = modal.querySelector('.cral-cal-modal__close');
        if (closeBtn) {
            closeBtn.focus();
        }
    }

    function closeModal(root) {
        var modal = root.querySelector('[data-cal-modal]');
        if (!modal) {
            return;
        }
        modal.hidden = true;
        document.body.classList.remove('cral-cal-modal-open');
        clearActiveDay(root);
    }

    function changeMonth(root, delta) {
        var year = parseInt(root.dataset.year, 10);
        var month = parseInt(root.dataset.month, 10);
        if (!year || !month) {
            return;
        }

        closeModal(root);

        month += delta;
        if (month < 1) {
            month = 12;
            year -= 1;
        } else if (month > 12) {
            month = 1;
            year += 1;
        }

        fetchMonth(root, year, month)
            .then(function (data) {
                applyMonthData(root, data);
            })
            .catch(function () {
                window.alert('Impossibile caricare il calendario. Riprova.');
            });
    }

    function handleOpenDay(root, day) {
        if (!day) {
            return;
        }
        openDayModal(root, day);
    }

    function bindInteractive(root) {
        if (root._cralBound) {
            return;
        }
        root._cralBound = true;

        var initial = parseEventsJson(root);
        root._cralEventsMap = indexEvents(initial.flat || []);

        root.addEventListener('click', function (e) {
            var openDayBtn = e.target.closest('[data-cal-open-day]');
            if (openDayBtn) {
                e.preventDefault();
                handleOpenDay(root, openDayBtn.getAttribute('data-cal-open-day'));
                return;
            }

            var card = e.target.closest('.cral-cal__event-card');
            if (card) {
                e.preventDefault();
                e.stopPropagation();
                var dayCell = card.closest('.cral-cal__cell--day');
                if (dayCell) {
                    handleOpenDay(root, dayCell.getAttribute('data-cal-day'));
                }
                return;
            }

            var dayCell = e.target.closest('.cral-cal__cell--day.has-events');
            if (dayCell && !e.target.closest('.cral-cal__event-card')) {
                e.preventDefault();
                handleOpenDay(root, dayCell.getAttribute('data-cal-day'));
                return;
            }

            var prev = e.target.closest('[data-cal-prev]');
            if (prev) {
                changeMonth(root, -1);
                return;
            }

            var next = e.target.closest('[data-cal-next]');
            if (next) {
                changeMonth(root, 1);
                return;
            }

            var close = e.target.closest('[data-cal-modal-close]');
            if (close) {
                closeModal(root);
            }
        });

        root.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal(root);
                return;
            }

            var card = e.target.closest('.cral-cal__event-card');
            if (card && (e.key === 'Enter' || e.key === ' ')) {
                e.preventDefault();
                var cell = card.closest('.cral-cal__cell--day');
                if (cell) {
                    handleOpenDay(root, cell.getAttribute('data-cal-day'));
                }
                return;
            }

            var dayCell = e.target.closest('.cral-cal__cell--day.has-events');
            if (dayCell && (e.key === 'Enter' || e.key === ' ')) {
                e.preventDefault();
                handleOpenDay(root, dayCell.getAttribute('data-cal-day'));
            }
        });
    }

    function init() {
        document.querySelectorAll('.cral-cal').forEach(function (root) {
            bindInteractive(root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
