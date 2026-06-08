/**
 * pz-filter.js
 * AJAX filter for Patterns – Beaver Builder compatible
 */

(function ($) {
    'use strict';

    let debounceTimer;
    const $wrap = $('.pz-filter-wrap');

    if (!$wrap.length) return;

    const $results      = $('#pz-results');
    const $noResults    = $('#pz-no-results');
    const $count        = $('.pz-results-count');
    const $loadMoreWrap = $('#pz-load-more-wrap');

    // ── Collect filter values ─────────────────────────────────────────────────

    function getFilters() {
        return {
            action:           'pz_filter',
            nonce:            pzFilter.nonce,
            name:             ($('#pz-filter-name').val() || '').trim(),
            jugglers:         $('#pz-filter-jugglers').val() || '',
            difficulty:       $('#pz-filter-difficulty').val() || '',
            type:             $('#pz-filter-type').val() || '',
            tag:              $('#pz-filter-tag').val() || '',
            sort:             $('#pz-filter-sort').val(),
            archive_taxonomy: $wrap.data('archive-taxonomy') || '',
            archive_term:     $wrap.data('archive-term') || '',
        };
    }

    // ── Load more button ──────────────────────────────────────────────────────

    function updateLoadMore() {
        if ($results.find('.pz-card--hidden').length > 0) {
            $loadMoreWrap.show();
        } else {
            $loadMoreWrap.hide();
        }
    }

    // ── Result counter ────────────────────────────────────────────────────────

    function updateCount(n) {
        if (n === 0) {
            $count.text('');
            return;
        }
        const label = n === 1 ? pzFilter.i18n.result : pzFilter.i18n.results;
        $count.text(n + ' ' + label);
    }

    // ── AJAX request ──────────────────────────────────────────────────────────

    function doFilter() {
        $results.addClass('pz-loading');
        $noResults.hide();

        $.ajax({
            url:  pzFilter.ajaxurl,
            type: 'POST',
            data: getFilters(),

            success: function (response) {
                $results.removeClass('pz-loading');

                if (response.success && response.data.html) {
                    $results.html(response.data.html);
                    $noResults.hide();
                    updateCount(response.data.count);
                    updateLoadMore();
                } else {
                    $results.empty();
                    $noResults.show();
                    $loadMoreWrap.hide();
                    updateCount(0);
                }
            },

            error: function () {
                $results.removeClass('pz-loading');
                console.warn('pz-filter: AJAX error');
            },
        });
    }

    // ── Event listeners ───────────────────────────────────────────────────────

    $(document).on('change', '#pz-filter-jugglers, #pz-filter-difficulty, #pz-filter-type, #pz-filter-tag, #pz-filter-sort', function () {
        doFilter(); // Dropdowns: respond immediately
    });

    $(document).on('input', '#pz-filter-name', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(doFilter, 320); // wait 320ms before firing
    });

    $(document).on('click', '#pz-filter-reset', function () {
        $('#pz-filter-name').val('');
        $('#pz-filter-jugglers').val('');
        $('#pz-filter-difficulty').val('');
        $('#pz-filter-type').val('');
        $('#pz-filter-tag').val('');
        $('#pz-filter-sort').val('date_desc');
        clearTimeout(debounceTimer);
        doFilter();
    });

    $(document).on('click', '#pz-load-more', function () {
        $results.find('.pz-card--hidden').removeClass('pz-card--hidden');
        $loadMoreWrap.hide();
    });

    // Initial count from already-rendered HTML
    const initialCount = $results.find('.pz-card').length;
    updateCount(initialCount);
    updateLoadMore();

})(jQuery);
