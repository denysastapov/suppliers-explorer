<?php
if (! defined('ABSPATH')) exit;

class SE_REST
{

  public static function init()
  {
    add_action('rest_api_init', [__CLASS__, 'register_routes']);
  }

  public static function register_routes()
  {
    register_rest_route('suppliers/v1', '/list', [
      'methods'             => 'GET',
      'callback'            => [__CLASS__, 'list_suppliers'],
      'permission_callback' => '__return_true',
      'args'                => [
        'q'        => ['type' => 'string',  'required' => false],
        'top'      => ['type' => 'array',   'required' => false],
        'sub'      => ['type' => 'array',   'required' => false],
        'page'     => ['type' => 'integer', 'required' => false, 'default' => 1],
        'per_page' => ['type' => 'integer', 'required' => false, 'default' => 15],
        'orderby'  => ['type' => 'string',  'required' => false, 'default' => 'name'],
        'order'    => ['type' => 'string',  'required' => false, 'default' => 'asc'],
      ],
    ]);

    register_rest_route('suppliers/v1', '/single', [
      'methods'             => 'GET',
      'callback'            => [__CLASS__, 'single_supplier'],
      'permission_callback' => '__return_true',
      'args'                => [
        'id' => ['type' => 'integer', 'required' => true],
      ],
    ]);
  }

  protected static function map_subs_to_top($sub_slugs)
  {
    if (empty($sub_slugs)) return [];
    $top_from_subs = [];

    foreach ($sub_slugs as $slug) {
      $term = get_term_by('slug', sanitize_title($slug), 'sub-category');
      if ($term && ! is_wp_error($term) && function_exists('get_field')) {
        $parent = get_field('parent_top_level', 'sub-category_' . $term->term_id);
        if ($parent) {
          if (is_array($parent)) {
            foreach ($parent as $p) {
              if (is_object($p) && isset($p->slug)) $top_from_subs[] = $p->slug;
            }
          } elseif (is_object($parent) && isset($parent->slug)) {
            $top_from_subs[] = $parent->slug;
          }
        }
      }
    }

    return array_values(array_unique(array_filter($top_from_subs)));
  }

