<?php
if (! defined('ABSPATH')) exit;

/**
 * Registers/enqueues front assets and exposes seData for the JS app.
 */
class SE_Assets
{

  public static function enqueue_front()
  {
    wp_register_style(
      'se-style',
      SE_URL . 'assets/css/suppliers-explorer.css',
      [],
      SE_VERSION
    );

    wp_register_script(
      'se-script',
      SE_URL . 'assets/js/suppliers-explorer.js',
      [],
      SE_VERSION,
      true
    );

    $top_levels = [];
    if (taxonomy_exists('top-level-category')) {
      $top_terms = get_terms([
        'taxonomy'   => 'top-level-category',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
        'number'     => 0,
      ]);

      if (! is_wp_error($top_terms) && is_array($top_terms)) {
        foreach ($top_terms as $t) {
          $top_levels[] = [
            'slug' => $t->slug,
            'name' => $t->name,
          ];
        }
      }
    }

    $se_data = [
      'remote'    => false,
      'restBase'  => untrailingslashit(get_rest_url(null, 'suppliers/v1')),
      'termsBase' => untrailingslashit(get_rest_url(null, 'wp/v2')),

      'perPage'   => 25,

      'topLevels' => $top_levels,

      'restNonce' => wp_create_nonce('wp_rest'),

      'strings' => [
        'searchPlaceholder' => __('Type to search…', 'se'),
        'search'            => __('Search', 'se'),
        'reset'             => __('Reset',  'se'),
        'loadMore'          => __('Load more', 'se'),
        'selected'          => __('Selected',  'se'),
      ],
    ];

    wp_enqueue_style('se-style');
    wp_enqueue_script('se-script');
    wp_localize_script('se-script', 'seData', $se_data);
  }
}
