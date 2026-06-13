/**
 * pz-finder.js
 * Filter picker that builds a URL and redirects to the patterns page.
 */

(function ($) {
    'use strict';

    const $wrap = $('.pz-finder-wrap');
    if (!$wrap.length) return;

    const target   = $wrap.data('target')   || '/patterns';
    const ajaxurl  = $wrap.data('ajaxurl');
    const nonce    = $wrap.data('nonce');
    const state    = { jugglers: '', difficulty: '', type: '', tag: '' };
    const $findBtn = $wrap.find('.pz-finder-find');
    let   countTimer;

    // ── Juggler slider ────────────────────────────────────────────────────────

    const $viewport = $wrap.find('.pz-finder-slider-viewport');
    const $track    = $wrap.find('.pz-finder-slider-track');
    const $jugItems = $track.find('.pz-finder-juggler-btn');
    const $prev     = $wrap.find('.pz-finder-prev');
    const $next     = $wrap.find('.pz-finder-next');
    const GAP       = 8; // must match CSS gap on .pz-finder-slider-track
    let   slideIdx  = 0;

    // Pixel offset to scroll item at `idx` to the left edge of the viewport
    function calcOffset(idx) {
        let offset = 0;
        $jugItems.each(function (i) {
            if (i >= idx) return false;
            offset += $(this).outerWidth() + GAP;
        });
        return offset;
    }

    // True when all items from `idx` onwards fit inside the viewport (no overflow)
    function fitFrom(idx) {
        const viewW = $viewport.width();
        let total = 0;
        let fits = true;
        $jugItems.each(function (i) {
            if (i < idx) return;
            total += (total === 0 ? $(this).outerWidth() : $(this).outerWidth() + GAP);
            if (total > viewW) { fits = false; return false; }
        });
        return fits;
    }

    function slideUpdate() {
        if (!$jugItems.length) return;
        $track.css('transform', 'translateX(' + (-calcOffset(slideIdx)) + 'px)');
        const atStart = slideIdx === 0;
        const atEnd   = fitFrom(slideIdx);
        $prev.prop('disabled', atStart).toggleClass('pz-finder-nav--disabled', atStart);
        $next.prop('disabled', atEnd).toggleClass('pz-finder-nav--disabled', atEnd);
    }

    $prev.on('click', function () {
        if (slideIdx > 0) { slideIdx--; slideUpdate(); }
    });

    $next.on('click', function () {
        if (!fitFrom(slideIdx)) { slideIdx++; slideUpdate(); }
    });

    let resizeTimer;
    $(window).on('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () { slideIdx = 0; slideUpdate(); }, 100);
    });

    // ── Live pattern count on Find button ────────────────────────────────────

    function updateFindCount() {
        clearTimeout(countTimer);
        countTimer = setTimeout(function () {
            $.ajax({
                url:  ajaxurl,
                type: 'POST',
                data: {
                    action:     'pz_filter',
                    nonce:      nonce,
                    jugglers:   state.jugglers,
                    difficulty: state.difficulty,
                    type:       state.type,
                    tag:        state.tag,
                },
                success: function (response) {
                    if (response.success) {
                        const n = response.data.count;
                        $findBtn.text(n > 0 ? 'Find ' + n + ' Patterns' : 'Find Patterns');
                    }
                },
            });
        }, 200);
    }

    // ── Toggle buttons (jugglers, difficulty, type, tag) ─────────────────────

    $wrap.on('click', '.pz-finder-btn[data-filter]', function () {
        const $btn   = $(this);
        const filter = $btn.data('filter');
        const val    = $btn.data('value');

        $wrap.find('.pz-finder-btn[data-filter="' + filter + '"]').not($btn).removeClass('is-active');

        if ($btn.hasClass('is-active')) {
            $btn.removeClass('is-active');
            state[filter] = '';
        } else {
            $btn.addClass('is-active');
            state[filter] = val;
        }

        updateFindCount();
    });

    // ── Accordion (Type / Tags) ───────────────────────────────────────────────

    $wrap.on('click', '.pz-finder-acc-toggle', function () {
        const $toggle = $(this);
        const $body   = $('#' + $toggle.data('body'));
        const isOpen  = $toggle.hasClass('is-open');

        $toggle.toggleClass('is-open', !isOpen).attr('aria-expanded', String(!isOpen));

        if (isOpen) {
            $body.slideUp(200);
        } else {
            $body.slideDown(200);
        }
    });

    // ── Reset ─────────────────────────────────────────────────────────────────

    $wrap.on('click', '.pz-finder-reset', function () {
        state.jugglers = state.difficulty = state.type = state.tag = '';
        $wrap.find('.pz-finder-btn').removeClass('is-active');
        updateFindCount();
    });

    // ── Find Patterns → redirect ──────────────────────────────────────────────

    $wrap.on('click', '.pz-finder-find', function () {
        const pairs = [];
        if (state.jugglers)   pairs.push('jugglers='   + encodeURIComponent(state.jugglers));
        if (state.difficulty) pairs.push('difficulty=' + encodeURIComponent(state.difficulty));
        if (state.type)       pairs.push('type='       + encodeURIComponent(state.type));
        if (state.tag)        pairs.push('tag='        + encodeURIComponent(state.tag));

        window.location.href = target + (pairs.length ? '?' + pairs.join('&') : '');
    });

    // ── Init ──────────────────────────────────────────────────────────────────

    slideUpdate();
    updateFindCount();

    // Equalize all 4 difficulty buttons to the width of the widest one.
    // Called on window load so star SVG images have rendered and affect layout.
    function equalizeDiffBtns() {
        const $btns = $wrap.find('.pz-finder-diff-btn');
        $btns.css('width', '');
        const maxW = Math.max.apply(null, $btns.map(function () { return $(this).outerWidth(); }).get());
        if (maxW > 0) $btns.css('width', maxW + 'px');
    }

    $(window).on('load', equalizeDiffBtns);
    equalizeDiffBtns();

})(jQuery);
