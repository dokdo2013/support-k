<?php

use App\Services\Extensions\ChannelRegistry;
use App\Services\Extensions\ExtensionRegistry;
use App\Services\Extensions\SettingsNamespace;
use App\Services\Extensions\VersionConstraint;
use PHPUnit\Framework\TestCase;

final class ExtensionRegistryTest extends TestCase
{
    public function testCompatibleTrustedExtensionRegistersItsChannel(): void
    {
        $channels = new ChannelRegistry();
        $registry = new ExtensionRegistry([ROOTPATH . 'modules'], '0.1.0', $channels);

        $registry->load();

        self::assertSame(['supportk/mock-notifier'], $channels->ids());
        self::assertSame('active', $registry->entries()['supportk/mock-notifier']['status']);
    }

    public function testSafeModeParsesManifestWithoutExecutingEntrypoint(): void
    {
        $flag = tempnam(sys_get_temp_dir(), 'support-k-safe-mode-');
        self::assertNotFalse($flag);

        try {
            $channels = new ChannelRegistry();
            $registry = ExtensionRegistry::fromSafeModeFlag([ROOTPATH . 'modules'], '0.1.0', $channels, $flag);
            $registry->load();

            self::assertSame([], $channels->ids());
            self::assertSame('safe_mode', $registry->entries()['supportk/mock-notifier']['status']);
        } finally {
            @unlink($flag);
        }
    }

    public function testIncompatibleCoreDoesNotExecuteEntrypoint(): void
    {
        $channels = new ChannelRegistry();
        $registry = new ExtensionRegistry([ROOTPATH . 'modules'], '1.0.0', $channels);

        $registry->load();

        self::assertSame([], $channels->ids());
        self::assertSame('incompatible', $registry->entries()['supportk/mock-notifier']['status']);
        self::assertSame('core_version', $registry->entries()['supportk/mock-notifier']['error']);
    }

    public function testVersionAndSettingsNamespacesAreBounded(): void
    {
        self::assertTrue(VersionConstraint::matches('0.1.5', '^0.1.0'));
        self::assertFalse(VersionConstraint::matches('0.2.0', '^0.1.0'));
        self::assertSame('extensions.example.notifier.endpoint', (new SettingsNamespace('example/notifier'))->key('endpoint'));
    }
}
