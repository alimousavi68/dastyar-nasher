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

    // $post_publisher_start_work_time = get_option('news_interval_start') ? get_option('news_interval_start') : '30';
    $post_publisher_daily_post_count = get_option('news_interval_end') ? get_option('news_interval_end') : '30';

    $post_publishe_count = get_option('daily_post_count_for_schedule') ? get_option('daily_post_count_for_schedule') : '10';

    $interval = $post_publisher_end_time - $post_publisher_start_time;
    if ($interval <= 0) {
        $interval = 1; // جلوگیری از تقسیم بر صفر یا زمان منفی
    }

    $post_per_hours = ($post_publisher_daily_post_count / $interval);
    $post_publishe_interval_time = (ceil(60 / $post_per_hours))*60;
    return $post_publishe_interval_time;
}

// Hook to handle the scheduled event
add_action( 'i8_action_publish_post_at_scheduling_table', 'publish_post_at_scheduling_table' );
function publish_post_at_scheduling_table()
{
    // date_default_timezone_set(timezoneId: 'Asia/Tehran');
    error_log('i8_action_publish_post_at_scheduling_table RUNNING- ' . date('Y-m-d H:i:s'));

    // //error_log('publish_post_at_scheduling_table RUNNING');
    // date_default_timezone_set('Asia/Tehran');
    $start_time = strtotime(get_option('start_cron_time'));
    $end_time = strtotime(get_option('end_cron_time'));

    // تنظیم محدوده زمانی
    if (time() >= $start_time && time() <= $end_time) {

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
}

