<?php

require_once ABSPATH . 'wp-admin/includes/file.php';
$wp_load_path = get_home_path() . 'wp-load.php';

if (file_exists($wp_load_path)) {
    require_once ($wp_load_path);
} else {
    exit;
}


// ایجاد صفحه تنظیمات
function publisher_copoilot_license_page_callback()
{


    $old_secret_code = get_option('i8_secret_code');
    $secret_code = $_POST['cop_secret_code'];
    $response = false;
    if ($secret_code) {

        update_option('i8_secret_code', $secret_code);
        $old_secret_code = get_option('i8_secret_code');
        $response = send_license_validation_request($secret_code);

    } elseif (isset($old_secret_code)) {

        $response = send_license_validation_request($old_secret_code);

    }
    ?>
    <div class="wrap">
        <div class="license_section">
            <form action="" method="post">
                <label for="cop_secret_code">
                    <span>کد مخفی: </span>
                    <input type="text" value="<?php echo $old_secret_code; ?>" name="cop_secret_code"
                        style="direction:ltr;text-align:left;">
                    <button type="submit" name="cop_send_request_to_server">به روز رسانی وضعیت</button>
                    <?php
                    if ($response == true) {
                        print_r('<p style="color:green;"> لایسنس شما معتبر است </p>');
                    } else {
                        print_r('<p style="color:red;"> کد شما معتبر نیست  </p>');
                    }
                    ?>
                </label>

                <?php
                if ($response):
                    $i8_plan_name = (get_option('i8_plan_name')) ? get_option('i8_plan_name') : '-';
                    $i8_subscription_start_date = (get_option('i8_subscription_start_date')) ? get_option('i8_subscription_start_date') : '-';
                    $i8_subscription_end_date = (get_option('i8_subscription_end_date')) ? get_option('i8_subscription_end_date') : '-';
                    $i8_plan_duration = (get_option('i8_plan_duration')) ? get_option('i8_plan_duration') : '-';
                    $i8_plan_cron_interval = (get_option('i8_plan_cron_interval')) ? get_option('i8_plan_cron_interval') : '-';
                    $i8_plan_max_post_fetch = (get_option('i8_plan_max_post_fetch')) ? get_option('i8_plan_max_post_fetch') : '-';
                    ?>
                    <table class="form-table">
                        <tr>
                            <td class="">نوع اشتراک:</td>
                            <td><?php echo $i8_plan_name; ?></td>
                        </tr>

                        <tr>
                            <td>مدت اشتراک(به روز):</td>
                            <td><?php echo $i8_plan_duration; ?></td>
                        </tr>
                        <tr>
                            <td>تاریخ شروع اشتراک:</td>
                            <td><?php echo $i8_subscription_start_date;
                            echo ' [ ';
                            echo @\i8_jDateTime::convertFormatToFormat('Y/m/d - H:i', 'Y-m-d H:i:s', $i8_subscription_start_date);
                            echo ' ]';

                            ?></td>
                        </tr>
                        <tr>
                            <td>تاریخ پایان اشتراک</td>
                            <td><?php echo $i8_subscription_end_date;
                            echo ' [ ';
                            echo @\i8_jDateTime::convertFormatToFormat('Y/m/d - H:i', 'Y-m-d H:i:s', $i8_subscription_end_date);
                            echo ' ]';
                            ?></td>
                        </tr>
                        <tr>
                            <td>فواصل بروزرسانی اتوماتیک فیدها:</td>
                            <td><?php echo $i8_plan_cron_interval; ?></td>
                        </tr>
                        <tr>
                            <td>تعداد مجاز انتشار پست: </td>
                            <td><?php echo $i8_plan_max_post_fetch; ?></td>
                        </tr>
                        <tr>
                            <td>تعداد پست های امروز:</td>
                            <td><?php echo get_option('daily_post_count_for_schedule'); ?></td>
                        </tr>
                        <tr>
                            <td>فواصل کرون جاب به دقیقه:</td>
                            <td><?php
                            $schedules = wp_get_schedules();
                            if (isset($schedules['i8_pc_post_publisher_cron'])) {
                                $interval = $schedules['i8_pc_post_publisher_cron']['interval'];
                                echo $interval / 60;
                            }

                            ?></td>
                        </tr>
                    </table>
                    <?php
                endif;
                ?>

            </form>

        </div>

        <?php
        $resources = get_resources_details();
        if (!empty($resources)) :
            ?>
            <hr style="margin: 40px 0 30px 0; border: 0; border-top: 1px dashed #ccd0d4;">
            
            <div class="resources-debug-container" style="direction: rtl; text-align: right; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border-radius: 4px; padding: 20px; margin-top: 20px; font-family: Tahoma, Arial, sans-serif;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
                    <h3 style="margin: 0; color: #23282d; font-size: 18px; font-weight: bold; display: flex; align-items: center;">
                        <span class="dashicons dashicons-database" style="font-size: 24px; width: 24px; height: 24px; margin-left: 8px; color: #0073aa;"></span>
                        لیست منابع و سلکتورهای دریافت شده از سرور لایسنس
                    </h3>
                    <span style="background: #e7f5fe; color: #0073aa; border: 1px solid #b8e1fc; padding: 6px 14px; border-radius: 30px; font-size: 13px; font-weight: bold;">
                        تعداد کل منابع فعال: <?php echo count($resources); ?> مورد
                    </span>
                </div>
                
                <p style="color: #646970; font-size: 13px; margin-top: 0; margin-bottom: 20px;">
                    در این بخش می‌توانید لیست کامل خبرگزاری‌ها و سلکتورهای CSS مربوط به هرکدام را که از طرف سرور لایسنس ارسال و در پایگاه داده محلی ذخیره شده‌اند، مشاهده نمایید.
                </p>

                <!-- Search Input -->
                <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px; max-width: 450px; background: #f6f7f7; padding: 6px 12px; border: 1px solid #8c8f94; border-radius: 4px;">
                    <span class="dashicons dashicons-search" style="color: #646970; font-size: 20px; width: 20px; height: 20px;"></span>
                    <input type="text" id="resource-search-input" placeholder="جستجو در عنوان، شناسه منبع یا آدرس فید..." 
                           style="width: 100%; background: transparent; border: none; outline: none; box-shadow: none; padding: 4px 0; margin: 0; font-size: 13px;">
                </div>

                <div style="overflow-x: auto;">
                    <table class="wp-list-table widefat fixed striped table-view-list" style="border: 1px solid #c3c4c7; width: 100%; border-collapse: collapse; min-width: 800px;">
                        <thead>
                            <tr style="background: #f6f7f7;">
                                <th style="font-weight: bold; width: 85px; padding: 12px 10px; border-bottom: 2px solid #c3c4c7; text-align: center;">شناسه منبع</th>
                                <th style="font-weight: bold; width: 180px; padding: 12px 10px; border-bottom: 2px solid #c3c4c7; text-align: right;">عنوان منبع</th>
                                <th style="font-weight: bold; padding: 12px 10px; border-bottom: 2px solid #c3c4c7; text-align: right;">آدرس فید (Feed URL)</th>
                                <th style="font-weight: bold; width: 120px; padding: 12px 10px; border-bottom: 2px solid #c3c4c7; text-align: right;">سلکتور عنوان</th>
                                <th style="font-weight: bold; width: 120px; padding: 12px 10px; border-bottom: 2px solid #c3c4c7; text-align: right;">سلکتور تصویر</th>
                                <th style="font-weight: bold; width: 120px; padding: 12px 10px; border-bottom: 2px solid #c3c4c7; text-align: right;">سلکتور لید</th>
                                <th style="font-weight: bold; width: 140px; padding: 12px 10px; border-bottom: 2px solid #c3c4c7; text-align: right;">سلکتور بدنه (Body)</th>
                                <th style="font-weight: bold; width: 85px; padding: 12px 10px; border-bottom: 2px solid #c3c4c7; text-align: center;">ادغام GUID</th>
                            </tr>
                        </thead>
                        <tbody id="resources-table-body">
                            <?php foreach ($resources as $res) : ?>
                                <tr class="resource-row">
                                    <td class="res-id" style="padding: 12px 10px; font-family: monospace; font-size: 13px; color: #555; text-align: center; vertical-align: middle;"><?php echo esc_html($res->resource_id); ?></td>
                                    <td class="res-title" style="padding: 12px 10px; font-weight: 600; color: #23282d; vertical-align: middle;"><?php echo esc_html($res->resource_title); ?></td>
                                    <td class="res-feed" style="padding: 12px 10px; direction: ltr; text-align: left; font-family: monospace; font-size: 12px; color: #0073aa; word-break: break-all; vertical-align: middle;">
                                        <a href="<?php echo esc_url($res->source_feed_link); ?>" target="_blank" style="text-decoration: none; display: flex; align-items: center; gap: 4px;">
                                            <span class="dashicons dashicons-external" style="font-size: 15px; width: 15px; height: 15px; text-decoration: none; vertical-align: middle;"></span>
                                            <?php echo esc_html($res->source_feed_link); ?>
                                        </a>
                                    </td>
                                    <td style="padding: 12px 10px; font-family: monospace; font-size: 12px; color: #c22026; vertical-align: middle;"><?php echo $res->title_selector ? esc_html($res->title_selector) : '<span style="color:#a7aaad; font-style:italic;">—</span>'; ?></td>
                                    <td style="padding: 12px 10px; font-family: monospace; font-size: 12px; color: #1a73e8; vertical-align: middle;"><?php echo $res->img_selector ? esc_html($res->img_selector) : '<span style="color:#a7aaad; font-style:italic;">—</span>'; ?></td>
                                    <td style="padding: 12px 10px; font-family: monospace; font-size: 12px; color: #e37400; vertical-align: middle;"><?php echo $res->lead_selector ? esc_html($res->lead_selector) : '<span style="color:#a7aaad; font-style:italic;">—</span>'; ?></td>
                                    <td style="padding: 12px 10px; font-family: monospace; font-size: 12px; color: #137333; font-weight: bold; vertical-align: middle;"><?php echo $res->body_selector ? esc_html($res->body_selector) : '<span style="color:#a7aaad; font-style:italic;">—</span>'; ?></td>
                                    <td style="padding: 12px 10px; text-align: center; vertical-align: middle;">
                                        <?php if ($res->need_to_merge_guid_link == '1') : ?>
                                            <span style="background: #e6f4ea; color: #137333; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; display: inline-block;">بله</span>
                                        <?php else : ?>
                                            <span style="background: #f1f3f4; color: #5f6368; padding: 3px 10px; border-radius: 12px; font-size: 11px; display: inline-block;">خیر</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var searchInput = document.getElementById('resource-search-input');
                    if (searchInput) {
                        searchInput.addEventListener('keyup', function() {
                            var filter = this.value.toLowerCase().trim();
                            var rows = document.querySelectorAll('#resources-table-body .resource-row');
                            
                            rows.forEach(function(row) {
                                var id = row.querySelector('.res-id').textContent.toLowerCase();
                                var title = row.querySelector('.res-title').textContent.toLowerCase();
                                var feed = row.querySelector('.res-feed').textContent.toLowerCase();
                                
                                if (id.indexOf(filter) > -1 || title.indexOf(filter) > -1 || feed.indexOf(filter) > -1) {
                                    row.style.display = '';
                                } else {
                                    row.style.display = 'none';
                                }
                            });
                        });
                    }
                });
            </script>
        <?php endif; ?>

        </div>

    </div>
    <?php

}




// تابع برای اضافه کردن صفحه تنظیمات
function i8_add_license_page_menu()
{
    add_submenu_page(
        'publisher_copoilot',
        'لاینسس',
        'لایسنس',
        'manage_options',
        'publisher_copoilot_license',
        'publisher_copoilot_license_page_callback'
    );

    // ثبت فیلدهای تنظیمات
    add_action('admin_init', 'publisher_copoilot_register_licenses');
}

// ثبت فیلدهای تنظیمات
function publisher_copoilot_register_licenses()
{
   
}

// تابع بازگشتی برای نمایش بخش تنظیمات
function publisher_copoilot_licenses_section_callback()
{
    // echo '<p>لطفا تنظیمات مورد نیاز را انجام دهید.</p>';

}


// فراخوانی تابع افزودن صفحه تنظیمات
add_action('admin_menu', 'i8_add_license_page_menu');