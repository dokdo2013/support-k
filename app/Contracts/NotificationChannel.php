<?php

namespace App\Contracts;

interface NotificationChannel
{
    public function id(): string;

    public function deliver(Notification $notification): DeliveryResult;
}
