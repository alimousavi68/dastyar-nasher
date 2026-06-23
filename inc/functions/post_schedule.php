<?php

// Track the active action ID of Action Scheduler
global $i8_current_action_id;
$i8_current_action_id = null;

if (function_exists('add_action')) {
    add_action('action_scheduler_begin_execute', function($action_id) {
        global $i8_current_action_id;
        $i8_current_action_id = $action_id;
    });

    add_action('action_scheduler_after_execute', function($action_id) {
        global $i8_current_action_id;
        if ($i8_current_action_id === $action_id) {
            $i8_current_action_id = null;
        }
    });
}

/**
 * ثبت لاگ در دیتابیس Action Scheduler برای اکشن در حال اجرا
 */
function i8_action_log($message) {
    global $i8_current_action_id;
    if ($i8_current_action_id && class_exists('ActionScheduler') && is_callable(array('ActionScheduler', 'logger'))) {
        ActionScheduler::logger()->log($i8_current_action_id, $message);
    }
    error_log('i8 Action Scheduler: ' . $message);
}

// Recive Ajax request and manage it
if (isset($_POST['action']) && !empty($_POST['action'])) {
    if ($_POST['action'] == 'delete_item') {

        $item_id = intval($_POST['id']);

        $response = i8_delete_item_at_scheulde_list($item_id);
        echo json_encode($response);
        wp_die();
    } elseif ($_POST['action'] == 'update_priority') {
        $item_id = intval($_POST['id']);
        $priority = sanitize_text_field($_POST['priority']);
        
        if (!in_array($priority, array('high', 'medium', 'low'))) {
            echo json_encode(array('status' => 'error', 'message' => 'اولویت نامعتبر است.'));
            wp_die();
        }
        
        global $wpdb;
        $table_post_schedule = $wpdb->prefix . 'pc_post_schedule';
        $updated = $wpdb->update(
            $table_post_schedule,
            array('publish_priority' => $priority),
            array('id' => $item_id)
        );
        
        if ($updated !== false) {
            echo json_encode(array('status' => 'success', 'message' => 'اولویت با موفقیت به‌روزرسانی شد.'));
        } else {
            echo json_encode(array('status' => 'error', 'message' => 'خطا در به‌روزرسانی اولویت.'));
        }
        wp_die();
    }
}

/**
 * بررسی و بازیابی خودکار scheduled action در صورت نیاز
 */
function i8_check_and_recover_scheduled_action() {
    // بررسی وجود Action Scheduler
    if (!function_exists('as_next_scheduled_action')) {
        return false;
    }
    
    // بررسی وجود scheduled action
    $next_scheduled = as_next_scheduled_action('i8_action_publish_post_at_scheduling_table');
    
    if (!$next_scheduled) {
        // اگر scheduled action وجود ندارد، آن را بازیابی کن
        error_log('i8: scheduled action پیدا نشد، در حال بازیابی...');
        
        // اجرای action برای تنظیم مجدد
        if (function_exists('do_action')) {
            do_action('i8_action_set_cron_job_publishe_posts');
        }
        
        // بررسی مجدد
        $recovered = as_next_scheduled_action('i8_action_publish_post_at_scheduling_table');
        
        if ($recovered) {
            error_log('i8: scheduled action با موفقیت بازیابی شد');
            return true;
        } else {
            error_log('i8: خطا در بازیابی scheduled action');
            return false;
        }
    }
    
    return true;
}

/**
 * بهبود تابع محاسبه زمان انتشار با بررسی اضافی
 */
function i8_improved_calculate_post_publish_time() {
    // ابتدا بررسی کن که scheduled action وجود دارد
    if (!i8_check_and_recover_scheduled_action()) {
        error_log('i8: مشکل در scheduled action، امکان محاسبه زمان انتشار وجود ندارد');
        return false;
    }
    
    // اجرای تابع اصلی محاسبه زمان
    return calculate_post_publish_time();
}

// اجرای بررسی خودکار هر ساعت
if (function_exists('wp_next_scheduled') && !wp_next_scheduled('i8_hourly_check_scheduled_action')) {
    if (function_exists('wp_schedule_event')) {
        wp_schedule_event(time(), 'hourly', 'i8_hourly_check_scheduled_action');
    }
}
if (function_exists('add_action')) {
    add_action('i8_hourly_check_scheduled_action', 'i8_check_and_recover_scheduled_action');
}


