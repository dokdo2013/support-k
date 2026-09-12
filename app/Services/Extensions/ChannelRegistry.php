<?php

namespace App\Services\Extensions;

use App\Contracts\NotificationChannel;
use InvalidArgumentException;

final class ChannelRegistry
{
    /** @var array<string,NotificationChannel> */
    private array $channels = [];

    public function add(NotificationChannel $channel): void
    {
        $id = $channel->id();
        if (preg_match('/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/', $id) !== 1) {
            throw new InvalidArgumentException('Notification channel ID must use the vendor/name format.');
        }
        if (isset($this->channels[$id])) {
            throw new InvalidArgumentException("Notification channel {$id} is already registered.");
        }
        $this->channels[$id] = $channel;
    }

    public function get(string $id): ?NotificationChannel
    {
        return $this->channels[$id] ?? null;
    }

    /** @return list<string> */
    public function ids(): array
    {
        $ids = array_keys($this->channels);
        sort($ids);

        return $ids;
    }
}
