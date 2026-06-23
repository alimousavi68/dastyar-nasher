<?php
// Add menu page
add_action('admin_menu', 'add_report_submenu_page');

function add_report_submenu_page()
{
    add_submenu_page(
        'publisher_copoilot',
        'گزارشات',
        'گزارشات',
        'manage_options',
        'publisher-copilot-report',
        'display_report_page'
    );
}

// Helper to convert numbers to Persian (local to reports page)
if (!function_exists('i8_report_to_persian_num')) {
    function i8_report_to_persian_num($number) {
        $english_digits = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        $persian_digits = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
        return str_replace($english_digits, $persian_digits, (string)$number);
    }
}

function display_report_page()
{
    $reports = display_rss_reports() ?: array();
    
    // محاسبه آمار گزارش‌ها
    $total_reports = count($reports);
    $success_count = 0;
    $failed_count = 0;
    foreach ($reports as $r) {
        if ($r['status'] == 1) {
            $success_count++;
        } else {
            $failed_count++;
        }
    }

    // استخراج لیست اکشن‌های یکتا برای فیلتر
    $unique_actions = array_unique(array_column($reports, 'action_title'));

    // گروه‌بندی عملیات‌ها
    $groups = array(
        'crawling' => array(
            'label' => 'گزارشات خزیدن',
            'actions' => array('خزیدن غیرهمزمان فید', 'اتمام خزیدن فید', 'درخواست واکشی یک پست')
        ),
        'testing' => array(
            'label' => 'تست منابع',
            'actions' => array('ثبت خطای دائمی منبع', 'تست مجدد موفقیت‌آمیز', 'خطای بررسی منبع', 'تست مجدد موفقیت‌آمیز منبع', 'بررسی خودکار منابع خبری', 'بررسی دستی منبع خبری')
        ),
        'publishing' => array(
            'label' => 'صف انتشار',
            'actions' => array('انتشار پست زمان‌بندی شده')
        )
    );

    // دریافت فیلترهای ارسالی از سمت کاربر
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
    $filter_action = isset($_GET['filter_action']) ? sanitize_text_field($_GET['filter_action']) : '';

    // فیلتر کردن لاگ‌ها
    $filtered_reports = array();
    foreach ($reports as $report) {
        if ($filter_status !== '' && (string)$report['status'] !== $filter_status) {
            continue;
        }
        if ($filter_action !== '') {
            if (strpos($filter_action, 'group:') === 0) {
                $group_key = substr($filter_action, 6);
                if (isset($groups[$group_key])) {
                    if (!in_array($report['action_title'], $groups[$group_key]['actions'])) {
                        continue;
                    }
                }
            } else {
                if ($report['action_title'] !== $filter_action) {
                    continue;
                }
            }
        }
        $filtered_reports[] = $report;
    }
?>
    <style>
        :root {
            --i8-primary: #4f46e5;
            --i8-primary-hover: #4338ca;
            --i8-success: #10b981;
            --i8-danger: #ef4444;
            --i8-neutral-50: #f8fafc;
            --i8-neutral-100: #f1f5f9;
            --i8-neutral-800: #1e293b;
            --i8-glass-bg: rgba(255, 255, 255, 0.75);
            --i8-glass-border: rgba(226, 232, 240, 0.8);
        }

        .i8-report-wrap {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            margin: 20px 20px 0 0;
            max-width: 98%;
            direction: rtl;
        }

        .i8-report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .i8-title-sec h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--i8-neutral-800);
            margin: 0 0 6px 0;
        }

        .i8-title-sec .description {
            font-size: 14px;
            color: #64748b;
            margin: 0;
        }

        .i8-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none !important;
        }

        .i8-btn-primary {
            background: linear-gradient(135deg, var(--i8-primary), #6366f1);
            color: white;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
        }

        .i8-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
            color: white;
        }

        .i8-btn-danger {
            background: linear-gradient(135deg, var(--i8-danger), #f87171);
            color: white;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);
        }

        .i8-btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        /* Stats Grid */
        .i8-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .i8-stat-card {
            background: var(--i8-glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--i8-glass-border);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.02);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .i8-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .i8-stat-info {
            display: flex;
            flex-direction: column;
        }

        .i8-stat-label {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
        }

        .i8-stat-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--i8-neutral-800);
            margin-top: 4px;
        }

        .i8-bg-primary { background: rgba(79, 70, 229, 0.1); color: var(--i8-primary); }
        .i8-bg-success { background: rgba(16, 185, 129, 0.1); color: var(--i8-success); }
        .i8-bg-danger { background: rgba(239, 68, 68, 0.1); color: var(--i8-danger); }

        /* Container Card */
        .i8-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            padding: 24px;
            margin-bottom: 24px;
        }

        /* Filter Form Styling */
        .i8-filter-form {
            display: flex;
            gap: 16px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            background: #fff;
            padding: 16px 20px;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
        }

        .i8-filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .i8-filter-group label {
            font-weight: 600;
            font-size: 13px;
            color: #475569;
        }

        .i8-filter-group select {
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 13px;
            background-color: #fff;
            color: #1e293b;
        }

        #filter_action {
            min-width: 250px;
        }

        #filter_status {
            min-width: 180px; /* افزایش عرض لیست کشویی وضعیت */
        }

        /* Table */
        .i8-table-wrapper {
            overflow-x: auto;
        }

        .i8-table {
            width: 100%;
            border-collapse: collapse;
            text-align: right;
            border: 1px solid #cbd5e1;
        }

        .i8-table th {
            padding: 18px 20px;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            background-color: #f8fafc;
            border-bottom: 2px solid #cbd5e1;
            border-left: 1px solid #cbd5e1;
        }

        .i8-table th:last-child {
            border-left: none;
        }

        .i8-table td {
            padding: 18px 20px;
            font-size: 14px;
            color: #334155;
            border-bottom: 1px solid #cbd5e1;
            border-left: 1px solid #cbd5e1;
            vertical-align: middle;
        }

        .i8-table td:last-child {
            border-left: none;
        }

        .i8-table tbody tr {
            transition: background 0.15s ease;
        }

        .i8-table tbody tr:hover {
            background: #f1f5f9;
        }

        /* Badges */
        .i8-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 6px;
        }

        .i8-badge-success { background: rgba(16, 185, 129, 0.1); color: var(--i8-success); }
        .i8-badge-danger { background: rgba(239, 68, 68, 0.1); color: var(--i8-danger); }

        .i8-link {
            color: var(--i8-primary);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: color 0.15s ease;
        }

        .i8-link:hover {
            color: var(--i8-primary-hover);
            text-decoration: underline;
        }
    </style>

    <div class="wrap i8-report-wrap">
        <!-- Header -->
        <div class="i8-report-header">
            <div class="i8-title-sec">
                <h1>گزارشات و رویدادهای سیستم</h1>
                <p class="description">لاگ دقیق فعالیت‌های خزش منابع، وضعیت تراکنش‌ها و تاریخچه انتشار صف زمان‌بندی</p>
            </div>
            <?php if (!empty($reports)): ?>
                <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" onsubmit="return confirm('آیا از پاک کردن کامل تاریخچه گزارشات اطمینان دارید؟');">
                    <input type="hidden" name="action" value="delete_all_reports">
                    <input type="hidden" name="page" value="publisher-copilot-report">
                    <?php wp_nonce_field('delete_all_reports'); ?>
                    <button type="submit" class="i8-btn i8-btn-danger">🗑️ حذف همه گزارشات</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Stats Grid -->
        <div class="i8-stats-grid">
            <div class="i8-stat-card">
                <div class="i8-stat-icon i8-bg-primary">📊</div>
                <div class="i8-stat-info">
                    <span class="i8-stat-label">کل رویدادهای ثبت‌شده</span>
                    <span class="i8-stat-value"><?php echo i8_report_to_persian_num($total_reports); ?> مورد</span>
                </div>
            </div>
            <div class="i8-stat-card">
                <div class="i8-stat-icon i8-bg-success">✅</div>
                <div class="i8-stat-info">
                    <span class="i8-stat-label">عملیات موفق</span>
                    <span class="i8-stat-value"><?php echo i8_report_to_persian_num($success_count); ?> رویداد</span>
                </div>
            </div>
            <div class="i8-stat-card">
                <div class="i8-stat-icon i8-bg-danger">❌</div>
                <div class="i8-stat-info">
                    <span class="i8-stat-label">عملیات ناموفق (خطا)</span>
                    <span class="i8-stat-value"><?php echo i8_report_to_persian_num($failed_count); ?> رویداد</span>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="get" action="" class="i8-filter-form">
            <input type="hidden" name="page" value="publisher-copilot-report">
            
            <div class="i8-filter-group">
                <label for="filter_action">نوع عملیات:</label>
                <select name="filter_action" id="filter_action">
                    <option value="">همه عملیات‌ها</option>
                    <?php foreach ($groups as $key => $group_info): ?>
                        <option value="group:<?php echo esc_attr($key); ?>" <?php selected($filter_action, 'group:' . $key); ?>>
                            ⭐ <?php echo esc_html($group_info['label']); ?> (کل گروه)
                        </option>
                        <optgroup label="<?php echo esc_attr($group_info['label']); ?>">
                            <?php 
                            $group_actions = array_intersect($group_info['actions'], $unique_actions);
                            foreach ($group_actions as $act): 
                            ?>
                                <option value="<?php echo esc_attr($act); ?>" <?php selected($filter_action, $act); ?>>
                                    <?php echo esc_html($act); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                    
                    <?php
                    $grouped_actions_flat = array();
                    foreach ($groups as $g) {
                        $grouped_actions_flat = array_merge($grouped_actions_flat, $g['actions']);
                    }
                    $other_actions = array_diff($unique_actions, $grouped_actions_flat);
                    if (!empty($other_actions)):
                    ?>
                        <optgroup label="سایر موارد">
                            <?php foreach ($other_actions as $act): ?>
                                <option value="<?php echo esc_attr($act); ?>" <?php selected($filter_action, $act); ?>>
                                    <?php echo esc_html($act); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
            </div>

            <div class="i8-filter-group">
                <label for="filter_status">وضعیت:</label>
                <select name="filter_status" id="filter_status">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="1" <?php selected($filter_status, '1'); ?>>موفق</option>
                    <option value="0" <?php selected($filter_status, '0'); ?>>ناموفق</option>
                </select>
            </div>

            <button type="submit" class="i8-btn i8-btn-primary" style="padding: 6px 16px; font-size: 13px;">🔍 اعمال فیلتر</button>
            
            <?php if ($filter_status !== '' || $filter_action !== ''): ?>
                <a href="<?php echo admin_url('admin.php?page=publisher-copilot-report'); ?>" class="i8-btn" style="padding: 6px 16px; font-size: 13px; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;">❌ پاک کردن فیلتر</a>
            <?php endif; ?>
        </form>

        <!-- Reports Card -->
        <div class="i8-card">
            <div class="i8-table-wrapper">
                <table class="i8-table">
                    <thead>
                        <tr>
                            <th>تاریخ وقوع</th>
                            <th>عنوان عملیات</th>
                            <th>وضعیت</th>
                            <th>توضیحات / پیغام خطا</th>
                            <th>پست مربوطه</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($filtered_reports)): ?>
                            <?php foreach ($filtered_reports as $report): 
                                $status_class = ($report['status'] == 1) ? 'i8-badge-success' : 'i8-badge-danger';
                                $status_text = ($report['status'] == 1) ? 'موفق' : 'ناموفق';
                                
                                // قالب‌بندی نمایش تاریخ شمسی
                                $date_display = $report['pub_date'];
                                if (class_exists('i8_jDateTime')) {
                                    $tz = wp_timezone();
                                    $tz_name = $tz ? $tz->getName() : 'Asia/Tehran';
                                    $timestamp = strtotime($report['pub_date'] . ' UTC');
                                    if ($timestamp) {
                                        $jdate = new i8_jDateTime(true, true, $tz_name);
                                        $date_display = $jdate->date('Y/m/d H:i:s', $timestamp);
                                    }
                                }
                            ?>
                                <tr>
                                    <td style="direction: ltr; text-align: right;"><?php echo i8_report_to_persian_num($date_display); ?></td>
                                    <td><strong><?php echo safe_esc_html($report['action_title']); ?></strong></td>
                                    <td>
                                        <span class="i8-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td>
                                        <span style="font-size: 13px; color: #64748b;">
                                            <?php echo i8_report_to_persian_num(safe_esc_html($report['error_msg'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($report['action_title'] === 'انتشار پست زمان‌بندی شده') {
                                            $post_id = intval($report['resource_id']);
                                            $post_title = '';
                                            $post_link = '';
                                            if ($post_id > 0) {
                                                $post = get_post($post_id);
                                                if ($post) {
                                                    $post_title = get_the_title($post);
                                                    $post_link = get_edit_post_link($post_id);
                                                    if (!$post_link) {
                                                        $post_link = get_permalink($post_id);
                                                    }
                                                }
                                            }
                                            
                                            // در صورتی که پست حذف شده باشد اما عنوان یا لینک آن در فیلد ذخیره شده باشد، از آن استفاده می‌شود
                                            if (empty($post_title) && !empty($report['resource_name'])) {
                                                if (filter_var($report['resource_name'], FILTER_VALIDATE_URL)) {
                                                    $post_title = $report['resource_name'];
                                                    $post_link = $report['resource_name'];
                                                } else {
                                                    $post_title = $report['resource_name'];
                                                    $post_link = '';
                                                }
                                            }

                                            if (!empty($post_title)) {
                                                $truncated_title = $post_title;
                                                if (mb_strlen($post_title, 'UTF-8') > 50) {
                                                    $truncated_title = mb_substr($post_title, 0, 50, 'UTF-8') . '...';
                                                }
                                                if (!empty($post_link)) {
                                                    echo '<a href="' . esc_url($post_link) . '" class="i8-link" target="_blank" title="' . esc_attr($post_title) . '">🔗 ' . esc_html($truncated_title) . '</a>';
                                                } else {
                                                    echo esc_html($truncated_title);
                                                }
                                            } else {
                                                echo '-';
                                            }
                                        } else {
                                            // برای رکوردهای مربوط به خزیدن فید و غیره، این ستون کاملاً خالی می‌ماند
                                            echo '';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: #64748b;">
                                    📭 هیچ گزارش یا رویدادی منطبق با فیلترهای انتخابی یافت نشد.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php
}
