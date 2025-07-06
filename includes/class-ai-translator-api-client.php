<?php
// includes/class-ai-translator-api-client.php

// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * کلاسی برای مدیریت ارتباط با APIهای مترجم‌های مختلف.
 */
class AI_Translator_API_Client {

    private $settings;

    public function __construct( $settings ) {
        $this->settings = $settings;
    }

    /**
     * متنی را با استفاده از مترجم پیش‌فرض یا مشخص شده ترجمه می‌کند.
     *
     * @param array  $texts           آرایه‌ای از متن‌ها برای ترجمه.
     * @param string $target_lang     کد زبان مقصد (مثلاً 'fa_IR').
     * @param string $translator_type نوع مترجم (مثلاً 'gemini', 'libretranslate', 'chatgpt', 'google', 'grok', 'laratranslate').
     * @param array  $api_keys        آرایه‌ای شامل کلیدهای API برای مترجم‌های مختلف.
     * @return array|WP_Error آرایه‌ای از متن‌های ترجمه شده یا WP_Error در صورت خطا.
     */
    public function translate_texts( $texts, $target_lang, $translator_type, $api_keys ) {
        $translated_texts = [];

        switch ( $translator_type ) {
            case 'gemini':
                $api_key = $api_keys['gemini'] ?? '';
                if ( empty( $api_key ) ) {
                    return new WP_Error( 'api_key_missing', 'برای مترجم Gemini نیاز به API Key است.' );
                }
                foreach ( $texts as $text ) {
                    $result = $this->translate_with_gemini( $text, $target_lang, $api_key );
                    if ( is_wp_error( $result ) ) {
                        return $result; // بازگرداندن اولین خطا
                    }
                    $translated_texts[] = $result;
                }
                break;

            case 'libretranslate':
                $libretranslate_endpoint = $this->settings->get_setting('libretranslate_endpoint');
                if (empty($libretranslate_endpoint)) {
                    return new WP_Error('endpoint_missing', 'LibreTranslate endpoint تنظیم نشده است.');
                }
                foreach ( $texts as $text ) {
                    $result = $this->translate_with_libretranslate( $text, $target_lang, $libretranslate_endpoint );
                    if ( is_wp_error( $result ) ) {
                        return $result; // بازگرداندن اولین خطا
                    }
                    $translated_texts[] = $result;
                }
                break;

            case 'laratranslate':
                $laratranslate_endpoint = $this->settings->get_setting('laratranslate_endpoint');
                if (empty($laratranslate_endpoint)) {
                    return new WP_Error('endpoint_missing', 'LaraTranslate endpoint تنظیم نشده است.');
                }
                foreach ( $texts as $text ) {
                    $result = $this->translate_with_laratranslate( $text, $target_lang, $laratranslate_endpoint );
                    if ( is_wp_error( $result ) ) {
                        return $result; // بازگرداندن اولین خطا
                    }
                    $translated_texts[] = $result;
                }
                break;
            
            case 'chatgpt':
                $api_key = $api_keys['chatgpt'] ?? '';
                if ( empty( $api_key ) ) {
                    return new WP_Error( 'api_key_missing', 'برای مترجم ChatGPT نیاز به API Key است.' );
                }
                foreach ( $texts as $text ) {
                    $result = $this->translate_with_chatgpt( $text, $target_lang, $api_key );
                    if ( is_wp_error( $result ) ) {
                        return $result;
                    }
                    $translated_texts[] = $result;
                }
                break;

            case 'google':
                $api_key = $api_keys['google'] ?? '';
                if ( empty( $api_key ) ) {
                    return new WP_Error( 'api_key_missing', 'برای مترجم Google Translate نیاز به API Key است.' );
                }
                foreach ( $texts as $text ) {
                    $result = $this->translate_with_google_translate( $text, $target_lang, $api_key );
                    if ( is_wp_error( $result ) ) {
                        return $result;
                    }
                    $translated_texts[] = $result;
                }
                break;

            case 'grok':
                $api_key = $api_keys['grok'] ?? '';
                if ( empty( $api_key ) ) {
                    return new WP_Error( 'api_key_missing', 'برای مترجم Grok نیاز به API Key است.' );
                }
                foreach ( $texts as $text ) {
                    $result = $this->translate_with_grok( $text, $target_lang, $api_key );
                    if ( is_wp_error( $result ) ) {
                        return $result;
                    }
                    $translated_texts[] = $result;
                }
                break;

            default:
                return new WP_Error( 'invalid_translator', 'نوع مترجم نامعتبر است: ' . $translator_type );
        }

        return $translated_texts;
    }

