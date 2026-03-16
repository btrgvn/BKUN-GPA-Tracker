<?php
session_start();
require_once __DIR__ . '/includes/config.php';

// ── Đổi mật khẩu admin ở đây ─────────────────────────────────
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin@gpa2025'); // ← ĐỔI MẬT KHẨU NÀY
// ─────────────────────────────────────────────────────────────

$db = getDB();
$error = '';
$success = '';

// Đăng nhập
if ($_POST['action'] ?? '' === 'login') {
    if ($_POST['u'] === ADMIN_USER && $_POST['p'] === ADMIN_PASS) {
        $_SESSION['admin'] = true;
    } else {
        $error = 'Sai tài khoản hoặc mật khẩu!';
    }
}

// Đăng xuất
if (($_GET['do'] ?? '') === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Kiểm tra đăng nhập
$loggedIn = !empty($_SESSION['admin']);

// Xử lý action (phải đăng nhập)
if ($loggedIn) {
    $do = $_GET['do'] ?? '';

    // Xóa user
    if ($do === 'delete' && !empty($_GET['id'])) {
        $id = (int)$_GET['id'];
        $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        $success = 'Đã xóa tài khoản!';
    }

    // Đổi mật khẩu user
    if ($do === 'changepw' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)$_POST['uid'];
        $pw = trim($_POST['newpw'] ?? '');
        if (strlen($pw) < 6) {
            $error = 'Mật khẩu tối thiểu 6 ký tự!';
        } else {
            $db->prepare('UPDATE users SET password = ? WHERE id = ?')
               ->execute([password_hash($pw, PASSWORD_BCRYPT), $id]);
            $success = 'Đã đổi mật khẩu thành công!';
        }
    }

    // Thêm user
    if ($do === 'adduser' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $u    = strtolower(trim($_POST['username'] ?? ''));
        $pw   = trim($_POST['password'] ?? '');
        $name = trim($_POST['fullname'] ?? '');
        if (!$u || !$pw || !$name) {
            $error = 'Vui lòng điền đầy đủ!';
        } elseif (strlen($pw) < 6) {
            $error = 'Mật khẩu tối thiểu 6 ký tự!';
        } else {
            try {
                $db->prepare('INSERT INTO users (username,password,full_name) VALUES (?,?,?)')
                   ->execute([$u, password_hash($pw, PASSWORD_BCRYPT), $name]);
                $success = "Đã tạo tài khoản \"$u\" thành công!";
            } catch (PDOException $e) {
                $error = 'Tên đăng nhập đã tồn tại!';
            }
        }
    }

    // Lấy danh sách users
    $search = trim($_GET['q'] ?? '');
    $sql = 'SELECT u.*, (SELECT COUNT(*) FROM hoc_ky h WHERE h.user_id=u.id) as so_hk,
                        (SELECT COUNT(*) FROM mon_hoc m JOIN hoc_ky h ON m.hoc_ky_id=h.id WHERE h.user_id=u.id) as so_mon
            FROM users u';
    if ($search) {
        $sql .= ' WHERE u.username LIKE ? OR u.full_name LIKE ?';
        $stmt = $db->prepare($sql . ' ORDER BY u.id DESC');
        $stmt->execute(["%$search%", "%$search%"]);
    } else {
        $stmt = $db->prepare($sql . ' ORDER BY u.id DESC');
        $stmt->execute();
    }
    $users = $stmt->fetchAll();
    $totalUsers = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $totalHK    = $db->query('SELECT COUNT(*) FROM hoc_ky')->fetchColumn();
    $totalMon   = $db->query('SELECT COUNT(*) FROM mon_hoc')->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Admin — BKUN</title>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--g:#0d9e73;--gd:#087a58;--gl:#e6f7f2;--bg:#f0f4f8;--card:#fff;--text:#1e293b;--muted:#64748b;--border:#e2e8f0;--red:#ef4444;--redbg:#fef2f2}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Be Vietnam Pro',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

/* BG */
.bgw{position:fixed;inset:0;z-index:0;overflow:hidden;
  background:linear-gradient(135deg,#e8f5f0,#f0f7ff 50%,#fdf4e8)}
.blob{position:absolute;border-radius:50%;filter:blur(70px);opacity:.3;animation:bm 14s ease-in-out infinite alternate}
.b1{width:500px;height:500px;background:radial-gradient(#34d399,#10b981);top:-200px;left:-150px}
.b2{width:400px;height:400px;background:radial-gradient(#60a5fa,#3b82f6);bottom:-100px;right:-100px;animation-delay:-6s}
.b3{width:300px;height:300px;background:radial-gradient(#fbbf24,#f59e0b);top:40%;right:10%;animation-delay:-3s}
@keyframes bm{0%{transform:translate(0,0) scale(1)}100%{transform:translate(25px,30px) scale(1.07)}}

/* LAYOUT */
.wrap{position:relative;z-index:1;min-height:100vh}
.inner{max-width:1100px;margin:0 auto;padding:1.5rem}

/* LOGIN */
.login-page{min-height:100vh;display:flex;align-items:center;justify-content:center}
.login-box{width:380px;background:rgba(255,255,255,.9);backdrop-filter:blur(20px);
  border:1px solid rgba(255,255,255,.7);border-radius:20px;padding:2.5rem;
  box-shadow:0 20px 60px rgba(0,0,0,.12)}
.login-logo{text-align:center;margin-bottom:2rem}
.login-logo .icon{width:64px;height:64px;border-radius:16px;margin:0 auto 12px;
  background:linear-gradient(135deg,#0d9e73,#34d399);display:flex;align-items:center;
  justify-content:center;font-size:28px;box-shadow:0 8px 24px rgba(13,158,115,.35)}
.login-logo h1{font-size:20px;font-weight:700;color:var(--text)}
.login-logo p{font-size:12px;color:var(--muted);margin-top:4px}

/* HEADER */
.header{background:rgba(255,255,255,.85);backdrop-filter:blur(16px);
  border-bottom:1px solid rgba(255,255,255,.6);position:sticky;top:0;z-index:100;padding:0 1.5rem}
.header-i{max-width:1100px;margin:0 auto;height:58px;display:flex;align-items:center;justify-content:space-between}
.logo{display:flex;align-items:center;gap:10px}
.logo-dot{width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,#0d9e73,#34d399);
  display:flex;align-items:center;justify-content:center;font-size:16px}
.logo span{font-size:15px;font-weight:700;color:var(--g)}
.admin-badge{background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600}

/* CARDS */
.card{background:rgba(255,255,255,.88);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.7);
  border-radius:14px;padding:1.25rem;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:1.5rem}
.stat{border-radius:12px;padding:1.1rem;position:relative;overflow:hidden}
.stat::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.sg::before{background:linear-gradient(90deg,#10b981,#34d399)}
.sb::before{background:linear-gradient(90deg,#3b82f6,#60a5fa)}
.sa::before{background:linear-gradient(90deg,#f59e0b,#fbbf24)}
.stat-v{font-size:28px;font-weight:700}
.stat-l{font-size:12px;color:var(--muted);margin-top:3px}
.sg .stat-v{color:var(--g)}.sb .stat-v{color:#3b82f6}.sa .stat-v{color:#d97706}

/* FORM */
.fg{margin-bottom:12px}
label{font-size:13px;font-weight:500;color:var(--muted);display:block;margin-bottom:5px}
input[type=text],input[type=password],input[type=search]{
  width:100%;padding:9px 13px;border:1.5px solid var(--border);border-radius:9px;
  background:#fff;color:var(--text);font-size:14px;font-family:inherit;transition:border-color .2s}
input:focus{outline:none;border-color:var(--g);box-shadow:0 0 0 3px rgba(13,158,115,.1)}

/* BUTTONS */
.btn{padding:9px 18px;border-radius:9px;font-size:13px;font-weight:500;cursor:pointer;
  border:1.5px solid var(--border);background:#fff;color:var(--text);font-family:inherit;
  transition:all .2s;display:inline-flex;align-items:center;gap:5px}
.btn:hover{background:#f8fafc}
.bp{background:linear-gradient(135deg,#0d9e73,#10b981);color:#fff;border-color:transparent;box-shadow:0 3px 12px rgba(13,158,115,.3)}
.bp:hover{background:linear-gradient(135deg,#087a58,#0d9e73)!important}
.br{background:#fef2f2;color:var(--red);border-color:#fecaca}
.br:hover{background:#fee2e2!important}
.bsm{padding:5px 11px;font-size:12px;border-radius:7px}
.bfl{width:100%;justify-content:center;padding:11px}

/* ALERTS */
.alert{padding:10px 14px;border-radius:9px;font-size:13px;margin-bottom:14px;font-weight:500}
.ae{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
.ao{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}

/* TABLE */
.tw{overflow-x:auto;border-radius:10px;border:1px solid var(--border);margin-top:1rem}
table{width:100%;border-collapse:collapse;font-size:13px;min-width:700px}
thead tr{background:#f8fafc}
th{padding:10px 14px;text-align:left;font-size:11px;font-weight:600;color:var(--muted);
  text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border)}
td{padding:11px 14px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#f8fffe}
.av{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#0d9e73,#34d399);
  color:#fff;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center}

/* MODAL */
.overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;
  align-items:center;justify-content:center}
.overlay.open{display:flex}
.modal{background:#fff;border-radius:16px;padding:1.75rem;width:380px;max-width:95vw;
  box-shadow:0 20px 60px rgba(0,0,0,.2);animation:mopen .25s ease}
@keyframes mopen{from{opacity:0;transform:scale(.95)}to{opacity:1;transform:scale(1)}}
.modal h3{font-size:16px;font-weight:700;margin-bottom:1.25rem;color:var(--text)}

/* MISC */
.toolbar{display:flex;gap:10px;align-items:center;margin-bottom:1rem;flex-wrap:wrap}
.toolbar form{flex:1;min-width:200px;display:flex;gap:8px}
.chip{display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:600;background:var(--gl);color:var(--gd)}
.section-title{font-size:15px;font-weight:700;margin-bottom:1rem}
.empty{text-align:center;padding:2.5rem;color:var(--muted);font-size:13px}
.mt{margin-top:1.5rem}
.footer{text-align:center;padding:2rem 1rem 1.5rem;font-size:12px;color:var(--muted)}
.footer a{color:var(--g);font-weight:600;text-decoration:none}
@media(max-width:640px){.stats{grid-template-columns:1fr 1fr}.inner{padding:1rem}}
</style>
</head>
<body>
<div class="bgw"><div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div></div>
<div class="wrap">

<?php if (!$loggedIn): ?>
<!-- ── TRANG ĐĂNG NHẬP ── -->
<div class="login-page">
  <div class="login-box">
    <div class="login-logo">
      <div class="icon">🛡️</div>
      <h1>Admin Panel</h1>
      <p>BKUN - Bảng Điểm Cá Nhân</p>
    </div>
    <?php if ($error): ?><div class="alert ae"><?= htmlspecialchars($error) ?></div><?php endif ?>
    <form method="POST">
      <input type="hidden" name="action" value="login">
      <div class="fg"><label>Tên đăng nhập</label><input type="text" name="u" placeholder="admin" required autofocus></div>
      <div class="fg"><label>Mật khẩu</label><input type="password" name="p" required></div>
      <button type="submit" class="btn bp bfl" style="margin-top:8px">Đăng nhập</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ── HEADER ── -->
<header class="header">
  <div class="header-i">
    <div class="logo">
      <div class="logo-dot">🛡️</div>
      <span>Admin Panel</span>
      <span class="admin-badge">BKUN</span>
    </div>
    <a href="?do=logout" class="btn bsm br">🚪 Đăng xuất</a>
  </div>
</header>

<div class="inner">

  <!-- THÔNG BÁO -->
  <?php if ($error): ?><div class="alert ae"><?= htmlspecialchars($error) ?></div><?php endif ?>
  <?php if ($success): ?><div class="alert ao"><?= htmlspecialchars($success) ?></div><?php endif ?>

  <!-- THỐNG KÊ TỔNG -->
  <div class="stats">
    <div class="card stat sg">
      <div class="stat-v"><?= $totalUsers ?></div>
      <div class="stat-l">Tổng người dùng</div>
    </div>
    <div class="card stat sb">
      <div class="stat-v"><?= $totalHK ?></div>
      <div class="stat-l">Tổng học kỳ</div>
    </div>
    <div class="card stat sa">
      <div class="stat-v"><?= $totalMon ?></div>
      <div class="stat-l">Tổng môn học</div>
    </div>
  </div>

  <!-- QUẢN LÝ USER -->
  <div class="card">
    <div class="toolbar">
      <span class="section-title" style="margin:0">Danh sách tài khoản</span>
      <form method="GET" style="flex:1;min-width:180px;display:flex;gap:8px">
        <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="🔍 Tìm theo tên, username..." style="flex:1">
        <button class="btn bsm">Tìm</button>
        <?php if ($search): ?><a href="admin.php" class="btn bsm">✕</a><?php endif ?>
      </form>
      <button class="btn bp bsm" onclick="openAdd()">+ Thêm tài khoản</button>
    </div>

    <?php if (empty($users)): ?>
      <div class="empty">Không tìm thấy tài khoản nào</div>
    <?php else: ?>
    <div class="tw">
      <table>
        <thead><tr>
          <th>#</th><th>Người dùng</th><th>Tên đăng nhập</th>
          <th style="text-align:center">Học kỳ</th><th style="text-align:center">Môn học</th>
          <th>Ngày tạo</th><th style="text-align:right">Thao tác</th>
        </tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td style="color:var(--muted)"><?= $u['id'] ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <div class="av"><?= mb_strtoupper(mb_substr($u['full_name'],0,1)) ?></div>
                <div>
                  <div style="font-weight:600"><?= htmlspecialchars($u['full_name']) ?></div>
                </div>
              </div>
            </td>
            <td><span class="chip"><?= htmlspecialchars($u['username']) ?></span></td>
            <td style="text-align:center;font-weight:600;color:#3b82f6"><?= $u['so_hk'] ?></td>
            <td style="text-align:center;font-weight:600;color:var(--g)"><?= $u['so_mon'] ?></td>
            <td style="color:var(--muted);font-size:12px"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
            <td style="text-align:right;white-space:nowrap">
              <button class="btn bsm" onclick="openChangePw(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')">🔑 Đổi MK</button>
              <a href="?do=delete&id=<?= $u['id'] ?>" class="btn bsm br" style="margin-left:4px"
                onclick="return confirm('Xóa tài khoản <?= htmlspecialchars($u['username']) ?>?\nTất cả dữ liệu điểm sẽ bị xóa!')">🗑️ Xóa</a>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php endif ?>
  </div>

</div><!-- /inner -->

<footer class="footer">Powered by <a href="https://fb.com/baotruong.ga" target="_blank">Kun</a> · Admin Panel</footer>

<!-- MODAL ĐỔI MẬT KHẨU -->
<div class="overlay" id="pw-modal">
  <div class="modal">
    <h3>🔑 Đổi mật khẩu</h3>
    <form method="POST" action="?do=changepw">
      <input type="hidden" name="uid" id="pw-uid">
      <div class="fg">
        <label>Tài khoản: <strong id="pw-uname" style="color:var(--g)"></strong></label>
      </div>
      <div class="fg">
        <label>Mật khẩu mới <span style="font-size:11px;color:#aaa">(tối thiểu 6 ký tự)</span></label>
        <input type="password" name="newpw" id="pw-input" placeholder="Nhập mật khẩu mới" required>
      </div>
      <div style="display:flex;gap:8px;margin-top:14px">
        <button type="submit" class="btn bp" style="flex:1;justify-content:center">Lưu</button>
        <button type="button" class="btn" onclick="closeModal('pw-modal')">Hủy</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL THÊM TÀI KHOẢN -->
<div class="overlay" id="add-modal">
  <div class="modal">
    <h3>➕ Thêm tài khoản mới</h3>
    <form method="POST" action="?do=adduser">
      <div class="fg"><label>Họ và tên</label><input type="text" name="fullname" placeholder="Nguyễn Văn A" required></div>
      <div class="fg"><label>Tên đăng nhập</label><input type="text" name="username" placeholder="MSSV hoặc username" required></div>
      <div class="fg"><label>Mật khẩu</label><input type="password" name="password" placeholder="Tối thiểu 6 ký tự" required></div>
      <div style="display:flex;gap:8px;margin-top:14px">
        <button type="submit" class="btn bp" style="flex:1;justify-content:center">Tạo tài khoản</button>
        <button type="button" class="btn" onclick="closeModal('add-modal')">Hủy</button>
      </div>
    </form>
  </div>
</div>

<script>
function openChangePw(id, uname) {
  document.getElementById('pw-uid').value = id;
  document.getElementById('pw-uname').textContent = uname;
  document.getElementById('pw-input').value = '';
  document.getElementById('pw-modal').classList.add('open');
  setTimeout(()=>document.getElementById('pw-input').focus(), 100);
}
function openAdd() {
  document.getElementById('add-modal').classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}
// Click overlay để đóng
document.querySelectorAll('.overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target === o) o.classList.remove('open'); });
});
</script>

<?php endif ?>
</div><!-- /wrap -->
</body>
</html>
