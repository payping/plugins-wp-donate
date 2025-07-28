<?php
class PP_Donate_DB {
    /**
     * Install or upgrade donation table with proper UTF-8 support for Persian.
     */
    public static function install() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'payping_donate';
        $current_db_version = get_option('pp_donate_db_version', '1.0');

        // Force Persian-friendly charset and collation
        $charset_collate = "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

        // 1. Create table if not exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            $sql = "CREATE TABLE $table_name (
                `DonateID` INT(11) NOT NULL AUTO_INCREMENT,
                `PaymentCode` VARCHAR(100) DEFAULT NULL,
                `Authority` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                `Name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                `AmountTomaan` BIGINT NOT NULL,
                `Mobile` VARCHAR(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                `Email` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                `Description` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                `InputDate` DATETIME NOT NULL,
                `Status` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
                PRIMARY KEY (`DonateID`)
            ) $charset_collate;";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }
        // 2. Run updates for existing tables
        else {
            if (version_compare($current_db_version, '1.1', '<')) {
                self::update_to_1_1($table_name);
            }
        }

        // 3. Log any DB errors
        if (!empty($wpdb->last_error)) {
            error_log('PayPing Donate DB Error: ' . $wpdb->last_error);
        }
    }

    /**
     * Update database to version 1.1
     * Adds PaymentCode column if missing
     */
    private static function update_to_1_1($table_name) {
        global $wpdb;

        // Check if PaymentCode exists
        $column_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_NAME = %s 
                 AND COLUMN_NAME = 'PaymentCode' 
                 AND TABLE_SCHEMA = DATABASE()",
                $table_name
            )
        );

        if (!$column_exists) {
            $result = $wpdb->query(
                "ALTER TABLE $table_name 
                 ADD COLUMN `PaymentCode` VARCHAR(100) DEFAULT NULL AFTER `DonateID`"
            );

            if ($result === false) {
                error_log("Failed to add PaymentCode column to $table_name");
            } else {
                error_log("Successfully added PaymentCode column to $table_name");
            }
        }

        // Ensure columns support Persian
        $wpdb->query("ALTER TABLE $table_name CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
}
