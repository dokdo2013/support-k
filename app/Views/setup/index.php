<?= $this->extend('layout') ?>
<?= $this->section('content') ?>
<div class="narrow">
<p class="eyebrow">WELCOME TO SUPPORT K</p>
<h1>내 고객센터를 시작하세요</h1>
<p class="lead">파일 업로드와 DB 정보만 준비하면 됩니다.<br>메일과 AI는 나중에 연결할 수 있습니다.</p>
<ol class="steps"><li<?= !$verified ? ' class="active"' : '' ?>>1. 업로드 권한 확인</li><li<?= $verified ? ' class="active"' : '' ?>>2. 고객센터 설정</li><li>3. 완료</li></ol>
<?php if (!$writable): ?><div class="alert error">설정 폴더에 쓸 수 없습니다. 해당 폴더의 소유자와 쓰기 권한을 확인해 주세요.</div><?php endif ?>
<?php if (!$hasMysql): ?><div class="alert error">mysqli 확장이 없습니다. 호스팅 설정에서 MySQL 연결 확장을 켜 주세요.</div><?php endif ?>
<?php if (!$verified): ?>
<section class="panel">
    <h2>설치할 수 있는 분인지 확인할게요</h2>
    <p>다른 사람이 먼저 고객센터를 설정하지 못하도록 한 번만 확인합니다.</p>
    <ol class="instructions"><li>아래 확인 파일을 받으세요.</li><li>FTP로 <code>index.php</code>와 같은 폴더에 올려 주세요.</li><li>이 화면으로 돌아와 확인 버튼을 누르세요.</li></ol>
    <a class="button secondary" href="<?= esc(site_url('setup/proof')) ?>">확인 파일 받기</a>
    <form method="post" action="<?= esc(site_url('setup/verify')) ?>"><?= csrf_field() ?><button>업로드 확인하고 계속</button></form>
</section>
<?php else: ?>
<form class="panel" method="post" action="<?= esc(site_url('setup/install')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <h2>데이터베이스 연결</h2>
    <p class="hint">호스팅 관리 화면에 있는 DB 접속 정보를 입력해 주세요. 기존 테이블은 덮어쓰지 않습니다.</p>
    <div class="form-grid"><label>DB 호스트<input name="db_host" value="localhost" required maxlength="255"></label><label>포트<input name="db_port" type="number" value="3306" min="1" max="65535" required></label></div>
    <label>DB 이름<input name="db_name" required maxlength="64" autocomplete="off"></label>
    <label>DB 사용자<input name="db_user" required maxlength="128" autocomplete="off"></label>
    <label>DB 비밀번호<input name="db_password" type="password" autocomplete="new-password"></label>
    <details><summary>고급 DB 설정</summary><label>테이블 접두사<input name="db_prefix" value="sk_" required pattern="[a-z][a-z0-9_]{0,19}_" maxlength="21"></label></details>
    <hr><h2>내 고객센터</h2>
    <label>고객센터 이름<input name="site_name" value="고객센터" maxlength="100" required></label>
    <label>설치 주소<input name="base_url" type="url" value="<?= esc($baseUrl) ?>" required></label>
    <p class="hint">기존 사이트 아래에 설치했다면 /support/ 같은 경로도 포함해 주세요.</p>
    <label>관리자 이메일<input name="owner_email" type="email" maxlength="254" required autocomplete="username"></label>
    <label>관리자 비밀번호<input name="owner_password" type="password" minlength="12" maxlength="72" required autocomplete="new-password"></label>
    <p class="hint">12자 이상으로 정해 주세요. 메일 인증 없이 바로 로그인할 수 있습니다.</p>
    <button<?= (!$writable || !$hasMysql) ? ' disabled' : '' ?>>고객센터 설치</button>
</form>
<?php endif ?>
</div>
<?= $this->endSection() ?>
