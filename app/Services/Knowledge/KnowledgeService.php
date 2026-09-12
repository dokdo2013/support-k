<?php

namespace App\Services\Knowledge;

use App\Contracts\KnowledgeDocument;
use App\Contracts\KnowledgeQuery;
use App\Contracts\KnowledgeSource;
use App\Contracts\Retriever;
use CodeIgniter\Database\BaseConnection;

class KnowledgeService implements KnowledgeSource, Retriever
{
    private const KINDS = ['faq', 'notice', 'document'];
    private const VISIBILITIES = ['public', 'internal'];
    private const STATUSES = ['draft', 'published'];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function id(): string
    {
        return 'support-k.database';
    }

    /** @return iterable<KnowledgeDocument> */
    public function documents(KnowledgeQuery $query): iterable
    {
        yield from $this->findPublished($query->text, $query->allowedScopes, 20);
    }

    /** @return list<KnowledgeDocument> */
    public function search(KnowledgeQuery $query, int $limit = 5): array
    {
        return $this->findPublished($query->text, $query->allowedScopes, $limit);
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function create(array $input): array
    {
        $document = $this->normaliseInput($input);
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('knowledge_documents')->insert($document + [
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->requireAdminDocument((int) $this->db->insertID());
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function update(int $id, int $expectedVersion, array $input): array
    {
        if ($expectedVersion < 1) {
            throw new KnowledgeConflictException('문서 버전을 확인할 수 없습니다. 페이지를 새로고침한 뒤 다시 시도해 주세요.');
        }
        $document = $this->normaliseInput($input);
        $document['version'] = $expectedVersion + 1;
        $document['updated_at'] = gmdate('Y-m-d H:i:s');
        $updated = $this->db->table('knowledge_documents')
            ->where('id', $id)
            ->where('version', $expectedVersion)
            ->update($document);
        if (! $updated || $this->db->affectedRows() !== 1) {
            $this->throwUpdateFailure($id);
        }

        return $this->requireAdminDocument($id);
    }

    public function delete(int $id, int $expectedVersion): void
    {
        if ($expectedVersion < 1) {
            throw new KnowledgeConflictException('문서 버전을 확인할 수 없습니다. 페이지를 새로고침한 뒤 다시 시도해 주세요.');
        }
        $deleted = $this->db->table('knowledge_documents')
            ->where('id', $id)
            ->where('version', $expectedVersion)
            ->delete();
        if (! $deleted || $this->db->affectedRows() !== 1) {
            $this->throwUpdateFailure($id);
        }
    }

    /** @return array<string, mixed> */
    public function adminDocument(int $id): array
    {
        return $this->requireAdminDocument($id);
    }

    /** @return array<string, mixed>|null */
    public function publicDocument(int $id): ?array
    {
        $document = $this->db->table('knowledge_documents')
            ->where('id', $id)
            ->where('visibility', 'public')
            ->where('status', 'published')
            ->get()
            ->getRowArray();

        return $document === null ? null : $this->normaliseRow($document);
    }

    /** @return list<array<string, mixed>> */
    public function listForAdmin(string $kind = '', string $status = '', string $visibility = '', string $text = '', int $limit = 100): array
    {
        $builder = $this->db->table('knowledge_documents')->select('id, kind, title, visibility, status, version, created_at, updated_at');
        if (in_array($kind, self::KINDS, true)) {
            $builder->where('kind', $kind);
        }
        if (in_array($status, self::STATUSES, true)) {
            $builder->where('status', $status);
        }
        if (in_array($visibility, self::VISIBILITIES, true)) {
            $builder->where('visibility', $visibility);
        }
        if (trim($text) !== '') {
            $builder->groupStart()->like('title', trim($text))->orLike('body', trim($text))->groupEnd();
        }

        return array_map(fn (array $document): array => $this->normaliseRow($document), $builder->orderBy('updated_at', 'DESC')->limit(max(1, min(100, $limit)))->get()->getResultArray());
    }

    /** @return list<array<string, mixed>> */
    public function publicDocuments(int $limit = 20): array
    {
        $documents = $this->db->table('knowledge_documents')
            ->select('id, kind, title, body, version, updated_at')
            ->where('visibility', 'public')
            ->where('status', 'published')
            ->orderBy('updated_at', 'DESC')
            ->limit(max(1, min(50, $limit)))
            ->get()
            ->getResultArray();

        return array_map(fn (array $document): array => $this->normaliseRow($document), $documents);
    }

    /** @return list<array<string, mixed>> */
    public function searchPublicFaq(string $text, int $limit = 10): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $documents = $this->db->table('knowledge_documents')
            ->select('id, kind, title, body, version, updated_at')
            ->where('kind', 'faq')
            ->where('visibility', 'public')
            ->where('status', 'published')
            ->groupStart()->like('title', $text)->orLike('body', $text)->groupEnd()
            ->orderBy('updated_at', 'DESC')
            ->limit(max(1, min(20, $limit)))
            ->get()
            ->getResultArray();

        return array_map(fn (array $document): array => $this->normaliseRow($document), $documents);
    }

    /**
     * Rechecks a retrieved citation immediately before it is linked or shown.
     * It rejects deleted, unpublished, internal, and superseded sources.
     */
    public function verifyCurrentPublicSource(string $id, int $version): ?KnowledgeDocument
    {
        if (! ctype_digit($id) || $version < 1) {
            return null;
        }
        $document = $this->db->table('knowledge_documents')
            ->where('id', (int) $id)
            ->where('version', $version)
            ->where('visibility', 'public')
            ->where('status', 'published')
            ->get()
            ->getRowArray();

        return $document === null ? null : $this->toContract($document);
    }

    /** @param list<string> $allowedScopes
     *  @return list<KnowledgeDocument>
     */
    private function findPublished(string $text, array $allowedScopes, int $limit): array
    {
        $scopes = array_values(array_intersect(self::VISIBILITIES, $allowedScopes));
        if ($scopes === []) {
            return [];
        }
        $builder = $this->db->table('knowledge_documents')
            ->where('status', 'published')
            ->whereIn('visibility', $scopes);
        if (trim($text) !== '') {
            $builder->groupStart()->like('title', trim($text))->orLike('body', trim($text))->groupEnd();
        }
        $rows = $builder->orderBy('updated_at', 'DESC')->limit(max(1, min(20, $limit)))->get()->getResultArray();

        return array_map(fn (array $row): KnowledgeDocument => $this->toContract($row), $rows);
    }

    /** @param array<string, mixed> $input
     *  @return array{kind: string, title: string, body: string, visibility: string, status: string}
     */
    private function normaliseInput(array $input): array
    {
        $kind = is_string($input['kind'] ?? null) ? $input['kind'] : '';
        $visibility = is_string($input['visibility'] ?? null) ? $input['visibility'] : '';
        $status = is_string($input['status'] ?? null) ? $input['status'] : '';
        if (! in_array($kind, self::KINDS, true) || ! in_array($visibility, self::VISIBILITIES, true) || ! in_array($status, self::STATUSES, true)) {
            throw new KnowledgeException('문서 종류, 공개 범위 또는 상태가 올바르지 않습니다.');
        }

        return [
            'kind' => $kind,
            'title' => $this->plainText($input['title'] ?? null, '제목', 200),
            'body' => $this->plainText($input['body'] ?? null, '내용', 20000),
            'visibility' => $visibility,
            'status' => $status,
        ];
    }

    /** @return array<string, mixed> */
    private function requireAdminDocument(int $id): array
    {
        $document = $this->db->table('knowledge_documents')->where('id', $id)->get()->getRowArray();
        if ($document === null) {
            throw new KnowledgeNotFoundException('문서를 찾을 수 없습니다.');
        }

        return $this->normaliseRow($document);
    }

    private function throwUpdateFailure(int $id): never
    {
        $exists = $this->db->table('knowledge_documents')->select('id')->where('id', $id)->get()->getRowArray();
        if ($exists === null) {
            throw new KnowledgeNotFoundException('문서를 찾을 수 없습니다.');
        }
        throw new KnowledgeConflictException('다른 운영자가 문서를 변경했습니다. 새 내용을 확인한 뒤 다시 저장해 주세요.');
    }

    private function plainText(mixed $value, string $label, int $limit): string
    {
        $text = trim(is_string($value) ? $value : '');
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        if ($text === '' || $length > $limit) {
            throw new KnowledgeException($label . '을(를) 1자 이상 ' . $limit . '자 이하로 입력해 주세요.');
        }

        return $text;
    }

    /** @param array<string, mixed> $document */
    private function toContract(array $document): KnowledgeDocument
    {
        return new KnowledgeDocument(
            (string) $document['id'],
            (int) $document['version'],
            (string) $document['visibility'],
            (string) $document['title'],
            (string) $document['body'],
        );
    }

    /** @param array<string, mixed> $document
     *  @return array<string, mixed>
     */
    private function normaliseRow(array $document): array
    {
        $document['id'] = (int) $document['id'];
        $document['version'] = (int) $document['version'];

        return $document;
    }
}
