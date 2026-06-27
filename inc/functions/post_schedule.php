<?php

// Action Hooks
add_action('i8_action_publish_specific_post', 'i8_publish_specific_post_callback', 10, 1);
add_action('i8_action_process_queue', 'i8_process_queue');
add_action('i8_action_rebuild_queue', 'i8_rebuild_queue');

// AJAX Handlers
add_action('wp_ajax_i8_delete_from_queue', 'i8_ajax_delete_from_queue');
add_action('wp_ajax_i8_update_priority', 'i8_ajax_update_priority');
add_action('wp_ajax_i8_reorder_queue', 'i8_ajax_reorder_queue');
add_action('wp_ajax_i8_get_queue_status', 'i8_ajax_get_queue_status');
add_action('wp_ajax_i8_publish_now', 'i8_ajax_publish_now');

function i8_ajax_publish_now() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('عدم دسترسی');
    }
    check_ajax_referer('i8_queue_action', 'nonce');
    
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    if (!$item_id) {
        wp_send_json_error('شناسه نامعتبر است');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'pc_post_schedule';
    
    $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $item_id));
    if (!$item) {
        wp_send_json_error('آیتم یافت نشد');
    }
    
    $post_id = $item->post_id;
    
    // Publish post
    wp_update_post(array(
        'ID' => $post_id,
        'post_status' => 'publish'
    ));
    
    // Update queue status
    $wpdb->update(
        $table,
        array(
            'status' => 'published',
            'scheduled_for' => gmdate('Y-m-d H:i:s'), // Mark it published now
            'as_action_id' => null
        ),
        array('id' => $item_id)
    );
    
    // Cancel any scheduled Action Scheduler task
    if ($item->as_action_id && function_exists('as_unschedule_action')) {
        as_unschedule_action('i8_action_publish_specific_post', array('post_id' => $post_id), 'i8_post_publisher');
    }
    
    wp_send_json_success(array('message' => 'خبر با موفقیت در سایت منتشر شد.'));
}

function i8_action_log($message) {
    if (get_option('i8_enable_action_logs', true)) {
        error_log('i8_Action: ' . $message);
    }
}

/**
 * Add post to queue
 */
function add_post_to_post_schedule_table($post_id, $post_priority) {
    global $wpdb;
    $table = $wpdb->prefix . 'pc_post_schedule';
    
    // گرفتن بزرگترین sort_order موجود برای قرار دادن در انتهای صف
    $max_order = $wpdb->get_var("SELECT MAX(sort_order) FROM $table WHERE status IN ('queued', 'scheduled')");
    $new_order = intval($max_order) + 1;

    $wpdb->insert(
        $table,
        array(
            'post_id' => $post_id,
            'publish_priority' => $post_priority,
            'status' => 'queued',
            'sort_order' => $new_order,
            'created_at' => current_time('mysql')
        )
    );

    // زمان‌بندی پردازش صف به صورت غیرهمزمان
    if (function_exists('as_enqueue_async_action')) {
        as_enqueue_async_action('i8_action_process_queue', array(), 'i8_post_publisher');
    }
}

/**
 * Process the queue: read 'queued' posts and schedule them
 */
function i8_process_queue() {
    global $wpdb;
    $table = $wpdb->prefix . 'pc_post_schedule';

    // Locking mechanism to prevent race conditions
    $lock_key = 'i8_queue_processing_lock';
    if (get_transient($lock_key)) {
        return; // Already processing
    }
    set_transient($lock_key, true, 60); // 60 seconds lock

    try {
        // Clean up stuck publishing actions (e.g. status = 'publishing' for more than 15 minutes)
        $timeout_limit = gmdate('Y-m-d H:i:s', time() - 900);
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET status = 'queued', last_error = 'خطای اتمام زمان انتشار (Timeout). بازگشت خودکار به صف.' WHERE status = 'publishing' AND scheduled_for < %s",
            $timeout_limit
        ));

        $queued_posts = $wpdb->get_results("SELECT * FROM $table WHERE status = 'queued' ORDER BY FIELD(publish_priority, 'high', 'medium', 'low'), sort_order ASC, id ASC");
        
        if (empty($queued_posts)) {
            delete_transient($lock_key);
            return;
        }

        foreach ($queued_posts as $post) {
            if (function_exists('as_schedule_single_action')) {
                $slot = i8_get_next_available_publishing_slot();
                $adjusted_slot = i8_adjust_timestamp_to_working_hours($slot);
                
                $action_id = as_schedule_single_action($adjusted_slot, 'i8_action_publish_specific_post', array('post_id' => $post->post_id), 'i8_post_publisher');
                
                if ($action_id) {
                    $wpdb->update(
                        $table,
                        array(
                            'status' => 'scheduled',
                            'scheduled_for' => gmdate('Y-m-d H:i:s', $adjusted_slot), // store in UTC or local depending on needs
                            'as_action_id' => $action_id
                        ),
                        array('id' => $post->id)
                    );
                } else {
                    i8_action_log(sprintf('Failed to schedule action for post ID %d via Action Scheduler.', $post->post_id));
                }
            }
        }
    } catch (Exception $e) {
        i8_action_log('Error processing queue: ' . $e->getMessage());
    }

    delete_transient($lock_key);
}

