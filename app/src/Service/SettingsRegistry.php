<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Central registry for site settings metadata and defaults.
 */
class SettingsRegistry
{
    public const GROUP_GENERAL = 'general';
    public const GROUP_SIDEBAR = 'sidebar';
    public const GROUP_HOME = 'home';
    public const GROUP_COMMENTS = 'comments';
    public const GROUP_TURNSTILE = 'turnstile';
    public const GROUP_SMTP = 'smtp';
    public const GROUP_NOTIFICATIONS = 'notifications';
    public const GROUP_PERFORMANCE = 'performance';
    public const GROUP_SEO = 'seo';
    public const GROUP_PERMALINKS = 'permalinks';
    public const GROUP_APPEARANCE = 'appearance';
    public const GROUP_TRACKER = 'tracker';
    public const GROUP_WAF = 'waf';
    public const GROUP_METRIKA = 'metrika';
    public function all(): array
    {
        return [
            'site_title' => ['label' => 'Site Başlığı', 'group' => self::GROUP_GENERAL, 'type' => 'text', 'default' => 'Slaweally'],
            'site_description' => ['label' => 'Site Açıklaması', 'group' => self::GROUP_GENERAL, 'type' => 'textarea', 'default' => 'Sysadmin & Yazılım Geliştirici'],
            'site_email' => ['label' => 'İletişim E-postası', 'group' => self::GROUP_GENERAL, 'type' => 'email', 'default' => ''],
            'site_url' => ['label' => 'Site URL', 'group' => self::GROUP_GENERAL, 'type' => 'url', 'default' => ''],
            'site_logo' => ['label' => 'Site Logosu (URL)', 'group' => self::GROUP_GENERAL, 'type' => 'hidden', 'default' => ''],
            'site_favicon' => ['label' => 'Favicon (URL)', 'group' => self::GROUP_GENERAL, 'type' => 'hidden', 'default' => ''],
            'analytics_id' => ['label' => 'Analytics ID', 'group' => self::GROUP_GENERAL, 'type' => 'text', 'default' => ''],
            'custom_head_code' => [
                'label' => 'Head Özel Kod',
                'group' => self::GROUP_GENERAL,
                'type' => 'textarea',
                'default' => '',
                'help' => '</head> etiketinden hemen önce her sayfaya olduğu gibi eklenir (script, meta, style vb.).',
            ],
            'custom_footer_code' => [
                'label' => 'Body Sonu Özel Kod',
                'group' => self::GROUP_GENERAL,
                'type' => 'textarea',
                'default' => '',
                'help' => '</body> etiketinden hemen önce her sayfaya olduğu gibi eklenir (script vb.).',
            ],
            'seo_meta_title_suffix' => ['label' => 'SEO Başlık Soneki', 'group' => self::GROUP_SEO, 'type' => 'text', 'default' => '', 'help' => 'Örn: — Slaweally veya | Blog Adı. Boş bırakılırsa site başlığı kullanılır. Anasayfa hariç tüm sayfalara eklenir.'],
            'seo_home_title' => ['label' => 'Anasayfa SEO Başlığı', 'group' => self::GROUP_SEO, 'type' => 'text', 'default' => '', 'help' => 'Boş bırakılırsa Genel → Site Başlığı kullanılır.'],
            'seo_home_description' => ['label' => 'Anasayfa SEO Açıklaması', 'group' => self::GROUP_SEO, 'type' => 'textarea', 'default' => '', 'help' => 'Boş bırakılırsa Genel → Site Açıklaması kullanılır.'],
            'seo_home_keywords' => ['label' => 'Anasayfa Anahtar Kelimeler', 'group' => self::GROUP_SEO, 'type' => 'text', 'default' => ''],
            'seo_home_og_image' => ['label' => 'Anasayfa OG Görseli (URL)', 'group' => self::GROUP_SEO, 'type' => 'url', 'default' => ''],
            'seo_default_robots' => ['label' => 'Varsayılan Robots', 'group' => self::GROUP_SEO, 'type' => 'text', 'default' => 'index,follow'],
            'seo_robots_custom' => ['label' => 'robots.txt Ek Kurallar', 'group' => self::GROUP_SEO, 'type' => 'textarea', 'default' => ''],
            'seo_google_site_verification' => [
                'label' => 'Google Search Console Doğrulama Kodu',
                'group' => self::GROUP_SEO,
                'type' => 'text',
                'default' => '',
                'help' => 'Search Console → Ayarlar → Mülk Sahipliğini Doğrula → HTML Etiketi\'ndeki content değerini yapıştırın (meta tag\'in tamamını değil).',
            ],
            'seo_bing_site_verification' => [
                'label' => 'Bing Webmaster Doğrulama Kodu',
                'group' => self::GROUP_SEO,
                'type' => 'text',
                'default' => '',
                'help' => 'Bing Webmaster Tools → Doğrulama → Meta Etiketi\'ndeki content değerini yapıştırın.',
            ],
            'seo_yandex_site_verification' => [
                'label' => 'Yandex Webmaster Doğrulama Kodu',
                'group' => self::GROUP_SEO,
                'type' => 'text',
                'default' => '',
                'help' => 'Yandex Webmaster → Doğrulama → Meta Etiketi\'ndeki content değerini yapıştırın.',
            ],
            'seo_indexnow_enabled' => [
                'label' => 'IndexNow Otomatik Bildirim',
                'group' => self::GROUP_SEO,
                'type' => 'checkbox',
                'default' => 'false',
                'help' => 'Açıkken bir içerik yayınlandığında/güncellendiğinde Bing ve Yandex\'e IndexNow protokolüyle anında haber verilir.',
            ],
            'seo_indexnow_key' => [
                'label' => 'IndexNow Anahtarı',
                'group' => self::GROUP_SEO,
                'type' => 'hidden',
                'default' => '',
                'help' => 'Otomatik üretilir. IndexNow doğrulaması için /{anahtar}.txt adresinde sunulur.',
            ],
            'seo_not_found_redirect_enabled' => [
                'label' => '404 Sayfalarını Yönlendir',
                'group' => self::GROUP_SEO,
                'type' => 'checkbox',
                'default' => '1',
                'help' => 'Bulunamayan (404) ziyaretçi URL\'lerini aşağıdaki hedefe 301 ile yönlendirir. Bilinen eski adresler için Redirect Yöneticisi kullanın.',
            ],
            'seo_not_found_redirect_target' => [
                'label' => '404 Yönlendirme Hedefi',
                'group' => self::GROUP_SEO,
                'type' => 'text',
                'default' => '/',
                'help' => 'Örn: / veya /blog — site içi yol (http ile başlamamalı).',
            ],
            'seo_not_found_redirect_status' => [
                'label' => '404 Yönlendirme HTTP Kodu',
                'group' => self::GROUP_SEO,
                'type' => 'select',
                'default' => '301',
                'options' => [
                    '301' => '301 Kalıcı (SEO için önerilen)',
                    '302' => '302 Geçici',
                ],
            ],

            'permalink_structure' => [
                'label' => 'Kalıcı bağlantı yapısı',
                'group' => self::GROUP_PERMALINKS,
                'type' => 'select',
                'default' => 'default',
                'options' => [
                    'default' => 'Varsayılan (/{tip}/{slug})',
                    'postname' => 'Yazı adı (/{slug}/)',
                    'year_month' => 'Yıl / Ay / Yazı adı',
                    'year_month_day' => 'Yıl / Ay / Gün / Yazı adı',
                    'custom' => 'Özel yapı',
                ],
            ],
            'permalink_custom_structure' => [
                'label' => 'Özel yapı şablonu',
                'group' => self::GROUP_PERMALINKS,
                'type' => 'text',
                'default' => '/%postname%/',
                'help' => 'Etiketler: %postname%, %year%, %monthnum%, %day%, %category%',
            ],
            'permalink_suffix' => [
                'label' => 'URL uzantısı',
                'group' => self::GROUP_PERMALINKS,
                'type' => 'text',
                'default' => '',
                'help' => 'Örnek: .html — WordPress import uyumu için',
            ],
            'permalink_trailing_slash' => [
                'label' => 'Sonda eğik çizgi (/)',
                'group' => self::GROUP_PERMALINKS,
                'type' => 'checkbox',
                'default' => 'true',
            ],
            'permalink_category_base' => [
                'label' => 'Kategori tabanı',
                'group' => self::GROUP_PERMALINKS,
                'type' => 'text',
                'default' => 'kategori',
                'help' => 'WordPress: category',
            ],
            'permalink_tag_base' => [
                'label' => 'Etiket tabanı',
                'group' => self::GROUP_PERMALINKS,
                'type' => 'text',
                'default' => 'etiket',
                'help' => 'WordPress: tag',
            ],
            'permalink_page_base' => [
                'label' => 'Sayfa tabanı',
                'group' => self::GROUP_PERMALINKS,
                'type' => 'text',
                'default' => 'sayfa',
                'help' => 'Boş bırakırsanız sayfalar kök URL\'de yayınlanır',
            ],

            'admin_theme_accent' => ['label' => 'Admin Accent Renk', 'group' => self::GROUP_APPEARANCE, 'type' => 'text', 'default' => '#2f5ea6'],
            'admin_theme_bg' => ['label' => 'Admin Arkaplan Renk', 'group' => self::GROUP_APPEARANCE, 'type' => 'text', 'default' => '#eef2f7'],
            'admin_theme_text' => ['label' => 'Admin Yazı Renk', 'group' => self::GROUP_APPEARANCE, 'type' => 'text', 'default' => '#334155'],
            'admin_theme_font_family' => ['label' => 'Admin Font Ailesi', 'group' => self::GROUP_APPEARANCE, 'type' => 'text', 'default' => 'Inter'],
            'admin_theme_font_size' => ['label' => 'Admin Font Boyutu (px)', 'group' => self::GROUP_APPEARANCE, 'type' => 'number', 'default' => '15'],

            'footer_tags_enabled' => ['label' => 'Footer Etiket Barı', 'group' => self::GROUP_SIDEBAR, 'type' => 'checkbox', 'default' => 'true'],
            'footer_stats_enabled' => ['label' => 'Footer İstatistik Kartı', 'group' => self::GROUP_SIDEBAR, 'type' => 'checkbox', 'default' => 'true'],
            'sidebar_recent_count' => ['label' => 'Sidebar Son İçerik Sayısı', 'group' => self::GROUP_SIDEBAR, 'type' => 'number', 'default' => '6'],
            'sidebar_comments_count' => ['label' => 'Sidebar Yorum Sayısı', 'group' => self::GROUP_SIDEBAR, 'type' => 'number', 'default' => '5'],
            'footer_tags_count' => ['label' => 'Footer Etiket Sayısı', 'group' => self::GROUP_SIDEBAR, 'type' => 'number', 'default' => '24'],
            'footer_tags_sort' => [
                'label' => 'Footer Etiket Sıralaması',
                'group' => self::GROUP_SIDEBAR,
                'type' => 'select',
                'default' => 'popular',
                'options' => ['popular' => 'Popüler', 'recent' => 'Son eklenen', 'first' => 'A-Z'],
            ],

            'home_featured_count' => ['label' => 'Anasayfa Öne Çıkan Sayısı', 'group' => self::GROUP_HOME, 'type' => 'number', 'default' => '5'],
            'home_articles_count' => ['label' => 'Anasayfa Makale Sayısı', 'group' => self::GROUP_HOME, 'type' => 'number', 'default' => '5'],
            'home_notes_count' => ['label' => 'Anasayfa Not Sayısı', 'group' => self::GROUP_HOME, 'type' => 'number', 'default' => '10'],
            'home_recent_count' => ['label' => 'Anasayfa Son İçerikler Sayısı', 'group' => self::GROUP_HOME, 'type' => 'number', 'default' => '6'],
            'home_recent_show_excerpt' => [
                'label' => 'recent.log Özet Göster',
                'group' => self::GROUP_HOME,
                'type' => 'checkbox',
                'default' => 'false',
                'help' => 'Kapalıyken sadece başlık/tarih satırı (git log tarzı) gösterilir; açıkken kısa özet de eklenir.',
            ],
            'home_projects_count' => ['label' => 'Anasayfa Proje Sayısı', 'group' => self::GROUP_HOME, 'type' => 'number', 'default' => '3'],
            'home_software_count' => ['label' => 'Anasayfa Paket Sayısı', 'group' => self::GROUP_HOME, 'type' => 'number', 'default' => '3'],
            'home_howto_count' => ['label' => 'Anasayfa Nasıl Yapılır Sayısı', 'group' => self::GROUP_HOME, 'type' => 'number', 'default' => '4'],
            'home_troubleshoot_count' => ['label' => 'Anasayfa Hata/Çözüm Sayısı', 'group' => self::GROUP_HOME, 'type' => 'number', 'default' => '4'],
            'posts_per_page' => ['label' => 'Liste Sayfa Başına', 'group' => self::GROUP_HOME, 'type' => 'number', 'default' => '10'],
            'notes_per_page' => ['label' => 'Not Sayfa Başına', 'group' => self::GROUP_HOME, 'type' => 'number', 'default' => '20'],

            'comments_enabled' => ['label' => 'Yorumlar Aktif', 'group' => self::GROUP_COMMENTS, 'type' => 'checkbox', 'default' => 'true'],
            'moderation_enabled' => ['label' => 'Yorum Moderasyonu', 'group' => self::GROUP_COMMENTS, 'type' => 'checkbox', 'default' => 'true'],

            'notify_comment_enabled' => ['label' => 'Yeni Yorumda E-posta Bildirimi', 'group' => self::GROUP_NOTIFICATIONS, 'type' => 'checkbox', 'default' => 'true'],
            'notify_contact_enabled' => ['label' => 'Yeni Mesajda E-posta Bildirimi', 'group' => self::GROUP_NOTIFICATIONS, 'type' => 'checkbox', 'default' => 'true'],
            'notify_admin_email' => ['label' => 'Bildirim E-postası (boş = site e-postası)', 'group' => self::GROUP_NOTIFICATIONS, 'type' => 'email', 'default' => ''],
            'waf_notify_realtime_enabled' => [
                'label' => 'WAF Anlık E-posta Bildirimi',
                'group' => self::GROUP_NOTIFICATIONS,
                'type' => 'checkbox',
                'default' => 'false',
                'help' => 'Kapalıyken (önerilen) tekil bot taraması/SQLi/XSS denemeleri mail atmaz — sadece bir IP eşiği aşıp gerçekten banlandığında anlık mail gider. Açıksanız her tespitte (recon dahil) anlık mail alırsınız; sık bot taraması olan sitelerde kutunuzu doldurabilir.',
            ],
            'waf_notify_digest_enabled' => [
                'label' => 'WAF Günlük Özet E-postası',
                'group' => self::GROUP_NOTIFICATIONS,
                'type' => 'checkbox',
                'default' => 'true',
                'help' => 'Açıkken son 24 saatteki tüm WAF olaylarının (tür bazında sayı, banlanan IP sayısı, en çok tekrar eden IP\'ler) özetini günde bir kez tek mailde alırsınız. `app:security:daily-digest` komutu cron ile günde bir kez çalıştırılmalıdır.',
            ],

            'turnstile_enabled' => ['label' => 'Turnstile Aktif', 'group' => self::GROUP_TURNSTILE, 'type' => 'checkbox', 'default' => 'false'],
            'turnstile_site_key' => ['label' => 'Turnstile Site Key', 'group' => self::GROUP_TURNSTILE, 'type' => 'text', 'default' => ''],
            'turnstile_secret_key' => ['label' => 'Turnstile Secret Key', 'group' => self::GROUP_TURNSTILE, 'type' => 'password', 'default' => ''],

            'smtp_host' => ['label' => 'SMTP Sunucu', 'group' => self::GROUP_SMTP, 'type' => 'text', 'default' => ''],
            'smtp_port' => ['label' => 'SMTP Port', 'group' => self::GROUP_SMTP, 'type' => 'number', 'default' => '587'],
            'smtp_user' => ['label' => 'SMTP Kullanıcı', 'group' => self::GROUP_SMTP, 'type' => 'text', 'default' => ''],
            'smtp_password' => ['label' => 'SMTP Şifre', 'group' => self::GROUP_SMTP, 'type' => 'password', 'default' => ''],
            'smtp_encryption' => ['label' => 'Şifreleme (tls/ssl)', 'group' => self::GROUP_SMTP, 'type' => 'text', 'default' => 'tls'],
            'smtp_from_email' => ['label' => 'Gönderen E-posta', 'group' => self::GROUP_SMTP, 'type' => 'email', 'default' => ''],
            'smtp_from_name' => ['label' => 'Gönderen Adı', 'group' => self::GROUP_SMTP, 'type' => 'text', 'default' => ''],

            'performance_lazyload' => ['label' => 'Görsel Lazy Load', 'group' => self::GROUP_PERFORMANCE, 'type' => 'checkbox', 'default' => 'true'],
            'performance_compress_html' => ['label' => 'HTML Sıkıştırma', 'group' => self::GROUP_PERFORMANCE, 'type' => 'checkbox', 'default' => 'false'],
            'performance_doctrine_cache' => ['label' => 'Doctrine Sorgu Önbelleği', 'group' => self::GROUP_PERFORMANCE, 'type' => 'checkbox', 'default' => 'true'],
            'settings_cache_ttl' => ['label' => 'Ayar Önbellek Süresi (sn)', 'group' => self::GROUP_PERFORMANCE, 'type' => 'number', 'default' => '3600'],

            'cache_redis_enabled' => ['label' => 'Redis Önbellek Aktif', 'group' => self::GROUP_PERFORMANCE, 'type' => 'checkbox', 'default' => 'false'],
            'cache_redis_dsn' => ['label' => 'Redis DSN', 'group' => self::GROUP_PERFORMANCE, 'type' => 'text', 'default' => 'redis://127.0.0.1:6379'],
            'cache_redis_test_ok' => ['label' => 'Redis Test Durumu', 'group' => self::GROUP_PERFORMANCE, 'type' => 'hidden', 'default' => 'false'],

            'cache_varnish_enabled' => ['label' => 'Varnish Önbellek Aktif', 'group' => self::GROUP_PERFORMANCE, 'type' => 'checkbox', 'default' => 'false'],
            'cache_varnish_mode' => [
                'label' => 'Varnish Modu',
                'group' => self::GROUP_PERFORMANCE,
                'type' => 'select',
                'default' => 'cloudpanel',
                'options' => [
                    'cloudpanel' => 'CloudPanel (önerilen)',
                    'standalone' => 'Standalone Varnish (CLI secret)',
                ],
                'help' => 'CloudPanel sunucularında "CloudPanel" modunu seçin. PURGE isteği 127.0.0.1:6081 adresine X-Cache-Tags header ile gider.',
            ],
            'cache_varnish_host' => ['label' => 'Varnish Host', 'group' => self::GROUP_PERFORMANCE, 'type' => 'text', 'default' => '127.0.0.1', 'help' => 'CloudPanel\'de genelde 127.0.0.1'],
            'cache_varnish_port' => ['label' => 'Varnish Port', 'group' => self::GROUP_PERFORMANCE, 'type' => 'number', 'default' => '6081', 'help' => 'CloudPanel varsayılan port: 6081'],
            'cache_varnish_tag_prefix' => ['label' => 'Cache Tag Prefix', 'group' => self::GROUP_PERFORMANCE, 'type' => 'text', 'default' => '', 'help' => 'CloudPanel → Varnish Cache → Cache Tag Prefix ile aynı olmalı.'],
            'cache_varnish_secret' => ['label' => 'Varnish Secret (standalone)', 'group' => self::GROUP_PERFORMANCE, 'type' => 'password', 'default' => '', 'help' => 'Yalnızca standalone modda kullanılır.'],
            'cache_varnish_purge_url' => ['label' => 'PURGE URL (standalone)', 'group' => self::GROUP_PERFORMANCE, 'type' => 'url', 'default' => '', 'help' => 'Yalnızca standalone modda kullanılır.'],
            'cache_varnish_test_ok' => ['label' => 'Varnish Test Durumu', 'group' => self::GROUP_PERFORMANCE, 'type' => 'hidden', 'default' => 'false'],
            'tracker_enabled' => ['label' => 'Takip Paneli Aktif', 'group' => self::GROUP_TRACKER, 'type' => 'checkbox', 'default' => 'true'],
            'tracker_page_size' => ['label' => 'Sayfa Başına Kayıt', 'group' => self::GROUP_TRACKER, 'type' => 'number', 'default' => '20'],
            'tracker_max_logs' => ['label' => 'Azami Kayıt Sayısı', 'group' => self::GROUP_TRACKER, 'type' => 'number', 'default' => '500', 'help' => 'Bu sayının üzerindeki en eski kayıtlar temizlik komutunda silinir.'],
            'tracker_retention_days' => ['label' => 'Saklama Süresi (gün)', 'group' => self::GROUP_TRACKER, 'type' => 'number', 'default' => '30'],

            'waf_max_requests' => [
                'label' => 'Dakikadaki Azami Şüpheli İstek',
                'group' => self::GROUP_WAF,
                'type' => 'number',
                'default' => '3',
                'help' => 'Bir IP bu sayıyı 1 dakika içinde aşarsa otomatik banlanır.',
            ],
            'waf_ban_duration' => [
                'label' => 'Ban Süresi (saat)',
                'group' => self::GROUP_WAF,
                'type' => 'number',
                'default' => '24',
            ],
            'waf_detect_sqli' => ['label' => 'SQL Injection Taraması', 'group' => self::GROUP_WAF, 'type' => 'checkbox', 'default' => 'true'],
            'waf_detect_xss' => ['label' => 'XSS Taraması', 'group' => self::GROUP_WAF, 'type' => 'checkbox', 'default' => 'true'],
            'waf_detect_traversal' => ['label' => 'Directory Traversal Taraması', 'group' => self::GROUP_WAF, 'type' => 'checkbox', 'default' => 'true'],
            'waf_detect_recon' => [
                'label' => 'Bot Tarama / Hassas Dosya Keşfi Taraması',
                'group' => self::GROUP_WAF,
                'type' => 'checkbox',
                'default' => 'true',
                'help' => '.env, wp-config.php, .aws/config gibi yaygın dosya keşif isteklerini "SQL Injection" yerine "Bot Scan / Reconnaissance" olarak sınıflandırır.',
            ],
            'waf_blacklist_ips' => [
                'label' => 'Kara Liste IP\'ler',
                'group' => self::GROUP_WAF,
                'type' => 'textarea',
                'default' => '',
                'help' => 'Her satıra bir IP. Bu IP\'ler tarama yapılmadan doğrudan 403 ile engellenir.',
            ],
            'waf_whitelist_ips' => [
                'label' => 'Beyaz Liste IP\'ler',
                'group' => self::GROUP_WAF,
                'type' => 'textarea',
                'default' => '',
                'help' => 'Her satıra bir IP. Bu IP\'ler WAF taramasına ve ban mekanizmasına hiç takılmaz.',
            ],
            'waf_log_banned_repeats' => [
                'label' => 'Banlı IP Tekrarlarını Logla',
                'group' => self::GROUP_WAF,
                'type' => 'checkbox',
                'default' => 'false',
                'help' => 'Kapalıyken banlı bir IP tekrar istek attığında veritabanına hiç yazılmaz, sadece cache üzerinden 403 ile engellenir (DB yükü sıfıra iner).',
            ],
            'waf_auto_purge_days' => [
                'label' => 'Otomatik Log Temizleme (gün)',
                'group' => self::GROUP_WAF,
                'type' => 'number',
                'default' => '30',
                'help' => '`app:security:purge` komutu çalıştığında bu süreden uzun süredir görülmemiş (son görülme) alarm kayıtları silinir. 0 = otomatik temizlik kapalı.',
            ],
            'metrika_enabled' => ['label' => 'Yandex Metrika Aktif', 'group' => self::GROUP_METRIKA, 'type' => 'checkbox', 'default' => 'false'],
            'metrika_counter_id' => ['label' => 'Sayaç ID', 'group' => self::GROUP_METRIKA, 'type' => 'text', 'default' => ''],
            'metrika_webvisor' => ['label' => 'Webvisor', 'group' => self::GROUP_METRIKA, 'type' => 'checkbox', 'default' => 'true'],
            'metrika_clickmap' => ['label' => 'Tıklama Haritası', 'group' => self::GROUP_METRIKA, 'type' => 'checkbox', 'default' => 'true'],
            'metrika_tracklinks' => ['label' => 'Link Takibi', 'group' => self::GROUP_METRIKA, 'type' => 'checkbox', 'default' => 'true'],
            'metrika_accurate_track' => ['label' => 'Hassas Hemen Çıkma Takibi', 'group' => self::GROUP_METRIKA, 'type' => 'checkbox', 'default' => 'false'],
            'metrika_track_hash' => ['label' => 'URL Hash Takibi', 'group' => self::GROUP_METRIKA, 'type' => 'checkbox', 'default' => 'false'],
            'metrika_oauth_access_token' => ['label' => 'OAuth Erişim Anahtarı', 'group' => self::GROUP_METRIKA, 'type' => 'password', 'default' => ''],
            'metrika_oauth_authorized' => ['label' => 'OAuth Bağlantı Durumu', 'group' => self::GROUP_METRIKA, 'type' => 'hidden', 'default' => 'false'],
        ];
    }

    /** @return array<string, array{label: string, group: string, type: string, default?: string}> */
    public function byGroup(string $group): array
    {
        return array_filter($this->all(), static fn (array $meta): bool => $meta['group'] === $group);
    }

    /** @return array<string, string> */
    public function groupLabels(): array
    {
        return [
            self::GROUP_GENERAL => 'Genel',
            self::GROUP_SIDEBAR => 'Sidebar & Görünüm',
            self::GROUP_HOME => 'Anasayfa & Listeleme',
            self::GROUP_COMMENTS => 'Yorumlar',
            self::GROUP_NOTIFICATIONS => 'Bildirimler',
            self::GROUP_TURNSTILE => 'Turnstile (CAPTCHA)',
            self::GROUP_SMTP => 'E-posta (SMTP)',
            self::GROUP_PERFORMANCE => 'Performans',
            self::GROUP_SEO => 'SEO',
            self::GROUP_PERMALINKS => 'Kalıcı Bağlantılar',
            self::GROUP_APPEARANCE => 'Admin Teması',
            self::GROUP_TRACKER => 'Takip Paneli',
            self::GROUP_WAF => 'Güvenlik Duvarı (WAF)',
            self::GROUP_METRIKA => 'Yandex Metrika',
        ];
    }
}
