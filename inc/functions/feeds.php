<?php 
// Function to fetch the RSS feed
function fetch_rss_feed($url)
{
    $response = wp_remote_get($url);
    
    if (is_wp_error($response)) {
        // $report_id = insert_rss_report(
        //     'درخواست واکشی فید های یک منبع',
        //     $url,
        //     123,
        //     '0',
        //     $response->get_error_message()
        // );
        return false;
    }

    $body = wp_remote_retrieve_body($response);

    // Suppress errors and capture them manually
    libxml_use_internal_errors(true);
    $rss_feed = simplexml_load_string($body);

    if ($rss_feed === false) {
        $errors = libxml_get_errors();
        foreach ($errors as $error) {
            // error_log('i8_feeds_error: XML Parsing Error: ' . $error->message . ' in ' . $error->file . ' on line ' . $error->line);
        }
        // error_log('i8_feeds_error: Raw body content that caused error: ' . $body);
        libxml_clear_errors();
        return false;
    }
    libxml_use_internal_errors(false);
    // $report_id = insert_rss_report(
    //     'درخواست واکشی فید های یک منبع',
    //     $url,
    //     123,
    //     'موفق',
    // );
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