/**
 * Rebuild queue schedule (e.g. after reordering or settings change)
 */
function i8_rebuild_queue() {
    global $wpdb;
    $table = $wpdb->prefix . 'pc_post_schedule';

    // Cancel all currently pending actions
    $scheduled_posts = $wpdb->get_results("SELECT * FROM $table WHERE status = 'scheduled' ORDER BY FIELD(publish_priority, 'high', 'medium', 'low'), sort_order ASC, id ASC");
    
    foreach ($scheduled_posts as $post) {
        if ($post->as_action_id && function_exists('as_unschedule_action')) {
            // Cancel specific action by ID is not natively simple in AS unless using generic unschedule
            as_unschedule_action('i8_action_publish_specific_post', array('post_id' => $post->post_id), 'i8_post_publisher');
        }
        $wpdb->update($table, array('status' => 'queued', 'as_action_id' => null, 'scheduled_for' => null), array('id' => $post->id));
    }

    // Now re-process the queue
    i8_process_queue();
}

/**
 * Callback to publish specific post
 */
function i8_publish_specific_post_callback($post_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'pc_post_schedule';
    
    $post_record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE post_id = %d LIMIT 1", $post_id));
    if (!$post_record) {
        return;
    }

    $wpdb->update($table, array('status' => 'publishing'), array('id' => $post_record->id));
    
    $post_status = get_post_status($post_id);
    if ($post_status !== 'draft' && $post_status !== 'pending') {
        $wpdb->update($table, array('status' => 'cancelled', 'last_error' => 'پست در وضعیت پیش‌نویس یا در انتظار بررسی نبود'), array('id' => $post_record->id));
        return;
    }

    // Publish
    $post_data = array(
        'ID' => $post_id,
        'post_status' => 'publish',
        'post_date' => current_time('mysql'),
        'post_date_gmt' => current_time('mysql', 1),
    );
    $updated = wp_update_post($post_data);

    if (is_wp_error($updated)) {
        $attempts = intval($post_record->attempts) + 1;
        $error_msg = $updated->get_error_message();
        
        if ($attempts < 3) {
            $wpdb->update($table, array('status' => 'failed', 'attempts' => $attempts, 'last_error' => $error_msg), array('id' => $post_record->id));
            
            // Retry in 5 minutes
            $retry_time = time() + 300;
            $action_id = as_schedule_single_action($retry_time, 'i8_action_publish_specific_post', array('post_id' => $post_id), 'i8_post_publisher');
            $wpdb->update($table, array('status' => 'scheduled', 'as_action_id' => $action_id, 'scheduled_for' => gmdate('Y-m-d H:i:s', $retry_time)), array('id' => $post_record->id));
        } else {
            $wpdb->update($table, array('status' => 'cancelled', 'attempts' => $attempts, 'last_error' => 'حداکثر تلاش رسید: ' . $error_msg), array('id' => $post_record->id));
        }
    } else {
        $wpdb->update($table, array('status' => 'published'), array('id' => $post_record->id));
        i8_action_log(sprintf('پست با شناسه %d با موفقیت منتشر شد.', $post_id));
    }
}

/**
 * Calculate fixed interval
 */
function calculate_post_publish_time() {
    $post_publisher_start_time = get_option('start_cron_time') ? get_option('start_cron_time') : '08:00';
    $post_publisher_end_time = get_option('end_cron_time') ? get_option('end_cron_time') : '22:00';

    $post_publishe_count = get_option('daily_post_count', 30);
    $post_publishe_count = max(1, intval($post_publishe_count));

    $start_parts = explode(':', $post_publisher_start_time);
    $end_parts = explode(':', $post_publisher_end_time);
    $start_seconds = isset($start_parts[0], $start_parts[1]) ? ($start_parts[0] * 3600 + $start_parts[1] * 60) : 0;
    $end_seconds = isset($end_parts[0], $end_parts[1]) ? ($end_parts[0] * 3600 + $end_parts[1] * 60) : 0;

    $interval = ($end_seconds <= $start_seconds) ? (24 * 3600 - $start_seconds + $end_seconds) : ($end_seconds - $start_seconds);
    if ($interval <= 0) $interval = 1;
    
    $post_publishe_interval_time = round($interval / $post_publishe_count);
    return max(60, $post_publishe_interval_time);
}

