<?= $this->extend('layout') ?>

<?= $this->section('content') ?>
<h1>도움말</h1>
<p>자주 묻는 질문을 검색하거나 공개된 안내 문서를 확인해 보세요.</p>

<form method="get" action="<?= esc(site_url('knowledge')) ?>">
    <label for="q">FAQ 검색</label>
    <input id="q" name="q" type="search" value="<?= esc($query) ?>">
    <button type="submit">검색</button>
</form>

<?php if ($query !== ''): ?>
    <h2>FAQ 검색 결과</h2>
    <?php if ($results === []): ?>
        <p>일치하는 공개 FAQ가 없습니다.</p>
    <?php else: ?>
        <?php foreach ($results as $document): ?>
            <article>
                <h3><a href="<?= esc(site_url('knowledge/' . $document['id'])) ?>"><?= esc($document['title']) ?></a></h3>
                <p><?= nl2br(esc($document['body'])) ?></p>
            </article>
        <?php endforeach ?>
    <?php endif ?>
<?php else: ?>
    <h2>공개 문서</h2>
    <?php if ($documents === []): ?>
        <p>현재 공개된 문서가 없습니다.</p>
    <?php else: ?>
        <?php foreach ($documents as $document): ?>
            <?php $kindLabels = ['faq' => 'FAQ', 'notice' => '공지', 'document' => '문서']; ?>
            <article>
                <p><?= esc($kindLabels[$document['kind']] ?? '문서') ?></p>
                <h3><a href="<?= esc(site_url('knowledge/' . $document['id'])) ?>"><?= esc($document['title']) ?></a></h3>
                <p><?= nl2br(esc($document['body'])) ?></p>
            </article>
        <?php endforeach ?>
    <?php endif ?>
<?php endif ?>
<?= $this->endSection() ?>
