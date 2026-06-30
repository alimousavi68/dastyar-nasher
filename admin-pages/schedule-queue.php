<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('add_action')) {
    return;
}

if (!function_exists('i8_to_persian_num')) {
    function i8_to_persian_num($number) {
        $english_digits = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        $persian_digits = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
        return str_replace($english_digits, $persian_digits, (string)$number);
    }
}

// Add page menu
if (function_exists('add_action')) {
    add_action('admin_menu', 'i8_add_schedule_queue_page_menu', 12);
} else {
    return;
}

function i8_add_schedule_queue_page_menu() {
    if (function_exists('add_submenu_page')) {
        add_submenu_page(
            'publisher_copoilot',
            'صف انتشار',
            'صف انتشار',
            'manage_options',
            'publisher_copoilot_schedule_queue',
            'pc_schedule_queue_page_callback'
        );
    }
}

// Helpers
function safe_get_option($option_name, $default = '') { return function_exists('get_option') ? get_option($option_name, $default) : $default; }
function safe_esc_attr($text) { return function_exists('esc_attr') ? esc_attr($text) : htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function safe_esc_html($text) { return function_exists('esc_html') ? esc_html($text) : htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function safe_get_edit_post_link($post_id) { return function_exists('get_edit_post_link') ? get_edit_post_link($post_id) : '#'; }
function safe_get_the_title($post_id) { return function_exists('get_the_title') ? (get_the_title($post_id) ?: 'بدون عنوان') : 'بدون عنوان'; }
function safe_get_post_status($post_id) { return function_exists('get_post_status') ? get_post_status($post_id) : 'unknown'; }
function safe_get_permalink($post_id) { return function_exists('get_permalink') ? get_permalink($post_id) : '#'; }
function safe_get_author_name($post_id) {
    if (function_exists('get_the_author_meta') && function_exists('get_post_field')) {
        $author_id = get_post_field('post_author', $post_id);
        return $author_id ? (get_the_author_meta('display_name', $author_id) ?: 'نامشخص') : 'نامشخص';
    }
    return 'نامشخص';
}

function pc_schedule_queue_page_callback() {
    global $wpdb;
    $table = $wpdb->prefix . 'pc_post_schedule';
    
    // pagination
    $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 25;
    if (!in_array($per_page, [25, 50, 70])) $per_page = 25;
    
    $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $per_page;
    
    $total_posts = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $total_pages = ceil($total_posts / $per_page);

    $results = $wpdb->get_results("SELECT * FROM $table ORDER BY FIELD(status, 'publishing', 'scheduled', 'queued', 'failed', 'cancelled', 'published'), FIELD(publish_priority, 'high', 'medium', 'low'), sort_order ASC, id ASC LIMIT $per_page OFFSET $offset");
    
    $today_utc = gmdate('Y-m-d 00:00:00', strtotime('today'));
    $stats = $wpdb->get_row("SELECT 
        SUM(CASE WHEN status IN ('queued', 'scheduled') THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
    FROM $table");
    
    // کوئری بهینه و مستقیم جهت شمارش پست‌های منتشر شده امروز بدون سرریز لود WP_Query
    $today_start = date('Y-m-d 00:00:00', current_time('timestamp'));
    $today_end = date('Y-m-d 23:59:59', current_time('timestamp'));
    $published_today = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_date >= %s AND post_date <= %s",
        $today_start,
        $today_end
    ));
    // Check if Action Scheduler is active
    $as_active = function_exists('as_schedule_single_action');
?>
    <!-- Include Stylesheets -->
    <link rel="stylesheet" href="<?php echo COP_PLUGIN_URL; ?>/assets/css/feed_list.css">
    <link rel="stylesheet" href="<?php echo COP_PLUGIN_URL; ?>/assets/css/bootstrap.rtl.min.css">
    <script src="<?php echo COP_PLUGIN_URL; ?>/assets/js/jquery.min.js"></script>
    <script src="<?php echo COP_PLUGIN_URL; ?>/assets/js/sweetalert2@11.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script src="<?php echo COP_PLUGIN_URL; ?>/assets/js/bootstrap.bundle.min.js"></script>
    
    <style>
        .sortable-ghost { opacity: 0.4; background-color: #f8fafc; }
        /* Pagination overrides to match design system */
        .page-numbers { padding: 6px 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #475569; display: inline-block; margin: 0 2px; }
        .page-numbers.current { background-color: #0d6efd !important; color: #ffffff !important; border-color: #0d6efd !important; }
        .page-numbers:hover:not(.current) { background: #f1f5f9; }
        .page-numbers-inner { font-family: 'IRANSansX', sans-serif; }
        ul.page-numbers { list-style: none; display: flex; padding: 0; margin: 0; }

        /* Responsive adjustments for header badges on smaller desktop screens */
        @media (min-width: 1200px) and (max-width: 1450px) {
            .sticky-header-bar {
                padding: 10px 12px !important;
                gap: 10px !important;
            }
            .sticky-header-bar .queue-icon-container {
                width: 38px !important;
                height: 38px !important;
            }
            .sticky-header-bar .queue-icon-container svg {
                width: 18px !important;
                height: 18px !important;
            }
            .sticky-header-bar span.fs-5 {
                font-size: 15px !important;
            }
            .sticky-header-bar div[style*="font-size: 11px"] {
                font-size: 10px !important;
            }
            .status-time-badge, .status-count-badge {
                padding: 4px 8px !important;
                gap: 4px !important;
            }
            .status-time-badge svg, .status-count-badge svg {
                width: 14px !important;
                height: 14px !important;
            }
            .status-time-badge .d-flex.flex-column, .status-count-badge .d-flex.flex-column {
                line-height: 1.1 !important;
            }
            .status-time-badge span[style*="font-size: 10px"], 
            .status-count-badge span[style*="font-size: 10px"] {
                font-size: 9px !important;
            }
            .status-time-badge span[style*="font-size: 13px"], 
            .status-count-badge span[style*="font-size: 13px"] {
                font-size: 11px !important;
            }
            .status-count-badge span[style*="font-size: 11px"] {
                font-size: 9.5px !important;
            }
        }
        
        /* Balanced two-row layout for screens below 1200px */
        @media (max-width: 1199px) {
            .sticky-header-bar {
                flex-direction: column !important;
                align-items: center !important;
                text-align: center !important;
                gap: 15px !important;
            }
            .sticky-header-bar > div {
                justify-content: center !important;
            }
            .sticky-header-bar .border-start-xl {
                border-left: none !important;
                flex-direction: column !important;
            }
            .sticky-header-bar .ms-xl-auto {
                margin-left: 0 !important;
                margin-right: 0 !important;
                justify-content: center !important;
            }
        }
    </style>

    <div class="app-container" style="margin-top: 20px;">
        <?php if (!$as_active): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 rounded-xl mb-4" role="alert">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-exclamation-triangle-fill" viewBox="0 0 16 16">
                    <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
                </svg>
                <div style="font-size: 13px;">
                    <strong>هشدار سیستم:</strong> ابزار پیش‌نیاز Action Scheduler در سایت فعال نشده است. فرآیند خودکار زمان‌بندی و انتشار متوقف خواهد بود.
                </div>
            </div>
        <?php endif; ?>
        <!-- Unified Sticky Header -->
        <div class="sticky-header-bar d-flex flex-wrap flex-xl-nowrap align-items-center justify-content-between gap-3 p-3 mb-4 shadow-sm-soft border bg-white rounded-xl" style="position: sticky; top: 32px; z-index: 100;">
            <!-- Logo & Title -->
            <div class="d-flex align-items-center gap-2 pe-xl-3 border-start-xl">
                <div class="queue-icon-container me-2 d-flex align-items-center justify-content-center text-primary rounded-circle" style="width: 48px; height: 48px; position: relative; overflow: hidden; background-color: #eff6ff;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-calendar-check" viewBox="0 0 16 16" style="z-index: 2;">
                        <path d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0"/>
                        <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z"/>
                    </svg>
                    <!-- Background rotating/pulsing ring -->
                    <div style="position: absolute; width: 100%; height: 100%; border: 2px dashed rgba(13, 110, 253, 0.4); border-radius: 50%; animation: radar-spin 8s linear infinite;"></div>
                </div>
                <div>
                    <span class="fs-5 fw-bolder text-slate-800" style="white-space: nowrap;">صف انتشار دستیار</span>
                    <div style="font-size: 11px; color: #64748b; margin-top: -2px;">مدیریت انتشار، جابجایی دستی و مانیتورینگ</div>
                </div>
            </div>

            <!-- Stats Grid Moved into Header -->
            <div class="d-flex flex-wrap gap-2 ms-xl-auto align-items-center">
                
                <!-- Live Clock -->
                <div class="status-time-badge d-flex align-items-center" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 8px;" id="live-clock-badge">
                    <span style="color: #64748b; margin-left: 6px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clock" viewBox="0 0 16 16">
                          <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71z"/>
                          <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0"/>
                        </svg>
                    </span>
                    <div class="d-flex flex-column" style="line-height: 1.2;">
                        <span style="font-size: 10px; color: #64748b; font-weight: 500;">ساعت محلی</span>
                        <span style="color: #64748b; font-size: 13px; font-weight: 500;" id="live-clock-time">--:--:--</span>
                    </div>
                </div>
                <script>
                    setInterval(function() {
                        const now = new Date();
                        const timeStr = now.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                        document.getElementById('live-clock-time').innerText = timeStr;
                    }, 1000);
                </script>

                <?php
                $next_run = $wpdb->get_row("SELECT scheduled_for FROM $table WHERE status IN ('queued', 'scheduled') ORDER BY sort_order ASC, id ASC LIMIT 1");
                if ($next_run && $next_run->scheduled_for) {
                    $local_date_str = function_exists('i8_get_local_time_from_gmt') ? i8_get_local_time_from_gmt($next_run->scheduled_for) : get_date_from_gmt($next_run->scheduled_for);
                    $fake_timestamp = strtotime($local_date_str . ' UTC');
                    if (class_exists('i8_jDateTime')) {
                        $jdate = new i8_jDateTime(true, true, 'UTC');
                        $next_time_str = i8_to_persian_num($jdate->date('H:i', $fake_timestamp));
                    } else {
                        $next_time_str = i8_to_persian_num(date('H:i', $fake_timestamp));
                    }
                } else {
                    $next_time_str = '---';
                }
                ?>
                <!-- Next Run Time -->
                <div class="status-time-badge d-flex align-items-center" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 8px;" data-bs-toggle="tooltip" data-bs-title="زمان اجرای پست بعدی">
                    <span style="color: #6366f1; margin-left: 6px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clock-history" viewBox="0 0 16 16">
                            <path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022zm2.004.45a7 7 0 0 0-.985-.299l.219-.976q.576.129 1.126.342zm1.37.71a7 7 0 0 0-.439-.27l.493-.87a8 8 0 0 1 .979.654l-.615.789a7 7 0 0 0-.418-.302zm1.834 1.79a7 7 0 0 0-.653-.796l.724-.69q.406.429.747.91zm.744 1.352a7 7 0 0 0-.214-.468l.893-.45a8 8 0 0 1 .45 1.088l-.95.313a7 7 0 0 0-.179-.483m.53 2.507a7 7 0 0 0-.1-1.025l.985-.17q.1.58.116 1.17zm-.131 1.538q.05-.254.081-.51l.993.123a8 8 0 0 1-.23 1.155l-.964-.267q.069-.247.12-.501m-.952 2.379q.276-.436.486-.908l.914.405q-.24.54-.555 1.038zm-.964 1.205q.183-.183.35-.378l.758.653a8 8 0 0 1-.401.432z"/>
                            <path d="M8 1a7 7 0 1 0 4.95 11.95l.707.707A8.001 8.001 0 1 1 8 0z"/>
                            <path d="M7.5 3a.5.5 0 0 1 .5.5v5.21l3.248 1.856a.5.5 0 0 1-.496.868l-3.5-2A.5.5 0 0 1 7 9V3.5a.5.5 0 0 1 .5-.5"/>
                        </svg>
                    </span>
                    <div class="d-flex flex-column" style="line-height: 1.2;">
                        <span style="font-size: 10px; color: #6366f1; font-weight: 500;">اجرای بعدی</span>
                        <span style="color: #6366f1; font-size: 13px; font-weight: 500;"><?php echo $next_time_str; ?></span>
                    </div>
                </div>

                <!-- Pending Queue Count -->
                <div class="status-count-badge d-flex align-items-center" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 8px;">
                    <span style="color: #f59e0b; margin-left: 6px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-hourglass-split" viewBox="0 0 16 16">
                            <path d="M2.5 15a.5.5 0 1 1 0-1h1v-1a4.5 4.5 0 0 1 2.557-4.06c.29-.139.443-.377.443-.59v-.7c0-.213-.154-.451-.443-.59A4.5 4.5 0 0 1 3.5 3V2h-1a.5.5 0 0 1 0-1h11a.5.5 0 0 1 0 1h-1v1a4.5 4.5 0 0 1-2.557 4.06c-.29.139-.443.377-.443.59v.7c0 .213.154.451.443.59A4.5 4.5 0 0 1 12.5 14v1h1a.5.5 0 0 1 0 1zm2-13v1c0 .537.12 1.045.337 1.524a3.5 3.5 0 0 0 1.863 1.863c.33.155.602.408.802.713v.78c0 .285-.145.543-.38.713a3.5 3.5 0 0 0-1.885 1.885A3.5 3.5 0 0 0 4.5 14v1h7v-1a3.5 3.5 0 0 0-.637-2.024 3.5 3.5 0 0 0-1.885-1.885c-.235-.17-.38-.428-.38-.713v-.78c.2-.305.472-.558.802-.713a3.5 3.5 0 0 0 1.863-1.863A3.5 3.5 0 0 0 11.5 3V2z"/>
                        </svg>
                    </span>
                    <div class="d-flex flex-column" style="line-height: 1.2;">
                        <span style="font-size: 10px; color: #f59e0b; font-weight: 500;">در انتظار</span>
                        <span style="color: #f59e0b; font-size: 13px; font-weight: 500;"><?php echo i8_to_persian_num(intval($stats->pending_count)); ?> پست</span>
                    </div>
                </div>
                
                <!-- Published Today -->
                <div class="status-count-badge d-flex align-items-center" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 8px;">
                    <span style="color: #10b981; margin-left: 6px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check2-circle" viewBox="0 0 16 16">
                            <path d="M2.5 8a5.5 5.5 0 0 1 8.25-4.764.5.5 0 0 0 .5-.866A6.5 6.5 0 1 0 14.5 8a.5.5 0 0 0-1 0 5.5 5.5 0 1 1-11 0"/>
                            <path d="M15.354 3.354a.5.5 0 0 0-.708-.708L8 9.293 5.354 6.646a.5.5 0 1 0-.708.708l3 3a.5.5 0 0 0 .708 0z"/>
                        </svg>
                    </span>
                    <div class="d-flex flex-column" style="line-height: 1.2;">
                        <span style="font-size: 10px; color: #10b981; font-weight: 500;">منتشر شده امروز</span>
                        <span style="color: #10b981; font-size: 13px; font-weight: 500;"><?php echo i8_to_persian_num(intval($published_today)); ?> پست</span>
                    </div>
                </div>
                
                <?php 
                $interval_secs = function_exists('calculate_post_publish_time') ? calculate_post_publish_time() : 0;
                $interval_mins = round($interval_secs / 60, 1);
                $daily_count = safe_get_option('daily_post_count', 30);
                $start_t = safe_get_option('start_cron_time', '08:00');
                $end_t = safe_get_option('end_cron_time', '22:00');
                ?>
                
                <!-- Daily Capacity -->
                <div class="status-count-badge d-flex align-items-center" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 8px;">
                    <span style="color: #14b8a6; margin-left: 6px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-speedometer2" viewBox="0 0 16 16">
                          <path d="M8 4a.5.5 0 0 1 .5.5V6a.5.5 0 0 1-1 0V4.5A.5.5 0 0 1 8 4M3.732 5.732a.5.5 0 0 1 .707 0l.915.914a.5.5 0 1 1-.708.708l-.914-.915a.5.5 0 0 1 0-.707M2 10a.5.5 0 0 1 .5-.5h1.586a.5.5 0 0 1 0 1H2.5A.5.5 0 0 1 2 10m9.5 0a.5.5 0 0 1 .5-.5h1.5a.5.5 0 0 1 0 1H12a.5.5 0 0 1-.5-.5m.754-4.246a.389.389 0 0 0-.527-.02L7.547 9.31a.91.91 0 1 0 1.302 1.258l3.434-4.297a.389.389 0 0 0-.029-.518z"/>
                          <path fill-rule="evenodd" d="M0 10a8 8 0 1 1 15.547 2.661c-.442 1.253-1.845 2.508-3.972 2.508a.5.5 0 0 1-.5-.5v-2a.5.5 0 0 0-.5-.5H5.425a.5.5 0 0 0-.5.5v2a.5.5 0 0 1-.5.5c-2.127 0-3.53-1.255-3.972-2.508A8 8 0 0 1 0 10m8-7a7 7 0 0 0-6.603 9.329c1.232-.902 2.753-1.128 4.352-1.128h4.502c1.6 0 3.12.226 4.352 1.128A7 7 0 0 0 8 3"/>
                        </svg>
                    </span>
                    <div class="d-flex flex-column" style="line-height: 1.2;">
                        <span style="font-size: 10px; color: #14b8a6; font-weight: 500;">ظرفیت انتشار روزانه</span>
                        <span style="color: #14b8a6; font-size: 13px; font-weight: 500;"><?php echo i8_to_persian_num($daily_count); ?> خبر در روز</span>
                    </div>
                </div>

                <!-- Queue Activity Period -->
                <div class="status-count-badge d-flex align-items-center" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 12px; border-radius: 8px;">
                    <span style="color: #64748b; margin-left: 6px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar-event" viewBox="0 0 16 16">
                          <path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5v-1z"/>
                          <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z"/>
                        </svg>
                    </span>
                    <div class="d-flex flex-column" style="line-height: 1.2;">
                        <span style="font-size: 10px; color: #64748b; font-weight: 500;">بازه فعالیت صف</span>
                        <span style="color: #64748b; font-size: 11px; font-weight: 500;">از <?php echo i8_to_persian_num($start_t); ?> تا <?php echo i8_to_persian_num($end_t); ?> (هر <?php echo i8_to_persian_num($interval_mins); ?> دقیقه)</span>
                    </div>
                </div>
            </div>

            <!-- Items Per Page -->
            <div class="d-flex align-items-center gap-2 border-end-xl pe-xl-3">
                <select id="per-page-select" class="form-select form-select-sm border-1 border-slate-300 bg-white shadow-sm" style="width: auto; font-size: 12px; font-weight: bold; border-color: #cbd5e1 !important; border-radius: 8px;" onchange="window.location.href='?page=publisher_copoilot_schedule_queue&per_page='+this.value">
                    <option value="25" <?php selected($per_page, 25); ?>>۲۵ آیتم</option>
                    <option value="50" <?php selected($per_page, 50); ?>>۵۰ آیتم</option>
                    <option value="70" <?php selected($per_page, 70); ?>>۷۰ آیتم</option>
                </select>
            </div>

            <!-- Refresh Button -->
            <div class="d-flex align-items-center gap-1">
                <button onclick="location.reload();" class="btn-icon btn-icon-outline text-slate-500" data-bs-toggle="tooltip" data-bs-title="به‌روزرسانی صفحه">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-repeat" viewBox="0 0 16 16">
                        <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"/>
                        <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3zM3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.1z"/>
                    </svg>
                </button>
            </div>
        </div>

        <div class="px-0 px-lg-3">
            <div class=" d-none d-md-flex align-items-center justify-content-between gap-3 fw-bold text-slate-400 mb-2 px-3" style="font-size: 11px;">
                <!-- Left Section (Matches Row Layout) -->
                <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
                    <div style="width: 20px;"></div> <!-- drag handle space -->
                    <div class="text-center px-2" style="min-width: 20px; padding-left: 10px !important;">#</div>
                    <div class="feed-vertical-divider invisible"></div>
                    <div class="text-center px-2" style="min-width: 60px;">زمان انتشار</div>
                    <div class="feed-vertical-divider"></div>
                    <div class="text-start px-2 min-w-0 flex-grow-1">وضعیت و عنوان</div>
                </div>
                
                <!-- Right Section (Matches Row Layout) -->
                <div class="d-flex align-items-center gap-3">
                    <div class="text-center" style="width: 250px;">اولویت و عملیات</div>
                </div>
            </div>
            <div class="table-body" id="i8-queue-body">
                <?php
                $_POST['paged'] = $page;
                include plugin_dir_path(__FILE__) . 'partials/queue-rows.php';
                ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="row mt-4">
                <div class="col-12 d-flex justify-content-center">
                    <nav aria-label="Page navigation">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '« قبلی',
                            'next_text' => 'بعدی »',
                            'total' => $total_pages,
                            'current' => $page,
                            'type' => 'list',
                            'add_args' => array('per_page' => $per_page)
                        ));
                        ?>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            function showToast(message, type = 'success') {
                var toast = $('#i8-toast');
                toast.text(message);
                toast.css('background', type === 'error' ? '#ef4444' : (type === 'warning' ? '#f59e0b' : '#10b981'));
                toast.addClass('toast-show');
                setTimeout(function() { toast.removeClass('toast-show'); }, 3000);
            }

            // Init SortableJS
            var tbody = document.getElementById('i8-queue-body');
            var sortable = Sortable.create(tbody, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function(evt) {
                    var orders = {};
                    $('#i8-queue-body .table-item').each(function(index) {
                        orders[$(this).data('id')] = index + 1 + <?php echo $offset; ?>;
                    });
                    
                    $.post(ajaxurl, {
                        action: 'i8_reorder_queue',
                        nonce: '<?php echo wp_create_nonce("i8_queue_action"); ?>',
                        orders: orders
                    }, function(response) {
                        if(response.success) {
                            showToast(response.data.message, 'success');
                            pollQueue(true); // Force refresh
                        } else {
                            showToast(response.data.message || 'خطا در ثبت تغییرات', 'error');
                        }
                    });
                }
            });

            // Handle Priority Change
            $(document).on('change', '.i8-priority-select', function() {
                var select = $(this);
                $.post(ajaxurl, {
                    action: 'i8_update_priority',
                    nonce: '<?php echo wp_create_nonce("i8_queue_action"); ?>',
                    id: select.data('id'),
                    priority: select.val()
                }, function(response) {
                    if(response.success) {
                        select.attr('data-val', select.val());
                        showToast(response.data.message, 'success');
                        pollQueue(true);
                    } else {
                        showToast(response.data.message, 'error');
                    }
                });
            });

            // Handle Delete
            $(document).on('click', '.delete-link', function(e) {
                e.preventDefault();
                if (!confirm('از حذف این پست از صف اطمینان دارید؟')) return;
                
                var btn = $(this);
                $.post(ajaxurl, {
                    action: 'i8_delete_from_queue',
                    nonce: '<?php echo wp_create_nonce("i8_queue_action"); ?>',
                    id: btn.data('id')
                }, function(response) {
                    if(response.success) {
                        $('#item-' + btn.data('id')).fadeOut(300, function() { $(this).remove(); });
                        showToast(response.data.message, 'success');
                    } else {
                        showToast(response.data.message, 'error');
                    }
                });
            });

            // Handle Publish Now
            $(document).on('click', '.pc-publish-now-btn', function(e) {
                e.preventDefault();
                if (!confirm('آیا از انتشار فوری این پست اطمینان دارید؟')) return;
                
                var btn = $(this);
                btn.find('.i8-loader-gif').show();
                $.post(ajaxurl, {
                    action: 'i8_publish_now',
                    nonce: '<?php echo wp_create_nonce("i8_queue_action"); ?>',
                    item_id: btn.data('id')
                }, function(response) {
                    btn.find('.i8-loader-gif').hide();
                    if(response.success) {
                        $('#item-' + btn.data('id')).fadeOut(300, function() { $(this).remove(); });
                        showToast(response.data.message, 'success');
                    } else {
                        showToast(response.data.message || 'خطا در انتشار', 'error');
                    }
                });
            });

            // Polling using Page Visibility API
            var pollInterval;
            function pollQueue(force = false) {
                if (document.hidden && !force) return; // Don't poll if page is hidden
                
                $.post(ajaxurl, {
                    action: 'i8_get_queue_status',
                    nonce: '<?php echo wp_create_nonce("i8_queue_action"); ?>',
                    paged: <?php echo $page; ?>
                }, function(response) {
                    if(response.success && response.data.html) {
                        $('#i8-queue-body').html(response.data.html);
                    }
                });
            }

            document.addEventListener("visibilitychange", function() {
                if (!document.hidden) {
                    pollQueue(true);
                }
            });

            setInterval(pollQueue, 30000); // 30 seconds
        });
    </script>
<?php
}
