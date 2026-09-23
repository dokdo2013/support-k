<?= $this->extend('layout') ?>

<?= $this->section('content') ?>
<?php $statusLabels = ['open' => '처리 중', 'pending' => '고객 확인 대기', 'closed' => '종료']; ?>
<h1>문의 <?= esc($ticket['number']) ?></h1>
<p>상태: <?= esc($statusLabels[$ticket['status']] ?? '알 수 없음') ?></p>
<h2><?= esc($ticket['subject']) ?></h2>

<?php foreach ($messages as $message): ?>
    <article>
        <h3><?= esc($message['kind'] === 'staff' ? '담당자 답변' : '고객 답글') ?></h3>
        <p><time datetime="<?= esc($message['created_at']) ?>"><?= esc($message['created_at']) ?></time></p>
        <p><?= nl2br(esc($message['body'])) ?></p>
    </article>
<?php endforeach ?>

<h2>추가 답글</h2>
<p>추가 답글을 등록하면 종료된 문의도 다시 처리 상태로 바뀝니다.</p>
<form method="post" action="<?= esc(site_url('tickets/' . $ticket['number'] . '/replies')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="request_key" value="<?= esc($requestKey) ?>">
    <p>
        <label for="body">내용</label>
        <textarea id="body" name="body" rows="8" maxlength="10000" required><?= esc(old('body')) ?></textarea>
    </p>
    <button type="submit">답글 등록</button>
</form>

<p><a href="<?= esc(site_url('tickets/lookup')) ?>">다른 문의 조회</a></p>
<?= $this->endSection() ?>
