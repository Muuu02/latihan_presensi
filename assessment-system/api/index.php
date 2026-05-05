<?php
/**
 * api/index.php - Router Utama API Assessment System
 * 
 * FASE 2: Menambahkan endpoint baru dengan tetap menjaga kompatibilitas Fase 1
 * Endpoint baru: GET /v1/export/rapor, GET /v1/profile/{student_code}
 * 
 * Semua response dalam format JSON: { "status": "success"|"error", "data": ... }
 */

// Define constant untuk mencegah akses langsung
define('ASSESSMENT_SYSTEM', true);

// Load konfigurasi
require_once __DIR__ . '/../config.php';

// Load middleware
require_once __DIR__ . '/middleware/Auth.php';
require_once __DIR__ . '/middleware/RBAC.php';
require_once __DIR__ . '/middleware/RateLimit.php';
require_once __DIR__ . '/middleware/AuditLog.php';

// Load engines (Fase 1 + Fase 2)
require_once __DIR__ . '/engines/AccommodationEngine.php';
require_once __DIR__ . '/engines/ExportEngine.php';

// Note: StatEngine, FeedbackEngine, Repositories dari Fase 1 tidak diubah
// require_once __DIR__ . '/engines/StatEngine.php';
// require_once __DIR__ . '/engines/FeedbackEngine.php';
// require_once __DIR__ . '/repositories/IDataRepository.php';
// require_once __DIR__ . '/repositories/MySQLRepository.php';

/**
 * Helper function untuk send JSON response
 */
function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Helper function untuk error response
 */
function errorResponse(string $message, int $statusCode = 400): void
{
    jsonResponse([
        'status' => 'error',
        'message' => $message,
        'code' => $statusCode
    ], $statusCode);
}

/**
 * Helper function untuk success response
 */
function successResponse($data, string $message = 'Berhasil'): void
{
    jsonResponse([
        'status' => 'success',
        'message' => $message,
        'data' => $data
    ], 200);
}

// Inisialisasi middleware
$auth = new Auth();
$auditLog = new AuditLog();
$rateLimiter = new RateLimit();

