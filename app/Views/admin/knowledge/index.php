<?= $this->extend('layout') ?>

<?= $this->section('content') ?>
<h1>지식 문서</h1>
<p><a href="<?= esc(site_url('admin/knowledge/new')) ?>">새 문서 만들기</a></p>

<form method="get" action="<?= esc(site_url('admin/knowledge')) ?>">
    <label for="kind">종류</label>
    <select id="kind" name="kind">
        <option value="">전체</option>
        <option value="faq" <?= $kind === 'faq' ? 'selected' : '' ?>>FAQ</option>
        <option value="notice" <?= $kind === 'notice' ? 'selected' : '' ?>>공지</option>
        <option value="document" <?= $kind === 'document' ? 'selected' : '' ?>>문서</option>
    </select>
    <label for="status">상태</label>
    <select id="status" name="status">
        <option value="">전체</option>
        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>초안</option>
        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>게시</option>
    </select>
    <label for="visibility">공개 범위</label>
    <select id="visibility" name="visibility">
        <option value="">전체</option>
        <option value="public" <?= $visibility === 'public' ? 'selected' : '' ?>>공개</option>
        <option value="internal" <?= $visibility === 'internal' ? 'selected' : '' ?>>내부</option>
    </select>
    <label for="q">검색</label>
    <input id="q" name="q" type="search" value="<?= esc($query) ?>">
    <button type="submit">조회</button>
</form>

<?php if ($documents === []): ?>
    <p>조건에 맞는 문서가 없습니다.</p>
<?php else: ?>
    <table>
        <thead><tr><th>종류</th><th>제목</th><th>범위</th><th>상태</th><th>수정 시각</th></tr></thead>
        <tbody>
        <?php foreach ($documents as $document): ?>
            <tr>
                <td><?= esc($document['kind']) ?></td>
                <td><a href="<?= esc(site_url('admin/knowledge/' . $document['id'])) ?>"><?= esc($document['title']) ?></a></td>
                <td><?= esc($document['visibility']) ?></td>
                <td><?= esc($document['status']) ?></td>
                <td><?= esc($document['updated_at']) ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
<?= $this->endSection() ?>
