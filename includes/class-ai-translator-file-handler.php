<?php
// includes/class-ai-translator-file-handler.php

// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// استفاده از کلاس‌های کتابخانه Gettext (بعد از نصب Composer)
// از کلاس‌های خاص‌تر برای سازگاری بیشتر استفاده می‌کنیم.
use Gettext\Translations;
use Gettext\PO\Parser as PoParser; // استفاده از PO Parser
use Gettext\MO\Generator as MoGenerator; // استفاده از MO Generator
use Gettext\Translation;

/**
 * کلاسی برای خواندن، نوشتن و مدیریت فایل‌های .po و .mo.
 * از کتابخانه `php-gettext` (که با Composer نصب می‌شود) استفاده می‌کند.
 */
class AI_Translator_File_Handler {

    public function __construct() {
        // If the Gettext library is not installed via Composer, an exception will be thrown
        // which is handled by `ai-translator.php`.
        // So, no need to check class_exists here.
    }

    /**
     * تمام فایل‌های .po و .pot را در قالب‌ها و افزونه‌ها پیدا می‌کند.
     * WordPress core language files are *excluded* by default from this scan,
     * as they are generally not meant to be directly translated by a plugin.
     * Global theme/plugin language files (in wp-content/languages/) are included.
     *
     * @return array لیستی از فایل‌های قابل ترجمه (مسیر و اطلاعات مربوطه).
     */
    public function find_translatable_files() {
        $files = array();

        // Get all installed themes (active and inactive)
        $installed_themes_slugs = array_keys(wp_get_themes());

        // Get all installed plugins (active and inactive)
        $installed_plugins_data = get_plugins();
        $installed_plugin_slugs = [];
        foreach ($installed_plugins_data as $plugin_file => $data) {
            $slug = dirname($plugin_file);
            if ( '.' === $slug ) { // Handle root plugins (e.g., hello.php)
                $slug = basename($plugin_file, '.php');
            }
            $installed_plugin_slugs[] = $slug;
        }
        $installed_plugin_slugs = array_unique($installed_plugin_slugs);


        // ---- اسکان قالب‌ها ----
        $themes = wp_get_themes(); // Get all theme data again for properties
        foreach ( $themes as $stylesheet => $theme ) {
            $theme_path = get_theme_root() . '/' . $stylesheet;
            $lang_dir = $theme_path . '/languages/';
            $text_domain = $theme->get( 'TextDomain' ) ? $theme->get( 'TextDomain' ) : $stylesheet;

            // Add POT file from theme root (if it exists)
            $root_pot = $theme_path . '/' . $text_domain . '.pot';
            $exists_on_disk = file_exists( $root_pot ) && is_readable($root_pot);
            $files[] = array(
                'id'          => 'theme_' . $stylesheet . '_pot_' . md5($root_pot),
                'name'        => $theme->get( 'Name' ) . ' (POT)',
                'type'        => 'theme',
                'filepath'    => $root_pot,
                'filename'    => basename( $root_pot ),
                'text_domain' => $text_domain,
                'status'      => 'needs_translation',
                'locale'      => 'en_US', // POTs are usually in English
                'exists'      => $exists_on_disk,
            );

            // Add PO/MO files from theme languages directory
            if ( is_dir( $lang_dir ) ) {
                $po_files = glob( $lang_dir . '*.po' );
                $pot_files_in_lang_dir = glob( $lang_dir . '*.pot' ); // Some themes might place POTs here too
                foreach ( array_merge( $po_files, $pot_files_in_lang_dir ) as $file ) {
                    $exists_on_disk = file_exists($file) && is_readable($file);
                    $filename_base = basename($file, '.po');
                    $filename_base = basename($filename_base, '.pot');
                    $locale = substr($filename_base, strrpos($filename_base, '-') + 1);
                    if (empty($locale) || !preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $locale)) {
                        $locale = 'unknown'; // Fallback if locale cannot be parsed
                    }

                    $files[] = array(
                        'id'          => 'theme_' . $stylesheet . '_' . $locale . '_' . md5($file),
                        'name'        => $theme->get( 'Name' ),
                        'type'        => 'theme',
                        'filepath'    => $file,
                        'filename'    => basename( $file ),
                        'text_domain' => $text_domain,
                        'locale'      => $locale,
                        'status'      => (strpos($file, '.pot') !== false) ? 'needs_translation' : 'ترجمه شده',
                        'exists'      => $exists_on_disk,
                    );
                }
            }
        }

