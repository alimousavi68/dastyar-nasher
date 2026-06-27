<?php
// Add menu page
add_action('admin_menu', 'add_report_submenu_page', 14);

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

    // Pagination logic
    $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
    if ($per_page < 1) $per_page = 20;
    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
    if ($paged < 1) $paged = 1;

    $total_items = count($filtered_reports);
    $total_pages = ceil($total_items / $per_page);
    $paged = min($paged, max(1, $total_pages));

    $offset = ($paged - 1) * $per_page;
    $paged_reports = array_slice($filtered_reports, $offset, $per_page);
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
            direction: rtl !important;
            text-align: right !important;
        }
        .stat-card-custom:hover {
            transform: translateY(-2px);
            box-shadow: var(--tw-shadow-md);
        }
        .i8-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .table-item {
            direction: rtl !important;
            text-align: right !important;
        }
        .filter-input-group select {
            cursor: pointer;
            border: none;
            background: transparent;
            outline: none;
            font-size: 13px;
            padding: 0 8px;
            color: var(--tw-slate-700);
        }
    </style>

    <div class="app-container">
        <!-- Header -->
        <div class="sticky-header-bar d-flex flex-wrap flex-xl-nowrap align-items-center justify-content-between gap-3 p-3 mb-4 shadow-sm-soft border bg-white rounded-xl" style="position: sticky; top: 32px; z-index: 100; direction: rtl !important;">
            <div class="d-flex align-items-center gap-2 pe-xl-3">
                <div class="radar-container me-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="radar-scanner-icon">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    <span class="radar-pulse-ring"></span>
                </div>
                <div>
                    <span class="fs-5 fw-bolder text-slate-800" style="white-space: nowrap;">گزارشات و رویدادهای سیستم</span>
                    <div class="text-slate-500" style="font-size: 12px; margin-top: 2px;">لاگ دقیق فعالیت‌های خزش منابع، وضعیت تراکنش‌ها و تاریخچه انتشار صف زمان‌بندی</div>
                </div>
            </div>

            <?php if (!empty($reports)): ?>
                <div class="d-flex align-items-center gap-2">
                    <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" class="m-0" onsubmit="return confirm('آیا از پاک کردن کامل تاریخچه گزارشات اطمینان دارید؟');">
                        <input type="hidden" name="action" value="delete_all_reports">
                        <input type="hidden" name="page" value="publisher-copilot-report">
                        <?php wp_nonce_field('delete_all_reports'); ?>
                        <button type="submit" class="btn btn-icon-danger btn-icon w-auto px-3" style="height: 42px; gap: 8px; background: #fff !important; border: 1px solid #fca5a5 !important; color: #dc2626 !important;" title="حذف همه گزارشات">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                <line x1="14" y1="11" x2="14" y2="17"></line>
                            </svg>
                            حذف همه گزارشات
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Stats Grid -->
        <div class="row g-3 mb-4" style="direction: rtl !important;">
            <div class="col-12 col-md-4">
                <div class="stat-card-custom d-flex align-items-center justify-content-start gap-3">
                    <div class="i8-stat-icon" style="background: rgba(79, 70, 229, 0.1); color: #4f46e5;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                    </div>
                    <div class="d-flex flex-column text-start">
                        <span class="text-slate-500" style="font-size: 13px; font-weight: 500;">کل رویدادهای ثبت‌شده</span>
                        <span class="text-slate-800 fw-bolder mt-1" style="font-size: 18px;"><?php echo i8_report_to_persian_num($total_reports); ?> مورد</span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-md-4">
                <div class="stat-card-custom d-flex align-items-center justify-content-start gap-3">
                    <div class="i8-stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <div class="d-flex flex-column text-start">
                        <span class="text-slate-500" style="font-size: 13px; font-weight: 500;">عملیات موفق</span>
                        <span class="text-slate-800 fw-bolder mt-1" style="font-size: 18px;"><?php echo i8_report_to_persian_num($success_count); ?> رویداد</span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="stat-card-custom d-flex align-items-center justify-content-start gap-3">
                    <div class="i8-stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <div class="d-flex flex-column text-start">
                        <span class="text-slate-500" style="font-size: 13px; font-weight: 500;">عملیات ناموفق (خطا)</span>
                        <span class="text-slate-800 fw-bolder mt-1" style="font-size: 18px;"><?php echo i8_report_to_persian_num($failed_count); ?> رویداد</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Form -->
        <form method="get" action="" class="filter-bar-card d-flex flex-wrap align-items-center gap-3" style="direction: rtl !important;">
            <input type="hidden" name="page" value="publisher-copilot-report">
            
            <div class="filter-input-group d-flex align-items-center gap-2">
                <label for="filter_action" class="text-slate-600 fw-bold fs-7 mb-0" style="white-space: nowrap;">نوع عملیات:</label>
                <select name="filter_action" id="filter_action">
                    <option value="">همه عملیات‌ها</option>
                    <?php foreach ($groups as $key => $group_info): ?>
                        <option value="group:<?php echo esc_attr($key); ?>" <?php selected($filter_action, 'group:' . $key); ?>>
                            <?php echo esc_html($group_info['label']); ?> (کل گروه)
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

            <div class="filter-input-group d-flex align-items-center gap-2">
                <label for="filter_status" class="text-slate-600 fw-bold fs-7 mb-0" style="white-space: nowrap;">وضعیت:</label>
                <select name="filter_status" id="filter_status">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="1" <?php selected($filter_status, '1'); ?>>موفق</option>
                    <option value="0" <?php selected($filter_status, '0'); ?>>ناموفق</option>
                </select>
            </div>

            <div class="filter-input-group d-flex align-items-center gap-2">
                <label for="per_page" class="text-slate-600 fw-bold fs-7 mb-0" style="white-space: nowrap;">تعداد در صفحه:</label>
                <select name="per_page" id="per_page">
                    <option value="20" <?php selected($per_page, 20); ?>>۲۰</option>
                    <option value="50" <?php selected($per_page, 50); ?>>۵۰</option>
                    <option value="100" <?php selected($per_page, 100); ?>>۱۰۰</option>
                    <option value="250" <?php selected($per_page, 250); ?>>۲۵۰</option>
                </select>
            </div>

            <button type="submit" class="btn btn-icon-primary btn-icon" title="اعمال فیلتر" style="width: 42px; height: 42px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            </button>
            
            <?php if ($filter_status !== '' || $filter_action !== '' || isset($_GET['per_page'])): ?>
                <a href="<?php echo admin_url('admin.php?page=publisher-copilot-report'); ?>" class="btn btn-icon-danger btn-icon" title="پاک کردن فیلتر" style="width: 42px; height: 42px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </a>
            <?php endif; ?>
        </form>

        <!-- Reports List -->
        <div class="my-4">
            <div class="table-body">
                <?php if (!empty($paged_reports)): ?>
                    <?php foreach ($paged_reports as $report): 
                        $status_class = ($report['status'] == 1) ? 'published' : 'draft';
                        
                        // Date formatting
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
                        <div class="table-item d-flex align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3 flex-grow-1 min-w-0" style="direction: rtl !important;">
                                <!-- Vertical indicator strip (aligned to right side in RTL) -->
                                <div style="width: 4px; height: 48px; background-color: <?php echo ($report['status'] == 1) ? '#10b981' : '#ef4444'; ?>; border-radius: 4px; flex-shrink: 0; order: 1;"></div>
                                
                                <div class="d-flex flex-column gap-1 flex-grow-1 min-w-0 order-2 text-start" style="padding-right: 4px;">
                                    <!-- Title & Status Badge -->
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="fs-6 fw-bold text-slate-800"><?php echo safe_esc_html($report['action_title']); ?></span>
                                        <span class="badge-status-<?php echo $status_class; ?>">
                                            <?php echo ($report['status'] == 1) ? 'موفق' : 'ناموفق'; ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Error message/Description -->
                                    <?php if (!empty($report['error_msg'])): ?>
                                        <div class="text-slate-600 fw-medium" style="font-size: 13.5px;"><?php echo i8_report_to_persian_num(safe_esc_html($report['error_msg'])); ?></div>
                                    <?php endif; ?>
                                    
                                    <!-- Metadata footer -->
                                    <div class="d-flex align-items-center gap-3 flex-wrap text-slate-400" style="font-size: 12px; margin-top: 4px;">
                                        <div class="d-flex align-items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <polyline points="12 6 12 12 16 14"></polyline>
                                            </svg>
                                            <span style="direction: ltr;"><?php echo i8_report_to_persian_num($date_display); ?></span>
                                        </div>
                                        
                                        <?php 
                                        $resource_html = '';
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
                                                    $resource_html = '<a href="' . esc_url($post_link) . '" class="text-primary text-decoration-none" target="_blank" title="' . esc_attr($post_title) . '">' . esc_html($truncated_title) . '</a>';
                                                } else {
                                                    $resource_html = esc_html($truncated_title);
                                                }
                                            }
                                        } else {
                                            $url = $report['resource_name'];
                                            if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                                                $resource_html = '<a href="' . esc_url($url) . '" class="text-primary text-decoration-none" target="_blank" title="' . esc_attr($url) . '">مشاهده لینک منبع/فید</a>';
                                            } else {
                                                $resource_html = esc_html($url);
                                            }
                                        }
                                        
                                        if (!empty($resource_html)):
                                        ?>
                                            <div class="d-flex align-items-center gap-1">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                                                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                                                </svg>
                                                <span><?php echo $resource_html; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5 bg-white border rounded-xl shadow-sm-soft">
                        <div class="text-slate-400 mb-3 d-flex justify-content-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="m15 9-6 6"></path>
                                <path d="m9 9 6 6"></path>
                            </svg>
                        </div>
                        <h3 class="fw-bold text-slate-800 fs-5">هیچ گزارش یا رویدادی یافت نشد.</h3>
                        <p class="text-slate-500 mb-0 mt-2">هیچ رویدادی منطبق با فیلترهای انتخابی در سیستم ثبت نشده است.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-center mt-4">
                    <nav aria-label="Page navigation">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '«',
                            'next_text' => '»',
                            'total' => $total_pages,
                            'current' => $paged,
                            'type' => 'list'
                        ));
                        ?>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php
}
