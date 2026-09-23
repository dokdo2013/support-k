<?= $this->extend('layout') ?>

<?= $this->section('content') ?>
<?php $kindLabels = ['faq' => 'FAQ', 'notice' => '공지', 'document' => '문서']; ?>
<p><a href="<?= esc(site_url('knowledge')) ?>">도움말로</a></p>
<article>
    <p><?= esc($kindLabels[$document['kind']] ?? '문서') ?></p>
    <h1><?= esc($document['title']) ?></h1>
    <p><time datetime="<?= esc($document['updated_at']) ?>"><?= esc($document['updated_at']) ?></time></p>
    <p><?= nl2br(esc($document['body'])) ?></p>
</article>
<?= $this->endSection() ?>
