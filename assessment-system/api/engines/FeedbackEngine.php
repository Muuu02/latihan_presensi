<?php
/**
 * FeedbackEngine.php - AI Feedback Engine (FASE 1)
 * 
 * File ini TIDAK DIUBAH di Fase 2, hanya sebagai referensi.
 * Lihat dokumentasi Fase 1 untuk implementasi lengkap.
 */

class FeedbackEngine
{
    /**
     * Generate feedback berdasarkan nilai dan konteks
     */
    public function generateFeedback(array $assessmentData): array
    {
        $score = $assessmentData['score'] ?? 0;
        $predicate = $this->determinePredicate($score);
        $description = $this->generateDescription($score, $predicate);
        
        return [
            'score' => $score,
            'predicate' => $predicate,
            'description' => $description,
            'recommendation' => $this->generateRecommendation($score, $predicate)
        ];
    }
    
    /**
     * Tentukan predikat dari nilai
     */
    private function determinePredicate(float $score): string
    {
        if ($score >= 86) return 'PBS';
        if ($score >= 71) return 'BSH';
        if ($score >= 56) return 'BC';
        return 'BB';
    }
    
    /**
     * Generate deskripsi otomatis
     */
    private function generateDescription(float $score, string $predicate): string
    {
        $descriptions = [
            'PBS' => 'Peserta didik menunjukkan pemahaman mendalam dan mampu menerapkan konsep dalam berbagai konteks.',
            'BSH' => 'Peserta didik menunjukkan pemahaman yang baik dan mampu menerapkan konsep dalam sebagian besar konteks.',
            'BC' => 'Peserta didik menunjukkan pemahaman dasar namun masih memerlukan bimbingan.',
            'BB' => 'Peserta didik memerlukan pendampingan intensif untuk mencapai kompetensi dasar.'
        ];
        
        return $descriptions[$predicate] ?? $descriptions['BC'];
    }
    
    /**
     * Generate rekomendasi tindak lanjut
     */
    private function generateRecommendation(float $score, string $predicate): string
    {
        $recommendations = [
            'PBS' => 'Pertahankan prestasi dan tingkatkan dengan tantangan yang lebih kompleks.',
            'BSH' => 'Tingkatkan lagi dengan memperdalam pemahaman konsep.',
            'BC' => 'Perlu latihan tambahan pada konsep yang belum dikuasai.',
            'BB' => 'Diperlukan remedial dan pendampingan intensif.'
        ];
        
        return $recommendations[$predicate] ?? $recommendations['BC'];
    }
}
