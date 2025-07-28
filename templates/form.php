<?php
/**
 * Donation Form Template
 * 
 * @param array $args {
 *     Optional. Array of arguments.
 *     
 *     @type string $form_class
 *     @type string $amount_value
 * }
 */
if (!isset($args)) {
    $args = [];
}

$defaults = [
    'form_class' => 'payping-donate-form',
    'amount_value' => '',
];

$args = wp_parse_args($args, $defaults);

// Check if custom style should be added
if (!get_option('pp_donate_custom_style')) {
    $form_class = esc_attr($args['form_class'] ?? 'payping-donate-form');
    
    echo <<<CSS
    <style>
        :root {
            --pp-primary-color: #000060;
            --pp-primary-hover: #0000ff;
            --pp-text-color: #ffffff;
            --pp-border-radius: 4px;
            --pp-transition: all 0.3s ease;
        }
        
        .{$form_class} input:not([type="submit"]),
        .{$form_class} textarea,
        .{$form_class} select {
            display: block;
            width: 100%;
            margin: 10px 0;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: var(--pp-border-radius);
            font-size: 14px;
            line-height: 1.5;
            transition: border-color 0.3s ease;
        }
        
        .{$form_class} input:focus,
        .{$form_class} textarea:focus,
        .{$form_class} select:focus {
            outline: none;
            border-color: var(--pp-primary-color);
            box-shadow: 0 0 0 2px rgba(0, 0, 96, 0.1);
        }
        
        .{$form_class} input[type="submit"] {
            display: block;
            width: 100%;
            background-color: var(--pp-primary-color);
            color: var(--pp-text-color);
            border: none;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: bold;
            border-radius: var(--pp-border-radius);
            cursor: pointer;
            transition: var(--pp-transition);
            margin: 20px 0;
            text-align: center;
        }
        
        .{$form_class} input[type="submit"]:hover,
        .{$form_class} input[type="submit"]:focus {
            background-color: var(--pp-primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }
        
        .{$form_class} input[type="submit"]:active {
            transform: translateY(0);
            box-shadow: none;
        }
    </style>
CSS;
}
?>

<form method="post" class="<?php echo esc_attr($args['form_class']); ?>">
    <?php wp_nonce_field('payping_donate_action', 'payping_donate_nonce'); ?>

    <p>
        <label>مبلغ (تومان)</label><br>
        <input type="number" name="amount" required min="1000" value="<?php echo esc_attr($args['amount_value']); ?>" />
    </p>
    <p>
        <label>نام</label><br>
        <input type="text" name="name" required />
    </p>
    <p>
        <label>ایمیل</label><br>
        <input type="email" name="email" />
    </p>
    <p>
        <label>شماره موبایل</label><br>
        <input type="text" name="mobile" pattern="^09\d{9}$" />
    </p>
    <p>
        <label>یادداشت</label><br>
        <textarea name="message" rows="3"></textarea>
    </p>

    <input type="submit" name="payping_donate_submit" value="پرداخت" class="button payping-donate-submit button-primary" />
</form>