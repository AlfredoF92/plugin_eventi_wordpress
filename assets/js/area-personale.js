(function () {
    'use strict';

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

    function bindDrag(track, updateFn) {
        if (!track) {
            return;
        }
        var dragging = false;
        var moved = false;
        var startX = 0;
        var startScroll = 0;

        track.addEventListener('pointerdown', function (e) {
            if (e.button !== 0) {
                return;
            }
            dragging = true;
            moved = false;
            startX = e.clientX;
            startScroll = track.scrollLeft;
            track.classList.add('is-dragging');
            try {
                track.setPointerCapture(e.pointerId);
            } catch (err) {}
        });

        track.addEventListener('pointermove', function (e) {
            if (!dragging) {
                return;
            }
            var dx = e.clientX - startX;
            if (Math.abs(dx) > 3) {
                moved = true;
            }
            track.scrollLeft = startScroll - dx;
        });

        function endDrag(e) {
            if (!dragging) {
                return;
            }
            dragging = false;
            track.classList.remove('is-dragging');
            try {
                track.releasePointerCapture(e.pointerId);
            } catch (err) {}
            updateFn();
        }

        track.addEventListener('pointerup', endDrag);
        track.addEventListener('pointercancel', endDrag);
        track.addEventListener('click', function (e) {
            if (moved) {
                e.preventDefault();
                e.stopPropagation();
                moved = false;
            }
        }, true);
        track.addEventListener('scroll', function () {
            updateFn();
        }, { passive: true });
    }

    function initArea(root) {
        function updateUp() {
            updateCarouselNav(root, '[data-area-upcoming]', '[data-area-up-prev]', '[data-area-up-next]');
        }
        function updatePast() {
            updateCarouselNav(root, '[data-area-past]', '[data-area-past-prev]', '[data-area-past-next]');
        }

        bindDrag(root.querySelector('[data-area-upcoming]'), updateUp);
        bindDrag(root.querySelector('[data-area-past]'), updatePast);

        root.addEventListener('click', function (e) {
            if (e.target.closest('[data-area-up-prev]')) {
                scrollCarousel(root, '[data-area-upcoming]', -1, 18, updateUp);
            } else if (e.target.closest('[data-area-up-next]')) {
                scrollCarousel(root, '[data-area-upcoming]', 1, 18, updateUp);
            } else if (e.target.closest('[data-area-past-prev]')) {
                scrollCarousel(root, '[data-area-past]', -1, 10, updatePast);
            } else if (e.target.closest('[data-area-past-next]')) {
                scrollCarousel(root, '[data-area-past]', 1, 10, updatePast);
            }
        });

        updateUp();
        updatePast();
        window.addEventListener('resize', function () {
            updateUp();
            updatePast();
        });
    }

    function boot() {
        document.querySelectorAll('.cral-area').forEach(initArea);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
