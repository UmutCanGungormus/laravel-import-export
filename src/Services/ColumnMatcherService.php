<?php

namespace Umutcangungormus\LaravelImportExport\Services;

use Umutcangungormus\LaravelImportExport\Contracts\ColumnMatcherContract;
use Umutcangungormus\LaravelImportExport\Enums\MatchMethod;

/**
 * Matches file headers to importable model fields using multiple strategies:
 *  1. Exact key match       → 1.0
 *  2. Label exact match     → 0.95
 *  3. Alias exact match     → 0.9
 *  4. Fuzzy (Levenshtein + similar_text + word overlap) → 0.3 – 0.89
 */
class ColumnMatcherService implements ColumnMatcherContract
{
    public function match(array $headers, array $importableFields): array
    {
        $results = [];

        foreach ($headers as $header) {
            $results[] = $this->findBestMatch($header, $importableFields);
        }

        return $results;
    }

    public function suggest(string $sourceColumn, array $importableFields): array
    {
        $suggestions = [];

        foreach ($importableFields as $fieldKey => $fieldConfig) {
            $score = $this->scoreCandidate($sourceColumn, $fieldKey, $fieldConfig);

            if ($score >= config('import-export.column_matching.suggestion_threshold', 0.3)) {
                $suggestions[] = [
                    'field' => $fieldKey,
                    'label' => $fieldConfig['label'] ?? $fieldKey,
                    'confidence' => $score,
                ];
            }
        }

        usort($suggestions, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return $suggestions;
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function findBestMatch(string $header, array $importableFields): array
    {
        $bestScore = 0.0;
        $bestField = null;
        $bestMethod = MatchMethod::None;

        foreach ($importableFields as $fieldKey => $fieldConfig) {
            [$score, $method] = $this->scoreWithMethod($header, $fieldKey, $fieldConfig);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestField = $fieldKey;
                $bestMethod = $method;
            }
        }

        $threshold = config('import-export.column_matching.suggestion_threshold', 0.3);

        return [
            'source_column' => $header,
            'target_field' => $bestScore >= $threshold ? $bestField : null,
            'confidence_score' => $bestScore >= $threshold ? round($bestScore, 3) : 0.0,
            'match_method' => $bestScore >= $threshold ? $bestMethod->value : MatchMethod::None->value,
        ];
    }

    private function scoreWithMethod(string $header, string $fieldKey, array $fieldConfig): array
    {
        $normalizedHeader = $this->normalize($header);
        $normalizedKey = $this->normalize($fieldKey);

        // 1. Exact key match
        if ($normalizedHeader === $normalizedKey) {
            return [1.0, MatchMethod::Exact];
        }

        // 2. Label exact match
        $label = $this->normalize($fieldConfig['label'] ?? '');
        if ($label !== '' && $normalizedHeader === $label) {
            return [0.95, MatchMethod::Label];
        }

        // 3. Alias exact match
        foreach ($fieldConfig['aliases'] ?? [] as $alias) {
            if ($normalizedHeader === $this->normalize($alias)) {
                return [0.9, MatchMethod::Alias];
            }
        }

        // 4. Fuzzy match
        $fuzzyScore = $this->fuzzyScore($normalizedHeader, $normalizedKey);

        if ($label !== '') {
            $fuzzyScore = max($fuzzyScore, $this->fuzzyScore($normalizedHeader, $label));
        }

        foreach ($fieldConfig['aliases'] ?? [] as $alias) {
            $fuzzyScore = max($fuzzyScore, $this->fuzzyScore($normalizedHeader, $this->normalize($alias)));
        }

        return [$fuzzyScore, MatchMethod::Fuzzy];
    }

    private function scoreCandidate(string $header, string $fieldKey, array $fieldConfig): float
    {
        [$score] = $this->scoreWithMethod($header, $fieldKey, $fieldConfig);

        return $score;
    }

    /**
     * Combines Levenshtein distance, similar_text percentage and word overlap.
     * Returns a score between 0 and 0.89.
     */
    private function fuzzyScore(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        $maxLen = max(strlen($a), strlen($b));
        $levenshteinScore = 1.0 - (levenshtein($a, $b) / $maxLen);

        similar_text($a, $b, $percent);
        $similarScore = $percent / 100;

        $wordsA = preg_split('/[\s_\-]+/', $a);
        $wordsB = preg_split('/[\s_\-]+/', $b);
        $intersection = array_intersect($wordsA, $wordsB);
        $union = array_unique(array_merge($wordsA, $wordsB));
        $overlapScore = count($union) > 0 ? count($intersection) / count($union) : 0.0;

        $combined = ($levenshteinScore * 0.4) + ($similarScore * 0.4) + ($overlapScore * 0.2);

        return min(round($combined, 3), 0.89);
    }

    private function normalize(string $value): string
    {
        return strtolower(trim(preg_replace('/[\s\-]+/', '_', $value)));
    }
}
