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
    $escape_elements = get_resource_data($resource_id, 'escape_elements');
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
            $html = true; // Flag for legacy checks
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
            $html = true; // Flag for legacy checks
        }
    }


    // Check if HTML is successfu   lly loaded
    if ($html) {

        // ایجاد DOMDocument برای استفاده از XPath بجای Simple HTML DOM
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $content_type_handled_html = mb_convert_encoding($html_content, 'HTML-ENTITIES', 'UTF-8');
        @$dom->loadHTML($content_type_handled_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        // Find and extract the required elements
        $title = trim(strip_tags(dastyar_extract_by_selector($xpath, $title_selector)));
        
        if (empty($title)) {
            $report_id = insert_rss_report(
                'درخواست واکشی یک پست',
                $encoded_url,
                123,
                '0',
                'عنوان پیدا نشد'
            );
        }

        $excerpt = trim(strip_tags(dastyar_extract_by_selector($xpath, $lead_selector)));
        
        if (empty($excerpt) && !empty($lead_selector)) {
            $report_id = insert_rss_report(
                'درخواست واکشی یک پست',
                $encoded_url,
                123,
                '0',
                'لید پیدا نشد'
            );
        }

        // 1. اول تصویر شاخص را استخراج می‌کنیم (تا بر اثر حذف المان‌ها از بین نرود)
        $thumbnail_url = '';
        $img_xpath_query = dastyar_css_to_xpath($img_selector);
        if (!empty($img_xpath_query)) {
            $nodes = $xpath->query($img_xpath_query);
            if ($nodes && $nodes->length > 0) {
                $img_node = $nodes->item(0);
                $tag_name = strtolower($img_node->nodeName);
                if ($tag_name === 'meta') {
                    $thumbnail_url = trim($img_node->getAttribute('content'));
                } elseif ($tag_name === 'img') {
                    $thumbnail_url = trim($img_node->getAttribute('src'));
                    if (empty($thumbnail_url)) $thumbnail_url = trim($img_node->getAttribute('data-src'));
                    if (empty($thumbnail_url)) $thumbnail_url = trim($img_node->getAttribute('data-lazy-src'));
                    if (empty($thumbnail_url)) {
                        $srcset = trim($img_node->getAttribute('srcset'));
                        if (!empty($srcset)) {
                            $srcset_parts = preg_split('/\s+/', $srcset);
                            if (!empty($srcset_parts[0])) $thumbnail_url = $srcset_parts[0];
                        }
                    }
                } else {
                    $sub_imgs = $xpath->query('.//img', $img_node);
                    if ($sub_imgs && $sub_imgs->length > 0) {
                        $sub_img = $sub_imgs->item(0);
                        $thumbnail_url = trim($sub_img->getAttribute('src'));
                        if (empty($thumbnail_url)) $thumbnail_url = trim($sub_img->getAttribute('data-src'));
                        if (empty($thumbnail_url)) $thumbnail_url = trim($sub_img->getAttribute('data-lazy-src'));
                    }
                }
            }
        }
        
        if (empty($thumbnail_url) && !empty($img_selector)) {
            $report_id = insert_rss_report(
                'درخواست واکشی یک پست',
                $encoded_url,
                123,
                '0',
                'عکس پست پیدا نشد'
            );
        }

        // 2. دوم، اعمال Escape Elements (حذف المان‌های ناخواسته از DOM)
        $flatten_escape = function($input) use (&$flatten_escape) {
            $result = array();
            if (empty($input)) return $result;
            $items = is_array($input) ? $input : array($input);
            foreach ($items as $item) {
                if (is_array($item)) {
                    $result = array_merge($result, $flatten_escape($item));
                    continue;
                }
                if (!is_string($item)) continue;
                $item = trim($item);
                $item = html_entity_decode($item, ENT_QUOTES, 'UTF-8');
                $item = trim($item, "'\" \t\n\r\0\x0B");
                if (empty($item)) continue;
                
                if (substr($item, 0, 1) === '[' && substr($item, -1) === ']') {
                    $decoded = json_decode($item, true);
                    if (is_array($decoded)) {
                        $result = array_merge($result, $flatten_escape($decoded));
                        continue;
                    }
                    // If json_decode fails, it is likely a CSS attribute selector like [class*="ad"]
                    $result[] = $item;
                    continue;
                }
                if (strpos($item, "\n") !== false) {
                    $lines = explode("\n", $item);
                    $result = array_merge($result, $flatten_escape($lines));
                    continue;
                }
                $result[] = $item;
            }
            return array_values(array_unique(array_filter($result)));
        };

        $escape_selectors = $flatten_escape($escape_elements);

        if (!empty($escape_selectors)) {
            $removed_count = 0;
            foreach ($escape_selectors as $esc_selector) {
                $esc_selector = trim($esc_selector);
                if (empty($esc_selector)) continue;
                
                $esc_xpath = dastyar_css_to_xpath($esc_selector);
                if (empty($esc_xpath)) continue;
                
                // اجرای کوئری اصلی و کوئری نسبی جهت اطمینان از یافتن نود در تمام سطوح DOM
                $esc_nodes = @$xpath->query($esc_xpath);
                
                // اگر کوئری اول نود را پیدا نکرد، کوئری Fallback با پشتیبانی از چند کلاس با فاصله‌گذاری اجرا می‌شود
                if ((!$esc_nodes || $esc_nodes->length === 0) && preg_match('/^([a-zA-Z0-9_-]*)\.([a-zA-Z0-9_-]+)/', $esc_selector, $matches)) {
                    $tag = !empty($matches[1]) ? $matches[1] : '*';
                    $cls = $matches[2];
                    $fallback_xpath = "//{$tag}[contains(concat(' ', normalize-space(@class), ' '), ' {$cls} ')]";
                    $esc_nodes = @$xpath->query($fallback_xpath);
                }

                if ($esc_nodes && $esc_nodes->length > 0) {
                    // حذف تمام نودهای یافته‌شده و فرزندان آن‌ها از آخر به اول
                    for ($i = $esc_nodes->length - 1; $i >= 0; $i--) {
                        $node = $esc_nodes->item($i);
                        if ($node && $node->parentNode) {
                            $node->parentNode->removeChild($node);
                            $removed_count++;
                        }
                    }
                }
            }
            if ($removed_count > 0) {
                error_log("Escape Elements: {$removed_count} nodes removed from {$encoded_url}");
            }
        }

        // 3. سوم، استخراج بدنه خبر از DOM پاکسازی شده
        $content_raw = dastyar_extract_by_selector($xpath, $body_selector);
        if (!empty($content_raw)) {
            $content = clear_not_allowed_tags($content_raw, $source_root_link);
        } else {
            $content = '';
            // اگر بعد از اعمال escape بدنه خالی شد، گزارش می‌دهیم
            $report_id = insert_rss_report(
                'درخواست واکشی یک پست',
                $encoded_url,
                123,
                '0',
                'بدنه پست پیدا نشد' . (!empty($escape_selectors) ? ' (احتمالاً توسط Escape Elements حذف شده)' : '')
            );
        }


        $post_status = 'draft';
        if ($publish_priority == 'pending') {
            $post_status = 'pending';
        }

        //error_log('post_status here:' . $post_status);

        // Check if all required elements are found (image is now optional)
        if ($title && $excerpt && $content) {
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

            // Upload and set the featured image (only if URL exists)
            if (!empty($thumbnail_url) && $post_id && function_exists('media_sideload_image')) {
                $thumbnail_url = complete_url($thumbnail_url, $source_root_link);

                ob_start();
                $attachment_id = media_sideload_image($thumbnail_url, $post_id, 'thumbnail', 'id');
                ob_end_clean();

                if (!is_wp_error($attachment_id)) {
                    set_post_thumbnail($post_id, $attachment_id);
                } else {
                    // insert rss report error for this section
                    $report_id = insert_rss_report(
                        'درخواست واکشی یک پست',
                        $encoded_url,
                        123,
                        '0',
                        'خطایی در حین آپلود عکس پیش آمده است: ' . $attachment_id->get_error_message()
                    );
                    // به مسیر ادامه می‌دهیم و پست منتشر می‌شود
                }
            } elseif (!empty($thumbnail_url) && !function_exists('media_sideload_image')) {
                // insert rss report error for this section
                $report_id = insert_rss_report(
                    'درخواست واکشی یک پست',
                    $encoded_url,
                    123,
                    '0',
                    'تابع media_sideload_image() در دسترس نیست.'
                );
                // به مسیر ادامه می‌دهیم و پست منتشر می‌شود
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
        // Remove simple_html_dom specific cleanup since we removed it
    } finally {
        if (isset($html) && is_object($html) && method_exists($html, 'clear')) {
            $html->clear();
        }
        unset($html);
        unset($dom);
        unset($xpath);
    }
}
