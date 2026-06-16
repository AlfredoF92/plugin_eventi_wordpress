(function () {
    'use strict';

    var cfg = window.cralCalendarioAdmin || {};
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
        body.append('action', 'cral_calendario_admin_mese');
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

    function closeModal(root) {
        var modal = root.querySelector('[data-cal-modal]');
        if (!modal) {
            return;
        }
        modal.hidden = true;
        document.body.classList.remove('cral-cal-modal-open');
        clearActiveDay(root);
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

        var actionsHtml = '<div class="cral-cal-day-item__footer cral-cal-day-item__footer--admin">';
        if (ev.edit_url) {
            actionsHtml += '<a href="' + escapeHtml(ev.edit_url) + '" class="cral-cal-list__action cral-cal-list__action--edit">' +
                escapeHtml(i18n.modifica || 'Modifica Evento') + '</a>';
        }
        if (ev.iscritti_url) {
            actionsHtml += '<a href="' + escapeHtml(ev.iscritti_url) + '" class="cral-cal-list__action cral-cal-list__action--iscritti">' +
                escapeHtml(i18n.iscritti || 'Vedi iscritti') + '</a>';
        }
        actionsHtml += '</div>';

        return '<article class="cral-cal-day-item cral-cal-day-item--admin">' +
            '<div class="cral-cal-day-item__head">' +
                '<span class="cral-cal-day-item__thumb-wrap">' + thumb + '</span>' +
                '<div class="cral-cal-day-item__body">' +
                    '<h4 class="cral-cal-day-item__title">' + escapeHtml(ev.title) + '</h4>' +
                    (metaParts.length ? '<p class="cral-cal-day-item__meta">' + escapeHtml(metaParts.join(' · ')) + '</p>' : '') +
                    (ev.badge_html ? '<div class="cral-cal-day-item__badge">' + ev.badge_html + '</div>' : '') +
                '</div>' +
            '</div>' +
            actionsHtml +
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

    function handleOpenDay(root, day) {
        if (!day) {
            return;
        }
        openDayModal(root, day);
    }

    function applyMonthData(root, data) {
        root.dataset.year = String(data.year);
        root.dataset.month = String(data.month);

        if (data.navHtml) {
            var navWrap = root.querySelector('.cral-cal__nav-admin-wrap');
            if (navWrap) {
                navWrap.outerHTML = data.navHtml;
            } else {
                var nav = root.querySelector('.cral-cal__nav');
                if (nav) {
                    nav.outerHTML = data.navHtml;
                }
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

        clearActiveDay(root);
    }

    function getYearMonth(root) {
        return {
            year: parseInt(root.dataset.year, 10),
            month: parseInt(root.dataset.month, 10)
        };
    }

    function loadMonth(root, year, month) {
        if (!year || !month) {
            return;
        }

        closeModal(root);

        fetchMonth(root, year, month)
            .then(function (data) {
                applyMonthData(root, data);
            })
            .catch(function () {
                window.alert('Impossibile caricare il calendario. Riprova.');
            });
    }

    function changeMonth(root, delta) {
        var current = getYearMonth(root);
        var year = current.year;
        var month = current.month + delta;

        if (month < 1) {
            month = 12;
            year -= 1;
        } else if (month > 12) {
            month = 1;
            year += 1;
        }

        loadMonth(root, year, month);
    }

    function bindInteractive(root) {
        if (root._cralAdminBound) {
            return;
        }
        root._cralAdminBound = true;

        root.addEventListener('click', function (e) {
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

            if (e.target.closest('[data-cal-prev]')) {
                changeMonth(root, -1);
                return;
            }

            if (e.target.closest('[data-cal-next]')) {
                changeMonth(root, 1);
                return;
            }

            if (e.target.closest('[data-cal-today]')) {
                var todayYear = parseInt(root.dataset.todayYear, 10);
                var todayMonth = parseInt(root.dataset.todayMonth, 10);
                if (todayYear && todayMonth) {
                    loadMonth(root, todayYear, todayMonth);
                }
                return;
            }

            if (e.target.closest('[data-cal-modal-close]')) {
                closeModal(root);
            }
        });

        root.addEventListener('change', function (e) {
            var monthSelect = e.target.closest('[data-cal-month-select]');
            var yearSelect = e.target.closest('[data-cal-year-select]');

            if (!monthSelect && !yearSelect) {
                return;
            }

            var monthEl = root.querySelector('[data-cal-month-select]');
            var yearEl = root.querySelector('[data-cal-year-select]');
            if (!monthEl || !yearEl) {
                return;
            }

            loadMonth(root, parseInt(yearEl.value, 10), parseInt(monthEl.value, 10));
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

    function boot() {
        document.querySelectorAll('.cral-cal--admin').forEach(function (root) {
            bindInteractive(root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
