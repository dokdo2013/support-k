<?php

namespace SupportK\Examples\Notifier;

use App\Contracts\DeliveryResult;
use App\Contracts\Extension;
use App\Contracts\ExtensionRegistrar;
use App\Contracts\Notification;
use App\Contracts\NotificationChannel;

final class ExampleNotifierExtension implements Extension
{
    public function register(ExtensionRegistrar $registrar): void
    {
        $registrar->notificationChannel(new ExampleNotificationChannel());
    }
}

/** Demonstrates the channel contract without making an external request. */
final class ExampleNotificationChannel implements NotificationChannel
{
    public function id(): string
    {
        return 'example/notifier';
    }

    public function deliver(Notification $notification): DeliveryResult
    {
        return DeliveryResult::success('example:' . $notification->eventId);
    }
}
