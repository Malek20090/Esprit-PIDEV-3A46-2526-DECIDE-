<?php

namespace App\Tests\Service;

use App\Service\GoalWhatIfAdvisor;
use App\Service\GoalWhatIfService;
use PHPUnit\Framework\TestCase;

class GoalWhatIfAdvisorTest extends TestCase
{
    public function testBuildWhenGoalAlreadyAchieved(): void
    {
        $service = new GoalWhatIfAdvisor(new GoalWhatIfService());

        $metrics = [
            'remaining_after_now' => 0.0,
            'risk_level' => 'LOW',
            'deadline_gap_months' => 0,
            'deadline_confidence' => 1.0,
            'required_monthly_to_hit_deadline' => 0.0,
            'projected_finish_date' => '2026-03-01',
            'feasibility_score' => 1.0,
        ];

        $context = [
            'goal_name' => 'Laptop',
            'today_date' => '2026-03-01',
            'current_saved' => 500.0,
            'target_amount' => 500.0,
            'deadline_date' => '2026-12-01',
            'monthly_deposit' => 0.0,
            'one_time_deposit' => 0.0,
        ];

        $out = $service->build($metrics, $context);

        self::assertStringContainsString('already achieved', $out['executive_insight']);
        self::assertSame('Stabilize completed goal', $out['best_action']['title']);
        self::assertSame(3, count($out['options']));
    }

    public function testBuildWhenMonthlyIsZeroSuggestsRestore(): void
    {
        $service = new GoalWhatIfAdvisor(new GoalWhatIfService());

        $metrics = [
            'remaining_after_now' => 600.0,
            'risk_level' => 'HIGH',
            'deadline_gap_months' => 4,
            'deadline_confidence' => 0.2,
            'required_monthly_to_hit_deadline' => 120.0,
            'projected_finish_date' => null,
            'feasibility_score' => 0.2,
        ];

        $context = [
            'goal_name' => 'Trip',
            'today_date' => '2026-03-01',
            'current_saved' => 100.0,
            'target_amount' => 700.0,
            'deadline_date' => '2026-09-01',
            'monthly_deposit' => 0.0,
            'one_time_deposit' => 0.0,
        ];

        $out = $service->build($metrics, $context);

        self::assertStringContainsString('0 TND/month', $out['executive_insight']);
        self::assertSame('Restore feasibility', $out['best_action']['title']);
        self::assertNotEmpty($out['next_7_days']);
    }
}
