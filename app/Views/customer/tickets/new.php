<?= $this->extend('layout') ?>

<?= $this->section('content') ?>
<h1>문의하기</h1>
<p>문의 번호와 조회 비밀번호로 답변을 확인할 수 있습니다. 두 정보를 꼭 보관해 주세요.</p>

<form method="post" action="<?= esc(site_url('tickets')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="request_key" value="<?= esc($requestKey) ?>">

    <p>
        <label for="requester_name">이름 (선택)</label>
        <input id="requester_name" name="requester_name" type="text" maxlength="100" value="<?= esc(old('requester_name')) ?>">
    </p>
    <p>
        <label for="requester_email">이메일 (선택)</label>
        <input id="requester_email" name="requester_email" type="email" maxlength="254" value="<?= esc(old('requester_email')) ?>">
    </p>
    <p>
        <label for="subject">제목</label>
        <input id="subject" name="subject" type="text" maxlength="200" required value="<?= esc(old('subject')) ?>">
    </p>
    <p>
        <label for="lookup_password">조회 비밀번호</label>
        <input id="lookup_password" name="lookup_password" type="password" minlength="8" maxlength="72" required autocomplete="new-password">
    </p>
    <p>
        <label for="body">문의 내용</label>
        <textarea id="body" name="body" rows="10" maxlength="10000" required><?= esc(old('body')) ?></textarea>
    </p>
    <button type="submit">문의 접수</button>
</form>

<p><a href="<?= esc(site_url('tickets/lookup')) ?>">기존 문의 조회</a></p>
<?= $this->endSection() ?>
