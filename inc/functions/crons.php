<?php

// add action for active plugin and update option
add_action('i8_action_set_cron_job_publishe_posts', 'i8_set_cron_job_publishe_posts');
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




