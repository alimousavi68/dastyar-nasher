<?php

// تعریف زمانبدی های اختصاصی
add_filter('cron_schedules', 'i8_register_daily_cron_schedule');
function i8_register_daily_cron_schedule($schedules)
{
    $schedules['i8_Scrap_Timing'] = array(
        'interval' => 60,
        'display' => __('این کرون هر چند دقیقه فیدهای منابع خبری را واکشی میکند')
    );
    return $schedules;
}

// Declare Schedules 
add_action('admin_init', 'custom_rss_parser_schedule_event');
function custom_rss_parser_schedule_event()
{

    // Schedule for Fetch All Feeds at Resourses Feeds
    if (!wp_next_scheduled('custom_rss_parser_event')) {
        wp_schedule_event(time(), 'i8_Scrap_Timing', 'custom_rss_parser_event');
    }

    // Schedule for Remove All Feed on 24h
    if (!wp_next_scheduled('remove_all_feed_on_feeds_table')) {
        wp_schedule_event(time(), 'daily', 'remove_all_feed_on_feeds_table');
    }

    if (!wp_next_scheduled('set_daily_post_count_for_schedule_task')) {
        wp_schedule_event(time(), 'daily', 'set_daily_post_count_for_schedule_task');
    }

}


add_action('set_daily_post_count_for_schedule_task', 'set_daily_post_count_for_schedule_task');
function set_daily_post_count_for_schedule_task()
{
    //error_log('im here: set_daily_post_count_for_schedule_task');
    $news_interval_start = get_option('news_interval_start') ? get_option('news_interval_start') : '20';
    $news_interval_end = get_option('news_interval_end') ? get_option('news_interval_end') : '30';

    update_option('daily_post_count_for_schedule', rand($news_interval_start, $news_interval_end));
}

// Hook to handle the scheduled event
add_action('custom_rss_parser_event', 'custom_rss_parser_run');
// Function to parse and store RSS feed data
function custom_rss_parser_run()
{
    $feeds_list = get_resources_details();
    // error_log(print_r($feeds_list, true));

    if ($feeds_list):

        foreach ($feeds_list as $feed):
            // error_log($feed->resource_title);

            // fetch data form feed item to variable 
            $rss_feed_url = $feed->source_feed_link;
            $source_root_link = $feed->source_root_link;
            $resource_id = $feed->resource_id;
            $resource_name = $feed->resource_title;
            $need_to_merge_guid_link = $feed->need_to_merge_guid_link;

            // error_log($rss_feed_url);
            // Fetch the RSS feed
            $rss_feed = fetch_rss_feed($rss_feed_url);
            // error_log($rss_feed);

            // exit, if rss feed not found
            if ($rss_feed) {
                // error_log('+ this feed is available');

                // Parse and store RSS feed data
                foreach ($rss_feed->channel->item as $item) {
                    $title = $item->title;

                    $pub_date = date('Y-m-d H:i:s', strtotime($item->pubDate));

                    if (isset($item->guid)) {
                        if ($need_to_merge_guid_link == 1) {
                            $guid = $source_root_link . $item->guid . '';
                        } else {
                            $guid = $item->guid . '';
                        }
                    } elseif (isset($item->link)) {
                        $guid = $item->link . '';
                    }

                    // Check if the item already exists in the database
                    if (!custom_rss_parser_item_exists($guid)) {
                        // Insert the new item into the custom table
                        custom_rss_parser_insert_item($title, $pub_date, $guid, $resource_id, $resource_name);
                    }
                }
            }
        endforeach;
    endif;

    // // ست کردن زمان اجرای بعدی رویداد
    $i8_plan_cron_interval = (get_option('i8_plan_cron_interval')) ? get_option('i8_plan_cron_interval') : '-';
    $next_run_time = time() + intval($i8_plan_cron_interval);

    update_option('i8_next_scrap_all_resource_feed_time', $next_run_time); // فقط برای ثبت و نمایش به کاربر

    // لغو رویداد قبلی اگر وجود داشته باشد
    $timestamp = wp_next_scheduled('custom_rss_parser_event');
    if ($timestamp) {
        //error_log('im hereee');
        wp_unschedule_event($timestamp, 'custom_rss_parser_event');
    }
    wp_schedule_event($next_run_time, 'i8_Scrap_Timing', 'custom_rss_parser_event');

}