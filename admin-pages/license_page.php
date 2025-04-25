<?php

require_once ABSPATH . 'wp-admin/includes/file.php';
$wp_load_path = get_home_path() . 'wp-load.php';

if (file_exists($wp_load_path)) {
    require_once ($wp_load_path);
} else {
    //error_log('wp-load.php not found!');
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