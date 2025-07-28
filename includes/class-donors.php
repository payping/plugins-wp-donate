<?php
defined('ABSPATH') || exit;

/**
 * Class Payping_Donate_List
 *
 * Displays a table of donors and a detail view in the admin area.
 */
class Payping_Donate_List {

    /**
     * Render the donors list table
     *
     * @param int $paged Current page number
     * @return void
     */
    public static function render($paged = 1) {
        global $wpdb;

        // Table name
        $donate_table = $wpdb->prefix . 'payping_donate';

        // Pagination config
        $per_page = 30;
        $offset   = ($paged - 1) * $per_page;

        // Sanitize search query if present
        $search_name = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $where_sql   = '';
        $args        = [];

        if (!empty($search_name)) {
            // Prepare search pattern for LIKE query
            $like       = '%' . $wpdb->esc_like($search_name) . '%';
            $where_sql  = "WHERE `Name` LIKE %s";
            $args[]     = $like;
        }

        // Add LIMIT parameters for pagination
        $args[] = $offset;
        $args[] = $per_page;

        // Build SQL query with prepare
        $query = "SELECT * FROM `$donate_table` $where_sql ORDER BY `DonateID` DESC LIMIT %d, %d";
        $query = $wpdb->prepare($query, ...$args);

        $results = $wpdb->get_results($query);

        // Calculate total rows for pagination
        $count_sql = "SELECT COUNT(*) FROM `$donate_table`";
        if (!empty($search_name)) {
            // Count with search condition
            $count_sql .= $wpdb->prepare(" WHERE `Name` LIKE %s", $like);
        }
        $total = (int) $wpdb->get_var($count_sql);
        $total_pages = ceil($total / $per_page);

        // Current page for pagination sanity
        $current_page = max(1, $paged);

        // Prepare base URL for pagination links (preserve 'page' and 's' parameters)
        $base_url = add_query_arg(
            [
                'page'  => isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '',
                's'     => $search_name,
                'paged' => '%#%'
            ],
            admin_url('admin.php')
        );
        ?>
        <div class="wrap">
            <h2>لیست حامیان مالی</h2>

            <form method="get" style="margin-bottom: 1em;">
                <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page'] ?? ''); ?>">
                <input type="search" name="s" value="<?php echo esc_attr($search_name); ?>" placeholder="نام حامی را جستجو کنید..." />
                <input type="submit" class="button" value="جستجو">
            </form>

            <table class="widefat striped fixed">
                <thead>
                    <tr>
                        <th>شناسه</th>
                        <th>نام</th>
                        <th>مبلغ (تومان)</th>
                        <th>پیام</th>
                        <th>تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($results)) : ?>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td><?php echo esc_html($row->DonateID); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=' . esc_attr($_GET['page']) . '&action=view&donate_id=' . intval($row->DonateID) . '&paged=' . $current_page . '&s=' . urlencode($search_name))); ?>">
                                    <?php echo esc_html($row->Name); ?>
                                </a>
                            </td>
                            <td><?php echo number_format($row->AmountTomaan); ?></td>
                            <td><?php echo esc_html($row->Description); ?></td>
                            <td><?php echo date_i18n('Y/m/d H:i', strtotime($row->InputDate)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5">هیچ حامی یافت نشد.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
            <div class="tablenav bottom">
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php printf('%s مورد', number_format_i18n($total)); ?></span>
                    <span class="pagination-links">
                        <?php if ($current_page == 1): ?>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>
                        <?php else: ?>
                            <a class="first-page button" href="<?php echo esc_url(remove_query_arg('paged')); ?>" aria-label="برگه اول">«</a>
                            <a class="prev-page button" href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>" aria-label="برگه قبلی">‹</a>
                        <?php endif; ?>
            
                        <span class="screen-reader-text">برگهٔ فعلی</span>
                        <span id="table-paging" class="paging-input">
                            <span class="tablenav-paging-text"><?php echo $current_page . ' از '; ?><span class="total-pages"><?php echo $total_pages; ?></span></span>
                        </span>
            
                        <?php if ($current_page == $total_pages): ?>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>
                            <span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>
                        <?php else: ?>
                            <a class="next-page button" href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>" aria-label="برگه بعدی">›</a>
                            <a class="last-page button" href="<?php echo esc_url(add_query_arg('paged', $total_pages)); ?>" aria-label="برگه آخر">»</a>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render detail page for a specific donation
     *
     * @param int $donate_id Donation ID to display details for
     * @return void
     */
    public static function render_donate_detail($donate_id) {
        global $wpdb;
        $donate_table = $wpdb->prefix . 'payping_donate';

        // Fetch single donation by ID
        $donation = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$donate_table` WHERE DonateID = %d", $donate_id));

        if (!$donation) {
            echo '<div class="notice notice-error"><p>پرداخت مورد نظر یافت نشد.</p></div>';
            return;
        }

        // Get current page and search term to preserve when returning
        $paged  = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        ?>
        <div class="wrap">
            <h2>جزئیات پرداخت - شناسه #<?php echo esc_html($donation->DonateID); ?></h2>
            <table class="form-table">
                <tr><th>نام و نام خانوادگی</th><td><?php echo esc_html($donation->Name); ?></td></tr>
                <tr><th>مبلغ (تومان)</th><td><?php echo number_format($donation->AmountTomaan); ?></td></tr>
                <tr><th>موبایل</th><td><?php echo esc_html($donation->Mobile); ?></td></tr>
                <tr><th>ایمیل</th><td><?php echo esc_html($donation->Email); ?></td></tr>
                <tr><th>شماره پیگیری</th><td><?php echo esc_html($donation->Authority); ?></td></tr>
                <tr><th>توضیحات</th><td><?php echo esc_html($donation->Description); ?></td></tr>
                <tr><th>تاریخ پرداخت</th><td><?php echo date_i18n('Y/m/d H:i', strtotime($donation->InputDate)); ?></td></tr>
                <tr><th>وضعیت</th><td><?php echo esc_html($donation->Status); ?></td></tr>
            </table>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . esc_attr($_GET['page']) . '&paged=' . $paged . '&s=' . urlencode($search))); ?>" class="button button-secondary">بازگشت به لیست</a>
            </p>
        </div>
        <?php
    }
}