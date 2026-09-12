<?php

namespace SupportK\Modules\MockNotifier;

use App\Contracts\DeliveryResult;
use App\Contracts\Extension;
use App\Contracts\ExtensionRegistrar;
use App\Contracts\Notification;
use App\Contracts\NotificationChannel;

final class MockNotifierExtension implements Extension
{
    public function register(ExtensionRegistrar $registrar): void
    {
        $registrar->notificationChannel(new MockNotificationChannel());
    }
}

/** Local deterministic channel for development and tests. It performs no I/O. */
final class MockNotificationChannel implements NotificationChannel
{
    /** @var list<Notification> */
    private array $delivered = [];

    public function id(): string
    {
        return 'supportk/mock-notifier';
    }

    public function deliver(Notification $notification): DeliveryResult
    {
        $this->delivered[] = $notification;

        return DeliveryResult::success('mock:' . $notification->idempotencyKey);
    }

    /** @return list<Notification> */
    public function delivered(): array
    {
        return $this->delivered;
    }
}
