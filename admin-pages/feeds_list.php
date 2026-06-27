<?php
defined('ABSPATH') || exit;

// Helper to convert English digits to Persian digits
function cop_convert_to_persian_digits($string) {
    $persian_digits = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
    $english_digits = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
    return str_replace($english_digits, $persian_digits, $string);
}

// Hook into the admin menu
add_action('admin_menu', 'custom_rss_parser_menu');


// Function to add menu and page
function custom_rss_parser_menu()
{
    add_menu_page(
        'آخرین فیدهای دستیار سردبیر',
        'دستیار سردبیر',
        'edit_posts',
        'publisher_copoilot',
        'publisher_copoilot_callback',
        'dashicons-image-filter',
        5
    );
    
    // Rename the first submenu item
    add_submenu_page(
        'publisher_copoilot',
        'رصدخانه اخبار',
        'رصدخانه اخبار',
        'edit_posts',
        'publisher_copoilot'
    );
}


// Callback function for menu page
function publisher_copoilot_callback()
{
    ?>
    <!-- Include Stylesheets -->
    <link rel="stylesheet" href="<?php echo COP_PLUGIN_URL; ?>/assets/css/feed_list.css">
    <link rel="stylesheet" href="<?php echo COP_PLUGIN_URL; ?>/assets/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="<?php echo COP_PLUGIN_URL; ?>/assets/css/select2.min.css">
    <!-- include Js scripts -->
    <script src="<?php echo COP_PLUGIN_URL; ?>/assets/js/jquery.min.js"></script>
    <script src="<?php echo COP_PLUGIN_URL; ?>/assets/js/sweetalert2@11.js"></script>
    <?php

    if (isset($_GET['success'])) {
        $action_status = $_GET['success'];
        if ($action_status == 'true') {
            ?>
            <script>
                window.onload = function () {
                    Toast.fire({
                        icon: "success",
                        title: 'عملیات با موفقیت انجام شد!'
                    });
                };
            </script>

            <?php

        } elseif ($action_status == 'false') {
            ?>
            <script>
                window.onload = function () {
                    Toast.fire({
                        icon: "error",
                        title: 'مشکلی پیش آمد!'
                    });
                };
            </script>
            <?php
            
        }
    }

    $action = (isset($_GET['action'])) ? $_GET['action'] : '';
    if (!empty($action)) {
        if (!current_user_can('edit_posts')) {
            wp_die(__('شما دسترسی کافی برای انجام این عملیات را ندارید.'));
        }

        if ($action == 'delete_all') {
            check_admin_referer('cop_delete_all_nonce');
            remove_all_feed_on_feeds_table();
            wp_safe_redirect(add_query_arg('success', 'true', remove_query_arg(array('action', '_wpnonce'))));
            exit;
        }
        if ($action == 'delete_item') {
            check_admin_referer('cop_delete_item_nonce');
            $item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;
            if ($item_id > 0) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'custom_rss_items';
                $wpdb->delete($table_name, array('id' => $item_id), array('%d'));
                wp_safe_redirect(add_query_arg('success', 'true', remove_query_arg(array('action', 'item_id', '_wpnonce'))));
                exit;
            }
        }

        if ($action == 'update_feeds') {
            check_admin_referer('cop_update_feeds_nonce');
            do_action('custom_rss_parser_event');
            wp_safe_redirect(add_query_arg('success', 'true', remove_query_arg(array('action', '_wpnonce'))));
            exit;
        }
    }
    // $current_url = wp_get_referer();

    // اگر URL صفحه قبل موجود نبود، از URL فعلی استفاده کنید

    $current_url = add_query_arg(NULL, NULL);
    // if (isset($_GET['source']) && $_GET['source'] == '#') {
    //     $url = strtok($current_url, '?'); 
    //     header("Location: $url");
    //     exit;
    // }

    $file = __DIR__ . '/../inc/icons.php';
    if (file_exists($file)) {
        include_once $file;
    } else {
        echo 'icons.php not found!';
    }


    ?>

    <div class="app-container">
        <?php
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_rss_items';
        // Fetch items from the database with pagination
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        ?>
        
        <!-- Unified Sticky Header -->
        <!-- Unified Sticky Header -->
        <div class="sticky-header-bar d-flex flex-wrap flex-xl-nowrap align-items-center gap-3 p-3 mb-4 shadow-sm-soft border bg-white rounded-xl" style="position: sticky; top: 32px; z-index: 100;">
            <!-- Logo & Title -->
            <div class="d-flex align-items-center gap-2 pe-xl-3 border-start-xl">
                <div class="radar-container me-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary radar-scanner-icon">
                        <circle cx="12" cy="12" r="10" stroke="rgba(13, 110, 253, 0.15)" stroke-width="1.5" />
                        <circle cx="12" cy="12" r="6" stroke="rgba(13, 110, 253, 0.3)" stroke-width="1.5" />
                        <circle cx="12" cy="12" r="2" stroke="currentColor" fill="currentColor" />
                        <line x1="12" y1="12" x2="19" y2="5" stroke="currentColor" stroke-width="2" class="radar-sweep" />
                    </svg>
                    <span class="radar-pulse-ring"></span>
                </div>
                <span class="fs-5 fw-bolder text-slate-800" style="white-space: nowrap;">رصدخانه اخبار</span>
            </div>

            <!-- Filters -->
            <form id="filter_form" action="<?php echo get_full_url() ; ?>" method="post" class="d-flex flex-grow-1 flex-wrap flex-md-nowrap align-items-center gap-2 m-0">
                <!-- Hidden Search Keyword Input for Spotlight Search -->
                <input type="hidden" id="search_keyword" name="search_keyword" value="<?php echo isset($_POST['search_keyword']) ? esc_attr($_POST['search_keyword']) : '' ?>">

                <!-- Source Selector (Expanded size) -->
                <div class="flex-grow-1" style="max-width: 700px; min-width: 280px;">
                    <select class="form-control form-select" id="resource_list" name="resource_list[]" multiple="multiple" data-placeholder="انتخاب منابع خبری..." style="display: none;">
                        <optgroup label="منابع خبری">
                            <?php
                            foreach (get_all_source_name() as $source_name): ?>
                                <option value="<?php echo $source_name->resource_id; ?>" <?php
                                    echo (isset($_POST['resource_list']) && is_array($_POST['resource_list']) && in_array($source_name->resource_id, $_POST['resource_list'])) ? esc_attr('selected') : '' ?>>
                                    <?php echo $source_name->resource_title; ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>

                <!-- Action Buttons (Filters & General) -->
                <div class="d-flex align-items-center gap-1">
                    <!-- Submit Filter Button -->
                    <button type="submit" class="btn-icon btn-icon-primary" data-bs-toggle="tooltip" data-bs-title="اعمال فیلتر">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-funnel-fill" viewBox="0 0 16 16">
                            <path d="M1.5 1.5A.5.5 0 0 1 2 1h12a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.128.334L10 8.692V13.5a.5.5 0 0 1-.342.474l-3 1A.5.5 0 0 1 6 14.5V8.692L1.628 3.834A.5.5 0 0 1 1.5 3.5v-2z"/>
                        </svg>
                    </button>

                    <!-- Clear Filters Button -->
                    <button type="button" id="clear_filters" class="btn-icon btn-icon-outline text-slate-500" data-bs-toggle="tooltip" data-bs-title="پاکسازی فیلترها">
                        <?php echo $icon_eraser; ?>
                    </button>

                    <!-- Search Spotlight Button -->
                    <button type="button" id="toggle_search_btn" class="btn-icon btn-icon-outline text-slate-500" data-bs-toggle="tooltip" data-bs-title="جستجو در اخبار">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                        </svg>
                    </button>
                </div>
            </form>

            <!-- Redesigned Left Section: Stats/Badges mirroring the uploaded image -->
            <div class="d-flex align-items-center gap-2 ms-auto mt-2 mt-xl-0">
                <!-- Total Feeds Badge -->
                <div class="status-count-badge" style="white-space: nowrap;" data-bs-toggle="tooltip" data-bs-title="تعداد کل اخبار واکشی شده">
                    <span class="count-text"><?php echo cop_convert_to_persian_digits(number_format_i18n($total_items)); ?> خبر</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-text" viewBox="0 0 16 16">
                        <path d="M5.5 7a.5.5 0 0 0 0 1h5a.5.5 0 0 0 0-1zM5 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5"/>
                        <path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5z"/>
                    </svg>
                </div>

                <!-- Time Badge -->
                <div class="status-time-badge" style="white-space: nowrap;" data-bs-toggle="tooltip" data-bs-title="زمان واکشی بعدی">
                    <span class="time-text">
                    <?php
                    $next_time = get_option('i8_next_scrap_all_resource_feed_time', '');
                    if ($next_time) {
                        $local_time = intval($next_time) + (floatval(get_option('gmt_offset')) * HOUR_IN_SECONDS);
                        $time_str = gmdate('H:i:s', $local_time);
                    } else {
                        $local_time = time() + (floatval(get_option('gmt_offset')) * HOUR_IN_SECONDS);
                        $time_str = gmdate('H:i:s', $local_time);
                    }
                    echo cop_convert_to_persian_digits($time_str);
                    ?>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clock" viewBox="0 0 16 16">
                        <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71z"/>
                        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0"/>
                    </svg>
                </div>

                <!-- Connection Active Badge -->
                <div class="status-connection-badge" style="white-space: nowrap;">
                    <span class="connection-text">اتصال فعال</span>
                    <span class="status-dot"></span>
                </div>

                <!-- Delete All Button -->
                <a href="javascript:void(0)" class="btn-header-delete-all text-slate-500" data-nonce="<?php echo esc_attr(wp_create_nonce('cop_delete_all_nonce')); ?>" data-bs-toggle="tooltip" data-bs-title="حذف همه فیدها">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3" viewBox="0 0 16 16">
                        <path d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5M11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.885 16h6.23a2 2 0 0 0 1.994-1.84l.853-10.66h.538a.5.5 0 0 0 0-1zm1.958 1-.846 10.58a1 1 0 0 1-.997.92h-6.23a1 1 0 0 1-.997-.92L3.042 3.5zm-7.487 1a.5.5 0 0 1 .528.47l.5 8.5a.5.5 0 0 1-.998.06L5 5.03a.5.5 0 0 1 .47-.53Zm5.058 0a.5.5 0 0 1 .47.53l-.5 8.5a.5.5 0 1 1-.998-.06l.5-8.5a.5.5 0 0 1 .528-.47M8 4.5a.5.5 0 0 1 .5.5v8.5a.5.5 0 0 1-1 0V5a.5.5 0 0 1 .5-.5"/>
                    </svg>
                </a>

                <!-- Refresh/Update Button -->
                <a href="javascript:void(0)" class="btn-header-refresh text-slate-500" data-nonce="<?php echo esc_attr(wp_create_nonce('cop_update_feeds_nonce')); ?>" data-bs-toggle="tooltip" data-bs-title="به‌روزرسانی هم‌اکنون">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-repeat" viewBox="0 0 16 16">
                        <path d="M11.534 7h3.932a.25.25 0 0 1 .192.41l-1.966 2.36a.25.25 0 0 1-.384 0l-1.966-2.36a.25.25 0 0 1 .192-.41zm-11 2h3.932a.25.25 0 0 0 .192-.41L2.692 6.23a.25.25 0 0 0-.384 0L.342 8.59A.25.25 0 0 0 .534 9z"/>
                        <path fill-rule="evenodd" d="M8 3c-1.552 0-2.94.707-3.857 1.818a.5.5 0 1 1-.771-.636A6.002 6.002 0 0 1 13.917 7H12.9A5.002 5.002 0 0 0 8 3zM3.1 9a5.002 5.002 0 0 0 8.757 2.182.5.5 0 1 1 .771.636A6.002 6.002 0 0 1 2.083 9H3.1z"/>
                    </svg>
                </a>

            </div>
        </div>

        <div class="px-0 px-lg-3">
            <!-- table header (disabled for modern row redesign) -->
            <!-- <div class="table-header row d-none d-md-flex align-items-center fw-bold">
                <div class="col col-auto p-0"> </div>
                <div class="col col-8 col-md-2 px-0 text-center" style="max-width: 110px;">
                    <span class="item_counter">#</span> زمان انتشار
                </div>
                <div class="col col-12 col-md-5 text-start pe-4">عنوان</div>
                <div class="col col-4 col-md-2 text-center">منبع</div>
                <div class="col col-12 col-md-3 text-center">عملیات سریع</div>
            </div> -->

            <!-- table body -->
            <div class="table-body">
                <?php custom_rss_parser_display_items(); ?>

                <?php
}


