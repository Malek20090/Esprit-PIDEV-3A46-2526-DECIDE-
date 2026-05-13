<?php

namespace App\EventSubscriber;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class DatabaseContextLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -50],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/_profiler') || str_starts_with($path, '/_wdt')) {
            return;
        }

        try {
            $this->logger->info('DB context', [
                'method' => $request->getMethod(),
                'path' => $path,
                'database' => (string) ($this->connection->fetchOne('SELECT DATABASE()') ?? ''),
                'host' => (string) ($this->connection->fetchOne('SELECT @@hostname') ?? ''),
                'port' => (string) ($this->connection->fetchOne('SELECT @@port') ?? ''),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('DB context log failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

