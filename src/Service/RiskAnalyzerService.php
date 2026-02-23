<?php

namespace App\Service;

use App\Entity\CasRelles;

class RiskAnalyzerService
{
    /**
     * @param CasRelles[] $cases
     * @return array{
     *   typology: array{
     *     counts: array<string,int>,
     *     percentages: array<string,float>,
     *     total: int,
     *     topCategory: string,
     *     topPercent: float,
     *     exposureMessage: string
     *   },
     *   riskScore: int,
     *   riskLevel: string,
     *   weeklyTips: string[],
     *   suggestedIncidents: string[],
     *   suggestedOpportunities: string[]
     * }
     */
    public function analyze(array $cases): array
    {
        $counts = [
            'VOITURE' => 0,
            'PANNE_MAISON' => 0,
            'SANTE' => 0,
            'AUTRE' => 0,
        ];
        $amounts = [
            'VOITURE' => 0.0,
            'PANNE_MAISON' => 0.0,
            'SANTE' => 0.0,
            'AUTRE' => 0.0,
        ];

        $negativeCount = 0;
        $negativeAmount = 0.0;
        $recentCounts = [
            'VOITURE' => 0,
            'PANNE_MAISON' => 0,
            'SANTE' => 0,
            'AUTRE' => 0,
        ];
        $recentAmounts = [
            'VOITURE' => 0.0,
            'PANNE_MAISON' => 0.0,
            'SANTE' => 0.0,
            'AUTRE' => 0.0,
        ];
        $recentNegativeCount = 0;

        $weightedFrequency = 0.0;
        $weightedSeverity = 0.0;

        foreach ($cases as $case) {
            if ((string) $case->getType() !== CasRelles::TYPE_NEGATIF) {
                continue;
            }

            $negativeCount++;
            $amount = (float) $case->getMontant();
            $negativeAmount += $amount;

            $text = mb_strtolower(trim(((string) $case->getTitre()) . ' ' . ((string) $case->getDescription())));
            $category = $this->categorizeText($text);
            $counts[$category]++;
            $amounts[$category] += $amount;

            $days = $this->daysSince($case);
            if ($days <= 30) {
                $recentCounts[$category]++;
                $recentAmounts[$category] += $amount;
                $recentNegativeCount++;
            }

            // Recency-weighted learning: newer cases influence the model more.
            $weight = max(0.20, 1.0 - ($days / 180.0));
            $weightedFrequency += (10.0 * $weight);
            $weightedSeverity += (($amount / 20.0) * $weight);
        }

        $totalTyped = array_sum($counts);
        $percentages = [];
        foreach ($counts as $key => $value) {
            $percentages[$key] = $totalTyped > 0 ? ($value * 100 / $totalTyped) : 0.0;
        }

        $sorted = $counts;
        arsort($sorted);
        $topCategory = (string) array_key_first($sorted);
        $topPercent = $percentages[$topCategory] ?? 0.0;

        $labels = [
            'VOITURE' => 'automobiles',
            'PANNE_MAISON' => 'de pannes maison',
            'SANTE' => 'de santé',
            'AUTRE' => 'divers',
        ];
        $exposureMessage = $totalTyped > 0
            ? sprintf('Tu es fortement exposé aux risques %s.', $labels[$topCategory] ?? 'divers')
            : 'Pas assez de données pour définir un risque dominant.';

        $avgAmount = $negativeCount > 0 ? ($negativeAmount / $negativeCount) : 0.0;
        $frequencyScore = min(50, (int) round($weightedFrequency));
        $severityScore = min(50, (int) round($weightedSeverity));
        $riskScore = max(0, min(100, $frequencyScore + $severityScore));

        if ($riskScore >= 70) {
            $riskLevel = 'ELEVE';
        } elseif ($riskScore >= 40) {
            $riskLevel = 'MOYEN';
        } else {
            $riskLevel = 'FAIBLE';
        }

        return [
            'typology' => [
                'counts' => $counts,
                'percentages' => $percentages,
                'total' => $totalTyped,
                'topCategory' => $topCategory,
                'topPercent' => $topPercent,
                'exposureMessage' => $exposureMessage,
            ],
            'riskScore' => $riskScore,
            'riskLevel' => $riskLevel,
            'weeklyTips' => $this->buildWeeklyTips($recentCounts, $recentAmounts, $recentNegativeCount, $avgAmount),
            'suggestedIncidents' => $this->buildSuggestedIncidents($recentCounts, $counts),
            'suggestedOpportunities' => $this->buildSuggestedOpportunities($recentCounts, $counts),
        ];
    }

    private function categorizeText(string $text): string
    {
        if ($text === '') {
            return 'AUTRE';
        }

        if (preg_match('/panne moteur|essuie|essuie-glace|batterie|pneu|voiture|auto/u', $text)) {
            return 'VOITURE';
        }
        if (preg_match('/machine [aà] laver|t[eé]l[eé]phone|frigo|chaudi[eè]re|panne/u', $text)) {
            return 'PANNE_MAISON';
        }
        if (preg_match('/urgence|m[eé]dicament|consultation|analyse|sant[eé]/u', $text)) {
            return 'SANTE';
        }

        return 'AUTRE';
    }