/**
 * Get next available publishing slot safely
 */
function i8_get_next_available_publishing_slot() {
    static $last_scheduled_timestamp = null;
    $interval = calculate_post_publish_time();
    
    $base_time = time();
    if ($last_scheduled_timestamp !== null) {
        $base_time = $last_scheduled_timestamp;
    } else {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_post_schedule';
        $last_record = $wpdb->get_row("SELECT scheduled_for FROM $table WHERE status = 'scheduled' ORDER BY scheduled_for DESC LIMIT 1");
        
        if ($last_record && $last_record->scheduled_for) {
            // Assume scheduled_for is UTC, convert to local timestamp if needed
            // Actually, we store it via gmdate above, so we parse it as GMT
            $base_time = strtotime($last_record->scheduled_for . ' GMT');
        }
    }
    
    $next_timestamp = max(time(), $base_time + $interval);
    $adjusted_timestamp = i8_adjust_timestamp_to_working_hours($next_timestamp);
    $last_scheduled_timestamp = $adjusted_timestamp;
    return $adjusted_timestamp;
}

/**
 * Adjust timestamp to working hours safely
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

// ----- AJAX Handlers -----

function i8_ajax_delete_from_queue() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'i8_queue_action')) {
        wp_send_json_error(array('message' => 'عدم دسترسی امنیتی.'));
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'عدم دسترسی کافی.'));
    }

    $id = intval($_POST['id']);
    global $wpdb;
    $table = $wpdb->prefix . 'pc_post_schedule';
    
    $post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    if ($post) {
        if ($post->status === 'scheduled' && $post->post_id && function_exists('as_unschedule_action')) {
            as_unschedule_action('i8_action_publish_specific_post', array('post_id' => $post->post_id), 'i8_post_publisher');
        }
        $wpdb->delete($table, array('id' => $id));
        wp_send_json_success(array('message' => 'پست با موفقیت از صف حذف شد.'));
    } else {
        wp_send_json_error(array('message' => 'پست یافت نشد.'));
    }
}

function i8_ajax_update_priority() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'i8_queue_action')) {
        wp_send_json_error(array('message' => 'عدم دسترسی امنیتی.'));
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'عدم دسترسی کافی.'));
    }

    $id = intval($_POST['id']);
    $priority = sanitize_text_field($_POST['priority']);
    
    if (!in_array($priority, array('high', 'medium', 'low'))) {
        wp_send_json_error(array('message' => 'اولویت نامعتبر است.'));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'pc_post_schedule';
    $wpdb->update($table, array('publish_priority' => $priority), array('id' => $id));
    
    // Rebuild queue
    if (function_exists('as_enqueue_async_action')) {
        as_enqueue_async_action('i8_action_rebuild_queue', array(), 'i8_post_publisher');
    }

    wp_send_json_success(array('message' => 'اولویت به‌روزرسانی شد. صف در حال بازمحاسبه است...'));
}

function i8_ajax_reorder_queue() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'i8_queue_action')) {
        wp_send_json_error(array('message' => 'عدم دسترسی امنیتی.'));
    }
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'عدم دسترسی کافی.'));
    }

    $orders = $_POST['orders']; // Array of id => new_sort_order
    if (is_array($orders)) {
        global $wpdb;
        $table = $wpdb->prefix . 'pc_post_schedule';
        foreach ($orders as $id => $order) {
            $wpdb->update($table, array('sort_order' => intval($order)), array('id' => intval($id)));
        }
        
        // Rebuild queue
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('i8_action_rebuild_queue', array(), 'i8_post_publisher');
        }
        
        wp_send_json_success(array('message' => 'ترتیب به‌روزرسانی شد. صف در حال بازمحاسبه است...'));
    }
    wp_send_json_error(array('message' => 'داده‌های نامعتبر.'));
}

function i8_ajax_get_queue_status() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'i8_queue_action')) {
        wp_send_json_error();
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'pc_post_schedule';
    // Return HTML rows for the table
    ob_start();
    include plugin_dir_path(dirname(__FILE__)) . '../admin-pages/partials/queue-rows.php';
    $html = ob_get_clean();
    
    wp_send_json_success(array('html' => $html));
}

// Helper to get priority
function cop_get_post_priority($post_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pc_post_schedule';
    $query = $wpdb->prepare("SELECT publish_priority FROM $table_name WHERE post_id = %d LIMIT 1", $post_id);
    return $wpdb->get_var($query);
}

function cop_update_post_priority($post_id, $new_priority) {
    global $wpdb;
    $pc_post_schedule_table_name = $wpdb->prefix . 'pc_post_schedule';
    $wpdb->update(
        $pc_post_schedule_table_name,
        array('publish_priority' => $new_priority),
        array('post_id' => $post_id)
    );
}
