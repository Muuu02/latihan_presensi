<?php
/**
 * config.php - Konfigurasi sistem dengan parsing .env aman
 * 
 * File ini memuat konfigurasi dari .env menggunakan parse_ini_file
 * dan menyediakan fallback ke nilai default jika .env tidak ada.
 * Compatible dengan shared hosting cPanel tanpa dependency tambahan.
 */

// Mencegah akses langsung
if (!defined('ASSESSMENT_SYSTEM')) {
    define('ASSESSMENT_SYSTEM', true);
}

// Path dasar sistem
define('BASE_PATH', dirname(__DIR__));
define('CONFIG_PATH', BASE_PATH . '/.env');
define('LOG_PATH', BASE_PATH . '/logs/audit.jsonl');

/**
 * Parse file .env dengan aman
 * Menggunakan parse_ini_file untuk kompatibilitas cPanel
 * 
 * @return array Array konfigurasi dengan fallback default
 */
function loadConfig(): array
{
    $defaultConfig = [
        'DB_HOST' => 'localhost',
        'DB_NAME' => 'asesmen_sd',
        'DB_USER' => 'root',
        'DB_PASS' => '',
        'API_KEY_GURU' => '',
        'API_KEY_WALIKELAS' => '',
        'API_KEY_KEPSEK' => '',
        'APP_ENV' => 'production',
        'APP_DEBUG' => false,
        'RATE_LIMIT_REQUESTS' => 30,
        'RATE_LIMIT_WINDOW' => 60,
        'AI_PROXY_URL' => '',
        'AI_PROXY_KEY' => '',
    ];

    // Coba load dari .env jika ada
    if (file_exists(CONFIG_PATH)) {
        $envConfig = parse_ini_file(CONFIG_PATH, false, INI_SCANNER_RAW);
        if ($envConfig !== false) {
            return array_merge($defaultConfig, $envConfig);
        }
    }

    return $defaultConfig;
}

// Load konfigurasi global
$CONFIG = loadConfig();

/**
 * Helper function untuk mendapatkan nilai config
 * 
 * @param string $key Kunci konfigurasi
 * @param mixed $default Nilai default jika key tidak ditemukan
 * @return mixed Nilai konfigurasi
 */
function config(string $key, $default = null)
{
    global $CONFIG;
    return $CONFIG[$key] ?? $default;
}

/**
 * Cek apakah aplikasi dalam mode debug
 * 
 * @return bool True jika debug mode aktif
 */
function isDebugMode(): bool
{
    return config('APP_DEBUG', false) === true || 
           config('APP_DEBUG', 'false') === 'true';
}

/**
 * Dapatkan path lengkap untuk log file
 * Pastikan direktori logs ada
 * 
 * @return string Path lengkap file audit log
 */
function getLogPath(): string
{
    $logDir = BASE_PATH . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    return $logDir . '/audit.jsonl';
}

/**
 * Validasi koneksi database
 * 
 * @return PDO|null Object PDO atau null jika gagal
 */
function getDbConnection(): ?PDO
{
    try {
        $dsn = "mysql:host=" . config('DB_HOST') . 
               ";dbname=" . config('DB_NAME') . 
               ";charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        return new PDO(
            $dsn,
            config('DB_USER'),
            config('DB_PASS'),
            $options
        );
    } catch (PDOException $e) {
        if (isDebugMode()) {
            error_log("Database connection failed: " . $e->getMessage());
        }
        return null;
    }
}

// Set header keamanan default
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy untuk shared hosting
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
