<?php
// admin/class-ai-translator-admin.php

// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * کلاسی برای مدیریت رابط کاربری و عملکرد پنل مدیریت پلاگین.
 */
class AI_Translator_Admin {

    private $settings;
    private $file_handler;
    private $api_client;
    private $backup;
    private $database;

    /**
     * سازنده کلاس.
     * نمونه‌های کلاس‌های مورد نیاز را از AI_Translator دریافت می‌کند و هوک‌های AJAX را ثبت می‌کند.
     */
    public function __construct( $settings, $file_handler, $api_client, $backup, $database ) {
        $this->settings     = $settings;
        $this->file_handler = $file_handler;
        $this->api_client   = $api_client; // Corrected assignment
        $this->backup       = $backup;     // Corrected assignment
        $this->database     = $database;   // Corrected assignment

        // هوک‌های AJAX برای ارتباط React با PHP
        // این هوک‌ها مستقیماً در سازنده ثبت می‌شوند تا از بارگذاری زودهنگام آن‌ها اطمینان حاصل شود.
        // این گام حیاتی برای حل مشکل wp-auth-check است.
        add_action( 'wp_ajax_ai_translator_get_translatable_items', array( $this, 'get_translatable_items_ajax' ) );
        add_action( 'wp_ajax_ai_translator_get_po_data_for_editing', array( $this, 'get_po_data_for_editing_ajax' ) );
        add_action( 'wp_ajax_ai_translator_translate_item', array( $this, 'translate_item_ajax' ) );
        add_action( 'wp_ajax_ai_translator_save_translation', array( $this, 'save_translation_ajax' ) );
        add_action( 'wp_ajax_ai_translator_get_settings', array( $this, 'get_settings_ajax' ) );
        add_action( 'wp_ajax_ai_translator_save_settings', array( $this, 'save_settings_ajax' ) );
        add_action( 'wp_ajax_ai_translator_get_stats', array( $this, 'get_stats_ajax' ) );
        add_action( 'wp_ajax_ai_translator_get_history', array( $this, 'get_history_ajax' ) );
        add_action( 'wp_ajax_ai_translator_rewrite_text_gemini', array( $this, 'rewrite_text_gemini_ajax' ) );
    }

    /**
     * افزودن منو پلاگین به پنل مدیریت وردپرس.
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            esc_html__( 'AI Translator', 'ai-translator' ), // عنوان صفحه در تایتل مرورگر
            esc_html__( 'AI Translator', 'ai-translator' ), // عنوان منو
            'manage_options', // قابلیت کاربر برای دسترسی
            'ai-translator', // اسلاگ منو
            array( $this, 'display_plugin_admin_page' ), // کال‌بک تابع برای نمایش صفحه
            'dashicons-translation', // آیکون منو
            6 // موقعیت منو
        );
    }

    /**
     * تابع کال‌بک برای نمایش صفحه اصلی پلاگین.
     * این تابع کانتینر HTML را برای React App فراهم می‌کند.
     */
    public function display_plugin_admin_page() {
        echo '<div id="ai-translator-root"></div>'; // این div نقطه mount شدن React App است
    }

