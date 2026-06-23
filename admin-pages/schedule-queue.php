<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure WordPress functions are available
if (!function_exists('add_action')) {
    return;
}

// Helper to convert numbers to Persian
if (!function_exists('i8_to_persian_num')) {
    function i8_to_persian_num($number) {
        $english_digits = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        $persian_digits = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
        return str_replace($english_digits, $persian_digits, (string)$number);
    }
}

// فراخوانی تابع افزودن صفحه تنظیمات
if (function_exists('add_action')) {
    add_action('admin_menu', 'i8_add_scheduleـqueue_page_menu');
} else {
    return;
}

// تابع برای اضافه کردن صفحه تنظیمات
function i8_add_scheduleـqueue_page_menu()
{
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

// Helper functions for safe WordPress function calls
function safe_get_option($option_name, $default = '') {
    if (function_exists('get_option')) {
        return get_option($option_name, $default);
    }
    return $default;
}

// esc_attr wrapper
function safe_esc_attr($text) {
    if (function_exists('esc_attr')) {
        return esc_attr($text);
    }
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// esc_html wrapper
function safe_esc_html($text) {
    if (function_exists('esc_html')) {
        return esc_html($text);
    }
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function safe_get_edit_post_link($post_id) {
    if (function_exists('get_edit_post_link')) {
        return get_edit_post_link($post_id);
    }
    return '#';
}

function safe_get_the_title($post_id) {
    if (function_exists('get_the_title')) {
        $title = get_the_title($post_id);
        return !empty($title) ? $title : 'بدون عنوان';
    }
    return 'بدون عنوان';
}

function safe_get_post_status($post_id) {
    if (function_exists('get_post_status')) {
        return get_post_status($post_id);
    }
    return 'unknown';
}

function safe_get_permalink($post_id) {
    if (function_exists('get_permalink')) {
        return get_permalink($post_id);
    }
    return '#';
}

function safe_get_author_name($post_id) {
    if (function_exists('get_the_author_meta') && function_exists('get_post_field')) {
        $author_id = get_post_field('post_author', $post_id);
        if ($author_id) {
            return get_the_author_meta('display_name', $author_id) ?: 'نامشخص';
        }
    }
    return 'نامشخص';
}

function safe_wp_get_current_user() {
    if (function_exists('wp_get_current_user')) {
        return wp_get_current_user();
    }
    return null;
}

function safe_current_user_can($capability) {
    if (function_exists('current_user_can')) {
        return current_user_can($capability);
    }
    return false;
}

function safe_wp_nonce_field($action, $name = '_wpnonce', $referer = true, $echo = true) {
    if (function_exists('wp_nonce_field')) {
        return wp_nonce_field($action, $name, $referer, $echo);
    }
    return '';
}

function safe_wp_verify_nonce($nonce, $action) {
    if (function_exists('wp_verify_nonce')) {
        return wp_verify_nonce($nonce, $action);
    }
    return false;
}

function safe_wp_die($message = '', $title = '', $args = array()) {
    if (function_exists('wp_die')) {
        wp_die($message, $title, $args);
    } else {
        die($message);
    }
}

function safe_get_recurrence($schedule) {
    return (is_object($schedule) && method_exists($schedule, 'get_recurrence')) ? $schedule->get_recurrence() : 0;
}

// get schedule date
function safe_get_schedule_date($schedule) {
    if (!is_object($schedule) || !method_exists($schedule, 'get_date')) {
        return null;
    }
    return $schedule->get_date();
}

function post_priority_persian($priority)
{
    switch ($priority) {
        case 'high':
            return 'اولویت بالا';
        case 'medium':
            return 'اولویت متوسط';
        case 'low':
            return 'اولویت پایین';
        default:
            return $priority;
    }
}

// تابع بازگشتی برای نمایش بخش تنظیمات
function pc_schedule_queue_page_callback()
{
    global $wpdb;
    $results = array();
    $post_publish_priority = array();
    
    if (isset($wpdb) && is_object($wpdb) && method_exists($wpdb, 'get_results')) {
        // ایجاد محدودیت ۱۰۰ تایی برای جلوگیری از کرش احتمالی در صف‌های بسیار طولانی
        $query = "SELECT * FROM {$wpdb->prefix}pc_post_schedule ORDER BY FIELD(publish_priority, 'high', 'medium', 'low'), id ASC LIMIT 100";
        $results = $wpdb->get_results($query);

        $query = "SELECT publish_priority, COUNT(*) as count FROM {$wpdb->prefix}pc_post_schedule GROUP BY publish_priority";
        $post_publish_priority = $wpdb->get_results($query);
    }
?>
    <style>
        :root {
            --i8-primary: #4f46e5;
            --i8-primary-hover: #4338ca;
            --i8-secondary: #0ea5e9;
            --i8-success: #10b981;
            --i8-warning: #f59e0b;
            --i8-danger: #ef4444;
            --i8-neutral-50: #f8fafc;
            --i8-neutral-100: #f1f5f9;
            --i8-neutral-800: #1e293b;
            --i8-glass-bg: rgba(255, 255, 255, 0.75);
            --i8-glass-border: rgba(226, 232, 240, 0.8);
        }

        .i8-dashboard-wrap {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            margin: 20px 20px 0 0;
            max-width: 98%; /* استفاده از پهنای کامل صفحه */
            direction: rtl;
        }

        .i8-dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .i8-title-sec h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--i8-neutral-800);
            margin: 0 0 6px 0;
        }

        .i8-title-sec .description {
            font-size: 14px;
            color: #64748b;
            margin: 0;
        }

        .i8-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .i8-btn-primary {
            background: linear-gradient(135deg, var(--i8-primary), #6366f1);
            color: white;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
        }

        .i8-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
        }

        .i8-btn-secondary {
            background: white;
            color: var(--i8-neutral-800);
            border: 1px solid #cbd5e1;
        }

        .i8-btn-secondary:hover {
            background: var(--i8-neutral-100);
        }

        /* Stats Grid */
        .i8-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .i8-stat-card {
            background: var(--i8-glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--i8-glass-border);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.02);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .i8-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.04);
        }

        .i8-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .i8-stat-info {
            display: flex;
            flex-direction: column;
        }

        .i8-stat-label {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }

        .i8-stat-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--i8-neutral-800);
            margin-top: 4px;
        }

        /* Colors for Card Icons */
        .i8-bg-primary { background: rgba(79, 70, 229, 0.1); color: var(--i8-primary); }
        .i8-bg-success { background: rgba(16, 185, 129, 0.1); color: var(--i8-success); }
        .i8-bg-warning { background: rgba(245, 158, 11, 0.1); color: var(--i8-warning); }
        .i8-bg-danger { background: rgba(239, 68, 68, 0.1); color: var(--i8-danger); }

        /* Container Card */
        .i8-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            padding: 24px;
            margin-bottom: 24px;
        }

        .i8-card-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--i8-neutral-800);
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Table */
        .i8-table-wrapper {
            overflow-x: auto;
        }

        .i8-table {
            width: 100%;
            border-collapse: collapse;
            text-align: right;
        }

        .i8-table th {
            padding: 14px 16px;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            border-bottom: 2px solid #f1f5f9;
        }

        .i8-table td {
            padding: 16px;
            font-size: 14px;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .i8-table tbody tr {
            transition: background 0.15s ease;
        }

        .i8-table tbody tr:hover {
            background: #f8fafc;
        }

        /* Badges */
        .i8-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 6px;
        }

        .i8-badge-draft { background: rgba(79, 70, 229, 0.1); color: var(--i8-primary); }
        .i8-badge-published { background: rgba(16, 185, 129, 0.1); color: var(--i8-success); }
        .i8-badge-trash { background: rgba(100, 116, 139, 0.1); color: #64748b; }

        /* Priority Selector Dropdown */
        .i8-priority-select {
            padding: 6px 12px 6px 28px; /* پدینگ اضافه در سمت چپ برای آیکون فلش */
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid #cbd5e1;
            transition: all 0.2s ease;
            outline: none;
            background-color: white;
            appearance: none; /* حذف فلش پیش‌فرض مرورگر */
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: left 8px center;
            background-size: 16px;
        }

        .i8-priority-select[data-val="high"] { background: rgba(239, 68, 68, 0.05); color: var(--i8-danger); border-color: rgba(239, 68, 68, 0.2); }
        .i8-priority-select[data-val="medium"] { background: rgba(245, 158, 11, 0.05); color: var(--i8-warning); border-color: rgba(245, 158, 11, 0.2); }
        .i8-priority-select[data-val="low"] { background: rgba(16, 185, 129, 0.05); color: var(--i8-success); border-color: rgba(16, 185, 129, 0.2); }

        .i8-priority-select:focus {
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.2);
        }

        /* Actions Cell */
        .i8-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .i8-action-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e2e8f0;
            background: white;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none !important;
        }

        .i8-action-icon:hover {
            border-color: #cbd5e1;
            color: var(--i8-neutral-800);
            background: var(--i8-neutral-50);
        }

        .i8-action-icon-delete:hover {
            border-color: rgba(239, 68, 68, 0.3);
            background: rgba(239, 68, 68, 0.05);
            color: var(--i8-danger);
        }

        /* Terminal Logs style */
        .i8-terminal {
            background: #0f172a;
            border: 1px solid #1e293b;
            border-radius: 12px;
            padding: 16px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 13px;
            color: #cbd5e1;
            max-height: 250px;
            overflow-y: auto;
            line-height: 1.6;
        }

        .i8-terminal-line {
            margin-bottom: 8px;
            display: flex;
            gap: 12px;
        }

        .i8-terminal-time {
            color: #64748b;
            user-select: none;
        }

        .i8-terminal-success { color: #34d399; }
        .i8-terminal-danger { color: #f87171; }
        .i8-terminal-info { color: #60a5fa; }

        /* Error Recovery Box */
        .i8-recovery-box {
            background: rgba(245, 158, 11, 0.05);
            border: 1px dashed var(--i8-warning);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            box-sizing: border-box;
        }

        .i8-recovery-text p {
            margin: 0;
            font-size: 14px;
            color: #78350f;
            font-weight: 600;
        }

        .i8-recovery-text span {
            font-size: 12px;
            color: #92400e;
        }
    </style>

    <div class="wrap i8-dashboard-wrap">
        <!-- Header -->
        <div class="i8-dashboard-header">
            <div class="i8-title-sec">
                <h1>صف زمان‌بندی و انتشار پست‌ها</h1>
                <p class="description">داشبورد مانیتورینگ پردازش‌های در جریان، بررسی وضعیت صف‌ها و عیب‌یابی فعالیت‌ها</p>
            </div>
            <div>
                <button onclick="location.reload();" class="i8-btn i8-btn-secondary">🔄 به‌روزرسانی صفحه</button>
            </div>
        </div>

        <!-- System Alerts / Recovery -->
        <?php
        try {
            $has_scheduler = function_exists('as_next_scheduled_action');
            $timestamp = null;
            $recurrence = '-';
            $recurrence_seconds = 0;

            if ($has_scheduler) {
                $actions = as_get_scheduled_actions([
                    'hook'     => 'i8_action_publish_post_at_scheduling_table',
                    'status'   => (class_exists('ActionScheduler_Store') ? ActionScheduler_Store::STATUS_PENDING : 'pending'),
                    'per_page' => 1,
                    'orderby'  => 'scheduled_date',
                    'order'    => 'ASC',
                ]);
                
                if (!empty($actions)) {
                    $action_id = array_key_first($actions);
                    $action = null;
                    if (class_exists('ActionScheduler') && method_exists('ActionScheduler', 'store')) {
                        $store = ActionScheduler::store();
                        if (method_exists($store, 'fetch_action')) {
                            $action = $store->fetch_action($action_id);
                        }
                    }
                    
                    if ($action) {
                        $schedule = method_exists($action, 'get_schedule') ? $action->get_schedule() : null;
                        if ($schedule && is_object($schedule)) {
                            $recurrence_seconds = safe_get_recurrence($schedule);
                            if ($recurrence_seconds) {
                                $recurrence = 'هر ' . i8_to_persian_num(round($recurrence_seconds / 60, 1)) . ' دقیقه';
                            }
                            $scheduled_date = safe_get_schedule_date($schedule);
                            if ($scheduled_date && is_object($scheduled_date) && method_exists($scheduled_date, 'getTimestamp')) {
                                $timestamp = $scheduled_date->getTimestamp();
                            }
                        }
                    }
                }
            }

            if (!$timestamp && $has_scheduler) {
                $is_running = false;
                $running_actions = as_get_scheduled_actions([
                    'hook'     => 'i8_action_publish_post_at_scheduling_table',
                    'status'   => (class_exists('ActionScheduler_Store') ? ActionScheduler_Store::STATUS_RUNNING : 'in-progress'),
                    'per_page' => 1,
                ]);
                if (!empty($running_actions)) {
                    $is_running = true;
                }

                if ($is_running) {
                    echo '<div class="i8-badge i8-badge-medium mb-3 p-3 w-100 text-center d-block">⏳ در حال اجرا و انتشار (لطفاً کمی صبر کنید)...</div>';
                } else {
                    ?>
                    <div class="i8-recovery-box">
                        <div class="i8-recovery-text">
                            <p>⚠️ تسک زمان‌بندی موتور انتشار غیرفعال است</p>
                            <span>سیستم زمان‌بندی Action Scheduler برای اجرای خودکار یافت نشد.</span>
                        </div>
                        <button onclick="i8_recreate_scheduled_action()" class="i8-btn i8-btn-primary">🔄 راه‌اندازی و بازیابی خودکار</button>
                    </div>
                    <?php
                }
            }
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>خطا در بازیابی وضعیت صف: ' . safe_esc_html($e->getMessage()) . '</p></div>';
        }
        ?>

        <!-- Stats Grid -->
        <div class="i8-stats-grid">
            <!-- Card 1 -->
            <div class="i8-stat-card">
                <div class="i8-stat-icon i8-bg-primary">⏰</div>
                <div class="i8-stat-info">
                    <span class="i8-stat-label">اجرای بعدی صف انتشار</span>
                    <span class="i8-stat-value">
                        <?php
                        if ($timestamp) {
                            $tz = wp_timezone();
                            $date_time = new DateTime('@' . $timestamp);
                            $date_time->setTimezone($tz);
                            $scheduled_day_gregorian = $date_time->format('Y-m-d');

                            $today_dt = new DateTime('now', $tz);
                            $today_gregorian = $today_dt->format('Y-m-d');

                            $tomorrow_dt = clone $today_dt;
                            $tomorrow_dt->modify('+1 day');
                            $tomorrow_gregorian = $tomorrow_dt->format('Y-m-d');

                            if (class_exists('i8_jDateTime')) {
                                $jdate = new i8_jDateTime(true, true, $tz->getName());
                                if ($scheduled_day_gregorian == $today_gregorian) {
                                    echo 'امروز ساعت ' . i8_to_persian_num($jdate->date('H:i', $timestamp));
                                } elseif ($scheduled_day_gregorian == $tomorrow_gregorian) {
                                    echo 'فردا ساعت ' . i8_to_persian_num($jdate->date('H:i', $timestamp));
                                } else {
                                    echo i8_to_persian_num($jdate->date('Y/m/d H:i', $timestamp));
                                }
                            } else {
                                echo i8_to_persian_num($date_time->format('Y-m-d H:i'));
                            }
                        } else {
                            echo 'غیرفعال';
                        }
                        ?>
                    </span>
                </div>
            </div>

            <!-- Card 2 -->
            <div class="i8-stat-card">
                <div class="i8-stat-icon i8-bg-success">📋</div>
                <div class="i8-stat-info">
                    <span class="i8-stat-label">تعداد کل اخبار در صف</span>
                    <span class="i8-stat-value"><?php echo i8_to_persian_num(count($results)); ?> پست</span>
                </div>
            </div>

            <!-- Card 3 -->
            <div class="i8-stat-card">
                <div class="i8-stat-icon i8-bg-warning">⚡</div>
                <div class="i8-stat-info">
                    <span class="i8-stat-label">ساعت کار ربات / فاصله انتشار</span>
                    <span class="i8-stat-value">
                        <?php 
                        $start_time = safe_get_option('start_cron_time', '08:00');
                        $end_time = safe_get_option('end_cron_time', '22:00');
                        echo i8_to_persian_num($start_time) . ' تا ' . i8_to_persian_num($end_time); 
                        ?>
                        <span class="small-font text-secondary" style="font-size: 13px; color: #64748b;">(<?php echo $recurrence; ?>)</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Main Posts Table -->
        <div class="i8-card">
            <h2 class="i8-card-title">📰 پست‌های در انتظار انتشار نوبتی (حداکثر ۱۰۰ مورد اخیر)</h2>
            <div class="i8-table-wrapper">
                <table class="i8-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">نوبت</th>
                            <th>عنوان خبر</th>
                            <th>اولویت انتشار</th>
                            <th>زمان تقریبی انتشار</th>
                            <th>وضعیت فعلی</th>
                            <th>نویسنده</th>
                            <th style="text-align: left; width: 120px;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($results):
                            $counter = 0;
                            foreach ($results as $key => $item):
                                $post_id = $item->post_id;
                                $post_status = safe_get_post_status($post_id);
                        ?>
                            <tr id="item-<?php echo $item->id; ?>">
                                <td><strong><?php echo i8_to_persian_num($key + 1); ?></strong></td>
                                <td>
                                    <a href="<?php echo safe_get_edit_post_link($post_id); ?>" target="_blank" style="color: var(--i8-primary); text-decoration: none; font-weight: 600;">
                                        <?php echo safe_get_the_title($post_id); ?>
                                    </a>
                                </td>
                                <td>
                                    <select class="i8-priority-select" data-id="<?php echo $item->id; ?>" data-val="<?php echo esc_attr($item->publish_priority); ?>">
                                        <option value="high" <?php selected($item->publish_priority, 'high'); ?>>🔥 اولویت بالا</option>
                                        <option value="medium" <?php selected($item->publish_priority, 'medium'); ?>>⚠️ اولویت متوسط</option>
                                        <option value="low" <?php selected($item->publish_priority, 'low'); ?>>✅ اولویت پایین</option>
                                    </select>
                                </td>
                                <td>
                                    <?php
                                    if ($timestamp) {
                                        $post_scheduled_timestamp = $timestamp;
                                        if ($counter >= 1 && $recurrence_seconds > 0) {
                                            $post_scheduled_timestamp = $timestamp + ($recurrence_seconds * $counter);
                                        }
                                        
                                        $tz = wp_timezone();
                                        $date_time = new DateTime('@' . $post_scheduled_timestamp);
                                        $date_time->setTimezone($tz);
                                        $scheduled_post_day_gregorian = $date_time->format('Y-m-d');

                                        $today_dt = new DateTime('now', $tz);
                                        $today_gregorian = $today_dt->format('Y-m-d');

                                        $tomorrow_dt = clone $today_dt;
                                        $tomorrow_dt->modify('+1 day');
                                        $tomorrow_gregorian = $tomorrow_dt->format('Y-m-d');

                                        if (class_exists('i8_jDateTime')) {
                                            $jdate = new i8_jDateTime(true, true, $tz->getName());
                                            if ($scheduled_post_day_gregorian == $today_gregorian) {
                                                echo 'امروز ساعت ' . i8_to_persian_num($jdate->date('H:i', $post_scheduled_timestamp));
                                            } elseif ($scheduled_post_day_gregorian == $tomorrow_gregorian) {
                                                echo 'فردا ساعت ' . i8_to_persian_num($jdate->date('H:i', $post_scheduled_timestamp));
                                            } else {
                                                echo i8_to_persian_num($jdate->date('Y/m/d H:i', $post_scheduled_timestamp));
                                            }
                                        } else {
                                            echo i8_to_persian_num($date_time->format('Y-m-d H:i'));
                                        }
                                        $counter++;
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $s_class = 'i8-badge-draft';
                                    $s_text = 'پیش‌نویس';
                                    if ($post_status === 'publish') { $s_class = 'i8-badge-published'; $s_text = 'منتشر شده'; }
                                    elseif ($post_status === 'trash') { $s_class = 'i8-badge-trash'; $s_text = 'زباله‌دان'; }
                                    echo '<span class="i8-badge ' . $s_class . '">' . $s_text . '</span>';
                                    ?>
                                </td>
                                <td><?php echo safe_get_author_name($post_id); ?></td>
                                <td>
                                    <div class="i8-actions">
                                        <a href="<?php echo safe_get_edit_post_link($post_id); ?>" class="i8-action-icon" title="ویرایش پست" target="_blank">📝</a>
                                        <a href="<?php echo safe_get_permalink($post_id); ?>" class="i8-action-icon" title="نمایش پست" target="_blank">🔗</a>
                                        <button class="i8-action-icon i8-action-icon-delete delete-link" data-id="<?php echo $item->id; ?>" title="حذف از صف">❌</button>
                                    </div>
                                </td>
                            </tr>
                        <?php
                            endforeach;
                        else:
                        ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: #64748b;">
                                    📭 در حال حاضر هیچ پستی در صف زمان‌بندی وجود ندارد.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Terminal Logs Widget -->
        <div class="i8-card">
            <h2 class="i8-card-title">🖥️ آخرین گزارش فعالیت‌های موتور انتشار</h2>
            <div class="i8-terminal">
                <?php
                if (function_exists('display_rss_reports')) {
                    $all_reports = display_rss_reports();
                    if (!empty($all_reports)) {
                        // فیلتر کردن لاگ‌ها جهت نمایش اختصاصی موارد مربوط به انتشار پست‌ها
                        $publishing_logs = array();
                        foreach ($all_reports as $log) {
                            if ($log['action_title'] === 'انتشار پست زمان‌بندی شده') {
                                $publishing_logs[] = $log;
                            }
                        }
                        
                        $logs_slice = array_slice($publishing_logs, 0, 15);
                        foreach ($logs_slice as $log) {
                            $tz = wp_timezone();
                            $date_time = new DateTime($log['pub_date'], new DateTimeZone('UTC'));
                            $date_time->setTimezone($tz);
                            $time_display = $date_time->format('H:i:s');
                            $status_class = ($log['status'] == 0) ? 'i8-terminal-danger' : 'i8-terminal-success';
                            $status_badge = ($log['status'] == 0) ? '[ERROR]' : '[SUCCESS]';
                            
                            // واکشی عنوان پست و کوتاه کردن آن جهت تعادل بصری در ترمینال لاگ
                            $post_title = safe_get_the_title($log['resource_id']);
                            $truncated_title = mb_substr($post_title, 0, 40);
                            if (mb_strlen($post_title) > 40) {
                                $truncated_title .= '...';
                            }
                            $post_identifier = sprintf('پست #%d (%s)', $log['resource_id'], $truncated_title);
                            ?>
                            <div class="i8-terminal-line">
                                <span class="i8-terminal-time">[<?php echo i8_to_persian_num($time_display); ?>]</span>
                                <span class="<?php echo $status_class; ?>"><?php echo $status_badge; ?></span>
                                <span style="color: #38bdf8; font-weight: 600;"><?php echo safe_esc_html($post_identifier); ?>:</span>
                                <span><?php echo i8_to_persian_num(safe_esc_html($log['error_msg'])); ?></span>
                            </div>
                            <?php
                        }
                        if (empty($publishing_logs)) {
                            echo '<div class="text-secondary">هیچ فعالیت انتشاری تاکنون ثبت نشده است.</div>';
                        }
                    } else {
                        echo '<div class="text-secondary">هیچ لاگ یا فعالیتی ثبت نشده است.</div>';
                    }
                } else {
                    echo '<div class="text-secondary">تابع گزارشات در دسترس نیست.</div>';
                }
                ?>
            </div>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            // تغییر داینامیک استایل در هنگام تغییر اولویت‌ها
            $('.i8-priority-select').change(function() {
                var select = $(this);
                var itemId = select.data('id');
                var priority = select.val();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'update_priority',
                        id: itemId,
                        priority: priority
                    },
                    dataType: 'json',
                    beforeSend: function() {
                        select.css('opacity', '0.5').prop('disabled', true);
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            select.attr('data-val', priority);
                            // یک افکت فلش سبز رنگ کوچک برای تایید تغییر
                            select.css('border-color', '#10b981');
                            setTimeout(function() {
                                location.reload(); // رفرش صفحه برای اعمال اولویت جدید و مرتب‌سازی مجدد صف
                            }, 400);
                        } else {
                            alert('❌ خطا: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('❌ مشکلی در ارتباط با سرور پیش آمد.');
                    },
                    complete: function() {
                        select.css('opacity', '1').prop('disabled', false);
                    }
                });
            });

            $('.delete-link').click(function(e) {
                e.preventDefault();
                var itemId = $(this).data('id');
                var button = $(this);

                if (confirm('آیا از حذف این آیتم از صف انتشار اطمینان دارید؟')) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'delete_item',
                            id: itemId
                        },
                        dataType: 'json',
                        beforeSend: function() {
                            button.prop('disabled', true).text('⏳');
                        },
                        success: function(response) {
                            if (response.status === 'success') {
                                $('#item-' + itemId).fadeOut("slow", function() {
                                    $(this).remove();
                                    if ($('.i8-table tbody tr').length === 0) {
                                        $('.i8-table tbody').append('<tr><td colspan="7" style="text-align: center; padding: 40px; color: #64748b;">📭 در حال حاضر هیچ پستی در صف زمان‌بندی وجود ندارد.</td></tr>');
                                    }
                                });
                            } else {
                                alert('❌ خطا: ' + response.message);
                                button.prop('disabled', false).text('❌');
                            }
                        },
                        error: function() {
                            alert('❌ مشکلی در ارتباط با سرور پیش آمد.');
                            button.prop('disabled', false).text('❌');
                        }
                    });
                }
            });
            
            window.i8_recreate_scheduled_action = function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'i8_recreate_scheduled_action',
                        nonce: '<?php echo wp_create_nonce("i8_recreate_action"); ?>'
                    },
                    dataType: 'json',
                    beforeSend: function() {
                        $('button[onclick="i8_recreate_scheduled_action()"]').prop('disabled', true).text('در حال بازیابی...');
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('✅ موتور زمان‌بندی با موفقیت بازیابی و فعال شد!');
                            location.reload();
                        } else {
                            alert('❌ خطا در بازیابی: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('❌ مشکلی در ارتباط با سرور پیش آمد');
                    },
                    complete: function() {
                        $('button[onclick="i8_recreate_scheduled_action()"]').prop('disabled', false).text('🔄 بازیابی خودکار');
                    }
                });
            };
        });
    </script>
<?php
}
