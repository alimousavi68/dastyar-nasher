<?php

defined('ABSPATH') || exit;

// ایجاد صفحه تنظیمات
function publisher_copoilot_setting_page_callback() {
    $updated = false;
    $license_updated = false;
    
    // ۱. پردازش ذخیره لایسنس
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cop_secret_code'])) {
        $secret_code = sanitize_text_field($_POST['cop_secret_code']);
        update_option('i8_secret_code', $secret_code);
        $license_updated = true;
    }
    
    // ۲. پردازش تنظیمات انتشار
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['publisher_copoilot_settings_group_nonce'])) {
        if (!wp_verify_nonce($_POST['publisher_copoilot_settings_group_nonce'], 'publisher_copoilot_settings_action')) {
            wp_die('عدم دسترسی به دلیل مشکلات امنیتی.');
        }

        $old_daily_post_count = get_option('daily_post_count');
        $new_daily_post_count = isset($_POST['daily_post_count']) ? intval($_POST['daily_post_count']) : 30;
        if ($new_daily_post_count <= 0) $new_daily_post_count = 1;
        update_option('daily_post_count', $new_daily_post_count);

        $old_start_cron_time = get_option('start_cron_time');
        $old_end_cron_time = get_option('end_cron_time');
        $new_start_cron_time = isset($_POST['start_cron_time']) ? sanitize_text_field($_POST['start_cron_time']) : '08:00';
        $new_end_cron_time = isset($_POST['end_cron_time']) ? sanitize_text_field($_POST['end_cron_time']) : '22:00';
        
        update_option('start_cron_time', $new_start_cron_time);
        update_option('end_cron_time', $new_end_cron_time);

        // بررسی تغییرات و فراخوانی اکشن بازنشانی
        if ($old_daily_post_count != $new_daily_post_count || $old_start_cron_time != $new_start_cron_time || $old_end_cron_time != $new_end_cron_time) {
            do_action('i8_action_rebuild_queue'); 
            error_log('i8: settings page: queue settings changed, rebuilding queue.');
        }
        $updated = true;
    }

    $daily_post_count = get_option('daily_post_count', 30);
    $start_cron_time = get_option('start_cron_time', '08:00');
    $end_cron_time = get_option('end_cron_time', '22:00');
    
    // دریافت اطلاعات وضعیت لایسنس
    $old_secret_code = get_option('i8_secret_code');
    $response = false;
    if ($old_secret_code) {
        $response = send_license_validation_request($old_secret_code);
    }

    // Calculations for preview
    $start_parts = explode(':', $start_cron_time);
    $end_parts = explode(':', $end_cron_time);
    $start_seconds = isset($start_parts[0], $start_parts[1]) ? ($start_parts[0] * 3600 + $start_parts[1] * 60) : 0;
    $end_seconds = isset($end_parts[0], $end_parts[1]) ? ($end_parts[0] * 3600 + $end_parts[1] * 60) : 0;
    $interval = ($end_seconds <= $start_seconds) ? (24*3600 - $start_seconds + $end_seconds) : ($end_seconds - $start_seconds);
    $interval_hours = round($interval/3600, 1);
    $post_interval = ($interval_hours > 0 && $daily_post_count > 0) ? round(($interval_hours*60)/$daily_post_count, 1) : 0;
    ?>
    <link rel="stylesheet" href="<?php echo COP_PLUGIN_URL; ?>/assets/css/feed_list.css">
    <link rel="stylesheet" href="<?php echo COP_PLUGIN_URL; ?>/assets/css/bootstrap.rtl.min.css">
    
    <style>
        .app-container {
            max-width: 100% !important;
            margin: 20px 10px;
            direction: rtl !important;
            text-align: right !important;
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
        .i8-settings-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
        }
        @media (min-width: 1024px) {
            .i8-settings-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        .i8-card {
            background: #ffffff;
            border: 1px solid var(--tw-slate-200);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--tw-shadow-sm);
        }
        .i8-card-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--tw-slate-800);
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid var(--tw-slate-200);
            padding-bottom: 12px;
        }
        .i8-form-group {
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid var(--tw-slate-200);
            transition: all 0.2s ease;
        }
        .i8-form-group:hover {
            border-color: var(--tw-slate-300);
            box-shadow: var(--tw-shadow-sm);
        }
        .i8-label-col {
            flex: 1;
            padding-left: 12px;
        }
        .i8-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--tw-slate-800);
            margin-bottom: 4px;
        }
        .i8-description {
            font-size: 12px;
            color: var(--tw-slate-500);
        }
        .i8-input-col {
            width: 160px;
            display: flex;
            justify-content: flex-end;
        }
        .i8-input {
            width: 100%;
            padding: 8px 12px;
            font-size: 14px;
            border: 1px solid var(--tw-slate-200);
            border-radius: 8px;
            outline: none;
            transition: all 0.2s ease;
            text-align: center;
            font-weight: 600;
            color: var(--tw-slate-800);
            background: var(--tw-slate-50);
        }
        .i8-input:focus {
            border-color: #4f46e5;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        .i8-preview-box {
            background: linear-gradient(to left, #f8fafc, #f1f5f9);
            border-radius: 12px;
            padding: 20px;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--tw-slate-200);
        }
        .i8-timeline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 24px;
            position: relative;
            padding: 0 10px;
        }
        .i8-timeline::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 10px;
            right: 10px;
            height: 4px;
            background: var(--tw-slate-200);
            z-index: 1;
            border-radius: 4px;
            transform: translateY(-50%);
        }
        .i8-time-node {
            position: relative;
            z-index: 2;
            background: #ffffff;
            border: 3px solid #4f46e5;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            box-shadow: 0 0 0 4px rgba(255,255,255,0.8);
        }
        .i8-time-node::after {
            content: attr(data-time);
            position: absolute;
            bottom: -24px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 11px;
            font-weight: 700;
            color: var(--tw-slate-700);
            white-space: nowrap;
        }
        .i8-stats-row {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
        }
        .i8-stat-badge {
            background: #ffffff;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #4f46e5;
            box-shadow: var(--tw-shadow-sm);
            border: 1px solid var(--tw-slate-200);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
    </style>

    <?php
    if (!function_exists('i8_setting_to_persian_num')) {
        function i8_setting_to_persian_num($number) {
            $english_digits = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
            $persian_digits = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
            return str_replace($english_digits, $persian_digits, (string)$number);
        }
    }
    ?>

    <div class="app-container">
        <!-- Header -->
        <div class="sticky-header-bar d-flex flex-wrap flex-xl-nowrap align-items-center justify-content-between gap-3 p-3 mb-4 shadow-sm-soft border bg-white rounded-xl" style="position: sticky; top: 32px; z-index: 100; direction: rtl !important;">
            <div class="d-flex align-items-center gap-2 pe-xl-3">
                <div class="radar-container me-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="radar-scanner-icon">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                    </svg>
                    <span class="radar-pulse-ring"></span>
                </div>
                <div>
                    <span class="fs-5 fw-bolder text-slate-800" style="white-space: nowrap;">تنظیمات و لایسنس دستیار ناشر</span>
                    <div class="text-slate-500" style="font-size: 12px; margin-top: 2px;">پیکربندی هوشمند زمان‌بندی، انتشار خودکار مطالب و مدیریت وضعیت فعال‌سازی افزونه</div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <?php if ($response == true): ?>
                    <span class="badge-status-published fs-7 px-3 py-2 fw-bold" style="background-color: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0;">لایسنس فعال و معتبر است</span>
                <?php else: ?>
                    <span class="badge-status-draft fs-7 px-3 py-2 fw-bold" style="background-color: #fee2e2; color: #dc2626; border: 1px solid #fca5a5;">کد لایسنس نامعتبر یا منقضی شده است</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Success/Error Alerts -->
        <?php if ($updated): ?>
            <div class="d-flex align-items-center gap-2 p-3 mb-4 rounded-xl" style="background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; font-weight: 600; font-size: 14px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span>تنظیمات با موفقیت ذخیره شد. صف انتشار به زودی بر اساس تنظیمات جدید بازسازی می‌شود.</span>
            </div>
        <?php endif; ?>

        <?php if ($license_updated): ?>
            <div class="d-flex align-items-center gap-2 p-3 mb-4 rounded-xl" style="background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; font-weight: 600; font-size: 14px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span>لایسنس با موفقیت به‌روزرسانی شد.</span>
            </div>
        <?php endif; ?>

        <!-- Section 1: License Activation Form -->
        <div class="i8-card mb-4" style="direction: rtl !important;">
            <h2 class="i8-card-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" class="text-primary">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                فعال‌سازی و کلید لایسنس
            </h2>
            <form action="" method="post" class="filter-bar-card d-flex flex-wrap align-items-center gap-3 m-0" style="background: transparent; border: none; padding: 0; box-shadow: none;">
                <div class="filter-input-group d-flex align-items-center gap-2 flex-grow-1" style="max-width: 500px; background: var(--tw-slate-50);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" class="text-slate-400">
                        <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                    </svg>
                    <input type="text" value="<?php echo esc_attr($old_secret_code); ?>" name="cop_secret_code" placeholder="کد مخفی لایسنس خود را وارد نمایید..." style="direction: ltr; text-align: left; border: none; background: transparent; outline: none; font-size: 13.5px; width: 100%;">
                </div>
                <button type="submit" class="btn btn-icon-primary btn-icon w-auto px-4" style="height: 42px; gap: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
                    </svg>
                    ثبت و فعال‌سازی لایسنس
                </button>
            </form>
        </div>

        <!-- Section 2: Subscription Info Cards (Only if Valid) -->
        <?php if ($response): 
            $i8_plan_name = (get_option('i8_plan_name')) ? get_option('i8_plan_name') : '-';
            $i8_subscription_start_date = (get_option('i8_subscription_start_date')) ? get_option('i8_subscription_start_date') : '-';
            $i8_subscription_end_date = (get_option('i8_subscription_end_date')) ? get_option('i8_subscription_end_date') : '-';
            $i8_plan_duration = (get_option('i8_plan_duration')) ? get_option('i8_plan_duration') : '-';
            $i8_plan_cron_interval = (get_option('i8_plan_cron_interval')) ? get_option('i8_plan_cron_interval') : '-';
            $i8_plan_max_post_fetch = (get_option('i8_plan_max_post_fetch')) ? get_option('i8_plan_max_post_fetch') : '-';
            
            $start_jalali = @\i8_jDateTime::convertFormatToFormat('Y/m/d - H:i', 'Y-m-d H:i:s', $i8_subscription_start_date);
            $end_jalali = @\i8_jDateTime::convertFormatToFormat('Y/m/d - H:i', 'Y-m-d H:i:s', $i8_subscription_end_date);
            
            $cron_interval_minutes = '-';
            $schedules = wp_get_schedules();
            if (isset($schedules['i8_pc_post_publisher_cron'])) {
                $cron_interval_minutes = intval($schedules['i8_pc_post_publisher_cron']['interval'] / 60);
            }
        ?>
            <div class="row g-3 mb-4" style="direction: rtl !important;">
                <!-- Plan Name -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                    <div class="stat-card-custom d-flex align-items-center justify-content-start gap-3">
                        <div class="i8-stat-icon" style="background: rgba(79, 70, 229, 0.1); color: #4f46e5;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                        </div>
                        <div class="d-flex flex-column text-start">
                            <span class="text-slate-500" style="font-size: 12px; font-weight: 500;">نوع اشتراک لایسنس</span>
                            <span class="text-slate-800 fw-bolder mt-1" style="font-size: 15px;"><?php echo esc_html($i8_plan_name); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Duration -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                    <div class="stat-card-custom d-flex align-items-center justify-content-start gap-3">
                        <div class="i8-stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </div>
                        <div class="d-flex flex-column text-start">
                            <span class="text-slate-500" style="font-size: 12px; font-weight: 500;">مدت اعتبار کل طرح</span>
                            <span class="text-slate-800 fw-bolder mt-1" style="font-size: 15px;"><?php echo i8_setting_to_persian_num($i8_plan_duration); ?> روز</span>
                        </div>
                    </div>
                </div>

                <!-- Start Date -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                    <div class="stat-card-custom d-flex align-items-center justify-content-start gap-3">
                        <div class="i8-stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                        <div class="d-flex flex-column text-start">
                            <span class="text-slate-500" style="font-size: 12px; font-weight: 500;">تاریخ شروع اشتراک</span>
                            <span class="text-slate-800 fw-bolder mt-1" style="font-size: 13.5px; direction: ltr; text-align: right;"><?php echo i8_setting_to_persian_num($start_jalali ?: $i8_subscription_start_date); ?></span>
                        </div>
                    </div>
                </div>

                <!-- End Date -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                    <div class="stat-card-custom d-flex align-items-center justify-content-start gap-3">
                        <div class="i8-stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                        </div>
                        <div class="d-flex flex-column text-start">
                            <span class="text-slate-500" style="font-size: 12px; font-weight: 500;">تاریخ انقضای لایسنس</span>
                            <span class="text-slate-800 fw-bolder mt-1" style="font-size: 13.5px; direction: ltr; text-align: right;"><?php echo i8_setting_to_persian_num($end_jalali ?: $i8_subscription_end_date); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Limit -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                    <div class="stat-card-custom d-flex align-items-center justify-content-start gap-3">
                        <div class="i8-stat-icon" style="background: rgba(124, 58, 237, 0.1); color: #7c3aed;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M4 22V4c0-.5.2-1 .6-1.4C5 2.2 5.5 2 6 2h12c.5 0 1 .2 1.4.6.4.4.6.9.6 1.4v18l-7-4-7 4z"/>
                            </svg>
                        </div>
                        <div class="d-flex flex-column text-start">
                            <span class="text-slate-500" style="font-size: 12px; font-weight: 500;">محدودیت روزانه انتشار</span>
                            <span class="text-slate-800 fw-bolder mt-1" style="font-size: 15px;"><?php echo i8_setting_to_persian_num($i8_plan_max_post_fetch); ?> پست</span>
                        </div>
                    </div>
                </div>

                <!-- Done Today -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                    <div class="stat-card-custom d-flex align-items-center justify-content-start gap-3">
                        <div class="i8-stat-icon" style="background: rgba(14, 116, 144, 0.1); color: #0e7490;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M12 20h9M3 20v-8c0-2.2 1.8-4 4-4h10c2.2 0 4 1.8 4 4v8M3 10V6c0-1.1.9-2 2-2h14c1.1 0 2 .9 2 2v4"/>
                            </svg>
                        </div>
                        <div class="d-flex flex-column text-start">
                            <span class="text-slate-500" style="font-size: 12px; font-weight: 500;">پست‌های منتشرشده امروز</span>
                            <span class="text-slate-800 fw-bolder mt-1" style="font-size: 15px;"><?php echo i8_setting_to_persian_num(get_option('daily_post_count_for_schedule', 0)); ?> پست</span>
                        </div>
                    </div>
                </div>

                <!-- Feed Frequency -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                    <div class="stat-card-custom d-flex align-items-center justify-content-start gap-3">
                        <div class="i8-stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
                            </svg>
                        </div>
                        <div class="d-flex flex-column text-start">
                            <span class="text-slate-500" style="font-size: 12px; font-weight: 500;">تناوب بررسی فیدها</span>
                            <span class="text-slate-800 fw-bolder mt-1" style="font-size: 15px;"><?php echo esc_html($i8_plan_cron_interval); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Local Cron frequency -->
                <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                    <div class="stat-card-custom d-flex align-items-center justify-content-start gap-3">
                        <div class="i8-stat-icon" style="background: rgba(100, 116, 139, 0.1); color: #64748b;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                                <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                            </svg>
                        </div>
                        <div class="d-flex flex-column text-start">
                            <span class="text-slate-500" style="font-size: 12px; font-weight: 500;">تناوب کرون‌جاب محلی</span>
                            <span class="text-slate-800 fw-bolder mt-1" style="font-size: 15px;"><?php echo i8_setting_to_persian_num($cron_interval_minutes); ?> دقیقه</span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Section 3: Cron Schedule Settings Grid -->
        <form method="post" action="" style="direction: rtl !important;" class="m-0">
            <?php wp_nonce_field('publisher_copoilot_settings_action', 'publisher_copoilot_settings_group_nonce'); ?>
            
            <div class="i8-settings-grid">
                <!-- Config Card -->
                <div class="i8-card">
                    <h2 class="i8-card-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" class="text-primary">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        زمان‌بندی انتشار فیدها
                    </h2>
                    
                    <div class="i8-form-group">
                        <div class="i8-label-col">
                            <span class="i8-label">ساعت شروع کار کرون جاب</span>
                            <span class="i8-description">اولین زمان در روز که انتشار مطالب آغاز می‌شود.</span>
                        </div>
                        <div class="i8-input-col">
                            <input type="time" name="start_cron_time" class="i8-input" value="<?php echo esc_attr($start_cron_time); ?>" required />
                        </div>
                    </div>

                    <div class="i8-form-group">
                        <div class="i8-label-col">
                            <span class="i8-label">ساعت پایان کار کرون جاب</span>
                            <span class="i8-description">آخرین زمانی که انتشار مطالب در روز متوقف می‌شود.</span>
                        </div>
                        <div class="i8-input-col">
                            <input type="time" name="end_cron_time" class="i8-input" value="<?php echo esc_attr($end_cron_time); ?>" required />
                        </div>
                    </div>

                    <div class="i8-form-group">
                        <div class="i8-label-col">
                            <span class="i8-label">تعداد اخبار روزانه (هدف انتشار)</span>
                            <span class="i8-description">سیستم با توجه به ساعات کاری، فواصل منظم را محاسبه می‌کند.</span>
                        </div>
                        <div class="i8-input-col">
                            <input type="number" min="1" max="500" name="daily_post_count" class="i8-input" value="<?php echo esc_attr($daily_post_count); ?>" required />
                        </div>
                    </div>

                    <button type="submit" class="btn btn-icon-primary btn-icon w-100 py-3 mt-3" style="height: 48px; gap: 8px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        ذخیره و اعمال معماری جدید
                    </button>
                </div>

                <!-- Preview Card -->
                <div class="i8-card">
                    <h2 class="i8-card-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" class="text-primary">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                        پیش‌نمایش توزیع پست‌ها
                    </h2>
                    
                    <div class="i8-preview-box">
                        <div class="i8-stats-row">
                            <div class="i8-stat-badge">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                <span><?php echo i8_setting_to_persian_num($interval_hours); ?> ساعت کار مداوم</span>
                            </div>
                            <div class="i8-stat-badge">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                                </svg>
                                <span>یک پست هر <?php echo i8_setting_to_persian_num($post_interval); ?> دقیقه</span>
                            </div>
                        </div>
                        
                        <div class="i8-timeline">
                            <div class="i8-time-node" data-time="<?php echo esc_attr(i8_setting_to_persian_num($start_cron_time)); ?>" title="شروع"></div>
                            <div class="i8-time-node" style="transform: scale(0.6); opacity: 0.5;"></div>
                            <div class="i8-time-node" style="transform: scale(0.6); opacity: 0.5;"></div>
                            <div class="i8-time-node" style="transform: scale(0.6); opacity: 0.5;"></div>
                            <div class="i8-time-node" style="transform: scale(0.6); opacity: 0.5;"></div>
                            <div class="i8-time-node" data-time="<?php echo esc_attr(i8_setting_to_persian_num($end_cron_time)); ?>" title="پایان"></div>
                        </div>
                        <p style="margin-top: 40px; font-size: 13px; color: var(--tw-slate-500); text-align: center; margin-bottom: 0;">
                            در این بازه زمانی، سیستم به صورت کاملاً یکنواخت <?php echo esc_html(i8_setting_to_persian_num($daily_post_count)); ?> جایگاه انتشار را برای اخبار شما ایجاد خواهد کرد.
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <script>
        jQuery(document).ready(function($) {
            // Live Preview Updates logic can be added here if needed
        });
    </script>
<?php
}

// تابع برای اضافه کردن صفحه تنظیمات
function i8_add_seeting_page_menu() {
    add_submenu_page(
        'publisher_copoilot',
        'تنظیمات دستیار',
        'تنظیمات',
        'manage_options',
        'publisher_copoilot_setting',
        'publisher_copoilot_setting_page_callback'
    );
}
add_action('admin_menu', 'i8_add_seeting_page_menu', 18);