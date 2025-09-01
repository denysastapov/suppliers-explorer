<?php

/**
 * Plugin Name: Suppliers Explorer
 * Description: Searchable, filterable suppliers grid with REST API and shortcode. CPT: supplier; Taxonomies: top-level-category, sub-category.
 * Version: 0.1.0
 * Author: Admin EICT
 * License: GPLv2 or later
 */

if (! defined('ABSPATH')) exit;

define('SE_VERSION', '0.1.0');
define('SE_PATH', plugin_dir_path(__FILE__));
define('SE_URL',  plugin_dir_url(__FILE__));

require_once SE_PATH . 'includes/class-suppliers-explorer-assets.php';
require_once SE_PATH . 'includes/class-suppliers-explorer-rest.php';
require_once SE_PATH . 'includes/class-suppliers-explorer-shortcode.php';
