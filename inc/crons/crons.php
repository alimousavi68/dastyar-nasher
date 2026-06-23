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

    // Schedule for Auto Cleaning Reports Weekly
    if (!wp_next_scheduled('i8_weekly_cleanup_reports_event')) {
        wp_schedule_event(time(), 'weekly', 'i8_weekly_cleanup_reports_event');
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

// Hook to handle weekly reports cleanup
add_action('i8_weekly_cleanup_reports_event', 'i8_weekly_cleanup_reports');
function i8_weekly_cleanup_reports() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pc_reports';
    $wpdb->query("DELETE FROM $table_name");
    error_log('i8: Auto cleaned up system reports (weekly).');
}

// Hook to handle the scheduled event
add_action('custom_rss_parser_event', 'custom_rss_parser_run');
// Function to parse and store RSS feed data via Async Queue
function custom_rss_parser_run()
{
    $feeds_list = get_resources_details();

    if ($feeds_list):
        foreach ($feeds_list as $feed):
            $feed_id = intval($feed->resource_id);
            if (function_exists('as_enqueue_async_action')) {
                // برای جلوگیری از ثبت تکراری یک منبع خاص، ابتدا چک می‌کنیم که آیا این شناسه فید در صف انتظار وجود دارد یا خیر
                if (function_exists('as_has_scheduled_action') && as_has_scheduled_action('i8_action_crawl_feed', array('feed_id' => $feed_id), 'i8_scraper')) {
                    error_log('i8: Feed ID ' . $feed_id . ' is already enqueued. Skipping.');
                    continue;
                }
                as_enqueue_async_action(
                    'i8_action_crawl_feed', 
                    array('feed_id' => $feed_id), 
                    'i8_scraper', 
                    false // مقدار را فالس می‌گذاریم تا همه فیدها ثبت شوند و مقایسه شناسه فید را خودمان در خط بالا انجام می‌دهیم
                );
                error_log('i8: Enqueued async crawl job for feed ID: ' . $feed_id);
            } else {
                // Fallback به روش همزمان در صورت غیرفعال بودن Action Scheduler
                i8_crawl_single_feed($feed_id);
            }
        endforeach;
    endif;

    // ست کردن زمان اجرای بعدی رویداد
    $i8_plan_cron_interval = (get_option('i8_plan_cron_interval')) ? get_option('i8_plan_cron_interval') : '600';
    $next_run_time = time() + intval($i8_plan_cron_interval);

    update_option('i8_next_scrap_all_resource_feed_time', $next_run_time); // فقط برای ثبت و نمایش به کاربر

    // لغو رویداد قبلی اگر وجود داشته باشد
    $timestamp = wp_next_scheduled('custom_rss_parser_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'custom_rss_parser_event');
    }
    wp_schedule_event($next_run_time, 'i8_Scrap_Timing', 'custom_rss_parser_event');
}

// Hook to run async single feed crawl
add_action('i8_action_crawl_feed', 'i8_crawl_single_feed', 10, 1);

/**
 * پردازش و خزش غیرهمزمان یک فید خبری مشخص (پوشش تمام استثناها)
 */
function i8_crawl_single_feed($feed_id) {
    global $wpdb;
    $feed_id = intval($feed_id);
    
    // گرفتن اطلاعات فید
    $table_name = $wpdb->prefix . 'custom_resource_details';
    $feed = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE resource_id = %d LIMIT 1", $feed_id));
    
    if (!$feed) {
        error_log('i8: Feed ID ' . $feed_id . ' not found in custom_resource_details.');
        return;
    }
    
    $rss_feed_url = $feed->source_feed_link;
    $source_root_link = $feed->source_root_link;
    $resource_id = $feed->resource_id;
    $resource_name = $feed->resource_title;
    $need_to_merge_guid_link = $feed->need_to_merge_guid_link;
    
    error_log('i8: Starting async crawl for resource: ' . $resource_name . ' (URL: ' . $rss_feed_url . ')');
    
    if (function_exists('insert_rss_report')) {
        insert_rss_report('خزیدن غیرهمزمان فید', $rss_feed_url, $resource_id, '1', 'شروع عملیات واکشی فید در پس‌زمینه');
    }

    $rss_feed = fetch_rss_feed($rss_feed_url);
    
    // بررسی صحت پارس فید و وجود تگ‌های استاندارد XML
    if ($rss_feed && isset($rss_feed->channel->item)) {
        $new_items_count = 0;
        foreach ($rss_feed->channel->item as $item) {
            $title = isset($item->title) ? (string)$item->title : '';
            
            // پیشگیری از خطای زمان‌های نامعتبر
            $raw_date = isset($item->pubDate) ? strtotime((string)$item->pubDate) : time();
            $pub_date = gmdate('Y-m-d H:i:s', $raw_date ? $raw_date : time());

            if (isset($item->guid)) {
                if ($need_to_merge_guid_link == 1) {
                    $guid = $source_root_link . (string)$item->guid;
                } else {
                    $guid = (string)$item->guid;
                }
            } elseif (isset($item->link)) {
                $guid = (string)$item->link;
            } else {
                continue; // اگر شناسه یکتایی نبود، عبور کن
            }

            if (function_exists('custom_rss_parser_item_exists') && !custom_rss_parser_item_exists($guid)) {
                if (function_exists('custom_rss_parser_insert_item')) {
                    custom_rss_parser_insert_item($title, $pub_date, $guid, $resource_id, $resource_name);
                    $new_items_count++;
                }
            }
        }
        error_log('i8: Finished async crawl for: ' . $resource_name . '. Added ' . $new_items_count . ' new posts.');
        if (function_exists('insert_rss_report')) {
            insert_rss_report('اتمام خزیدن فید', $rss_feed_url, $resource_id, '1', sprintf('عملیات موفقیت‌آمیز بود. تعداد %d خبر جدید یافت شد.', $new_items_count));
        }
    } else {
        global $i8_last_feed_error;
        $err_msg = isset($i8_last_feed_error) && !empty($i8_last_feed_error) ? $i8_last_feed_error : 'امکان واکشی یا اتصال به سرور فید وجود نداشت.';
        error_log('i8: Failed to fetch feed for: ' . $resource_name . ' (' . $err_msg . ')');
        if (function_exists('insert_rss_report')) {
            insert_rss_report(
                'خطا در خزیدن فید', 
                $rss_feed_url, 
                $resource_id, 
                '0', 
                $rss_feed ? 'فرمت ساختار فید نامعتبر است (آیتمی یافت نشد).' : $err_msg
            );
        }
    }
}