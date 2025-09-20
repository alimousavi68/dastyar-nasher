<?php

// add action for active plugin and update option
add_action('i8_action_set_cron_job_publishe_posts', 'i8_set_cron_job_publishe_posts');

// مکانیزم خودکار بررسی وجود scheduled action
function i8_ensure_scheduled_action_exists() {
    // بررسی اینکه آیا Action Scheduler فعال است
    if (!function_exists('as_next_scheduled_action')) {
        return;
    }
    
    // بررسی وجود scheduled action
    $next_scheduled = as_next_scheduled_action('i8_action_publish_post_at_scheduling_table');
    
    if (!$next_scheduled) {
        error_log('i8: Scheduled action not found, recreating automatically...');
        // بازیابی خودکار
        do_action('i8_action_set_cron_job_publishe_posts');
        error_log('i8: Scheduled action recreated successfully');
    }
}

// اجرای بررسی در admin_init
add_action('admin_init', 'i8_ensure_scheduled_action_exists');

// ایجاد Fallback Mechanism با WordPress Cron
function i8_setup_fallback_cron() {
    if (!wp_next_scheduled('i8_fallback_check_scheduled_action')) {
        wp_schedule_event(time(), 'hourly', 'i8_fallback_check_scheduled_action');
        error_log('i8: Fallback cron scheduled');
    }
}

function i8_fallback_check_scheduled_action() {
    if (function_exists('as_next_scheduled_action')) {
        $next_scheduled = as_next_scheduled_action('i8_action_publish_post_at_scheduling_table');
        if (!$next_scheduled) {
            error_log('i8: Fallback cron detected missing scheduled action, recreating...');
            do_action('i8_action_set_cron_job_publishe_posts');
        }
    }
}

// Hook برای fallback cron
add_action('i8_fallback_check_scheduled_action', 'i8_fallback_check_scheduled_action');

// راه‌اندازی fallback cron هنگام فعال‌سازی پلاگین
add_action('init', 'i8_setup_fallback_cron');

// AJAX handler برای بازیابی خودکار scheduled action
function i8_ajax_recreate_scheduled_action() {
    // بررسی nonce برای امنیت
    if (!wp_verify_nonce($_POST['nonce'], 'i8_recreate_action')) {
        wp_die(json_encode(array('success' => false, 'message' => 'نامعتبر است')));
    }
    
    // بررسی دسترسی کاربر
    if (!current_user_can('manage_options')) {
        wp_die(json_encode(array('success' => false, 'message' => 'شما دسترسی لازم را ندارید')));
    }
    
    try {
        // اجرای action برای بازیابی scheduled action
        do_action('i8_action_set_cron_job_publishe_posts');
        
        // بررسی موفقیت
        if (function_exists('as_next_scheduled_action')) {
            $next_scheduled = as_next_scheduled_action('i8_action_publish_post_at_scheduling_table');
            if ($next_scheduled) {
                wp_die(json_encode(array('success' => true, 'message' => 'scheduled action با موفقیت بازیابی شد')));
            } else {
                wp_die(json_encode(array('success' => false, 'message' => 'خطا در بازیابی scheduled action')));
            }
        } else {
            wp_die(json_encode(array('success' => false, 'message' => 'Action Scheduler فعال نیست')));
        }
    } catch (Exception $e) {
        wp_die(json_encode(array('success' => false, 'message' => 'خطا: ' . $e->getMessage())));
    }
}

// Hook کردن AJAX handler
add_action('wp_ajax_i8_recreate_scheduled_action', 'i8_ajax_recreate_scheduled_action');
add_action('wp_ajax_nopriv_i8_recreate_scheduled_action', 'i8_ajax_recreate_scheduled_action');
function i8_set_cron_job_publishe_posts(){

    //فواصل بین کرون های انتشار پست
    $post_publish_time = calculate_post_publish_time() ;
    // پاک کردن زمانبدی قدیمی
    as_unschedule_action('i8_action_publish_post_at_scheduling_table', array(), 'i8_cronjobs');

    // تعریف زمانبدی جدید بر اساس تنظیمات جدید ساعت کاری و حداکثیر پست روزانه
    if (function_exists('as_next_scheduled_action')) {
        if (! as_next_scheduled_action('i8_action_publish_post_at_scheduling_table', array(), 'i8_cronjobs')) {
            as_schedule_recurring_action(time(), $post_publish_time, 'i8_action_publish_post_at_scheduling_table', array(), 'i8_cronjobs'); // 100 ثانیه
        }
    }
    // error_log('i8: cron job set');
}




