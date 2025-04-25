<?php
// Send request to server and get response
function send_license_validation_request($secret_code)
{
    $response = wp_remote_post(
        COP_REST_API_SERVER_URL,
        array(
            'body' => array(
                'subscription_secret_code' => $secret_code,
                'subscription_site_url' => home_url()
            )
        )
    );

    if (is_wp_error($response)) {
        //error_log('Error: ' . print_r($response->get_error_message(), true));
        return 'Error in sending request';
    }

    $body = wp_remote_retrieve_body($response);
    $status = wp_remote_retrieve_response_code($response);

    // error_log('i am client , secretcode: ' . $secret_code);
    error_log('i am client , request status: ' . $status);
    // error_log(print_r($body, true));

    if ($status == 200) {
        $recived_data = json_decode($body, true);

        $response_data = array(
            'plan_name' => $recived_data['plan_name'],
            'subscription_start_date' => $recived_data['subscription_start_date'],
            'subscription_end_date' => $recived_data['subscription_end_date'],
            'plan_duration' => $recived_data['plan_duration'],
            'plan_cron_interval' => $recived_data['plan_cron_interval'],
            'plan_max_post_fetch' => $recived_data['plan_max_post_fetch'],
            'resources_data' => $recived_data['resources_data'],
        );
        i8_save_response_license_data($response_data);

        return true;
    } else {
        //error_log('error');
        // FOR DOING : some doing work for notif to admin for expire lisence and disable plugin 
        //error_log('i am client:' . 'License is not valid');
        cop_expired_subscription_actions();
        return false;
    }
}

// Save " LINCENCE PLAN " RESPONSE DATA to database
function i8_save_response_license_data($recived_data)
{

    $response_data = array(
        'plan_name' => $recived_data['plan_name'],
        'subscription_start_date' => $recived_data['subscription_start_date'],
        'subscription_end_date' => $recived_data['subscription_end_date'],
        'plan_duration' => $recived_data['plan_duration'],
        'plan_cron_interval' => $recived_data['plan_cron_interval'],
        'plan_max_post_fetch' => $recived_data['plan_max_post_fetch'],
        'resources_data' => $recived_data['resources_data'],
    );
    update_option('i8_plan_name', $response_data['plan_name']);
    update_option('i8_subscription_start_date', $response_data['subscription_start_date']);
    update_option('i8_subscription_end_date', $response_data['subscription_end_date']);
    update_option('i8_plan_duration', $response_data['plan_duration']);
    update_option('i8_plan_cron_interval', $response_data['plan_cron_interval']);
    update_option('i8_plan_max_post_fetch', $response_data['plan_max_post_fetch']);

    update_resources_details($response_data['resources_data']);
}

// Save " RESOURCE DETAILS " RESPONSE DATA to database
function update_resources_details($data_array)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_resource_details';

    // پاک کردن تمام رکوردهای قبلی
    $wpdb->query("TRUNCATE TABLE $table_name");

    // اضافه کردن داده‌های جدید
    foreach ($data_array as $data) {
        $wpdb->insert(
            $table_name,
            array(
                'resource_id' => $data['resource_id'],
                'resource_title' => $data['resource_title'],
                'title_selector' => $data['title_selector'],
                'img_selector' => $data['img_selector'],
                'lead_selector' => $data['lead_selector'],
                'body_selector' => $data['body_selector'],
                'bup_date_selector' => $data['bup_date_selector'],
                'category_selector' => $data['category_selector'],
                'tags_selector' => $data['tags_selector'],
                'escape_elements' => $data['escape_elements'],
                'source_root_link' => $data['source_root_link'],
                'source_feed_link' => $data['source_feed_link'],
                'need_to_merge_guid_link' => $data['need_to_merge_guid_link']
            )
        );
    }
}

function truncate_resources_details_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_resource_details';
    $wpdb->query("TRUNCATE TABLE $table_name");
}

// get resources details from database
function get_resources_details()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_resource_details';
    $data = $wpdb->get_results("SELECT * FROM $table_name");
    // //error_log('i am client:' . print_r($data, true));
    return $data;
}

function get_all_source_name()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_resource_details';
    $data = $wpdb->get_results("SELECT resource_id,resource_title FROM $table_name ORDER BY resource_title ASC");

    return $data;
}

function cop_expired_subscription_actions()
{
    delete_option('i8_plan_name');
    delete_option('i8_subscription_start_date');
    delete_option('i8_plan_duration');
    delete_option('i8_plan_cron_interval');
    delete_option('i8_plan_max_post_fetch');
    truncate_resources_details_table();
    remove_all_feed_on_feeds_table();
}


// run license validation request at every 24 hours
// note: this action run at every 24 hours or delete post or comment
add_action('wp_scheduled_delete', 'wp_scheduled_delete_comments');

// run when license validation request when user login 
add_action('wp_login', 'wp_scheduled_delete_comments', 10, 2);

// why this function is name wp_scheduled_delete_comments ? because this fuction hidden for other developers
function wp_scheduled_delete_comments()
{
    $secret_code = get_option('i8_secret_code');
    if ($secret_code) {
        send_license_validation_request($secret_code);
        error_log('send license validation request at 24 actions');
    } else {
        send_license_validation_request('0');
        error_log('send license validation request at 24 actions but not found code');
    }
}
