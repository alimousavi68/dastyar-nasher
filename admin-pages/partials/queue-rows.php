<?php
// admin-pages/partials/queue-rows.php
global $wpdb;
$table = $wpdb->prefix . 'pc_post_schedule';
// Pagination variables
$per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : (isset($_GET['per_page']) ? intval($_GET['per_page']) : 25);
if (!in_array($per_page, [25, 50, 70])) $per_page = 25;

$page = isset($_POST['paged']) ? intval($_POST['paged']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $per_page;

$results = $wpdb->get_results("SELECT * FROM $table ORDER BY FIELD(status, 'publishing', 'scheduled', 'queued', 'failed', 'cancelled', 'published'), FIELD(publish_priority, 'high', 'medium', 'low'), sort_order ASC, id ASC LIMIT $per_page OFFSET $offset");

if ($results):
    $counter = $offset;
    foreach ($results as $item):
        $post_id = $item->post_id;
        $counter++;
        $row_opacity_class = ($item->status == 'published') ? 'opacity-50' : '';
        
        $pri_color = 'transparent';
        if ($item->status != 'published') {
            if ($item->publish_priority == 'high') { $pri_color = '#dc2626'; }
            elseif ($item->publish_priority == 'medium') { $pri_color = '#d97706'; }
            elseif ($item->publish_priority == 'low') { $pri_color = '#15803d'; }
        }
        $row_style = '';
        if ($item->status == 'published') $row_style .= 'background-color: #f8fafc; ';
        if ($pri_color != 'transparent') $row_style .= 'border-right: 4px solid ' . $pri_color . '; ';
        
        $pub_date = '-';
        $pub_time = '-';
        if ($item->scheduled_for) {
            $local_date_str = function_exists('i8_get_local_time_from_gmt') ? i8_get_local_time_from_gmt($item->scheduled_for) : get_date_from_gmt($item->scheduled_for);
            $fake_timestamp = strtotime($local_date_str . ' UTC');
            if (class_exists('i8_jDateTime')) {
                $jdate = new i8_jDateTime(true, true, 'UTC');
                $pub_date = i8_to_persian_num($jdate->date('d / m', $fake_timestamp));
                $pub_time = i8_to_persian_num($jdate->date('H:i', $fake_timestamp));
            } else {
                $pub_date = i8_to_persian_num(date('m/d', $fake_timestamp));
                $pub_time = i8_to_persian_num(date('H:i', $fake_timestamp));
            }
        }
?>
    <div id="item-<?php echo $item->id; ?>" data-id="<?php echo $item->id; ?>" class="table-item d-flex align-items-center justify-content-between gap-3 position-relative i8-sortable-row <?php echo $row_opacity_class; ?>" style="<?php echo $row_style; ?>">
        <!-- Left Section: Drag, Number, Title -->
        <div class="feed-item-info d-flex align-items-center gap-3 min-w-0 flex-grow-1">
            <div class="drag-handle" style="cursor: grab; color: #cbd5e1; display: flex; align-items: center;" title="برای جابجایی بکشید">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M7 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 5a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 8a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm-3 3a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm3 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                </svg>
            </div>
            
            <div class="text-slate-400 fw-normal px-2" style="font-size: 13px;">
                <?php echo i8_to_persian_num($counter); ?>
            </div>
            
            <div class="feed-vertical-divider"></div>

            <!-- Time & Date Column -->
            <div class="feed-time-column d-flex flex-column align-items-center justify-content-center text-center px-2">
                <span class="feed-time"><?php echo $pub_time; ?></span>
                <span class="feed-date"><?php echo $pub_date; ?></span>
            </div>
            
            <div class="feed-vertical-divider"></div>
            
            <div class="feed-item-right-section d-flex flex-column align-items-start gap-2 min-w-0">
                <!-- Status Badges -->
                <div class="feed-metadata d-flex align-items-center gap-2 flex-wrap">
                    <?php
                    $s_class = 'badge-status-draft';
                    $s_text = 'در صف';
                    $s_style = '';
                    switch ($item->status) {
                        case 'queued': 
                            $s_class = 'badge-status-queued'; 
                            $s_text = 'در صف انتظار'; 
                            break;
                        case 'scheduled': 
                            $s_class = ''; 
                            $s_text = 'زمان‌بندی شده'; 
                            $s_style = 'style="background: #fdf4ff; color: #a21caf; border: 1px solid #f5d0fe; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 300;"'; 
                            break;
                        case 'publishing': 
                            $s_class = 'badge-status-queued'; 
                            $s_text = 'در حال انتشار...'; 
                            break;
                        case 'published': 
                            $s_class = 'badge-status-published'; 
                            $s_text = 'منتشر شد'; 
                            break;
                        case 'failed': 
                            $s_class = ''; 
                            $s_text = 'خطا در انتشار'; 
                            $s_style = 'style="background: #fef2f2; color: #ef4444; border: 1px solid #fca5a5; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 300;"'; 
                            break;
                        case 'cancelled': 
                            $s_class = 'badge-status-draft'; 
                            $s_text = 'لغو شده'; 
                            break;
                    }
                    if ($s_style) {
                        echo '<span ' . $s_style . '>' . $s_text . '</span>';
                    } else {
                        echo '<span class="' . $s_class . '">' . $s_text . '</span>';
                    }
                    ?>
                    
                    <span style="font-size: 10px; opacity: 0.6; font-weight: 300;">
                        توسط: <?php echo safe_get_author_name($post_id); ?>
                    </span>
                </div>
                
                <a href="<?php echo safe_get_edit_post_link($post_id); ?>" target="_blank" class="feed-title-link text-slate-700 text-decoration-none fw-normal">
                    <?php echo safe_get_the_title($post_id); ?>
                </a>
                
                <?php if ($item->status == 'failed') { echo '<div style="font-size:11px; color:#ef4444; margin-top:2px;">' . safe_esc_html($item->last_error) . ' (تلاش ' . intval($item->attempts) . '/3)</div>'; } ?>
            </div>
        </div>

        <!-- Right Section: Actions & Priority -->
        <div class="action-bar d-flex align-items-center gap-3">
            <div class="priority-selector-group" style="padding: 4px; <?php if($item->status == 'published') echo 'opacity: 0.6; pointer-events: none; background: transparent; border-color: transparent;'; ?>">
                <span class="priority-label d-none d-md-inline" style="font-weight: 300;">اولویت</span>
                <select class="form-select form-select-sm i8-priority-select border-0 shadow-none bg-transparent" data-id="<?php echo $item->id; ?>" data-val="<?php echo esc_attr($item->publish_priority); ?>" style="width: 160px; font-size: 12px; font-weight: 400; cursor: pointer; padding-left: 20px; color: <?php echo $pri_color != 'transparent' ? $pri_color : 'inherit'; ?>;" <?php if($item->status == 'published') echo 'disabled'; ?>>
                    <option value="high" <?php selected($item->publish_priority, 'high'); ?> style="background: #fff; color: #dc2626;">بالا</option>
                    <option value="medium" <?php selected($item->publish_priority, 'medium'); ?> style="background: #fff; color: #d97706;">متوسط</option>
                    <option value="low" <?php selected($item->publish_priority, 'low'); ?> style="background: #fff; color: #15803d;">کم</option>
                </select>
            </div>
            
            <div class="d-flex align-items-center gap-2 border-start ps-3 me-2" <?php if($item->status == 'published') echo 'style="opacity: 0.4; pointer-events: none;"'; ?>>
                <button class="btn-action-yellow pc-publish-now-btn position-relative" data-id="<?php echo $item->id; ?>" data-bs-toggle="tooltip" data-bs-title="انتشار فوری" <?php if($item->status == 'published') echo 'disabled'; ?>>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-lightning-charge" viewBox="0 0 16 16"><path d="M11.251.068a.5.5 0 0 1 .227.58L9.677 6.5H13a.5.5 0 0 1 .364.843l-8 8.5a.5.5 0 0 1-.842-.49L6.323 9.5H3a.5.5 0 0 1-.364-.843l8-8.5a.5.5 0 0 1 .615-.09zM4.157 8.5H7a.5.5 0 0 1 .478.647L6.11 13.59l5.732-6.09H9a.5.5 0 0 1-.478-.647L9.89 2.41z"/></svg>
                    <img src="<?php echo admin_url('images/spinner.gif'); ?>" class="i8-loader-gif" style="display:none;position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);z-index:100;width:16px;" />
                </button>
                <a href="<?php echo safe_get_edit_post_link($post_id); ?>" class="btn-action-gray" data-bs-toggle="tooltip" data-bs-title="ویرایش پست" target="_blank" <?php if($item->status == 'published') echo 'disabled style="pointer-events:none;"'; ?>>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293l6.5-6.5zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.468-.325z"/></svg>
                </a>
                <a href="<?php echo safe_get_permalink($post_id); ?>" class="btn-action-gray" data-bs-toggle="tooltip" data-bs-title="نمایش پست" target="_blank" <?php if($item->status == 'published') echo 'disabled style="pointer-events:none;"'; ?>>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/></svg>
                </a>
                <button class="btn-action-danger delete-link" data-id="<?php echo $item->id; ?>" data-bs-toggle="tooltip" data-bs-title="حذف از صف" <?php if($item->status == 'published') echo 'disabled'; ?>>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3" viewBox="0 0 16 16"><path d="M6.5 1h3a.5.5 0 0 1 .5.5v1H6v-1a.5.5 0 0 1 .5-.5M11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3A1.5 1.5 0 0 0 5 1.5v1H1.5a.5.5 0 0 0 0 1h.538l.853 10.66A2 2 0 0 0 4.885 16h6.23a2 2 0 0 0 1.994-1.84l.853-10.66h.538a.5.5 0 0 0 0-1zm1.958 1-.846 10.58a1 1 0 0 1-.997.92h-6.23a1 1 0 0 1-.997-.92L3.042 3.5zm-7.487 1a.5.5 0 0 1 .528.47l.5 8.5a.5.5 0 0 1-.998.06L5 5.03a.5.5 0 0 1 .47-.53Zm5.058 0a.5.5 0 0 1 .47.53l-.5 8.5a.5.5 0 1 1-.998-.06l.5-8.5a.5.5 0 0 1 .528-.47M8 4.5a.5.5 0 0 1 .5.5v8.5a.5.5 0 0 1-1 0V5a.5.5 0 0 1 .5-.5"/></svg>
                </button>
            </div>
        </div>
    </div>
<?php
    endforeach;
else:
?>
    <div class="text-center p-5 text-slate-400 d-flex flex-column align-items-center justify-content-center">
        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="currentColor" class="bi bi-inbox mb-3 opacity-50" viewBox="0 0 16 16">
            <path d="M4.98 4a.5.5 0 0 0-.39.188L1.54 8H6a.5.5 0 0 1 .5.5 1.5 1.5 0 1 0 3 0A.5.5 0 0 1 10 8h4.46l-3.05-3.812A.5.5 0 0 0 11.02 4H4.98zm-1.17-.437A1.5 1.5 0 0 1 4.98 3h6.04a1.5 1.5 0 0 1 1.17.563l3.7 4.625a.5.5 0 0 1 .106.374l-.39 3.124A1.5 1.5 0 0 1 14.117 13H1.883a1.5 1.5 0 0 1-1.489-1.314l-.39-3.124a.5.5 0 0 1 .106-.374l3.7-4.625zM.125 8.163l.39 3.124A.5.5 0 0 0 1.883 12h12.234a.5.5 0 0 0 .493-.437l.39-3.124A1.5 1.5 0 0 1 13.5 10h-2.5a2.5 2.5 0 0 1-5 0H3.5a1.5 1.5 0 0 1-1.375-1.837z"/>
        </svg>
        <span>در حال حاضر هیچ پستی در این صفحه وجود ندارد.</span>
    </div>
<?php endif; ?>
