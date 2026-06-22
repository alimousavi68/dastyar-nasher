<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Hook to create the custom table for monitoring errors
add_action('admin_init', 'i8_create_monitoring_errors_table');

function i8_create_monitoring_errors_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'i8_monitoring_errors';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            resource_id bigint(20) NOT NULL,
            resource_name varchar(255) NOT NULL,
            error_type varchar(100) NOT NULL,
            error_message text NOT NULL,
            stack_trace text NOT NULL,
            first_occurrence datetime NOT NULL,
            last_checked datetime NOT NULL,
            retry_count int(11) DEFAULT 0 NOT NULL,
            status varchar(50) DEFAULT 'pending_retry' NOT NULL,
            consecutive_success_count int(11) DEFAULT 0 NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY resource_id (resource_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}

// Hook daily monitoring cron schedule
add_action('admin_init', 'i8_schedule_daily_monitoring');
function i8_schedule_daily_monitoring()
{
    if (!wp_next_scheduled('i8_daily_monitoring_event')) {
        wp_schedule_event(time(), 'daily', 'i8_daily_monitoring_event');
    }
}

add_action('i8_daily_monitoring_event', 'i8_run_daily_monitoring');
add_action('i8_action_check_single_resource', 'i8_process_resource_check', 10, 1);

/**
 * Main function to run daily monitoring for all resources
 */
function i8_run_daily_monitoring()
{
    $resources = get_resources_details();
    if (!$resources) {
        return;
    }

    foreach ($resources as $resource) {
        $resource_id = intval($resource->resource_id);
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(
                'i8_action_check_single_resource', 
                array('resource_id' => $resource_id), 
                'i8_monitoring', 
                true
            );
        } else {
            i8_process_resource_check($resource_id);
        }
    }
}


/**
 * Process single resource check (used in cron or manual trigger)
 */
function i8_process_resource_check($resource_id)
{
    global $wpdb;
    $table_errors = $wpdb->prefix . 'i8_monitoring_errors';
    $resource_id = intval($resource_id);

    $test_result = i8_test_resource($resource_id);
    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_errors WHERE resource_id = %d", $resource_id));

    $now = current_time('mysql');


    if ($test_result === true) {
        // Success case
        if ($existing) {
            if ($existing->status === 'active_error') {
                $new_success_count = intval($existing->consecutive_success_count) + 1;
                if ($new_success_count >= 2) {
                    // Remove error log
                    $wpdb->delete($table_errors, array('resource_id' => $resource_id));
                    insert_rss_report(
                        'پایان خطای منبع مانیتورینگ',
                        $existing->resource_name,
                        $resource_id,
                        1,
                        'منبع با موفقیت در دو تست متوالی بررسی شد و از لیست خطاها خارج گردید.'
                    );
                } else {
                    $wpdb->update(
                        $table_errors,
                        array(
                            'consecutive_success_count' => $new_success_count,
                            'last_checked' => $now
                        ),
                        array('resource_id' => $resource_id)
                    );
                }
            } else {
                // If it was in pending_retry status, clear it since it was a transient error
                $wpdb->delete($table_errors, array('resource_id' => $resource_id));
            }
        }
    } else {
        // Failure case
        $error_type = $test_result['error_type'];
        $error_message = $test_result['error_message'];
        $stack_trace = $test_result['stack_trace'];

        // Get resource name
        $resource_name = $wpdb->get_var($wpdb->prepare(
            "SELECT resource_title FROM {$wpdb->prefix}custom_resource_details WHERE resource_id = %d",
            $resource_id
        )) ?: 'نامشخص';

        if (!$existing) {
            // First time failing, initiate pending_retry and schedule first retry in 10 minutes
            $wpdb->insert(
                $table_errors,
                array(
                    'resource_id' => $resource_id,
                    'resource_name' => $resource_name,
                    'error_type' => $error_type,
                    'error_message' => $error_message,
                    'stack_trace' => $stack_trace,
                    'first_occurrence' => $now,
                    'last_checked' => $now,
                    'retry_count' => 1,
                    'status' => 'pending_retry',
                    'consecutive_success_count' => 0
                )
            );

            // Schedule first retry in 10 minutes using Action Scheduler
            if (function_exists('as_enqueue_async_action')) {
                as_schedule_single_action(
                    time() + 600,
                    'i8_retry_resource_monitoring_action',
                    array('resource_id' => $resource_id),
                    'i8_monitoring'
                );
            }
        } else {
            if ($existing->status === 'active_error') {
                // Keep it active, reset success count and update details
                $wpdb->update(
                    $table_errors,
                    array(
                        'error_type' => $error_type,
                        'error_message' => $error_message,
                        'stack_trace' => $stack_trace,
                        'last_checked' => $now,
                        'consecutive_success_count' => 0
                    ),
                    array('resource_id' => $resource_id)
                );
            }
            // If already in pending_retry status, we do not touch it here as the pending retry task chain is in progress.
        }
    }
}

