<?php
/**
 * StatEngine.php - Statistical Engine (FASE 1)
 * 
 * File ini TIDAK DIUBAH di Fase 2, hanya sebagai referensi.
 * Lihat dokumentasi Fase 1 untuk implementasi lengkap.
 */

class StatEngine
{
    /**
     * Hitung mean dari array nilai
     */
    public function calculateMean(array $scores): float
    {
        if (empty($scores)) return 0;
        return array_sum($scores) / count($scores);
    }
    
    /**
     * Hitung median dari array nilai
     */
    public function calculateMedian(array $scores): float
    {
        if (empty($scores)) return 0;
        sort($scores);
        $count = count($scores);
        $middle = intdiv($count, 2);
        
        return $count % 2 === 0 
            ? ($scores[$middle - 1] + $scores[$middle]) / 2 
            : $scores[$middle];
    }
    
    /**
     * Hitung distribusi predikat
     */
    public function calculateDistribution(array $results): array
    {
        $distribution = ['PBS' => 0, 'BSH' => 0, 'BC' => 0, 'BB' => 0];
        
        foreach ($results as $result) {
            $predicate = $result['predicate'] ?? 'BB';
            if (isset($distribution[$predicate])) {
                $distribution[$predicate]++;
            }
        }
        
        return $distribution;
    }
}
