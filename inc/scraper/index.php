<?php
defined('ABSPATH') || exit;

require_once(__DIR__ . '/scraper_functions.php');

add_action('wp_ajax_publish_scraper', 'dastyar_publish_scraper_ajax_handler');

function dastyar_publish_scraper_ajax_handler() {
    ob_start(); // Start output buffering to capture any accidental warnings/notices

    // Validate security nonce
    check_ajax_referer('dastyar_publish_scraper_nonce', 'security');

    // Check capability
    if (!current_user_can('edit_posts')) {
        ob_end_clean(); // فقط بافر خودمان را پاک می‌کنیم
        wp_send_json(array('status' => false, 'message' => 'شما دسترسی کافی ندارید.'));
    }

    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $guid = isset($_POST['post_Guid']) ? esc_url_raw($_POST['post_Guid']) : '';
    $resource_id = isset($_POST['resource_id']) ? intval($_POST['resource_id']) : 0;
    $publish_priority = isset($_POST['publish_priority']) ? sanitize_text_field($_POST['publish_priority']) : 'now';

    if (empty($guid) || empty($resource_id) || empty($item_id)) {
        ob_end_clean(); // فقط بافر خودمان را پاک می‌کنیم
        wp_send_json(array('status' => false, 'message' => 'پارامترهای ارسالی نامعتبر هستند.'));
    }

    // لود پیش‌نیازهای مدیا به صورت مستقیم
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');

    $response = scrape_and_publish_post($guid, $resource_id, $publish_priority);

    // فقط بافری که خودمان باز کردیم را می‌بندیم - while(ob_get_level>0) اشتباه است و بافرهای هسته وردپرس را هم از بین می‌برد
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    wp_send_json($response);
}


// اکشن AJAX برای حذف تمام فیدها
add_action('wp_ajax_dastyar_delete_all_feeds', 'dastyar_delete_all_feeds_ajax_handler');
function dastyar_delete_all_feeds_ajax_handler() {
    check_ajax_referer('cop_delete_all_nonce', 'security');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('شما دسترسی کافی ندارید.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_rss_items';
    $result = $wpdb->query("DELETE FROM $table_name");

    if ($result !== false) {
        wp_send_json_success(array('message' => 'تمامی فیدها با موفقیت حذف شدند.'));
    } else {
        wp_send_json_error('خطا در حذف فیدها از دیتابیس.');
    }
}

// اکشن AJAX برای به‌روزرسانی تمام فیدها به صورت مستقیم و همزمان (جهت ثبات بالا و رفع قفل شدن صف)
add_action('wp_ajax_dastyar_update_all_feeds', 'dastyar_update_all_feeds_ajax_handler');
function dastyar_update_all_feeds_ajax_handler() {
    check_ajax_referer('cop_update_feeds_nonce', 'security');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('شما دسترسی کافی ندارید.');
    }

    try {
        global $wpdb;
        $table_name = $wpdb->prefix . 'custom_resource_details';
        $resources = $wpdb->get_results("SELECT resource_id FROM $table_name");

        if (file_exists(dirname(__DIR__) . '/crons/crons.php')) {
            require_once(dirname(__DIR__) . '/crons/crons.php');
        }

        $total_new_items = 0;
        if ($resources) {
            foreach ($resources as $resource) {
                $feed_id = intval($resource->resource_id);
                $new_items = i8_crawl_single_feed($feed_id);
                $total_new_items += intval($new_items);
            }
        }

        wp_send_json_success(array(
            'message' => 'به‌روزرسانی با موفقیت به صورت مستقیم انجام شد.',
            'new_items_count' => $total_new_items
        ));
    } catch (Throwable $e) {
        wp_send_json_error('خطای سیستم: ' . $e->getMessage());
    }
}




