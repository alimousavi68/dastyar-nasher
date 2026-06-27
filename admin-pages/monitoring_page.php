<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Hook to register submenu page
add_action('admin_menu', 'i8_add_monitoring_submenu_page', 16);

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
    if (!function_exists('i8_to_persian_num')) {
        function i8_to_persian_num($number) {
            $english_digits = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
            $persian_digits = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
            return str_replace($english_digits, $persian_digits, (string)$number);
        }
    }

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
    <link rel="stylesheet" href="<?php echo COP_PLUGIN_URL; ?>/assets/css/feed_list.css">
    <link rel="stylesheet" href="<?php echo COP_PLUGIN_URL; ?>/assets/css/bootstrap.rtl.min.css">
    
    <style>
        .app-container {
            max-width: 100% !important;
            margin: 20px 10px;
        }
        .stat-card-custom {
            border: 1px solid var(--tw-slate-200);
            border-radius: 16px;
            padding: 20px;
            background: #ffffff;
            box-shadow: var(--tw-shadow-sm);
            transition: all 0.2s ease;
        }
        .stat-card-custom:hover {
            transform: translateY(-2px);
            box-shadow: var(--tw-shadow-md);
        }
        .trace-box-terminal {
            background: #0f172a !important;
            color: #38bdf8 !important;
            font-family: monospace;
            font-size: 11.5px !important;
            padding: 16px;
            border-radius: 12px;
            overflow-x: auto;
            max-height: 250px;
            border: 1px solid #1e293b;
            direction: ltr;
            text-align: left;
            margin-top: 8px;
        }
        .details-custom summary {
            cursor: pointer;
            color: #4f46e5;
            font-weight: 600;
            font-size: 13px;
            list-style: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            user-select: none;
            outline: none;
        }
        .details-custom summary::-webkit-details-marker {
            display: none;
        }
        .details-custom[open] summary {
            color: #312e81;
        }
        
        .btn-mon-primary {
            background-color: #eff6ff !important;
            color: #1d4ed8 !important;
            border: 1px solid #bfdbfe !important;
            border-radius: 12px !important;
            padding: 10px 18px !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            transition: all 0.2s ease !important;
            box-shadow: none !important;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-mon-primary:hover {
            background-color: #1d4ed8 !important;
            color: #ffffff !important;
            border-color: #1d4ed8 !important;
            transform: translateY(-1px);
        }
        .btn-mon-primary svg {
            transition: transform 0.2s ease;
        }
        .btn-mon-primary:hover svg {
            transform: scale(1.1);
        }
    </style>

    <div class="app-container">
        <!-- Header -->
        <div class="sticky-header-bar d-flex flex-wrap flex-xl-nowrap align-items-center justify-content-between gap-3 p-3 mb-4 shadow-sm-soft border bg-white rounded-xl" style="position: sticky; top: 32px; z-index: 100;">
            <div class="d-flex align-items-center gap-2 pe-xl-3">
                <div class="radar-container me-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="radar-scanner-icon">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                    <span class="radar-pulse-ring"></span>
                </div>
                <div>
                    <span class="fs-5 fw-bolder text-slate-800" style="white-space: nowrap;">مانیتورینگ و پایش خودکار منابع</span>
                    <div class="text-slate-500" style="font-size: 12px; margin-top: 2px;">بررسی زنده و ۲۴ ساعته فیدهای RSS و سلکتورهای استخراج محتوا</div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" class="m-0">
                    <input type="hidden" name="action" value="i8_manual_trigger_all_monitoring">
                    <?php wp_nonce_field('i8_manual_trigger_all_monitoring'); ?>
                    <button type="submit" class="btn btn-mon-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M5.52.359A.5.5 0 0 1 6 0h4a.5.5 0 0 1 .474.658L8.694 6H12.5a.5.5 0 0 1 .395.807l-7 9a.5.5 0 0 1-.873-.454L6.823 9.5H3.5a.5.5 0 0 1-.48-.641l2.5-8.5z"/>
                        </svg>
                        اجرای مانیتورینگ بر روی تمام منابع
                    </button>
                </form>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card-custom d-flex align-items-center gap-3">
                    <div class="i8-stat-icon d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(79, 70, 229, 0.1); color: #4f46e5;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 11a9 9 0 0 1 9 9" />
                            <path d="M4 4a16 16 0 0 1 16 16" />
                            <circle cx="5" cy="19" r="1" />
                        </svg>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="text-slate-500" style="font-size: 13px; font-weight: 500;">کل منابع فعال</span>
                        <span class="text-slate-800 fw-bolder mt-1" style="font-size: 18px;"><?php echo i8_to_persian_num(count($all_resources)); ?> منبع</span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card-custom d-flex align-items-center gap-3">
                    <div class="i8-stat-icon d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                            <line x1="12" y1="9" x2="12" y2="13" />
                            <line x1="12" y1="17" x2="12.01" y2="17" />
                        </svg>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="text-slate-500" style="font-size: 13px; font-weight: 500;">خطاهای فعال (دائمی)</span>
                        <span class="text-slate-800 fw-bolder mt-1" style="font-size: 18px;"><?php echo i8_to_persian_num(count($active_errors)); ?> مورد</span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card-custom d-flex align-items-center gap-3">
                    <div class="i8-stat-icon d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <polyline points="12 6 12 12 16 14" />
                        </svg>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="text-slate-500" style="font-size: 13px; font-weight: 500;">در حال بررسی مجدد</span>
                        <span class="text-slate-800 fw-bolder mt-1" style="font-size: 18px;"><?php echo i8_to_persian_num(count($pending_retries)); ?> مورد</span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="stat-card-custom d-flex align-items-center gap-3">
                    <div class="i8-stat-icon d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            <path d="m9 11 2 2 4-4" />
                        </svg>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="text-slate-500" style="font-size: 13px; font-weight: 500;">منابع بدون خطا</span>
                        <span class="text-slate-800 fw-bolder mt-1" style="font-size: 18px;"><?php echo i8_to_persian_num(count($all_resources) - count($active_errors)); ?> منبع</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Errors Card -->
        <div class="mb-4">
            <h2 class="text-slate-800 fw-bolder fs-5 mb-3 d-flex align-items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                    <line x1="12" y1="9" x2="12" y2="13" />
                    <line x1="12" y1="17" x2="12.01" y2="17" />
                </svg>
                خطاهای فعال گزارش‌شده در منابع
            </h2>
            
            <div class="table-body">
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
                        <div class="table-item d-flex flex-column gap-3">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <div class="d-flex align-items-center gap-3 flex-grow-1 min-w-0">
                                    <div style="width: 4px; height: 50px; background-color: #ef4444; border-radius: 4px; flex-shrink: 0;"></div>
                                    
                                    <div class="d-flex flex-column gap-2 min-w-0">
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <span class="fs-6 fw-bold text-slate-800"><?php echo esc_html($err['resource_name']); ?></span>
                                            <span class="badge-status-draft" style="background-color: #fee2e2; color: #dc2626; border-color: #fca5a5; font-size: 11px; padding: 3px 8px; font-weight: bold;"><?php echo esc_html($err['error_type']); ?></span>
                                            
                                            <span class="badge-status-published" style="background-color: #f0fdf4; color: #15803d; border-color: #bbf7d0; font-size: 11px; padding: 3px 8px;" title="منبع برای پاک شدن خودکار از جدول خطاها، نیاز به ۲ تست موفقیت‌آمیز متوالی دارد.">
                                                موفقیت پیاپی: <?php echo i8_to_persian_num(intval($err['consecutive_success_count'])); ?> / ۲
                                            </span>
                                        </div>
                                        
                                        <div class="text-slate-600 fw-medium" style="font-size: 13.5px;"><?php echo esc_html($err['error_message']); ?></div>
                                        
                                        <div class="d-flex align-items-center gap-1 text-slate-400" style="font-size: 12px;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                                                <circle cx="12" cy="12" r="10" />
                                                <polyline points="12 6 12 12 16 14" />
                                            </svg>
                                            <span>تاریخ وقوع:</span>
                                            <span style="direction: ltr;"><?php echo esc_html($date_display); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <?php if (!empty($err['stack_trace'])): ?>
                                        <button type="button" class="btn toggle-trace-btn d-flex align-items-center gap-2" data-target="trace-<?php echo esc_attr($err['resource_id']); ?>" style="border-radius: 10px; font-size: 12px; font-weight: bold; background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 8px 14px; transition: all 0.2s ease; outline: none; box-shadow: none;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="10"/>
                                                <line x1="12" y1="16" x2="12" y2="12"/>
                                                <line x1="12" y1="8" x2="12.01" y2="8"/>
                                            </svg>
                                            مشاهده جزئیات خطا
                                        </button>
                                    <?php endif; ?>
                                    
                                    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=i8_manual_check_resource&resource_id=' . $err['resource_id']), 'i8_manual_check_resource'); ?>" class="btn d-flex align-items-center gap-2" style="border-radius: 10px; font-size: 12px; font-weight: bold; background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 8px 14px; transition: all 0.2s ease;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M23 4v6h-6"></path>
                                            <path d="M20.49 15a10 10 0 1 1-2.12-9.36L23 10"></path>
                                        </svg>
                                        بررسی مجدد
                                    </a>
                                </div>
                            </div>

                            <!-- Full Width Stack Trace Box -->
                            <?php if (!empty($err['stack_trace'])): ?>
                                <div id="trace-<?php echo esc_attr($err['resource_id']); ?>" style="display: none; border-top: 1px solid var(--tw-slate-200); padding-top: 10px;">
                                    <pre class="trace-box-terminal"><?php echo esc_html($err['stack_trace']); ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5 bg-white border rounded-xl shadow-sm-soft">
                        <div style="font-size: 48px; margin-bottom: 12px;">💚</div>
                        <h3 class="fw-bold text-slate-800 fs-5">هیچ خطای فعالی در منابع خبری گزارش نشده است!</h3>
                        <p class="text-slate-500 mb-0 mt-2">تمامی سلکتورها و اتصالات منابع خبری به درستی کار می‌کنند.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pending Retries Section -->
        <?php if (!empty($pending_retries)): ?>
            <div class="mt-5 mb-4">
                <h2 class="text-slate-800 fw-bolder fs-5 mb-3 d-flex align-items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" />
                        <polyline points="12 6 12 12 16 14" />
                    </svg>
                    بررسی‌های موقت در حال تکرار (تشخیص خطاهای مقطعی)
                </h2>
                <p class="text-slate-500" style="font-size: 13px; margin-bottom: 20px;">
                    این منابع اخیراً با خطا مواجه شده‌اند اما برای اطمینان از عدم مقطعی بودن خطا، تا ۳ بار در فواصل ۱۰ دقیقه‌ای تست خواهند شد.
                </p>
                <div class="table-body">
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
                        <div class="table-item d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3 flex-grow-1 min-w-0">
                                <div style="width: 4px; height: 50px; background-color: #f59e0b; border-radius: 4px; flex-shrink: 0;"></div>
                                
                                <div class="d-flex flex-column gap-2 min-w-0">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="fs-6 fw-bold text-slate-800"><?php echo esc_html($err['resource_name']); ?></span>
                                        <span class="badge-status-draft" style="background-color: #fffbeb; color: #d97706; border-color: #fef08a; font-size: 11px; padding: 3px 8px; font-weight: bold;"><?php echo esc_html($err['error_type']); ?></span>
                                        <span class="badge-status-published" style="background-color: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; font-size: 11px; padding: 3px 8px;">
                                            تعداد تلاش: <?php echo i8_to_persian_num(intval($err['retry_count'])); ?> / ۳
                                        </span>
                                    </div>
                                    <div class="text-slate-600 fw-medium" style="font-size: 13.5px;"><?php echo esc_html($err['error_message']); ?></div>
                                    <div class="d-flex align-items-center gap-1 text-slate-400" style="font-size: 12px;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                                            <circle cx="12" cy="12" r="10" />
                                            <polyline points="12 6 12 12 16 14" />
                                        </svg>
                                        <span>آخرین بررسی:</span>
                                        <span style="direction: ltr;"><?php echo esc_html($date_display); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=i8_manual_check_resource&resource_id=' . $err['resource_id']), 'i8_manual_check_resource'); ?>" class="btn d-flex align-items-center gap-2" style="border-radius: 10px; font-size: 12px; font-weight: bold; background-color: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 8px 14px; transition: all 0.2s ease;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M23 4v6h-6"></path>
                                        <path d="M20.49 15a10 10 0 1 1-2.12-9.36L23 10"></path>
                                    </svg>
                                    بررسی آنی
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        jQuery(document).ready(function ($) {
            $('.toggle-trace-btn').on('click', function(e) {
                e.preventDefault();
                var targetId = $(this).data('target');
                $('#' + targetId).slideToggle(200);
            });
        });
    </script>
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

