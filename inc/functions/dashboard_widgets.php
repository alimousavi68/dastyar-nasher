<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Hook to register dashboard widgets
add_action('wp_dashboard_setup', 'i8_add_dashboard_widgets');

function i8_add_dashboard_widgets() {
    wp_add_dashboard_widget(
        'i8_dashboard_overview_widget',
        '📡 دستیار ناشر - شناسنامه و لایسنس',
        'i8_dashboard_overview_widget_render'
    );

    wp_add_dashboard_widget(
        'i8_dashboard_publishing_widget',
        '📅 دستیار ناشر - وضعیت انتشار و صف',
        'i8_dashboard_publishing_widget_render'
    );

    wp_add_dashboard_widget(
        'i8_dashboard_monitoring_widget',
        '🛠️ دستیار ناشر - پایش منابع و کرون',
        'i8_dashboard_monitoring_widget_render'
    );
}

// Helper to convert numbers to Persian
if (!function_exists('i8_dash_to_persian_num')) {
    function i8_dash_to_persian_num($number) {
        $english_digits = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        $persian_digits = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
        return str_replace($english_digits, $persian_digits, (string)$number);
    }
}

// Get cached stats to prevent DB/Server overload
function i8_get_dashboard_stats() {
    $stats = get_transient('i8_dashboard_widgets_stats');
    if ($stats === false) {
        global $wpdb;
        $table_errors = $wpdb->prefix . 'i8_monitoring_errors';
        $table_queue = $wpdb->prefix . 'pc_post_schedule';
        $table_reports = $wpdb->prefix . 'pc_reports';
        
        // 1. Queue count
        $queue_count = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_queue'") === $table_queue) {
            $queue_count = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_queue"));
        }
        
        // 2. Active errors count
        $active_errors_count = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_errors'") === $table_errors) {
            $active_errors_count = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_errors WHERE status = 'active_error'"));
        }
        
        // 3. Active resources count
        $resources_count = 0;
        if (function_exists('get_resources_details')) {
            $all_resources = get_resources_details() ?: array();
            $resources_count = count($all_resources);
        }
        
        // 4. Reports overview
        $total_reports = 0;
        $success_reports = 0;
        $failed_reports = 0;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_reports'") === $table_reports) {
            $total_reports = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_reports"));
            $success_reports = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_reports WHERE status = 1"));
            $failed_reports = intval($wpdb->get_var("SELECT COUNT(*) FROM $table_reports WHERE status = 0"));
        }
        
        // 5. Next scheduled post details
        $next_post_title = '';
        $next_post_time = '';
        if (class_exists('ActionScheduler_Store') && function_exists('as_get_scheduled_actions')) {
            $actions = as_get_scheduled_actions(array(
                'hook' => 'i8_action_publish_specific_post',
                'status' => ActionScheduler_Store::STATUS_PENDING,
                'per_page' => 1,
                'order' => 'ASC',
            ));
            if (!empty($actions)) {
                $next_action = array_shift($actions);
                $args = $next_action->get_args();
                $post_id = isset($args['post_id']) ? intval($args['post_id']) : 0;
                if ($post_id > 0) {
                    $next_post_title = get_the_title($post_id);
                    $schedule_date = $next_action->get_schedule()->get_date();
                    if ($schedule_date) {
                        date_default_timezone_set('Asia/Tehran');
                        $next_post_time = $schedule_date->getTimestamp();
                    }
                }
            }
        }
        
        // 6. Last 3 successful published posts in reports
        $last_published = array();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_reports'") === $table_reports) {
            $last_published_rows = $wpdb->get_results(
                "SELECT * FROM $table_reports WHERE action_title = 'انتشار پست زمان‌بندی شده' AND status = 1 ORDER BY id DESC LIMIT 3",
                ARRAY_A
            ) ?: array();
            
            foreach ($last_published_rows as $row) {
                $post_id = intval($row['resource_id']);
                $title = '';
                $link = '';
                if ($post_id > 0) {
                    $title = get_the_title($post_id);
                    $link = get_edit_post_link($post_id);
                }
                if (empty($title)) {
                    $title = $row['resource_name'];
                    $link = filter_var($row['resource_name'], FILTER_VALIDATE_URL) ? $row['resource_name'] : '';
                }
                $last_published[] = array(
                    'title' => $title,
                    'link' => $link,
                    'date' => $row['pub_date']
                );
            }
        }
        
        $stats = array(
            'queue_count' => $queue_count,
            'active_errors_count' => $active_errors_count,
            'resources_count' => $resources_count,
            'total_reports' => $total_reports,
            'success_reports' => $success_reports,
            'failed_reports' => $failed_reports,
            'next_post_title' => $next_post_title,
            'next_post_time' => $next_post_time,
            'last_published' => $last_published
        );
        
        // Cache stats for 5 minutes to keep dashboard page loads extremely fast
        set_transient('i8_dashboard_widgets_stats', $stats, 300);
    }
    return $stats;
}

