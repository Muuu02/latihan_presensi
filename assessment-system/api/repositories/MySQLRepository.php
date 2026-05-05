<?php
/**
 * MySQLRepository.php - Implementasi Repository Pattern (FASE 1)
 * 
 * File ini TIDAK DIUBAH di Fase 2, hanya sebagai referensi.
 * Lihat dokumentasi Fase 1 untuk implementasi lengkap.
 */

require_once __DIR__ . '/IDataRepository.php';

class MySQLRepository implements IDataRepository
{
    private ?PDO $db;
    
    public function __construct()
    {
        $this->db = getDbConnection();
    }
    
    public function saveAssessment(array $data): bool
    {
        // Implementasi dari Fase 1
        return true;
    }
    
    public function getStudentByCode(string $code): ?array
    {
        // Implementasi dari Fase 1
        return null;
    }
    
    public function getStudentsByClass(string $classId): array
    {
        // Implementasi dari Fase 1
        return [];
    }
    
    public function getClassStatistics(string $classId): array
    {
        // Implementasi dari Fase 1
        return [];
    }
}
