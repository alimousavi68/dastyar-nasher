<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure WordPress functions are available
if (!function_exists('add_action')) {
    return;
}


// فراخوانی تابع افزودن صفحه تنظیمات
if (function_exists('add_action')) {
    add_action('admin_menu', 'i8_add_scheduleـqueue_page_menu');
} else {
    // Fallback if add_action is not available
    return;
}

// تابع برای اضافه کردن صفحه تنظیمات
function i8_add_scheduleـqueue_page_menu()
{
    if (function_exists('add_submenu_page')) {
        add_submenu_page(
            'edit.php',
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

function safe_esc_attr($text) {
    if (function_exists('esc_attr')) {
        return esc_attr($text);
    }
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

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
        return get_the_title($post_id);
    }
    return 'عنوان پست';
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
            return '<span class="text-danger">' . 'اولویت بالا' . '</span>';
            break;
        case 'medium':
            return '<span class="text-warning">' . 'اولویت متوسط' . '</span>';
            break;
        case 'low':
            return '<span class="text-success">' . 'اولویت پایین' . '</span>';
            break;
        default:
            return $priority;
    }
}

// تابع بازگشتی برای نمایش بخش تنظیمات
function pc_schedule_queue_page_callback()
{
    // یک کویری میخام که بره لیستی از رکوردهای جدول i8_pc_post_schedule رو برام واکشی کنه و در یک متغییر بهم بده
    global $wpdb;
    $results = array();
    $post_publish_priority = array();
    
    if (isset($wpdb) && is_object($wpdb) && method_exists($wpdb, 'get_results')) {
        $query = "SELECT * FROM {$wpdb->prefix}pc_post_schedule ORDER BY FIELD(publish_priority, 'high', 'medium', 'low'), id ASC ";
        $results = $wpdb->get_results($query);
        // رکورد های result بر اساس اینکه اول اونایی که فیلد publish_priority شون high هست و بعد اونایی که medium هست و بعد اونایی که low هست بهم مرتب کن

        $query = "SELECT publish_priority, COUNT(*) as count FROM {$wpdb->prefix}pc_post_schedule GROUP BY publish_priority";
        $post_publish_priority = $wpdb->get_results($query);
    }


?>
    <link href="<?php echo (COP_PLUGIN_URL . '/assets/css/bootstrap.min.css'); ?>" rel="stylesheet">

    <style>
        .row-counter {
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 13px;
            font-family: serif !important;
            color: #767575;
        }

        .item-meta-data {
            font-size: 13px;
            color: #767575;
        }

        .wp-filter {
            border: 3px solid #f2f2f2;
        }

        .action-bar .btn {
            max-height: 31px;
        }

        .table {
            border: 3px solid #f2f2f2;
        }

        .th {
            padding: 5px;

            font-weight: bold;
            min-height: 50px;
            display: flex !important;
            align-content: center;
            justify-content: flex-start;
            flex-wrap: wrap;
            align-items: center;
            color: #757575;
        }

        .tr:nth-child(even) {
            background-color: #f2f2f2;
            color: #4c4c4ccc;
        }

        .tr:nth-child(odd) {
            background-color: #ffffff;
        }

        .tr:hover {
            background-color: rgb(209 209 209);
        }

        .feed-item-title a {
            color: #297aa9;
            text-decoration: none !important;
        }


        @keyframes blink {
            0% {
                background-color: #f0f0f1;
            }

            50% {
                background-color: gold;
            }

            100% {
                background-color: #f0f0f1;
            }
        }

        .blinking {
            animation: blink 2s infinite;
            filter: blur(1.2px);
        }

        .scraped-feeds-table .tr:hover {
            background-color: #eaf9ff;
        }

        /* pagination */
        .tablenav-pages .page-numbers {
            display: flex;
            gap: 15px;
            padding-left: 0;
        }

        .tablenav .tablenav-pages li {
            display: flex;
            flex-wrap: nowrap;
            align-content: center;
            justify-content: center;
            min-width: 30px;
            min-height: 30px;
            margin: 0;
            padding: 0 4px;
            font-size: 16px;
            line-height: 1.625;
            text-align: center;
            color: #000;
            border: 3px solid #f2f2f2;
            border-radius: 1px;

        }

        .tablenav .tablenav-pages li:hover {
            background-color: #f2f2f2;

        }

        .tablenav .tablenav-pages li a {
            text-decoration: none;
            color: #297aa9;
            width: 100%;
            height: 100%;
        }
    </style>

    <style>
        .page_header {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            align-content: center;
            justify-content: space-between;
            align-items: center;
        }

        .page_info {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            background: #f0f0f1; /* Light gray background */
            padding: 25px 35px;
            border-radius: 16px;
            box-shadow: 0 2px 12px #e2e7ff80;
            font-size: 15px;
            width: 100%;
            box-sizing: border-box;
        }

        .i8-flex-column {
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: #fff;
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 1px 6px #e2e7ff40;
            min-width: 260px;
            flex: 1;
        }

        .i8-flex-column span, .i8-flex-column p {
            font-size: 15px;
            color: #444;
            margin-bottom: 0;
        }

        .i8-flex-column span span:first-child {
            color: #6c63ff;
            font-weight: bold;
        }

        .i8-flex-column span span:last-child {
            color: #297aa9;
            font-weight: bold;
        }

        .small-font {
            font-size: 13px;
        }
    </style>

    <div class="wrap d-flex flex-column gap-4">
        <div class="page_header">
            <div class="page_title">
                <h1>صف انتشار</h1>
                <p>صف انتشار پست های دستیار</p>
            </div>
            <div class="page_info">
                <div class="i8-flex-column ">
                    <span>
                        <span>⏰  اجرای بعدی: </span>
                        <span><?php
                                try {
                                    if (file_exists(COP_PLUGIN_DIR_PATH . '/library/jdatetime.class.php')) {
                                        require_once(COP_PLUGIN_DIR_PATH . '/library/jdatetime.class.php');
                                    }
                                    
                                    $recurrence = '-'; // Initialize recurrence
                                    $recurrence_seconds = 0; // Initialize recurrence_seconds
                                    $timestamp = null; // Initialize timestamp
                                    
                                    if (function_exists('as_next_scheduled_action') && function_exists('as_get_scheduled_actions')) {
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
                                                $schedule = null;
                                                if (method_exists($action, 'get_schedule')) {
                                                    $schedule = $action->get_schedule();
                                                }
                                                
                                                if ($schedule && is_object($schedule)) {
                                     try {
                                         $recurrence_seconds = safe_get_recurrence($schedule);
                                         if ($recurrence_seconds) {
                                             $recurrence = 'هر ' . ($recurrence_seconds / 60) . ' دقیقه';
                                         }
                                     } catch (Exception $e) {
                                         // Ignore method call errors
                                     }
                                 }
                                 
                                 if ($schedule && is_object($schedule)) {
                                     try {
                                         $scheduled_date = safe_get_schedule_date($schedule);
                                         if ($scheduled_date && is_object($scheduled_date) && method_exists($scheduled_date, 'getTimestamp')) {
                                             $timestamp = $scheduled_date->getTimestamp();
                                             date_default_timezone_set('Asia/Tehran');
                                         }
                                     } catch (Exception $e) {
                                         // Ignore method call errors
                                     }
                                 }
                                            }
                                        }
                                        
                                        if ($timestamp) {
                                            // Get cron working hours
                            $start_cron_time = safe_get_option('start_cron_time');
                             $end_cron_time = safe_get_option('end_cron_time');

                                            // Adjust timestamp based on working hours
                                            if ($start_cron_time && $end_cron_time) {
                                                $today = date('Y-m-d', $timestamp);
                                                $start_today_timestamp = strtotime($today . ' ' . $start_cron_time);
                                                $end_today_timestamp = strtotime($today . ' ' . $end_cron_time);

                                                // Handle overnight working hours (e.g., 22:00 to 06:00)
                                                if ($end_today_timestamp <= $start_today_timestamp) {
                                                    if ($timestamp >= $start_today_timestamp) {
                                                        $end_today_timestamp += 86400;
                                                    } else {
                                                        $start_today_timestamp -= 86400;
                                                    }
                                                }

                                                if ($timestamp < $start_today_timestamp) {
                                                    $timestamp = $start_today_timestamp;
                                                } elseif ($timestamp > $end_today_timestamp) {
                                                    $timestamp = $start_today_timestamp + 86400;
                                                }
                                            }

                                            // Check if jDateTime class exists before using it
                                            if (class_exists('i8_jDateTime')) {
                                                // Validate timestamp before using with jDateTime
                                                if (empty($timestamp) || !is_numeric($timestamp) || $timestamp <= 0) {
                                                    echo '❌ زمان نامعتبر برای نمایش';
                                                } else {
                                                    $jdate = new i8_jDateTime(true, true, 'Asia/Tehran');
                                                    $jalali_date = $jdate->date('Y/m/d H:i:s', $timestamp);

                                                    $today_gregorian = date('Y-m-d');
                                                    $tomorrow_gregorian = date('Y-m-d', strtotime('+1 day'));
                                                    $scheduled_day_gregorian = date('Y-m-d', $timestamp);

                                                    $display_date = '';
                                                    if ($scheduled_day_gregorian == $today_gregorian) {
                                                        $display_date = 'امروز ساعت ' . $jdate->date('H:i', $timestamp);
                                                    } elseif ($scheduled_day_gregorian == $tomorrow_gregorian) {
                                                        $display_date = 'فردا ساعت ' . $jdate->date('H:i', $timestamp);
                                                    } else {
                                                        $display_date = $jdate->date('Y/m/d H:i', $timestamp);
                                                    }
                                                    echo '<span title="' . safe_esc_attr($jalali_date) . '">' . $display_date . '</span>';
                                                }
                                            } else {
                                                // Fallback to Gregorian date if jDateTime class is not available
                                                if (!empty($timestamp) && is_numeric($timestamp) && $timestamp > 0) {
                                                    echo date('Y-m-d H:i', $timestamp);
                                                } else {
                                                    echo '❌ زمان نامعتبر';
                                                }
                                            }
                                        } else {
                                            echo '❌ هیچ اجرای برنامه‌ریزی‌شده‌ای برای این هوک پیدا نشد.';
                                        }
                                    } else {
                                        echo '❌ این افزونه برای استفاده از این قابلیت باید افزونه اکشن اسکدر را نصب کنید.';
                                    }
                                } catch (Exception $e) {
                                    echo '❌ خطا در نمایش زمان اجرای بعدی: ' . safe_esc_html($e->getMessage());
                                }
                                ?></span>
                    </span>
                    <p>
                        <span>📰 تعداد خبرهای امروز:</span>
                        <span><?php echo safe_get_option('daily_post_count_for_schedule', '-'); ?></span>
                    </p>
                    <p>
                        <span>⏱️ فاصله انتشار خبرها:</span>
                        <span><?php echo isset($recurrence) ? $recurrence : '-'; ?></span>
                    </p>
                </div>
                <div class="i8-flex-column">
                    <p>
                        <span>📝 جمع کل اخبار در صف:</span>
                        <span><?php echo isset($results) ? count($results) : '-'; ?></span>
                    </p>
                    <p>
                        <span>▶️ ساعت شروع ربات:</span>
                        <span><?php echo safe_get_option('start_cron_time', '-'); ?></span>
                    </p>
                    <p>
                        <span>⏹️ ساعت پایان ربات:</span>
                        <span><?php echo safe_get_option('end_cron_time', '-'); ?></span>
                    </p>

                    <small style="font-size: 12px; color: #777;">ساعات انتشار تقریبی هستند و ممکن است کمی تاخیر یا تقدم داشته باشند.</small>
                </div>
                <div class="i8-flex-column">
                    <?php
                    if (!empty($post_publish_priority)) {
                        // Reorder priorities to display high, medium, then low
                        $ordered_priorities = [];
                        foreach ($post_publish_priority as $item) {
                            $ordered_priorities[$item->publish_priority] = $item;
                        }

                        $display_order = ['high', 'medium', 'low'];

                        foreach ($display_order as $priority_key) {
                            if (isset($ordered_priorities[$priority_key])) {
                                $priority_item = $ordered_priorities[$priority_key];
                                $emoji = '';
                                switch ($priority_item->publish_priority) {
                                    case 'high':
                                        $emoji = '🔥 '; // Fire emoji for high priority
                                        break;
                                    case 'medium':
                                        $emoji = '⚠️ '; // Warning emoji for medium priority
                                        break;
                                    case 'low':
                                        $emoji = '✅ '; // Checkmark emoji for low priority
                                        break;
                                }
                                echo '<p><span>' . $emoji . post_priority_persian($priority_item->publish_priority) . ':</span> <span>' . $priority_item->count . '</span></p>';
                            }
                        }
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="post-list">
            <div class="table table-hover px-3 ">
                <div class="row d-none d-md-flex">
                    <div class="th col col-auto"> نوبت </div>
                    <div class="col col-9 col-md-11 row ">
                        <div class="th col col-12 col-xl-7">عنوان</div>
                        <div class="th col col-4 col-xl-1  d-none d-md-flex">اولویت</div>
                        <div class="th col col-4 col-xl-1  d-none d-md-flex">زمان انتشار</div>
                        <div class="th col col-8 col-xl-1  d-none d-md-flex">وضعیت</div>
                        <div class="th col col-8 col-xl-1  d-none d-md-flex">نویسنده</div>
                        <div class="th col col-12 col-xl-2 d-none d-md-flex"></div>
                    </div>
                </div>


                <?php
                if ($results && isset($timestamp)):
                    $first_publishe_time_timestamp = $timestamp;
                    $counter = 0;
                    foreach ($results as $key => $item):

                ?>
                        <div class="tr row p-2" id="item-<?php echo $item->id; ?>">
                            <div class="col-auto bg-transparent row-counter"><?php echo $key + 1; ?></div>
                            <div class="col-11 row bg-transparent">
                                <div class="col-12 col-xl-7 bg-transparent feed-item-title">
                                    <a href="<?php echo safe_get_edit_post_link($item->post_id); ?>" target="_blank"><?php echo safe_get_the_title($item->post_id); ?></a>
                                </div>
                                <div class="col-4 col-xl-1 bg-transparent text-secondary item-meta-data">

                                    <?php
                                    $priority = $item->publish_priority;
                                    echo post_priority_persian($priority);

                                    ?></span>
                                </div>
                                <div class="col-4 col-xl-1 bg-transparent text-secondary item-meta-data">
                                    <?php
                                    try {
                                        date_default_timezone_set('Asia/Tehran');
                                        
                                        // Validate timestamp before using it
                                        if (empty($timestamp) || !is_numeric($timestamp) || $timestamp <= 0) {
                                            echo '❌ زمان نامعتبر';
                                            continue;
                                        }
                                        
                                        $post_scheduled_timestamp = $timestamp;
                                        if ($counter >= 1 && isset($recurrence_seconds) && $recurrence_seconds > 0) {
                                            $step = ($recurrence_seconds * $counter);
                                            $post_scheduled_timestamp = ($timestamp + $step);
                                        }

                                        // Additional validation for calculated timestamp
                                        if (empty($post_scheduled_timestamp) || !is_numeric($post_scheduled_timestamp) || $post_scheduled_timestamp <= 0) {
                                            echo '❌ زمان محاسبه شده نامعتبر';
                                            continue;
                                        }

                                        if (class_exists('i8_jDateTime')) {
                                            $jdate = new i8_jDateTime(true, true, 'Asia/Tehran');
                                            $jalali_post_date = $jdate->date('Y/m/d H:i:s', $post_scheduled_timestamp);

                                            $today_gregorian = date('Y-m-d');
                                            $tomorrow_gregorian = date('Y-m-d', strtotime('+1 day'));
                                            $scheduled_post_day_gregorian = date('Y-m-d', $post_scheduled_timestamp);

                                            $display_post_date = '';
                                            if ($scheduled_post_day_gregorian == $today_gregorian) {
                                                $display_post_date = 'امروز ساعت ' . $jdate->date('H:i', $post_scheduled_timestamp);
                                            } elseif ($scheduled_post_day_gregorian == $tomorrow_gregorian) {
                                                $display_post_date = 'فردا ساعت ' . $jdate->date('H:i', $post_scheduled_timestamp);
                                            } else {
                                                $display_post_date = $jdate->date('Y/m/d H:i', $post_scheduled_timestamp, true, true);
                                            }
                                            echo '<span title="' . safe_esc_attr($jalali_post_date) . '">' . $display_post_date . '</span>';
                                        } else {
                                            // Fallback to Gregorian date if jDateTime class is not available
                                            echo date('Y-m-d H:i', $post_scheduled_timestamp);
                                        }
                                        
                                        $counter++;
                                    } catch (Exception $e) {
                                        echo '❌ خطا در نمایش تاریخ';
                                    }
                                    ?>
                                </div>
                                <div class="col-8 col-xl-1 bg-transparent text-secondary item-meta-data" style="direction:left">
                                    <?php
                                    $post_status = safe_get_post_status($item->post_id);
                                    switch ($post_status) {
                                        case 'draft':
                                            echo '<span class="text-primary">' . 'پیش نویس' . '</span>';
                                            break;
                                        case 'publish':
                                            echo '<span class="text-danger"> ' .
                                                ' <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-triangle" viewBox="0 0 16 16"><path d="M7.938 2.016A.13.13 0 0 1 8.002 2a.13.13 0 0 1 .063.016.15.15 0 0 1 .054.057l6.857 11.667c.036.06.035.124.002.183a.2.2 0 0 1-.054.06.1.1 0 0 1-.066.017H1.146a.1.1 0 0 1-.066-.017.2.2 0 0 1-.054-.06.18.18 0 0 1 .002-.183L7.884 2.073a.15.15 0 0 1 .054-.057m1.044-.45a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767z"/><path d="M7.002 12a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 5.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/></svg>'
                                                . ' منتشر شده '
                                                . '</span>';
                                            break;
                                        case 'pending':
                                            echo '<span class="text-danger">' . 'در انتظار بررسی' . '</span>';
                                            break;
                                        case 'future':
                                            echo '<span class="text-danger">' . 'زمانبدی شده' . '</span>';
                                            break;
                                        case 'trash':
                                            echo '<span class="text-danger">' . 'حذف شده' . '</span>';
                                            break;
                                        default:
                                            echo $post_status;
                                    }
                                    ?>
                                </div>
                                <div class="col-8 col-xl-1 bg-transparent text-secondary item-meta-data" style="direction:left">
                                    <?php echo safe_get_author_name($item->post_id); ?>
                                </div>
                                <div class="col-12 col-xl-2 row gap-2 bg-transparent action-bar">
                                    <a class="col btn btn-sm rounded-pill btn-outline-secondary" title="ویرایش فید" target="_blank"
                                        href="<?php echo safe_get_edit_post_link($item->post_id); ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                            class="bi bi-pencil-square" viewBox="0 0 16 16">
                                            <path
                                                d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z" />
                                            <path fill-rule="evenodd"
                                                d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z" />
                                        </svg>
                                    </a>
                                    <a class="col btn btn-sm rounded-pill btn-outline-secondary " title="نمایش فید" target="_blank"
                                        href="<?php echo safe_get_permalink($item->post_id); ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                            class="bi bi-box-arrow-up-right" viewBox="0 0 16 16">
                                            <path fill-rule="evenodd"
                                                d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5" />
                                            <path fill-rule="evenodd"
                                                d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0z" />
                                        </svg>
                                    </a>
                                    <a class="delete-link col btn btn-sm rounded-pill btn-outline-danger"
                                        id="item-<?php echo $item->id; ?>" data-id="<?php echo $item->id; ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                            class="bi bi-bookmark-x" viewBox="0 0 16 16">
                                            <path fill-rule="evenodd"
                                                d="M6.146 5.146a.5.5 0 0 1 .708 0L8 6.293l1.146-1.147a.5.5 0 1 1 .708.708L8.707 7l1.147 1.146a.5.5 0 0 1-.708.708L8 7.707 6.854 8.854a.5.5 0 1 1-.708-.708L7.293 7 6.146 5.854a.5.5 0 0 1 0-.708">
                                            </path>
                                            <path
                                                d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.777.416L8 13.101l-5.223 2.815A.5.5 0 0 1 2 15.5zm2-1a1 1 0 0 0-1 1v12.566l4.723-2.482a.5.5 0 0 1 .554 0L13 14.566V2a1 1 0 0 0-1-1z">
                                            </path>
                                        </svg>
                                    </a>

                                </div>
                            </div>
                        </div>
                <?php

                    endforeach;
                endif;
                ?>

            </div>
        </div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            $('.delete-link').click(function(e) {
                e.preventDefault();
                var itemId = $(this).data('id'); // دریافت ID از attribute data-id

                $.ajax({
                    url: ajaxurl, // این متغیر توسط وردپرس تعریف شده است
                    type: 'POST',
                    data: {
                        action: 'delete_item', // این نام عملیات است که در PHP تعریف می‌کنیم
                        id: itemId
                    },
                    dataType: 'json',
                    success: function(response) {
                        var query_status = response.status;
                        delete_html_list_item(query_status, itemId);
                        // alert(response.message);
                    },
                    error: function(response) {
                        alert('مشکلی پیش امد')
                    }
                });

                function delete_html_list_item(query_status, itemId) {
                    if (query_status == 'success') {
                        $('#item-' + itemId).toggle("slow", function() {
                            $(this).remove();
                        });
                    }
                }

            });
        });
    </script>

<?php


}