// Clear transient on settings update or manual action
add_action('update_option_start_cron_time', 'i8_clear_dashboard_stats_transient');
add_action('update_option_end_cron_time', 'i8_clear_dashboard_stats_transient');
add_action('update_option_news_interval_start', 'i8_clear_dashboard_stats_transient');
add_action('update_option_news_interval_end', 'i8_clear_dashboard_stats_transient');
function i8_clear_dashboard_stats_transient() {
    delete_transient('i8_dashboard_widgets_stats');
}

// Embedded clean style for widgets
function i8_dashboard_widgets_inline_styles() {
    ?>
    <style>
        .i8-dash-widget {
            direction: rtl;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        }
        .i8-dash-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .i8-dash-row:last-child {
            border-bottom: none;
        }
        .i8-dash-label {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }
        .i8-dash-val {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
        }
        .i8-dash-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 8px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 6px;
        }
        .i8-dash-badge-success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .i8-dash-badge-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .i8-dash-badge-warning { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .i8-dash-badge-info { background: rgba(79, 70, 229, 0.1); color: #4f46e5; }
        
        .i8-dash-list {
            margin-top: 10px;
            padding-right: 0;
            list-style: none;
        }
        .i8-dash-list li {
            padding: 8px 10px;
            margin-bottom: 6px;
            background: #f8fafc;
            border-radius: 8px;
            border-right: 3px solid #cbd5e1;
            font-size: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .i8-dash-list li.success-post {
            border-right-color: #10b981;
        }
        .i8-dash-list li a {
            text-decoration: none;
            color: #4f46e5;
            font-weight: 600;
        }
        .i8-dash-list li a:hover {
            text-decoration: underline;
        }
        .i8-dash-footer {
            margin-top: 12px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            text-align: left;
        }
        .i8-dash-link {
            font-size: 12px;
            color: #4f46e5;
            text-decoration: none;
            font-weight: 600;
        }
        .i8-dash-link:hover {
            text-decoration: underline;
        }
    </style>
    <?php
}
add_action('admin_head', 'i8_dashboard_widgets_inline_styles');

// Render 1: Overview & License
function i8_dashboard_overview_widget_render() {
    $plan_name = get_option('i8_plan_name', 'فارغ');
    $sub_end = get_option('i8_subscription_end_date');
    $start_time = get_option('start_cron_time', '08:00');
    $end_time = get_option('end_cron_time', '22:00');
    $interval_start = get_option('news_interval_start', '30');
    $interval_end = get_option('news_interval_end', '40');
    $daily_post_count = get_option('daily_post_count_for_schedule', '35');

    $stats = i8_get_dashboard_stats();

    // License expiry days calculation
    $days_left = 0;
    $license_status = 'expired';
    $license_badge_class = 'i8-dash-badge-danger';
    $license_text = 'منقضی شده';

    if ($plan_name !== 'فارغ' && !empty($sub_end)) {
        $end_timestamp = strtotime($sub_end);
        if ($end_timestamp && $end_timestamp > time()) {
            $days_left = ceil(($end_timestamp - time()) / 86400);
            $license_status = 'active';
            $license_badge_class = $days_left < 7 ? 'i8-dash-badge-warning' : 'i8-dash-badge-success';
            $license_text = 'فعال (' . i8_dash_to_persian_num($days_left) . ' روز)';
        }
    }
    
    // Interval time estimation
    $interval_seconds = 0;
    if (function_exists('calculate_post_publish_time')) {
        $interval_seconds = calculate_post_publish_time();
    }
    $interval_minutes = $interval_seconds ? round($interval_seconds / 60) : 30;

    ?>
    <div class="i8-dash-widget">
        <div class="i8-dash-row">
            <span class="i8-dash-label">اشتراک جاری:</span>
            <span class="i8-dash-val"><?php echo esc_html($plan_name); ?></span>
        </div>
        <div class="i8-dash-row">
            <span class="i8-dash-label">وضعیت اعتبار لایسنس:</span>
            <span class="i8-dash-badge <?php echo $license_badge_class; ?>"><?php echo esc_html($license_text); ?></span>
        </div>
        <div class="i8-dash-row">
            <span class="i8-dash-label">بازه زمانی صف انتشار:</span>
            <span class="i8-dash-val" style="direction: ltr;"><?php echo esc_html($start_time . ' - ' . $end_time); ?></span>
        </div>
        <div class="i8-dash-row">
            <span class="i8-dash-label">انتشار روزانه (تصادفی):</span>
            <span class="i8-dash-val"><?php echo i8_dash_to_persian_num($daily_post_count); ?> نوشته (بازه <?php echo i8_dash_to_persian_num($interval_start); ?>-<?php echo i8_dash_to_persian_num($interval_end); ?>)</span>
        </div>
        <div class="i8-dash-row">
            <span class="i8-dash-label">فاصله تخمینی انتشار:</span>
            <span class="i8-dash-badge i8-dash-badge-info">هر <?php echo i8_dash_to_persian_num($interval_minutes); ?> دقیقه</span>
        </div>
        <div class="i8-dash-footer">
            <a href="<?php echo admin_url('admin.php?page=publisher_copoilot_license'); ?>" class="i8-dash-link">⚙️ مدیریت لایسنس و اشتراک</a>
        </div>
    </div>
    <?php
}

// Render 2: Queue & Live Logs
function i8_dashboard_publishing_widget_render() {
    $stats = i8_get_dashboard_stats();
    
    // Formatting next execution time
    $next_run_display = 'بدون برنامه';
    if (!empty($stats['next_post_time'])) {
        if (class_exists('i8_jDateTime')) {
            $jdate = new i8_jDateTime(true, true, 'Asia/Tehran');
            $next_run_display = $jdate->date('H:i:s', $stats['next_post_time']);
        } else {
            $next_run_display = date('H:i:s', $stats['next_post_time']);
        }
        $next_run_display = i8_dash_to_persian_num($next_run_display);
    }
    
    // Truncating next post title
    $next_post_title_display = '-';
    if (!empty($stats['next_post_title'])) {
        $next_post_title_display = $stats['next_post_title'];
        if (mb_strlen($next_post_title_display, 'UTF-8') > 30) {
            $next_post_title_display = mb_substr($next_post_title_display, 0, 30, 'UTF-8') . '...';
        }
    }

    ?>
    <div class="i8-dash-widget">
        <div class="i8-dash-row">
            <span class="i8-dash-label">نوشته‌های در صف انتظار:</span>
            <span class="i8-dash-badge i8-dash-badge-info"><?php echo i8_dash_to_persian_num($stats['queue_count']); ?> نوشته</span>
        </div>
        <div class="i8-dash-row">
            <span class="i8-dash-label">ساعت انتشار بعدی:</span>
            <span class="i8-dash-val" style="color: #10b981; font-weight: 700;"><?php echo esc_html($next_run_display); ?></span>
        </div>
        <div class="i8-dash-row">
            <span class="i8-dash-label">نوشته بعدی صف:</span>
            <span class="i8-dash-val" title="<?php echo esc_attr($stats['next_post_title']); ?>"><?php echo esc_html($next_post_title_display); ?></span>
        </div>
        
        <div style="margin-top: 12px; font-weight: 600; font-size: 12px; color: #1e293b;">آخرین انتشارهای موفق:</div>
        <ul class="i8-dash-list">
            <?php if (!empty($stats['last_published'])): ?>
                <?php foreach ($stats['last_published'] as $pub): 
                    $title = $pub['title'];
                    if (mb_strlen($title, 'UTF-8') > 45) {
                        $title = mb_substr($title, 0, 45, 'UTF-8') . '...';
                    }
                ?>
                    <li class="success-post">
                        <?php if (!empty($pub['link'])): ?>
                            <a href="<?php echo esc_url($pub['link']); ?>" target="_blank">🔗 <?php echo esc_html($title); ?></a>
                        <?php else: ?>
                            <?php echo esc_html($title); ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li style="border-right-color: #cbd5e1; color: #64748b; font-style: italic;">هیچ انتشار موفقی اخیراً ثبت نشده است.</li>
            <?php endif; ?>
        </ul>

        <div class="i8-dash-footer">
            <a href="<?php echo admin_url('admin.php?page=publisher-copilot-schedule-queue'); ?>" class="i8-dash-link">📋 مشاهده صف انتشار نوشته‌ها</a>
        </div>
    </div>
    <?php
}

// Render 3: Sources & Cron Monitoring
function i8_dashboard_monitoring_widget_render() {
    $stats = i8_get_dashboard_stats();
    
    // Formatting next crawl time
    $next_crawl_time = get_option('i8_next_scrap_all_resource_feed_time');
    $next_crawl_display = 'در صف انتظار';
    if ($next_crawl_time) {
        if (class_exists('i8_jDateTime')) {
            $jdate = new i8_jDateTime(true, true, 'Asia/Tehran');
            $next_crawl_display = $jdate->date('H:i:s', $next_crawl_time);
        } else {
            $next_crawl_display = date('H:i:s', $next_crawl_time);
        }
        $next_crawl_display = i8_dash_to_persian_num($next_crawl_display);
    }
    
    $error_badge_class = $stats['active_errors_count'] > 0 ? 'i8-dash-badge-danger' : 'i8-dash-badge-success';
    $error_text = $stats['active_errors_count'] > 0 ? $stats['active_errors_count'] . ' منبع خطا دارد' : 'همه منابع سالم';

    ?>
    <div class="i8-dash-widget">
        <div class="i8-dash-row">
            <span class="i8-dash-label">کل منابع فعال:</span>
            <span class="i8-dash-val"><?php echo i8_dash_to_persian_num($stats['resources_count']); ?> منبع خبری</span>
        </div>
        <div class="i8-dash-row">
            <span class="i8-dash-label">وضعیت سلامت منابع:</span>
            <span class="i8-dash-badge <?php echo $error_badge_class; ?>"><?php echo esc_html(i8_dash_to_persian_num($error_text)); ?></span>
        </div>
        <div class="i8-dash-row">
            <span class="i8-dash-label">نوبت خزش بعدی فیدها:</span>
            <span class="i8-dash-badge i8-dash-badge-warning"><?php echo esc_html($next_crawl_display); ?></span>
        </div>
        <div class="i8-dash-row">
            <span class="i8-dash-label">کل لاگ‌های ثبت‌شده:</span>
            <span class="i8-dash-val"><?php echo i8_dash_to_persian_num($stats['total_reports']); ?> رویداد</span>
        </div>
        <div class="i8-dash-row">
            <span class="i8-dash-label">میزان موفقیت کل خزش‌ها:</span>
            <span class="i8-dash-val" style="color: #10b981;">
                <?php 
                $percent = $stats['total_reports'] > 0 ? round(($stats['success_reports'] / $stats['total_reports']) * 100) : 100;
                echo i8_dash_to_persian_num($percent) . ' درصد';
                ?>
            </span>
        </div>

        <div class="i8-dash-footer" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <a href="<?php echo admin_url('admin.php?page=publisher-copilot-monitoring'); ?>" class="i8-dash-link">🚨 پایش خطاها</a>
            <a href="<?php echo admin_url('admin.php?page=publisher-copilot-report'); ?>" class="i8-dash-link">📊 لاگ رویدادها</a>
        </div>
    </div>
    <?php
}
