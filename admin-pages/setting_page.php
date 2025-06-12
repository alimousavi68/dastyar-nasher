<?php

require_once ABSPATH . 'wp-admin/includes/file.php';
$wp_load_path = get_home_path() . 'wp-load.php';

if (file_exists($wp_load_path)) {
    require_once ($wp_load_path);
} else {
   
    exit;
}


// ایجاد صفحه تنظیمات
function publisher_copoilot_setting_page_callback()
{
    // اگر مقادیر تغییر کرده بودن میزان پست امروز رو محدد تغییر بده
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // دریافت مقادیر قبلی
        $old_news_interval_start = get_option('news_interval_start');
        $old_news_interval_end = get_option('news_interval_end');

        // به‌روزرسانی مقادیر جدید
        if (isset($_POST['news_interval_start'])) {
            $new_news_interval_start = intval($_POST['news_interval_start']);
            update_option('news_interval_start', $new_news_interval_start);
        }
        if (isset($_POST['news_interval_end'])) {
            $new_news_interval_end = intval($_POST['news_interval_end']);
            update_option('news_interval_end', $new_news_interval_end);
        }

        // بررسی تغییرات و فراخوانی اکشن
        if ($old_news_interval_start != $new_news_interval_start || $old_news_interval_end != $new_news_interval_end) {
            do_action('set_daily_post_count_for_schedule_task');
            do_action('i8_action_set_cron_job_publishe_posts');
            error_log('i8: settings page: news_interval_start or news_interval_end changed');
        }

        $old_start_cron_time = get_option('start_cron_time');
        $old_end_cron_time = get_option('end_cron_time');
        
        // به‌روزرسانی مقادیر جدید
        if (isset($_POST['start_cron_time'])) {
            $new_start_cron_time = $_POST['start_cron_time'];
            update_option('start_cron_time', $new_start_cron_time);
        }
        if (isset($_POST['end_cron_time'])) {
            $new_end_cron_time = $_POST['end_cron_time'];
            update_option('end_cron_time', $new_end_cron_time);
        }
        // بررسی تغییرات و فراخوانی اکشن
        if ($old_start_cron_time != $new_start_cron_time || $old_end_cron_time != $new_end_cron_time) {
            // do_action('calculate_post_publishing_schedule');
            do_action('i8_action_set_cron_job_publishe_posts');
            error_log('i8: settings page: start_cron_time or end_cron_time changed');
            
        }
    }

    ?>
    <div class="wrap">
        <div class="license_section">
            <form method="post" action="">
                <?php

                settings_fields('publisher_copoilot_settings_group');

                do_settings_sections('publisher_copoilot_setting');

                submit_button('ذخیره تنظیمات'); // دکمه ارسال برای فرم تنظیمات
                ?>
            </form>

        </div>


    </div>
    <script>

    </script>
    <?php

}



// تابع برای اضافه کردن صفحه تنظیمات
function i8_add_seeting_page_menu()
{
    add_submenu_page(
        'publisher_copoilot',
        'تنظیمات دستیار',
        'تنظیمات',
        'manage_options',
        'publisher_copoilot_setting',
        'publisher_copoilot_setting_page_callback'
    );

    // ثبت فیلدهای تنظیمات
    add_action('admin_init', 'publisher_copoilot_register_settings');
}

// ثبت فیلدهای تنظیمات
function publisher_copoilot_register_settings()
{
    register_setting('publisher_copoilot_settings_group', 'start_cron_time');
    register_setting('publisher_copoilot_settings_group', 'end_cron_time');

    register_setting('publisher_copoilot_settings_group', 'news_interval_start');
    register_setting('publisher_copoilot_settings_group', 'news_interval_end');


    add_settings_section(
        'publisher_copoilot_settings_section',
        'تنظیمات دستیار',
        'publisher_copoilot_settings_section_callback',
        'publisher_copoilot_setting'
    );

    add_settings_field(
        'start_cron_time',
        'ساعت شروع کار کرون جاب',
        'start_cron_time_callback',
        'publisher_copoilot_setting',
        'publisher_copoilot_settings_section'
    );

    add_settings_field(
        'end_cron_time',
        'ساعت پایان کار کرون جاب',
        'end_cron_time_callback',
        'publisher_copoilot_setting',
        'publisher_copoilot_settings_section'
    );

    add_settings_field(
        'news_interval',
        'بازه عددی تعداد اخبار روزانه (بازه شروع و پایان)',
        'news_interval_callback',
        'publisher_copoilot_setting',
        'publisher_copoilot_settings_section'
    );
}

// تابع بازگشتی برای نمایش بخش تنظیمات
function publisher_copoilot_settings_section_callback()
{
    // echo '<p>لطفا تنظیمات مورد نیاز را انجام دهید.</p>';

}

