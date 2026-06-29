<?php 

// add custom meta box to post publish
add_action('add_meta_boxes', 'render_custom_meta_box');
function render_custom_meta_box()
{
    add_meta_box(
        'cop-manager-metabox',
        __('داشبورد دستیار'),
        'render_cop_manager_meta_box',
        'post',
        'side',
        'high'
    );
}


function render_cop_manager_meta_box()
{
    global $post;
    $old_priority = cop_get_post_priority($post->ID);

?>
    <div class="">
        <label for="cop_post_priority">
            ⏰ زمانبندی هوشمند:
            <select name="cop_post_priority" id="cop_post_priority" class="widefat">
                <option value=""> ⏤ بدون زمانبندی </option>
                <option value="now" <?php echo ($old_priority == 'now') ? 'selected' : ''; ?>>⏳ انتشار با تاخیر</option>
                <option value="high" <?php echo ($old_priority == 'high') ? 'selected' : ''; ?>>🔴 الویت بالا</option>
                <option value="medium" <?php echo ($old_priority == 'medium') ? 'selected' : ''; ?>>🟠 اولویت متوسط</option>
                <option value="low" <?php echo ($old_priority == 'low') ? 'selected' : ''; ?>>🟢 اولویت پایین</option>
            </select>
        </label>
    </div>
<?php
}

add_action('save_post', 'cop_set_post_priority_in_manager_meta_box', 10, 3);

function cop_set_post_priority_in_manager_meta_box($post_id, $post, $update)
{
    static $updating_post = false;

    if ($updating_post) {
        return;
    }


    // اگر در حال اجرای یک بازنویسی خودکار هستیم، اجرا نکنید
    if (wp_is_post_autosave($post_id)) {
        return;
    }
    // اگر در حال اجرای یک بازبینی هستیم، اجرا نکنید
    if (wp_is_post_revision($post_id)) {
        return;
    }
    if ($post->post_type != 'post') {
        return; // اگر پست موجود نیست، کد بیشتر اجرا نشود
    }


    $priority_value = isset($_POST['cop_post_priority']) ? $_POST['cop_post_priority'] : false;
    $old_priority = cop_get_post_priority($post->ID);

    // اطمینان حاصل کنید که وضعیت پست تغییر کرده است تا از لوپ بینهایت جلوگیری شود
    if ($old_priority == $priority_value) {
        return; // اگر وضعیت تغییر نکرده باشد، فرآیند را متوقف کنید
    }


    if ($priority_value != 'now' and $priority_value != false) {
        $updating_post = true;

        if ($old_priority == null) {
            // add this post to cop schueduling list 
            add_post_to_post_schedule_table($post->ID, $priority_value);
        } else {
            cop_update_post_priority($post_id, $priority_value);
        }

        // change post status to draft if priority is not "now"
        $post_data = array(
            'ID' => $post->ID,
            'post_status' => 'draft',
        );
        remove_action('save_post', 'cop_set_post_priority_in_manager_meta_box', 10);
        wp_update_post($post_data);
        add_action('save_post', 'cop_set_post_priority_in_manager_meta_box', 10, 3);
        $updating_post = false;
    } elseif ($priority_value == 'now') {

        $updating_post = true;

        if ($old_priority != null and $old_priority != 'now') {
            i8_delete_item_at_scheulde_list(null, $post_id);
        }

        $tz = wp_timezone();
        $random_interval = rand(400, 900);
        $publish_time = time() + $random_interval;

        $date = new DateTime('@' . $publish_time);
        $date->setTimezone($tz);
        $post_date_str = $date->format('Y-m-d H:i:s');

        $date_gmt = new DateTime('@' . $publish_time);
        $date_gmt->setTimezone(new DateTimeZone('UTC'));
        $post_date_gmt_str = $date_gmt->format('Y-m-d H:i:s');

        // Prepare data for creating a WordPress post
        $post_data = array(
            'ID' => $post->ID,
            'post_status' => 'future',
            'post_date' => $post_date_str, // استفاده از زمان تصادفی برای post_date
            'post_date_gmt' => $post_date_gmt_str, // استفاده از زمان تصادفی برای post_date_gmt
        );

        remove_action('save_post', 'cop_set_post_priority_in_manager_meta_box', 10);
        wp_update_post($post_data);
        add_action('save_post', 'cop_set_post_priority_in_manager_meta_box', 10, 3);
        $updating_post = false;
    }
}