// Function to display the list of items
function custom_rss_parser_display_items()
{
    $file = __DIR__ . '/../inc/icons.php';
    if (file_exists($file)) {
        include_once $file;
    } else {
        echo 'icons.php not found!';
    }

    if (isset($_GET['item_per_page'])) {
        $items_per_page = $_GET['item_per_page'];
        if (get_option('pc_item_per_page') && get_option('pc_item_per_page') != $items_per_page) {
            update_option('pc_item_per_page', $items_per_page);
        } else {
            add_option('pc_item_per_page', $items_per_page);
        }
    } else {
        if (get_option('pc_item_per_page')) {
            $items_per_page = get_option('pc_item_per_page');
        } else {
            add_option('pc_item_per_page', 20);
            $items_per_page = 20;
        }
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_rss_items';

    // اصلاح خودکار فیدهایی که به دلیل داشتن تاریخ در آینده در بالای لیست قفل (استیکی) شده‌اند
    $now_gmt = current_time('mysql', 1);
    $wpdb->query($wpdb->prepare("UPDATE $table_name SET pub_date = %s WHERE pub_date > %s", $now_gmt, $now_gmt));

    // Get the current page number
    $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

    // Calculate the offset for the query
    $offset = ($paged - 1) * $items_per_page;

    // Check if $_POST['resource_list'] is an array
    $source_ids = isset($_POST['resource_list']) && is_array($_POST['resource_list']) ? $_POST['resource_list'] : [];

    $search_keyword = isset($_POST['search_keyword']) ? $_POST['search_keyword'] : '';

    // Fetch items using the function
    $result = fetch_items_from_database($table_name, $source_ids, $items_per_page, $offset, $search_keyword);

    $items = $result[0];
    $total_items = $result[1];

    // Batch fetch feed statuses
    $status_map = [];
    if (!empty($items)) {
        $guids = array_column($items, 'guid');
        $guids_escaped = array_map(function($g) use ($wpdb) { return "'" . esc_sql($g) . "'"; }, $guids);
        $guids_str = implode(',', $guids_escaped);

        $query = "SELECT pm.meta_value as guid, p.post_status, s.status as schedule_status 
                  FROM {$wpdb->postmeta} pm 
                  JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                  LEFT JOIN {$wpdb->prefix}pc_post_schedule s ON s.post_id = p.ID 
                  WHERE pm.meta_key = '_dastyar_feed_guid' AND pm.meta_value IN ($guids_str)";
        
        $feed_statuses = $wpdb->get_results($query);
        
        foreach ($feed_statuses as $fs) {
            if ($fs->schedule_status === 'queued' || $fs->schedule_status === 'scheduled') {
                $status_map[$fs->guid] = 'queued';
            } elseif ($fs->post_status === 'publish') {
                $status_map[$fs->guid] = 'published';
            } elseif ($fs->post_status === 'pending') {
                $status_map[$fs->guid] = 'pending';
            } else {
                $status_map[$fs->guid] = 'draft';
            }
        }
    }

    // Calculate total pages
    $total_pages = ceil($total_items / $items_per_page);

    ?>
                <?php
                foreach ($items as $key => $item) {
                    $item_id = esc_html($item->id);
                    $row_counter = $offset + $key + 1;
                    $item_guid = esc_html($item->guid);
                    $item_title = esc_html($item->title);
                    $resource_name = $item->resource_name ? $item->resource_name : '-';

                    // Parse pub_date as UTC, then convert to site timezone and format using i8_jDateTime
                    // Robust timezone parsing using get_date_from_gmt
                    $local_date_str = get_date_from_gmt($item->pub_date);
                    $fake_timestamp = strtotime($local_date_str . ' UTC');
                    if (class_exists('i8_jDateTime')) {
                        $jdate = new i8_jDateTime(true, true, 'UTC');
                        $pub_date = i8_to_persian_num($jdate->date('d / m', $fake_timestamp));
                        $pub_time = i8_to_persian_num($jdate->date('H:i', $fake_timestamp));
                    } else {
                        $pub_date = i8_to_persian_num(date('m/d', $fake_timestamp));
                        $pub_time = i8_to_persian_num(date('H:i', $fake_timestamp));
                    }

                    $resource_id = esc_attr($item->resource_id);
                    $admin_url = esc_url(get_admin_url() . 'images/wpspin_light-2x.gif');

                    ?>

                    <!-- Table Item -->
                    <!-- Table Item -->
                    <div id="item-<?php echo $item_id; ?>" class="table-item d-flex align-items-center justify-content-between gap-3 position-relative" data-guid="<?php echo $item_guid; ?>" data-resource-id="<?php echo $resource_id; ?>">
                        <!-- Loading Overlay -->
                        <div class="row-loading-overlay">
                            <div class="premium-spinner"></div>
                            <span class="text-slate-600 fw-bold" style="font-size: 13px;">در حال واکشی اطلاعات...</span>
                        </div>

                        <!-- Right Side Content Info -->
                        <div class="feed-item-info d-flex align-items-center gap-3 min-w-0 flex-grow-1">
                            <!-- Time & Date Column (Rightmost) -->
                            <div class="feed-time-column d-flex flex-column align-items-center justify-content-center text-center">
                                <span class="feed-time"><?php echo $pub_time; ?></span>
                                <span class="feed-date"><?php echo $pub_date; ?></span>
                            </div>

                            <!-- Vertical Divider -->
                            <div class="feed-vertical-divider"></div>

                            <!-- Metadata and Title Column -->
                            <div class="feed-item-right-section d-flex flex-column align-items-start gap-2 min-w-0">
                                <!-- Metadata Badges (Above Title) -->
                                <div class="feed-metadata d-flex align-items-center gap-2 flex-wrap">
                                    <!-- Source Badge -->
                                    <span class="badge-source"><?php echo $resource_name; ?></span>

                                    <!-- Status Badge -->
                                    <span class="status-badge-wrapper" id="status-badge-wrapper-<?php echo $item->id; ?>">
                                        <?php
                                        $feed_status = isset($status_map[$item_guid]) ? $status_map[$item_guid] : 'fetched';
                                        
                                        if ($feed_status === 'published') {
                                            echo '<span class="badge-status-published">منتشر شده</span>';
                                        } elseif ($feed_status === 'queued') {
                                            echo '<span class="badge-status-queued">در صف انتشار</span>';
                                        } elseif ($feed_status === 'draft' || $feed_status === 'pending') {
                                            echo '<span class="badge-status-draft">پیش‌نویس</span>';
                                        }
                                        ?>
                                    </span>
                                </div>

                                <!-- Title (Below Metadata) -->
                                <a href="<?php echo $item_guid; ?>" target="_blank" class="feed-title-link text-slate-700 text-decoration-none fw-normal">
                                    <?php echo $item_title; ?>
                                </a>
                            </div>
                        </div>

                        <!-- Left Side: Actions -->
                        <div class="action-bar d-flex align-items-center gap-2" <?php if($feed_status === 'published') echo 'style="opacity: 0.5; pointer-events: none;"'; ?>>
                            <!-- Priority Selector (Without Tooltips) -->
                            <div class="priority-selector-group" <?php if($feed_status === 'published') echo 'style="background: transparent; border-color: transparent;"'; ?>>
                                <span class="priority-label">اولویت صف</span>
                                <button type="button" class="scrape-link btn-priority high" data-id="<?php echo $item->id; ?>" data-guid="<?php echo $item_guid; ?>" data-priority="high" data-resource-id="<?php echo $resource_id; ?>" <?php if($feed_status === 'published') echo 'disabled'; ?>>بالا</button>
                                <button type="button" class="scrape-link btn-priority medium" data-id="<?php echo $item->id; ?>" data-guid="<?php echo $item_guid; ?>" data-priority="medium" data-resource-id="<?php echo $resource_id; ?>" <?php if($feed_status === 'published') echo 'disabled'; ?>>متوسط</button>
                                <button type="button" class="scrape-link btn-priority low" data-id="<?php echo $item->id; ?>" data-guid="<?php echo $item_guid; ?>" data-priority="low" data-resource-id="<?php echo $resource_id; ?>" <?php if($feed_status === 'published') echo 'disabled'; ?>>کم</button>
                            </div>

                            <!-- Fetch Custom Button -->
                            <button type="button" class="scrape-link btn-fetch-custom position-relative" data-bs-toggle="tooltip" data-bs-title="واکشی مستقیم" data-id="<?php echo $item->id; ?>" data-guid="<?php echo $item_guid; ?>" data-priority="now" data-resource-id="<?php echo $resource_id; ?>" <?php if($feed_status === 'published') echo 'disabled'; ?>>
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-cloud-download" viewBox="0 0 16 16">
                                    <path d="M4.406 1.342A5.53 5.53 0 0 1 8 0c2.69 0 4.923 2 5.166 4.579C14.758 4.804 16 6.137 16 7.773 16 9.569 14.502 11 12.687 11H10a.5.5 0 0 1 0-1h2.688C13.979 10 15 8.988 15 7.773c0-1.216-1.02-2.228-2.313-2.228h-.5v-.5C12.188 2.825 10.328 1 8 1a4.53 4.53 0 0 0-2.941 1.1c-.757.652-1.153 1.438-1.153 2.055v.448l-.445.049C2.064 4.805 1 5.952 1 7.318 1 8.785 2.23 10 3.781 10H6a.5.5 0 0 1 0 1H3.781C1.708 11 0 9.366 0 7.318c0-1.763 1.266-3.223 2.942-3.593.143-.863.698-1.723 1.464-2.383"/>
                                    <path d="M7.646 15.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 14.293V5.5a.5.5 0 0 0-1 0v8.793l-2.146-2.147a.5.5 0 0 0-.708.708z"/>
                                </svg>
                                <img src="<?php echo $admin_url; ?>" class="i8-loader-gif" style="display:none;position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);z-index:100;width:20px;" />
                            </button>

                            <!-- Publish Now Button -->
                            <a href="javascript:void(0);" class="btn-action-yellow scrape-link position-relative" data-guid="<?php echo $item_guid; ?>" data-resource-id="<?php echo $resource_id; ?>" data-id="<?php echo $item_id; ?>" data-priority="now" data-bs-toggle="tooltip" data-bs-title="انتشار فوری" <?php if($feed_status === 'published') echo 'disabled style="pointer-events:none;"'; ?>>
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-lightning-charge" viewBox="0 0 16 16"><path d="M11.251.068a.5.5 0 0 1 .227.58L9.677 6.5H13a.5.5 0 0 1 .364.843l-8 8.5a.5.5 0 0 1-.842-.49L6.323 9.5H3a.5.5 0 0 1-.364-.843l8-8.5a.5.5 0 0 1 .615-.09zM4.157 8.5H7a.5.5 0 0 1 .478.647L6.11 13.59l5.732-6.09H9a.5.5 0 0 1-.478-.647L9.89 2.41z"/></svg>
                                <img src="<?php echo $admin_url; ?>" class="i8-loader-gif" style="display:none;position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);z-index:100;width:20px;" />
                            </a>

                            <!-- View Original Button -->
                            <a href="<?php echo $item_guid; ?>" target="_blank" class="btn-action-gray" data-bs-toggle="tooltip" data-bs-title="مشاهده اصلی" <?php if($feed_status === 'published') echo 'disabled style="pointer-events:none;"'; ?>>
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                                </svg>
                            </a>

                            <!-- Delete Button -->
                            <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'delete_item', 'item_id' => $item_id)), 'cop_delete_item_nonce')); ?>" class="btn-action-danger delete-item-link" data-bs-toggle="tooltip" data-bs-title="حذف فید" <?php if($feed_status === 'published') echo 'disabled style="pointer-events:none;"'; ?>>
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-trash3" viewBox="0 0 16 16">
                                    <path d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5M11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.885 16h6.23a2 2 0 0 0 1.994-1.84l.853-10.66h.538a.5.5 0 0 0 0-1zm1.958 1-.846 10.58a1 1 0 0 1-.997.92h-6.23a1 1 0 0 1-.997-.92L3.042 3.5zm-7.487 1a.5.5 0 0 1 .528.47l.5 8.5a.5.5 0 0 1-.998.06L5 5.03a.5.5 0 0 1 .47-.53Zm5.058 0a.5.5 0 0 1 .47.53l-.5 8.5a.5.5 0 1 1-.998-.06l.5-8.5a.5.5 0 0 1 .528-.47M8 4.5a.5.5 0 0 1 .5.5v8.5a.5.5 0 0 1-1 0V5a.5.5 0 0 1 .5-.5"/>
                                </svg>
                            </a>

                        </div>
                    </div>

                    <?php
                }
                ?>

                <!-- table footer -->
                <div class="row mt-2">
                    <div
                        class="col col-md-6 col-12 d-flex align-items-start justify-content-center justify-content-md-start text-secondary">
                        نتایج: <?php echo $total_items ?> مورد -
                        <?php echo 'نمایش ' . ($offset + 1) . ' تا ' . ($offset + $items_per_page); ?>
                    </div>
                    <div
                        class="col col-md-6 col-12 d-flex flex-row gap-2 justify-content-center justify-content-md-end d-flex flex-column flex-md-row justify-content-center">
                        <?php

                        $total_pages = ceil($total_items / $items_per_page);

                        if ($total_pages > 1) {
                            $current_page = max(1, $paged);
                            echo "<nav aria-label='Page navigation' aria-label='صفحه' data-bs-toggle='tooltip'
                        data-bs-placement='top' data-bs-custom-class='custom-tooltip' data-bs-title='صفحه'>";
                            echo paginate_links(
                                array(
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'current' => $current_page,
                                    'total' => $total_pages,
                                    'prev_text' => __('&laquo; Previous'),
                                    'next_text' => __('Next &raquo;'),
                                    'type' => 'list',
                                )
                            );
                            echo "</nav>";
                            echo '<span class="align-items-center d-none d-md-flex"> / </span>';
                        }
                        ?>

                        <?php $current_url = add_query_arg(NULL, NULL); ?>
                            <!-- post per page counter -->
                            <?php
                            $current_item_per_page = isset($_GET['item_per_page']) ? $_GET['item_per_page'] : 50;
                            ?>
                            <ul class="page-numbers justify-content-between" aria-label="تعداد " data-bs-toggle="tooltip"
                                data-bs-placement="top" data-bs-custom-class="custom-tooltip" data-bs-title="تعداد ">

                                <li>
                                    <span class="text-secondary">
                                        تعداد :
                                    </span>
                                </li>
                                <?php for ($i = 25; $i <= 100; $i = $i + 25) {

                                    if ($current_item_per_page != $i) {
                                        ?>
                                        <li>
                                            <a href="<?php echo add_query_arg('item_per_page', $i, $current_url); ?>"
                                                class=" page-numbers">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                        <?php
                                    } else {
                                        ?>
                                        <li>
                                            <span aria-current="page" class="page-numbers current"> <?php echo $i; ?></span>
                                        </li>
                                        <?php
                                    }
                                }
                                ?>


                            </ul>




                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Spotlight Search Overlay -->
        <div id="spotlight_search_modal" class="spotlight-search-overlay" style="display: none;">
            <div class="spotlight-search-box shadow-lg border">
                <div class="d-flex align-items-center gap-2 p-3 border-bottom">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-search text-slate-400" viewBox="0 0 16 16">
                        <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                    </svg>
                    <input type="text" id="spotlight_search_input" placeholder="جستجو در فیدها (کلید Enter برای اعمال)..." class="form-control border-0 shadow-none p-0 fs-5" style="background: transparent; direction: rtl; text-align: right;">
                    <button type="button" id="close_spotlight_btn" class="btn-close shadow-none" aria-label="بستن"></button>
                </div>
                <div class="p-3 text-slate-400" style="font-size: 12px; direction: rtl; text-align: right;">
                    <span>برای اعمال فیلتر جستجو عبارت خود را تایپ کرده و دکمه <strong>Enter</strong> را فشار دهید.</span>
                </div>
            </div>
        </div>


    <!-- include Js scripts -->

    <script src="<?php echo COP_PLUGIN_URL; ?>/assets/js/bootstrap.min.js"></script>
    <script src="<?php echo COP_PLUGIN_URL; ?>/assets/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo COP_PLUGIN_URL; ?>/assets/js/popper.min.js"></script>
    <script src="<?php echo COP_PLUGIN_URL; ?>/assets/js/select2.min.js"></script>

    <script src="<?php echo COP_PLUGIN_URL; ?>/assets/js/custom_js.js"></script>

    <!-- Send Scrpe Request -->
    <script>
        // Custom Toast Function
        function showCustomToast(message, type = 'success', postTitle = '') {
            var container = document.getElementById('custom-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'custom-toast-container';
                container.className = 'custom-toast-container';
                document.body.appendChild(container);
            }

            var toast = document.createElement('div');
            toast.className = 'custom-toast';
            
            var iconHtml = type === 'success' 
                ? '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#22c55e" class="bi bi-check-circle-fill" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#ef4444" class="bi bi-x-circle-fill" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/></svg>';

            var titleHtml = postTitle ? '<div style="font-size: 11px; color: #94a3b8; margin-top: 4px; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' + postTitle + '</div>' : '';

            toast.innerHTML = iconHtml + '<div><div style="font-weight: bold;">' + message + '</div>' + titleHtml + '</div>';
            container.appendChild(toast);

            setTimeout(function () {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(-20px)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(function () {
                    toast.remove();
                }, 300);
            }, 4000);
        }

        jQuery(document).ready(function ($) {
            // Spotlight Search Actions
            $('#toggle_search_btn').on('click', function(e) {
                e.preventDefault();
                $('#spotlight_search_modal').css('display', 'flex').hide().fadeIn(200);
                $('#spotlight_search_input').val($('#search_keyword').val()).focus();
            });
            $('#close_spotlight_btn, #spotlight_search_modal').on('click', function(e) {
                if (e.target === this || $(e.target).hasClass('btn-close') || $(e.target).closest('#close_spotlight_btn').length > 0) {
                    $('#spotlight_search_modal').fadeOut(200);
                }
            });
            $('#spotlight_search_input').on('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('#search_keyword').val($(this).val());
                    $('#filter_form').submit();
                }
            });
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('#spotlight_search_modal').fadeOut(200);
                }
            });
            // Delete confirmation with SweetAlert for single item
            $('.delete-item-link').on('click', function (e) {
                e.preventDefault();
                var deleteUrl = $(this).attr('href');
                Swal.fire({
                    title: 'آیا از حذف این فید مطمئن هستید؟',
                    text: 'این عمل غیرقابل بازگشت است!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'بله، حذف شود',
                    cancelButtonText: 'انصراف'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = deleteUrl;
                    }
                });
            });



            // شیء مدیریت صف کلاینت برای جلوگیری از فشار همزمان روی سرور و لوکال‌هاست
            var ScraperQueue = {
                pending: [],
                activeCount: 0,
                maxConcurrent: 2, // حداکثر ۲ اجرای همزمان جهت ثبات لوکال‌هاست

                add: function(task) {
                    this.pending.push(task);
                    this.processNext();
                },

                processNext: function() {
                    if (this.activeCount >= this.maxConcurrent || this.pending.length === 0) {
                        return;
                    }

                    var task = this.pending.shift();
                    this.activeCount++;
                    
                    var self = this;
                    task.run(function() {
                        self.activeCount--;
                        self.processNext();
                    });
                }
            };

            var scrapeLinks = document.querySelectorAll(".scrape-link");
            scrapeLinks.forEach(function (link) {
                link.addEventListener("click", function (e) {
                    var post_Guid = this.getAttribute("data-guid");
                    var resource_id = this.getAttribute("data-resource-id");
                    var post_id = this.getAttribute("data-id");
                    var publish_priority = this.getAttribute("data-priority");

                    var rowEl = document.getElementById("item-" + post_id);
                    var postTitle = rowEl ? rowEl.querySelector('.feed-title-link').innerText : '';

                    // فعال‌سازی حالت لودینگ اسکلتون فوراً در رابط کاربری
                    if (rowEl) {
                        rowEl.classList.add("loading-state");
                        if(publish_priority === "now") {
                            rowEl.classList.add("processing-now");
                        } else {
                            rowEl.classList.add("processing-queue");
                        }
                    }

                    // اضافه کردن درخواست به صف کلاینت
                    ScraperQueue.add({
                        run: function(callback) {
                            var clickedLink = link;
                            var scrapeImg = clickedLink.querySelectorAll(".i8-loader-gif");
                            scrapeImg.forEach(function (img) {
                                img.style.display = 'inline-block';
                            });

                            $.ajax({
                                url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                                type: 'POST',
                                data: {
                                    action: 'publish_scraper',
                                    security: '<?php echo esc_js(wp_create_nonce('dastyar_publish_scraper_nonce')); ?>',
                                    item_id: post_id,
                                    post_Guid: post_Guid,
                                    resource_id: resource_id,
                                    publish_priority: publish_priority
                                },
                                success: function (response) {
                                    try {
                                        var res = typeof response === 'object' ? response : JSON.parse(response);
                                        if (res.status === true) {
                                            showCustomToast(res.message, 'success', postTitle);
                                            if (rowEl) {
                                                rowEl.classList.remove("loading-state", "processing-now", "processing-queue");
                                                rowEl.classList.add("processed-success");
                                                
                                                var badgeWrapper = document.getElementById("status-badge-wrapper-" + post_id);
                                                if (badgeWrapper) {
                                                    var newBadgeHtml = '';
                                                    if (publish_priority === 'now') {
                                                        newBadgeHtml = '<span class="badge-status-published">منتشر شده</span>';
                                                    } else if (publish_priority === 'pending') {
                                                        newBadgeHtml = '<span class="badge-status-draft">پیش‌نویس</span>';
                                                    } else {
                                                        newBadgeHtml = '<span class="badge-status-queued">در صف انتشار</span>';
                                                    }
                                                    badgeWrapper.innerHTML = newBadgeHtml;
                                                }
                                                
                                                if (publish_priority === "now") {
                                                    $(rowEl).find('.action-bar').css({
                                                        'opacity': '0.5',
                                                        'pointer-events': 'none'
                                                    });
                                                }
                                            }
                                        } else {
                                            showCustomToast(res.message, 'error', postTitle);
                                            if (rowEl) {
                                                rowEl.classList.remove("loading-state", "processing-now", "processing-queue");
                                            }
                                        }
                                    } catch (err) {
                                        showCustomToast("پاسخ نامعتبر از سرور دریافت شد.", 'error', postTitle);
                                        if (rowEl) {
                                            rowEl.classList.remove("loading-state", "processing-now", "processing-queue");
                                        }
                                    }
                                },
                                error: function() {
                                    showCustomToast("خطا در برقراری ارتباط با سرور.", 'error', postTitle);
                                    if (rowEl) {
                                        rowEl.classList.remove("loading-state", "processing-now", "processing-queue");
                                    }
                                },
                                complete: function() {
                                    scrapeImg.forEach(function (img) {
                                        img.style.display = 'none';
                                    });
                                    callback(); // اجرای کار بعدی در صف کلاینت
                                }
                            });
                        }
                    });
                });
            });

            // اکشن AJAX دکمه حذف همه فیدها
            $('.btn-header-delete-all').on('click', function(e) {
                e.preventDefault();
                var nonce = $(this).attr('data-nonce');

                Swal.fire({
                    title: 'حذف تمام فیدها',
                    text: 'آیا از حذف تمامی فیدهای واکشی‌شده مطمئن هستید؟ این عمل غیرقابل بازگشت است.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'بله، حذف شوند',
                    cancelButtonText: 'انصراف'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'در حال پاکسازی...',
                            html: 'لطفاً منتظر بمانید',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        $.ajax({
                            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                            type: 'POST',
                            data: {
                                action: 'dastyar_delete_all_feeds',
                                security: nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        title: 'موفقیت‌آمیز',
                                        text: response.data.message + ' (به دلیل فعال بودن خزش خودکار دقیقه‌ای، فیدهای جدید ممکن است به زودی مجدداً دریافت شوند.)',
                                        icon: 'success',
                                        confirmButtonText: 'تایید'
                                    }).then(() => {
                                        // انیمیشن محو شدن زیبای اخبار پس از حذف و بروزرسانی تعداد بدون رفرش صفحه
                                        $('.table-item').fadeOut(400, function() {
                                            $(this).remove();
                                        });
                                        $('.status-count-badge .count-text').text('۰ خبر');
                                    });
                                } else {
                                    Swal.fire('خطا', response.data || 'عملیات با خطا مواجه شد.', 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                var errText = xhr.responseText ? xhr.responseText.substring(0, 300) : error;
                                Swal.fire('خطا', 'خطا در ارتباط با سرور: ' + errText, 'error');
                            }
                        });
                    }
                });
            });

            // اکشن AJAX دکمه به‌روزرسانی سراسری فیدها
            $('.btn-header-refresh').on('click', function(e) {
                e.preventDefault();
                var nonce = $(this).attr('data-nonce');

                Swal.fire({
                    title: 'به‌روزرسانی فیدها',
                    text: 'آیا می‌خواهید تمامی منابع خبری را هم‌اکنون به‌روزرسانی کنید؟ این کار ممکن است چند ثانیه به طول انجامد.',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#3b82f6',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'بله، به‌روزرسانی شود',
                    cancelButtonText: 'انصراف'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'در حال به‌روزرسانی فیدها...',
                            html: 'در حال دریافت اطلاعات و واکشی خبرهای جدید، لطفاً شکیبا باشید.',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        $.ajax({
                            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                            type: 'POST',
                            data: {
                                action: 'dastyar_update_all_feeds',
                                security: nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    var newCount = response.data.new_items_count;
                                    Swal.fire({
                                        title: 'به‌روزرسانی موفقیت‌آمیز',
                                        text: 'تعداد ' + newCount + ' خبر جدید واکشی شد. در حال بازنشانی صفحه...',
                                        icon: 'success',
                                        confirmButtonText: 'تایید',
                                        timer: 3000,
                                        timerProgressBar: true
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire('خطا', response.data || 'عملیات با خطا مواجه شد.', 'error');
                                }
                            },
                            error: function(xhr, status, error) {
                                var errText = xhr.responseText ? xhr.responseText.substring(0, 300) : error;
                                Swal.fire('خطا', 'خطا در ارتباط با سرور: ' + errText, 'error');
                            }
                        });
                    }
                });
            });
        });


    </script>

    <?php
}

