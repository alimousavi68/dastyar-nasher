<?php 
// Function to fetch the RSS feed
function fetch_rss_feed($url)
{
    // Make sure URL is properly encoded if it contains Persian or non-ASCII characters
    $encoded_url = $url;
    if (function_exists('encodeUrl')) {
        $encoded_url = encodeUrl(trim($url));
    } elseif (function_exists('encode_persian_chracter_allowed_url')) {
        $encoded_url = encode_persian_chracter_allowed_url(trim($url));
    } else {
        $encoded_url = preg_replace_callback('/[^\x20-\x7f]/', function ($matches) {
            return rawurlencode($matches[0]);
        }, trim($url));
    }

    $args = array(
        'timeout'     => 20,
        'sslverify'   => false,
        'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'headers'     => array(
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'fa-IR,fa;q=0.9,en-US;q=0.8,en;q=0.7',
        ),
    );

    $response = wp_remote_get($encoded_url, $args);
    
    if (is_wp_error($response)) {
        error_log('i8 fetch_rss_feed error: ' . $response->get_error_message() . ' for URL: ' . $url);
        global $i8_last_feed_error;
        $i8_last_feed_error = $response->get_error_message();
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        error_log('i8 fetch_rss_feed HTTP error code: ' . $response_code . ' for URL: ' . $url);
        global $i8_last_feed_error;
        $i8_last_feed_error = 'کد وضعیت HTTP: ' . $response_code;
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    if (empty($body)) {
        global $i8_last_feed_error;
        $i8_last_feed_error = 'پاسخ دریافتی از سرور خالی بود.';
        return false;
    }

    // Suppress errors and capture them manually
    libxml_use_internal_errors(true);
    $rss_feed = simplexml_load_string($body);

    if ($rss_feed === false) {
        $errors = libxml_get_errors();
        $xml_err_msgs = [];
        foreach ($errors as $error) {
            $xml_err_msgs[] = trim($error->message);
        }
        libxml_clear_errors();
        libxml_use_internal_errors(false);
        
        global $i8_last_feed_error;
        $i8_last_feed_error = 'خطا در پارس XML فید: ' . implode(' | ', array_slice($xml_err_msgs, 0, 3));
        error_log('i8 fetch_rss_feed XML error: ' . $i8_last_feed_error . ' for URL: ' . $url);
        return false;
    }
    libxml_use_internal_errors(false);
    return $rss_feed;
}

// Function to check if an item already exists in the database
function custom_rss_parser_item_exists($guid)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_rss_items';

    $result = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE guid = %s", $guid . ''));

    return $result > 0;
}

// Function to insert a new item into the custom table
function custom_rss_parser_insert_item($title, $pub_date, $guid, $resource_id, $resource_name)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_rss_items';

    $wpdb->insert(
        $table_name,
        array(
            'title' => '' . $title,
            'resource_name' => $resource_name,
            'resource_id' => $resource_id,
            'pub_date' => $pub_date,
            'guid' => '' . $guid,
        )
    );
}


add_action('remove_all_feed_on_feeds_table', 'remove_all_feed_on_feeds_table');


// remove all feed on feed table [custom_rss_items]
function remove_all_feed_on_feeds_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_rss_items';
    $delete_status = $wpdb->query("DELETE FROM $table_name");
    if ($delete_status) {
        wp_safe_redirect(add_query_arg('success', 'true', wp_get_referer()));
        exit;
    } else {
        echo '<div class="notice notice-error is-dismissible">
                <p>مشکلی پیش آمد!</p>
            </div>';
    }
}