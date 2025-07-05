<?php
// includes/class-ai-translator-settings.php

// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * کلاسی برای مدیریت تنظیمات پلاگین.
 * تنظیمات را در دیتابیس وردپرس ذخیره و بازیابی می‌کند.
 */
class AI_Translator_Settings {

    private $option_name;

    public function __construct() {
        $this->option_name = 'ai_translator_settings';
    }

    /**
     * تنظیمات پیش‌فرض پلاگین را دریافت می‌کند.
     * @return array آرایه‌ای از تنظیمات پیش‌فرض.
     */
    private function get_default_settings() {
        return array(
            'default_translator'        => 'libretranslate', // تغییر پیش‌فرض به 'libretranslate'
            'chatgpt_api_key'           => '',
            'google_translate_api_key'  => '',
            'grok_api_key'              => '',
            'gemini_api_key'            => '',
            'libretranslate_endpoint'   => 'https://translate.argosopentech.com/translate', // Example, should be configurable if self-hosted
            'laratranslate_endpoint'    => '', // Placeholder, needs actual endpoint if implemented
            'enable_cache'              => true,
            'backup_before_replace'     => true,
            // می‌توانید تنظیمات دیگر مانند کیفیت ترجمه، لیست سیاه کلمات و ... را اضافه کنید.
        );
    }

    /**
     * تمام تنظیمات ذخیره شده را دریافت می‌کند.
     * اگر تنظیمات قبلاً ذخیره نشده باشند، تنظیمات پیش‌فرض را برمی‌گرداند.
     * @return array آرایه‌ای از تنظیمات فعلی پلاگین.
     */
    public function get_settings() {
        $saved_settings = get_option( $this->option_name, array() );
        return array_merge( $this->get_default_settings(), $saved_settings );
    }

    /**
     * یک تنظیم خاص را دریافت می‌کند.
     * @param string $key کلید تنظیم.
     * @return mixed|null مقدار تنظیم یا null اگر یافت نشد.
     */
    public function get_setting( $key ) {
        $settings = $this->get_settings();
        return $settings[$key] ?? null;
    }

    /**
     * تنظیمات پلاگین را ذخیره می‌کند.
     * @param array $new_settings آرایه‌ای از تنظیمات جدید برای ذخیره.
     * @return bool True در صورت موفقیت، False در صورت شکست.
     */
    public function save_settings( array $new_settings ) {
        // اطمینان از پاکسازی و اعتبارسنجی ورودی‌ها قبل از ذخیره
        $current_settings = $this->get_settings(); // Get current settings to merge/validate against
        $merged_settings = array_merge($current_settings, $new_settings);

        // sanitize inputs
        $merged_settings['default_translator']       = sanitize_text_field( $merged_settings['default_translator'] );
        $merged_settings['chatgpt_api_key']          = sanitize_text_field( $merged_settings['chatgpt_api_key'] );
        $merged_settings['google_translate_api_key'] = sanitize_text_field( $merged_settings['google_translate_api_key'] );
        $merged_settings['grok_api_key']             = sanitize_text_field( $merged_settings['grok_api_key'] );
        $merged_settings['gemini_api_key']           = sanitize_text_field( $merged_settings['gemini_api_key'] );
        $merged_settings['libretranslate_endpoint']  = esc_url_raw( $merged_settings['libretranslate_endpoint'] );
        $merged_settings['laratranslate_endpoint']   = esc_url_raw( $merged_settings['laratranslate_endpoint'] );
        $merged_settings['enable_cache']             = (bool) $merged_settings['enable_cache'];
        $merged_settings['backup_before_replace']    = (bool) $merged_settings['backup_before_replace'];
        
        return update_option( $this->option_name, $merged_settings );
    }
}
