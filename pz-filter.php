<?php
/**
 * Plugin Name: Passing.zone Filter
 * Description: AJAX filter for the CPT "Patterns". Filter by number of jugglers, difficulty, pattern type and pattern tags. Sort by title, date and random.
 * Version:     1.2.0
 * Author:      Juliane de Vries
*/

if (!defined('ABSPATH')) exit;

// ─────────────────────────────────────────────────────────────────────────────
// 0. SHARED HELPERS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns the pattern-type taxonomy as a parent→children tree.
 * Within each child group, terms ending in "-about" sort first, then alpha.
 */
function pz_get_type_tree(): array {
    $flat = get_terms( [ 'taxonomy' => 'pattern-type', 'hide_empty' => false, 'orderby' => 'name' ] );
    $tree = [];
    foreach ( (array) $flat as $term ) {
        $tree[ $term->parent ][] = $term;
    }
    foreach ( $tree as $parent_id => &$children ) {
        if ( $parent_id === 0 ) continue;
        usort( $children, function ( $a, $b ) {
            $a_first = str_contains( $a->slug, 'about' ) ? 0 : 1;
            $b_first = str_contains( $b->slug, 'about' ) ? 0 : 1;
            return $a_first !== $b_first ? $a_first - $b_first : strnatcmp( $a->name, $b->name );
        } );
    }
    unset( $children );
    return $tree;
}


// ─────────────────────────────────────────────────────────────────────────────
// 1. SHORTCODE
//    Usage in Beaver Builder: HTML module → [pz_filter]
// ─────────────────────────────────────────────────────────────────────────────

