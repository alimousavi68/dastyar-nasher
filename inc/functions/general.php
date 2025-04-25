<?php 

// DEACTIVATE PLUGIN FUNCTION
function i8_pc_plugin_deactivate_self()
{
    deactivate_plugins(plugin_basename(__FILE__));
}

// تابع سفارشی برای تغییر کلاس‌های paginate_links
function custom_paginate_links($output)
{
    // تغییر کلاس‌های <ul>
    $output = str_replace('<ul class="page-numbers">', '<ul class="pagination justify-content-center">', $output);

    // تغییر کلاس‌های <li>
    $output = str_replace('<li', '<li class="page-item"', $output);

    return $output;
}

// اضافه کردن فیلتر به paginate_links
add_filter('paginate_links', 'custom_paginate_links');



// Get Resource Item Selector From Resource Id
function get_resource_data($resource_id, $resource_item_selector)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_resource_details';
    $sql = $wpdb->prepare("SELECT $resource_item_selector FROM $table_name WHERE resource_id = %d ", $resource_id);

    $selector = $wpdb->get_var($sql);

    if ($selector) {
        return $selector;
    } else {
        return '';
    }
}