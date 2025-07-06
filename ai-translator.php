<?php
/**
 * Plugin Name:       AI Translator
 * Plugin URI:        https://baanel.ir/ai-translator
 * Description:       مترجم قالب و افزونه وردپرس با هوش مصنوعی. ترجمه اتوماتیک با مترجم‌های رایگان و پولی.
 * Version:           1.0
 * Author:            بانل
 * Author URI:        https://baanel.ir
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ai-translator
 * Domain Path:       /languages
 */

// جلوگیری از دسترسی مستقیم به فایل
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// تعریف ثوابت پلاگین
define( 'AI_TRANSLATOR_VERSION', '1.0' );
define( 'AI_TRANSLATOR_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_TRANSLATOR_URL', plugin_dir_url( __FILE__ ) );

/**
 * بررسی و بارگذاری Autoloader Composer.
 * این تابع را به 'plugins_loaded' هوک می‌کنیم تا مطمئن شویم که Composer در زمان مناسب بارگذاری می‌شود.
 */
function ai_translator_load_composer_autoload() {
    $autoload_path = AI_TRANSLATOR_DIR . 'vendor/autoload.php';

    if ( file_exists( $autoload_path ) ) {
        require_once $autoload_path;

        if ( ! class_exists( 'Gettext\\Translations' ) ) {
            $parser_path = AI_TRANSLATOR_DIR . 'vendor/gettext/gettext/src/PO/Parser.php';
            $generator_path = AI_TRANSLATOR_DIR . 'vendor/gettext/gettext/src/MO/Generator.php';
            $translations_path = AI_TRANSLATOR_DIR . 'vendor/gettext/gettext/src/Translations.php';
            $translation_path = AI_TRANSLATOR_DIR . 'vendor/gettext/gettext/src/Translation.php';

            if ( file_exists( $parser_path ) && file_exists( $generator_path ) && file_exists( $translations_path ) && file_exists( $translation_path ) ) {
                require_once $translations_path;
                require_once $translation_path;
                require_once $parser_path;
                require_once $generator_path;
            } else {
                add_action( 'admin_notices', function() {
                    ?>
                    <div class="notice notice-error is-dismissible">
                        <p><strong>AI Translator:</strong> کتابخانه Gettext (مورد نیاز برای ترجمه) به درستی بارگذاری نشد. لطفاً مطمئن شوید `composer install` در پوشه پلاگین به درستی اجرا شده است.</p>
                        <p><strong>جزییات:</strong> کلاس <code>Gettext\Translations</code> یا فایل‌های اصلی Gettext یافت نشدند. مسیر <code>vendor/gettext/gettext/src/</code> را بررسی کنید.</p>
                    </div>
                    <?php
                });
                return;
            }
        }
    } else {
        add_action( 'admin_notices', function() {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><strong>AI Translator:</strong> وابستگی‌های پلاگین نصب نشده‌اند! لطفاً به پوشه پلاگین (<code><?php echo esc_html( basename(AI_TRANSLATOR_DIR) ); ?></code>) بروید و دستور <code>composer install</code> را در ترمینال اجرا کنید. سپس صفحه را رفرش کنید.</p>
                <p><strong>جزییات:</strong> فایل <code>vendor/autoload.php</code> یافت نشد.</p>
            </div>
            <?php
        });
        return;
    }

    // اگر Composer Autoload با موفقیت بارگذاری شد و کلاس Gettext پیدا شد، سپس کلاس اصلی پلاگین را اجرا کنید.
    if ( class_exists( 'AI_Translator' ) ) {
        $ai_translator = new AI_Translator();

        // هوک‌های فعال‌سازی/غیرفعال‌سازی
        register_activation_hook( __FILE__, array( 'AI_Translator', 'activate' ) );
        register_deactivation_hook( __FILE__, array( 'AI_Translator', 'deactivate' ) );
    }
}
// بازگرداندن به 'plugins_loaded' که برای بارگذاری پلاگین‌ها معمول‌تر است
add_action( 'plugins_loaded', 'ai_translator_load_composer_autoload' );


/**
 * کلاسی برای مدیریت اصلی پلاگین.
 */