function fetch_items_from_database($table_name, $source_ids, $items_per_page, $offset, $search_keyword)
{
    global $wpdb;

    // شروع ساخت پرس و جو
    $where_sql = "";

    // اضافه کردن فیلتر بر اساس IDها، اگر موجود باشند
    if (!empty($source_ids)) {
        $source_ids_str = implode(',', array_map('intval', $source_ids));
        $where_sql .= " AND resource_id IN ($source_ids_str)";
    }

    // اضافه کردن فیلتر جستجو بر اساس کلمه کلیدی در عنوان، اگر موجود باشد
    if (!empty($search_keyword)) {
        $search_keyword = like_escape($search_keyword);
        $where_sql .= $wpdb->prepare(" AND title LIKE %s", '%' . $wpdb->esc_like($search_keyword) . '%');
    }

    // اجرای پرس و جوی تعداد کل رکوردها بدون مرتب سازی و لیمیت
    $final_return_record_count_query = "SELECT COUNT(*) FROM $table_name WHERE 1=1" . $where_sql;
    $final_return_count = $wpdb->get_var($final_return_record_count_query);

    // اضافه کردن مرتب سازی و صفحه بندی برای دیتای اصلی
    $data_sql = $where_sql . $wpdb->prepare(" ORDER BY pub_date DESC LIMIT %d OFFSET %d", $items_per_page, $offset);
    $final_return_data_query = "SELECT * FROM $table_name WHERE 1=1" . $data_sql;
    $final_return_data = $wpdb->get_results($final_return_data_query);

    // بازگرداندن نتایج
    return array($final_return_data, $final_return_count);
}


function get_sql_query_count($table_name, $source_ids, $search_keyword)
{
    global $wpdb;
    // تعریف شرط WHERE برای فیلتر کردن بر اساس ID و کلمه کلیدی
    $where_conditions = [];

    if (!empty($source_ids)) {
        $source_ids = implode(',', array_map('intval', $source_ids));
        $where_conditions[] = "resource_id IN ($source_ids)";
    }

    if (!empty($search_keyword)) {
        $search_keyword = $wpdb->esc_like($search_keyword);
        $search_keyword = '%' . $search_keyword . '%';
        $where_conditions[] = "description LIKE '$search_keyword'";
    }

    $where_sql = '';
    if (!empty($where_conditions)) {
        $where_sql = ' WHERE ' . implode(' AND ', $where_conditions);
    }

    // شمارش کل رکوردها با شرایط فیلتر
    $total_items_sql = "SELECT COUNT(*) FROM $table_name$where_sql";
    $total_items = $wpdb->get_var($total_items_sql);
    return $total_items;
}


function get_full_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    $requestUri = $_SERVER['REQUEST_URI'];
    return $protocol . $domainName . $requestUri;
}
