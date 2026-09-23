<?= $this->extend('layout') ?>

<?= $this->section('content') ?>
<?php $statusLabels = ['open' => '처리 중', 'pending' => '고객 확인 대기', 'closed' => '종료']; ?>
<p><a href="<?= esc(site_url('admin/tickets')) ?>">문의함으로</a></p>
<h1><?= esc($ticket['number']) ?>: <?= esc($ticket['subject']) ?></h1>
<p>고객: <?= esc($ticket['requester_name'] !== '' ? $ticket['requester_name'] : '고객') ?><?php if ($ticket['requester_email'] !== null && $ticket['requester_email'] !== ''): ?> (<?= esc($ticket['requester_email']) ?>)<?php endif ?></p>
<p>상태: <?= esc($statusLabels[$ticket['status']] ?? '알 수 없음') ?></p>

<form method="post" action="<?= esc(site_url('admin/tickets/' . $ticket['id'] . '/status')) ?>">
    <?= csrf_field() ?>
    <label for="status">상태 변경</label>
    <select id="status" name="status">
        <option value="open" <?= $ticket['status'] === 'open' ? 'selected' : '' ?>>처리 중</option>
        <option value="pending" <?= $ticket['status'] === 'pending' ? 'selected' : '' ?>>고객 확인 대기</option>
        <option value="closed" <?= $ticket['status'] === 'closed' ? 'selected' : '' ?>>종료</option>
    </select>
    <button type="submit">상태 저장</button>
</form>

<h2>대화 및 내부 메모</h2>
<?php foreach ($messages as $message): ?>
    <?php $kindLabels = ['customer' => '고객', 'staff' => '고객 공개 답변', 'note' => '내부 메모']; ?>
    <article>
        <h3><?= esc($kindLabels[$message['kind']] ?? '알 수 없음') ?></h3>
        <p><time datetime="<?= esc($message['created_at']) ?>"><?= esc($message['created_at']) ?></time><?php if ($message['author_name'] !== null && $message['author_name'] !== ''): ?> · <?= esc($message['author_name']) ?><?php endif ?></p>
        <p><?= nl2br(esc($message['body'])) ?></p>
    </article>
<?php endforeach ?>

<h2>고객 답변</h2>
<form method="post" action="<?= esc(site_url('admin/tickets/' . $ticket['id'] . '/replies')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="request_key" value="<?= esc($replyRequestKey) ?>">
    <label for="reply_body">고객에게 보낼 내용</label>
    <textarea id="reply_body" name="body" rows="8" maxlength="10000" required><?= esc(old('body')) ?></textarea><br>
    <button type="submit">답변 등록</button>
</form>

<h2>내부 메모</h2>
<p>내부 메모는 고객 화면에 표시되지 않습니다.</p>
<form method="post" action="<?= esc(site_url('admin/tickets/' . $ticket['id'] . '/notes')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="request_key" value="<?= esc($noteRequestKey) ?>">
    <label for="note_body">내부 메모</label>
    <textarea id="note_body" name="body" rows="6" maxlength="10000" required><?= esc(old('body')) ?></textarea><br>
    <button type="submit">메모 등록</button>
</form>
<?= $this->endSection() ?>
