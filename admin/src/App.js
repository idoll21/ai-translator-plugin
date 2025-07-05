import React, { useState, useEffect, useRef, useCallback } from 'react';
import './App.css'; // اطمینان حاصل کنید که این فایل CSS وجود دارد و در صورت نیاز استایل‌های عمومی را در آن قرار دهید.

/**
 * کامپوننت اصلی اپلیکیشن React.
 * این کامپوننت ناوبری، مدیریت صفحات مختلف و نمایش داده‌ها را انجام می‌دهد.
 */
function App() {
    // State برای مدیریت صفحه فعلی که نمایش داده می‌شود
    const [currentPage, setCurrentPage] = useState('dashboard');
    // State برای مدیریت لیست قالب‌ها و افزونه‌های قابل ترجمه
    const [translatableItems, setTranslatableItems] = useState([]);
    // State برای مدیریت داده‌های PO در حال ویرایش (برای ویرایشگر)
    const [editingPoData, setEditingPoData] = useState(null);
    // State برای آیتم ID در حال ویرایش
    const [editingItemId, setEditingItemId] = useState(null);
    // State برای زبان مقصد در حال ویرایش
    const [editingTargetLang, setEditingTargetLang] = useState('fa_IR'); // زبان فارسی به عنوان پیش‌فرض
    // State برای مدیریت تنظیمات پلاگین
    const [settings, setSettings] = useState({});
    // State برای مدیریت آمارهای پلاگین
    const [stats, setStats] = useState({});
    // State برای مدیریت سابقه تغییرات
    const [history, setHistory] = useState([]);
    // State برای نمایش پیام‌های لودینگ
    const [loading, setLoading] = useState(false);
    // State برای نمایش پیام‌های موفقیت/خطا
    const [message, setMessage] = useState({ text: '', type: '' }); // type: 'success' or 'error'
    // State برای نمایش/عدم نمایش مدال ویرایشگر
    const [showEditorModal, setShowEditorModal] = useState(false);
    // State برای مدیریت داده‌های فیلتر و جستجو
    const [filterQuery, setFilterQuery] = useState('');
    // Ref برای تایمر پیام
    const messageTimerRef = useRef(null);
    // State برای متن بازنویسی شده
    const [rewrittenText, setRewrittenText] = useState('');
    // State برای مدیریت متن در حال ویرایش در مدال بازنویسی
    const [rewriteEditorText, setRewriteEditorText] = useState('');
    // State برای نمایش مدال بازنویسی
    const [showRewriteModal, setShowRewriteModal] = useState(false);
    // State برای ذخیره stringId (original string) برای بازنویسی
    const [rewriteOriginalString, setRewriteOriginalString] = useState('');
    // State برای دستورالعمل بازنویسی
    const [rewriteInstruction, setRewriteInstruction] = useState('متن را مختصر و رسمی تر کنید.');

    // پاک کردن پیام پس از چند ثانیه
    const clearMessage = () => {
        if (messageTimerRef.current) {
            clearTimeout(messageTimerRef.current);
        }
        messageTimerRef.current = setTimeout(() => {
            setMessage({ text: '', type: '' });
        }, 5000); // پیام 5 ثانیه نمایش داده می‌شود
    };

    // افکت برای پاک کردن پیام
    useEffect(() => {
        if (message.text) {
            clearMessage();
        }
        return () => {
            if (messageTimerRef.current) {
                clearTimeout(messageTimerRef.current);
            }
        };
    }, [message]);


    // تابع عمومی برای هندل کردن درخواست‌های AJAX
    const sendAjaxRequest = useCallback(async (action, data, successCallback, errorCallback) => {
        setLoading(true);
        setMessage({ text: '', type: '' }); // پاک کردن پیام قبلی

        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', aiTranslatorData.nonce); // Nonce از wp_localize_script
        for (const key in data) {
            formData.append(key, data[key]);
        }

        try {
            const response = await fetch(aiTranslatorData.ajax_url, {
                method: 'POST',
                body: formData,
            });

            // Handle non-JSON response errors (e.g., PHP Fatal Errors)
            const contentType = response.headers.get('content-type');
            if (contentType && !contentType.includes('application/json')) {
                const rawText = await response.text();
                console.error('AI Translator: Non-JSON response received. Raw text:', rawText);
                setMessage({ text: `خطای AJAX: پاسخ غیر JSON دریافت شد. (لطفاً کنسول را برای جزئیات PHP Fatal Error بررسی کنید)`, type: 'error' });
                if (errorCallback) errorCallback(new Error('Non-JSON response'));
                return;
            }

            const result = await response.json();

            if (result.success) {
                setMessage({ text: result.data.message || 'عملیات موفقیت‌آمیز بود.', type: 'success' });
                if (successCallback) successCallback(result.data);
            } else {
                setMessage({ text: result.data.message || 'خطا در عملیات.', type: 'error' });
                if (errorCallback) errorCallback(result.data);
            }
        } catch (error) {
            console.error('AI Translator AJAX Fetch Error:', error);
            setMessage({ text: `خطای AJAX: ${error.message}`, type: 'error' });
            if (errorCallback) errorCallback(error);
        } finally {
            setLoading(false);
            clearMessage(); // تنظیم تایمر برای پاک کردن پیام
        }
    }, []); // Dependencies: aiTranslatorData.ajax_url, aiTranslatorData.nonce

    // --- توابع واکشی داده‌ها ---

    // واکشی لیست قالب‌ها و افزونه‌ها
    const fetchTranslatableItems = useCallback(() => {
        sendAjaxRequest('ai_translator_get_translatable_items', {}, (data) => {
            setTranslatableItems(data);
        });
    }, [sendAjaxRequest]);

    // واکشی تنظیمات پلاگین
    const fetchSettings = useCallback(() => {
        sendAjaxRequest('ai_translator_get_settings', {}, (data) => {
            setSettings(data);
        });
    }, [sendAjaxRequest]);

    // واکشی آمار
    const fetchStats = useCallback(() => {
        sendAjaxRequest('ai_translator_get_stats', {}, (data) => {
            setStats(data);
        });
    }, [sendAjaxRequest]);

    // واکشی تاریخچه
    const fetchHistory = useCallback(() => {
        sendAjaxRequest('ai_translator_get_history', {}, (data) => {
            setHistory(data);
        });
    }, [sendAjaxRequest]);

    // افکت برای واکشی داده‌های اولیه هنگام بارگذاری داشبورد
    useEffect(() => {
        if (currentPage === 'dashboard') {
            fetchStats();
            fetchTranslatableItems();
        } else if (currentPage === 'settings') {
            fetchSettings();
        } else if (currentPage === 'history') {
            fetchHistory();
        }
    }, [currentPage, fetchStats, fetchTranslatableItems, fetchSettings, fetchHistory]);


    // --- توابع مدیریت عملیات پلاگین ---

    // شروع ترجمه خودکار برای یک آیتم
    const handleTranslateItem = async (itemId, targetLang, forceRetranslate = false) => {
        sendAjaxRequest(
            'ai_translator_translate_item',
            { item_id: itemId, target_lang: targetLang, force_retranslate: forceRetranslate },
            (data) => {
                setEditingPoData(data.translated_data); // به‌روزرسانی داده‌های ترجمه شده در ویرایشگر
                setEditingItemId(itemId);
                setEditingTargetLang(targetLang);
                setShowEditorModal(true); // نمایش ویرایشگر برای بازبینی نهایی
                fetchStats(); // Update stats after translation
            }
        );
    };

    // باز کردن ویرایشگر برای یک آیتم (با واکشی داده‌های PO)
    const handleEditTranslation = (item) => {
        // اگر فایل .pot است، یا وجود ندارد، امکان ویرایش مستقیم رشته ها وجود ندارد.
        if (item.filepath.endsWith('.pot') || !item.exists) {
            setMessage({ text: 'فایل‌های POT (قالب‌های ترجمه) مستقیماً از اینجا قابل ویرایش نیستند. لطفاً یک فایل .po واقعی انتخاب کنید. یا فایل اصلی قالب/افزونه وجود ندارد.', type: 'error' });
            return;
        }

        setEditingItemId(item.id);
        setEditingTargetLang(item.locale); // Set target language based on existing PO
        sendAjaxRequest(
            'ai_translator_get_po_data_for_editing',
            { item_id: item.id },
            (data) => {
                setEditingPoData(data); // داده‌های کامل PO برای ویرایشگر
                setShowEditorModal(true);
            },
            (errorData) => {
                 // اگر خطا مربوط به "فایل ترجمه یافت نشد" باشد، نمایش پیام کاربرپسندتر
                if (errorData.message && errorData.message.includes('فایل ترجمه یافت نشد در لیست فایل های قابل ترجمه')) {
                     setMessage({ text: 'فایل ترجمه مورد نظر در لیست یافت نشد یا حذف شده است.', type: 'error' });
                } else if (errorData.message && errorData.message.includes('فایل POT یک قالب است')) {
                     // This case is already handled by the early exit condition
                } else if (errorData.message && errorData.message.includes('این فایل (یا قالب/افزونه اصلی آن) حذف شده')) {
                    setMessage({ text: 'فایل ترجمه یا فایل اصلی قالب/افزونه مربوطه حذف شده و قابل ویرایش نیست.', type: 'error' });
                } else {
                    setMessage({ text: `خطا در بارگذاری فایل ترجمه: ${errorData.message}`, type: 'error' });
                }
                setShowEditorModal(false);
            }
        );
    };

    // ذخیره ترجمه‌های ویرایش شده
    const handleSaveTranslation = (poData) => {
        sendAjaxRequest(
            'ai_translator_save_translation',
            {
                item_id: editingItemId,
                target_lang: editingTargetLang,
                po_data: JSON.stringify(poData), // ارسال کل داده PO به صورت JSON
            },
            () => {
                setShowEditorModal(false);
                setEditingPoData(null);
                fetchTranslatableItems(); // به‌روزرسانی لیست آیتم‌ها بعد از ذخیره
                fetchHistory(); // به‌روزرسانی تاریخچه
                fetchStats(); // Update stats
            }
        );
    };

    // ذخیره تنظیمات
    const handleSaveSettings = (newSettings) => {
        sendAjaxRequest(
            'ai_translator_save_settings',
            { settings: JSON.stringify(newSettings) },
            () => {
                fetchSettings(); // رفرش تنظیمات بعد از ذخیره
            }
        );
    };

    // هندل کردن درخواست بازنویسی متن با Gemini
    const handleRewriteText = async (text, instruction) => {
        setLoading(true);
        try {
            const formData = new FormData();
            formData.append('action', 'ai_translator_rewrite_text_gemini');
            formData.append('nonce', aiTranslatorData.nonce);
            formData.append('text', text);
            formData.append('instruction', instruction);

            const response = await fetch(aiTranslatorData.ajax_url, {
                method: 'POST',
                body: formData,
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const contentType = response.headers.get('content-type');
            if (contentType && !contentType.includes('application/json')) {
                const rawText = await response.text();
                console.error('AI Translator: Non-JSON response received for rewrite. Raw text:', rawText);
                setRewrittenText(`خطا: پاسخ غیر JSON. (کنسول PHP را بررسی کنید)`);
                return;
            }

            const result = await response.json();

            if (result.success) {
                setRewrittenText(result.data.rewritten_text);
                setMessage({ text: 'متن با موفقیت بازنویسی شد.', type: 'success' });
            } else {
                setRewrittenText(`خطا: ${result.data.message || 'مشکل در بازنویسی متن.'}`);
                setMessage({ text: `خطا در بازنویسی: ${result.data.message || 'مشکل ناشناخته.'}`, type: 'error' });
            }
        } catch (error) {
            console.error('AI Translator Rewrite AJAX Error:', error);
            setRewrittenText(`خطا در بازنویسی: ${error.message}`);
            setMessage({ text: `خطای شبکه در بازنویسی: ${error.message}`, type: 'error' });
        } finally {
            setLoading(false);
            clearMessage();
        }
    };


    // --- کامپوننت‌های فرعی UI ---

    // کامپوننت داشبورد
    const Dashboard = () => (
        <div className="bg-white p-6 rounded-lg shadow-md">
            <h2 className="text-2xl font-semibold text-gray-800 mb-6">پیشخوان AI Translator</h2>
            {loading && <p className="text-blue-500 mb-4">در حال بارگذاری...</p>}
            {message.text && (
                <div className={`p-3 mb-4 rounded-md ${message.type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                    {message.text}
                </div>
            )}
            
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div className="bg-blue-50 p-5 rounded-lg shadow-sm">
                    <h3 className="text-lg font-medium text-blue-800 mb-2">مصرف API این ماه</h3>
                    <p className="text-3xl font-bold text-blue-600">{stats.api_char_count_month || 0} کاراکتر ترجمه شده با API</p>
                </div>
                <div className="bg-green-50 p-5 rounded-lg shadow-sm">
                    <h3 className="text-lg font-medium text-green-800 mb-2">رشته‌های ترجمه شده</h3>
                    <p className="text-3xl font-bold text-green-600">{stats.total_strings_translated || 0} تعداد کل رشته‌های ترجمه شده</p>
                </div>
                <div className="bg-yellow-50 p-5 rounded-lg shadow-sm">
                    <h3 className="text-lg font-medium text-yellow-800 mb-2">ترجمه‌های فعال</h3>
                    <p className="text-3xl font-bold text-yellow-600">{stats.total_translations || 0} قالب و افزونه ترجمه شده</p>
                </div>
            </div>

            <h3 className="text-xl font-semibold text-gray-700 mb-4">فایل‌های قابل ترجمه</h3>
            <div className="mb-4">
                <input
                    type="text"
                    placeholder="جستجو بر اساس نام، نوع، دامنه متن یا وضعیت..."
                    className="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                    value={filterQuery}
                    onChange={(e) => setFilterQuery(e.target.value)}
                />
            </div>
            
            <div className="overflow-x-auto bg-white rounded-lg shadow">
                <table className="min-w-full leading-normal">
                    <thead>
                        <tr className="bg-gray-100 border-b-2 border-gray-200 text-gray-800 text-left text-sm font-semibold uppercase tracking-wider">
                            <th className="px-5 py-3">نام</th>
                            <th className="px-5 py-3">نوع</th>
                            <th className="px-5 py-3">فایل</th>
                            <th className="px-5 py-3">دامنه متنی</th>
                            <th className="px-5 py-3">زبان</th>
                            <th className="px-5 py-3">وضعیت</th>
                            <th className="px-5 py-3 text-center">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        {translatableItems.filter(item => 
                            item.name.toLowerCase().includes(filterQuery.toLowerCase()) ||
                            item.type.toLowerCase().includes(filterQuery.toLowerCase()) ||
                            item.filename.toLowerCase().includes(filterQuery.toLowerCase()) ||
                            item.text_domain.toLowerCase().includes(filterQuery.toLowerCase()) ||
                            item.status.toLowerCase().includes(filterQuery.toLowerCase()) ||
                            item.locale.toLowerCase().includes(filterQuery.toLowerCase())
                        ).length > 0 ? (
                            translatableItems.filter(item => 
                                item.name.toLowerCase().includes(filterQuery.toLowerCase()) ||
                                item.type.toLowerCase().includes(filterQuery.toLowerCase()) ||
                                item.filename.toLowerCase().includes(filterQuery.toLowerCase()) ||
                                item.text_domain.toLowerCase().includes(filterQuery.toLowerCase()) ||
                                item.status.toLowerCase().includes(filterQuery.toLowerCase()) ||
                                item.locale.toLowerCase().includes(filterQuery.toLowerCase())
                            ).map((item) => (
                                <tr key={item.id} className="hover:bg-gray-50 border-b border-gray-200">
                                    <td className="px-5 py-4 text-sm text-gray-900">{item.name}</td>
                                    <td className="px-5 py-4 text-sm text-gray-900">{item.type}</td>
                                    <td className="px-5 py-4 text-sm text-gray-900">{item.filename}</td>
                                    <td className="px-5 py-4 text-sm text-gray-900">{item.text_domain}</td>
                                    <td className="px-5 py-4 text-sm text-gray-900">{item.locale}</td>
                                    <td className="px-5 py-4 text-sm">
                                        <span className={`relative inline-block px-3 py-1 font-semibold leading-tight rounded-full ${item.status === 'ترجمه شده' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                            {item.status}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4 text-sm text-center">
                                        <button
                                            onClick={() => handleEditTranslation(item)}
                                            className="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-md text-xs transition duration-300 ease-in-out transform hover:scale-105 mr-2"
                                        >
                                            ویرایش
                                        </button>
                                        <button
                                            onClick={() => handleTranslateItem(item.id, 'fa_IR')} // پیش‌فرض به فارسی ترجمه شود
                                            className="bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-4 rounded-md text-xs transition duration-300 ease-in-out transform hover:scale-105"
                                        >
                                            ترجمه
                                        </button>
                                    </td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan="7" className="px-5 py-4 text-sm text-gray-600 text-center">هیچ فایل قابل ترجمه‌ای یافت نشد.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );

    // کامپوننت افزودن ترجمه جدید
    const AddNewTranslation = () => {
        const [selectedItem, setSelectedItem] = useState('');
        const [selectedTargetLang, setSelectedTargetLang] = useState('fa_IR');
        const [forceRetranslate, setForceRetranslate] = useState(false);

        const availableLanguages = [
            { code: 'fa_IR', name: 'فارسی (ایران)' },
            { code: 'en_US', name: 'انگلیسی (ایالات متحده)' },
            { code: 'ar', name: 'عربی' },
            { code: 'tr', name: 'ترکی استانبولی' },
            { code: 'es_ES', name: 'اسپانیایی' },
            { code: 'fr_FR', name: 'فرانسوی' },
            { code: 'de_DE', name: 'آلمانی' },
            { code: 'zh', name: 'چینی' },
            { code: 'ru', name: 'روسی' },
        ];

        return (
            <div className="bg-white p-6 rounded-lg shadow-md">
                <h2 className="text-2xl font-semibold text-gray-800 mb-6">افزودن ترجمه جدید</h2>
                {loading && <p className="text-blue-500 mb-4">در حال بارگذاری...</p>}
                {message.text && (
                    <div className={`p-3 mb-4 rounded-md ${message.type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                        {message.text}
                    </div>
                )}

                <div className="mb-4">
                    <label htmlFor="item-select" className="block text-gray-700 text-sm font-bold mb-2">
                        قالب یا افزونه‌ای را انتخاب کنید تا فایلهای ترجمه آن را پیدا و ترجمه کنید:
                    </label>
                    <select
                        id="item-select"
                        className="block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        value={selectedItem}
                        onChange={(e) => setSelectedItem(e.target.value)}
                    >
                        <option value="">انتخاب قالب یا افزونه</option>
                        {translatableItems.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name} ({item.type}) - {item.filename} | وضعیت فعلی: {item.status}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="mb-4">
                    <label htmlFor="target-lang-select" className="block text-gray-700 text-sm font-bold mb-2">
                        زبان مقصد ترجمه:
                    </label>
                    <select
                        id="target-lang-select"
                        className="block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                        value={selectedTargetLang}
                        onChange={(e) => setSelectedTargetLang(e.target.value)}
                    >
                        {availableLanguages.map((lang) => (
                            <option key={lang.code} value={lang.code}>{lang.name}</option>
                        ))}
                    </select>
                </div>

                <div className="mb-6 flex items-center">
                    <input
                        type="checkbox"
                        id="force-retranslate"
                        checked={forceRetranslate}
                        onChange={(e) => setForceRetranslate(e.target.checked)}
                        className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <label htmlFor="force-retranslate" className="ml-2 block text-sm text-gray-900">
                        اجبار به بازترجمه (حتی رشته‌های ترجمه شده را دوباره ترجمه کند)
                    </label>
                </div>

                <button
                    onClick={() => handleTranslateItem(selectedItem, selectedTargetLang, forceRetranslate)}
                    disabled={!selectedItem || !selectedTargetLang || loading}
                    className="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-md text-lg transition duration-300 ease-in-out transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
                >
                    {loading ? (
                        <>
                            <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            در حال ترجمه...
                        </>
                    ) : (
                        <>
                            <svg className="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            شروع ترجمه خودکار
                        </>
                    )}
                </button>
                <p className="mt-4 text-sm text-gray-600">پس از اتمام ترجمه، شما می‌توانید انتخاب کنید که فایل ترجمه شده جایگزین فایل اصلی شود یا به عنوان یک فایل جدید ذخیره شود.</p>
            </div>
        );
    };

    // کامپوننت ویرایشگر ترجمه (مدال)
    const TranslationEditorModal = ({ poData, onSave, onClose, onRewrite }) => {
        const [editedEntries, setEditedEntries] = useState(poData.entries);

        // به‌روزرسانی entries وقتی poData تغییر می‌کند
        useEffect(() => {
            setEditedEntries(poData.entries);
        }, [poData]);

        const handleChange = (index, field, value) => {
            const newEntries = [...editedEntries];
            newEntries[index][field] = value;
            setEditedEntries(newEntries);
        };

        const handleRewriteClick = (originalText, instruction = 'متن را مختصر و رسمی تر کنید.') => {
            setRewriteOriginalString(originalText);
            setRewriteInstruction(instruction);
            setRewrittenText('در حال بارگذاری...'); // Reset and show loading for rewrite
            setShowRewriteModal(true);
            handleRewriteText(originalText, instruction);
        };

        const handleApplyRewrittenText = () => {
            const newEntries = editedEntries.map(entry => {
                if (entry.original === rewriteOriginalString) {
                    return { ...entry, translation: rewrittenText };
                }
                return entry;
            });
            setEditedEntries(newEntries);
            setShowRewriteModal(false);
            setRewrittenText(''); // Clear rewritten text
            setRewriteOriginalString(''); // Clear original string
        };


        return (
            <div className="fixed inset-0 bg-gray-600 bg-opacity-75 overflow-y-auto h-full w-full z-50 flex justify-center items-center p-4">
                <div className="relative bg-white rounded-lg shadow-xl max-w-4xl w-full p-6 max-h-[90vh] overflow-y-auto">
                    <div className="flex justify-between items-center pb-3 border-b border-gray-200">
                        <h3 className="text-xl font-semibold text-gray-900">ویرایش ترجمه</h3>
                        <button onClick={onClose} className="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    {loading && <p className="text-blue-500 mb-4">در حال بارگذاری...</p>}
                    {message.text && (
                        <div className={`p-3 my-4 rounded-md ${message.type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                            {message.text}
                        </div>
                    )}

                    <div className="mt-4">
                        {editedEntries.map((entry, index) => (
                            <div key={index} className="mb-4 p-4 border border-gray-200 rounded-md bg-gray-50">
                                <div className="mb-2">
                                    <label className="block text-sm font-medium text-gray-700">متن اصلی (Original String):</label>
                                    <textarea
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-100 cursor-not-allowed"
                                        rows="2"
                                        readOnly
                                        value={entry.original}
                                    ></textarea>
                                </div>
                                <div className="mb-2">
                                    <label htmlFor={`translation-${index}`} className="block text-sm font-medium text-gray-700">ترجمه (Translation):</label>
                                    <textarea
                                        id={`translation-${index}`}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                        rows="2"
                                        value={entry.translation}
                                        onChange={(e) => handleChange(index, 'translation', e.target.value)}
                                    ></textarea>
                                </div>
                                {entry.context && (
                                    <div className="mb-2">
                                        <label className="block text-sm font-medium text-gray-700">محتوا (Context):</label>
                                        <input
                                            type="text"
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-100 cursor-not-allowed"
                                            readOnly
                                            value={entry.context}
                                        />
                                    </div>
                                )}
                                {entry.plural && (
                                    <div className="mb-2">
                                        <label className="block text-sm font-medium text-gray-700">صفت جمع (Plural):</label>
                                        <input
                                            type="text"
                                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-100 cursor-not-allowed"
                                            readOnly
                                            value={entry.plural}
                                        />
                                        {entry.plural_translations && entry.plural_translations.map((pt, ptIndex) => (
                                            <div key={ptIndex} className="mt-1">
                                                <label htmlFor={`plural-translation-${index}-${ptIndex}`} className="block text-sm font-medium text-gray-700">ترجمه جمع {ptIndex} (Plural Translation):</label>
                                                <textarea
                                                    id={`plural-translation-${index}-${ptIndex}`}
                                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                                    rows="1"
                                                    value={pt}
                                                    onChange={(e) => {
                                                        const newPluralTranslations = [...(newEntries[index].plural_translations || [])];
                                                        newPluralTranslations[ptIndex] = e.target.value;
                                                        handleChange(index, 'plural_translations', newPluralTranslations);
                                                    }}
                                                ></textarea>
                                            </div>
                                        ))}
                                    </div>
                                )}
                                <button
                                    onClick={() => handleRewriteClick(entry.original)}
                                    className="mt-2 bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-4 rounded-md text-sm transition duration-300 ease-in-out transform hover:scale-105"
                                >
                                    بازنویسی با AI
                                </button>
                            </div>
                        ))}
                    </div>

                    <div className="flex justify-end gap-3 mt-6 border-t pt-4">
                        <button
                            onClick={onClose}
                            className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-5 rounded-md transition duration-300 ease-in-out transform hover:scale-105"
                        >
                            انصراف
                        </button>
                        <button
                            onClick={() => onSave({ ...poData, entries: editedEntries })}
                            className="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-5 rounded-md transition duration-300 ease-in-out transform hover:scale-105"
                        >
                            ذخیره تغییرات
                        </button>
                    </div>
                </div>
            </div>
        );
    };

    // کامپوننت مدال بازنویسی
    const RewriteModal = ({ originalText, rewrittenText, onApply, onClose, onRewriteInstructionChange, rewriteInstruction }) => (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-75 overflow-y-auto h-full w-full z-50 flex justify-center items-center p-4">
            <div className="relative bg-white rounded-lg shadow-xl max-w-2xl w-full p-6">
                <div className="flex justify-between items-center pb-3 border-b border-gray-200">
                    <h3 className="text-xl font-semibold text-gray-900">بازنویسی با AI</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 transition-colors">
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div className="mt-4">
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700">متن اصلی:</label>
                        <textarea
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-100 cursor-not-allowed"
                            rows="3"
                            readOnly
                            value={originalText}
                        ></textarea>
                    </div>
                    <div className="mb-4">
                        <label htmlFor="rewrite-instruction" className="block text-sm font-medium text-gray-700">دستورالعمل بازنویسی:</label>
                        <input
                            type="text"
                            id="rewrite-instruction"
                            name="rewrite-instruction"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                            value={rewriteInstruction}
                            onChange={(e) => onRewriteInstructionChange(e.target.value)}
                            placeholder="مثلاً: متن را رسمی‌تر و کوتاه‌تر کن."
                        />
                         <button
                            onClick={() => handleRewriteText(originalText, rewriteInstruction)}
                            disabled={loading}
                            className="mt-2 bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-md text-sm transition duration-300 ease-in-out transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {loading ? 'در حال بازنویسی...' : 'بازنویسی مجدد'}
                        </button>
                    </div>
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700">متن بازنویسی شده:</label>
                        <textarea
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-100"
                            rows="5"
                            readOnly
                            value={rewrittenText}
                            placeholder={loading ? 'در حال دریافت پاسخ...' : 'متن بازنویسی شده در اینجا نمایش داده می‌شود.'}
                        ></textarea>
                    </div>
                </div>

                <div className="flex justify-end gap-3 mt-6 border-t pt-4">
                    <button
                        onClick={onClose}
                        className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-5 rounded-md transition duration-300 ease-in-out transform hover:scale-105"
                    >
                        بستن
                    </button>
                    <button
                        onClick={onApply}
                        disabled={!rewrittenText || loading}
                        className="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-5 rounded-md transition duration-300 ease-in-out transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        اعمال بازنویسی
                    </button>
                </div>
            </div>
        </div>
    );


    // کامپوننت تنظیمات
    const Settings = () => {
        const [localSettings, setLocalSettings] = useState(settings);

        useEffect(() => {
            setLocalSettings(settings);
        }, [settings]);

        const handleChange = (e) => {
            const { name, value, type, checked } = e.target;
            setLocalSettings(prevSettings => ({
                ...prevSettings,
                [name]: type === 'checkbox' ? checked : value,
            }));
        };

        const handleSubmit = (e) => {
            e.preventDefault();
            handleSaveSettings(localSettings);
        };

        return (
            <div className="bg-white p-6 rounded-lg shadow-md">
                <h2 className="text-2xl font-semibold text-gray-800 mb-6">تنظیمات پلاگین</h2>
                {loading && <p className="text-blue-500 mb-4">در حال بارگذاری...</p>}
                {message.text && (
                    <div className={`p-3 mb-4 rounded-md ${message.type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                        {message.text}
                    </div>
                )}

                <form onSubmit={handleSubmit}>
                    <div className="mb-4">
                        <label htmlFor="default_translator" className="block text-gray-700 text-sm font-bold mb-2">
                            مترجم پیش‌فرض:
                        </label>
                        <select
                            id="default_translator"
                            name="default_translator"
                            className="block w-full p-2 border border-gray-300 rounded-md shadow-sm"
                            value={localSettings.default_translator || ''}
                            onChange={handleChange}
                        >
                            <option value="gemini">Gemini (Google)</option>
                            <option value="libretranslate">LibreTranslate</option>
                            <option value="laratranslate">LaraTranslate</option>
                            <option value="chatgpt">ChatGPT (OpenAI)</option>
                            <option value="google">Google Translate API</option>
                            <option value="grok">Grok (XAI - فرضی)</option>
                        </select>
                    </div>

                    <div className="mb-4">
                        <label htmlFor="gemini_api_key" className="block text-gray-700 text-sm font-bold mb-2">
                            Gemini API Key:
                        </label>
                        <input
                            type="text"
                            id="gemini_api_key"
                            name="gemini_api_key"
                            className="w-full p-2 border border-gray-300 rounded-md shadow-sm"
                            value={localSettings.gemini_api_key || ''}
                            onChange={handleChange}
                            placeholder="کلید API جیمینای خود را وارد کنید"
                        />
                    </div>

                    <div className="mb-4">
                        <label htmlFor="libretranslate_endpoint" className="block text-gray-700 text-sm font-bold mb-2">
                            LibreTranslate Endpoint:
                        </label>
                        <input
                            type="url"
                            id="libretranslate_endpoint"
                            name="libretranslate_endpoint"
                            className="w-full p-2 border border-gray-300 rounded-md shadow-sm"
                            value={localSettings.libretranslate_endpoint || ''}
                            onChange={handleChange}
                            placeholder="مثلاً: http://localhost:5000/translate"
                        />
                    </div>

                    <div className="mb-4">
                        <label htmlFor="laratranslate_endpoint" className="block text-gray-700 text-sm font-bold mb-2">
                            LaraTranslate Endpoint:
                        </label>
                        <input
                            type="url"
                            id="laratranslate_endpoint"
                            name="laratranslate_endpoint"
                            className="w-full p-2 border border-gray-300 rounded-md shadow-sm"
                            value={localSettings.laratranslate_endpoint || ''}
                            onChange={handleChange}
                            placeholder="مثلاً: http://localhost:8000/api/translate"
                        />
                    </div>

                    <div className="mb-4">
                        <label htmlFor="chatgpt_api_key" className="block text-gray-700 text-sm font-bold mb-2">
                            ChatGPT API Key:
                        </label>
                        <input
                            type="text"
                            id="chatgpt_api_key"
                            name="chatgpt_api_key"
                            className="w-full p-2 border border-gray-300 rounded-md shadow-sm"
                            value={localSettings.chatgpt_api_key || ''}
                            onChange={handleChange}
                            placeholder="کلید API چت‌جی‌پی‌تی خود را وارد کنید"
                        />
                    </div>

                    <div className="mb-4">
                        <label htmlFor="google_translate_api_key" className="block text-gray-700 text-sm font-bold mb-2">
                            Google Translate API Key:
                        </label>
                        <input
                            type="text"
                            id="google_translate_api_key"
                            name="google_translate_api_key"
                            className="w-full p-2 border border-gray-300 rounded-md shadow-sm"
                            value={localSettings.google_translate_api_key || ''}
                            onChange={handleChange}
                            placeholder="کلید API گوگل ترنسلیت خود را وارد کنید"
                        />
                    </div>

                    <div className="mb-4">
                        <label htmlFor="grok_api_key" className="block text-gray-700 text-sm font-bold mb-2">
                            Grok API Key:
                        </label>
                        <input
                            type="text"
                            id="grok_api_key"
                            name="grok_api_key"
                            className="w-full p-2 border border-gray-300 rounded-md shadow-sm"
                            value={localSettings.grok_api_key || ''}
                            onChange={handleChange}
                            placeholder="کلید API گراک خود را وارد کنید (فرضی)"
                        />
                    </div>

                    <div className="mb-4 flex items-center">
                        <input
                            type="checkbox"
                            id="backup_before_replace"
                            name="backup_before_replace"
                            checked={localSettings.backup_before_replace || false}
                            onChange={handleChange}
                            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                        <label htmlFor="backup_before_replace" className="ml-2 block text-sm text-gray-900">
                            قبل از جایگزینی فایل ترجمه، پشتیبان‌گیری انجام شود.
                        </label>
                    </div>
                    
                    <div className="mb-4 flex items-center">
                        <input
                            type="checkbox"
                            id="skip_translated_strings"
                            name="skip_translated_strings"
                            checked={localSettings.skip_translated_strings || false}
                            onChange={handleChange}
                            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                        <label htmlFor="skip_translated_strings" className="ml-2 block text-sm text-gray-900">
                            هنگام ترجمه خودکار، رشته‌های از قبل ترجمه شده نادیده گرفته شوند.
                        </label>
                    </div>

                    <button
                        type="submit"
                        className="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-5 rounded-md transition duration-300 ease-in-out transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled={loading}
                    >
                        {loading ? 'در حال ذخیره...' : 'ذخیره تنظیمات'}
                    </button>
                </form>
            </div>
        );
    };

    // کامپوننت آمار و گزارش
    const StatsAndReports = () => (
        <div className="bg-white p-6 rounded-lg shadow-md">
            <h2 className="text-2xl font-semibold text-gray-800 mb-6">آمار و گزارشات</h2>
            {loading && <p className="text-blue-500 mb-4">در حال بارگذاری...</p>}
            {message.text && (
                <div className={`p-3 mb-4 rounded-md ${message.type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                    {message.text}
                </div>
            )}
            
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <div className="bg-blue-50 p-5 rounded-lg shadow-sm">
                    <h3 className="text-lg font-medium text-blue-800 mb-2">کاراکتر ترجمه شده با API</h3>
                    <p className="text-3xl font-bold text-blue-600">{stats.api_char_count_month || 0}</p>
                </div>
                <div className="bg-green-50 p-5 rounded-lg shadow-sm">
                    <h3 className="text-lg font-medium text-green-800 mb-2">تعداد کل رشته‌های ترجمه شده</h3>
                    <p className="text-3xl font-bold text-green-600">{stats.total_strings_translated || 0}</p>
                </div>
                <div className="bg-yellow-50 p-5 rounded-lg shadow-sm">
                    <h3 className="text-lg font-medium text-yellow-800 mb-2">قالب و افزونه ترجمه شده</h3>
                    <p className="text-3xl font-bold text-yellow-600">{stats.total_translations || 0}</p>
                </div>
            </div>

            <h3 className="text-xl font-semibold text-gray-700 mb-4">گزارش پیشرفت اخیر</h3>
            {stats.progress_report && stats.progress_report.length > 0 ? (
                <ul className="list-disc list-inside bg-gray-50 p-4 rounded-md shadow-inner">
                    {stats.progress_report.map((report, index) => (
                        <li key={index} className="mb-2 text-gray-700">
                            <strong>{report.timestamp}:</strong> {report.message}
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-gray-600 italic">هنوز گزارشی برای نمایش وجود ندارد.</p>
            )}
        </div>
    );

    // کامپوننت سابقه تغییرات
    const History = () => (
        <div className="bg-white p-6 rounded-lg shadow-md">
            <h2 className="text-2xl font-semibold text-gray-800 mb-6">سابقه تغییرات</h2>
            {loading && <p className="text-blue-500 mb-4">در حال بارگذاری...</p>}
            {message.text && (
                <div className={`p-3 mb-4 rounded-md ${message.type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                    {message.text}
                </div>
            )}

            <div className="overflow-x-auto bg-white rounded-lg shadow">
                <table className="min-w-full leading-normal">
                    <thead>
                        <tr className="bg-gray-100 border-b-2 border-gray-200 text-gray-800 text-left text-sm font-semibold uppercase tracking-wider">
                            <th className="px-5 py-3">زمان</th>
                            <th className="px-5 py-3">نام آیتم</th>
                            <th className="px-5 py-3">نوع عملیات</th>
                            <th className="px-5 py-3">توضیحات</th>
                            <th className="px-5 py-3">مسیر فایل</th>
                        </tr>
                    </thead>
                    <tbody>
                        {history.length > 0 ? (
                            history.map((entry) => (
                                <tr key={entry.id} className="hover:bg-gray-50 border-b border-gray-200">
                                    <td className="px-5 py-4 text-sm text-gray-900">{new Date(entry.timestamp).toLocaleString('fa-IR')}</td>
                                    <td className="px-5 py-4 text-sm text-gray-900">{entry.item_name}</td>
                                    <td className="px-5 py-4 text-sm text-gray-900">{entry.operation_type}</td>
                                    <td className="px-5 py-4 text-sm text-gray-900">{entry.changes_description}</td>
                                    <td className="px-5 py-4 text-sm text-gray-900 break-all">{entry.file_path}</td>
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td colSpan="5" className="px-5 py-4 text-sm text-gray-600 text-center">سابقه تغییری یافت نشد.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );


    // رندر کردن کامپوننت‌های بر اساس صفحه فعلی
    const renderPage = () => {
        switch (currentPage) {
            case 'dashboard':
                return <Dashboard />;
            case 'add-new-translation':
                return <AddNewTranslation />;
            case 'settings':
                return <Settings />;
            case 'stats-reports':
                return <StatsAndReports />;
            case 'history':
                return <History />;
            default:
                return <Dashboard />;
        }
    };

    return (
        <div className="ai-translator-admin wrap p-6 bg-gray-100 min-h-screen font-sans">
            <div className="md:flex md:space-x-6">
                {/* نوار کناری (Sidebar) */}
                <div className="w-full md:w-64 bg-white rounded-lg shadow-md p-6 mb-6 md:mb-0">
                    <h2 className="text-xl font-bold text-gray-800 mb-6">AI Translator</h2>
                    <nav>
                        <ul>
                            <li className="mb-3">
                                <button
                                    onClick={() => setCurrentPage('dashboard')}
                                    className={`w-full text-right py-3 px-4 rounded-md font-medium transition-colors duration-200 flex items-center ${currentPage === 'dashboard' ? 'bg-blue-600 text-white shadow' : 'text-gray-700 hover:bg-gray-100'}`}
                                >
                                    <svg className="w-5 h-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                                    پیشخوان
                                </button>
                            </li>
                            <li className="mb-3">
                                <button
                                    onClick={() => setCurrentPage('add-new-translation')}
                                    className={`w-full text-right py-3 px-4 rounded-md font-medium transition-colors duration-200 flex items-center ${currentPage === 'add-new-translation' ? 'bg-blue-600 text-white shadow' : 'text-gray-700 hover:bg-gray-100'}`}
                                >
                                    <svg className="w-5 h-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4"></path></svg>
                                    افزودن ترجمه جدید
                                </button>
                            </li>
                            <li className="mb-3">
                                <button
                                    onClick={() => setCurrentPage('settings')}
                                    className={`w-full text-right py-3 px-4 rounded-md font-medium transition-colors duration-200 flex items-center ${currentPage === 'settings' ? 'bg-blue-600 text-white shadow' : 'text-gray-700 hover:bg-gray-100'}`}
                                >
                                    <svg className="w-5 h-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.416 2.573-1.065z"></path><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    تنظیمات
                                </button>
                            </li>
                            <li className="mb-3">
                                <button
                                    onClick={() => setCurrentPage('stats-reports')}
                                    className={`w-full text-right py-3 px-4 rounded-md font-medium transition-colors duration-200 flex items-center ${currentPage === 'stats-reports' ? 'bg-blue-600 text-white shadow' : 'text-gray-700 hover:bg-gray-100'}`}
                                >
                                    <svg className="w-5 h-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0h7.5L12 4.5M18 10l4-4m-4 0l-4 4"></path></svg>
                                    آمار و گزارشات
                                </button>
                            </li>
                            <li className="mb-3">
                                <button
                                    onClick={() => setCurrentPage('history')}
                                    className={`w-full text-right py-3 px-4 rounded-md font-medium transition-colors duration-200 flex items-center ${currentPage === 'history' ? 'bg-blue-600 text-white shadow' : 'text-gray-700 hover:bg-gray-100'}`}
                                >
                                    <svg className="w-5 h-5 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    سابقه تغییرات
                                </button>
                            </li>
                        </ul>
                    </nav>
                    <div className="mt-8 pt-6 border-t border-gray-200 text-center text-sm text-gray-500">
                        <p>AI Translator v{aiTranslatorData.plugin_version}</p> {/* <-- اینجا اصلاح شد */}
                        <p>&copy; {new Date().getFullYear()} <a href="https://baanel.ir" target="_blank" rel="noopener noreferrer" className="text-blue-500 hover:underline">بانل</a></p>
                    </div>
                </div>

                {/* محتوای اصلی */}
                <div className="flex-1">
                    {renderPage()}
                </div>
            </div>

            {/* مدال ویرایشگر ترجمه */}
            {showEditorModal && editingPoData && (
                <TranslationEditorModal
                    poData={editingPoData}
                    onSave={handleSaveTranslation}
                    onClose={() => {
                        setShowEditorModal(false);
                        setEditingPoData(null); // Clear data when closing
                    }}
                />
            )}

            {/* مدال بازنویسی */}
            {showRewriteModal && (
                <RewriteModal
                    originalText={rewriteOriginalString}
                    rewrittenText={rewrittenText}
                    onApply={handleApplyRewrittenText}
                    onClose={() => {
                        setShowRewriteModal(false);
                        setRewrittenText(''); // Clear rewritten text
                        setRewriteOriginalString(''); // Clear original string
                    }}
                    onRewriteInstructionChange={setRewriteInstruction}
                    rewriteInstruction={rewriteInstruction}
                />
            )}
        </div>
    );
}

export default App;

