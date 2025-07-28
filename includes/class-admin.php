<?php
defined('ABSPATH') or die('Access denied!');

class PP_Donate_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'handle_form_submission']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            'حمایت مالی پی‌پینگ',
            'حمایت مالی',
            'manage_options',
            'payping-donate',
            [$this, 'render_dashboard_page'],
            'dashicons-money-alt'
        );

        // Submenus
        add_submenu_page(
            'payping-donate',
            'داشبورد',
            'داشبورد',
            'manage_options',
            'payping-donate',
            [$this, 'render_dashboard_page']
        );

        add_submenu_page(
            'payping-donate',
            'تنظیمات',
            'تنظیمات',
            'manage_options',
            'payping-donate-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'payping-donate',
            'حامیان مالی',
            'حامیان مالی',
            'manage_options',
            'payping-donate-donors',
            [$this, 'render_donors_page']
        );
    }

    public function render_dashboard_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'payping_donate';
        
        // Get donation statistics
        $stats = [
            'today' => $wpdb->get_var("SELECT SUM(AmountTomaan) FROM $table_name WHERE DATE(InputDate) = CURDATE() AND Status = 'OK'"),
            'week'  => $wpdb->get_var("SELECT SUM(AmountTomaan) FROM $table_name WHERE YEARWEEK(InputDate) = YEARWEEK(CURDATE()) AND Status = 'OK'"),
            'month' => $wpdb->get_var("SELECT SUM(AmountTomaan) FROM $table_name WHERE MONTH(InputDate) = MONTH(CURDATE()) AND YEAR(InputDate) = YEAR(CURDATE()) AND Status = 'OK'"),
            'year'  => $wpdb->get_var("SELECT SUM(AmountTomaan) FROM $table_name WHERE YEAR(InputDate) = YEAR(CURDATE()) AND Status = 'OK'"),
            'total' => $wpdb->get_var("SELECT SUM(AmountTomaan) FROM $table_name WHERE Status = 'OK'")
        ];

        // Get recent donations
        $recent_donations = $wpdb->get_results(
            "SELECT * FROM $table_name 
            WHERE Status = 'OK' 
            ORDER BY InputDate DESC 
            LIMIT 10"
        );
        ?>
        <div class="wrap">
            <h1>داشبورد حمایت مالی پی‌پینگ</h1>
            <div class="pp-plugin-info">
                <h2>راهنمای افزونه</h2>
                <p>برای استفاده از افزونه، کد شورت‌کد <code>[PayPing_Donate]</code> را در صفحات یا نوشته‌های خود قرار دهید.</p>
                <p>برای تنظیمات بیشتر به بخش <a href="<?php echo admin_url('admin.php?page=payping-donate-settings'); ?>">تنظیمات</a> مراجعه کنید.</p>
            </div>
            
            <div class="pp-donate-stats">
                <h2>آمار پرداخت‌ها</h2>
                <div class="pp-stat-cards">
                    <div class="pp-stat-card">
                        <h3>امروز</h3>
                        <p><?php echo esc_html($stats['today'] ?? 0); ?> تومان</p>
                    </div>
                    <div class="pp-stat-card">
                        <h3>این هفته</h3>
                        <p><?php echo esc_html($stats['week'] ?? 0); ?> تومان</p>
                    </div>
                    <div class="pp-stat-card">
                        <h3>این ماه</h3>
                        <p><?php echo esc_html($stats['month'] ?? 0); ?> تومان</p>
                    </div>
                    <div class="pp-stat-card">
                        <h3>امسال</h3>
                        <p><?php echo esc_html($stats['year'] ?? 0); ?> تومان</p>
                    </div>
                    <div class="pp-stat-card">
                        <h3>کل پرداخت‌ها</h3>
                        <p><?php echo esc_html($stats['total'] ?? 0); ?> تومان</p>
                    </div>
                </div>
            </div>
            
            <div class="pp-recent-donations">
                <h2>آخرین پرداخت‌ها</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>مبلغ</th>
                            <th>تاریخ</th>
                            <th>کد پیگیری</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_donations as $donation): ?>
                        <tr>
                            <td><?php echo esc_html($donation->Name); ?></td>
                            <td><?php echo esc_html($donation->AmountTomaan); ?> تومان</td>
                            <td><?php echo esc_html(date_i18n('j F Y H:i', strtotime($donation->InputDate))); ?></td>
                            <td><?php echo esc_html($donation->Authority); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        </div>
        <?php
    }

    public function render_donors_page() {
        if ( ! class_exists('Payping_Donate_List') ) {
            include_once PP_DONATE_PATH . 'includes/class-donors.php';
        }
    
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $paged  = isset($_GET['paged']) && is_numeric($_GET['paged']) && $_GET['paged'] > 0 ? intval($_GET['paged']) : 1;
    
        if ($action === 'view' && isset($_GET['donate_id'])) {
            $donate_id = intval($_GET['donate_id']);
            Payping_Donate_List::render_donate_detail($donate_id);
        } else {
            Payping_Donate_List::render($paged);
        }
    }

    public function handle_form_submission() {
        if(!isset($_POST['submit'])) return;

        // Verify nonce for security
        check_admin_referer('payping_donate_settings');

        $fields = [
            'payPingDonate_MerchantID' => 'text',
            'payPingDonate_IsOK' => 'text',
            'payPingDonate_IsError' => 'text'
        ];

        foreach ($fields as $field => $type) {
            if (isset($_POST[$field])) {
                update_option($field, sanitize_text_field($_POST[$field]));
            }
        }

        // Handle custom style toggle
        $use_custom_style = isset($_POST['payPingDonate_UseCustomStyle']) ? 'true' : 'false';
        update_option('payPingDonate_UseCustomStyle', $use_custom_style);

        if ($use_custom_style === 'true' && isset($_POST['payPingDonate_CustomStyle'])) {
            update_option('payPingDonate_CustomStyle', strip_tags($_POST['payPingDonate_CustomStyle']));
        }

        add_settings_error(
            'payping_donate_messages',
            'payping_donate_message',
            'تنظیمات با موفقیت ذخیره شدند',
            'updated'
        );
    }

    public function enqueue_admin_scripts($hook) {
        //if ($hook !== 'payping-donate-settings') return;
        
        wp_enqueue_style(
                'payping-donate-admin',
                PP_DONATE_URL . 'assets/admin/css/payping-donate-style.css',
                [],
                PP_DONATE_VERSION
            );
            
        wp_enqueue_script(
            'payping-donate-admin',
            PP_DONATE_URL . 'assets/admin/js/payping-donate-script.js',
            ['jquery'],
            PP_DONATE_VERSION,
            true
        );
        
    }

    public function render_settings_page() {
        settings_errors('payping_donate_messages');
        ?>
        <div class="wrap">
            <h1>تنظیمات افزونه حمایت مالی - پی‌پینگ</h1>
            <h2>جمع تمام پرداخت‌ها: <?php echo esc_html(get_option("payPingDonate_TotalAmount")); ?> تومان</h2>
            <p>برای استفاده تنها کافی است کد زیر را درون بخشی از برگه یا نوشته خود قرار دهید: <code>[PayPing_Donate]</code></p>
            
            <form method="post">
                <?php wp_nonce_field('payping_donate_settings'); ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th><label for="payPingDonate_MerchantID">توکن</label></th>
                            <td>
                                <input type="text" class="regular-text" 
                                    value="<?php echo esc_attr(get_option('payPingDonate_MerchantID')); ?>" 
                                    id="payPingDonate_MerchantID" name="payPingDonate_MerchantID">
                                <p class="description">توکن درگاه پی‌پینگ</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="payPingDonate_IsOK">متن پرداخت موفق</label></th>
                            <td>
                                <input type="text" class="regular-text" 
                                    value="<?php echo esc_attr(get_option('payPingDonate_IsOK')); ?>" 
                                    id="payPingDonate_IsOK" name="payPingDonate_IsOK">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="payPingDonate_IsError">متن خطا در پرداخت</label></th>
                            <td>
                                <input type="text" class="regular-text" 
                                    value="<?php echo esc_attr(get_option('payPingDonate_IsError')); ?>" 
                                    id="payPingDonate_IsError" name="payPingDonate_IsError">
                            </td>
                        </tr>
                        <tr>
                            <th>استفاده از استایل سفارشی</th>
                            <td>
                                <input type="checkbox" name="payPingDonate_UseCustomStyle" id="payPingDonate_UseCustomStyle" 
                                    value="true" <?php checked(get_option('payPingDonate_UseCustomStyle'), 'true'); ?>>
                                <label for="payPingDonate_UseCustomStyle">استفاده از استایل سفارشی برای فرم</label>
                            </td>
                        </tr>
                        <tr id="payPingDonate_CustomStyleBox" style="<?php echo get_option('payPingDonate_UseCustomStyle') !== 'true' ? 'display:none' : ''; ?>">
                            <th>استایل سفارشی</th>
                            <td>
                                <textarea style="width: 90%; min-height: 400px; direction: ltr;" 
                                    name="payPingDonate_CustomStyle" id="payPingDonate_CustomStyle"><?php 
                                    echo esc_textarea(get_option('payPingDonate_CustomStyle')); 
                                ?></textarea>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button('به روز رسانی تنظیمات'); ?>
            </form>
        </div>
        <?php
    }
}