        // ---- اسکان افزونه‌ها ----
        foreach ( $installed_plugins_data as $plugin_file => $plugin_data ) {
            $plugin_slug = dirname( $plugin_file );
            if ( '.' === $plugin_slug ) {
                $plugin_slug = basename( $plugin_file, '.php' );
            }
            $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_slug;
            $lang_dir = $plugin_path . '/languages/';
            $text_domain = $plugin_data['TextDomain'] ? $plugin_data['TextDomain'] : $plugin_slug;

            // Add POT file from plugin root (if it exists)
            $root_pot = $plugin_path . '/' . $text_domain . '.pot';
            $exists_on_disk = file_exists( $root_pot ) && is_readable($root_pot);
            $files[] = array(
                'id'          => 'plugin_' . $plugin_slug . '_pot_' . md5($root_pot),
                'name'        => $plugin_data['Name'] . ' (POT)',
                'type'        => 'plugin',
                'filepath'    => $root_pot,
                'filename'    => basename( $root_pot ),
                'text_domain' => $text_domain,
                'status'      => 'needs_translation',
                'locale'      => 'en_US',
                'exists'      => $exists_on_disk,
            );

            // Add PO/MO files from plugin languages directory
            if ( is_dir( $lang_dir ) ) {
                $po_files = glob( $lang_dir . '*.po' );
                $pot_files_in_lang_dir = glob( $lang_dir . '*.pot' );
                foreach ( array_merge( $po_files, $pot_files_in_lang_dir ) as $file ) {
                    $exists_on_disk = file_exists($file) && is_readable($file);
                    $filename_base = basename($file, '.po');
                    $filename_base = basename($filename_base, '.pot');
                    $locale = substr($filename_base, strrpos($filename_base, '-') + 1);
                    if (empty($locale) || !preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $locale)) {
                        $locale = 'unknown'; // Fallback if locale cannot be parsed
                    }

                    $files[] = array(
                        'id'          => 'plugin_' . $plugin_slug . '_' . $locale . '_' . md5($file),
                        'name'        => $plugin_data['Name'],
                        'type'        => 'plugin',
                        'filepath'    => $file,
                        'filename'    => basename( $file ),
                        'text_domain' => $text_domain,
                        'locale'      => $locale,
                        'status'      => (strpos($file, '.pot') !== false) ? 'needs_translation' : 'ترجمه شده',
                        'exists'      => $exists_on_disk,
                    );
                }
            }
        }

        // ---- اسکان فایل‌های ترجمه گلوبال در WP_LANG_DIR/themes/ و WP_LANG_DIR/plugins/ ----
        // این فایل‌ها می‌توانند مربوط به قالب‌ها یا افزونه‌هایی باشند که فعال نیستند یا قبلاً حذف شده‌اند.
        // ما این فایل‌ها را پیدا می‌کنیم و وضعیت 'exists' آنها را بر اساس وجود قالب/افزونه اصلی بررسی می‌کنیم.
        
        $global_lang_dirs = [
            'theme'  => WP_LANG_DIR . '/themes/',
            'plugin' => WP_LANG_DIR . '/plugins/',
        ];

        foreach ($global_lang_dirs as $type_key => $dir) {
            if (is_dir($dir)) {
                $global_files = glob($dir . '*.po');
                $global_files = array_merge($global_files, glob($dir . '*.pot'));
                foreach ($global_files as $file) {
                    $exists_on_disk = file_exists($file) && is_readable($file);
                    if (!$exists_on_disk) { // If the file is not even on disk, skip it.
                        continue;
                    }
                    $filename_base = basename($file, '.po');
                    $filename_base = basename($filename_base, '.pot');
                    
                    // Attempt to extract text domain and locale
                    // Example: 'twentyfifteen-fa_IR' -> textdomain: 'twentyfifteen', locale: 'fa_IR'
                    $guessed_slug = '';
                    $locale = '';
                    if (preg_match('/^(.+)-([a-z]{2}(_[A-Z]{2})?)$/', $filename_base, $matches)) {
                        $guessed_slug = $matches[1];
                        $locale = $matches[2];
                    } else if (preg_match('/^([a-zA-Z0-9_-]+)\.po$/', $filename_base, $matches)) { // Fallback for files like 'fa_IR.po' (e.g., Akismet-fa_IR.po)
                        $guessed_slug = $matches[1];
                        $locale = 'unknown'; // Can't determine from filename alone
                    } else { // Fallback for general cases
                        $guessed_slug = $filename_base;
                        $locale = 'unknown';
                    }
                    
                    // Check if the associated theme/plugin still exists by its slug
                    $item_exists_on_system = false;
                    if ($type_key === 'theme') {
                        $item_exists_on_system = in_array($guessed_slug, $installed_themes_slugs);
                    } elseif ($type_key === 'plugin') {
                        $item_exists_on_system = in_array($guessed_slug, $installed_plugin_slugs);
                    }

                    $files[] = array(
                        'id'          => 'global_' . $type_key . '_' . $guessed_slug . '_' . $locale . '_' . md5($file),
                        'name'        => ($type_key === 'theme' ? 'قالب سراسری: ' : 'افزونه سراسری: ') . $guessed_slug,
                        'type'        => 'global_' . $type_key,
                        'filepath'    => $file,
                        'filename'    => basename($file),
                        'text_domain' => $guessed_slug,
                        'locale'      => $locale,
                        'status'      => (strpos($file, '.pot') !== false) ? 'needs_translation' : 'ترجمه شده',
                        'exists'      => $item_exists_on_system, // Mark as exists or not based on installation status
                    );
                }
            }
        }

        // WordPress core language files are *not* included in this scan by default.
        // If you need to access core language files, this logic would need to be re-added
        // and carefully handled to avoid issues with WordPress core updates.

        // Remove duplicates based on filepath, prioritizing 'exists' = true if duplicates share same path
        $unique_files = [];
        foreach ($files as $file) {
            $path = $file['filepath'];
            // If we already have this path and the current file's exists is false,
            // but the existing one is true, keep the existing one.
            // Otherwise, or if current is true, update it.
            if (!isset($unique_files[$path]) || ($file['exists'] && !$unique_files[$path]['exists'])) {
                $unique_files[$path] = $file;
            }
        }

        return array_values($unique_files); // Return re-indexed array
    }

    /**
     * Reads a .po file and returns its content as a processable array.
     * This function uses the `php-gettext` library.
     *
     * @param string $filepath Full path to the .po file.
     * @return array|WP_Error Array containing 'headers' and 'entries' or WP_Error.
     */
    public function read_po_file( $filepath ) {
        if ( ! file_exists( $filepath ) || ! is_readable( $filepath ) ) {
            return new WP_Error( 'file_not_found', 'فایل PO یافت نشد یا قابل خواندن نیست: ' . $filepath );
        }
        
        try {
            // Use PoParser for robust PO file reading
            $parser = new PoParser();
            $file_content = file_get_contents($filepath);
            if ($file_content === false) {
                return new WP_Error( 'file_read_error', 'خطا در خواندن محتوای فایل PO: ' . $filepath );
            }
            $translations = $parser->parseString($file_content);

            $entries_array = [];
            foreach ( $translations as $entry ) {
                $entries_array[] = [
                    'original'    => $entry->getOriginal(),
                    'translation' => $entry->getTranslation(),
                    'context'     => $entry->getContext(),
                    'plural'      => $entry->getPlural(),
                    'plural_translations' => $entry->getPluralTranslations(),
                    'references'  => $entry->getReferences(),
                    'comments'    => $entry->getComments(),
                ];
            }

            $headers = [];
            foreach ($translations->getHeaders() as $key => $value) {
                $headers[$key] = $value;
            }


            return [
                'headers' => $headers,
                'entries' => $entries_array,
            ];

        } catch ( Exception $e ) {
            error_log( 'AI Translator: Error reading PO file with Gettext Library: ' . $e->getMessage() . ' (File: ' . basename($filepath) . ')' );
            return new WP_Error( 'po_parse_error', 'خطا در تجزیه فایل PO: ' . $e->getMessage() . ' (فایل: ' . basename($filepath) . ')' );
        }
    }

    /**
     * Updates PO data with new translations.
     *
     * @param array $po_data             Array of PO data from read_po_file.
     * @param array $original_strings    Original strings sent for translation.
     * @param array $translated_strings  Corresponding translated strings.
     * @return array Updated PO data.
     */
    public function update_po_data_with_translations( $po_data, $original_strings, $translated_strings ) {
        $updated_entries = [];
        // Create a map from original string to its translated counterpart
        // This is necessary because the order might not be strictly preserved
        // or there might be duplicates in original_strings if context is ignored.
        $translated_map = [];
        foreach ($original_strings as $i => $original_string) {
            $translated_map[$original_string] = $translated_strings[$i] ?? '';
        }
        
        foreach ( $po_data['entries'] as $entry ) {
            if ( isset( $translated_map[ $entry['original'] ] ) ) {
                // If the original string was part of the batch sent for translation, update its translation
                $entry['translation'] = $translated_map[ $entry['original'] ];
            }
            $updated_entries[] = $entry;
        }
        $po_data['entries'] = $updated_entries;
        return $po_data;
    }

    /**
     * Writes PO data to a .po file.
     * This function uses the `php-gettext` library.
     *
     * @param string $filepath Full path to save the .po file.
     * @param array  $po_data  Array of PO data.
     * @return bool True on success, False on failure.
     */
    public function write_po_file( $filepath, $po_data ) {
        $translations = new Translations();
        
        // Set headers (php-gettext requires setting headers this way)
        if (isset($po_data['headers']) && is_array($po_data['headers'])) {
            foreach ($po_data['headers'] as $key => $value) {
                $translations->setHeader($key, $value);
            }
        } else {
            // Default headers if none provided or invalid
            $translations->setHeader('Project-Id-Version', 'AI Translator 1.0');
            $translations->setHeader('MIME-Version', '1.0');
            $translations->setHeader('Content-Type', 'text/plain; charset=UTF-8');
            $translations->setHeader('Content-Transfer-Encoding', '8bit');
            $translations->setHeader('X-Generator', 'AI Translator WordPress Plugin');
            $translations->setHeader('Plural-Forms', 'nplurals=2; plural=(n != 1);'); // Default English
        }


        foreach ( $po_data['entries'] as $entry_array ) {
            $entry = new Translation( $entry_array['original'], $entry_array['context'] ?? '' );
            $entry->setTranslation( $entry_array['translation'] ?? '' );
            if (isset($entry_array['plural'])) {
                $entry->setPlural($entry_array['plural']);
                if (isset($entry_array['plural_translations']) && is_array($entry_array['plural_translations'])) {
                    foreach ($entry_array['plural_translations'] as $index => $plural_trans) {
                        $entry->setPluralTranslation($plural_trans, $index);
                    }
                }
            }
            if (isset($entry_array['references']) && is_array($entry_array['references'])) {
                foreach ($entry_array['references'] as $ref) {
                    $entry->addReference($ref);
                }
            }
            if (isset($entry_array['comments']) && is_array($entry_array['comments'])) {
                foreach ($entry_array['comments'] as $comment) {
                    $entry->addComment($comment);
                }
            }
            $translations->add($entry);
        }

        // Ensure directory exists
        $dir = dirname( $filepath );
        if ( ! is_dir( $dir ) ) {
            if ( ! wp_mkdir_p( $dir ) ) {
                error_log( 'AI Translator: Failed to create directory for PO file: ' . $dir );
                return false;
            }
        }

        try {
            // Use Translations::toPoFile() for writing
            return $translations->toPoFile( $filepath );
        } catch ( Exception $e ) {
            error_log( 'AI Translator: Error writing PO file with Gettext Library: ' . $e->getMessage() . ' (File: ' . basename($filepath) . ')' );
            return false;
        }
    }

    /**
     * Compiles a .po file to a .mo file.
     * This function uses the `php-gettext` library.
     *
     * @param string $po_filepath Path to the .po file.
     * @param string $mo_filepath Output path for the .mo file.
     * @return bool True on success, False on failure.
     */
    public function compile_mo_file( $po_filepath, $mo_filepath ) {
        // Ensure directory exists for MO file
        $dir = dirname( $mo_filepath );
        if ( ! is_dir( $dir ) ) {
            if ( ! wp_mkdir_p( $dir ) ) {
                error_log( 'AI Translator: Failed to create directory for MO file: ' . $dir );
                return false;
            }
        }
        
        try {
            // Read PO file using PoParser, then generate MO using MoGenerator
            $parser = new PoParser();
            $po_content = file_get_contents($po_filepath);
            if ($po_content === false) {
                return new WP_Error( 'file_read_error', 'خطا در خواندن محتوای فایل PO برای کامپایل MO: ' . $po_filepath );
            }
            $translations = $parser->parseString($po_content);
            
            $generator = new MoGenerator();
            $mo_content = $generator->generateString($translations);

            if ($mo_content === false) {
                return new WP_Error( 'mo_generate_error', 'خطا در تولید محتوای فایل MO.' );
            }

            return file_put_contents($mo_filepath, $mo_content) !== false;

        } catch (Exception $e) {
            error_log('AI Translator: Error compiling MO file with Gettext Library: ' . $e->getMessage() . ' (PO File: ' . basename($po_filepath) . ')' );
            return false;
        }
    }
}
