<?php
/**
 * Auth.php - Middleware Autentikasi API Key
 * 
 * Memvalidasi API Key dari header request dan mapping ke role user.
 * Mendukung 3 role: guru, walikelas, kepsek
 */

class Auth
{
    private array $validKeys = [];
    
    /**
     * Constructor - Load API keys dari config
     */
    public function __construct()
    {
        $this->validKeys = [
            config('API_KEY_GURU') => 'guru',
            config('API_KEY_WALIKELAS') => 'walikelas',
            config('API_KEY_KEPSEK') => 'kepsek',
        ];
    }
    
    /**
     * Validasi API Key dari header Authorization
     * 
     * @return array ['valid' => bool, 'role' => string|null, 'message' => string]
     */
    public function validate(): array
    {
        // Ambil API Key dari header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $apiKey = '';
        
        // Support format: "Bearer <key>" atau langsung "<key>"
        if (strpos($authHeader, 'Bearer ') === 0) {
            $apiKey = substr($authHeader, 7);
        } else {
            $apiKey = $authHeader;
        }
        
        // Cek jika API key ada di config
        if (empty($apiKey)) {
            return [
                'valid' => false,
                'role' => null,
                'message' => 'API Key tidak ditemukan dalam header Authorization'
            ];
        }
        
        // Validasi key
        if (!isset($this->validKeys[$apiKey])) {
            return [
                'valid' => false,
                'role' => null,
                'message' => 'API Key tidak valid'
            ];
        }
        
        // Pastikan key tidak kosong
        if (empty($this->validKeys[$apiKey])) {
            return [
                'valid' => false,
                'role' => null,
                'message' => 'API Key belum dikonfigurasi di server'
            ];
        }
        
        return [
            'valid' => true,
            'role' => $this->validKeys[$apiKey],
            'message' => 'Autentikasi berhasil'
        ];
    }
    
    /**
     * Dapatkan role dari API Key
     * 
     * @param string $apiKey API Key untuk dicek
     * @return string|null Role user atau null jika tidak valid
     */
    public function getRole(string $apiKey): ?string
    {
        return $this->validKeys[$apiKey] ?? null;
    }
    
    /**
     * Cek apakah role memiliki akses tertentu
     * 
     * @param string $role Role user
     * @param string $permission Permission yang dibutuhkan
     * @return bool True jika memiliki akses
     */
    public function hasPermission(string $role, string $permission): bool
    {
        $permissions = [
            'guru' => ['read_own_class', 'write_own_class', 'read_students'],
            'walikelas' => ['read_own_class', 'write_own_class', 'read_students', 'read_reports'],
            'kepsek' => ['read_all', 'write_all', 'read_reports', 'export_data'],
        ];
        
        return in_array($permission, $permissions[$role] ?? []);
    }
}
