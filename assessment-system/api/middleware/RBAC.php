<?php
/**
 * RBAC.php - Role-Based Access Control Middleware
 * 
 * Mengontrol akses berdasarkan role user dan resource yang diminta.
 * Guru hanya bisa akses kelasnya sendiri, Kepsek bisa akses semua.
 */

class RBAC
{
    private string $userRole;
    
    /**
     * Constructor dengan role user
     * 
     * @param string $role Role user (guru/walikelas/kepsek)
     */
    public function __construct(string $role)
    {
        $this->userRole = $role;
    }
    
    /**
     * Cek akses ke endpoint tertentu
     * 
     * @param string $endpoint Endpoint yang diakses
     * @param array $context Konteks tambahan (kelas_id, student_code, dll)
     * @return array ['allowed' => bool, 'message' => string]
     */
    public function checkAccess(string $endpoint, array $context = []): array
    {
        // Kepsek punya akses penuh
        if ($this->userRole === 'kepsek') {
            return [
                'allowed' => true,
                'message' => 'Akses diberikan (role: kepsek)'
            ];
        }
        
        // Mapping endpoint ke permission
        $permissionMap = [
            '/v1/assessments' => 'write_own_class',
            '/v1/students' => 'read_students',
            '/v1/classes' => 'read_own_class',
            '/v1/feedback' => 'read_own_class',
            '/v1/export' => 'read_reports',
            '/v1/profile' => 'read_students',
            '/v1/statistics' => 'read_own_class',
        ];
        
        // Tentukan permission yang dibutuhkan
        $requiredPermission = null;
        foreach ($permissionMap as $path => $permission) {
            if (strpos($endpoint, $path) === 0) {
                $requiredPermission = $permission;
                break;
            }
        }
        
        if ($requiredPermission === null) {
            return [
                'allowed' => false,
                'message' => 'Endpoint tidak dikenali'
            ];
        }
        
        // Validasi permission berdasarkan role
        $permissions = [
            'guru' => [
                'read_own_class' => true,
                'write_own_class' => true,
                'read_students' => true,
                'read_reports' => false,
            ],
            'walikelas' => [
                'read_own_class' => true,
                'write_own_class' => true,
                'read_students' => true,
                'read_reports' => true,
            ],
        ];
        
        $hasPermission = $permissions[$this->userRole][$requiredPermission] ?? false;
        
        if (!$hasPermission) {
            return [
                'allowed' => false,
                'message' => "Role '{$this->userRole}' tidak memiliki izin untuk mengakses endpoint ini"
            ];
        }
        
        // Validasi konteks khusus (misal: guru hanya bisa akses kelasnya sendiri)
        if (isset($context['class_id']) && $this->userRole === 'guru') {
            // Di implementasi nyata, cek class_id dengan database mapping guru-kelas
            // Untuk sekarang, kita asumsikan valid jika ada class_id
            if (empty($context['class_id'])) {
                return [
                    'allowed' => false,
                    'message' => 'Guru harus menyertakan class_id'
                ];
            }
        }
        
        return [
            'allowed' => true,
            'message' => 'Akses diberikan'
        ];
    }
    
    /**
     * Filter data berdasarkan role
     * Contoh: Guru hanya lihat siswa di kelasnya
     * 
     * @param array $data Data mentah dari database
     * @param array $context Konteks filter
     * @return array Data yang sudah difilter
     */
    public function filterData(array $data, array $context = []): array
    {
        if ($this->userRole === 'kepsek') {
            // Kepsek lihat semua data
            return $data;
        }
        
        // Guru/Walikelas: filter berdasarkan class_id jika tersedia
        if (isset($context['class_id']) && !empty($context['class_id'])) {
            return array_filter($data, function($item) use ($context) {
                return isset($item['class_id']) && 
                       $item['class_id'] == $context['class_id'];
            });
        }
        
        // Jika tidak ada context class_id, kembalikan data kosong untuk keamanan
        if ($this->userRole === 'guru') {
            return [];
        }
        
        return $data;
    }
    
    /**
     * Dapatkan role saat ini
     * 
     * @return string Role user
     */
    public function getRole(): string
    {
        return $this->userRole;
    }
}