  public static function list_suppliers(WP_REST_Request $request)
  {
    $q        = sanitize_text_field((string) ($request->get_param('q') ?? ''));
    $page     = max(1, intval($request->get_param('page') ?? 1));
    $per_page = min(50, max(1, intval($request->get_param('per_page') ?? 15)));
    $order    = (strtolower((string) $request->get_param('order')) === 'desc') ? 'DESC' : 'ASC';

    $top  = $request->get_param('top');
    $subs = $request->get_param('sub');

    $top  = is_array($top)  ? array_map('sanitize_title', $top)  : [];
    $subs = is_array($subs) ? array_map('sanitize_title', $subs) : [];

    $top_from_subs = self::map_subs_to_top($subs);
    $all_top       = array_values(array_unique(array_filter(array_merge($top, $top_from_subs))));

    $must_tax = [];
    if (! empty($all_top)) {
      $must_tax['relation'] = 'AND';
      foreach ($all_top as $slug) {
        $must_tax[] = [
          'taxonomy'         => 'top-level-category',
          'field'            => 'slug',
          'terms'            => [$slug],
          'operator'         => 'IN',
          'include_children' => false,
        ];
      }
    }

    if ($q !== '') {
      $ids_title = [];
      $qA_args = [
        'post_type'      => 'supplier',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'posts_per_page' => -1,
        'no_found_rows'  => true,
        's'              => $q,
      ];
      if (! empty($must_tax)) $qA_args['tax_query'] = $must_tax;

      $qA = new WP_Query($qA_args);
      if (! is_wp_error($qA) && ! empty($qA->posts)) {
        $ids_title = array_map('intval', $qA->posts);
      }

      $ids_tax = [];
      $term_top = get_terms([
        'taxonomy'   => 'top-level-category',
        'hide_empty' => false,
        'search'     => $q,
        'number'     => 0,
      ]);
      $term_sub = get_terms([
        'taxonomy'   => 'sub-category',
        'hide_empty' => false,
        'search'     => $q,
        'number'     => 0,
      ]);
      $top_ids = ! is_wp_error($term_top) ? wp_list_pluck($term_top, 'term_id') : [];
      $sub_ids = ! is_wp_error($term_sub) ? wp_list_pluck($term_sub, 'term_id') : [];

      if (! empty($top_ids) || ! empty($sub_ids)) {
        $or_block = ['relation' => 'OR'];
        if (! empty($top_ids)) {
          $or_block[] = [
            'taxonomy' => 'top-level-category',
            'field'    => 'term_id',
            'terms'    => array_map('intval', $top_ids),
            'operator' => 'IN',
          ];
        }
        if (! empty($sub_ids)) {
          $or_block[] = [
            'taxonomy' => 'sub-category',
            'field'    => 'term_id',
            'terms'    => array_map('intval', $sub_ids),
            'operator' => 'IN',
          ];
        }

        $tax_for_q = [];
        if (! empty($must_tax)) {
          $tax_for_q = ['relation' => 'AND'];
          foreach ($must_tax as $part) {
            $tax_for_q[] = $part;
          }
          $tax_for_q[] = $or_block;
        } else {
          $tax_for_q = $or_block;
        }

        $qB = new WP_Query([
          'post_type'      => 'supplier',
          'post_status'    => 'publish',
          'fields'         => 'ids',
          'posts_per_page' => -1,
          'no_found_rows'  => true,
          'tax_query'      => $tax_for_q,
        ]);
        if (! is_wp_error($qB) && ! empty($qB->posts)) {
          $ids_tax = array_map('intval', $qB->posts);
        }
      }

      $union_ids = array_values(array_unique(array_merge($ids_title, $ids_tax)));

      if (empty($union_ids)) {
        return new WP_REST_Response([
          'items' => [],
          'total' => 0,
          'pages' => 0,
          'page'  => 1,
        ], 200);
      }

      $args_final = [
        'post_type'      => 'supplier',
        'post_status'    => 'publish',
        'post__in'       => $union_ids,
        'orderby'        => 'title',
        'order'          => $order,
        'posts_per_page' => $per_page,
        'paged'          => $page,
      ];
      if (! empty($must_tax)) $args_final['tax_query'] = $must_tax;

      $qry = new WP_Query($args_final);
    } else {
      $args = [
        'post_type'      => 'supplier',
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => $order,
        'posts_per_page' => $per_page,
        'paged'          => $page,
      ];
      if (! empty($must_tax)) $args['tax_query'] = $must_tax;

      $qry = new WP_Query($args);
    }

    $items = [];
    if ($qry->have_posts()) {
      while ($qry->have_posts()) {
        $qry->the_post();
        $id   = get_the_ID();
        $logo = '';

        if (has_post_thumbnail($id)) {
          $logo = get_the_post_thumbnail_url($id, 'medium');
        } else {
          $meta_logo = get_post_meta($id, 'supplier_logo_url', true);
          if ($meta_logo) $logo = esc_url_raw($meta_logo);
        }

        $top_terms = get_the_terms($id, 'top-level-category');
        $sub_terms = get_the_terms($id, 'sub-category');

        $items[] = [
          'id'      => $id,
          'title'   => get_the_title(),
          'excerpt' => wp_trim_words(wp_strip_all_tags(get_the_content(null, false, $id)), 32, '…'),
          'logo'    => $logo,
          'top'     => array_values(array_map(function ($t) {
            return ['slug' => $t->slug, 'name' => $t->name];
          }, is_array($top_terms) ? $top_terms : [])),
          'sub'     => array_values(array_map(function ($t) {
            return ['slug' => $t->slug, 'name' => $t->name];
          }, is_array($sub_terms) ? $sub_terms : [])),
        ];
      }
      wp_reset_postdata();
    }

    return new WP_REST_Response([
      'items' => $items,
      'total' => intval($qry->found_posts),
      'pages' => intval($qry->max_num_pages),
      'page'  => $page,
    ], 200);
  }

  public static function single_supplier(WP_REST_Request $request)
  {
    $id   = intval($request->get_param('id'));
    $post = get_post($id);

    if (! $post || $post->post_type !== 'supplier') {
      return new WP_Error('not_found', 'Supplier not found', ['status' => 404]);
    }

    $logo = '';
    if (has_post_thumbnail($id)) {
      $logo = get_the_post_thumbnail_url($id, 'large');
    } else {
      $meta_logo = get_post_meta($id, 'supplier_logo_url', true);
      if ($meta_logo) $logo = esc_url_raw($meta_logo);
    }

    $top_terms = get_the_terms($id, 'top-level-category');
    $sub_terms = get_the_terms($id, 'sub-category');

    return new WP_REST_Response([
      'id'      => $id,
      'title'   => get_the_title($id),
      'content' => apply_filters('the_content', $post->post_content ?: ' '),
      'logo'    => $logo,
      'top'     => array_values(array_map(function ($t) {
        return ['slug' => $t->slug, 'name' => $t->name];
      }, is_array($top_terms) ? $top_terms : [])),
      'sub'     => array_values(array_map(function ($t) {
        return ['slug' => $t->slug, 'name' => $t->name];
      }, is_array($sub_terms) ? $sub_terms : [])),
    ], 200);
  }
}

SE_REST::init();