    /**
     * بارگذاری استایل‌ها و اسکریپت‌های مورد نیاز در پنل مدیریت.
     * اسکریپت‌های React App در اینجا enqueue می‌شوند.
     */
    public function enqueue_styles_scripts() {
        // فقط در صفحه پلاگین ما بارگذاری شود
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'ai-translator' ) {
            // مسیر پایه به پوشه `admin/` پلاگین
            $admin_url = AI_TRANSLATOR_URL . 'admin/';
            $admin_dir = AI_TRANSLATOR_DIR . 'admin/';

            $script_handle_to_localize = 'ai-translator-admin-main'; // Default to production handle

            // اگر در حالت توسعه هستیم (برای livereload)
            // با استفاده از یک فایل نشانگر و WP_DEBUG
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $admin_dir . '.env.development.local' ) ) {
                // فرض می‌کنیم React Dev Server روی پورت 3000 اجرا می‌شود.
                // این اسکریپت‌ها و استایل‌ها توسط سرور توسعه React ارائه می‌شوند.
                wp_enqueue_script( 'ai-translator-react-dev-bundle', 'http://localhost:3000/static/js/bundle.js', array(), null, true );
                wp_enqueue_style( 'ai-translator-react-dev-main-css', 'http://localhost:3000/static/css/main.css', array(), null, 'all' ); // ممکن است وجود نداشته باشد
                $script_handle_to_localize = 'ai-translator-react-dev-bundle'; // Update handle for dev
            } else {
                // در حالت Production (بعد از اجرای `npm run build`)
                // این فایل‌ها از پوشه `admin/build/` بارگذاری می‌شوند.
                $manifest_path = $admin_dir . 'build/asset-manifest.json';
                if ( file_exists( $manifest_path ) ) {
                    $manifest = json_decode( file_get_contents( $manifest_path ), true );

                    if ( is_array( $manifest ) && isset( $manifest['entrypoints'] ) ) {
                        $entrypoints = $manifest['entrypoints'];

                        // Enqueue CSS files
                        foreach ( $entrypoints as $entrypoint_file ) {
                            if ( preg_match( '/\.css$/', $entrypoint_file ) ) {
                                wp_enqueue_style(
                                    'ai-translator-admin-style-' . sanitize_title( basename( $entrypoint_file ) ),
                                    $admin_url . 'build/' . $entrypoint_file,
                                    array(),
                                    AI_TRANSLATOR_VERSION,
                                    'all'
                                );
                            }
                        }

                        // Enqueue JS files
                        // Order is important: runtime-main.js, then main.js, then other chunks
                        $js_files_to_enqueue = [];
                        foreach ( $entrypoints as $entrypoint_file ) {
                            if ( preg_match( '/\.js$/', $entrypoint_file ) ) {
                                if ( strpos( $entrypoint_file, 'runtime-main' ) !== false ) {
                                    $js_files_to_enqueue['runtime'] = $entrypoint_file;
                                } elseif ( strpos( $entrypoint_file, 'main' ) !== false ) {
                                    $js_files_to_enqueue['main'] = $entrypoint_file;
                                } else {
                                    $js_files_to_enqueue['chunks'][] = $entrypoint_file;
                                }
                            }
                        }

                        // Ensure runtime is enqueued first, then main, then chunks
                        if ( isset( $js_files_to_enqueue['runtime'] ) ) {
                            wp_enqueue_script(
                                'ai-translator-admin-runtime',
                                $admin_url . 'build/' . $js_files_to_enqueue['runtime'],
                                array(),
                                AI_TRANSLATOR_VERSION,
                                true
                            );
                        }
                        $main_deps = isset( $js_files_to_enqueue['runtime'] ) ? array('ai-translator-admin-runtime', 'wp-element') : array('wp-element');
                        if ( isset( $js_files_to_enqueue['main'] ) ) {
                            wp_enqueue_script(
                                'ai-translator-admin-main',
                                $admin_url . 'build/' . $js_files_to_enqueue['main'],
                                $main_deps,
                                AI_TRANSLATOR_VERSION,
                                true
                            );
                        }
                        if ( isset( $js_files_to_enqueue['chunks'] ) && is_array( $js_files_to_enqueue['chunks'] ) ) {
                            foreach ( $js_files_to_enqueue['chunks'] as $chunk_file ) {
                                wp_enqueue_script(
                                    'ai-translator-admin-chunk-' . sanitize_title( basename( $chunk_file ) ),
                                    $admin_url . 'build/' . $chunk_file,
                                    array( 'ai-translator-admin-main' ), // Chunks depend on main script
                                    AI_TRANSLATOR_VERSION,
                                    true
                                );
                            }
                        }

                    } else {
                        error_log('AI Translator: asset-manifest.json does not contain valid entrypoints.');
                    }
                } else {
                    error_log('AI Translator: React build files not found (asset-manifest.json missing). Please run `npm install` and `npm run build` in the `admin/` directory.');
                }
            }


            // داده‌های لازم برای React App را به صورت یک آبجکت JS به صفحه تزریق می‌کنیم
            // این مهم است که بعد از enqueue کردن اسکریپت اصلی React باشد.
            // اطمینان حاصل کنید که 'ai-translator-admin-main' (یا هر اسکریپت اصلی React) بارگذاری شده است.
            wp_localize_script(
                $script_handle_to_localize, // از هندل تعیین شده استفاده کنید
                'aiTranslatorData',
                array(
                    'ajax_url'      => admin_url( 'admin-ajax.php' ),
                    'nonce'         => wp_create_nonce( 'ai_translator_nonce' ),
                    'plugin_url'    => AI_TRANSLATOR_URL,
                    'plugin_version' => AI_TRANSLATOR_VERSION,
                )
            );
        }
    }

    /**
     * تابع AJAX برای دریافت لیست قالب‌ها و افزونه‌های قابل ترجمه.
     * این تابع توسط React App فراخوانی می‌شود.
     */
    public function get_translatable_items_ajax() {
        error_reporting(E_ALL); // برای دیباگ فعال کنید
        @ini_set('display_errors', 1); // برای دیباگ فعال کنید

        // Logging to confirm this AJAX handler is reached
        error_log('AI Translator: get_translatable_items_ajax called.');

        check_ajax_referer( 'ai_translator_nonce', 'nonce' );

        $items = $this->file_handler->find_translatable_files(); 
        
        wp_send_json_success( array_values($items) );
    }

    /**
     * تابع AJAX جدید برای دریافت داده‌های کامل PO برای یک آیتم خاص.
     * این تابع توسط React App هنگام باز شدن مدال ویرایش فراخوانی می‌شود.
     */
    public function get_po_data_for_editing_ajax() {
        error_reporting(E_ALL); // برای دیباگ فعال کنید
        @ini_set('display_errors', 1); // برای دیباگ فعال کنید

        error_log('AI Translator: get_po_data_for_editing_ajax called.');

        check_ajax_referer( 'ai_translator_nonce', 'nonce' );

        $item_id = sanitize_text_field( $_POST['item_id'] ?? '' );
        if ( empty( $item_id ) ) {
            wp_send_json_error( array( 'message' => 'شناسه آیتم نامعتبر است.' ) );
            return;
        }

        $translatable_files = $this->file_handler->find_translatable_files();
        $po_file_info = null;
        foreach ($translatable_files as $file_info) {
            if ($file_info['id'] === $item_id) {
                $po_file_info = $file_info;
                break;
            }
        }

        if ( is_null($po_file_info) ) {
            wp_send_json_error( array( 'message' => 'فایل ترجمه یافت نشد در لیست فایل های قابل ترجمه. (ID: ' . $item_id . ')' ) );
            return;
        }
        
        // اگر فایل از نوع .pot است و یا از نوع .po است اما عملاً موجود نیست، خطا بدهید.
        if ( ! $po_file_info['exists'] ) {
             wp_send_json_error( array( 'message' => 'این فایل (یا قالب/افزونه اصلی آن) حذف شده و قابل ویرایش نیست.' ) );
             return;
        }
        // POT files are templates, not meant for direct editing of translations in this manner.
        // We only allow editing of existing .po files.
        if ( strpos($po_file_info['filename'], '.pot') !== false) {
             wp_send_json_error( array( 'message' => 'فایل POT یک قالب است و برای ویرایش مستقیم رشته‌های ترجمه مناسب نیست. لطفاً یک فایل .po واقعی انتخاب کنید.' ) );
             return;
        }


        $po_data = $this->file_handler->read_po_file( $po_file_info['filepath'] );
        if ( is_wp_error( $po_data ) ) {
            wp_send_json_error( array( 'message' => $po_data->get_error_message() ) );
            return;
        }

        wp_send_json_success( $po_data );
    }


    /**
     * تابع AJAX برای انجام عملیات ترجمه خودکار.
     * توسط React App برای شروع ترجمه فراخوانی می‌شود.
     */
    public function translate_item_ajax() {
        error_reporting(E_ALL); // برای دیباگ فعال کنید
        @ini_set('display_errors', 1); // برای دیباگ فعال کنید

        error_log('AI Translator: translate_item_ajax called.');

        check_ajax_referer( 'ai_translator_nonce', 'nonce' );

        // اعتبارسنجی ورودی‌ها
        $item_id      = sanitize_text_field( $_POST['item_id'] ?? '' );
        $target_lang  = sanitize_text_field( $_POST['target_lang'] ?? '' );
        $force_retranslate = isset($_POST['force_retranslate']) ? (bool) $_POST['force_retranslate'] : false;

        if ( empty( $item_id ) || empty( $target_lang ) ) {
            wp_send_json_error( array( 'message' => 'شناسه آیتم یا زبان مقصد نامعتبر است.' ) );
            return;
        }

        // بارگذاری تنظیمات مترجم
        $plugin_settings = $this->settings->get_settings();
        $translator_type = $plugin_settings['default_translator'] ?? 'libretranslate'; // Default to LibreTranslate
        $api_keys = array(
            'chatgpt' => $plugin_settings['chatgpt_api_key'] ?? '',
            'google'  => $plugin_settings['google_translate_api_key'] ?? '',
            'grok'    => $plugin_settings['grok_api_key'] ?? '',
            'gemini'  => $plugin_settings['gemini_api_key'] ?? '',
        );

        // پیدا کردن مسیر فایل .po/.pot مربوط به $item_id
        $translatable_files = $this->file_handler->find_translatable_files();
        $po_file_info = null;
        foreach ($translatable_files as $file_info) {
            if ($file_info['id'] === $item_id) {
                $po_file_info = $file_info;
                break;
            }
        }

        if ( is_null($po_file_info) || ! $po_file_info['exists'] ) {
            wp_send_json_error( array( 'message' => 'فایل ترجمه یا POT یافت نشد یا وجود خارجی ندارد.' ) );
            return;
        }
        
        // اگر این یک فایل ترجمه (PO) است که از قبل وجود دارد و حاوی ترجمه است،
        // و کاربر گزینه "بازترجمه اجباری" را فعال نکرده است،
        // فقط رشته‌های ترجمه نشده را از آن فایل موجود استخراج می‌کنیم.
        // اگر این یک فایل POT است، تمام رشته‌ها را برای ترجمه در نظر می‌گیریم.
        $strings_to_translate_original = [];
        $po_data_current = $this->file_handler->read_po_file( $po_file_info['filepath'] );
        if ( is_wp_error( $po_data_current ) ) {
            wp_send_json_error( array( 'message' => $po_data_current->get_error_message() ) );
            return;
        }

        foreach ( $po_data_current['entries'] as $entry ) {
            // If it's a POT file, or if force_retranslate is true, or if translation is empty
            if ( strpos($po_file_info['filepath'], '.pot') !== false || $force_retranslate || empty( $entry['translation'] ) ) {
                $strings_to_translate_original[] = $entry['original'];
            }
        }
        
        if ( empty( $strings_to_translate_original ) ) {
             // If no strings need translation, return the current PO data
             wp_send_json_success( array( 'message' => 'تمام رشته‌ها از قبل ترجمه شده‌اند یا نیازی به بازترجمه نیست.', 'translated_data' => $po_data_current ) );
             return;
        }

        // فراخوانی سرویس ترجمه
        $translated_strings = $this->api_client->translate_texts( $strings_to_translate_original, $target_lang, $translator_type, $api_keys );

        if ( is_wp_error( $translated_strings ) ) {
            wp_send_json_error( array( 'message' => $translated_strings->get_error_message() ) );
            return;
        }

        // به‌روزرسانی $po_data_current با ترجمه‌های جدید
        $updated_po_data = $this->file_handler->update_po_data_with_translations( $po_data_current, $strings_to_translate_original, $translated_strings );

        // Add proper headers for the new PO file based on target language
        $updated_po_data['headers']['Language'] = $target_lang;
        $updated_po_data['headers']['MIME-Version'] = '1.0';
        $updated_po_data['headers']['Content-Type'] = 'text/plain; charset=UTF-8';
        $updated_po_data['headers']['Content-Transfer-Encoding'] = '8bit';
        $updated_po_data['headers']['X-Generator'] = 'AI Translator WordPress Plugin';
        // Plural-Forms should be dynamic based on target_lang for robustness
        $updated_po_data['headers']['Plural-Forms'] = 'nplurals=2; plural=(n != 1);'; // Default, needs to be dynamic per language


        wp_send_json_success( array( 'message' => 'ترجمه با موفقیت انجام شد.', 'translated_data' => $updated_po_data ) );
    }

    /**
     * تابع AJAX برای ذخیره ترجمه (پس از ویرایش دستی یا ترجمه خودکار نهایی).
     * اکنون `po_data` یک آرایه کامل از entries شامل تغییرات ترجمه شده است.
     */
    public function save_translation_ajax() {
        error_reporting(E_ALL); // برای دیباگ فعال کنید
        @ini_set('display_errors', 1); // برای دیباگ فعال کنید

        error_log('AI Translator: save_translation_ajax called.');

        check_ajax_referer( 'ai_translator_nonce', 'nonce' );

        $item_id          = sanitize_text_field( $_POST['item_id'] ?? '' );
        $target_lang      = sanitize_text_field( $_POST['target_lang'] ?? '' );
        $po_data_json     = wp_unslash( $_POST['po_data'] ?? '{}' );
        $po_data_array    = json_decode( $po_data_json, true ); // Decode full PO data sent from React

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $po_data_array ) || ! isset( $po_data_array['entries'] ) ) {
            wp_send_json_error( array( 'message' => 'خطا در دریافت داده‌های ترجمه یا ساختار نامعتبر PO.' ) );
            return;
        }
        if ( empty( $item_id ) || empty( $target_lang ) ) {
            wp_send_json_error( array( 'message' => 'شناسه آیتم یا زبان مقصد نامعتبر است.' ) );
            return;
        }

        // پیدا کردن مسیرهای فایل .po و .mo مقصد
        $item_name = '';
        $po_output_path = '';
        $mo_output_path = '';

        $translatable_files = $this->file_handler->find_translatable_files();
        $po_file_info = null;
        foreach ($translatable_files as $file_info) {
            if ($file_info['id'] === $item_id) {
                $po_file_info = $file_info;
                $item_name = $file_info['name'];
                $text_domain = $file_info['text_domain'];
                
                // Determine output path based on item type and text domain
                $base_filename = $text_domain . '-' . str_replace('_', '-', $target_lang);
                if ($file_info['type'] === 'theme' || $file_info['type'] === 'global_theme') {
                    // For themes, prefer the global /wp-content/languages/themes/ directory
                    $po_output_path = WP_LANG_DIR . '/themes/' . $base_filename . '.po';
                    $mo_output_path = WP_LANG_DIR . '/themes/' . $base_filename . '.mo';
                } else if ($file_info['type'] === 'plugin' || $file_info['type'] === 'global_plugin') {
                    // For plugins, prefer the global /wp-content/languages/plugins/ directory
                    $po_output_path = WP_LANG_DIR . '/plugins/' . $base_filename . '.po';
                    $mo_output_path = WP_LANG_DIR . '/plugins/' . $base_filename . '.mo';
                } else { // Fallback for unknown types or specific cases
                    $po_output_path = WP_LANG_DIR . '/' . $base_filename . '.po';
                    $mo_output_path = WP_LANG_DIR . '/' . $base_filename . '.mo';
                }
                break;
            }
        }

        if (empty($po_output_path) || is_null($po_file_info)) {
            wp_send_json_error( array( 'message' => 'مسیر ذخیره فایل ترجمه یافت نشد یا آیتم نامعتبر است.' ) );
            return;
        }

        // اگر فایل اصلی از نوع .pot بود، فقط یک فایل .po/.mo جدید با توجه به text_domain ایجاد کنید
        // در غیر این صورت، فایل موجود را رونویسی کنید.
        if ( strpos($po_file_info['filepath'], '.pot') !== false) {
             // If source is a POT, we are creating a new translation file.
             // Ensure po_data_array has the correct headers for the new language.
             $po_data_array['headers']['Language'] = $target_lang;
             $po_data_array['headers']['PO-Revision-Date'] = date('Y-m-d H:iO');
             $po_data_array['headers']['Last-Translator'] = get_bloginfo('name') . ' <' . get_bloginfo('admin_email') . '>';
             $po_data_array['headers']['X-Generator'] = 'AI Translator WordPress Plugin';
             // For a new translation, Plural-Forms should be set based on target_lang's plural rules.
             // For simplicity, we use a common one, but a robust solution would fetch this dynamically.
             $po_data_array['headers']['Plural-Forms'] = 'nplurals=2; plural=(n != 1);';

        } else {
            // If source is an existing PO, update its revision date.
            $po_data_array['headers']['PO-Revision-Date'] = date('Y-m-d H:iO');
        }


        // قبل از ذخیره، پشتیبان‌گیری خودکار انجام شود (اگر تنظیمات فعال باشد)
        // پشتیبان‌گیری باید از فایل مقصد باشد، نه فایل سورس POT
        if ( $this->settings->get_setting( 'backup_before_replace' ) ) {
            $backup_result = $this->backup->create_backup( $item_id, $po_output_path, $mo_output_path );
            if ( is_wp_error( $backup_result ) ) {
                error_log('AI Translator Backup Error: ' . $backup_result->get_error_message());
            } else {
                // ثبت پشتیبان‌گیری در تاریخچه
                $this->database->add_history_entry( $item_id, $item_name, implode(', ', $backup_result), 'backup', 'فایل ترجمه پشتیبان‌گیری شد.' );
            }
        }

        // ذخیره فایل .po جدید
        $po_saved = $this->file_handler->write_po_file( $po_output_path, $po_data_array );

        // کامپایل و ذخیره فایل .mo
        $mo_saved = $this->file_handler->compile_mo_file( $po_output_path, $mo_output_path );

        if ( $po_saved && $mo_saved ) {
            // ثبت در تاریخچه (History)
            $this->database->add_history_entry( $item_id, $item_name, $po_output_path, 'saved', 'فایل ترجمه با موفقیت ذخیره و کامپایل شد.' );
            // Update total translations and strings translated stats
            $this->database->update_stat('total_translations', 1); // Increment count of translated items
            $this->database->update_stat('total_strings_translated', count($po_data_array['entries'])); // Add number of strings
            wp_send_json_success( array( 'message' => 'فایل ترجمه با موفقیت ذخیره شد!' ) );
        } else {
            wp_send_json_error( array( 'message' => 'خطا در ذخیره فایل ترجمه یا کامپایل فایل MO.' ) );
        }
    }

    /**
     * تابع AJAX برای دریافت تنظیمات پلاگین.
     */
    public function get_settings_ajax() {
        error_reporting(E_ALL); // برای دیباگ فعال کنید
        @ini_set('display_errors', 1); // برای دیباگ فعال کنید

        error_log('AI Translator: get_settings_ajax called.');

        check_ajax_referer( 'ai_translator_nonce', 'nonce' );
        $settings = $this->settings->get_settings();
        wp_send_json_success( $settings );
    }

    /**
     * تابع AJAX برای ذخیره تنظیمات پلاگین.
     */
    public function save_settings_ajax() {
        error_reporting(E_ALL); // برای دیباگ فعال کنید
        @ini_set('display_errors', 1); // برای دیباگ فعال کنید

        error_log('AI Translator: save_settings_ajax called.');

        check_ajax_referer( 'ai_translator_nonce', 'nonce' );
        $new_settings_raw = wp_unslash( $_POST['settings'] ?? '{}' );
        $new_settings = json_decode( $new_settings_raw, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( array( 'message' => 'خطا در دریافت داده‌های تنظیمات: ' . json_last_error_msg() ) );
            return;
        }

        $saved = $this->settings->save_settings( $new_settings );
        if ($saved) {
            wp_send_json_success( array( 'message' => 'تنظیمات با موفقیت ذخیره شد!' ) );
        } else {
            wp_send_json_error( array( 'message' => 'خطا در ذخیره تنظیمات.' ) );
        }
    }

    /**
     * تابع AJAX برای دریافت آمار و گزارشات.
     */
    public function get_stats_ajax() {
        error_reporting(E_ALL); // برای دیباگ فعال کنید
        @ini_set('display_errors', 1); // برای دیباگ فعال کنید

        error_log('AI Translator: get_stats_ajax called.');

        check_ajax_referer( 'ai_translator_nonce', 'nonce' );
        $stats = $this->database->get_stats(); // Assuming this method exists and fetches real data
        wp_send_json_success( $stats );
    }

    /**
     * تابع AJAX برای دریافت سابقه تغییرات.
     */
    public function get_history_ajax() {
        error_reporting(E_ALL); // برای دیباگ فعال کنید
        @ini_set('display_errors', 1); // برای دیباگ فعال کنید

        error_log('AI Translator: get_history_ajax called.');

        check_ajax_referer( 'ai_translator_nonce', 'nonce' );
        $history = $this->database->get_history(); // Assuming this method exists and fetches real data
        wp_send_json_success( $history );
    }

    /**
     * New AJAX function: Rewrites text using Gemini API.
     */
    public function rewrite_text_gemini_ajax() {
        error_reporting(E_ALL); // برای دیباگ فعال کنید
        @ini_set('display_errors', 1); // برای دیباگ فعال کنید

        error_log('AI Translator: rewrite_text_gemini_ajax called.');

        check_ajax_referer( 'ai_translator_nonce', 'nonce' );

        $text        = sanitize_textarea_field( $_POST['text'] ?? '' );
        $instruction = sanitize_text_field( $_POST['instruction'] ?? 'Make it more concise and formal.' );

        if ( empty( $text ) ) {
            wp_send_json_error( array( 'message' => 'متنی برای بازنویسی وارد نشده است.' ) );
            return;
        }

        $plugin_settings = $this->settings->get_settings();
        $gemini_api_key = $plugin_settings['gemini_api_key'] ?? '';

        if ( empty( $gemini_api_key ) ) {
            wp_send_json_error( array( 'message' => 'API Key جیمینای برای بازنویسی وارد نشده است.' ) );
            return;
        }

        $rewritten_text = $this->api_client->rewrite_with_gemini( $text, $instruction, $gemini_api_key );

        if ( is_wp_error( $rewritten_text ) ) {
            wp_send_json_error( array( 'message' => $rewritten_text->get_error_message() ) );
            return;
        }

        wp_send_json_success( array( 'rewritten_text' => $rewritten_text ) );
    }

}
