<?php

namespace App\Tests\Service;

use App\Service\SavingsStatsService;
use PHPUnit\Framework\TestCase;

class SavingsStatsServiceTest extends TestCase
{
    public function testBuildTypeStatsAggregatesTotals(): void
    {
        $service = new SavingsStatsService();

        $rows = [
            ['type' => 'EPARGNE', 'montant' => 100, 'description' => 'd1', 'date' => '2026-03-01'],
            ['type' => 'EPARGNE', 'montant' => 50, 'description' => 'd2', 'date' => '2026-03-02'],
            ['type' => 'GOAL_CONTRIB', 'montant' => 25, 'description' => 'd3', 'date' => '2026-03-02'],
        ];

        $out = $service->build($rows, 'type');

        self::assertSame('type', $out['stat_by']);
        self::assertSame(3, $out['tx_stats']['total']);
        self::assertSame(175.0, $out['tx_stats']['sum']);
        self::assertSame(100.0, $out['tx_stats']['max']);
        self::assertSame(['EPARGNE', 'GOAL_CONTRIB'], $out['stat_labels']);
        self::assertSame([150.0, 25.0], $out['stat_values']);
    }

    public function testBuildDescriptionStatsUsesFallbackLabel(): void
    {
        $service = new SavingsStatsService();

        $rows = [
            ['type' => 'EPARGNE', 'montant' => 10, 'description' => '', 'date' => '2026-03-01'],
            ['type' => 'EPARGNE', 'montant' => 5, 'description' => 'Coffee', 'date' => '2026-03-01'],
        ];

        $out = $service->build($rows, 'description');

        self::assertSame('description', $out['stat_by']);
        self::assertSame(['Coffee', 'No description'], $out['stat_labels']);
        self::assertSame([5.0, 10.0], $out['stat_values']);
    }
}
