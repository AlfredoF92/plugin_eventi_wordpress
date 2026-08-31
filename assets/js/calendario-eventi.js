(function () {
    'use strict';

    var cfg = window.cralCalendario || {};
    var i18n = cfg.i18n || {};

    function parseEventsJson(root) {
        var node = root.querySelector('[data-cal-events-json]');
        if (!node) {
            return { byDay: {}, flat: [], focusDay: 0 };
        }
        try {
            return JSON.parse(node.textContent || '{}');
        } catch (e) {
            return { byDay: {}, flat: [], focusDay: 0 };
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

    function clearActiveDay(root) {
        root.querySelectorAll('.cral-cal__cell.is-active-day, .cral-cal__cell.is-selected').forEach(function (el) {
            el.classList.remove('is-active-day', 'is-selected');
        });
    }

    function setActiveDay(root, day) {
        clearActiveDay(root);
        var cell = root.querySelector('.cral-cal__cell[data-cal-day="' + day + '"]');
        if (cell) {
            cell.classList.add('is-active-day', 'is-selected');
        }
        root.dataset.focusDay = String(day || 0);
    }

    function formatDayTitle(root, day, events) {
        if (!day) {
            return escapeHtml(i18n.nessunEventoMese || 'Nessun evento questo mese');
        }

        var label = '';
        if (events && events[0] && events[0].data_estesa) {
            label = events[0].data_estesa;
        } else {
            label = String(day);
        }

        var year = parseInt(root.dataset.year, 10);
        var month = parseInt(root.dataset.month, 10);
        var now = new Date();
        var isToday = year === now.getFullYear() && month === (now.getMonth() + 1) && day === now.getDate();
        var prefix = isToday ? (i18n.oggi || 'Oggi') : (i18n.eventiGiorno || 'Eventi');

        return escapeHtml(prefix) + ' <span class="cral-cal__list-title-month">' + escapeHtml(label) + '</span>';
    }

    function renderDayListHtml(events) {
        if (!events || !events.length) {
            return '<p class="cral-cal__empty">' + escapeHtml(i18n.nessunEventoGiorno || 'Nessun evento in questo giorno.') + '</p>';
        }

        return events.map(function (ev) {
            var img = ev.thumb_md || ev.thumb;
            var thumb = img
                ? '<img src="' + escapeHtml(img) + '" alt="" class="cral-cal-thumb" loading="lazy">'
                : '<span class="cral-cal-thumb cral-cal-thumb--placeholder" aria-hidden="true">&#127917;</span>';

            var catBg = ev.categoria_colore || '#a7c957';
            var catFg = ev.categoria_testo || '#111827';
            var mod = ev.stato_mod || 'aperto';
            var when = ev.data_card || ev.ora || '';

            var meta = '';
            if (ev.luogo) {
                meta = '<span class="cral-cal-list__meta"><span class="cral-cal-list__meta-luogo">' + escapeHtml(ev.luogo) + '</span></span>';
            }

            var cat = ev.categoria
                ? '<span class="cral-cal-list__cat" style="background:' + escapeHtml(catBg) + ';color:' + escapeHtml(catFg) + '">' + escapeHtml(ev.categoria) + '</span>'
                : '';

            var stato = ev.stato_label
                ? '<span class="cral-cal-list__stato cral-cal-product__stato cral-cal-product__stato--' + escapeHtml(mod) + '">' + escapeHtml(ev.stato_label) + '</span>'
                : '';

            var parti = '<span class="cral-cal-list__parti">' +
                escapeHtml(String(ev.partecipanti || 0)) + ' / ' + escapeHtml(String(ev.posti_totali || 0)) + ' posti</span>';

            return '<article class="cral-cal-list__item cral-cal-list__item--day" data-event-id="' + escapeHtml(String(ev.id)) + '" style="--cral-cat:' + escapeHtml(catBg) + ';">' +
                '<a class="cral-cal-list__btn" href="' + escapeHtml(ev.url || '#') + '">' +
                    '<span class="cral-cal-list__thumb-wrap">' + thumb + '</span>' +
                    '<span class="cral-cal-list__body">' +
                        (when ? '<span class="cral-cal-list__when">' + escapeHtml(when) + '</span>' : '') +
                        '<span class="cral-cal-list__title">' + escapeHtml(ev.title) + '</span>' +
                        meta +
                        '<span class="cral-cal-list__meta-row">' + cat + stato + parti + '</span>' +
                    '</span>' +
                '</a>' +
            '</article>';
        }).join('');
    }

    function updateDayPanel(root, day) {
        var events = getDayEvents(root, day);
        var titleEl = root.querySelector('[data-cal-day-title]');
        var listEl = root.querySelector('[data-cal-day-list]');

        if (titleEl) {
            titleEl.innerHTML = formatDayTitle(root, day, events);
        }
        if (listEl) {
            listEl.innerHTML = renderDayListHtml(events);
        }

        setActiveDay(root, day);
    }

    function applyMonthData(root, data) {
        root.dataset.year = String(data.year);
        root.dataset.month = String(data.month);
        root.dataset.focusDay = String(data.focusDay || 0);

        if (data.navHtml) {
            var navWrap = root.querySelector('[data-cal-nav-wrap]');
            if (navWrap) {
                navWrap.outerHTML = data.navHtml;
            } else {
                var nav = root.querySelector('.cral-cal__nav');
                if (nav) {
                    nav.outerHTML = data.navHtml;
                }
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

        var dayTitle = root.querySelector('[data-cal-day-title]');
        if (dayTitle && data.dayTitleHtml) {
            dayTitle.innerHTML = data.dayTitleHtml;
        }

        var dayList = root.querySelector('[data-cal-day-list]');
        if (dayList && typeof data.dayListHtml === 'string') {
            dayList.innerHTML = data.dayListHtml;
        }

        var upcoming = root.querySelector('[data-cal-upcoming]');
        if (upcoming && typeof data.upcomingHtml === 'string') {
            upcoming.innerHTML = data.upcomingHtml;
            upcoming._cralCarouselBound = false;
            window.setTimeout(function () {
                bindUpcomingCarousel(root);
            }, 0);
        }

        var jsonNode = root.querySelector('[data-cal-events-json]');
        if (jsonNode) {
            jsonNode.textContent = JSON.stringify({
                byDay: data.eventsByDay || {},
                flat: data.eventsFlat || [],
                focusDay: data.focusDay || 0
            });
        }

        root._cralEventsMap = indexEvents(data.eventsFlat || []);
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
                    escapeHtml(i18n.goEvent || 'Scopri di più') +
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
        var dayLabel = events[0].data_estesa || ((i18n.eventiGiorno || 'Eventi') + ' ' + day);

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

    function goToToday(root) {
        var now = new Date();
        closeModal(root);
        fetchMonth(root, now.getFullYear(), now.getMonth() + 1)
            .then(function (data) {
                applyMonthData(root, data);
            })
            .catch(function () {
                window.alert('Impossibile caricare il calendario. Riprova.');
            });
    }

    function handleSelectDay(root, day) {
        if (!day) {
            return;
        }
        updateDayPanel(root, parseInt(day, 10));

        // Su mobile stretto apri anche il modal per comodità.
        if (window.matchMedia && window.matchMedia('(max-width: 640px)').matches) {
            openDayModal(root, day);
        }
    }

    function updateCarouselNav(root, trackSel, prevSel, nextSel) {
        var track = root.querySelector(trackSel);
        var prev = root.querySelector(prevSel);
        var next = root.querySelector(nextSel);
        if (!track || !prev || !next) {
            return;
        }
        var maxScroll = Math.max(0, track.scrollWidth - track.clientWidth - 2);
        prev.disabled = track.scrollLeft <= 2;
        next.disabled = track.scrollLeft >= maxScroll;
    }

    function scrollCarousel(root, trackSel, dir, gapFallback, updateFn) {
        var track = root.querySelector(trackSel);
        if (!track) {
            return;
        }
        var card = track.querySelector('.cral-cal-product');
        var styles = window.getComputedStyle(track);
        var gap = parseFloat(styles.columnGap || styles.gap) || gapFallback;
        var step = card ? (card.getBoundingClientRect().width + gap) : Math.max(200, track.clientWidth * 0.85);
        var visible = Math.max(1, Math.floor((track.clientWidth + gap) / step));
        track.scrollBy({ left: dir * step * visible, behavior: 'smooth' });
        window.setTimeout(updateFn, 320);
    }

    function updateUpcomingNav(root) {
        updateCarouselNav(root, '[data-cal-upcoming]', '[data-cal-up-prev]', '[data-cal-up-next]');
    }

    function updatePastNav(root) {
        updateCarouselNav(root, '[data-cal-past]', '[data-cal-past-prev]', '[data-cal-past-next]');
    }

    function scrollUpcoming(root, dir) {
        scrollCarousel(root, '[data-cal-upcoming]', dir, 18, function () {
            updateUpcomingNav(root);
        });
    }

    function scrollPast(root, dir) {
        scrollCarousel(root, '[data-cal-past]', dir, 10, function () {
            updatePastNav(root);
        });
    }

    function bindTrackCarousel(root, trackSel, updateFn) {
        var track = root.querySelector(trackSel);
        if (!track) {
            return;
        }
        if (track._cralCarouselBound) {
            updateFn();
            return;
        }
        track._cralCarouselBound = true;
        track.addEventListener('scroll', updateFn, { passive: true });
        window.addEventListener('resize', updateFn);
        bindCarouselDrag(track, updateFn);
        updateFn();
    }

    /**
     * Drag con mouse + swipe nativo touch (overflow).
     * Il drag parte solo dopo una soglia: il click sulla scheda resta cliccabile.
     */
    function bindCarouselDrag(track, updateFn) {
        var pending = false;
        var dragging = false;
        var suppressClick = false;
        var startX = 0;
        var startScroll = 0;
        var activePointer = null;
        var DRAG_THRESHOLD = 3;

        function setDraggingUi(on) {
            track.classList.toggle('is-dragging', !!on);
            if (on) {
                track.style.scrollBehavior = 'auto';
                track.style.scrollSnapType = 'none';
            } else {
                track.style.scrollBehavior = '';
                track.style.scrollSnapType = '';
            }
        }

        function resetDragState() {
            pending = false;
            dragging = false;
            activePointer = null;
            setDraggingUi(false);
        }

        track.addEventListener('pointerdown', function (e) {
            if (e.pointerType !== 'mouse' || e.button !== 0) {
                return;
            }
            // Non intercettare subito: aspetta il movimento.
            pending = true;
            dragging = false;
            suppressClick = false;
            startX = e.clientX;
            startScroll = track.scrollLeft;
            activePointer = e.pointerId;
        });

        track.addEventListener('pointermove', function (e) {
            if ((!pending && !dragging) || e.pointerId !== activePointer) {
                return;
            }

            var dx = e.clientX - startX;

            if (!dragging) {
                if (Math.abs(dx) < DRAG_THRESHOLD) {
                    return;
                }
                dragging = true;
                pending = false;
                suppressClick = true;
                try {
                    track.setPointerCapture(e.pointerId);
                } catch (err) { /* ignore */ }
                setDraggingUi(true);
                // Riallinea lo scroll al punto di partenza del drag effettivo.
                startScroll = track.scrollLeft;
                startX = e.clientX;
                dx = 0;
            }

            track.scrollLeft = startScroll - dx;
        });

        function endDrag(e) {
            if (activePointer !== null && e && e.pointerId !== activePointer) {
                return;
            }
            var wasDragging = dragging;
            resetDragState();
            if (wasDragging) {
                updateFn();
            }
        }

        track.addEventListener('pointerup', endDrag);
        track.addEventListener('pointercancel', endDrag);
        track.addEventListener('lostpointercapture', function (e) {
            if (dragging) {
                endDrag(e);
            } else {
                resetDragState();
            }
        });

        // Blocca il click solo se c'è stato un vero drag.
        track.addEventListener('click', function (e) {
            if (!suppressClick) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            suppressClick = false;
        }, true);
    }

    function bindUpcomingCarousel(root) {
        bindTrackCarousel(root, '[data-cal-upcoming]', function () {
            updateUpcomingNav(root);
        });
    }

    function bindPastCarousel(root) {
        bindTrackCarousel(root, '[data-cal-past]', function () {
            updatePastNav(root);
        });
    }

    function bindInteractive(root) {
        if (root._cralBound) {
            return;
        }
        root._cralBound = true;

        var initial = parseEventsJson(root);
        root._cralEventsMap = indexEvents(initial.flat || []);
        bindUpcomingCarousel(root);
        bindPastCarousel(root);

        root.addEventListener('click', function (e) {
            var card = e.target.closest('.cral-cal__event-card');
            if (card) {
                e.preventDefault();
                e.stopPropagation();
                var dayFromCard = card.closest('.cral-cal__cell--day');
                if (dayFromCard) {
                    handleSelectDay(root, dayFromCard.getAttribute('data-cal-day'));
                }
                return;
            }

            var dayCell = e.target.closest('.cral-cal__cell--day.has-events');
            if (dayCell) {
                e.preventDefault();
                handleSelectDay(root, dayCell.getAttribute('data-cal-day'));
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

            var todayBtn = e.target.closest('[data-cal-today]');
            if (todayBtn) {
                e.preventDefault();
                goToToday(root);
                return;
            }

            var close = e.target.closest('[data-cal-modal-close]');
            if (close) {
                closeModal(root);
                return;
            }

            var upPrev = e.target.closest('[data-cal-up-prev]');
            if (upPrev) {
                e.preventDefault();
                scrollUpcoming(root, -1);
                return;
            }

            var upNext = e.target.closest('[data-cal-up-next]');
            if (upNext) {
                e.preventDefault();
                scrollUpcoming(root, 1);
                return;
            }

            var pastPrev = e.target.closest('[data-cal-past-prev]');
            if (pastPrev) {
                e.preventDefault();
                scrollPast(root, -1);
                return;
            }

            var pastNext = e.target.closest('[data-cal-past-next]');
            if (pastNext) {
                e.preventDefault();
                scrollPast(root, 1);
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
                    handleSelectDay(root, cell.getAttribute('data-cal-day'));
                }
                return;
            }

            var dayCell = e.target.closest('.cral-cal__cell--day.has-events');
            if (dayCell && (e.key === 'Enter' || e.key === ' ')) {
                e.preventDefault();
                handleSelectDay(root, dayCell.getAttribute('data-cal-day'));
            }
        });
    }

    function init() {
        document.querySelectorAll('.cral-cal:not(.cral-cal--admin)').forEach(function (root) {
            bindInteractive(root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
