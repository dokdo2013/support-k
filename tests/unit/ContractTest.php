<?php

use App\Contracts\AiRequest;
use App\Contracts\DeliveryResult;
use App\Contracts\ExecutionContext;
use App\Contracts\KnowledgeQuery;
use PHPUnit\Framework\TestCase;

final class ContractTest extends TestCase
{
    public function testAiRequestCarriesExplicitContextAndScalarInput(): void
    {
        $context = new ExecutionContext('request-test-1', 7, ['ticket.read'], ['provider_key' => 'settings.ai.key'], '3');
        $request = new AiRequest('ticket.summary', ['ticket_id' => 12, 'ticket_version' => 3], $context);

        self::assertSame('ticket.summary', $request->feature);
        self::assertSame(['ticket.read'], $request->context->permissions);
        self::assertSame('settings.ai.key', $request->context->secretReferences['provider_key']);
    }

    public function testRetryableDeliveryRequiresAnExplicitRetryTime(): void
    {
        $result = DeliveryResult::retryable('rate_limited', new DateTimeImmutable('2026-09-12 00:01:00 UTC'));

        self::assertSame('retryable', $result->status);
        self::assertNotNull($result->retryAt);
    }

    public function testKnowledgeQueryRequiresAnAllowedScope(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new KnowledgeQuery('installation help', []);
    }
}
