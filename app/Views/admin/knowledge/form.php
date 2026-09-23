<?= $this->extend('layout') ?>

<?= $this->section('content') ?>
<?php
$kind = (string) old('kind', $document['kind'], 'raw');
$title = (string) old('title', $document['title'], 'raw');
$body = (string) old('body', $document['body'], 'raw');
$visibility = (string) old('visibility', $document['visibility'], 'raw');
$status = (string) old('status', $document['status'], 'raw');
?>
<p><a href="<?= esc(site_url('admin/knowledge')) ?>">문서 목록으로</a></p>
<h1><?= esc($title !== '' ? $title : '새 지식 문서') ?></h1>
<form method="post" action="<?= esc($action) ?>">
    <?= csrf_field() ?>
    <?php if (isset($document['id'])): ?>
        <input type="hidden" name="expected_version" value="<?= esc($document['version']) ?>">
    <?php endif ?>
    <p>
        <label for="kind">종류</label>
        <select id="kind" name="kind">
            <option value="faq" <?= $kind === 'faq' ? 'selected' : '' ?>>FAQ</option>
            <option value="notice" <?= $kind === 'notice' ? 'selected' : '' ?>>공지</option>
            <option value="document" <?= $kind === 'document' ? 'selected' : '' ?>>문서</option>
        </select>
    </p>
    <p>
        <label for="title">제목</label>
        <input id="title" name="title" type="text" maxlength="200" required value="<?= esc($title) ?>">
    </p>
    <p>
        <label for="body">내용</label>
        <textarea id="body" name="body" rows="14" maxlength="20000" required><?= esc($body) ?></textarea>
    </p>
    <p>
        <label for="visibility">공개 범위</label>
        <select id="visibility" name="visibility">
            <option value="internal" <?= $visibility === 'internal' ? 'selected' : '' ?>>내부</option>
            <option value="public" <?= $visibility === 'public' ? 'selected' : '' ?>>공개</option>
        </select>
    </p>
    <p>
        <label for="status">상태</label>
        <select id="status" name="status">
            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>초안</option>
            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>게시</option>
        </select>
    </p>
    <button type="submit"><?= esc($submitLabel) ?></button>
</form>

<?php if (isset($document['id'])): ?>
    <h2>문서 삭제</h2>
    <form method="post" action="<?= esc(site_url('admin/knowledge/' . $document['id'] . '/delete')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="expected_version" value="<?= esc($document['version']) ?>">
        <button type="submit">문서 삭제</button>
    </form>
<?php endif ?>
<?= $this->endSection() ?>
