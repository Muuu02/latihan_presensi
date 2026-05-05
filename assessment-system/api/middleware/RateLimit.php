<?php
/**
 * RateLimit.php - File-based Rate Limiter
 * 
 * Membatasi jumlah request per IP dalam jendela waktu tertentu.
 * Menggunakan file storage untuk kompatibilitas shared hosting.
 * Format file: ip_timestamp_count
 */

class RateLimit
{
    private string $storageDir;
    private int $maxRequests;
    private int $windowSeconds;
    
    /**
     * Constructor
     * 
     * @param string|null $storageDir Direktori penyimpanan (default: logs/ratelimit)
     */
    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?? BASE_PATH . '/logs/ratelimit';
        $this->maxRequests = (int)config('RATE_LIMIT_REQUESTS', 30);
        $this->windowSeconds = (int)config('RATE_LIMIT_WINDOW', 60);
        
        // Pastikan direktori ada
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }
    
    /**
     * Cek apakah IP masih dalam batas rate limit
     * 
     * @param string $ip Alamat IP client
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => int]
     */
    public function check(string $ip): array
    {
        $file = $this->getStorageFile($ip);
        $now = time();
        $windowStart = $now - $this->windowSeconds;
        
        // Baca data existing
        $data = $this->readFile($file);
        
        // Filter request dalam window waktu
        $validRequests = array_filter($data, function($timestamp) use ($windowStart) {
            return $timestamp >= $windowStart;
        });
        
        $requestCount = count($validRequests);
        $remaining = max(0, $this->maxRequests - $requestCount);
        
        // Hitung waktu reset (end of current window)
        $oldestRequest = !empty($validRequests) ? min($validRequests) : $now;
        $resetTime = $oldestRequest + $this->windowSeconds;
        
        // Cek jika melebihi limit
        if ($requestCount >= $this->maxRequests) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset' => $resetTime
            ];
        }
        
        // Catat request baru
        $validRequests[] = $now;
        $this->writeFile($file, $validRequests);
        
        return [
            'allowed' => true,
            'remaining' => $remaining - 1,
            'reset' => $resetTime
        ];
    }
    
    /**
     * Dapatkan nama file penyimpanan untuk IP
     * 
     * @param string $ip Alamat IP
     * @return string Path file lengkap
     */
    private function getStorageFile(string $ip): string
    {
        // Sanitasi IP untuk nama file yang aman
        $safeIp = preg_replace('/[^0-9a-fA-F.:]/', '_', $ip);
        return $this->storageDir . '/' . $safeIp . '.dat';
    }
    
    /**
     * Baca data dari file storage
     * 
     * @param string $file Path file
     * @return array Array timestamp request
     */
    private function readFile(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }
        
        $content = file_get_contents($file);
        if ($content === false) {
            return [];
        }
        
        $lines = explode("\n", trim($content));
        $timestamps = [];
        
        foreach ($lines as $line) {
            $timestamp = (int)trim($line);
            if ($timestamp > 0) {
                $timestamps[] = $timestamp;
            }
        }
        
        return $timestamps;
    }
    
    /**
     * Tulis data ke file storage
     * 
     * @param string $file Path file
     * @param array $timestamps Array timestamp
     */
    private function writeFile(string $file, array $timestamps): void
    {
        $content = implode("\n", $timestamps);
        file_put_contents($file, $content, LOCK_EX);
    }
    
    /**
     * Reset rate limit untuk IP tertentu
     * 
     * @param string $ip Alamat IP
     * @return bool True jika berhasil
     */
    public function reset(string $ip): bool
    {
        $file = $this->getStorageFile($ip);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }
    
    /**
     * Bersihkan file lama (cleanup)
     * Hapus file yang sudah tidak ada request dalam 24 jam
     */
    public function cleanup(): void
    {
        $files = glob($this->storageDir . '/*.dat');
        $now = time();
        $dayAgo = $now - 86400; // 24 jam
        
        foreach ($files as $file) {
            $data = $this->readFile($file);
            $recentRequests = array_filter($data, function($ts) use ($dayAgo) {
                return $ts > $dayAgo;
            });
            
            if (empty($recentRequests)) {
                unlink($file);
            }
        }
    }
}
