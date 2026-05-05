<?php
/**
 * AccommodationEngine.php - Engine Akomodasi ABK (Anak Berkebutuhan Khusus)
 * 
 * Menyesuaikan threshold penilaian dan menghasilkan prompt khusus untuk AI
 * berdasarkan flag akomodasi yang diberikan.
 * 
 * Sesuai prinsip Kurikulum Merdeka: pembelajaran berdiferensiasi
 */

class AccommodationEngine
{
    /**
     * Threshold default Kurikulum Merdeka (tanpa akomodasi)
     * Format: [min_score, predicate, description_template]
     */
    private const DEFAULT_THRESHOLDS = [
        ['min' => 86, 'predicate' => 'PBS', 'desc' => 'Peserta didik menunjukkan pemahaman mendalam dan mampu menerapkan konsep dalam berbagai konteks kompleks.'],
        ['min' => 71, 'predicate' => 'BSH', 'desc' => 'Peserta didik menunjukkan pemahaman yang baik dan mampu menerapkan konsep dalam sebagian besar konteks.'],
        ['min' => 56, 'predicate' => 'BC', 'desc' => 'Peserta didik menunjukkan pemahaman dasar namun masih memerlukan bimbingan dalam penerapan konsep.'],
        ['min' => 0, 'predicate' => 'BB', 'desc' => 'Peserta didik memerlukan pendampingan intensif untuk mencapai kompetensi dasar.'],
    ];
    
    /**
     * Flag akomodasi yang didukung
     */
    private const ACCOMMODATION_FLAGS = [
        'waktu_tambah' => 'Pemberian waktu tambahan',
        'modifikasi_instrumen' => 'Modifikasi instrumen asesmen',
        'pendampingan' => 'Pendampingan selama asesmen',
        'ruang_terpisah' => 'Ruang ujian terpisah',
        'format_besar' => 'Format soal diperbesar',
        'pembaca_soal' => 'Pembacaan soal oleh guru',
        'alat_bantu' => 'Penggunaan alat bantu khusus',
        'pengurangan_soal' => 'Pengurangan jumlah soal',
    ];
    
    /**
     * Sesuaikan threshold berdasarkan flag akomodasi
     * 
     * @param float $rawScore Nilai mentah siswa
     * @param array $flags Array flag akomodasi (misal: ['waktu_tambah', 'pendampingan'])
     * @return array ['score' => float, 'predicate' => string, 'description' => string, 'adjusted' => bool]
     */
    public function adjustDescriptor(float $rawScore, array $flags = []): array
    {
        // Dapatkan threshold yang disesuaikan
        $thresholds = $this->getAdjustedThresholds($flags);
        
        // Tentukan predikat berdasarkan score
        $predicate = 'BB';
        $description = self::DEFAULT_THRESHOLDS[3]['desc'];
        
        foreach ($thresholds as $threshold) {
            if ($rawScore >= $threshold['min']) {
                $predicate = $threshold['predicate'];
                $description = $threshold['desc'];
                break;
            }
        }
        
        // Cek apakah ada penyesuaian
        $adjusted = !empty($flags);
        
        // Jika ada akomodasi, tambahkan catatan di deskripsi
        if ($adjusted && !empty($flags)) {
            $accommodationNote = $this->buildAccommodationNote($flags);
            $description .= ' (' . $accommodationNote . ')';
        }
        
        return [
            'score' => round($rawScore, 2),
            'predicate' => $predicate,
            'description' => $description,
            'adjusted' => $adjusted,
            'flags' => $flags,
        ];
    }
    
