<?php
/**
 * ExportEngine.php - Generator Export Data untuk Rapor Pendidikan
 * 
 * Menghasilkan file CSV sesuai format Kemendikbud untuk Rapor Pendidikan.
 * Compatible dengan template export Kurikulum Merdeka.
 */

class ExportEngine
{
    /**
     * Header CSV standar untuk Rapor Pendidikan
     */
    private const CSV_HEADERS = [
        'Kode Siswa',
        'Nama Siswa',
        'Mata Pelajaran',
        'TP (Tujuan Pembelajaran)',
        'Nilai Akhir',
        'Predikat',
        'Deskripsi',
        'Tanggal Asesmen',
        'Kelas',
        'Semester',
        'Tahun Ajaran'
    ];
    
    /**
     * Generate CSV untuk Rapor Pendidikan
     * 
     * @param array $results Array hasil penilaian (dari StatEngine/FeedbackEngine)
     * @return string Content CSV yang siap di-download
     */
    public function generateRaporCSV(array $results): string
    {
        // Buka output buffer
        $output = fopen('php://temp', 'r+');
        
        // Tulis BOM untuk UTF-8 compatibility dengan Excel
        fwrite($output, "\xEF\xBB\xBF");
        
        // Tulis header
        fputcsv($output, self::CSV_HEADERS);
        
        // Proses setiap hasil
        foreach ($results as $result) {
            $row = $this->formatResultRow($result);
            fputcsv($output, $row);
        }
        
        // Dapatkan content
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        return $csvContent;
    }
    
    /**
     * Format result row untuk CSV
     * 
     * @param array $result Satu hasil penilaian
     * @return array Row data untuk CSV
     */
    private function formatResultRow(array $result): array
    {
        // Extract data dengan fallback nilai default
        $kodeSiswa = $result['student_code'] ?? $result['kode_siswa'] ?? 'N/A';
        $namaSiswa = $result['student_name'] ?? $result['nama_siswa'] ?? '';
        $mataPelajaran = $result['subject'] ?? $result['mapel'] ?? 'Umum';
        $tp = $result['learning_objective'] ?? $result['tp'] ?? $result['tujuan_pembelajaran'] ?? '-';
        $nilaiAkhir = $result['final_score'] ?? $result['nilai_akhir'] ?? $result['score'] ?? 0;
        $predikat = $result['predicate'] ?? $this->calculatePredicate($nilaiAkhir);
        $deskripsi = $result['description'] ?? $result['deskripsi'] ?? $this->generateDescription($nilaiAkhir, $predikat);
        $tanggalAsesmen = $result['assessment_date'] ?? $result['tanggal_asesmen'] ?? date('Y-m-d');
        $kelas = $result['class'] ?? $result['kelas'] ?? '-';
        $semester = $result['semester'] ?? $this->getCurrentSemester();
        $tahunAjaran = $result['academic_year'] ?? $result['tahun_ajaran'] ?? $this->getCurrentAcademicYear();
        
        // Format tanggal ke format Indonesia
        if (!empty($tanggalAsesmen)) {
            $tanggalAsesmen = date('d/m/Y', strtotime($tanggalAsesmen));
        }
        
        return [
            $kodeSiswa,
            $namaSiswa,
            $mataPelajaran,
            $tp,
            number_format($nilaiAkhir, 2, ',', '.'),
            $predikat,
            $deskripsi,
            $tanggalAsesmen,
            $kelas,
            $semester,
            $tahunAjaran
        ];
    }
    
    /**
     * Hitung predikat dari nilai (fallback jika tidak ada)
     * 
     * @param float $score Nilai siswa
     * @return string Predikat (PBS/BSH/BC/BB)
     */
    private function calculatePredicate(float $score): string
    {
        if ($score >= 86) return 'PBS'; // Perlu Bimbingan Spesifik (sebenarnya Unggul)
        if ($score >= 71) return 'BSH'; // Baik Sekali
        if ($score >= 56) return 'BC';  // Cukup
        return 'BB';                     // Perlu Bimbingan
    }
    
    /**
     * Generate deskripsi otomatis (fallback)
     * 
     * @param float $score Nilai siswa
     * @param string $predicate Predikat
     * @return string Deskripsi
     */
    private function generateDescription(float $score, string $predicate): string
    {
        $descriptions = [
            'PBS' => 'Peserta didik menunjukkan penguasaan kompetensi secara mendalam dan konsisten.',
            'BSH' => 'Peserta didik menunjukkan penguasaan kompetensi dengan baik.',
            'BC' => 'Peserta didik menunjukkan penguasaan kompetensi dasar namun perlu peningkatan.',
            'BB' => 'Peserta didik memerlukan bimbingan intensif untuk mencapai kompetensi minimum.',
        ];
        
        return $descriptions[$predicate] ?? $descriptions['BC'];
    }
    
    /**
     * Dapatkan semester saat ini
     * 
     * @return int Semester (1 atau 2)
     */
    private function getCurrentSemester(): int
    {
        $month = (int)date('n');
        // Semester 1: Juli-Desember, Semester 2: Januari-Juni
        return ($month >= 7 || $month <= 1) ? 1 : 2;
    }
    
