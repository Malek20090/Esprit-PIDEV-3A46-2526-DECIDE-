<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Expense;
use App\Entity\Revenue;
use App\Entity\User;
use App\Repository\ExpenseRepository;
use App\Repository\RevenueRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class FinancialMonitoringService
{
    public function __construct(
        private readonly RevenueRepository $revenueRepository,
        private readonly ExpenseRepository $expenseRepository,
        private readonly FinancialAlertMailerService $financialAlertMailerService,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Calculate totals and trigger email alerts based on business thresholds.
     *
     * @return array{totalIncome: float, totalExpenses: float, netBalance: float, expenseRatio: float|null}
     */
    public function evaluateAndNotify(User $user, bool $sendMonthlySummary = false): array
    {
        /** @var Revenue[] $revenues */
        $revenues = $this->revenueRepository->findBy(['user' => $user]);
        /** @var Expense[] $expenses */
        $expenses = $this->expenseRepository->findBy(['user' => $user]);

        $totalIncome = array_sum(array_map(static fn (Revenue $r): float => (float) $r->getAmount(), $revenues));
        $totalExpenses = array_sum(array_map(static fn (Expense $e): float => (float) ($e->getAmount() ?? 0.0), $expenses));
        $netBalance = $totalIncome - $totalExpenses;
        $expenseRatio = $totalIncome > 0 ? ($totalExpenses / $totalIncome) : null;

        $this->dispatchOverspendingAlertIfNeeded($user, $totalIncome, $totalExpenses, $expenseRatio);

        if ($sendMonthlySummary) {
            $this->dispatchMonthlySummaryIfNeeded($user, $totalIncome, $totalExpenses, $netBalance);
        }

        return [
            'totalIncome' => $totalIncome,
            'totalExpenses' => $totalExpenses,
            'netBalance' => $netBalance,
            'expenseRatio' => $expenseRatio,
        ];
    }

    private function dispatchOverspendingAlertIfNeeded(User $user, float $totalIncome, float $totalExpenses, ?float $expenseRatio): void
    {
        $alertLevel = null;
        if ($totalIncome > 0 && $expenseRatio !== null && $expenseRatio > 1.0) {
            $alertLevel = 'critical';
        } elseif ($totalIncome > 0 && $expenseRatio !== null && $expenseRatio > 0.8) {
            $alertLevel = 'warning';
        } elseif ($totalIncome <= 0 && $totalExpenses > 0) {
            $alertLevel = 'critical';
        }

        if ($alertLevel === null) {
            return;
        }

        $cacheKey = sprintf(
            'finance_alert_%d_%s_%s',
            (int) $user->getId(),
            $alertLevel,
            (new \DateTimeImmutable())->format('Ymd')
        );

        $alreadySentToday = $this->cache->get($cacheKey, static function (ItemInterface $item): bool {
            $item->expiresAfter(86400);
            return false;
        });

        if ($alreadySentToday) {
            return;
        }

        try {
            $this->financialAlertMailerService->sendOverspendingAlert($totalIncome, $totalExpenses);
            $this->cache->delete($cacheKey);
            $this->cache->get($cacheKey, static function (ItemInterface $item): bool {
                $item->expiresAfter(86400);
                return true;
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send overspending alert email.', [
                'userId' => $user->getId(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function dispatchMonthlySummaryIfNeeded(User $user, float $totalIncome, float $totalExpenses, float $netBalance): void
    {
        $cacheKey = sprintf(
            'finance_monthly_summary_%d_%s',
            (int) $user->getId(),
            (new \DateTimeImmutable())->format('Y-m')
        );

        $alreadySentThisMonth = $this->cache->get($cacheKey, static function (ItemInterface $item): bool {
            $item->expiresAfter(2678400);
            return false;
        });

        if ($alreadySentThisMonth) {
            return;
        }

        try {
            $this->financialAlertMailerService->sendMonthlySummary($totalIncome, $totalExpenses, $netBalance);
            $this->cache->delete($cacheKey);
            $this->cache->get($cacheKey, static function (ItemInterface $item): bool {
                $item->expiresAfter(2678400);
                return true;
            });
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to send monthly summary email.', [
                'userId' => $user->getId(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}