class AI_Translator {

    // اعلام Property ها برای جلوگیری از هشدارهای Deprecated در PHP 8.2+
    private AI_Translator_Settings     $settings;
    private AI_Translator_Database     $database;
    private AI_Translator_Backup       $backup;
    private AI_Translator_File_Handler $file_handler;
    private AI_Translator_API_Client   $api_client;
    private AI_Translator_Admin        $admin;


    /**
     * سازنده کلاس AI_Translator.
     * هوک‌های لازم را اضافه می‌کند و کلاس‌های مورد نیاز را بارگذاری می‌کند.
     */
    public function __construct() {
        $this->load_plugin_classes();
        $this->define_admin_hooks(); // این متد اکنون فقط هوک‌های منوی ادمین و enqueue scripts را تعریف می‌کند.
        $this->define_public_hooks(); // اگر پلاگین در فرانت‌اند هم هوکی داشته باشد
    }

    /**
     * بارگذاری فایل‌های کلاس‌های مورد نیاز پلاگین.
     * فرض بر این است که Composer Autoload.php قبلاً کلاس‌های Gettext را در دسترس قرار داده است.
     */
    private function load_plugin_classes() {
        // بارگذاری فایل‌های includes (منطق اصلی)
        require_once AI_TRANSLATOR_DIR . 'includes/class-ai-translator-settings.php';
        require_once AI_TRANSLATOR_DIR . 'includes/class-ai-translator-database.php';
        require_once AI_TRANSLATOR_DIR . 'includes/class-ai-translator-backup.php';
        require_once AI_TRANSLATOR_DIR . 'includes/class-ai-translator-file-handler.php';
        require_once AI_TRANSLATOR_DIR . 'includes/class-ai-translator-api-client.php';

        // بارگذاری فایل‌های admin (پنل مدیریت)
        require_once AI_TRANSLATOR_DIR . 'admin/class-ai-translator-admin.php';

        // نمونه‌سازی از کلاس‌ها
        $this->settings     = new AI_Translator_Settings();
        $this->database     = new AI_Translator_Database();
        $this->backup       = new AI_Translator_Backup( $this->database );
        $this->file_handler = new AI_Translator_File_Handler();
        $this->api_client   = new AI_Translator_API_Client( $this->settings );

        // نمونه‌سازی از کلاس ادمین و پاس دادن نمونه‌های دیگر به آن
        // هوک‌های AJAX اکنون در سازنده AI_Translator_Admin ثبت می‌شوند.
        $this->admin = new AI_Translator_Admin( $this->settings, $this->file_handler, $this->api_client, $this->backup, $this->database );
    }

    /**
     * هوک‌های مربوط به بخش مدیریت (Admin) را تعریف می‌کند.
     * ایجاد منو و بارگذاری اسکریپت‌ها. هوک‌های AJAX اکنون در سازنده AI_Translator_Admin ثبت می‌شوند.
     */
    private function define_admin_hooks() {
        add_action( 'admin_menu', array( $this->admin, 'add_plugin_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_styles_scripts' ) );
        // هوک‌های AJAX از اینجا حذف شدند و به سازنده AI_Translator_Admin منتقل شدند.
    }

    /**
     * هوک‌های مربوط به بخش عمومی (Public) را تعریف می‌کند. (در صورت نیاز)
     * برای این پلاگین، بیشتر فعالیت‌ها در بخش ادمین است.
     */
    private function define_public_hooks() {
        // add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
    }

    /**
     * فعال‌سازی پلاگین.
     * جداول دیتابیس را در زمان فعال‌سازی ایجاد می‌کند.
     */
    public static function activate() {
        error_reporting(0);
        @ini_set('display_errors', 0);
        $database = new AI_Translator_Database();
        $database->create_tables();
    }

    /**
     * غیرفعال‌سازی پلاگین.
     * (اختیاری: پاکسازی جداول دیتابیس یا تنظیمات)
     */
    public static function deactivate() {
        error_reporting(0);
        @ini_set('display_errors', 0);
        // این تابع می‌تواند برای پاکسازی داده‌ها یا تنظیمات استفاده شود.
    }
}
