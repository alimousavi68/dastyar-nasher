<?php

require_once ABSPATH . 'wp-admin/includes/file.php';
$wp_load_path = get_home_path() . 'wp-load.php';
// اگر فایل wp-load.php در مسیر محاسبه شده وجود داشته باشد، آن را لود کنید
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    exit;
}

// Hook into the admin menu
add_action('admin_menu', 'custom_rss_parser_menu');


// Function to add menu and page
function custom_rss_parser_menu()
{
    add_menu_page(
        'آخرین فیدهای دستیار',
        'دستیار',
        'edit_posts',
        'publisher_copoilot',
        'publisher_copoilot_callback',
        'dashicons-image-filter',
        5
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
    if ($action == 'delete_all') {
        remove_all_feed_on_feeds_table();
    }

    if ($action == 'update_feeds') {
        do_action('custom_rss_parser_event');
        wp_safe_redirect(add_query_arg('success', 'true', wp_get_referer()));
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

    <div class="table-frame border-lg-1 rounded-4 p-2 p-lg-4 mt-4 me-3">
        <!-- filter box -->
        <div class="filter-box mb-3">
            <div class="flex-container">

                <div class="box1 d-flex  align-items-end gap-2">
                    <img src="<?php echo COP_PLUGIN_URL; ?>/assets/images/fee-list-logo.svg" width="50px" height="50px"
                        alt="">
                    <span class="d-flex d-lg-none fs-4 fw-bolder"> فــیدها</span>
                </div>

                <div class="box2 mt-3 mt-lg-0 pe-0 pe-lg-1">
                    <?php

                    
                    ?>
                    <form action="<?php echo get_full_url() ; ?>" method="post">
                        <!-- serach input -->
                        <div class="input-group">

                            <div class="form-floating ">
                                <input type="text" class="form-control border" id="search_keyword" name="search_keyword"
                                    value="<?php echo isset($_POST['search_keyword']) ? $_POST['search_keyword'] : '' ?>"
                                    placeholder="کلمه مورد نظر را وارد کنید:">
                                <label for="search_keyword d-flex d-none">جستجو...</label>
                            </div>

                            <div class="form-floating">
                                <select class="form-control border rounded-0  form-select" id="category_list"
                                    name="category_list" multiple="multiple" aria-label="دسته مورد نظر را انتخاب کنید:">

                                    <optgroup label="سرویس ها">
                                        <!-- <option value="1">سیاسی</option>
                                        <option value="2">اجتماعی</option>
                                        <option value="3">ورزشی</option>
                                        <option value="1">سیاسی</option>
                                        <option value="2">اجتماعی</option>
                                        <option value="3">ورزشی</option>
                                        <option value="1">سیاسی</option>
                                        <option value="2">اجتماعی</option>
                                        <option value="3">ورزشی</option>
                                        <option value="1">سیاسی</option>
                                        <option value="2">اجتماعی</option>
                                        <option value="3">ورزشی</option> -->
                                    </optgroup>
                                </select>
                            </div>

                            <div class="form-floating">
                                <select class=" form-control border rounded-0 form-select" id="resource_list"
                                    name="resource_list[]" multiple="multiple" aria-label="منبع مورد نظر را انتخاب کنید:">

                                    <optgroup label="منابع خبری">
                                        <?php
                                        foreach (get_all_source_name() as $source_name): ?>
                                            <option value="<?php echo $source_name->resource_id; ?>" <?php
                                               echo (isset($_POST['resource_list']) && in_array($source_name->resource_id, $_POST['resource_list'])) ? esc_attr('selected') : '' ?>>
                                                <?php echo $source_name->resource_title; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>

                            <button type="button" id="clear_filters"
                                class="btn btn-outline-secondary btn-large border rounded-0" data-bs-toggle="tooltip"
                                data-bs-placement="bottom" data-bs-custom-class="custom-tooltip"
                                data-bs-title="پاکسازی فیلترها">
                                <?php echo $icon_eraser; ?>
                            </button>


                            <button type="submit" class="btn btn-primary btn-large" data-bs-toggle="tooltip"
                                data-bs-placement="bottom" data-bs-custom-class="custom-tooltip"
                                data-bs-title="اعمال فیلترها">

                                <?php echo $icon_search;
                                // echo 'x2v: ' . $icon_search;
                                ?>
                            </button>

                        </div>
                    </form>

                </div>
                <div class="box3 d-flex gap-1 justify-content-end px-0">

                    <a href="admin.php?page=publisher_copoilot&action=update_feeds"
                        class="btn btn-large btn-outline-primary d-flex align-items-center" data-bs-toggle="tooltip"
                        data-bs-placement="bottom" data-bs-custom-class="custom-tooltip" data-bs-title="به روز رسانی فیدها">
                        <?php echo $icon_arrow_repeat; ?>
                    </a>
                    <a href="admin.php?page=publisher_copoilot&action=delete_all"
                        class="btn btn-large btn-outline-primary d-flex align-items-center" data-bs-toggle="tooltip"
                        data-bs-placement="bottom" data-bs-custom-class="custom-tooltip" data-bs-title="حذف همه فیدها">
                        <?php echo $icon_trash; ?>

                    </a>
                </div>
            </div>

            <?php
            global $wpdb;
            $table_name = $wpdb->prefix . 'custom_rss_items';
            // Fetch items from the database with pagination
            $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name ORDER BY pub_date DESC");
            ?>
            <div class="d-flex justify-content-center text-center gap-2 mt-1">
                <span class="text-secondary" style="font-size: 11px;"> <?php echo $total_items; ?> مورد </span>
                <span> - </span>
                <span class="text-secondary" style="font-size: 11px;"><?php
                if ($i8_next_scrap_all_resource_feed_time = get_option('i8_next_scrap_all_resource_feed_time', '')) {
                    date_default_timezone_set('Asia/Tehran');
                    echo date('H:i', $i8_next_scrap_all_resource_feed_time);
                }
                ?> :واکشی بعدی </span>
            </div>

        </div>

        <div class="table table-hover px-0 px-lg-3 ">
            <!-- table header -->
            <div class="table-header row d-none d-md-flex border-bottom pb-2">
                <div class="col col-auto fw-bold justify-content-right text-right p-0"> </div>
                <div class="col col-8  col-md-1 align-items-center fw-bold d-none d-md-flex px-0">
                    <span class="item_counter">#</span>
                    زمان‌انتشار
                </div>
                <div class="col col-12 col-md-4 fw-bold text-center justify-content-center align-items-center  d-md-flex">
                    عنوان</div>
                <div
                    class="col col-4  col-md-2 fw-bold text-center justify-content-center align-items-center  d-none d-md-flex">
                    منبع</div>
                <div
                    class="col col-12 col-md-1 fw-bold text-center justify-content-center align-items-center  d-none d-md-flex">
                    دسته‌بندی</div>
                <div
                    class="col col-12 col-md-4 fw-bold text-center justify-content-center align-items-center  d-none d-md-flex">
                    اکشن ها</div>
            </div>

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

    // // Fetch total items for pagination
    
    

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

                    // fetch and set time zome feed_time and date
                    $dateTime = new DateTime($item->pub_date, new DateTimeZone('GMT'));
                    $dateTime->setTimezone(new DateTimeZone('Asia/Tehran'));
                    $pub_date = \i8_jDateTime::convertFormatToFormat('d / m', 'm-d', $dateTime->format('m-d'));
                    $pub_time = \i8_jDateTime::convertFormatToFormat('H:i', 'H:i', $dateTime->format('H:i'));

                    $resource_id = esc_attr($item->resource_id);
                    $admin_url = esc_url(get_admin_url() . 'images/wpspin_light-2x.gif');
                    ?>

                    <!-- Table Item -->
                    <div id="item-<?php echo $item_id; ?>" class="row border-bottom py-2 d-flex align-items-center table-item">

                        <div
                            class="col col-3  col-lg-1 col-md-2 text-center d-md-flex bg-transparent feed-item-title  align-items-center d-flex">
                            <span class="item_counter"><?php echo $row_counter; ?></span>
                            <div class="d-flex flex-column ">
                                <div class="fs-3 text-danger"><?php echo $pub_time; ?></div>
                                <div class="text-secondary fs-6 pub_time"><?php echo $pub_date; ?></div>
                            </div>
                        </div>

                        <div class="col col-9 col-lg-4 col-md-5 d-md-flex justify-content-start bg-transparent">
                            <a href="<?php echo $item_guid; ?>" target="_blank"><?php echo $item_title; ?></a>
                            <?php // echo $icon_check_all; ?>
                        </div>

                        <div
                            class="col col-6  col-md-2 text-center justify-content-center d-md-flex align-items-center gap-1 item-meta-data bg-transparent resource_name">
                            <?php echo $resource_name; ?>
                            <!-- <img src="<?php echo (DASTYAR_ASSETS_IMAGES . 'icons/icon_geo_alt.svg'); ?>"> -->
                        </div>

                        <div
                            class="col col-6 col-md-1 text-center justify-content-center d-md-flex align-items-center gap-1 item-meta-data bg-transparent resource_cat">
                            <!-- <img src="<?php echo (DASTYAR_ASSETS_IMAGES . 'icons/icon_tag.svg'); ?>"> -->
                            -
                        </div>

                        <!-- Action Buttons -->
                        <div
                            class="col col-12 col-md-4  text-center d-flex flex-wrap justify-content-center fw-bold d-md-flex gap-2 bg-transparent action-bar">

                            <a href="<?php echo $item_guid; ?>" target="_blank"
                                class="scrape-link col btn py-0 rounded-pill btn-outline-primary" data-bs-toggle="tooltip"
                                data-bs-placement="top" data-bs-custom-class="custom-tooltip" data-bs-title="نمایش خبر در منبع"
                                title="">
                                <img src="<?php echo (DASTYAR_ASSETS_IMAGES . 'icons/icon_eyeglasses.svg'); ?>">
                            </a>

                            <a class="scrape-link col btn py-0 rounded-pill btn-outline-primary" data-bs-toggle="tooltip"
                                data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                data-bs-title="واکشی / در انتظار بازبینی" id="scraper-link-<?php echo $item->id; ?>"
                                data-id="<?php echo $item->id; ?>" data-guid="<?php echo $item_guid; ?>" data-priority="pending"
                                data-resource-id="<?php echo $resource_id; ?>">
                                <img src="<?php echo $admin_url; ?>" class="i8-loader-gif"
                                    style="display:none;position:absolute;left:50%;z-index:100;" />
                                    <img src="<?php echo (DASTYAR_ASSETS_IMAGES . 'icons/icon_cloud_arrow_down.svg'); ?>">
                            </a>

                            <a class="scrape-link col btn py-0 rounded-pill btn-outline-primary" data-bs-toggle="tooltip"
                                data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                data-bs-title="واکشی / انتشار با تاخیر" id="scraper-link-<?php echo $item->id; ?>"
                                data-id="<?php echo $item->id; ?>" data-guid="<?php echo $item_guid; ?>" data-priority="now"
                                data-resource-id="<?php echo $resource_id; ?>">
                                <img src="<?php echo $admin_url; ?>" class="i8-loader-gif"
                                    style="display:none;position:absolute;left:50%;z-index:100;" />
                                <img src="<?php echo (DASTYAR_ASSETS_IMAGES . 'icons/icon_send_arrow_down.svg'); ?>">
                            </a>

                            <a class="scrape-link col btn py-0 rounded-pill btn-outline-danger" data-bs-toggle="tooltip"
                                data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                data-bs-title=" واکشی / زمانبندی با اولویت بالا" id="scraper-link-<?php echo $item->id; ?>"
                                data-id="<?php echo $item->id; ?>" data-guid="<?php echo $item_guid; ?>" data-priority="high"
                                data-resource-id="<?php echo $resource_id; ?>">
                                <img src="<?php echo (DASTYAR_ASSETS_IMAGES . 'icons/icon_hourglass_top.svg'); ?>">
                            </a>

                            <a class="scrape-link col btn py-0 rounded-pill btn-outline-warning" data-bs-toggle="tooltip"
                                data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                data-bs-title="واکشی / زمانبندی با اولویت متوسط" id="scraper-link-<?php echo $item->id; ?>"
                                data-id="<?php echo $item->id; ?>" data-guid="<?php echo $item_guid; ?>" data-priority="medium"
                                data-resource-id="<?php echo $resource_id; ?>">
                                <img src="<?php echo (DASTYAR_ASSETS_IMAGES . 'icons/icon_hourglass_top.svg'); ?>">
                            </a>

                            <a class="scrape-link col btn py-0 rounded-pill btn-outline-success" data-bs-toggle="tooltip"
                                data-bs-placement="top" data-bs-custom-class="custom-tooltip"
                                data-bs-title="واکشی / زمانبدی با اولویت کم" id="scraper-link-<?php echo $item->id; ?>"
                                data-id="<?php echo $item->id; ?>" data-guid="<?php echo $item_guid; ?>" data-priority="low"
                                data-resource-id="<?php echo $resource_id; ?>">
                                <img src="<?php echo (DASTYAR_ASSETS_IMAGES . 'icons/icon_hourglass_top.svg'); ?>">
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

        <?php
                        }
                        ?>


    <!-- include Js scripts -->

    <script src="<?php echo COP_PLUGIN_URL; ?>/assets/js/bootstrap.min.js"></script>
    <script src="<?php echo COP_PLUGIN_URL; ?>/assets/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo COP_PLUGIN_URL; ?>/assets/js/popper.min.js"></script>
    <script src="<?php echo COP_PLUGIN_URL; ?>/assets/js/select2.min.js"></script>

    <script src="<?php echo COP_PLUGIN_URL; ?>/assets/js/custom_js.js"></script>

    <!-- Send Scrpe Request -->
    <script>

        jQuery(document).ready(function ($) {
            var scrapeLinks = document.querySelectorAll(".scrape-link");
            scrapeLinks.forEach(function (link) {
                link.addEventListener("click", function (e) {
                    var post_Guid = this.getAttribute("data-guid");
                    var resource_id = this.getAttribute("data-resource-id");
                    var post_id = this.getAttribute("data-id");
                    var publish_priority = this.getAttribute("data-priority");

                    // style effect for this item
                    var scrapeImg = document.querySelectorAll("#scraper-link-" + post_id + ">img");
                    scrapeImg.forEach(function (link) {
                        link.style.display = 'inline-block';
                    });
                    document.getElementById("item-" + post_id).classList.add("blinking");


                    //send ajax request to scrape the post
                    $.ajax({
                        url: '<?php echo DASTYAR_URL . "/inc/scraper/index.php"; ?>',
                        type: 'POST',
                        data: {
                            action: 'publish_scraper',
                            post_Guid: post_Guid,
                            resource_id: resource_id,
                            publish_priority: publish_priority
                        },
                        success: function (response) {
                            var response = JSON.parse(response);

                            if (response.status === true) {
                                Toast.fire({
                                    icon: "success",
                                    title: response.message
                                });
                            } else {
                                Toast.fire({
                                    icon: "error",
                                    title: response.message
                                });
                            }

                            var scrapeImg = document.querySelectorAll("#scraper-link-" + post_id + ">img.i8-loader-gif");
                            scrapeImg.forEach(function (link) {
                                link.style.display = 'none';
                            });
                            document.getElementById("item-" + post_id).classList.remove("blinking");
                        }
                    });
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
    $sql = "";

    // اضافه کردن فیلتر بر اساس IDها، اگر موجود باشند
    if (!empty($source_ids)) {
        $source_ids_str = implode(',', array_map('intval', $source_ids));
        $sql .= $wpdb->prepare(" AND resource_id IN ($source_ids_str)");
    }

    // اضافه کردن فیلتر جستجو بر اساس کلمه کلیدی در عنوان، اگر موجود باشد
    if (!empty($search_keyword)) {
        $search_keyword = like_escape($search_keyword);
        $sql .= $wpdb->prepare(" AND title LIKE %s", '%' . $wpdb->esc_like($search_keyword) . '%');
    }

    // اضافه کردن مرتب سازی و صفحه بندی
    $sql .= $wpdb->prepare(" ORDER BY pub_date DESC LIMIT %d OFFSET %d", $items_per_page, $offset);

    $final_return_data_query = "SELECT * FROM $table_name WHERE 1=1" . $sql;
    $final_return_record_count_query = "SELECT COUNT(*) FROM $table_name WHERE 1=1" . $sql;


    $final_return_data = $wpdb->get_results($final_return_data_query);
    $final_return_count = $wpdb->get_var($final_return_record_count_query);

    // اجرای پرس و جو و بازگرداندن نتایج
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