// Action Scheduler action hook for retries
add_action('i8_retry_resource_monitoring_action', 'i8_retry_resource_monitoring', 10, 1);

/**
 * Handle retry attempts
 */
function i8_retry_resource_monitoring($resource_id)
{
    global $wpdb;
    $table_errors = $wpdb->prefix . 'i8_monitoring_errors';
    $resource_id = intval($resource_id);

    $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_errors WHERE resource_id = %d", $resource_id));
    if (!$existing || $existing->status !== 'pending_retry') {
        return;
    }

    $test_result = i8_test_resource($resource_id);
    $now = current_time('mysql');


    if ($test_result === true) {
        // Successful now -> transient issue resolved!
        $wpdb->delete($table_errors, array('resource_id' => $resource_id));
        insert_rss_report(
            'تست مجدد موفقیت‌آمیز',
            $existing->resource_name,
            $resource_id,
            1,
            'خطای مقطعی برطرف شد و بررسی مجدد موفقیت‌آمیز بود.'
        );
    } else {
        $error_type = $test_result['error_type'];
        $error_message = $test_result['error_message'];
        $stack_trace = $test_result['stack_trace'];
        $current_retry = intval($existing->retry_count);

        if ($current_retry < 3) {
            // Schedule next attempt
            $next_retry = $current_retry + 1;
            $wpdb->update(
                $table_errors,
                array(
                    'retry_count' => $next_retry,
                    'error_type' => $error_type,
                    'error_message' => $error_message,
                    'stack_trace' => $stack_trace,
                    'last_checked' => $now
                ),
                array('resource_id' => $resource_id)
            );

            if (function_exists('as_enqueue_async_action')) {
                as_schedule_single_action(
                    time() + 600,
                    'i8_retry_resource_monitoring_action',
                    array('resource_id' => $resource_id),
                    'i8_monitoring'
                );
            }
        } else {
            // Failed all 3 retries -> mark as active_error
            $wpdb->update(
                $table_errors,
                array(
                    'status' => 'active_error',
                    'error_type' => $error_type,
                    'error_message' => $error_message,
                    'stack_trace' => $stack_trace,
                    'last_checked' => $now
                ),
                array('resource_id' => $resource_id)
            );

            // Log this final error in system reports
            insert_rss_report(
                'ثبت خطای دائمی منبع',
                $existing->resource_name,
                $resource_id,
                0,
                sprintf('خطای دائمی [%s] پس از ۳ بار تلاش مجدد ثبت شد: %s', $error_type, $error_message)
            );
        }
    }
}

/**
 * Validate a resource
 * Returns true if valid, or array with keys [error_type, error_message, stack_trace]
 */
