<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Hook to register submenu page
add_action('admin_menu', 'i8_add_monitoring_submenu_page');

function i8_add_monitoring_submenu_page()
{
    add_submenu_page(
        'publisher_copoilot',
        'مانیتورینگ منابع',
        'مانیتورینگ منابع',
        'manage_options',
        'publisher-copilot-monitoring',
        'display_monitoring_page'
    );
}

// Admin post action to manually trigger check
add_action('admin_post_i8_manual_check_resource', 'i8_manual_check_resource_handler');

function i8_manual_check_resource_handler()
{
    // Security check
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'i8_manual_check_resource')) {
        wp_die(__('درخواست غیرمعتبر.', 'textdomain'));
    }

    if (!current_user_can('manage_options')) {
        wp_die(__('شما دسترسی لازم برای این کار را ندارید.', 'textdomain'));
    }

    $resource_id = isset($_GET['resource_id']) ? intval($_GET['resource_id']) : 0;
    if ($resource_id > 0) {
        // Run test directly
        i8_process_resource_check($resource_id);
    }

    // Redirect back
    wp_redirect(admin_url('admin.php?page=publisher-copilot-monitoring&status=checked'));
    exit;
}

function display_monitoring_page()
{
    global $wpdb;
    $table_errors = $wpdb->prefix . 'i8_monitoring_errors';
    
    // Fetch all monitoring records
    $errors = $wpdb->get_results("SELECT * FROM $table_errors ORDER BY first_occurrence DESC", ARRAY_A) ?: array();
    
    $active_errors = array();
    $pending_retries = array();
    
    foreach ($errors as $err) {
        if ($err['status'] == 'active_error') {
            $active_errors[] = $err;
        } else {
            $pending_retries[] = $err;
        }
    }
    
    // Toast notifications
    if (isset($_GET['status']) && $_GET['status'] == 'checked') {
        echo '<div class="notice notice-success is-dismissible"><p>بررسی منبع با موفقیت انجام شد.</p></div>';
    } elseif (isset($_GET['status']) && $_GET['status'] == 'enqueued') {
        echo '<div class="notice notice-success is-dismissible"><p>بررسی منابع به صف وظایف پس‌زمینه (Action Scheduler) منتقل شد تا بدون هیچ‌گونه کندی در پنل مدیریت، به مرور در پس‌زمینه اجرا گردد.</p></div>';
    }

    
    // Get all resource details for dropdown (optional if they want to check manually)
    $all_resources = get_resources_details() ?: array();
    
    ?>
    <style>
        :root {
            --i8-primary: #4f46e5;
            --i8-primary-hover: #4338ca;
            --i8-success: #10b981;
            --i8-danger: #ef4444;
            --i8-warning: #f59e0b;
            --i8-neutral-50: #f8fafc;
            --i8-neutral-100: #f1f5f9;
            --i8-neutral-800: #1e293b;
            --i8-glass-bg: rgba(255, 255, 255, 0.75);
            --i8-glass-border: rgba(226, 232, 240, 0.8);
        }

        .i8-mon-wrap {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            margin: 20px 20px 0 0;
            max-width: 98%;
            direction: rtl;
        }

        .i8-mon-header {
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
            font-size: 13px;
            font-weight: 600;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none !important;
            white-space: nowrap;
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

        .i8-btn-secondary {
            background: var(--i8-neutral-100);
            color: var(--i8-neutral-800);
            border: 1px solid #cbd5e1;
        }

        .i8-btn-secondary:hover {
            background: #e2e8f0;
        }

        /* Stats Grid */
        .i8-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
        .i8-bg-warning { background: rgba(245, 158, 11, 0.1); color: var(--i8-warning); }

        /* Container Card */
        .i8-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            padding: 24px;
            margin-bottom: 24px;
        }

        .i8-card-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--i8-neutral-800);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Table */
        .i8-table-wrapper {
            overflow-x: auto;
        }

        .i8-table {
            width: 100%;
            border-collapse: collapse;
            text-align: right;
        }

        .i8-table th {
            padding: 14px 16px;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            border-bottom: 2px solid #f1f5f9;
        }

        .i8-table td {
            padding: 16px;
            font-size: 13px;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .i8-table th:nth-child(6),
        .i8-table td:nth-child(6) {
            min-width: 170px;
        }

        .i8-trace-details summary {
            white-space: nowrap;
            outline: none;
        }


        .i8-table tbody tr:hover {
            background: #f8fafc;
        }

        /* Badges */
        .i8-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 6px;
        }

        .i8-badge-success { background: rgba(16, 185, 129, 0.1); color: var(--i8-success); }
        .i8-badge-danger { background: rgba(239, 68, 68, 0.1); color: var(--i8-danger); }
        .i8-badge-warning { background: rgba(245, 158, 11, 0.1); color: var(--i8-warning); }

        .i8-trace-details {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 6px 10px;
            max-width: 450px;
            transition: all 0.2s ease;
        }

        .i8-trace-details[open] {
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .trace-box {
            background: #0f172a;
            color: #38bdf8;
            font-family: monospace;
            font-size: 11px;
            padding: 12px;
            border-radius: 8px;
            overflow-x: auto;
            max-width: 420px;
            max-height: 120px;
            white-space: pre-wrap;
            direction: ltr;
            text-align: left;
        }


        .healthy-box {
            text-align: center;
            padding: 50px 20px;
        }

        .healthy-icon {
            font-size: 64px;
            margin-bottom: 16px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>

    <div class="wrap i8-mon-wrap">
        <!-- Header -->
        <div class="i8-mon-header">
            <div class="i8-title-sec">
                <h1>مانیتورینگ و پایش خودکار منابع</h1>
                <p class="description">بررسی زنده و ۲۴ ساعته فیدهای RSS و سلکتورهای استخراج محتوا</p>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="i8-stats-grid">
            <div class="i8-stat-card">
                <div class="i8-stat-icon i8-bg-primary">📡</div>
                <div class="i8-stat-info">
                    <span class="i8-stat-label">کل منابع فعال</span>
                    <span class="i8-stat-value"><?php echo count($all_resources); ?> منبع</span>
                </div>
            </div>
            <div class="i8-stat-card">
                <div class="i8-stat-icon i8-bg-danger">❌</div>
                <div class="i8-stat-info">
                    <span class="i8-stat-label">خطاهای فعال (دائمی)</span>
                    <span class="i8-stat-value"><?php echo count($active_errors); ?> مورد</span>
                </div>
            </div>
            <div class="i8-stat-card">
                <div class="i8-stat-icon i8-bg-warning">⏳</div>
                <div class="i8-stat-info">
                    <span class="i8-stat-label">در حال بررسی مجدد (موقتی)</span>
                    <span class="i8-stat-value"><?php echo count($pending_retries); ?> مورد</span>
                </div>
            </div>
            <div class="i8-stat-card">
                <div class="i8-stat-icon i8-bg-success">🛡️</div>
                <div class="i8-stat-info">
                    <span class="i8-stat-label">منابع بدون خطا</span>
                    <span class="i8-stat-value"><?php echo count($all_resources) - count($active_errors); ?> منبع</span>
                </div>
            </div>
        </div>

        <!-- Active Errors Card -->
        <div class="i8-card">
            <div class="i8-card-title">🚨 خطاهای فعال گزارش‌شده در منابع</div>
            <div class="i8-table-wrapper">
                <table class="i8-table">
                    <thead>
                        <tr>
                            <th>نام منبع</th>
                            <th>نوع خطا</th>
                            <th>علت و پیام خطا</th>
                            <th>تاریخ وقوع</th>
                            <th>موفقیت پیاپی</th>
                            <th>استک تریس دیباگ</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($active_errors)): ?>
                            <?php foreach ($active_errors as $err): 
                                $date_display = $err['first_occurrence'];
                                if (class_exists('i8_jDateTime')) {
                                    $jdate = new i8_jDateTime(true, true, 'Asia/Tehran');
                                    $tz = wp_timezone();
                                    $tz_name = $tz ? $tz->getName() : 'Asia/Tehran';
                                    $timestamp = strtotime($err['first_occurrence'] . ' ' . $tz_name);
                                    if ($timestamp) {
                                        $date_display = $jdate->date('Y/m/d H:i:s', $timestamp);
                                    }
                                }
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html($err['resource_name']); ?></strong></td>
                                    <td><span class="i8-badge i8-badge-danger"><?php echo esc_html($err['error_type']); ?></span></td>
                                    <td><span style="color: #64748b; font-weight: 500;"><?php echo esc_html($err['error_message']); ?></span></td>
                                    <td style="direction: ltr; text-align: right;"><?php echo esc_html($date_display); ?></td>
                                    <td>
                                        <span class="i8-badge i8-badge-success" title="منبع برای پاک شدن خودکار از جدول خطاها، نیاز به ۲ تست موفقیت‌آمیز متوالی دارد." style="cursor: help;">
                                            <?php echo intval($err['consecutive_success_count']); ?> / ۲ ⓘ
                                        </span>
                                    </td>
                                    <td>
                                        <details class="i8-trace-details">
                                            <summary style="cursor: pointer; color: var(--i8-primary); font-weight: 600; font-size: 12px; outline: none; user-select: none;">🔍 مشاهده جزئیات خطا</summary>
                                            <pre class="trace-box" style="margin-top: 8px;"><?php echo esc_html($err['stack_trace']); ?></pre>
                                        </details>
                                    </td>

                                    <td>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=i8_manual_check_resource&resource_id=' . $err['resource_id']), 'i8_manual_check_resource'); ?>" class="i8-btn i8-btn-primary">
                                            🔄 بررسی مجدد
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="healthy-box">
                                        <div class="healthy-icon">💚</div>
                                        <h3>هیچ خطای فعالی در منابع خبری گزارش نشده است!</h3>
                                        <p style="color: #64748b;">تمامی سلکتورها و اتصالات منابع خبری به درستی کار می‌کنند.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pending Retries Card -->
        <?php if (!empty($pending_retries)): ?>
            <div class="i8-card" style="border-top: 4px solid var(--i8-warning);">
                <div class="i8-card-title">⏳ بررسی‌های موقت در حال تکرار (تشخیص خطاهای مقطعی)</div>
                <p style="color: #64748b; font-size: 13px; margin-bottom: 20px;">
                    این منابع اخیراً با خطا مواجه شده‌اند اما برای اطمینان از عدم مقطعی بودن خطا، تا ۳ بار در فواصل ۱۰ دقیقه‌ای تست خواهند شد.
                </p>
                <div class="i8-table-wrapper">
                    <table class="i8-table">
                        <thead>
                            <tr>
                                <th>نام منبع</th>
                                <th>نوع خطا</th>
                                <th>علت خطا</th>
                                <th>تعداد تلاش مجدد</th>
                                <th>آخرین بررسی</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                             <?php foreach ($pending_retries as $err): 
                                 $date_display = $err['last_checked'];
                                 if (class_exists('i8_jDateTime')) {
                                     $jdate = new i8_jDateTime(true, true, 'Asia/Tehran');
                                     $tz = wp_timezone();
                                     $tz_name = $tz ? $tz->getName() : 'Asia/Tehran';
                                     $timestamp = strtotime($err['last_checked'] . ' ' . $tz_name);
                                     if ($timestamp) {
                                         $date_display = $jdate->date('Y/m/d H:i:s', $timestamp);
                                     }
                                 }
                             ?>
                                <tr>
                                    <td><strong><?php echo esc_html($err['resource_name']); ?></strong></td>
                                    <td><span class="i8-badge i8-badge-warning"><?php echo esc_html($err['error_type']); ?></span></td>
                                    <td style="color: #64748b;"><?php echo esc_html($err['error_message']); ?></td>
                                    <td><strong><?php echo intval($err['retry_count']); ?> / ۳</strong></td>
                                    <td style="direction: ltr; text-align: right;"><?php echo esc_html($date_display); ?></td>
                                    <td>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=i8_manual_check_resource&resource_id=' . $err['resource_id']), 'i8_manual_check_resource'); ?>" class="i8-btn i8-btn-secondary">
                                            🔄 بررسی آنی
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Manual Check All Section -->
        <div class="i8-card">
            <div class="i8-card-title">🛠️ تست گروهی منابع</div>
            <p style="color: #64748b; font-size: 13px; margin-bottom: 20px;">
                شما می‌توانید بررسی کل منابع خبری را به صورت دستی و در لحظه برای تست آغاز کنید.
            </p>
            <form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
                <input type="hidden" name="action" value="i8_manual_trigger_all_monitoring">
                <?php wp_nonce_field('i8_manual_trigger_all_monitoring'); ?>
                <button type="submit" class="i8-btn i8-btn-primary">⚡ اجرای مانیتورینگ بر روی تمام منابع</button>
            </form>
        </div>
    </div>
    <?php
}

// Add manual check all action handler
add_action('admin_post_i8_manual_trigger_all_monitoring', 'i8_manual_trigger_all_monitoring_handler');
function i8_manual_trigger_all_monitoring_handler()
{
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'i8_manual_trigger_all_monitoring')) {
        wp_die(__('درخواست غیرمعتبر.', 'textdomain'));
    }

    if (!current_user_can('manage_options')) {
        wp_die(__('شما دسترسی لازم برای این کار را ندارید.', 'textdomain'));
    }

    i8_run_daily_monitoring();

    wp_redirect(admin_url('admin.php?page=publisher-copilot-monitoring&status=enqueued'));
    exit;
}

