<?= $this->extend('layout') ?>

<?= $this->section('content') ?>
<h1>문의함</h1>
<form method="get" action="<?= esc(site_url('admin/tickets')) ?>">
    <label for="status">상태</label>
    <select id="status" name="status">
        <option value="">전체</option>
        <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>처리 중</option>
        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>고객 확인 대기</option>
        <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>종료</option>
    </select>
    <label for="q">검색</label>
    <input id="q" name="q" type="search" value="<?= esc($query) ?>">
    <button type="submit">조회</button>
</form>

<?php if ($tickets === []): ?>
    <p>조건에 맞는 문의가 없습니다.</p>
<?php else: ?>
    <table>
        <thead><tr><th>번호</th><th>제목</th><th>고객</th><th>상태</th><th>최근 활동</th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $ticket): ?>
            <tr>
                <td><a href="<?= esc(site_url('admin/tickets/' . $ticket['id'])) ?>"><?= esc($ticket['number']) ?></a></td>
                <td><?= esc($ticket['subject']) ?></td>
                <td><?= esc($ticket['requester_name'] !== '' ? $ticket['requester_name'] : '고객') ?></td>
                <td><?= esc($ticket['status']) ?></td>
                <td><?= esc($ticket['last_message_at']) ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
<?= $this->endSection() ?>