    /**
     * Dapatkan threshold yang disesuaikan berdasarkan flag
     * 
     * @param array $flags Array flag akomodasi
     * @return array Threshold yang sudah disesuaikan
     */
    private function getAdjustedThresholds(array $flags): array
    {
        // Mulai dari threshold default
        $thresholds = self::DEFAULT_THRESHOLDS;
        
        // Penyesuaian untuk waktu tambah (+5 poin toleransi)
        if (in_array('waktu_tambah', $flags)) {
            $thresholds = [
                ['min' => 81, 'predicate' => 'PBS', 'desc' => 'Peserta didik menunjukkan pemahaman mendalam dengan akomodasi waktu tambahan.'],
                ['min' => 66, 'predicate' => 'BSH', 'desc' => 'Peserta didik menunjukkan pemahaman yang baik dengan akomodasi waktu tambahan.'],
                ['min' => 51, 'predicate' => 'BC', 'desc' => 'Peserta didik menunjukkan pemahaman dasar dengan akomodasi waktu tambahan.'],
                ['min' => 0, 'predicate' => 'BB', 'desc' => 'Peserta didik memerlukan pendampingan intensif meskipun dengan waktu tambahan.'],
            ];
        }
        
        // Penyesuaian untuk modifikasi instrumen (lebih fleksibel)
        if (in_array('modifikasi_instrumen', $flags)) {
            $thresholds = [
                ['min' => 80, 'predicate' => 'PBS', 'desc' => 'Peserta didik mencapai tujuan pembelajaran dengan instrumen termodifikasi.'],
                ['min' => 65, 'predicate' => 'BSH', 'desc' => 'Peserta didik menunjukkan kemajuan baik dengan instrumen termodifikasi.'],
                ['min' => 50, 'predicate' => 'BC', 'desc' => 'Peserta didik menunjukkan perkembangan dengan instrumen termodifikasi.'],
                ['min' => 0, 'predicate' => 'BB', 'desc' => 'Peserta didik memerlukan modifikasi lebih lanjut pada instrumen.'],
            ];
        }
        
        // Penyesuaian untuk pengurangan soal (normalisasi score)
        if (in_array('pengurangan_soal', $flags)) {
            // Score sudah dinormalisasi, tidak perlu adjust threshold
            // Tapi berikan deskripsi khusus
            foreach ($thresholds as &$t) {
                $t['desc'] = str_replace(
                    ['Peserta didik', 'menunjukkan'],
                    ['Peserta didik (dengan penyesuaian beban)', 'menunjukkan'],
                    $t['desc']
                );
            }
        }
        
        return $thresholds;
    }
    
    /**
     * Build prompt khusus untuk AI berdasarkan flag ABK
     * 
     * @param array $flags Array flag akomodasi
     * @return string Prompt instruksi untuk AI Feedback Engine
     */
    public function buildABKPrompt(array $flags): string
    {
        if (empty($flags)) {
            return '';
        }
        
        $promptParts = [
            "CONTEKS AKOMODASI:",
            "Siswa ini menerima akomodasi berikut sesuai kebutuhan khusus:"
        ];
        
        foreach ($flags as $flag) {
            if (isset(self::ACCOMMODATION_FLAGS[$flag])) {
                $promptParts[] = "- " . self::ACCOMMODATION_FLAGS[$flag];
            }
        }
        
        $promptParts[] = "";
        $promptParts[] = "INSTRUKSI KHUSUS UNTUK AI:";
        $promptParts[] = "1. Berikan feedback yang mempertimbangkan akomodasi yang diberikan";
        $promptParts[] = "2. Fokus pada progres individu, bukan perbandingan dengan standar umum";
        $promptParts[] = "3. Gunakan bahasa yang positif dan mendukung";
        $promptParts[] = "4. Sertakan saran konkret untuk tindak lanjut yang sesuai dengan kebutuhan siswa";
        
        if (in_array('waktu_tambah', $flags)) {
            $promptParts[] = "5. Perhatikan bahwa siswa memerlukan waktu pemrosesan lebih lama";
        }
        
        if (in_array('modifikasi_instrumen', $flags)) {
            $promptParts[] = "5. Pertimbangkan bahwa instrumen telah dimodifikasi sesuai kemampuan siswa";
        }
        
        if (in_array('pendampingan', $flags)) {
            $promptParts[] = "5. Siswa memerlukan scaffolding selama pembelajaran";
        }
        
        $promptParts[] = "";
        $promptParts[] = "FORMAT FEEDBACK:";
        $promptParts[] = "- Gunakan kalimat sederhana dan jelas";
        $promptParts[] = "- Hindari istilah teknis yang kompleks tanpa penjelasan";
        $promptParts[] = "- Berikan contoh konkret dalam kehidupan sehari-hari";
        
        return implode(PHP_EOL, $promptParts);
    }
    