function i8_test_resource($resource_id)
{
    global $wpdb;
    $resource_id = intval($resource_id);

    $table_details = $wpdb->prefix . 'custom_resource_details';
    $resource = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_details WHERE resource_id = %d LIMIT 1", $resource_id));

    if (!$resource) {
        return array(
            'error_type' => 'db_error',
            'error_message' => 'منبع در پایگاه داده یافت نشد.',
            'stack_trace' => 'Database Query failed or returned empty for Resource ID ' . $resource_id
        );
    }

    $feed_url = $resource->source_feed_link;
    $source_root_link = $resource->source_root_link;
    $title_selector = $resource->title_selector;
    $body_selector = $resource->body_selector;

    // Start Stack Trace
    $trace = "1. Fetching Feed URL: $feed_url\n";

    // 1. Fetch feed
    $rss_feed = fetch_rss_feed($feed_url);
    if (!$rss_feed) {
        return array(
            'error_type' => 'network_feed_error',
            'error_message' => 'امکان واکشی یا پارس آدرس فید RSS وجود نداشت.',
            'stack_trace' => $trace . "fetch_rss_feed() returned false for URL: " . $feed_url
        );
    }

    // 2. Read first item
    if (!isset($rss_feed->channel->item)) {
        return array(
            'error_type' => 'xml_structure_error',
            'error_message' => 'ساختار فید XML نامعتبر است یا بدون آیتم می‌باشد.',
            'stack_trace' => $trace . "2. Channel items array not found in RSS structure."
        );
    }

    $first_item = null;
    foreach ($rss_feed->channel->item as $item) {
        $first_item = $item;
        break;
    }

    if (!$first_item) {
        return array(
            'error_type' => 'empty_feed_error',
            'error_message' => 'فید خبری فاقد هرگونه نوشته/خبر برای تست است.',
            'stack_trace' => $trace . "2. Feed XML is empty."
        );
    }

    // Determine GUID/link
    $guid = '';
    if (isset($first_item->guid)) {
        if ($resource->need_to_merge_guid_link == 1) {
            $guid = $source_root_link . (string)$first_item->guid;
        } else {
            $guid = (string)$first_item->guid;
        }
    } elseif (isset($first_item->link)) {
        $guid = (string)$first_item->link;
    }

    if (empty($guid)) {
        return array(
            'error_type' => 'guid_extraction_error',
            'error_message' => 'امکان استخراج لینک خبر از فید میسر نبود.',
            'stack_trace' => $trace . "2. Item guid/link could not be extracted."
        );
    }

    $trace .= "2. Extracted Guid Link: $guid\n";
    $encoded_url = encode_persian_chracter_allowed_url($guid);
    $trace .= "3. Encoded Link: $encoded_url\n";

    // 3. Check HTTP Status
    $status_result = check_post_link_status($encoded_url);
    $trace .= "4. HTTP Status Code Check: " . json_encode($status_result) . "\n";

    if (isset($status_result['code']) && $status_result['code'] != 200 && $status_result['code'] != 301 && $status_result['code'] != 302 && $status_result['code'] != '301-like' && $status_result['code'] != '301-in-html') {
        return array(
            'error_type' => 'http_status_error',
            'error_message' => 'وضعیت سرور مقصد ناموفق بود: HTTP Status Code ' . $status_result['code'],
            'stack_trace' => $trace
        );
    }

    // 4. Fetch HTML Body
    $html_content = fetch_html_with_curl($encoded_url);
    if (empty($html_content)) {
        return array(
            'error_type' => 'empty_html_error',
            'error_message' => 'محتوای HTML دریافتی از لینک خبر کاملاً خالی است.',
            'stack_trace' => $trace . "5. fetch_html_with_curl returned empty string."
        );
    }

    // 5. DOM Verification
    $html = str_get_html($html_content);
    if (!$html) {
        return array(
            'error_type' => 'html_parse_error',
            'error_message' => 'امکان پارس محتوای HTML با کتابخانه پارسر وجود نداشت.',
            'stack_trace' => $trace . "5. str_get_html failed to load HTML content."
        );
    }

    // Verify selectors
    // Title Selector
    $title_el = $html->find($title_selector, 0);
    if (!$title_el || empty(trim($title_el->plaintext))) {
        $html->clear();
        unset($html);
        return array(
            'error_type' => 'selector_title_error',
            'error_message' => sprintf('سلکتور عنوان (%s) در سایت مقصد یافت نشد یا خالی است.', $title_selector),
            'stack_trace' => $trace . "6. Selector '$title_selector' returned no text."
        );
    }

    // Body Selector
    $body_el = $html->find($body_selector, 0);
    if (!$body_el || empty(trim($body_el->innertext))) {
        $html->clear();
        unset($html);
        return array(
            'error_type' => 'selector_body_error',
            'error_message' => sprintf('سلکتور متن بدنه (%s) در سایت مقصد یافت نشد یا خالی است.', $body_selector),
            'stack_trace' => $trace . "6. Selector '$body_selector' returned no text."
        );
    }

    $html->clear();
    unset($html);
    return true; // Validated successfully

}
