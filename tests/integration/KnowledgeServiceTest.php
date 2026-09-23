<?php

declare(strict_types=1);

use App\Contracts\KnowledgeQuery;
use App\Services\Knowledge\KnowledgeConflictException;
use App\Services\Knowledge\KnowledgeService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/** @internal */
final class KnowledgeServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $migrate = true;
    protected $migrateOnce = false;
    protected $refresh = true;

    public function testPublicFaqSearchNeverReturnsDraftOrInternalDocuments(): void
    {
        $service = new KnowledgeService($this->db);
        $public = $service->create($this->document('공개 설치 안내', '설치 순서를 확인해 주세요.', 'faq', 'public', 'published'));
        $service->create($this->document('내부 설치 안내', '설치 순서를 확인해 주세요.', 'faq', 'internal', 'published'));
        $service->create($this->document('초안 설치 안내', '설치 순서를 확인해 주세요.', 'faq', 'public', 'draft'));
        $service->create($this->document('공개 공지', '설치 순서를 확인해 주세요.', 'notice', 'public', 'published'));

        $results = $service->searchPublicFaq('설치');
        $retrieved = $service->search(new KnowledgeQuery('설치', ['public']), 10);

        $this->assertCount(1, $results);
        $this->assertSame($public['id'], $results[0]['id']);
        $this->assertCount(2, $retrieved);
        $this->assertEqualsCanonicalizing(['공개 공지', '공개 설치 안내'], array_map(static fn ($document): string => $document->title, $retrieved));
    }

    public function testPublicCancellationInvalidatesCustomerLookupAndCitation(): void
    {
        $service = new KnowledgeService($this->db);
        $document = $service->create($this->document('배송 안내', '배송 상태를 확인합니다.', 'faq', 'public', 'published'));

        $this->assertNotNull($service->publicDocument((int) $document['id']));
        $this->assertNotNull($service->verifyCurrentPublicSource((string) $document['id'], 1));
        $updated = $service->update((int) $document['id'], 1, $this->document('배송 안내', '내부 검토 중입니다.', 'faq', 'internal', 'published'));

        $this->assertSame(2, $updated['version']);
        $this->assertNull($service->publicDocument((int) $document['id']));
        $this->assertNull($service->verifyCurrentPublicSource((string) $document['id'], 1));
        $this->assertNull($service->verifyCurrentPublicSource((string) $document['id'], 2));
    }

    public function testStaleEditorCannotOverwriteNewerVersion(): void
    {
        $service = new KnowledgeService($this->db);
        $document = $service->create($this->document('환불 안내', '첫 문장', 'document', 'internal', 'draft'));
        $updated = $service->update((int) $document['id'], 1, $this->document('환불 안내', '먼저 저장한 문장', 'document', 'internal', 'draft'));

        $this->expectException(KnowledgeConflictException::class);
        try {
            $service->update((int) $document['id'], 1, $this->document('환불 안내', '오래된 편집기 문장', 'document', 'internal', 'draft'));
        } finally {
            $current = $service->adminDocument((int) $document['id']);
            $this->assertSame(2, $updated['version']);
            $this->assertSame(2, $current['version']);
            $this->assertSame('먼저 저장한 문장', $current['body']);
        }
    }

    public function testPlainTextKnowledgePreservesCodeForEscapedRendering(): void
    {
        $service = new KnowledgeService($this->db);
        $document = $service->create($this->document('코드 예시', '<script>const answer = 42;</script>', 'faq', 'public', 'published'));

        $public = $service->publicDocument((int) $document['id']);

        $this->assertNotNull($public);
        $this->assertSame('<script>const answer = 42;</script>', $public['body']);
    }

    /** @return array<string, string> */
    private function document(string $title, string $body, string $kind, string $visibility, string $status): array
    {
        return compact('title', 'body', 'kind', 'visibility', 'status');
    }
}