    /**
     * Dapatkan tahun ajaran saat ini
     * Format: 2023/2024
     * 
     * @return string Tahun ajaran
     */
    private function getCurrentAcademicYear(): string
    {
        $year = (int)date('Y');
        $month = (int)date('n');
        
        // Tahun ajaran baru dimulai Juli
        $startYear = ($month >= 7) ? $year : $year - 1;
        $endYear = $startYear + 1;
        
        return "{$startYear}/{$endYear}";
    }
    
    /**
     * Generate CSV untuk statistik kelas
     * 
     * @param array $statistics Hasil statistik dari StatEngine
     * @param string $className Nama kelas
     * @return string Content CSV
     */
    public function generateClassStatisticsCSV(array $statistics, string $className): string
    {
        $output = fopen('php://temp', 'r+');
        
        // BOM UTF-8
        fwrite($output, "\xEF\xBB\xBF");
        
        // Header
        fputcsv($output, [
            'Statistik',
            'Nilai',
            'Keterangan'
        ]);
        
        // Data statistik
        $rows = [
            ['Jumlah Siswa', $statistics['count'] ?? 0, 'Total siswa dalam kelas'],
            ['Nilai Rata-rata', number_format($statistics['mean'] ?? 0, 2, ',', '.'), 'Mean score kelas'],
            ['Nilai Tertinggi', number_format($statistics['max'] ?? 0, 2, ',', '.'), 'Skor maksimal'],
            ['Nilai Terendah', number_format($statistics['min'] ?? 0, 2, ',', '.'), 'Skor minimal'],
            ['Median', number_format($statistics['median'] ?? 0, 2, ',', '.'), 'Nilai tengah'],
            ['Modus', number_format($statistics['mode'] ?? 0, 2, ',', '.'), 'Nilai paling sering muncul'],
            ['Simpangan Baku', number_format($statistics['std_deviation'] ?? 0, 2, ',', '.'), 'Sebaran nilai'],
            ['PBS (Unggul)', $statistics['distribution']['PBS'] ?? 0, 'Jumlah siswa predikat PBS'],
            ['BSH (Baik)', $statistics['distribution']['BSH'] ?? 0, 'Jumlah siswa predikat BSH'],
            ['BC (Cukup)', $statistics['distribution']['BC'] ?? 0, 'Jumlah siswa predikat BC'],
            ['BB (Perlu Bimbingan)', $statistics['distribution']['BB'] ?? 0, 'Jumlah siswa predikat BB'],
        ];
        
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        
        // Footer info
        fputcsv($output, []);
        fputcsv($output, ['Kelas', $className]);
        fputcsv($output, ['Tanggal Export', date('d/m/Y H:i')]);
        fputcsv($output, ['Tahun Ajaran', $this->getCurrentAcademicYear()]);
        
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        return $csvContent;
    }
    
    /**
     * Generate CSV untuk data siswa per kelas
     * 
     * @param array $students Array data siswa
     * @return string Content CSV
     */
    public function generateStudentListCSV(array $students): string
    {
        $output = fopen('php://temp', 'r+');
        
        fwrite($output, "\xEF\xBB\xBF");
        
        fputcsv($output, [
            'No',
            'NIS/NISN',
            'Nama Lengkap',
            'L/P',
            'Tanggal Lahir',
            'Kelas',
            'Status ABK',
            'Akomodasi'
        ]);
        
        $no = 1;
        foreach ($students as $student) {
            fputcsv($output, [
                $no++,
                $student['nisn'] ?? $student['nis'] ?? '-',
                $student['name'] ?? $student['nama'] ?? '-',
                $student['gender'] ?? $student['jenis_kelamin'] ?? '-',
                isset($student['birth_date']) ? date('d/m/Y', strtotime($student['birth_date'])) : '-',
                $student['class'] ?? $student['kelas'] ?? '-',
                !empty($student['special_needs']) ? 'Ya' : 'Tidak',
                !empty($student['accommodations']) ? implode(', ', $student['accommodations']) : '-'
            ]);
        }
        
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        return $csvContent;
    }
    
    /**
     * Download helper - set headers dan output CSV
     * 
     * @param string $content Content CSV
     * @param string $filename Nama file
     */
    public function downloadCSV(string $content, string $filename): void
    {
        // Set headers untuk download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output content
        echo $content;
        exit;
    }
    
    /**
     * Validasi data sebelum export
     * 
     * @param array $results Data hasil penilaian
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateData(array $results): array
    {
        $errors = [];
        
        if (empty($results)) {
            return [
                'valid' => false,
                'errors' => ['Data hasil penilaian kosong']
            ];
        }
        
        foreach ($results as $index => $result) {
            $prefix = "Baris " . ($index + 1);
            
            if (empty($result['student_code']) && empty($result['kode_siswa'])) {
                $errors[] = "{$prefix}: Kode siswa tidak ditemukan";
            }
            
            if (!isset($result['final_score']) && !isset($result['nilai_akhir']) && !isset($result['score'])) {
                $errors[] = "{$prefix}: Nilai akhir tidak ditemukan";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
