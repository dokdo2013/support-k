<?php

namespace App\Services\Extensions;

use App\Contracts\ExtensionRegistrar;
use App\Contracts\NotificationChannel;

final readonly class ExtensionContext implements ExtensionRegistrar
{
    private SettingsNamespace $namespace;

    public function __construct(
        private string $id,
        private ChannelRegistry $channels,
    ) {
        $this->namespace = new SettingsNamespace($id);
    }

    public function extensionId(): string
    {
        return $this->id;
    }

    public function settingsNamespace(): string
    {
        return $this->namespace->prefix();
    }

    public function notificationChannel(NotificationChannel $channel): void
    {
        $this->channels->add($channel);
    }
}
