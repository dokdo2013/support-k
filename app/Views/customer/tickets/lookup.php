<?= $this->extend('layout') ?>

<?= $this->section('content') ?>
<h1>문의 조회</h1>
<p>접수할 때 받은 문의 번호와 직접 정한 조회 비밀번호를 입력해 주세요.</p>

<form method="post" action="<?= esc(site_url('tickets/lookup')) ?>">
    <?= csrf_field() ?>
    <p>
        <label for="number">문의 번호</label>
        <input id="number" name="number" type="text" required value="<?= esc(old('number')) ?>" autocomplete="off">
    </p>
    <p>
        <label for="lookup_password">조회 비밀번호</label>
        <input id="lookup_password" name="lookup_password" type="password" maxlength="72" required autocomplete="current-password">
    </p>
    <button type="submit">문의 조회</button>
</form>

<p><a href="<?= esc(site_url('tickets/new')) ?>">새 문의 접수</a></p>
<?= $this->endSection() ?>
