<?php
// includes/class-ai-translator-database.php

// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * کلاسی برای مدیریت تعاملات دیتابیس پلاگین.
 * جداول سفارشی را برای آمار و تاریخچه تغییرات ایجاد و مدیریت می‌کند.
 */
class AI_Translator_Database {

    private $table_prefix;

    public function __construct() {
        global $wpdb;
        $this->table_prefix = $wpdb->prefix . 'ai_translator_';
    }

    /**
     * جداول دیتابیس مورد نیاز پلاگین را ایجاد می‌کند.
     * این تابع در زمان فعال‌سازی پلاگین فراخوانی می‌شود.
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // جدول برای ذخیره آمار پلاگین
        $stats_table_name = $this->table_prefix . 'stats';
        $sql_stats = "CREATE TABLE $stats_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            stat_name varchar(255) NOT NULL UNIQUE,
            stat_value longtext NOT NULL,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // جدول برای ذخیره سابقه تغییرات (History)
        $history_table_name = $this->table_prefix . 'history';
        $sql_history = "CREATE TABLE $history_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            item_id varchar(255) NOT NULL,
            item_name varchar(255) NOT NULL,
            file_path varchar(255) NOT NULL,
            operation_type varchar(50) NOT NULL, -- e.g., 'auto_translate', 'manual_edit', 'backup', 'restore', 'saved'
            changes_description text,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY item_id (item_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_stats );
        dbDelta( $sql_history );

        // Initial stats setup
        $this->initialize_stats();
    }

    /**
     * آمارهای اولیه را در دیتابیس تنظیم می‌کند.
     */
    private function initialize_stats() {
        global $wpdb;
        $stats_table_name = $this->table_prefix . 'stats';

        $default_stats = [
            'total_translations'     => 0,
            'total_strings_translated' => 0,
            'api_char_count_month'   => 0, // This should ideally reset monthly
            'cached_strings'         => 0,
            'last_month_reset'       => current_time('mysql'),
            'progress_report'        => json_encode([]), // Store as JSON array of recent activities
        ];

        foreach ( $default_stats as $stat_name => $default_value ) {
            $existing_stat = $wpdb->get_var( $wpdb->prepare(
                "SELECT stat_value FROM $stats_table_name WHERE stat_name = %s",
                $stat_name
            ) );

            if ( $existing_stat === null ) { // Only insert if not exists
                $wpdb->insert(
                    $stats_table_name,
                    [
                        'stat_name'  => $stat_name,
                        'stat_value' => $default_value,
                    ],
                    [ '%s', '%s' ]
                );
            }
        }
    }

    /**
     * یک آمار خاص را به‌روزرسانی می‌کند.
     *
     * @param string $stat_name  نام آمار برای به‌روزرسانی.
     * @param mixed  $value      مقدار جدید آمار.
     * @param bool   $increment  آیا مقدار جدید به مقدار موجود اضافه شود (true) یا جایگزین شود (false).
     * @return bool              True در صورت موفقیت، False در صورت شکست.
     */
    public function update_stat( $stat_name, $value, $increment = true ) {
        global $wpdb;
        $stats_table_name = $this->table_prefix . 'stats';

        $current_value = $wpdb->get_var( $wpdb->prepare(
            "SELECT stat_value FROM $stats_table_name WHERE stat_name = %s",
            $stat_name
        ) );

        if ( $current_value === null ) {
            // Stat does not exist, insert it
            return $wpdb->insert(
                $stats_table_name,
                [
                    'stat_name'  => $stat_name,
                    'stat_value' => $value,
                ],
                [ '%s', '%s' ]
            );
        } else {
            if ( $increment ) {
                // Ensure numeric value for increment
                $new_value = is_numeric($current_value) ? (float)$current_value + (float)$value : $value;
            } else {
                $new_value = $value;
            }

            return $wpdb->update(
                $stats_table_name,
                [ 'stat_value' => $new_value ],
                [ 'stat_name'  => $stat_name ],
                [ '%s' ],
                [ '%s' ]
            );
        }
    }

    /**
     * تمام آمارهای ذخیره شده را بازیابی می‌کند.
     *
     * @return array آرایه‌ای از آمارها.
     */
    public function get_stats() {
        global $wpdb;
        $stats_table_name = $this->table_prefix . 'stats';

        // Fetch all stats
        $results = $wpdb->get_results( "SELECT stat_name, stat_value FROM $stats_table_name", ARRAY_A );
        
        $stats = [];
        foreach ( $results as $row ) {
            $stats[ $row['stat_name'] ] = $row['stat_value'];
        }

        // Decode progress_report if it's stored as JSON
        if ( isset( $stats['progress_report'] ) ) {
            $decoded_report = json_decode( $stats['progress_report'], true );
            $stats['progress_report'] = is_array($decoded_report) ? $decoded_report : [];
        } else {
            $stats['progress_report'] = [];
        }

        return $stats;
    }

    /**
     * یک ورودی جدید به جدول تاریخچه تغییرات اضافه می‌کند.
     *
     * @param string $item_id            شناسه آیتم (قالب/افزونه).
     * @param string $item_name          نام نمایش آیتم.
     * @param string $file_path          مسیر فایل .po/.mo مرتبط.
     * @param string $operation_type     نوع عملیات (مثلاً 'auto_translate', 'manual_edit', 'backup').
     * @param string $changes_description توضیحات مختصری از تغییرات.
     * @return bool                      True در صورت موفقیت، False در صورت شکست.
     */
    public function add_history_entry( $item_id, $item_name, $file_path, $operation_type, $changes_description ) {
        global $wpdb;
        $history_table_name = $this->table_prefix . 'history';

        return $wpdb->insert(
            $history_table_name,
            [
                'item_id'           => $item_id,
                'item_name'         => $item_name,
                'file_path'         => $file_path,
                'operation_type'    => $operation_type,
                'changes_description' => $changes_description,
                'timestamp'         => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    /**
     * تمام ورودی‌های تاریخچه را بازیابی می‌کند.
     *
     * @param int $limit تعداد ورودی‌های قابل بازیابی.
     * @return array آرایه‌ای از ورودی‌های تاریخچه.
     */
    public function get_history( $limit = 100 ) {
        global $wpdb;
        $history_table_name = $this->table_prefix . 'history';

        // Order by timestamp in descending order to get most recent first
        $results = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $history_table_name ORDER BY timestamp DESC LIMIT %d", $limit ),
            ARRAY_A
        );

        return $results;
    }
}