    /**
     * ترجمه متن با استفاده از API گمنه‌ی (Gemini).
     *
     * @param string $text        متن اصلی برای ترجمه.
     * @param string $target_lang کد زبان مقصد.
     * @param string $api_key     کلید API گمنه‌ی.
     * @return string|WP_Error متن ترجمه شده یا WP_Error در صورت خطا.
     */
    private function translate_with_gemini( $text, $target_lang, $api_key ) {
        // برای Gemini، فرض می‌کنیم که زبان مقصد با خط تیره (مثلاً fa-IR) باشد
        $target_lang_gemini = str_replace('_', '-', $target_lang);

        $prompt = "Translate the following text to $target_lang_gemini:\n\n" . $text;

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topP'        => 0.95,
                'topK'        => 40,
                'maxOutputTokens' => 1024,
            ],
        ];

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $api_key;

        $response = wp_remote_post( $url, [
            'headers'     => $headers,
            'body'        => json_encode( $payload ),
            'method'      => 'POST',
            'timeout'     => 45, // Set a reasonable timeout
            'sslverify'   => false, // ONLY FOR TESTING/LOCAL, remove in production if possible
        ]);

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'gemini_api_error', 'خطا در ارتباط با API گمنه‌ی: ' . $response->get_error_message() );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'gemini_response_parse_error', 'پاسخ نامعتبر از API گمنه‌ی: ' . json_last_error_msg() . ' (Raw: ' . $body . ')' );
        }

        if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            // Success: extract translated text
            return $data['candidates'][0]['content']['parts'][0]['text'];
        } elseif ( isset( $data['error']['message'] ) ) {
            // Error from API
            return new WP_Error( 'gemini_api_error', 'خطای API گمنه‌ی: ' . $data['error']['message'] );
        } else {
            // Unexpected response structure
            return new WP_Error( 'gemini_api_error', 'پاسخ ناشناخته از API گمنه‌ی.' );
        }
    }

    /**
     * بازنویسی متن با استفاده از API گمنه‌ی.
     *
     * @param string $text        متن اصلی برای بازنویسی.
     * @param string $instruction دستورالعمل برای بازنویسی.
     * @param string $api_key     کلید API گمنه‌ی.
     * @return string|WP_Error متن بازنویسی شده یا WP_Error در صورت خطا.
     */
    public function rewrite_with_gemini( $text, $instruction, $api_key ) {
        $prompt = "بازنویسی کن: " . $text . "\n\nدستورالعمل: " . $instruction;

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topP'        => 0.95,
                'topK'        => 40,
                'maxOutputTokens' => 1024,
            ],
        ];

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $api_key;

        $response = wp_remote_post( $url, [
            'headers'     => $headers,
            'body'        => json_encode( $payload ),
            'method'      => 'POST',
            'timeout'     => 45, // Set a reasonable timeout
            'sslverify'   => false, // ONLY FOR TESTING/LOCAL, remove in production if possible
        ]);

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'gemini_rewrite_api_error', 'خطا در ارتباط با API گمنه‌ی برای بازنویسی: ' . $response->get_error_message() );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'gemini_rewrite_response_parse_error', 'پاسخ نامعتبر از API گمنه‌ی برای بازنویسی: ' . json_last_error_msg() . ' (Raw: ' . $body . ')' );
        }

        if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        } elseif ( isset( $data['error']['message'] ) ) {
            return new WP_Error( 'gemini_rewrite_api_error', 'خطای API گمنه‌ی در بازنویسی: ' . $data['error']['message'] );
        } else {
            return new WP_Error( 'gemini_rewrite_api_error', 'پاسخ ناشناخته از API گمنه‌ی در بازنویسی.' );
        }
    }

    /**
     * ترجمه متن با استفاده از API LibreTranslate.
     *
     * @param string $text                 متن اصلی.
     * @param string $target_lang          کد زبان مقصد.
     * @param string $libretranslate_endpoint URL نقطه پایانی LibreTranslate.
     * @return string|WP_Error متن ترجمه شده یا WP_Error.
     */
    private function translate_with_libretranslate( $text, $target_lang, $libretranslate_endpoint ) {
        // LibreTranslate از کدهای زبان ISO 639-1 (مثلاً fa, en) استفاده می‌کند.
        // WP locales (fa_IR) را به کدهای ISO 639-1 تبدیل می‌کنیم.
        $source_lang_iso = 'auto'; // Detect source language automatically
        $target_lang_iso = substr($target_lang, 0, 2); // 'fa_IR' -> 'fa'

        $body = [
            'q'      => $text,
            'source' => $source_lang_iso,
            'target' => $target_lang_iso,
            // 'format' => 'text', // Default is text
            // 'api_key' => 'YOUR_API_KEY_IF_NEEDED' // If you run your own LibreTranslate with API key
        ];

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $response = wp_remote_post( $libretranslate_endpoint, [
            'headers'     => $headers,
            'body'        => json_encode( $body ),
            'method'      => 'POST',
            'timeout'     => 60, // Increase timeout for external APIs
            'sslverify'   => false, // ONLY FOR TESTING/LOCAL, remove in production if possible if you face issues
        ]);

        if ( is_wp_error( $response ) ) {
            error_log('LibreTranslate API Error: ' . $response->get_error_message());
            return new WP_Error( 'libretranslate_api_error', 'خطا در ارتباط با LibreTranslate: ' . $response->get_error_message() );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            error_log('LibreTranslate Response Parse Error: ' . json_last_error_msg() . ' (Raw: ' . $response_body . ')');
            return new WP_Error( 'libretranslate_response_parse_error', 'پاسخ نامعتبر از LibreTranslate: ' . json_last_error_msg() );
        }

        if ( $response_code === 200 && isset( $data['translatedText'] ) ) {
            return $data['translatedText'];
        } elseif ( isset( $data['error'] ) && !empty($data['error']) ) {
            error_log('LibreTranslate API Error Response: ' . $data['error']);
            return new WP_Error( 'libretranslate_api_error', 'خطای LibreTranslate: ' . $data['error'] );
        } else {
            error_log('LibreTranslate Unexpected Response: ' . $response_body);
            return new WP_Error( 'libretranslate_api_error', 'پاسخ ناشناخته از LibreTranslate.' );
        }
    }

    /**
     * ترجمه متن با استفاده از API LaraTranslate.
     *
     * @param string $text                 متن اصلی.
     * @param string $target_lang          کد زبان مقصد.
     * @param string $laratranslate_endpoint URL نقطه پایانی LaraTranslate.
     * @return string|WP_Error متن ترجمه شده یا WP_Error.
     */
    private function translate_with_laratranslate( $text, $target_lang, $laratranslate_endpoint ) {
        // LaraTranslate ممکن است از فرمت‌های زبان متفاوتی استفاده کند.
        // فرض می‌کنیم از 'fa_IR' به 'fa' تبدیل می‌کند یا همان فرمت WP را می‌پذیرد.
        $source_lang_iso = 'en'; // Assuming source is English POT/PO
        $target_lang_laratranslate = substr($target_lang, 0, 2); // 'fa_IR' -> 'fa'

        $body = [
            'text'     => $text,
            'source'   => $source_lang_iso,
            'target'   => $target_lang_laratranslate,
            // 'api_key' => 'YOUR_LARATRANSLATE_API_KEY_IF_NEEDED'
        ];

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $response = wp_remote_post( $laratranslate_endpoint, [
            'headers'     => $headers,
            'body'        => json_encode( $body ),
            'method'      => 'POST',
            'timeout'     => 60, // Increase timeout for external APIs
            'sslverify'   => false, // ONLY FOR TESTING/LOCAL, remove in production if possible
        ]);

        if ( is_wp_error( $response ) ) {
            error_log('LaraTranslate API Error: ' . $response->get_error_message());
            return new WP_Error( 'laratranslate_api_error', 'خطا در ارتباط با LaraTranslate: ' . $response->get_error_message() );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            error_log('LaraTranslate Response Parse Error: ' . json_last_error_msg() . ' (Raw: ' . $response_body . ')');
            return new WP_Error( 'laratranslate_response_parse_error', 'پاسخ نامعتبر از LaraTranslate: ' . json_last_error_msg() );
        }

        if ( $response_code === 200 && isset( $data['translation'] ) ) {
            return $data['translation'];
        } elseif ( isset( $data['message'] ) ) { // LaraTranslate often uses 'message' for errors
            error_log('LaraTranslate API Error Response: ' . $data['message']);
            return new WP_Error( 'laratranslate_api_error', 'خطای LaraTranslate: ' . $data['message'] );
        } else {
            error_log('LaraTranslate Unexpected Response: ' . $response_body);
            return new WP_Error( 'laratranslate_api_error', 'پاسخ ناشناخته از LaraTranslate.' );
        }
    }

    /**
     * ترجمه متن با استفاده از API ChatGPT (OpenAI).
     *
     * @param string $text        متن اصلی.
     * @param string $target_lang کد زبان مقصد (مثلاً fa_IR).
     * @param string $api_key     کلید API.
     * @return string|WP_Error متن ترجمه شده یا WP_Error.
     */
    private function translate_with_chatgpt( $text, $target_lang, $api_key ) {
        $target_lang_name = get_language_name_from_locale($target_lang); // Helper function to get readable name

        $headers = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ];

        $body = [
            'model'    => 'gpt-3.5-turbo', // Or gpt-4, depending on preference/access
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => "You are a highly accurate translation assistant. Translate the following text into " . $target_lang_name . ".",
                ],
                [
                    'role'    => 'user',
                    'content' => $text,
                ],
            ],
            'temperature' => 0.7,
            'max_tokens'  => 1000,
        ];

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'headers'     => $headers,
            'body'        => json_encode( $body ),
            'method'      => 'POST',
            'timeout'     => 60,
            'sslverify'   => false, // ONLY FOR TESTING/LOCAL, remove in production
        ]);

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'chatgpt_api_error', 'خطا در ارتباط با API چت‌جی‌پی‌تی: ' . $response->get_error_message() );
        }

        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'chatgpt_response_parse_error', 'پاسخ نامعتبر از API چت‌جی‌پی‌تی: ' . json_last_error_msg() . ' (Raw: ' . $response_body . ')' );
        }

        if ( isset( $data['choices'][0]['message']['content'] ) ) {
            return trim( $data['choices'][0]['message']['content'] );
        } elseif ( isset( $data['error']['message'] ) ) {
            return new WP_Error( 'chatgpt_api_error', 'خطای API چت‌جی‌پی‌تی: ' . $data['error']['message'] );
        } else {
            return new WP_Error( 'chatgpt_api_error', 'پاسخ ناشناخته از API چت‌جی‌پی‌تی.' );
        }
    }

    /**
     * ترجمه متن با استفاده از API Google Translate.
     * (نیاز به فعال‌سازی Cloud Translation API در کنسول گوگل کلود).
     *
     * @param string $text        متن اصلی.
     * @param string $target_lang کد زبان مقصد.
     * @param string $api_key     کلید API.
     * @return string|WP_Error متن ترجمه شده یا WP_Error.
     */
    private function translate_with_google_translate( $text, $target_lang, $api_key ) {
        // Google Translate API از کدهای زبان BCP-47 (مثلاً 'fa', 'en') استفاده می‌کند.
        $target_lang_google = substr($target_lang, 0, 2); // 'fa_IR' -> 'fa'

        $url = 'https://translation.googleapis.com/language/translate/v2?key=' . $api_key;

        $body = [
            'q'      => $text,
            'target' => $target_lang_google,
            'format' => 'text',
        ];

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $response = wp_remote_post( $url, [
            'headers'     => $headers,
            'body'        => json_encode( $body ),
            'method'      => 'POST',
            'timeout'     => 45,
            'sslverify'   => false, // ONLY FOR TESTING/LOCAL, remove in production
        ]);

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'google_translate_api_error', 'خطا در ارتباط با Google Translate API: ' . $response->get_error_message() );
        }

        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'google_translate_response_parse_error', 'پاسخ نامعتبر از Google Translate API: ' . json_last_error_msg() . ' (Raw: ' . $response_body . ')' );
        }

        if ( isset( $data['data']['translations'][0]['translatedText'] ) ) {
            return $data['data']['translations'][0]['translatedText'];
        } elseif ( isset( $data['error']['message'] ) ) {
            return new WP_Error( 'google_translate_api_error', 'خطای Google Translate API: ' . $data['error']['message'] );
        } else {
            return new WP_Error( 'google_translate_api_error', 'پاسخ ناشناخته از Google Translate API.' );
        }
    }

    /**
     * ترجمه متن با استفاده از API Grok (XAI).
     * (این یک پیاده‌سازی فرضی است، زیرا API Grok ممکن است عمومی نباشد یا مستندات آن متفاوت باشد).
     *
     * @param string $text        متن اصلی.
     * @param string $target_lang کد زبان مقصد.
     * @param string $api_key     کلید API.
     * @return string|WP_Error متن ترجمه شده یا WP_Error.
     */
    private function translate_with_grok( $text, $target_lang, $api_key ) {
        // Grok API (XAI) - این یک مثال فرضی است، زیرا API عمومی آن در دسترس نیست.
        // شما باید مستندات API Grok را برای نقطه پایانی و فرمت درخواست/پاسخ صحیح بررسی کنید.
        $grok_endpoint = 'https://api.xai.com/v1/translate'; // Endpoint فرضی

        $headers = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ];

        $body = [
            'model'    => 'grok-translate-v1', // مدل فرضی
            'prompt'   => "Translate this text to $target_lang: " . $text,
            'temperature' => 0.7,
            'max_tokens'  => 1000,
        ];

        $response = wp_remote_post( $grok_endpoint, [
            'headers'     => $headers,
            'body'        => json_encode( $body ),
            'method'      => 'POST',
            'timeout'     => 60,
            'sslverify'   => false, // ONLY FOR TESTING/LOCAL, remove in production
        ]);

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'grok_api_error', 'خطا در ارتباط با Grok API: ' . $response->get_error_message() );
        }

        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'grok_response_parse_error', 'پاسخ نامعتبر از Grok API: ' . json_last_error_msg() . ' (Raw: ' . $response_body . ')' );
        }

        // فرض می‌کنیم پاسخ مشابه OpenAI باشد
        if ( isset( $data['choices'][0]['text'] ) ) { // یا 'choices'][0]['message']['content']
            return trim( $data['choices'][0]['text'] );
        } elseif ( isset( $data['error']['message'] ) ) {
            return new WP_Error( 'grok_api_error', 'خطای Grok API: ' . $data['error']['message'] );
        } else {
            return new WP_Error( 'grok_api_error', 'پاسخ ناشناخته از Grok API.' );
        }
    }
}

// Helper function (outside class) to get human-readable language name
if (!function_exists('get_language_name_from_locale')) {
    function get_language_name_from_locale($locale) {
        $lang_map = [
            'fa_IR' => 'Persian (Iran)',
            'en_US' => 'English (United States)',
            'es_ES' => 'Spanish (Spain)',
            'fr_FR' => 'French (France)',
            'de_DE' => 'German (Germany)',
            'ar'    => 'Arabic',
            'tr'    => 'Turkish',
            'zh'    => 'Chinese',
            'ru'    => 'Russian',
            // Add more as needed
        ];
        return $lang_map[$locale] ?? $locale;
    }
}

