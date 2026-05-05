<?php
/**
 * IDataRepository.php - Interface Repository Pattern (FASE 1)
 * 
 * File ini TIDAK DIUBAH di Fase 2, hanya sebagai referensi.
 * Lihat dokumentasi Fase 1 untuk implementasi lengkap.
 */

interface IDataRepository
{
    /**
     * Simpan data asesmen
     */
    public function saveAssessment(array $data): bool;
    
    /**
     * Ambil data siswa berdasarkan kode
     */
    public function getStudentByCode(string $code): ?array;
    
    /**
     * Ambil semua siswa per kelas
     */
    public function getStudentsByClass(string $classId): array;
    
    /**
     * Ambil statistik kelas
     */
    public function getClassStatistics(string $classId): array;
}
