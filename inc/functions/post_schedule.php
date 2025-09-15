<?php

// Recive Ajax request and manage it
if (isset($_POST['action']) && !empty($_POST['action'])) {
    if ($_POST['action'] == 'delete_item') {

        $item_id = intval($_POST['id']);

        $response = i8_delete_item_at_scheulde_list($item_id);
        echo json_encode($response);
        wp_die();
    }
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
            // //error_log('npost is draf and publishe it');

            date_default_timezone_set('Asia/Tehran');

            $random_interval = rand(400, 900);
            $publish_time = time() + $random_interval;

            // Prepare data for creating a WordPress post
            $post_data = array(
                'ID' => $post_id,
                'post_status' => 'future',
                'post_date' => date('Y-m-d H:i:s', $publish_time), // استفاده از زمان تصادفی برای post_date
                'post_date_gmt' => gmdate('Y-m-d H:i:s', $publish_time), // استفاده از زمان تصادفی برای post_date_gmt
            );
            wp_update_post($post_data);

            // delete record where id=$id at $table_post_schedule
            $action_status = $wpdb->delete($table_post_schedule, array('id' => $id));
            if ($action_status) {
                // //error_log('i8: deleted record with id=' . $id . 'from table ' . $table_post_schedule);
            } else {
                // //error_log('i8: failed to delete record with id=' . $id . 'from table ' . $table_post_schedule);
            }
        } else {
            // //error_log('not fund or not aa draft post and delete record');

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

    // اگر ساعت پایان کمتر از شروع بود، یعنی بازه تا روز بعد ادامه دارد (overnight hours)
    if ($end_seconds <= $start_seconds) {
        // For overnight hours: calculate total working time across midnight
        $interval = (24 * 3600 - $start_seconds) + $end_seconds;
    } else {
        // Normal hours: simple subtraction
        $interval = $end_seconds - $start_seconds;
    }
    if ($interval <= 0) {
        $interval = 1; // جلوگیری از تقسیم بر صفر یا زمان منفی
    }

    $post_per_hours = ($post_publisher_daily_post_count / ($interval / 3600));
    $post_publishe_interval_time = (ceil(60 / $post_per_hours))*60;

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
    // date_default_timezone_set(timezoneId: 'Asia/Tehran');
    error_log('i8_action_publish_post_at_scheduling_table RUNNING- ' . ' current time: ' . date('Y-m-d H:i:s') .' start_cron_time: ' . get_option('start_cron_time') . ' end_cron_time: ' . get_option('end_cron_time'));

    // //error_log('publish_post_at_scheduling_table RUNNING');
    date_default_timezone_set('Asia/Tehran');
    $start_time_str = get_option('start_cron_time');
    $end_time_str = get_option('end_cron_time');
    $now = time();
    $today = date('Y-m-d');
    $start_time = strtotime($today . ' ' . $start_time_str);
    $end_time_candidate = strtotime($today . ' ' . $end_time_str);

    // اگر ساعت پایان کمتر از ساعت شروع بود، یعنی بازه تا روز بعد ادامه دارد
    if ($end_time_candidate <= $start_time) {
        $end_time = $end_time_candidate + 86400; // اضافه کردن یک روز به ساعت پایان
    } else {
        $end_time = $end_time_candidate;
    }

    error_log('i8_action_publish_post_at_scheduling_table DEBUG - start_time: ' . date('Y-m-d H:i:s', $start_time) . ' end_time: ' . date('Y-m-d H:i:s', $end_time) . ' now: ' . date('Y-m-d H:i:s', $now));
    
    // بررسی اینکه آیا زمان فعلی در محدوده زمان کاری است
    // اگر ساعت پایان کمتر از ساعت شروع باشد (مثلاً 22:00 تا 06:00)، باید شرایط خاصی را بررسی کنیم
    if ($end_time_candidate <= $start_time) {
        // For overnight hours, check current time of day
        $current_time_of_day = $now % 86400; // Get time of day in seconds since midnight
        $start_time_of_day = $start_time % 86400;
        $end_time_of_day = $end_time_candidate % 86400;
        
        // در این حالت، یا زمان فعلی بعد از ساعت شروع است یا قبل از ساعت پایان روز بعد
        $in_work_time = ($current_time_of_day >= $start_time_of_day || $current_time_of_day <= $end_time_of_day);
    } else {
        // در حالت عادی، زمان فعلی باید بین ساعت شروع و پایان باشد
        $in_work_time = ($now >= $start_time && $now <= $end_time);
    }
    error_log('i8_action_publish_post_at_scheduling_table DEBUG - in_work_time: ' . ($in_work_time ? 'true' : 'false'));
    // تنظیم محدوده زمانی
    if (!$in_work_time) {
        error_log('i8_action_publish_post_at_scheduling_table - Not in work time, returning.');
        return; // Stop execution if not in work time
    }

    if ($in_work_time) {
        global $wpdb;
        $table_post_schedule = $wpdb->prefix . 'pc_post_schedule';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_post_schedule'") == $table_post_schedule) {
            $high_priority_posts = $wpdb->get_results("SELECT * FROM $table_post_schedule WHERE publish_priority = 'high' ORDER BY id ASC LIMIT 1");
            $medium_priority_posts = $wpdb->get_results("SELECT * FROM $table_post_schedule WHERE publish_priority = 'medium' ORDER BY id ASC LIMIT 1");
            $low_priority_posts = $wpdb->get_results("SELECT * FROM $table_post_schedule WHERE publish_priority = 'low' ORDER BY id ASC LIMIT 1");
            if ($high_priority_posts) {
                i8_change_post_status($high_priority_posts);
            } elseif ($medium_priority_posts) {
                i8_change_post_status($medium_priority_posts);
            } elseif ($low_priority_posts) {
                i8_change_post_status($low_priority_posts);
            }
        }
    }
    else {
        error_log('i8 Out Of Work Time - NOT RUNNING: ' . ' current time: ' . date('Y-m-d H:i:s') .' start_cron_time: ' . get_option('start_cron_time') . ' end_cron_time: ' . get_option('end_cron_time'));
    }
}

