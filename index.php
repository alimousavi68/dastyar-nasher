<?php
/*
Plugin Name: Dastyar Nasher
Description: اافزونه دستیار هوشمند (کلاینت)
Version: 2.0
Author: هشت بهشت
*/

// Declare Const Vraibles
define('COP_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__));
define('COP_PLUGIN_URL', plugins_url('', __FILE__));
// تعریف ثابت مسیر پلاگین
define('DASTYAR_URL', plugin_dir_url(__FILE__));
// تعریف ثابت مسیر عکس‌ها
define('DASTYAR_ASSETS_IMAGES', DASTYAR_URL . 'assets/images/');


// date_default_timezone_set('Asia/Tehran');

// Include Libraries

// چک می‌کنیم آیا کلاس قبلاً تعریف شده است
if (!class_exists('i8_jDateTime')) {
    // Include jalali-date external library
    require_once plugin_dir_path(__FILE__) . '/library/jdatetime.class.php';
}
// اگر Action Scheduler لود نشده بود، لود کن
if ( ! class_exists( 'ActionScheduler' ) && file_exists( __DIR__ . '/library/action-scheduler/action-scheduler.php' ) ) {
    require_once __DIR__ . '/library/action-scheduler/action-scheduler.php';
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once plugin_dir_path(__FILE__) . '/library/simple_html_dom.php';

require_once (COP_PLUGIN_DIR_PATH . '/inc/functions_loader.php');

// pages
include_once (plugin_dir_path(__FILE__) . '/admin-pages/feeds_list.php');
include_once (plugin_dir_path(__FILE__) . '/admin-pages/setting_page.php');
include_once (plugin_dir_path(__FILE__) . '/admin-pages/license_page.php');
include_once (plugin_dir_path(__FILE__) . '/admin-pages/schedule-queue.php');

include_once (plugin_dir_path(__FILE__) . '/inc/scraper/index.php');
include_once (plugin_dir_path(__FILE__) . '/inc/report/index.php');

include_once (plugin_dir_path(__FILE__) . '/inc/db.php');

include_once (plugin_dir_path(__FILE__) . '/inc/crons/crons.php');
// include_once (plugin_dir_path(__FILE__) . '/inc/crons/scheduling_post_publishing.php');