function pz_filter_shortcode() {

    // Load all available terms for the dropdowns
    $jugglers   = get_terms(['taxonomy' => 'number-of-jugglers', 'hide_empty' => true,  'orderby' => 'name']);
    $difficulty = get_terms(['taxonomy' => 'pattern-difficulty', 'hide_empty' => true,  'orderby' => 'name']);
    $tags       = get_terms(['taxonomy' => 'pattern-tag',        'hide_empty' => true,  'orderby' => 'name']);

    $types_tree = pz_get_type_tree();

    // Pre-fill filters from URL params (redirected from [pz_finder])
    $sel_jugglers   = isset($_GET['jugglers'])   ? sanitize_text_field(wp_unslash($_GET['jugglers']))   : '';
    $sel_difficulty = isset($_GET['difficulty']) ? sanitize_text_field(wp_unslash($_GET['difficulty'])) : '';
    $sel_type       = isset($_GET['type'])       ? sanitize_text_field(wp_unslash($_GET['type']))       : '';
    $sel_tag        = isset($_GET['tag'])        ? sanitize_text_field(wp_unslash($_GET['tag']))        : '';

    ob_start(); ?>

    <div class="pz-filter-wrap" data-ajaxurl="<?= esc_url(admin_url('admin-ajax.php')) ?>" data-nonce="<?= esc_attr(wp_create_nonce('pz_filter_nonce')) ?>">

        <!-- Filter bar -->
        <div class="pz-filter-controls">

            <!-- Row 1: taxonomy dropdowns -->
            <div class="pz-filter-field">
                <label for="pz-filter-jugglers">Number of jugglers</label>
                <select id="pz-filter-jugglers">
                    <option value="">All</option>
                    <?php foreach ((array) $jugglers as $term) : ?>
                        <option value="<?= esc_attr($term->slug) ?>" <?= selected($sel_jugglers, $term->slug, false) ?>><?= esc_html($term->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pz-filter-field">
                <label for="pz-filter-difficulty">Difficulty</label>
                <select id="pz-filter-difficulty">
                    <option value="">All</option>
                    <?php foreach ((array) $difficulty as $term) : ?>
                        <option value="<?= esc_attr($term->slug) ?>" <?= selected($sel_difficulty, $term->slug, false) ?>><?= esc_html(preg_replace('/^\d+\s*/', '', $term->name)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pz-filter-field">
                <label for="pz-filter-type">Type</label>
                <select id="pz-filter-type">
                    <option value="">All</option>
                    <?php foreach ($types_tree[0] ?? [] as $parent) : ?>
                        <option value="<?= esc_attr($parent->slug) ?>" <?= selected($sel_type, $parent->slug, false) ?>><?= esc_html($parent->name) ?></option>
                        <?php foreach ($types_tree[$parent->term_id] ?? [] as $child) : ?>
                            <option value="<?= esc_attr($child->slug) ?>" <?= selected($sel_type, $child->slug, false) ?>>&nbsp;&nbsp;— <?= esc_html($child->name) ?></option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="pz-filter-field">
                <label for="pz-filter-tag">Tag</label>
                <select id="pz-filter-tag">
                    <option value="">All</option>
                    <?php foreach ((array) $tags as $term) : ?>
                        <option value="<?= esc_attr($term->slug) ?>" <?= selected($sel_tag, $term->slug, false) ?>><?= esc_html($term->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Row 2: search + sort + reset -->
            <div class="pz-filter-field pz-filter-field--name">
                <label for="pz-filter-name">Name</label>
                <input
                    type="text"
                    id="pz-filter-name"
                    placeholder="Search name…"
                    autocomplete="off"
                />
            </div>

            <div class="pz-filter-field pz-filter-field--sort">
                <label for="pz-filter-sort">Sort by</label>
                <select id="pz-filter-sort">
                    <option value="title_asc">Title A–Z</option>
                    <option value="title_desc">Title Z–A</option>
                    <option value="date_desc" selected>Newest first</option>
                    <option value="date_asc">Oldest first</option>
                    <option value="difficulty_asc">Difficulty (easy first)</option>
                    <option value="difficulty_desc">Difficulty (hard first)</option>
                    <option value="random">Random</option>
                </select>
            </div>

            <div class="pz-filter-field pz-filter-field--reset">
                <button class="btn-primary btn-reset" type="button" id="pz-filter-reset">Reset filter</button>
            </div>

        </div><!-- .pz-filter-controls -->

        <!-- Result counter -->
        <p class="pz-results-count"></p>

        <!-- Card grid (initially populated, pre-filtered if URL params present) -->
        <div id="pz-results" class="pz-grid">
            <?= pz_render_cards('', $sel_jugglers, $sel_difficulty, $sel_type, $sel_tag) ?>
        </div>

        <!-- No results message -->
        <div id="pz-no-results" style="display:none;">
            <p>No patterns found for these filters. Please try again.</p>
        </div>

        <!-- Load more -->
        <div id="pz-load-more-wrap" style="display:none;">
            <button class="btn-primary btn-show-more" type="button" id="pz-load-more">Load more</button>
        </div>

    </div><!-- .pz-filter-wrap -->

    <?php
    return ob_get_clean();
}
add_shortcode('pz_filter', 'pz_filter_shortcode');


// ─────────────────────────────────────────────────────────────────────────────
// 2. RENDER CARDS
//    Generates the HTML for all matching entries.
// ─────────────────────────────────────────────────────────────────────────────

function pz_term_post_ids( string $taxonomy, string $slug ): array {
    global $wpdb;
    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT tr.object_id
           FROM {$wpdb->term_relationships} tr
           JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
           JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
          WHERE tt.taxonomy = %s AND t.slug = %s",
        $taxonomy, $slug
    ) );
    return array_map( 'intval', $ids );
}

function pz_render_cards( string $name = '', string $jugglers = '', string $difficulty = '', string $type = '', string $tag = '', string $sort = 'date_desc' ): string {

    $args = [
        'post_type'      => 'pattern',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'no_found_rows'  => true, // Performance: no pagination needed
    ];

    if ( $sort === 'random' ) {
        $args['orderby'] = 'rand';
    } else {
        $orderby_map = [
            'title_asc'       => ['title' => 'ASC'],
            'title_desc'      => ['title' => 'DESC'],
            'date_asc'        => ['date'  => 'ASC'],
            'date_desc'       => ['date'  => 'DESC'],
            'difficulty_asc'  => ['date'  => 'DESC'], // re-sorted in PHP below
            'difficulty_desc' => ['date'  => 'DESC'], // re-sorted in PHP below
        ];
        $args['orderby'] = $orderby_map[ $sort ] ?? ['date' => 'DESC'];
    }

    // ── Name search (against post_title) ──
    if ( $name !== '' ) {
        $args['s'] = $name;
    }

    // ── Taxonomy filter via direct DB query ──
    // tax_query is ignored in the admin-ajax.php context when taxonomies are
    // not registered there — direct SQL works in any context.
    if ( $jugglers !== '' || $difficulty !== '' || $type !== '' || $tag !== '' ) {
        $filtered_ids = null;

        if ( $jugglers !== '' ) {
            $ids          = pz_term_post_ids( 'number-of-jugglers', $jugglers );
            $filtered_ids = $filtered_ids === null ? $ids : array_intersect( $filtered_ids, $ids );
        }

        if ( $difficulty !== '' ) {
            $ids          = pz_term_post_ids( 'pattern-difficulty', $difficulty );
            $filtered_ids = $filtered_ids === null ? $ids : array_intersect( $filtered_ids, $ids );
        }

        if ( $type !== '' ) {
            $ids          = pz_term_post_ids( 'pattern-type', $type );
            $filtered_ids = $filtered_ids === null ? $ids : array_intersect( $filtered_ids, $ids );
        }

        if ( $tag !== '' ) {
            $ids          = pz_term_post_ids( 'pattern-tag', $tag );
            $filtered_ids = $filtered_ids === null ? $ids : array_intersect( $filtered_ids, $ids );
        }

        if ( empty( $filtered_ids ) ) {
            return '';
        }

        $args['post__in'] = array_values( $filtered_ids );
    }

    $query = new WP_Query($args);

    if ( ! $query->have_posts() ) {
        return '';
    }

    // WP_Query cannot ORDER BY taxonomy term — sort the posts array in PHP.
    // the_post() reads from $query->posts sequentially, so in-place sort works.
    if ( $sort === 'difficulty_asc' || $sort === 'difficulty_desc' ) {
        // Rank map matches the star SVG assignments in style.css
        $difficulty_rank = [
            'beginner'     => 1,
            'intermediate' => 2,
            'advanced'     => 3,
            'expert'       => 4,
        ];
        $dir = $sort === 'difficulty_asc' ? 1 : -1;
        $ranks = [];
        foreach ( $query->posts as $p ) {
            $t = get_the_terms( $p->ID, 'pattern-difficulty' );
            if ( $t && ! is_wp_error( $t ) ) {
                $class = preg_replace( '/^\d+-/', '', sanitize_html_class( $t[0]->slug ) );
                $ranks[ $p->ID ] = $difficulty_rank[ $class ] ?? PHP_INT_MAX;
            } else {
                $ranks[ $p->ID ] = PHP_INT_MAX;
            }
        }
        usort( $query->posts, function( $a, $b ) use ( $ranks, $dir ) {
            return ( $ranks[ $a->ID ] - $ranks[ $b->ID ] ) * $dir;
        } );
    }

    $output     = '';
    $card_index = 0;

    while ( $query->have_posts() ) {
        $query->the_post();
        $id = get_the_ID();

        // Terms for the badges
        $juggler_terms = get_the_terms($id, 'number-of-jugglers');
        $juggler_terms = ( $juggler_terms && ! is_wp_error($juggler_terms) ) ? $juggler_terms : [];

        $difficulty_terms = get_the_terms($id, 'pattern-difficulty');
        $difficulty_terms = ( $difficulty_terms && ! is_wp_error($difficulty_terms) ) ? $difficulty_terms : [];

        $type_terms = get_the_terms($id, 'pattern-type');
        $type_terms = ( $type_terms && ! is_wp_error($type_terms) ) ? $type_terms : [];

        $tag_terms  = get_the_terms($id, 'pattern-tag');
        $tag_terms  = ( $tag_terms && ! is_wp_error($tag_terms) ) ? $tag_terms : [];

        // ── Card ──
        // Tags are outside the card <a> to allow valid nested links.
        $url = esc_url( get_permalink() );

        $hidden = $card_index >= 100 ? ' pz-card--hidden' : '';
        $output .= '<article class="pz-card' . $hidden . '">';
        $output .= '<a href="' . $url . '" class="pz-card__link">';
        $output .= '<h3 class="pz-card__name">' . esc_html( get_the_title() ) . '</h3>';
        $output .= '</a>';

        $output .= '<div class="pz-card__tags">';
        foreach ( $difficulty_terms as $term ) {
            // Strip leading number from slug (e.g. "4-expert" → "expert") to match CSS classes
            $class = preg_replace( '/^\d+-/', '', sanitize_html_class( $term->slug ) );
            $output .= '<span class="pz-card__tag pz-card__tag--difficulty stars">'
                . '<i class="' . esc_attr( $class ) . '" title="' . esc_attr( preg_replace( '/^\d+\s*/', '', $term->name ) ) . '"></i>'
                . '</span>';
        }
        foreach ( $juggler_terms as $term ) {
            $link = get_term_link( $term );
            if ( is_wp_error( $link ) ) {
                $output .= '<span class="pz-card__tag pz-card__tag--jugglers">' . esc_html( $term->name ) . '</span>';
            } else {
                $output .= '<a href="' . esc_url( $link ) . '" class="pz-card__tag pz-card__tag--jugglers">' . esc_html( $term->name ) . '</a>';
            }
        }
        foreach ( $type_terms as $term ) {
            $link = get_term_link( $term );
            if ( is_wp_error( $link ) ) {
                $output .= '<span class="pz-card__tag pz-card__tag--type">' . esc_html( $term->name ) . '</span>';
            } else {
                $output .= '<a href="' . esc_url( $link ) . '" class="pz-card__tag pz-card__tag--type">' . esc_html( $term->name ) . '</a>';
            }
        }
        foreach ( $tag_terms as $term ) {
            $link = get_term_link( $term );
            if ( is_wp_error( $link ) ) {
                $output .= '<span class="pz-card__tag pz-card__tag--tag">' . esc_html( $term->name ) . '</span>';
            } else {
                $output .= '<a href="' . esc_url( $link ) . '" class="pz-card__tag pz-card__tag--tag">' . esc_html( $term->name ) . '</a>';
            }
        }
        $output .= '</div>';

        $output .= '</article>'; // .pz-card
        $card_index++;
    }

    wp_reset_postdata();

    return $output;
}


// ─────────────────────────────────────────────────────────────────────────────
// 3. FINDER SHORTCODE
//    Stand-alone filter picker that redirects to /patterns with URL params.
//    Usage: [pz_finder] or [pz_finder target="/patterns"]
// ─────────────────────────────────────────────────────────────────────────────

function pz_finder_shortcode( $atts ): string {
    $atts = shortcode_atts( [ 'target' => '/patterns' ], $atts, 'pz_finder' );

    $jugglers   = get_terms( [ 'taxonomy' => 'number-of-jugglers', 'hide_empty' => true,  'orderby' => 'name' ] );
    $difficulty = get_terms( [ 'taxonomy' => 'pattern-difficulty',  'hide_empty' => true,  'orderby' => 'name' ] );
    $tags       = get_terms( [ 'taxonomy' => 'pattern-tag',         'hide_empty' => true,  'orderby' => 'name' ] );

    $types_tree = pz_get_type_tree();

    ob_start(); ?>

    <div class="pz-finder-wrap"
         data-target="<?= esc_attr( $atts['target'] ) ?>"
         data-ajaxurl="<?= esc_url( admin_url('admin-ajax.php') ) ?>"
         data-nonce="<?= esc_attr( wp_create_nonce('pz_filter_nonce') ) ?>">

        <!-- ── Juggler slider ── -->
        <div class="pz-finder-section">
            <div class="pz-finder-slider-outer">
                <button class="pz-finder-nav pz-finder-prev" type="button" aria-label="Previous">
                    <svg width="8" height="14" viewBox="0 0 8 14" fill="none" aria-hidden="true">
                        <path d="M7 1L1 7L7 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="pz-finder-slider-viewport">
                    <div class="pz-finder-slider-track">
                        <?php foreach ( (array) $jugglers as $term ) : ?>
                        <button class="pz-finder-btn pz-finder-juggler-btn" type="button"
                                data-filter="jugglers" data-value="<?= esc_attr( $term->slug ) ?>">
                            <?= esc_html( $term->name ) ?> <span class="pz-finder-count"><?= esc_html( $term->count ) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="pz-finder-nav pz-finder-next" type="button" aria-label="Next">
                    <svg width="8" height="14" viewBox="0 0 8 14" fill="none" aria-hidden="true">
                        <path d="M1 1L7 7L1 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- ── Difficulty buttons (SVG stars) ── -->
        <div class="pz-finder-section pz-finder-diff-grid">
            <?php foreach ( (array) $difficulty as $term ) :
                $class = preg_replace( '/^\d+-/', '', sanitize_html_class( $term->slug ) );
            ?>
            <button class="pz-finder-btn pz-finder-diff-btn" type="button"
                    data-filter="difficulty" data-value="<?= esc_attr( $term->slug ) ?>">
                <span class="pz-finder-stars stars"><i class="<?= esc_attr( $class ) ?>" title="<?= esc_attr( preg_replace( '/^\d+\s*/', '', $term->name ) ) ?>"></i></span>
                <span class="pz-finder-count"><?= esc_html( $term->count ) ?></span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- ── Type accordion ── -->
        <div class="pz-finder-section pz-finder-acc">
            <button class="pz-finder-acc-toggle" type="button" data-body="pz-finder-type-body" aria-expanded="false">
                Type
                <svg class="pz-finder-chevron" width="12" height="8" viewBox="0 0 12 8" fill="none" aria-hidden="true">
                    <path d="M1 1L6 7L11 1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <div class="pz-finder-acc-body pz-finder-acc-body--tree" id="pz-finder-type-body" style="display:none;">
                <?php foreach ( $types_tree[0] ?? [] as $parent ) : ?>
                <button class="pz-finder-btn pz-finder-term-btn" type="button"
                        data-filter="type" data-value="<?= esc_attr( $parent->slug ) ?>">
                    <?= esc_html( $parent->name ) ?><span class="pz-finder-count"><?= esc_html( $parent->count ) ?></span>
                </button>
                <?php foreach ( $types_tree[ $parent->term_id ] ?? [] as $child ) : ?>
                <button class="pz-finder-btn pz-finder-term-btn pz-finder-term-btn--child" type="button"
                        data-filter="type" data-value="<?= esc_attr( $child->slug ) ?>">
                    <?= esc_html( $child->name ) ?><span class="pz-finder-count"><?= esc_html( $child->count ) ?></span>
                </button>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── Tags accordion ── -->
        <div class="pz-finder-section pz-finder-acc">
            <button class="pz-finder-acc-toggle" type="button" data-body="pz-finder-tags-body" aria-expanded="false">
                Tags
                <svg class="pz-finder-chevron" width="12" height="8" viewBox="0 0 12 8" fill="none" aria-hidden="true">
                    <path d="M1 1L6 7L11 1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <div class="pz-finder-acc-body" id="pz-finder-tags-body" style="display:none;">
                <?php foreach ( (array) $tags as $term ) : ?>
                <button class="pz-finder-btn pz-finder-term-btn" type="button"
                        data-filter="tag" data-value="<?= esc_attr( $term->slug ) ?>">
                    <?= esc_html( $term->name ) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── Footer: Reset + Find ── -->
        <div class="pz-finder-footer">
            <button class="pz-finder-reset" type="button">Reset Filters</button>
            <button class="pz-finder-find" type="button">Find Patterns</button>
        </div>

    </div><!-- .pz-finder-wrap -->

    <?php
    return ob_get_clean();
}
add_shortcode( 'pz_finder', 'pz_finder_shortcode' );


// ─────────────────────────────────────────────────────────────────────────────
// 4. ARCHIVE SHORTCODE
//    Simplified version for taxonomy archive pages: search + sort only.
//    Usage in Beaver Builder Themer layout for pattern taxonomies: [pz_archive]
// ─────────────────────────────────────────────────────────────────────────────

function pz_render_cards_archive( string $taxonomy, string $term_slug, string $name = '', string $sort = 'date_desc' ): string {
    $jugglers   = $taxonomy === 'number-of-jugglers' ? $term_slug : '';
    $difficulty = $taxonomy === 'pattern-difficulty'  ? $term_slug : '';
    $type       = $taxonomy === 'pattern-type'        ? $term_slug : '';
    $tag        = $taxonomy === 'pattern-tag'         ? $term_slug : '';
    return pz_render_cards( $name, $jugglers, $difficulty, $type, $tag, $sort );
}

function pz_archive_shortcode(): string {
    $term = get_queried_object();

    if ( ! ( $term instanceof WP_Term ) ) {
        return '';
    }

    ob_start(); ?>

    <div class="pz-filter-wrap"
         data-ajaxurl="<?= esc_url( admin_url('admin-ajax.php') ) ?>"
         data-nonce="<?= esc_attr( wp_create_nonce('pz_filter_nonce') ) ?>"
         data-archive-taxonomy="<?= esc_attr( $term->taxonomy ) ?>"
         data-archive-term="<?= esc_attr( $term->slug ) ?>">

        <div class="pz-filter-controls pz-filter-controls--archive">

            <div class="pz-filter-field pz-filter-field--sort">
                <label for="pz-filter-sort">Sort by</label>
                <select id="pz-filter-sort">
                    <option value="title_asc">Title A–Z</option>
                    <option value="title_desc">Title Z–A</option>
                    <option value="date_desc" selected>Newest first</option>
                    <option value="date_asc">Oldest first</option>
                    <option value="difficulty_asc">Difficulty (easy first)</option>
                    <option value="difficulty_desc">Difficulty (hard first)</option>
                    <option value="random">Random</option>
                </select>
            </div>

        </div>

        <p class="pz-results-count"></p>

        <div id="pz-results" class="pz-grid">
            <?= pz_render_cards_archive( $term->taxonomy, $term->slug ) ?>
        </div>

        <div id="pz-no-results" style="display:none;">
            <p>No patterns found for these filters. Please try again.</p>
        </div>

        <div id="pz-load-more-wrap" style="display:none;">
            <button class="btn-primary btn-show-more" type="button" id="pz-load-more">Load more</button>
        </div>

    </div>

    <?php
    return ob_get_clean();
}
add_shortcode( 'pz_archive', 'pz_archive_shortcode' );


// ─────────────────────────────────────────────────────────────────────────────
// 4. AJAX HANDLER
//    Responds to JS requests with freshly rendered cards.
// ─────────────────────────────────────────────────────────────────────────────

function pz_ajax_filter(): void {

    // Security check
    check_ajax_referer('pz_filter_nonce', 'nonce');

    $name             = isset($_POST['name'])             ? sanitize_text_field(wp_unslash($_POST['name']))             : '';
    $sort             = isset($_POST['sort'])             ? sanitize_text_field(wp_unslash($_POST['sort']))             : 'date_desc';
    $archive_taxonomy = isset($_POST['archive_taxonomy']) ? sanitize_text_field(wp_unslash($_POST['archive_taxonomy'])) : '';
    $archive_term     = isset($_POST['archive_term'])     ? sanitize_text_field(wp_unslash($_POST['archive_term']))     : '';

    if ( $archive_taxonomy !== '' && $archive_term !== '' ) {
        $html = pz_render_cards_archive( $archive_taxonomy, $archive_term, $name, $sort );
    } else {
        $jugglers   = isset($_POST['jugglers'])   ? sanitize_text_field(wp_unslash($_POST['jugglers']))   : '';
        $difficulty = isset($_POST['difficulty']) ? sanitize_text_field(wp_unslash($_POST['difficulty'])) : '';
        $type       = isset($_POST['type'])       ? sanitize_text_field(wp_unslash($_POST['type']))       : '';
        $tag        = isset($_POST['tag'])        ? sanitize_text_field(wp_unslash($_POST['tag']))        : '';
        $html = pz_render_cards( $name, $jugglers, $difficulty, $type, $tag, $sort );
    }

    $count = $html ? substr_count($html, '<article class="pz-card') : 0;

    wp_send_json_success([
        'html'  => $html,
        'count' => $count,
    ]);
}

add_action('wp_ajax_pz_filter',        'pz_ajax_filter'); // logged-in users
add_action('wp_ajax_nopriv_pz_filter', 'pz_ajax_filter'); // visitors (important!)


// ─────────────────────────────────────────────────────────────────────────────
// 4. ENQUEUE ASSETS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns true when the shortcode is present anywhere on the current request:
 * – regular post/page content
 * – any published Beaver Builder Themer Layout (result is transient-cached)
 */
function pz_shortcode_is_needed(): bool {
    global $post;

    // Regular post/page with the shortcode directly in its content
    if ( is_a( $post, 'WP_Post' ) &&
         ( has_shortcode( $post->post_content, 'pz_filter' )  ||
           has_shortcode( $post->post_content, 'pz_archive' ) ||
           has_shortcode( $post->post_content, 'pz_finder' ) ) ) {
        return true;
    }

    // Beaver Builder stores module content in _fl_builder_data post-meta.
    // We search every published fl-theme-layout once and cache the result.
    $cached = get_transient( 'pz_filter_in_themer_layout' );
    if ( $cached !== false ) {
        return (bool) $cached;
    }

    $layout_ids = get_posts( [
        'post_type'      => 'fl-theme-layout',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ] );

    foreach ( $layout_ids as $id ) {
        $bb_data = get_post_meta( $id, '_fl_builder_data', true );
        $serialized = maybe_serialize( $bb_data );
        if ( $bb_data && ( str_contains( $serialized, 'pz_filter' ) || str_contains( $serialized, 'pz_archive' ) || str_contains( $serialized, 'pz_finder' ) ) ) {
            set_transient( 'pz_filter_in_themer_layout', 1, DAY_IN_SECONDS );
            return true;
        }
    }

    set_transient( 'pz_filter_in_themer_layout', 0, DAY_IN_SECONDS );
    return false;
}

// Invalidate the cache whenever a Themer Layout is saved
add_action( 'save_post_fl-theme-layout', function () {
    delete_transient( 'pz_filter_in_themer_layout' );
} );

function pz_enqueue_assets(): void {

    if ( ! pz_shortcode_is_needed() ) {
        return;
    }

    $base = plugin_dir_url( __FILE__ );

    wp_enqueue_style(
        'pz-filter-style',
        $base . 'pz-filter.css',
        [],
        '1.5'
    );

    wp_enqueue_script(
        'pz-filter-script',
        $base . 'pz-filter.js',
        ['jquery'],
        '1.4',
        true
    );

    wp_localize_script( 'pz-filter-script', 'pzFilter', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'pz_filter_nonce' ),
        'i18n'    => [
            'result'  => 'pattern found',
            'results' => 'patterns found',
        ],
    ] );

    wp_enqueue_script(
        'pz-finder-script',
        $base . 'pz-finder.js',
        ['jquery'],
        '1.1',
        true
    );
}
add_action( 'wp_enqueue_scripts', 'pz_enqueue_assets' );
