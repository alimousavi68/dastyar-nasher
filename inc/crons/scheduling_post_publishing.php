<?php

// Add New Schedule Timing
add_filter('cron_schedules', 'set_custom_daynamic_schedule_cron');
function set_custom_daynamic_schedule_cron($schedules)
{
    // $schedules['i8_publish_post_at_schedules'] = array(
    //     'interval' => '60',
    //     'display' => __('کرون جاب انتشار پست داینامیک')
    // );
    // return $schedules;
}

// Add new event 
add_action('init', 'schedule_dynamic_cron_job');
function schedule_dynamic_cron_job()
{
    // if (!wp_next_scheduled('i8_publish_post_at_schedules_event')) {
    //     //error_log(date('Y m d - h:i:s'));

    //     wp_schedule_event(
    //         time(),
    //         'i8_publish_post_at_schedules',
    //         'i8_publish_post_at_schedules_event'
    //     );
    // }
}

// Add Evnet Function and Action 
add_action('i8_publish_post_at_schedules_event', 'i8_publish_post_at_schedules_event');
function i8_publish_post_at_schedules_event()
{
    // publish_post_at_scheduling_table();
    // //Calculate Post Publish Interval
    // calulate_execute_cron_job_interval();
}


function calulate_execute_cron_job_interval()
{
    // $post_publishing_schedule_interval = Calculate_post_publishing_schedule();
    // //error_log('post_publishing_schedule_interval: ' . $post_publishing_schedule_interval);

    // $random_addition = rand(1, 4);
    // //error_log('random addition: ' . $random_addition);
    // $total_interval = ($post_publishing_schedule_interval) + ($random_addition * 60);
    // //error_log('total_interval: ' . $total_interval);

    // $next_run_time = time() + $total_interval;
    // //error_log('current time: ' . date('Y-m-d H:i:s', time()));
    // //error_log('newx run time: ' . date('Y-m-d H:i:s', $next_run_time));

    // // لغو رویداد قبلی اگر وجود داشته باشد
    // $timestamp = wp_next_scheduled('i8_publish_post_at_schedules_event');
    // if ($timestamp) {
    //     wp_unschedule_event($timestamp, 'i8_publish_post_at_schedules_event');
    // }

    // wp_schedule_event($next_run_time, 'i8_publish_post_at_schedules', 'i8_publish_post_at_schedules_event');

    // update_option('i8_next_run_time', $next_run_time); //فقط برای نمایش
}


add_action( 'calculate_post_publishing_schedule', 'calculate_post_publishing_schedule');
function calculate_post_publishing_schedule()
{
    //Calculate Post Publish Interval
    // $start_time = get_option('start_cron_time') ? get_option('start_cron_time') : '08:30';
    // $start_time_res = strtotime($start_time);
    // $end_time = get_option('end_cron_time') ? get_option('end_cron_time') : '22:02';
    // $end_time_res = strtotime($end_time);

    // $work_time_count = intval($end_time) - intval($start_time);
    // //error_log('work time: ' . $work_time_count);
    // $sum_post_count = get_option('daily_post_count_for_schedule');
    // //error_log('sum post count: : ' . $sum_post_count);


    // if ($work_time_count == 0) {
    //     $post_count_publishing_per_hours = 10;
    // } else {
    //     $post_count_publishing_per_hours = round($sum_post_count / $work_time_count);
    //     //error_log('post_count_publishing_per_hours' . $post_count_publishing_per_hours);
    // }

    // $post_interval_publishing = (60 / round($post_count_publishing_per_hours)) * 60;

    // //error_log('post_interval_publishing: ' . $post_interval_publishing);

    // return ($post_interval_publishing);
}