// توابع بازگشتی برای نمایش فیلدها
function start_cron_time_callback()
{
    $start_time = get_option('start_cron_time');
    echo '<input type="time" name="start_cron_time" value="' . esc_attr($start_time) . '" placeholder="07:00:00" />';
}

function end_cron_time_callback()
{
    $end_time = get_option('end_cron_time');
    echo '<input type="time" name="end_cron_time" value="' . esc_attr($end_time) . '"  placeholder="22:00:00" />';
}

function news_interval_callback()
{
    $news_interval_start = get_option('news_interval_start');
    $news_interval_end = get_option('news_interval_end');
    $start_cron_time = get_option('start_cron_time');
    $end_cron_time = get_option('end_cron_time');
    $max_daily_post = get_option('daily_post_count_for_schedule');
    $next_run_time = get_option('i8_next_run_time');
    $now = current_time('timestamp');
    $now_str = date_i18n('Y-m-d H:i:s', $now);
    $start_parts = explode(':', $start_cron_time);
    $end_parts = explode(':', $end_cron_time);
    $start_seconds = isset($start_parts[0], $start_parts[1]) ? ($start_parts[0] * 3600 + $start_parts[1] * 60) : 0;
    $end_seconds = isset($end_parts[0], $end_parts[1]) ? ($end_parts[0] * 3600 + $end_parts[1] * 60) : 0;
    $interval = ($end_seconds <= $start_seconds) ? (24*3600 - $start_seconds + $end_seconds) : ($end_seconds - $start_seconds);
    $interval_hours = round($interval/3600, 2);
    $max_daily_post = $max_daily_post ? $max_daily_post : max($news_interval_start, $news_interval_end);
    $post_interval = ($interval_hours > 0 && $max_daily_post > 0) ? round(($interval_hours*60)/$max_daily_post, 2) : 0;
    // محاسبه زمان اجرای بعدی کرون با توجه به ساعت کاری
    $next_run_str = '';
    if ($next_run_time) {
        $next_run_time_int = intval($next_run_time);
        $next_run_str = date_i18n('Y-m-d H:i:s', $next_run_time_int);
        // اگر زمان اجرای بعدی خارج از بازه کاری است، زمان شروع بعدی را پیدا کن
        $today = date('Y-m-d', $now);
        $start_today = strtotime($today . ' ' . $start_cron_time);
        $end_today = strtotime($today . ' ' . $end_cron_time);
        if ($end_today <= $start_today) $end_today += 86400;
        if ($next_run_time_int < $start_today || $next_run_time_int > $end_today) {
            // اجرای بعدی در اولین دوره بعدی شروع ساعت کاری
            if ($now < $start_today) {
                $next_run_str = date_i18n('Y-m-d H:i:s', $start_today);
            } else {
                $next_start = $start_today + 86400;
                $next_run_str = date_i18n('Y-m-d H:i:s', $next_start);
            }
        }
    }
    echo '<input type="number" name="news_interval_start" value="' . esc_attr($news_interval_start) . '" /> - <input type="number" name="news_interval_end" value="' . esc_attr($news_interval_end) . '" /><br>';
    echo '<div class="i8-flex-column" style="padding:10px 5px;border:1px solid #ccc; margin: 10px 0; direction:rtl; text-align:right; background:#f9f9f9;">';
    echo '<b>ساعت شروع کار ربات:</b> <span style="color:#007bff">' . esc_html($start_cron_time) . '</span> <b>ساعت پایان:</b> <span style="color:#007bff">' . esc_html($end_cron_time) . '</span><br>';
    echo '<b>حداکثر تعداد پست برای انتشار:</b> <span style="color:#28a745">' . esc_html($max_daily_post) . '</span><br>';
    echo '<b>زمان اجرای بعدی کرون جاب:</b> <span style="color:#e67e22">' . esc_html($next_run_str) . '</span><br>';
    echo '<b>نحوه محاسبه ساعت کاری و فواصل انتشار:</b><br>';
    echo '<span style="display:inline-block;padding:4px 8px;background:#e3e3e3;border-radius:6px;margin:4px 0;">'
        . 'ساعت کاری: '
        . esc_html($start_cron_time) . ' '
        . '<span style="color:#888">تا</span> '
        . esc_html($end_cron_time) . ' '
        . '(<span style="color:#007bff">' . $interval_hours . ' ساعت</span>)'
        . ' | '
        . 'حداکثر پست: <span style="color:#28a745">' . esc_html($max_daily_post) . '</span>'
        . ' | '
        . 'فاصله انتشار: <span style="color:#e67e22">' . $post_interval . ' دقیقه</span>'
        . '</span>';
    echo '</div>';
}

// فراخوانی تابع افزودن صفحه تنظیمات
add_action('admin_menu', 'i8_add_seeting_page_menu');