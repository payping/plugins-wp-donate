<?php

defined('ABSPATH') || exit;

/**
 * Handles donation form rendering and processing
 */
class PP_Donate_Form_Handler {

    public function __construct() {
        add_shortcode('PayPing_Donate', [$this, 'render_form_shortcode']);
        add_action('init', [$this, 'handle_form_submission']);
    }

    /**
     * Renders the donation form via shortcode
     */
    public function render_form_shortcode($atts) {
        ob_start();
    
        // Check for return from PayPing
        if(isset($_GET['donate_verify']) && $_GET['donate_verify'] == 'payping_donate' && isset($_POST['status']) ){
            // 1. Get raw POST data
            $rawData = $_POST['data'];
            
            // 2. Remove extra slashes (if magic_quotes_gpc was enabled in older PHP versions)
            $cleanData = stripslashes($rawData);
            
            // 3. URL decode the data (if it was URL encoded)
            $decodedData = urldecode($cleanData);
            
            // 4. Convert JSON string to PHP array
            $result = json_decode($decodedData, true);
            
            if(isset($_POST['status']) && '0' === $_POST['status']){
                $this->show_notice(
                    __('تراکنش توسط شما لغو شده است.', 'payping-donate'),
                    'error',
                    [
                        'custom_icon' => 'f534', // Dashicon code
                        'border_color' => '#d63638',
                        'bg_color' => '#fef0f1'
                    ]
                );
                return;
            }
            
            // Get donation record with security validation
            $donate = $this->get_donate(absint($result['clientRefId']));
            
            // 1. Check if transaction is already completed
            if (isset($donate['Status']) && 'OK' === $donate['Status']) {
                $this->show_notice(
                    __('این تراکنش قبلاً با موفقیت پرداخت شده است.', 'payping-donate'),
                    'warning'
                );
                return;
            }
            
            // 2. Verify payment code
            if (!isset($result['paymentCode']) || !isset($donate['PaymentCode']) || 
                $result['paymentCode'] !== $donate['PaymentCode']) {
                $this->show_notice(
                    __('کد پرداخت نامعتبر است. لطفاً با پشتیبانی تماس بگیرید.', 'payping-donate'),
                    'error'
                );
                return;
            }
            
            // 3. Verify amount
            if (!isset($result['amount']) || !isset($donate['AmountTomaan']) || 
                intval($result['amount']) !== intval($donate['AmountTomaan'])) {
                $this->show_notice(
                    __('مبلغ پرداختی با مبلغ درخواستی مطابقت ندارد.', 'payping-donate'),
                    'error'
                );
                return;
            }
            
            $this->verify_payment($result['paymentRefId'], $donate);
            
        }
    
        // Load settings
        $token = get_option('payPingDonate_MerchantID');
        if(empty($token)){
            echo '<div class="notice notice-error"><p>توکن پی‌پینگ پیکربندی نشده است.</p></div>';
            return ob_get_clean();
        }
    
        // Parse shortcode attributes
        $atts = shortcode_atts([
            'amount' => '',
        ], $atts);
    
        // Load the form template
        $this->get_template('form.php', [
            'amount_value' => $atts['amount'],
        ]);
    
        return ob_get_clean();
    }

