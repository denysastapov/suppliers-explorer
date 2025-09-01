<?php
if (! defined('ABSPATH')) exit;

class SE_Shortcode
{

  public static function init()
  {
    add_shortcode('suppliers_explorer', [__CLASS__, 'render']);
  }

  public static function render($atts = [], $content = '', $tag = '')
  {
    $atts = shortcode_atts([
      'api'      => '',
      'per_page' => 25,
    ], $atts, $tag);

    $api       = trim((string) $atts['api']);
    $per_page  = (int) $atts['per_page'];
    if ($per_page < 1)  $per_page = 1;
    if ($per_page > 50) $per_page = 50;

    if (class_exists('SE_Assets')) {
      SE_Assets::enqueue_front();
    } else {
      wp_enqueue_style('se-style',  SE_URL . 'assets/css/suppliers-explorer.css', [], SE_VERSION);
      wp_enqueue_script('se-script', SE_URL . 'assets/js/suppliers-explorer.js', [], SE_VERSION, true);
    }

    $inline = '';
    if ($api !== '') {
      $base = esc_url_raw(untrailingslashit($api));
      $inline = "(function(){
        var base = '" . esc_js($base) . "';
        window.seData = Object.assign({}, window.seData || {}, {
          remote: true,
          restBase:  base + '/wp-json/suppliers/v1',
          termsBase: base + '/wp-json/wp/v2',
          perPage: " . $per_page . ",
          topLevels: null
        });
      })();";
    } else {
      $inline = "(function(){
        window.seData = Object.assign({}, window.seData || {}, { perPage: " . $per_page . " });
      })();";
    }
    wp_add_inline_script('se-script', $inline, 'before');

    ob_start(); ?>
    <div class="se-explorer"
      <?php echo $api ? ' data-api="' . esc_url($api) . '"' : ''; ?>
      data-per-page="<?php echo esc_attr($per_page); ?>">

      <div class="se-controls">
        <div class="se-dropdown">
          <button type="button" class="se-dropbtn" aria-expanded="false">
            <?php echo esc_html__('Top Level Categories', 'se'); ?>
            <span class="se-selected-count">0</span>
          </button>
          <div class="se-dropdown-panel" hidden>
            <input type="text" class="se-type-search"
              placeholder="<?php echo esc_attr__('Type to search…', 'se'); ?>">
            <div class="se-top-list"></div>
          </div>
        </div>

        <div class="se-query">
          <input type="search" class="se-q"
            placeholder="<?php echo esc_attr__('Search suppliers, categories…', 'se'); ?>">
          <button type="button" class="se-search">
            <?php echo esc_html__('Search', 'se'); ?>
          </button>
        </div>
      </div>

      <div class="se-grid"></div>

      <div class="se-loadmore-wrap">
        <button type="button" class="se-loadmore">
          <?php echo esc_html__('Load more', 'se'); ?>
        </button>
      </div>
    </div>
<?php
    return ob_get_clean();
  }
}

SE_Shortcode::init();