// Delete item from " pc_post_schedule " table at database
function i8_delete_item_at_scheulde_list($id = '', $post_id = '')
{
    global $wpdb;
    $table_post_schedule = $wpdb->prefix . 'pc_post_schedule';
    if ($id) {
        $deleted = $wpdb->delete($table_post_schedule, array('id' => $id));
    } elseif ($post_id) {
        $deleted = $wpdb->delete($table_post_schedule, array('post_id' => $post_id));
    }

    if ($deleted > 0) {
        return array('status' => 'success', 'message' => 'تعداد ' . $deleted . ' ردیف با موفقیت حذف شد.');
    } else {
        return array('status' => 'error', 'message' => 'هیچ ردیفی حذف نشد. امکان دارد شناسه مورد نظر وجود نداشته باشد.');
    }
}

// add post to post schedule table 
function add_post_to_post_schedule_table($post_id, $post_priority)
{
    global $wpdb;
    $pc_post_schedule_table_name = $wpdb->prefix . 'pc_post_schedule';
    $wpdb->insert(
        $pc_post_schedule_table_name,
        array(
            'post_id' => $post_id,
            'publish_priority' => $post_priority
        )
    );

    // ثبت به عنوان اکشن تکی در Action Scheduler
    if (function_exists('as_schedule_single_action')) {
        $slot = i8_get_next_available_publishing_slot();
        $adjusted_slot = i8_adjust_timestamp_to_working_hours($slot);
        as_schedule_single_action($adjusted_slot, 'i8_action_publish_specific_post', array('post_id' => $post_id), 'i8_post_publisher');
        i8_action_log(sprintf('پست %d مستقیماً زمان‌بندی شد در Action Scheduler برای زمان: %s', $post_id, date('Y-m-d H:i:s', $adjusted_slot)));
    }
}

// check post exist in post schedul table and retrive post priority
function cop_get_post_priority($post_id)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'pc_post_schedule';

    $query = $wpdb->prepare("SELECT publish_priority FROM $table_name WHERE post_id = %d LIMIT 1", $post_id);

    $post_priority = $wpdb->get_var($query);

    if ($post_priority !== null) {
        return $post_priority;
    } else {
        return null;
    }
}

// update Exist post priority in pc post scheudle table
function cop_update_post_priority($post_id, $new_priority)
{
    global $wpdb;
    $pc_post_schedule_table_name = $wpdb->prefix . 'pc_post_schedule';
    $wpdb->update(
        $pc_post_schedule_table_name,
        array(
            'publish_priority' => $new_priority
        ),
        array('post_id' => $post_id),
        array('%s'), // فرمت داده‌های جدید
        array('%d')  // فرمت شرایط
    );
}


function i8_change_post_status($priority_posts)
{
    // //error_log('i8_change_post_status RUNNING');

    global $wpdb;
    $table_post_schedule = $wpdb->prefix . 'pc_post_schedule';

    foreach ($priority_posts as $post) {

        // از $post برای دسترسی به مقادیر مختلف هر ردیف استفاده می‌کنیم
        $id = $post->id;
        $post_id = $post->post_id;

        // chack post status 
        $post_status = get_post_status($post_id);

        if ($post_status == 'draft') {
            // بجای قرار دادن در حالت future، مستقیما منتشر میکنیم تا جلوی انباشت و تاخیر گرفته شود
            $post_data = array(
                'ID' => $post_id,
                'post_status' => 'publish',
                'post_date' => current_time('mysql'),
                'post_date_gmt' => current_time('mysql', 1),
            );
            $updated = wp_update_post($post_data);
            if (is_wp_error($updated)) {
                i8_action_log(sprintf('خطا در تغییر وضعیت پست %d به منتشر شده: %s', $post_id, $updated->get_error_message()));
            } else {
                i8_action_log(sprintf('پست با شناسه %d با موفقیت منتشر شد.', $post_id));
            }

            // delete record where id=$id at $table_post_schedule
            $action_status = $wpdb->delete($table_post_schedule, array('id' => $id));
            if ($action_status) {
                i8_action_log(sprintf('پست %d از جدول صف انتشار حذف شد.', $post_id));
            } else {
                i8_action_log(sprintf('خطا در حذف پست %d از جدول صف انتشار.', $post_id));
            }
        } else {
            i8_action_log(sprintf('پست با شناسه %d در وضعیت پیش‌نویس نبود (وضعیت فعلی: %s). حذف از صف.', $post_id, $post_status));

            i8_delete_item_at_scheulde_list($id, null);
            publish_post_at_scheduling_table();
        }
    }
}