// Dapatkan IP client untuk rate limiting
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Cek rate limit
$rateLimitResult = $rateLimiter->check($clientIp);
if (!$rateLimitResult['allowed']) {
    $auditLog->logRequest($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'], 429);
    errorResponse('Terlalu banyak request. Silakan tunggu beberapa saat.', 429);
}

// Validasi autentikasi
$authResult = $auth->validate();
if (!$authResult['valid']) {
    $auditLog->logAuth(false, $authResult['message']);
    errorResponse($authResult['message'], 401);
}

// Autentikasi berhasil
$userRole = $authResult['role'];
$rbac = new RBAC($userRole);
$auditLog->logAuth(true, null, $userRole);

// Parse request URI
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestPath = str_replace('/api', '', $requestUri);

// Catat request awal
$auditLog->logRequest($requestPath, $requestMethod, 200, null, $userRole);

// Routing sederhana
try {
    // Route matching
    switch (true) {
        // ==========================================
        // ENDPOINT FASE 1 (Kompatibilitas)
        // ==========================================
        
        // POST /v1/assessments - Input nilai asesmen
        case ($requestPath === '/v1/assessments' && $requestMethod === 'POST'):
            $rbacCheck = $rbac->checkAccess('/v1/assessments', $_POST);
            if (!$rbacCheck['allowed']) {
                errorResponse($rbacCheck['message'], 403);
            }
            // TODO: Implementasi dari Fase 1
            successResponse([], 'Endpoint assessments (Fase 1)');
            break;
        
        // GET /v1/students - Daftar siswa
        case ($requestPath === '/v1/students' && $requestMethod === 'GET'):
            $rbacCheck = $rbac->checkAccess('/v1/students');
            if (!$rbacCheck['allowed']) {
                errorResponse($rbacCheck['message'], 403);
            }
            // TODO: Implementasi dari Fase 1
            successResponse([], 'Endpoint students (Fase 1)');
            break;
        
        // POST /v1/feedback - Generate feedback AI
        case ($requestPath === '/v1/feedback' && $requestMethod === 'POST'):
            $rbacCheck = $rbac->checkAccess('/v1/feedback');
            if (!$rbacCheck['allowed']) {
                errorResponse($rbacCheck['message'], 403);
            }
            // TODO: Implementasi dari Fase 1
            successResponse([], 'Endpoint feedback (Fase 1)');
            break;
        
        // GET /v1/statistics - Statistik kelas
        case ($requestPath === '/v1/statistics' && $requestMethod === 'GET'):
            $rbacCheck = $rbac->checkAccess('/v1/statistics');
            if (!$rbacCheck['allowed']) {
                errorResponse($rbacCheck['message'], 403);
            }
            // TODO: Implementasi dari Fase 1
            successResponse([], 'Endpoint statistics (Fase 1)');
            break;
        
        // ==========================================
        // ENDPOINT FASE 2 (Baru)
        // ==========================================
        
        // GET /v1/export/rapor - Export Rapor Pendidikan CSV
        case (preg_match('#^/v1/export/rapor$#', $requestPath) && $requestMethod === 'GET'):
            $rbacCheck = $rbac->checkAccess('/v1/export');
            if (!$rbacCheck['allowed']) {
                errorResponse($rbacCheck['message'], 403);
            }
            
            // Ambil parameter query
            $classId = $_GET['class_id'] ?? null;
            $subject = $_GET['subject'] ?? null;
            $semester = $_GET['semester'] ?? null;
            
            // Validasi parameter
            if (!$classId && $userRole !== 'kepsek') {
                errorResponse('Parameter class_id diperlukan', 400);
            }
            
            // TODO: Fetch data dari repository
            // Untuk demo, gunakan data dummy
            $dummyResults = [
                [
                    'student_code' => 'SIS001',
                    'student_name' => 'Ahmad Rizki',
                    'subject' => $subject ?? 'Matematika',
                    'tp' => 'Memahami operasi hitung dasar',
                    'final_score' => 85.5,
                    'predicate' => 'BSH',
                    'description' => 'Peserta didik menunjukkan pemahaman yang baik.',
                    'assessment_date' => date('Y-m-d'),
                    'class' => '4A',
                    'semester' => $semester ?? 1,
                    'tahun_ajaran' => '2024/2025'
                ],
                [
                    'student_code' => 'SIS002',
                    'student_name' => 'Budi Santoso',
                    'subject' => $subject ?? 'Matematika',
                    'tp' => 'Memahami operasi hitung dasar',
                    'final_score' => 72.0,
                    'predicate' => 'BSH',
                    'description' => 'Peserta didik menunjukkan kemajuan baik.',
                    'assessment_date' => date('Y-m-d'),
                    'class' => '4A',
                    'semester' => $semester ?? 1,
                    'tahun_ajaran' => '2024/2025'
                ]
            ];
            
            // Generate CSV
            $exportEngine = new ExportEngine();
            $csvContent = $exportEngine->generateRaporCSV($dummyResults);
            
            // Log export
            $auditLog->logExport('csv', count($dummyResults));
            
            // Download
            $filename = 'rapor_pendidikan_' . date('Ymd_His') . '.csv';
            $exportEngine->downloadCSV($csvContent, $filename);
            break;
        
        // GET /v1/profile/{student_code} - Profil lengkap siswa
        case (preg_match('#^/v1/profile/([^/]+)$#', $requestPath, $matches) && $requestMethod === 'GET'):
            $rbacCheck = $rbac->checkAccess('/v1/profile');
            if (!$rbacCheck['allowed']) {
                errorResponse($rbacCheck['message'], 403);
            }
            
            $studentCode = $matches[1];
            
            // Validasi student_code
            if (empty($studentCode)) {
                errorResponse('Kode siswa tidak valid', 400);
            }
            
            // Log akses data sensitif (UU PDP compliance)
            $auditLog->logDataAccess('student_profile', 'read', $studentCode);
            
            // TODO: Fetch dari repository
            // Data dummy untuk demo
            $profileData = [
                'student_code' => $studentCode,
                'nama' => 'Contoh Siswa',
                'nisn' => '0012345678',
                'kelas' => '4A',
                'tanggal_lahir' => '2015-05-15',
                'jenis_kelamin' => 'L',
                'special_needs' => false,
                'accommodations' => [],
                'assessment_history' => [
                    [
                        'date' => '2024-01-15',
                        'subject' => 'Matematika',
                        'score' => 85,
                        'predicate' => 'BSH'
                    ],
                    [
                        'date' => '2024-01-20',
                        'subject' => 'Bahasa Indonesia',
                        'score' => 78,
                        'predicate' => 'BSH'
                    ]
                ],
                'statistics' => [
                    'average_score' => 81.5,
                    'total_assessments' => 2,
                    'trend' => 'stable'
                ]
            ];
            
            successResponse($profileData, 'Profil siswa ditemukan');
            break;
        
        // GET /v1/accommodations - Daftar akomodasi tersedia
        case ($requestPath === '/v1/accommodations' && $requestMethod === 'GET'):
            $accommodationEngine = new AccommodationEngine();
            $flags = $accommodationEngine->getAvailableFlags();
            successResponse($flags, 'Daftar akomodasi tersedia');
            break;
        
        // POST /v1/accommodations/recommend - Rekomendasi akomodasi
        case ($requestPath === '/v1/accommodations/recommend' && $requestMethod === 'POST'):
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['student_profile'])) {
                errorResponse('student_profile diperlukan', 400);
            }
            
            $accommodationEngine = new AccommodationEngine();
            $recommendations = $accommodationEngine->recommendAccommodations($input['student_profile']);
            
            successResponse([
                'recommendations' => $recommendations,
                'details' => array_map(function($flag) use ($accommodationEngine) {
                    $flags = $accommodationEngine->getAvailableFlags();
                    return $flags[$flag] ?? $flag;
                }, $recommendations)
            ], 'Rekomendasi akomodasi generated');
            break;
        
        // GET /v1/audit-logs - Lihat audit log (hanya kepsek)
        case ($requestPath === '/v1/audit-logs' && $requestMethod === 'GET'):
            if ($userRole !== 'kepsek') {
                errorResponse('Hanya kepala sekolah yang dapat mengakses audit log', 403);
            }
            
            $limit = min((int)($_GET['limit'] ?? 100), 500);
            $offset = (int)($_GET['offset'] ?? 0);
            
            $logs = $auditLog->readLogs($limit, $offset);
            
            successResponse([
                'logs' => $logs,
                'total' => count($logs),
                'limit' => $limit,
                'offset' => $offset
            ], 'Audit logs retrieved');
            break;
        
        default:
            errorResponse('Endpoint tidak ditemukan', 404);
    }
    
} catch (Exception $e) {
    // Log error
    error_log("API Error: " . $e->getMessage());
    
    // Response error aman (tidak leak detail di production)
    if (isDebugMode()) {
        errorResponse('Error: ' . $e->getMessage(), 500);
    } else {
        errorResponse('Terjadi kesalahan pada server', 500);
    }
}