// Function to scrape data from a given URL and create a new WordPress post
function scrape_and_publish_post($guid, $resource_id, $publish_priority)
{
    $html = null;
    try {
        // دریافت مقادیر از فرم
    $title_selector = get_resource_data($resource_id, 'title_selector');
    $img_selector = get_resource_data($resource_id, 'img_selector');
    $lead_selector = get_resource_data($resource_id, 'lead_selector');
    $body_selector = get_resource_data($resource_id, 'body_selector');
    $source_root_link = get_resource_data($resource_id, 'source_root_link');

    // $bup_date_selector = get_resource_data($resource_id, 'bup_date_selector');
    // $category_selector = get_resource_data($resource_id, 'category_selector');
    // $tags_selector = get_resource_data($resource_id, 'tags_selector');
    // $escape_elements = get_resource_data($resource_id, 'escape_elements');
    // $source_feed_link = get_resource_data($resource_id, 'source_feed_link');
    // $need_to_merge_guid_link = get_resource_data($resource_id, 'need_to_merge_guid_link');

    $url = $guid . '';

    // encode persian chracter to allowed url with %
    $encoded_url = encode_persian_chracter_allowed_url($url);

    //check is 200 status code or 301 or 404 or.. 
    $result = check_post_link_status($encoded_url);

    if ($result['code'] == 301 || $result['code'] == 302 || $result['code'] == '301-like' || $result['code'] == '301-in-html') {
        insert_rss_report('درخواست واکشی یک پست', $encoded_url, 123, '0', 'خطای ریدایرکت ۳۰۱ صادر شد');
        $encoded_url = $result['new_location'];

        $html_content = fetch_html_with_curl($encoded_url);
        if ($html_content == '') {
            insert_rss_report('درخواست واکشی یک پست', $encoded_url, 123, '0', 'خطایی با این کد صادر شد: فایل html خالی است');
        } else {
            $html = str_get_html($html_content);
        }

        // return array('code' => '301', 'new_location' => $headers['Location']);
    } else if ($result['code'] != '200') {
        insert_rss_report('درخواست واکشی یک پست', $encoded_url, 123, '0', ' خطایی با این کد صادر شده: ' . $result['code']);
    } else {
        $html_content = fetch_html_with_curl($url);
        if ($html_content == '') {
            insert_rss_report('درخواست واکشی یک پست', $encoded_url, 123, '0', 'خطایی با این کد صادر شد: فایل html خالی است');
        } else {
            $html = str_get_html($html_content);
        }
    }

    // Alternative way for reCheck url with remove www. from url
    if ($html == '') {
        // Remove "www." only if it comes after "http://" or "https://"
        $url = $guid;
        $url = preg_replace('/^(https?:\/\/)www\./', '$1', $url);

        // encode persian chracter to allowed url with %
        $encoded_url = encode_persian_chracter_allowed_url($url);

        // Fetch the HTML content
        $html_content = fetch_html_with_curl($url);
        if ($html_content == '') {
            insert_rss_report('درخواست واکشی یک پست', $encoded_url, 123, '0', 'خطایی با این کد صادر شد: فایل html خالی است');
        } else {
            $html = str_get_html($html_content);
        }
    }


    // Check if HTML is successfu   lly loaded
    if ($html) {

        // Find and extract the required elements
        
        // انتخاب المان h1 با کلاس "title" و مشخصه itemprop="headline"
        $title_element = $html->find($title_selector, 0);

        // بررسی وجود المان قبل از استفاده از تابع find()
        if ($title_element) {
            // دریافت  متن موجود در المان
            $title = $title_element->plaintext;
            
        } else {
            // در صورت عدم وجود المان، مقدار پیشفرض یا اقدام مناسب دیگر
            $title = '';
            // insert rss report error for this section
            $report_id = insert_rss_report(
                'درخواست واکشی یک پست',
                $encoded_url,
                123,
                '0',
                'عنوان پیدا نشد'
            );
        }

        if ($html->find($lead_selector, 0) != null) {
            $excerpt = $html->find($lead_selector, 0);
            $excerpt = trim($excerpt->plaintext);
        } else {
            $excerpt = '';
            // insert rss report error for this section
            $report_id = insert_rss_report(
                'درخواست واکشی یک پست',
                $encoded_url,
                123,
                '0',
                'لید پیدا نشد'
            );
        }

        if ($html->find($body_selector, 0) != null) {
            $content = $html->find($body_selector, 0);
            $content = clear_not_allowed_tags($content->innertext, $source_root_link);
        } else {
            $content = '';
            // insert rss report error for this section
            $report_id = insert_rss_report(
                'درخواست واکشی یک پست',
                $encoded_url,
                123,
                '0',
                'بدنه پست پیدا نشد'
            );
        }

        $img_element = $html->find($img_selector, 0);
        if ($img_element && $img_element->src != null) {
            $thumbnail_url = $img_element->src;
        } else {
            $thumbnail_url = '';
            // insert rss report error for this section
            $report_id = insert_rss_report(
                'درخواست واکشی یک پست',
                $encoded_url,
                123,
                '0',
                'عکس پست پیدا نشد'
            );
        }


        $post_status = 'draft';
        if ($publish_priority == 'pending') {
            $post_status = 'pending';
        }

        //error_log('post_status here:' . $post_status);

        // Check if all required elements are found
        if ($title && $excerpt && $content && $thumbnail_url) {
            if ($publish_priority == 'now') {
                $publish_time = time();
            } else {
                $random_interval = rand(300, 600);
                $publish_time = time() + $random_interval;
            }
            $tz = wp_timezone();
            $date = new DateTime('@' . $publish_time);
            $date->setTimezone($tz);
            $post_date_str = $date->format('Y-m-d H:i:s');

            // Prepare data for creating a WordPress post
            $post_data = array(
                'post_title' => $title,
                'post_content' => $content,
                'post_excerpt' => $excerpt,
                'post_status' => $post_status,
                'post_date' => $post_date_str // زمان انتشار
            );

            // درست کردن پست در وردپرس
            // try {
            //     $post_id = wp_insert_post($post_data);
            //     ob_flush(); // تخلیه خروجی
            // } catch (Exception $e) {
            //     return (array('status' => false, 'message' => 'Failed to insert the post. Error: ' . $e->getMessage()));
            //     ob_flush(); // تخلیه خروجی
            // }

            // درست کردن پست در وردپرس
            try {
                ob_start(); // اطمینان از اینکه بافر خروجی شروع شده است
                $post_id = wp_insert_post($post_data);
                ob_end_clean(); // تمیز کردن و بستن بافر بدون خروجی به کاربر

                if (is_wp_error($post_id)) {
                    $error_msg = $post_id->get_error_message();
                    $report_id = insert_rss_report(
                        'درخواست واکشی یک پست',
                        $encoded_url,
                        123,
                        '0',
                        'خطایی در حین ایجاد پست پیش آمده است: ' . $error_msg
                    );
                    return array('status' => false, 'message' => 'خطایی در حین ایجاد پست پیش آمده است: ' . $error_msg);
                }
            } catch (Exception $e) {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                // insert rss report error for this section
                $report_id = insert_rss_report(
                    'درخواست واکشی یک پست',
                    $encoded_url,
                    123,
                    '0',
                    'خطایی در حین ایجاد پست پیش آمده است..' . $e->getMessage()
                );
                return array('status' => false, 'message' => 'خطایی در حین ایجاد پست پیش آمده است..' . $e->getMessage());
            }

            // Upload and set the featured image
            if ($post_id && function_exists('media_sideload_image')) {
                $thumbnail_url = complete_url($thumbnail_url, $source_root_link);

                ob_start();
                $attachment_id = media_sideload_image($thumbnail_url, $post_id, 'thumbnail', 'id');
                ob_end_clean();

                if (!is_wp_error($attachment_id)) {
                    set_post_thumbnail($post_id, $attachment_id);
                } elseif (is_wp_error($attachment_id)) {
                    // insert rss report error for this section
                    $report_id = insert_rss_report(
                        'درخواست واکشی یک پست',
                        $encoded_url,
                        123,
                        '0',
                        'خطایی در حین آپلود عکس پیش آمده است. لطفا با پشتیبانی تماس بگیرید.',
                    );
                    return (array('status' => false, 'message' => 'خطایی در حین آپلود عکس پیش آمده است: ' . $attachment_id->get_error_message()));
                }
            } elseif (!function_exists('media_sideload_image')) {
                // return (array('status' => false, 'message' => 'media_sideload_image() function is not available.'));
                // //error_log('media_sideload_image() function is not available.');
                // insert rss report error for this section
                $report_id = insert_rss_report(
                    'درخواست واکشی یک پست',
                    $encoded_url,
                    123,
                    '0',
                    'media_sideload_image() function is not available.'
                );
            }

            // Output success or failure message
            if ($post_id) {
                // ذخیره کردن لینک اصلی فید برای رهگیری وضعیت
                update_post_meta($post_id, '_dastyar_feed_guid', $guid);

                // echo '<script>window.open("' . admin_url('post.php?action=edit&post=' . $post_id) . '", "_blank", "noopener,noreferrer");</script>';
                // wp_safe_redirect(add_query_arg('success', 'true', wp_get_referer()));
                // exit;

                // add to wp_pc_post_schedule table in wordpress database a new record with $post_id and$publish_priority values

                //error_log('my post prority: ' . $publish_priority);

                if ($publish_priority == 'now') {
                    // انتشار غیرهمزمان در پس‌زمینه برای جلوگیری از خطای Timeout/Invalid Response در AJAX
                    global $wpdb;
                    $table = $wpdb->prefix . 'pc_post_schedule';
                    $publish_time = time() + 3;
                    
                    if (function_exists('as_schedule_single_action')) {
                        $action_id = as_schedule_single_action($publish_time, 'i8_action_publish_specific_post', array('post_id' => $post_id), 'i8_post_publisher');
                        
                        $max_order = $wpdb->get_var("SELECT MAX(sort_order) FROM $table WHERE status IN ('queued', 'scheduled')");
                        $new_order = intval($max_order) + 1;
                        
                        $wpdb->insert(
                            $table,
                            array(
                                'post_id' => $post_id,
                                'publish_priority' => 'high',
                                'status' => 'scheduled',
                                'sort_order' => $new_order,
                                'scheduled_for' => gmdate('Y-m-d H:i:s', $publish_time),
                                'as_action_id' => $action_id,
                                'created_at' => current_time('mysql')
                            )
                        );
                    } else {
                        // fallback
                        wp_update_post(array(
                            'ID' => $post_id,
                            'post_status' => 'publish',
                            'post_date' => current_time('mysql'),
                            'post_date_gmt' => current_time('mysql', 1)
                        ));
                    }
                } elseif ($publish_priority != 'pending') {
                    // استفاده از تابع زمان‌بندی بومی افزونه به جای درج مستقیم جهت ثبت در صف مدرن Action Scheduler
                    add_post_to_post_schedule_table($post_id, $publish_priority);
                }

                return (array('status' => true, 'message' => 'پست منتشر شد'));
            } else {
                $report_id = insert_rss_report(
                    'ایجاد پست جدید',
                    $encoded_url,
                    123,
                    '0',
                    'خطا در ایجاد پست'
                );
                return (array('status' => false, 'message' => 'خطا در ایجاد پست'));
            }
        } else {

            $err_element = '';
            if ($title == '') {
                $err_element .= ' عنوان/ ';
            }
            if ($excerpt == '') {
                $err_element .= 'خلاصه/ ';
            }
            if ($content == '') {
                $err_element .= 'متن/ ';
            }
            if ($thumbnail_url == '') {
                $err_element .= 'عکس ';
            }

            $report_id = insert_rss_report(
                'درخواست واکشی یک پست',
                $encoded_url,
                123,
                '0',
                'خطا در واکشی المان ' . $err_element
            );

            return (array('status' => false, 'message' => 'خطا در واکشی المان ' . $err_element));
        }
    } else {
        $report_id = insert_rss_report(
            'درخواست واکشی یک پست',
            $encoded_url,
            123,
            '0',
            'خطا در بارگیری HTML از لینک'
        );
        return (array('status' => false, 'message' => 'Failed to load HTML from the URL.'));
    }
    } finally {
        if ($html) {
            if (method_exists($html, 'clear')) {
                $html->clear();
            }
            unset($html);
        }
    }
}
