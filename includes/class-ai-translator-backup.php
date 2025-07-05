<?php
// includes/class-ai-translator-backup.php

// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * کلاسی برای مدیریت عملیات پشتیبان‌گیری از فایل‌های ترجمه.
 */
class AI_Translator_Backup {

    private $database;

    public function __construct( $database ) {
        $this->database = $database;
    }

    /**
     * یک نسخه پشتیبان از فایل PO و MO مشخص شده ایجاد می‌کند.
     *
     * @param string $item_id       شناسه آیتم ترجمه شده (مثلاً slug قالب یا افزونه).
     * @param string $po_filepath   مسیر کامل فایل .po اصلی برای پشتیبان‌گیری.
     * @param string $mo_filepath   مسیر کامل فایل .mo اصلی برای پشتیبان‌گیری.
     * @return array|WP_Error آرایه‌ای از مسیرهای فایل‌های پشتیبان ایجاد شده، یا WP_Error در صورت خطا.
     */
    public function create_backup( $item_id, $po_filepath, $mo_filepath ) {
        $upload_dir   = wp_upload_dir();
        $backup_base_dir = $upload_dir['basedir'] . '/ai-translator-backups/';
        
        // اطمینان از وجود دایرکتوری پشتیبان‌گیری
        if ( ! is_dir( $backup_base_dir ) ) {
            if ( ! wp_mkdir_p( $backup_base_dir ) ) {
                return new WP_Error( 'backup_dir_creation_failed', 'خطا در ایجاد پوشه پشتیبان‌گیری: ' . $backup_base_dir );
            }
        }

        $timestamp = current_time( 'Ymd-His' );
        $filename_base = basename( $po_filepath, '.po' ); // Get filename without .po extension

        $po_backup_path = $backup_base_dir . $filename_base . '-backup-' . $timestamp . '.po';
        $mo_backup_path = $backup_base_dir . $filename_base . '-backup-' . $timestamp . '.mo';

        $backed_up_files = [];

        // پشتیبان‌گیری از فایل .po
        if ( file_exists( $po_filepath ) && is_readable( $po_filepath ) ) {
            if ( copy( $po_filepath, $po_backup_path ) ) {
                $backed_up_files[] = $po_backup_path;
            } else {
                error_log( 'AI Translator Backup Error: Failed to copy PO file ' . $po_filepath );
                // Do not return error here, try to backup MO file too
            }
        }

        // پشتیبان‌گیری از فایل .mo
        if ( file_exists( $mo_filepath ) && is_readable( $mo_filepath ) ) {
            if ( copy( $mo_filepath, $mo_backup_path ) ) {
                $backed_up_files[] = $mo_backup_path;
            } else {
                error_log( 'AI Translator Backup Error: Failed to copy MO file ' . $mo_filepath );
            }
        }

        if ( empty( $backed_up_files ) ) {
            return new WP_Error( 'no_files_backed_up', 'هیچ فایلی برای پشتیبان‌گیری یافت نشد یا عملیات کپی با شکست مواجه شد.' );
        }

        // این اطلاعات پشتیبان را به تاریخچه (History) اضافه می‌کنیم
        // توضیحات و جزئیات بیشتر در کلاس AI_Translator_Admin در تابع save_translation_ajax() اضافه می‌شود.
        
        return $backed_up_files;
    }

    /**
     * (اختیاری) یک تابع برای بازیابی از پشتیبان (برای پیاده‌سازی آینده).
     *
     * @param string $backup_filepath مسیر کامل فایل پشتیبان برای بازیابی.
     * @param string $original_filepath مسیر اصلی که باید فایل به آن بازیابی شود.
     * @return bool|WP_Error True در صورت موفقیت، یا WP_Error در صورت خطا.
     */
    public function restore_backup( $backup_filepath, $original_filepath ) {
        if ( ! file_exists( $backup_filepath ) || ! is_readable( $backup_filepath ) ) {
            return new WP_Error( 'backup_file_not_found', 'فایل پشتیبان یافت نشد یا قابل خواندن نیست.' );
        }
        
        // اطمینان از اینکه دایرکتوری مقصد وجود دارد
        $dir = dirname( $original_filepath );
        if ( ! is_dir( $dir ) ) {
            if ( ! wp_mkdir_p( $dir ) ) {
                return new WP_Error( 'target_dir_creation_failed', 'خطا در ایجاد پوشه مقصد برای بازیابی.' );
            }
        }

        if ( copy( $backup_filepath, $original_filepath ) ) {
            // ممکن است نیاز به بازسازی فایل .mo مربوطه هم باشد
            return true;
        } else {
            return new WP_Error( 'restore_failed', 'خطا در بازیابی فایل از پشتیبان.' );
        }
    }

    /**
     * (اختیاری) پاکسازی پشتیبان‌های قدیمی (برای پیاده‌سازی آینده).
     */
    public function clean_old_backups() {
        // این تابع می‌تواند برای حذف پشتیبان‌های قدیمی‌تر از X روز/ماه استفاده شود.
    }
}