    /**
     * @param array<string,int> $counts
     * @param array<string,float> $amounts
     * @return string[]
     */
    private function buildWeeklyTips(array $counts, array $amounts, int $negativeCount, float $avgAmount): array
    {
        $tips = [];

        $carCount = (int) ($counts['VOITURE'] ?? 0);
        if ($carCount > 0) {
            $carAvg = ((float) ($amounts['VOITURE'] ?? 0.0)) / max(1, $carCount);
            $carBudget = (int) (round(max(80.0, min(300.0, $carAvg * 0.40)) / 10) * 10);
            $tips[] = sprintf('Tu as %d incidents voiture ce mois-ci. Prévois un budget entretien mensuel de %d DT.', $carCount, $carBudget);
        }

        $healthCount = (int) ($counts['SANTE'] ?? 0);
        if ($healthCount > 0) {
            $healthAvg = ((float) ($amounts['SANTE'] ?? 0.0)) / max(1, $healthCount);
            $healthBudget = (int) (round(max(40.0, min(180.0, $healthAvg * 0.35)) / 10) * 10);
            $tips[] = sprintf('Les urgences santé augmentent. Crée une réserve médicaments de %d DT.', $healthBudget);
        }

        if ($negativeCount >= 3) {
            $avgRounded = (int) (round($avgAmount / 10) * 10);
            $tips[] = sprintf('Tu as %d imprévus négatifs récents. Mets en place une enveloppe prévention de %d DT par mois.', $negativeCount, max(60, $avgRounded));
        }

        if ($avgAmount > 200) {
            $tips[] = 'Le coût moyen des imprévus est élevé: renforce progressivement ton fonds de sécurité.';
        }

        if (!$tips) {
            $tips[] = 'Pas de risque fort détecté: continue le suivi hebdomadaire pour garder cette stabilité.';
        }

        return array_slice(array_values(array_unique($tips)), 0, 4);
    }

    /**
     * @param array<string,int> $counts
     * @return string[]
     */
    private function buildSuggestedIncidents(array $recentCounts, array $allCounts): array
    {
        $suggestions = [];

        $car = max((int) ($recentCounts['VOITURE'] ?? 0), (int) floor(((int) ($allCounts['VOITURE'] ?? 0)) / 2));
        if ($car >= 1) {
            $suggestions[] = 'Panne moteur voiture';
            $suggestions[] = 'Panne essuie-glace';
            $suggestions[] = 'Batterie voiture';
            $suggestions[] = 'Pneu voiture';
        }
        $home = max((int) ($recentCounts['PANNE_MAISON'] ?? 0), (int) floor(((int) ($allCounts['PANNE_MAISON'] ?? 0)) / 2));
        if ($home >= 1) {
            $suggestions[] = 'Panne machine à laver';
            $suggestions[] = 'Panne téléphone';
            $suggestions[] = 'Panne frigo';
            $suggestions[] = 'Panne chaudière';
        }
        $health = max((int) ($recentCounts['SANTE'] ?? 0), (int) floor(((int) ($allCounts['SANTE'] ?? 0)) / 2));
        if ($health >= 1) {
            $suggestions[] = 'Urgence santé';
            $suggestions[] = 'Médicament';
            $suggestions[] = 'Consultation';
            $suggestions[] = 'Analyses';
        }

        if (!$suggestions) {
            $suggestions[] = 'Panne téléphone';
            $suggestions[] = 'Urgence santé';
        }

        return array_values(array_unique($suggestions));
    }

    /**
     * @param array<string,int> $counts
     * @return string[]
     */
    private function buildSuggestedOpportunities(array $recentCounts, array $allCounts): array
    {
        $ops = [];

        $car = max((int) ($recentCounts['VOITURE'] ?? 0), (int) floor(((int) ($allCounts['VOITURE'] ?? 0)) / 2));
        if ($car >= 1) {
            $ops[] = 'Opportunité: forfait entretien voiture préventif';
        }
        $home = max((int) ($recentCounts['PANNE_MAISON'] ?? 0), (int) floor(((int) ($allCounts['PANNE_MAISON'] ?? 0)) / 2));
        if ($home >= 1) {
            $ops[] = 'Opportunité: extension de garantie électroménager/téléphone';
        }
        $health = max((int) ($recentCounts['SANTE'] ?? 0), (int) floor(((int) ($allCounts['SANTE'] ?? 0)) / 2));
        if ($health >= 1) {
            $ops[] = 'Opportunité: pack prévention santé et pharmacie';
        }
        if (!$ops) {
            $ops[] = 'Opportunité: réserve mensuelle multi-risques';
        }

        return $ops;
    }

    private function daysSince(CasRelles $case): int
    {
        $date = $case->getDateEffet();
        if (!$date) {
            return 999;
        }

        $now = new \DateTimeImmutable();
        $caseDate = \DateTimeImmutable::createFromMutable(
            $date instanceof \DateTime ? $date : new \DateTime($date->format('Y-m-d H:i:s'))
        );

        return max(0, (int) $caseDate->diff($now)->format('%a'));
    }
}