// محاسبه فواصل بین زمان‌های تنظیم شده
// Calculte post published time interval base on user settings
function calculate_post_publish_time()
{
    $post_publisher_start_time = get_option('start_cron_time') ? get_option('start_cron_time') : '';
    $post_publisher_end_time = get_option('end_cron_time') ? get_option('end_cron_time') : '';

    $news_interval_start = get_option('news_interval_start') ? get_option('news_interval_start') : '30';
    $news_interval_end =   get_option('news_interval_end') ? get_option('news_interval_end') : '40';
    $post_publisher_daily_post_count = rand($news_interval_start,$news_interval_end);

    $post_publishe_count = get_option('daily_post_count_for_schedule') ? get_option('daily_post_count_for_schedule') : '60';

    // تبدیل ساعت شروع و پایان به ثانیه از ابتدای روز
    $start_parts = explode(':', $post_publisher_start_time);
    $end_parts = explode(':', $post_publisher_end_time);
    $start_seconds = isset($start_parts[0], $start_parts[1]) ? ($start_parts[0] * 3600 + $start_parts[1] * 60) : 0;
    $end_seconds = isset($end_parts[0], $end_parts[1]) ? ($end_parts[0] * 3600 + $end_parts[1] * 60) : 0;

    // محاسبه ساده فواصل زمانی بدون خطر تقسیم بر صفر
    $interval = $end_seconds - $start_seconds;
    if ($interval <= 0) {
        $interval = 1; // جلوگیری از مقدار صفر
    }
    
    $post_count = max(1, $post_publisher_daily_post_count);
    $post_publishe_interval_time = round($interval / $post_count);
    
    // اگر فاصله زمانی کمتر از ۱ دقیقه شد، حداقل ۶۰ ثانیه در نظر می‌گیریم
    $post_publishe_interval_time = max(60, $post_publishe_interval_time);

    error_log('i8_calculate_post_publish_time DEBUG - start_cron_time: ' . $post_publisher_start_time . ' end_cron_time: ' . $post_publisher_end_time);
    error_log('i8_calculate_post_publish_time DEBUG - start_seconds: ' . $start_seconds . ' end_seconds: ' . $end_seconds);
    error_log('i8_calculate_post_publish_time DEBUG - calculated interval (seconds): ' . $interval);
    error_log('i8_calculate_post_publish_time DEBUG - post_publishe_count: ' . $post_publishe_count);
    error_log('i8_calculate_post_publish_time DEBUG - post_per_hours: ' . $post_per_hours);
    error_log('i8_calculate_post_publish_time DEBUG - post_publishe_interval_time (seconds): ' . $post_publishe_interval_time);

    return $post_publishe_interval_time;
}


// Hook to handle the scheduled event
add_action( 'i8_action_publish_post_at_scheduling_table', 'publish_post_at_scheduling_table' );
function publish_post_at_scheduling_table()
{
    try {
        i8_action_log('شروع فرآیند همگام‌سازی صف انتشار پست‌ها.');

        // همگام‌سازی و مهاجرت داده‌های جدول قدیمی به صف جدید اکشن اسکژولر
        i8_migrate_old_queue_to_action_scheduler();
    } catch (Exception $e) {
        i8_action_log('خطا در اجرای فرآیند همگام‌سازی صف: ' . $e->getMessage());
        error_log('i8: Error in publish_post_at_scheduling_table: ' . $e->getMessage());
    }
}

// Hook to handle individual post scheduling
add_action('i8_action_publish_specific_post', 'i8_publish_specific_post_callback', 10, 1);

/**
 * کالبک انتشار یک پست مشخص زمان‌بندی شده
 */
function i8_publish_specific_post_callback($post_id) {
    i8_action_log(sprintf('شروع انتشار پست تکی با شناسه: %d', $post_id));
    
    $post_status = get_post_status($post_id);
    if ($post_status === 'draft') {
        global $wpdb;
        $table_post_schedule = $wpdb->prefix . 'pc_post_schedule';
        
        $post_data = array(
            'ID' => $post_id,
            'post_status' => 'publish',
            'post_date' => current_time('mysql'),
            'post_date_gmt' => current_time('mysql', 1),
        );
        
        $updated = wp_update_post($post_data);
        if (is_wp_error($updated)) {
            $err_msg = $updated->get_error_message();
            i8_action_log(sprintf('خطا در انتشار پست %d: %s', $post_id, $err_msg));
            if (function_exists('insert_rss_report')) {
                insert_rss_report('انتشار پست زمان‌بندی شده', get_the_title($post_id), $post_id, '0', $err_msg);
            }
            throw new Exception($err_msg);
        } else {
            i8_action_log(sprintf('پست تکی %d با موفقیت منتشر شد.', $post_id));
            if (function_exists('insert_rss_report')) {
                insert_rss_report('انتشار پست زمان‌بندی شده', get_permalink($post_id), $post_id, '1', 'با موفقیت منتشر شد');
            }
            $wpdb->delete($table_post_schedule, array('post_id' => $post_id));
        }
    } else {
        $status_translations = array(
            'publish' => 'منتشر شده',
            'future' => 'زمان‌بندی شده',
            'draft' => 'پیش‌نویس',
            'pending' => 'در انتظار بررسی',
            'private' => 'خصوصی',
            'trash' => 'زباله‌دان',
            'inherit' => 'ارث‌بری',
        );
        $translated_status = isset($status_translations[$post_status]) ? $status_translations[$post_status] : $post_status;
        
        i8_action_log(sprintf('پست %d در وضعیت پیش‌نویس نبود (وضعیت فعلی: %s). حذف از صف.', $post_id, $translated_status));
        if (function_exists('insert_rss_report')) {
            insert_rss_report('انتشار پست زمان‌بندی شده', get_the_title($post_id), $post_id, '0', 'پست پیش‌نویس نبود. وضعیت فعلی: ' . $translated_status);
        }
        global $wpdb;
        $table_post_schedule = $wpdb->prefix . 'pc_post_schedule';
        $wpdb->delete($table_post_schedule, array('post_id' => $post_id));
    }
}

