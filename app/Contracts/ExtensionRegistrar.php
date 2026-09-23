<?php

namespace App\Contracts;

interface ExtensionRegistrar
{
    public function extensionId(): string;

    public function settingsNamespace(): string;

    public function notificationChannel(NotificationChannel $channel): void;
}