    /**
     * Bangun catatan akomodasi untuk deskripsi
     * 
     * @param array $flags Array flag akomodasi
     * @return string Catatan singkat
     */
    private function buildAccommodationNote(array $flags): string
    {
        $notes = [];
        
        foreach ($flags as $flag) {
            if (isset(self::ACCOMMODATION_FLAGS[$flag])) {
                // Ambil kata kunci saja
                $shortNote = str_replace('_', ' ', $flag);
                $notes[] = ucfirst($shortNote);
            }
        }
        
        return 'Akomodasi: ' . implode(', ', $notes);
    }
    
    /**
     * Validasi flag akomodasi
     * 
     * @param array $flags Array flag untuk divalidasi
     * @return array ['valid' => array, 'invalid' => array]
     */
    public function validateFlags(array $flags): array
    {
        $valid = [];
        $invalid = [];
        
        foreach ($flags as $flag) {
            if (isset(self::ACCOMMODATION_FLAGS[$flag])) {
                $valid[] = $flag;
            } else {
                $invalid[] = $flag;
            }
        }
        
        return [
            'valid' => $valid,
            'invalid' => $invalid,
        ];
    }
    
    /**
     * Dapatkan daftar semua flag akomodasi yang tersedia
     * 
     * @return array Associative array [flag_key => description]
     */
    public function getAvailableFlags(): array
    {
        return self::ACCOMMODATION_FLAGS;
    }
    
    /**
     * Rekomendasikan akomodasi berdasarkan profil siswa
     * 
     * @param array $studentProfile Profil siswa (jenis_kebutuhan, tingkat, dll)
     * @return array Array flag akomodasi yang direkomendasikan
     */
    public function recommendAccommodations(array $studentProfile): array
    {
        $recommendations = [];
        
        $jenisKebutuhan = $studentProfile['jenis_kebutuhan'] ?? '';
        $tingkat = $studentProfile['tingkat'] ?? 'ringan';
        
        // Rekomendasi berdasarkan jenis kebutuhan
        switch ($jenisKebutuhan) {
            case 'disleksia':
                $recommendations[] = 'waktu_tambah';
                $recommendations[] = 'pembaca_soal';
                if ($tingkat === 'sedang' || $tingkat === 'berat') {
                    $recommendations[] = 'modifikasi_instrumen';
                }
                break;
                
            case 'adhd':
                $recommendations[] = 'waktu_tambah';
                $recommendations[] = 'ruang_terpisah';
                $recommendations[] = 'pengurangan_soal';
                break;
                
            case 'tunanetra':
                $recommendations[] = 'format_besar';
                $recommendations[] = 'pembaca_soal';
                $recommendations[] = 'waktu_tambah';
                $recommendations[] = 'alat_bantu';
                break;
                
            case 'autisme':
                $recommendations[] = 'ruang_terpisah';
                $recommendations[] = 'pendampingan';
                if ($tingkat === 'sedang' || $tingkat === 'berat') {
                    $recommendations[] = 'modifikasi_instrumen';
                }
                break;
                
            case 'tunarungu':
                $recommendations[] = 'format_besar';
                $recommendations[] = 'pendampingan';
                $recommendations[] = 'alat_bantu';
                break;
                
            default:
                // Kebutuhan umum
                if ($tingkat === 'sedang' || $tingkat === 'berat') {
                    $recommendations[] = 'waktu_tambah';
                    $recommendations[] = 'pendampingan';
                }
        }
        
        return array_unique($recommendations);
    }
}
