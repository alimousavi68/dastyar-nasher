<?php

require_once(__DIR__ . '/../../../../../wp-load.php');
require_once(__DIR__ . '/../../../../../wp-admin/includes/media.php');
require_once(__DIR__ . '/../../../../../wp-admin/includes/image.php');
require_once(__DIR__ . '/../../../../../wp-admin/includes/file.php');
require_once(__DIR__ . '/scraper_functions.php');


// Check if the request is an Ajax request
if (isset($_POST['action']) && !empty($_POST['action'])) {
    if ($_POST['action'] == 'publish_scraper') {
        $guid = $_POST['post_Guid'];
        $resource_id = $_POST['resource_id'];
        $publish_priority = isset($_POST['publish_priority']) ? $_POST['publish_priority'] : 'now';

        $response = scrape_and_publish_post($guid, $resource_id, $publish_priority);
        echo json_encode($response);
    }
}
add_action('admin_post_scrape_and_publish_post', 'scrape_and_publish_post');


// Function to scrape data from a given URL and create a new WordPress post
function scrape_and_publish_post($guid, $resource_id, $publish_priority)
{
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
        $encoded_url = $status_code['new_location'];

        $html_content = fetch_html_with_curl($encoded_url);
        if ($html_content == '') {
            insert_rss_report('درخواست واکشی یک پست', $encoded_url, 123, '0', 'خطایی با این کد صادر شد: فایل html خالی است');
        } else {
            $html = str_get_html($html_content);
        }

        // return array('code' => '301', 'new_location' => $headers['Location']);
    } else if ($result['code'] != '200') {
        insert_rss_report('درخواست واکشی یک پست', $encoded_url, 123, '0', ' خطایی با این کد صادر شده: ' . $status_code['code']);
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

        if ($thumbnail_url = $html->find($img_selector, 0)->src != null) {
            $thumbnail_url = $html->find($img_selector, 0)->src;
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
        if ($publish_priority == 'now') {
            $post_status = 'publish';
        } elseif ($publish_priority == 'pending') {
            $post_status = 'pending';
        }

        //error_log('post_status here:' . $post_status);

        date_default_timezone_set('Asia/Tehran');

        // Check if all required elements are found
        if ($title && $excerpt && $content && $thumbnail_url) {
            $random_interval = rand(300, 600);
            $publish_time = time() + $random_interval;

            // Prepare data for creating a WordPress post
            $post_data = array(
                'post_title' => $title,
                'post_content' => $content,
                'post_excerpt' => $excerpt,
                'post_status' => $post_status,
                'post_date' => date('Y-m-d H:i:s', $publish_time) // زمان انتشار
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
                ob_flush(); // تخلیه خروجی (اگر نیاز به تخلیه باشد)
            } catch (Exception $e) {
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


                $attachment_id = media_sideload_image($thumbnail_url, $post_id, 'thumbnail', 'id');

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
                // echo '<script>window.open("' . admin_url('post.php?action=edit&post=' . $post_id) . '", "_blank", "noopener,noreferrer");</script>';
                // wp_safe_redirect(add_query_arg('success', 'true', wp_get_referer()));
                // exit;

                // add to wp_pc_post_schedule table in wordpress database a new record with $post_id and$publish_priority values

                //error_log('my post prority: ' . $publish_priority);

                if ($publish_priority != 'now' and $publish_priority != 'pending') {
                    //error_log('im here . in to if : ' . $publish_priority);

                    global $wpdb;
                    $table_name = $wpdb->prefix . 'pc_post_schedule';
                    $wpdb->insert($table_name, array('post_id' => $post_id, 'publish_priority' => $publish_priority));
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
}
