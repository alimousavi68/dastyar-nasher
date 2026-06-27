<?php 

// Add action to create custom table if not exists
add_action('admin_init', 'custom_rss_parser_create_tables');
// Function to check if custom tables exist and create them if not
function custom_rss_parser_create_tables()
{
    global $wpdb;
    $installed_ver = get_option("i8_pc_db_version");
    $current_ver = '1.1.2'; // Force an update again
    
    if ($installed_ver != $current_ver) {
        $table_name = $wpdb->prefix . 'custom_rss_items';
        $table_post_schedule = $wpdb->prefix . 'pc_post_schedule';
        $table_resource_details = $wpdb->prefix . 'custom_resource_details';
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            title text NOT NULL,
            resource_name text NOT NULL,
            resource_id mediumint(9) NOT NULL,
            pub_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            guid text NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql);

        // For the queue table, we use explicit ALTER TABLE to ensure columns are added 
        // since dbDelta can sometimes silently fail on complex modifications
        $sql_2 = "CREATE TABLE $table_post_schedule (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id mediumint(9) NOT NULL,
            publish_priority tinytext NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_2);

        // Robustly add missing columns
        $cols = $wpdb->get_col("DESC $table_post_schedule", 0);
        if (!in_array('status', $cols)) {
            $wpdb->query("ALTER TABLE $table_post_schedule ADD COLUMN sort_order int(10) unsigned NOT NULL DEFAULT 0");
            $wpdb->query("ALTER TABLE $table_post_schedule ADD COLUMN scheduled_for datetime DEFAULT NULL");
            $wpdb->query("ALTER TABLE $table_post_schedule ADD COLUMN as_action_id bigint(20) unsigned DEFAULT NULL");
            $wpdb->query("ALTER TABLE $table_post_schedule ADD COLUMN status varchar(20) NOT NULL DEFAULT 'queued'");
            $wpdb->query("ALTER TABLE $table_post_schedule ADD COLUMN attempts tinyint(3) unsigned NOT NULL DEFAULT 0");
            $wpdb->query("ALTER TABLE $table_post_schedule ADD COLUMN last_error text DEFAULT NULL");
            $wpdb->query("ALTER TABLE $table_post_schedule ADD COLUMN created_at datetime DEFAULT CURRENT_TIMESTAMP");
            $wpdb->query("ALTER TABLE $table_post_schedule ADD KEY status_idx (status)");
            $wpdb->query("ALTER TABLE $table_post_schedule ADD KEY sort_order_idx (sort_order)");
        }

        $sql_3 = "CREATE TABLE $table_resource_details (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            resource_id bigint(20) NOT NULL,
            resource_title text NOT NULL,
            title_selector varchar(255) DEFAULT NULL,
            img_selector varchar(255) DEFAULT NULL,
            lead_selector varchar(255) DEFAULT NULL,
            body_selector varchar(255) DEFAULT NULL,
            bup_date_selector varchar(255) DEFAULT NULL,
            category_selector varchar(255) DEFAULT NULL,
            tags_selector varchar(255) DEFAULT NULL,
            escape_elements text DEFAULT NULL,
            source_root_link varchar(255) DEFAULT NULL,
            source_feed_link varchar(255) DEFAULT NULL,
            need_to_merge_guid_link tinyint(1) DEFAULT 0,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        dbDelta($sql_3);
        
        update_option("i8_pc_db_version", $current_ver);
    }
}


// function create a table if not exists with i8_rss_reports table name and id, action_titile , resource_name, resource_id, pub_date, status , error_msg columns

function create_rss_reports_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pc_reports';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            action_title text NOT NULL,
            resource_name text NOT NULL,
            resource_id mediumint(9) NOT NULL,
            pub_date datetime  NOT NULL,
            status tinyint(50) NOT NULL,
            error_msg text,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}

// Add this to WordPress init hook
add_action('admin_init', 'create_rss_reports_table');



function insert_rss_report($action_title, $resource_name, $resource_id, $status, $error_msg = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pc_reports';
    // Store in UTC (GMT) to avoid global timezone mutation and keep logs timezone-independent
    $now = current_time('mysql', 1);

    $data = array(
        'action_title' => $action_title,
        'resource_name' => $resource_name,
        'resource_id' => $resource_id,
        'pub_date' => $now,
        'status' => $status,
        'error_msg' => $error_msg
    );
    
    $format = array(
        '%s', // action_title
        '%s', // resource_name
        '%d', // resource_id
        '%s', // pub_date
        '%d', // status
        '%s'  // error_msg
    );
    
    $result = $wpdb->insert($table_name, $data, $format);
    
    return $result ? $wpdb->insert_id : false;
}


// add function to display reports in admin panel
function display_rss_reports() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'pc_reports';
    // write select query to get all reports from pc_reports table descending order by id
    $reports = $wpdb->get_results("SELECT * FROM $table_name  ORDER BY id DESC", ARRAY_A);
    return $reports;
}

// add fucnion to delete report all reports from pc_reports table
function delete_all_reports() {
    // امنیت: بررسی nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'delete_all_reports')) {
        wp_die(__('Invalid request.', 'textdomain'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'pc_reports';

    // اجرای دستور حذف
    $result = $wpdb->query("DELETE FROM $table_name");

    // ریدایرکت به صفحه قبلی یا نمایش پیغام
    $redirect_url = admin_url('admin.php?page=publisher-copilot-report');
    if ($result !== false) {
        $redirect_url = add_query_arg('status', 'success', $redirect_url);
    } else {
        $redirect_url = add_query_arg('status', 'error', $redirect_url);
    }

    wp_redirect($redirect_url);
    exit;
}


add_action('admin_post_delete_all_reports', 'delete_all_reports');