/**
 * محاسبه نوبت بعدی خالی برای انتشار پست به صورت کاملاً ایمن و بهینه
 */
function i8_get_next_available_publishing_slot() {
    static $last_scheduled_timestamp = null;
    $interval = calculate_post_publish_time();
    
    $base_time = time();
    if ($last_scheduled_timestamp !== null) {
        $base_time = $last_scheduled_timestamp;
    } else {
        if (function_exists('as_get_scheduled_actions')) {
            $actions = as_get_scheduled_actions(array(
                'hook' => 'i8_action_publish_specific_post',
                'status' => ActionScheduler_Store::STATUS_PENDING,
                'per_page' => 1,
                'order' => 'DESC',
            ));
            
            if (!empty($actions)) {
                $last_action = array_shift($actions);
                $last_schedule = $last_action->get_schedule()->get_date();
                if ($last_schedule) {
                    $base_time = $last_schedule->getTimestamp();
                }
            }
        }
    }
    
    $next_timestamp = max(time(), $base_time + $interval);
    $last_scheduled_timestamp = $next_timestamp;
    return $next_timestamp;
}

/**
 * تنظیم زمان اجرای اکشن متناسب با ساعت کاری با امنیت منطقه زمانی
 */
function i8_adjust_timestamp_to_working_hours($timestamp) {
    $tz = wp_timezone();
    $start_time_str = get_option('start_cron_time', '08:00');
    $end_time_str = get_option('end_cron_time', '22:00');
    
    $date = new DateTime('@' . $timestamp);
    $date->setTimezone($tz);
    $date_str = $date->format('Y-m-d');
    
    $start_dt = new DateTime($date_str . ' ' . $start_time_str, $tz);
    $end_dt = new DateTime($date_str . ' ' . $end_time_str, $tz);
    
    $start_time = $start_dt->getTimestamp();
    $end_time = $end_dt->getTimestamp();
    
    if ($timestamp < $start_time) {
        return $start_time;
    } elseif ($timestamp > $end_time) {
        $date->modify('+1 day');
        $next_date_str = $date->format('Y-m-d');
        $next_start_dt = new DateTime($next_date_str . ' ' . $start_time_str, $tz);
        return $next_start_dt->getTimestamp();
    }
    return $timestamp;
}

/**
 * انتقال داده‌های موجود در جدول قدیمی به سیستم جدید اکشن اسکژولر
 */
function i8_migrate_old_queue_to_action_scheduler() {
    global $wpdb;
    $table_post_schedule = $wpdb->prefix . 'pc_post_schedule';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_post_schedule'") != $table_post_schedule) {
        return;
    }
    
    $posts = $wpdb->get_results("SELECT * FROM $table_post_schedule ORDER BY FIELD(publish_priority, 'high', 'medium', 'low'), id ASC LIMIT 20");
    if (empty($posts)) {
        return;
    }
    
    i8_action_log(sprintf('یافتن %d پست در جدول قدیمی؛ در حال همگام‌سازی و برنامه‌ریزی در صف جدید...', count($posts)));
    
    foreach ($posts as $post) {
        $post_id = intval($post->post_id);
        
        if (function_exists('as_has_scheduled_action')) {
            if (!as_has_scheduled_action('i8_action_publish_specific_post', array('post_id' => $post_id))) {
                $slot = i8_get_next_available_publishing_slot();
                $adjusted_slot = i8_adjust_timestamp_to_working_hours($slot);
                
                as_schedule_single_action($adjusted_slot, 'i8_action_publish_specific_post', array('post_id' => $post_id), 'i8_post_publisher');
                i8_action_log(sprintf('پست %d با موفقیت به صف جدید منتقل و زمان‌بندی شد: %s', $post_id, date('Y-m-d H:i:s', $adjusted_slot)));
            }
        }
    }
}

