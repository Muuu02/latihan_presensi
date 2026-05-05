<?php
/**
 * AuditLog.php - Middleware Audit Logging
 * 
 * Mencatat semua request API ke file JSONL untuk keperluan audit trail.
 * Format: JSON Lines (satu JSON per baris)
 * Compatible dengan UU PDP Indonesia untuk logging akses data.
 */

class AuditLog
{
    private string $logFile;
    
    /**
     * Constructor
     * 
     * @param string|null $logFile Path file log (default: logs/audit.jsonl)
     */
    public function __construct(?string $logFile = null)
    {
        $this->logFile = $logFile ?? getLogPath();
        
        // Pastikan direktori ada
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Catat event audit ke file
     * 
     * @param array $event Data event yang akan dicatat
     * @return bool True jika berhasil
     */
    public function log(array $event): bool
    {
        // Tambahkan metadata default
        $logEntry = array_merge([
            'timestamp' => date('c'), // ISO 8601 format
            'timestamp_unix' => time(),
            'ip' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        ], $event);
        
        // Encode ke JSON
        $jsonLine = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($jsonLine === false) {
            error_log("AuditLog: Failed to encode JSON for entry");
            return false;
        }
        
        // Append ke file dengan lock untuk concurrent safety
        $result = file_put_contents(
            $this->logFile,
            $jsonLine . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
        
        return $result !== false;
    }
    
    /**
     * Catat request HTTP
     * 
     * @param string $endpoint Endpoint yang diakses
     * @param string $method HTTP method
     * @param int $statusCode HTTP response status code
     * @param string|null $userId ID user (jika ada)
     * @param string|null $role Role user
     * @return bool True jika berhasil
     */
    public function logRequest(
        string $endpoint,
        string $method,
        int $statusCode,
        ?string $userId = null,
        ?string $role = null
    ): bool {
        return $this->log([
            'type' => 'http_request',
            'endpoint' => $endpoint,
            'method' => strtoupper($method),
            'status_code' => $statusCode,
            'user_id' => $userId,
            'role' => $role,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'content_length' => (int)($_SERVER['CONTENT_LENGTH'] ?? 0),
        ]);
    }
    
    /**
     * Catat event autentikasi
     * 
     * @param bool $success Apakah autentikasi berhasil
     * @param string|null $reason Alasan jika gagal
     * @param string|null $role Role user (jika berhasil)
     * @return bool True jika berhasil
     */
    public function logAuth(bool $success, ?string $reason = null, ?string $role = null): bool
    {
        return $this->log([
            'type' => 'authentication',
            'success' => $success,
            'reason' => $reason,
            'role' => $role,
        ]);
    }
    
    /**
     * Catat akses data sensitif (untuk compliance UU PDP)
     * 
     * @param string $dataType Jenis data yang diakses
     * @param string $action Aksi yang dilakukan (read/update/delete)
     * @param string|null $recordId ID record yang diakses
     * @return bool True jika berhasil
     */
    public function logDataAccess(string $dataType, string $action, ?string $recordId = null): bool
    {
        return $this->log([
            'type' => 'data_access',
            'data_type' => $dataType,
            'action' => $action,
            'record_id' => $recordId,
            'purpose' => 'assessment_system',
        ]);
    }
    
    /**
     * Catat export data
     * 
     * @param string $exportType Jenis export (csv, pdf, dll)
     * @param int $recordCount Jumlah record yang diekspor
     * @return bool True jika berhasil
     */
    public function logExport(string $exportType, int $recordCount): bool
    {
        return $this->log([
            'type' => 'data_export',
            'export_type' => $exportType,
            'record_count' => $recordCount,
            'format' => $exportType,
        ]);
    }
    
    /**
     * Dapatkan alamat IP client
     * Handle proxy/load balancer headers
     * 
     * @return string Alamat IP client
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_FORWARDED_FOR',   // Standard proxy
            'HTTP_X_REAL_IP',         // Nginx
            'REMOTE_ADDR'             // Direct connection
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle multiple IPs in X-Forwarded-For
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                // Validasi format IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Baca log entries (untuk admin)
     * 
     * @param int $limit Jumlah maksimal entries yang dibaca
     * @param int $offset Offset untuk pagination
     * @return array Array of log entries
     */
    public function readLogs(int $limit = 100, int $offset = 0): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            return [];
        }
        
        // Reverse untuk dapat yang terbaru dulu
        $lines = array_reverse($lines);
        
        // Apply offset dan limit
        $subset = array_slice($lines, $offset, $limit);
        
        $entries = [];
        foreach ($subset as $line) {
            $entry = json_decode($line, true);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }
        
        return $entries;
    }
    
    /**
     * Rotasi log file (jika ukuran terlalu besar)
     * 
     * @param int $maxSizeBytes Ukuran maksimal file dalam bytes (default: 10MB)
     * @return bool True jika rotasi dilakukan
     */
    public function rotate(int $maxSizeBytes = 10485760): bool
    {
        if (!file_exists($this->logFile)) {
            return false;
        }
        
        $fileSize = filesize($this->logFile);
        if ($fileSize < $maxSizeBytes) {
            return false;
        }
        
        // Generate nama file backup dengan timestamp
        $backupFile = $this->logFile . '.' . date('Ymd_His');
        
        // Rename file lama
        rename($this->logFile, $backupFile);
        
        // Compress backup file (gzip)
        if (function_exists('gzcompress')) {
            $compressed = gzcompress(file_get_contents($backupFile), 9);
            file_put_contents($backupFile . '.gz', $compressed);
            unlink($backupFile);
        }
        
        return true;
    }
}