    /**
     * Handles form submission
     */
    public function handle_form_submission() {
        if (!isset($_POST['payping_donate_submit'])){
            return;
        }
    
        // Nonce check
        if (!isset($_POST['payping_donate_nonce']) || !wp_verify_nonce($_POST['payping_donate_nonce'], 'payping_donate_action')) {
            wp_die('درخواست نامعتبر است.');
        }
        
        global $wp;
        
        // Sanitize inputs
        $amount  = intval($_POST['amount']);
        $name    = sanitize_text_field($_POST['name']);
        $email   = sanitize_email($_POST['email']);
        $mobile  = sanitize_text_field($_POST['mobile']);
        $message = sanitize_textarea_field($_POST['message']);

        if ($amount < 1000 || empty($name)) {
            wp_die('اطلاعات وارد شده صحیح نیست.');
        }

        $token = get_option('payPingDonate_MerchantID');
        
        $return_url = $this->get_current_url(['donate_verify' => 'payping_donate']);
        
        $donation_data = [
            'Name' => $name,
            'AmountTomaan' => $amount,
            'Mobile' => $mobile,
            'Email' => $email,
            'Description' => $message,
            'Status' => 'SEND'
        ];
        
        $donation_id = $this->add_donate($donation_data);
        
        // Prepare request to PayPing
        $payment_data = [
            'amount' => intval($amount),
            'returnUrl' => strval($return_url),
            'payerIdentity' => $email ?: $mobile,
            'payerName' => strval($name),
            'description' => strval($message),
            'clientRefId' => strval($donation_id),
            "nationalCode" => null
        ];

        $response = wp_remote_post('https://api.payping.ir/v3/pay', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payment_data),
            'timeout' => 15
        ]);

        if(is_wp_error($response)){
            wp_die('ارتباط با درگاه برقرار نشد.');
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if($code === 200 && isset($body['paymentCode'])){
            $result = $this->set_paymentcode($donation_id, $body['paymentCode']);
            if($result !== false){
                wp_redirect($body['url']); exit;
            }else{
                wp_die("پرداخت با خطا مواجه شد:کد پرداخت ذخیره نشده است! ");
            }
            
        }else{
            $error = isset($body['error_description']) ? esc_html($body['error_description']) : 'خطای ناشناخته‌ای رخ داده است.';
            wp_die("پرداخت با خطا مواجه شد: $error");
        }
    }

    /**
     * Verifies the payment upon return from PayPing
     */
    private function verify_payment($paymentRefId, $donate) {
        if(empty($paymentRefId)){
            $this->show_notice(
                    __('کد پیگری پرداخت یافت نشد.', 'payping-donate'),
                    'error',
                    [
                        'custom_icon' => 'f534', // Dashicon code
                        'border_color' => '#d63638',
                        'bg_color' => '#fef0f1'
                    ]
                );
                return;
        }
        
        $token = get_option('payPingDonate_MerchantID');

        $response = wp_remote_post('https://api.payping.ir/v3/pay/verify', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'paymentRefId' => $paymentRefId,
                'paymentCode' => $donate['PaymentCode'],
                'amount' => intval($donate['AmountTomaan'])
            ]),
            'timeout' => 15
        ]);

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            $this->change_status($donate['DonateID'], 'OK');
            $this->set_authority($donate['DonateID'], $paymentRefId);
            $this->show_notice(
                __('پرداخت موفق، باتشکر از حمایت شما.', 'payping-donate'),
                'success',
                [
                    'custom_icon' => 'f534', // Dashicon code
                    'border_color' => '#d63638',
                    'bg_color' => '#fef0f1'
                ]
            );
        } else {
            $this->show_notice(
                __('مشکلی در تایید پرداخت وجود دارد', 'payping-donate'),
                'error',
                [
                    'custom_icon' => 'f534', // Dashicon code
                    'border_color' => '#d63638',
                    'bg_color' => '#fef0f1'
                ]
            );
        }
        return;
    }
    
    /**
     * Retrieves the full current URL with existing and additional query parameters.
     *
     * Works reliably in frontend (pages, posts, archives), backend (admin pages), 
     * REST API, AJAX, CLI, and shortcode contexts.
     *
     * @param array $args   Additional query parameters to add to the URL.
     * @param bool  $escape Whether to escape the URL for output.
     * @return string       The final computed URL.
     */
    public function get_current_url(array $args = [], bool $escape = true): string {
        // Fallbacks in case server variables are not available (CLI/REST)
        $scheme = is_ssl() ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? parse_url(home_url(), PHP_URL_HOST);
        $uri    = $_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'] ?? '';
    
        // Reconstruct base current URL
        $current_url = $scheme . '://' . $host . $uri;
    
        // Parse current query if exists
        $parsed_url = parse_url($current_url);
        $base_url   = $parsed_url['scheme'] . '://' . $parsed_url['host'] . ($parsed_url['path'] ?? '');
        
        $query_params = [];
        if (!empty($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query_params);
        }
    
        // Merge with additional query args
        if (!empty($args)) {
            $query_params = array_merge($query_params, $args);
        }
    
        // Build final URL
        $final_url = add_query_arg($query_params, $base_url);
    
        return $escape ? esc_url($final_url) : $final_url;
    }
    
    /**
     * Load a template file
     * 
     * @param string $template_name Name of the template file
     * @param array  $args          Variables to pass to the template
     */
    public function get_template($template_name, $args = []) {
        // Allow template overrides in theme
        $template_path = locate_template('pp-donate/' . $template_name);
        
        // If not found in theme, use plugin's template
        if (!$template_path) {
            $template_path = PP_DONATE_PATH . 'templates/' . $template_name;
        }
        
        if (file_exists($template_path)) {
            extract($args);
            include $template_path;
        } else {
            _e('قالب پیدا نشد!', 'pp-donate');
        }
    }
    
    /**
     * Adds a new donation record to the database
     * 
     * @param array $data Donation data including Name, AmountTomaan, etc.
     * @return int|false Returns donation ID on success, false on failure
     */
    private function add_donate($data){
        global $wpdb;
        $DonateTable = $wpdb->prefix . 'payping_donate';
        
        // Set default values and validate
        $default_data = [
            'PaymentCode' => '',
            'Authority' => '',
            'Name' => '',
            'AmountTomaan' => 0,
            'Mobile' => '',
            'Email' => '',
            'InputDate' => current_time('mysql'),
            'Description' => '',
            'Status' => 'SEND' // SEND, OK, or ERROR
        ];
        
        // Merge input data with defaults
        $insert_data = wp_parse_args($data, $default_data);
        
        // Validate required fields
        if (empty($insert_data['Name']) || empty($insert_data['AmountTomaan'])) {
            return false;
        }
        
        // Prepare data for database insertion
        $formatted_data = [
            'PaymentCode' => sanitize_text_field($insert_data['PaymentCode']),
            'Authority' => sanitize_text_field($insert_data['Authority']),
            'Name' => sanitize_text_field($insert_data['Name']),
            'AmountTomaan' => intval($insert_data['AmountTomaan']),
            'Mobile' => $this->sanitize_mobile($insert_data['Mobile']),
            'Email' => sanitize_email($insert_data['Email']),
            'InputDate' => $insert_data['InputDate'],
            'Description' => sanitize_textarea_field($insert_data['Description']),
            'Status' => in_array($insert_data['Status'], ['SEND', 'OK', 'ERROR']) ? $insert_data['Status'] : 'SEND'
        ];
        
        // Insert into database using WordPress DB API
        $result = $wpdb->insert($DonateTable, $formatted_data);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Retrieves a donation record by ID
     * 
     * @param int $id Donation ID
     * @return array|false Donation data array or false if not found
     */
    private function get_donate($id){
        global $wpdb;
        $DonateTable = $wpdb->prefix . 'payping_donate';
        
        // Prepare and validate ID
        $donate_id = absint($id);
        if (!$donate_id) {
            return false;
        }
        
        // Use prepared statement for security
        $query = $wpdb->prepare(
            "SELECT * FROM {$DonateTable} WHERE DonateID = %d LIMIT 1",
            $donate_id
        );
        
        $result = $wpdb->get_row($query, ARRAY_A);
        
        return $result ?: false;
    }
    
    /**
     * Updates donation status
     * 
     * @param int $id Donation ID
     * @param string $status New status (SEND, OK, ERROR)
     * @return int|false Number of rows affected or false on failure
     */
    private function change_status($id, $status){
        global $wpdb;
        $DonateTable = $wpdb->prefix . 'payping_donate';
        
        // Validate inputs
        $donate_id = absint($id);
        $valid_statuses = ['SEND', 'OK', 'ERROR'];
        
        if (!$donate_id || !in_array($status, $valid_statuses)) {
            return false;
        }
        
        // Use prepared statement
        return $wpdb->update(
            $DonateTable,
            ['Status' => $status],
            ['DonateID' => $donate_id],
            ['%s'], // Status format
            ['%d']  // ID format
        );
    }
    
    /**
     * Updates payment code for a donation record
     * 
     * @param int $id Donation ID
     * @param string $payment_code Payment code to store
     * @return int|false Number of rows affected or false on failure
     */
    private function set_paymentcode($id, $payment_code) {
        global $wpdb;
        $DonateTable = $wpdb->prefix . 'payping_donate';
        
        // Validate inputs
        $donate_id = absint($id);
        $clean_payment_code = sanitize_text_field($payment_code);
        
        if (!$donate_id || empty($clean_payment_code)) {
            return false;
        }
        
        // Use prepared statement to update PaymentCode
        return $wpdb->update(
            $DonateTable,
            ['PaymentCode' => $clean_payment_code],  // Column to update
            ['DonateID' => $donate_id],             // Where condition
            ['%s'],                                 // PaymentCode format (string)
            ['%d']                                  // ID format (integer)
        );
    }
    
    /**
     * Sets authority code for a donation
     * 
     * @param int $id Donation ID
     * @param string $authority Authority code
     * @return int|false Number of rows affected or false on failure
     */
    private function set_authority($id, $authority){
        global $wpdb;
        $DonateTable = $wpdb->prefix . 'payping_donate';
        
        // Validate inputs
        $donate_id = absint($id);
        $clean_authority = sanitize_text_field($authority);
        
        if (!$donate_id || empty($clean_authority)) {
            return false;
        }
        
        // Use prepared statement
        return $wpdb->update(
            $DonateTable,
            ['Authority' => $clean_authority],
            ['DonateID' => $donate_id],
            ['%s'], // Authority format
            ['%d']  // ID format
        );
    }

    /**
     * Sanitizes mobile number (Persian format)
     * 
     * @param string $mobile Mobile number
     * @return string Sanitized mobile number
     */
    private function sanitize_mobile($mobile){
        // Remove all non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $mobile);
        
        // Convert Persian numbers to English
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $cleaned = str_replace($persian, $english, $cleaned);
        
        // Validate Iranian mobile format (09xxxxxxxxx)
        if (preg_match('/^09\d{9}$/', $cleaned)) {
            return $cleaned;
        }
        
        return '';
    }
    
    /**
     * Gets human-readable status message from status code
     * 
     * @param int $status_number HTTP status code
     * @return string Status message in Persian
     */
    private function get_result_status_string($status_number) {
        $status_codes = [
            200 => 'عملیات با موفقیت انجام شد',
            400 => 'مشکلی در ارسال درخواست وجود دارد',
            401 => 'عدم دسترسی',
            403 => 'دسترسی غیر مجاز',
            404 => 'آیتم درخواستی مورد نظر موجود نمی‌باشد',
            500 => 'مشکلی در سرور رخ داده است',
            503 => 'سرور در حال حاضر قادر به پاسخگویی نمی‌باشد'
        ];

        return $status_codes[$status_number] ?? 'وضعیت نامشخص';
    }
    
    /**
     * Display styled notice to user with custom options
     * 
     * @param string $message Message text
     * @param string $type    Notice type (error|warning|success|info)
     * @param array  $args    Additional arguments {
     *     @type string $custom_icon    Optional dashicon code
     *     @type string $border_color   Custom border color
     *     @type string $bg_color       Custom background color
     * }
     */
    private function show_notice($message, $type = 'error', $args = []) {
        if (is_admin()) {
            wp_enqueue_style('dashicons');
        }
    
        $classes = [
            'error'   => 'notice notice-error is-dismissible',
            'success' => 'notice notice-success is-dismissible',
            'warning' => 'notice notice-warning is-dismissible',
            'info'    => 'notice notice-info is-dismissible'
        ];
    
        // Start output buffering to capture styles
        ob_start();
        ?>
        <div class="payping-notice <?php echo esc_attr($classes[$type] ?? $classes['error']); ?>">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
        
        // Add custom styles if needed
        if (!empty($args['custom_icon']) || !empty($args['border_color']) || !empty($args['bg_color'])) {
            $css = '.payping-notice {';
            $css .= !empty($args['bg_color']) ? 'background:' . esc_attr($args['bg_color']) . ';' : '';
            $css .= !empty($args['border_color']) ? 'border-left-color:' . esc_attr($args['border_color']) . ';' : '';
            $css .= '}';
            
            if (!empty($args['custom_icon'])) {
                $css .= '.payping-notice::before {';
                $css .= 'content:"\\' . esc_attr($args['custom_icon']) . '";';
                $css .= 'font-family:dashicons;';
                $css .= 'font-size:20px;';
                $css .= !empty($args['border_color']) ? 'color:' . esc_attr($args['border_color']) . ';' : '';
                $css .= 'margin-right:10px;';
                $css .= '}';
            }
            
            wp_add_inline_style('dashicons', $css);
        }
        
        echo ob_get_clean();
    }
}