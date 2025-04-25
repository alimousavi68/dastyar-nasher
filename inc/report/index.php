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

function display_report_page()
{
?>
    <div class="wrap">
        <h1>گزارشات دستیار</h1>
        <?php
        // Create WP_List_Table instance
        $table = new WP_List_Table();
        ?>
        <form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
            <input type="hidden" name="action" value="delete_all_reports">
            <input type="hidden" name="page" value="publisher-copilot-report">
            <?php wp_nonce_field('delete_all_reports'); ?>
            <input type="submit" class="button" value="حذف همه گزارشات">
        </form>


        <table class="widefat  ">
            <thead>
                <tr>

                    <th>عنوان عملیات</th>
                    <th>منبع</th>
                    <th>تاریخ</th>
                    <th>وضعیت</th>
                    <th>خطا</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $reports = display_rss_reports();
                

                foreach ($reports as $report) {
                    $status_text = ($report['status'] == 0) ? 'ناموفق' : 'موفق';
                    echo '<tr>';
                    echo '<td>' . $report['action_title'] . '</td>';
                    echo '<td> <a href="' . $report['resource_name'] . '">لینک</a></td>';
                    echo '<td>' . $report['pub_date'] . '</td>';
                    echo '<td>' . $status_text . '</td>';
                    echo '<td>' . $report['error_msg'] . '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
<?php
}
