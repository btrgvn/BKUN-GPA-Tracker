<?php
// ── Headers ──────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';

// ── Parse request ─────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$action = trim($_GET['action'] ?? '');
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Đọc body — tránh lỗi khi body rỗng
$rawBody = file_get_contents('php://input');
$body    = ($rawBody !== '' && $rawBody !== false)
           ? (json_decode($rawBody, true) ?? [])
           : [];

// ── Router ────────────────────────────────────────────────────
if ($action === 'ping') {
    // Endpoint test kết nối
    ok(['status' => 'ok', 'php' => PHP_VERSION, 'time' => date('Y-m-d H:i:s')]);
}

if ($action === 'register' && $method === 'POST') { doRegister($body); }
elseif ($action === 'login'    && $method === 'POST') { doLogin($body); }
elseif ($action === 'hocky') {
    if      ($method === 'GET')    hockyList();
    elseif  ($method === 'POST')   hockyCreate($body);
    elseif  ($method === 'DELETE') hockyDelete($id);
    else err('Method not allowed', 405);
}
elseif ($action === 'mon') {
    if      ($method === 'GET')    monList($id);       // id = hoc_ky_id
    elseif  ($method === 'POST')   monCreate($id, $body); // id = hoc_ky_id
    elseif  ($method === 'PUT')    monUpdate($id, $body); // id = mon_hoc.id
    elseif  ($method === 'DELETE') monDelete($id);        // id = mon_hoc.id
    else err('Method not allowed', 405);
}
else {
    err("action không hợp lệ: '$action'. Dùng: register|login|hocky|mon", 404);
}

// ═══════════════════════════════════════════════════════════════
// AUTH
// ═══════════════════════════════════════════════════════════════
function doRegister($b) {
    $db   = getDB();
    $u    = strtolower(trim($b['username']  ?? ''));
    $pw   = $b['password']  ?? '';
    $name = trim($b['full_name'] ?? '');

    if (!$u || !$pw || !$name) err('Vui lòng điền đầy đủ thông tin');
    if (strlen($pw) < 6)       err('Mật khẩu tối thiểu 6 ký tự');
    if (strlen($u) < 3)        err('Tên đăng nhập tối thiểu 3 ký tự');

    try {
        $db->prepare('INSERT INTO users (username,password,full_name) VALUES (?,?,?)')
           ->execute([$u, password_hash($pw, PASSWORD_BCRYPT), $name]);
        ok(['message' => 'Đăng ký thành công']);
    } catch (PDOException $e) {
        if ((int)$e->getCode() === 23000 || str_contains($e->getMessage(), 'Duplicate'))
            err('Tên đăng nhập đã tồn tại');
        err('Lỗi DB: ' . $e->getMessage(), 500);
    }
}

function doLogin($b) {
    $db = getDB();
    $u  = strtolower(trim($b['username'] ?? ''));
    $pw = $b['password'] ?? '';
    if (!$u || !$pw) err('Vui lòng nhập đầy đủ');

    $s = $db->prepare('SELECT * FROM users WHERE username=?');
    $s->execute([$u]);
    $user = $s->fetch();

    if (!$user)                                    err('Tên đăng nhập không tồn tại');
    if (!password_verify($pw, $user['password']))  err('Sai mật khẩu');

    $token = jwt_create([
        'id'        => (int)$user['id'],
        'username'  => $u,
        'full_name' => $user['full_name'],
    ]);
    ok(['token' => $token, 'full_name' => $user['full_name'], 'username' => $u]);
}

// ═══════════════════════════════════════════════════════════════
// HỌC KỲ
// ═══════════════════════════════════════════════════════════════
function hockyList() {
    $me = requireAuth(); $db = getDB();
    $s  = $db->prepare('SELECT * FROM hoc_ky WHERE user_id=? ORDER BY thu_tu,id');
    $s->execute([$me['id']]);
    ok($s->fetchAll());
}

function hockyCreate($b) {
    $me  = requireAuth(); $db = getDB();
    $ten = trim($b['ten'] ?? '');
    if (!$ten) err('Tên học kỳ không được để trống');

    $s = $db->prepare('SELECT COUNT(*) FROM hoc_ky WHERE user_id=?');
    $s->execute([$me['id']]);
    $ord = (int)$s->fetchColumn() + 1;

    $db->prepare('INSERT INTO hoc_ky (user_id,ten,thu_tu) VALUES (?,?,?)')
       ->execute([$me['id'], $ten, $ord]);
    ok(['id' => (int)$db->lastInsertId(), 'ten' => $ten, 'thu_tu' => $ord, 'user_id' => $me['id']]);
}

function hockyDelete($id) {
    $me = requireAuth(); $db = getDB();
    if (!$id) err('Thiếu id học kỳ');
    $db->prepare('DELETE FROM hoc_ky WHERE id=? AND user_id=?')->execute([$id, $me['id']]);
    ok(['ok' => true]);
}

// ═══════════════════════════════════════════════════════════════
// MÔN HỌC
// ═══════════════════════════════════════════════════════════════
function monList($hkId) {
    $me = requireAuth(); $db = getDB();
    if (!$hkId) err('Thiếu id học kỳ');

    // Xác nhận học kỳ thuộc user
    $chk = $db->prepare('SELECT id FROM hoc_ky WHERE id=? AND user_id=?');
    $chk->execute([$hkId, $me['id']]);
    if (!$chk->fetch()) err('Học kỳ không tồn tại', 404);

    $s = $db->prepare('SELECT * FROM mon_hoc WHERE hoc_ky_id=? ORDER BY id');
    $s->execute([$hkId]);
    $rows = $s->fetchAll();
    foreach ($rows as &$r) {
        $r['diem_hs2']      = json_decode($r['diem_hs2']      ?? '[]', true) ?: [];
        $r['diem_thuchanh'] = json_decode($r['diem_thuchanh'] ?? '[]', true) ?: [];
        $r['tin_chi']       = (int)$r['tin_chi'];
        $r['id']            = (int)$r['id'];
        $r['hoc_ky_id']     = (int)$r['hoc_ky_id'];
        $r['diem_hp']       = $r['diem_hp']  !== null ? (float)$r['diem_hp']  : null;
        $r['gpa4']          = $r['gpa4']     !== null ? (float)$r['gpa4']     : null;
    }
    ok($rows);
}

function monCreate($hkId, $b) {
    $me = requireAuth(); $db = getDB();
    if (!$hkId) err('Thiếu id học kỳ');
    if (empty($b['ten'])) err('Tên môn học không được để trống');

    $chk = $db->prepare('SELECT id FROM hoc_ky WHERE id=? AND user_id=?');
    $chk->execute([$hkId, $me['id']]);
    if (!$chk->fetch()) err('Không có quyền', 403);

    $loai = $b['loai'] ?? 'lytuyet';
    [$diem, $chu, $gpa4] = tinhDiem($loai, $b['diem_hs1']??null, $b['diem_hs2']??[], $b['diem_hs3']??null, $b['diem_thuchanh']??[]);

    $db->prepare(
        'INSERT INTO mon_hoc (hoc_ky_id,ten,tin_chi,loai,diem_hs1,diem_hs2,diem_hs3,diem_thuchanh,diem_hp,diem_chu,gpa4)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $hkId, trim($b['ten']), (int)($b['tin_chi']??3), $loai,
        isset($b['diem_hs1']) && $b['diem_hs1'] !== '' ? (float)$b['diem_hs1'] : null,
        json_encode($b['diem_hs2'] ?? []),
        isset($b['diem_hs3']) && $b['diem_hs3'] !== '' ? (float)$b['diem_hs3'] : null,
        json_encode($b['diem_thuchanh'] ?? []),
        $diem, $chu, $gpa4,
    ]);

    $newId = (int)$db->lastInsertId();
    ok(array_merge($b, ['id'=>$newId,'hoc_ky_id'=>$hkId,'diem_hp'=>$diem,'diem_chu'=>$chu,'gpa4'=>$gpa4]));
}

function monUpdate($id, $b) {
    $me = requireAuth(); $db = getDB();
    if (!$id) err('Thiếu id môn học');

    $loai = $b['loai'] ?? 'lytuyet';
    [$diem, $chu, $gpa4] = tinhDiem($loai, $b['diem_hs1']??null, $b['diem_hs2']??[], $b['diem_hs3']??null, $b['diem_thuchanh']??[]);

    $db->prepare(
        'UPDATE mon_hoc m JOIN hoc_ky h ON m.hoc_ky_id=h.id
         SET m.ten=?,m.tin_chi=?,m.loai=?,m.diem_hs1=?,m.diem_hs2=?,
             m.diem_hs3=?,m.diem_thuchanh=?,m.diem_hp=?,m.diem_chu=?,m.gpa4=?
         WHERE m.id=? AND h.user_id=?'
    )->execute([
        trim($b['ten']), (int)($b['tin_chi']??3), $loai,
        isset($b['diem_hs1']) && $b['diem_hs1'] !== '' ? (float)$b['diem_hs1'] : null,
        json_encode($b['diem_hs2'] ?? []),
        isset($b['diem_hs3']) && $b['diem_hs3'] !== '' ? (float)$b['diem_hs3'] : null,
        json_encode($b['diem_thuchanh'] ?? []),
        $diem, $chu, $gpa4, $id, $me['id'],
    ]);
    ok(['ok'=>true, 'diem_hp'=>$diem, 'diem_chu'=>$chu, 'gpa4'=>$gpa4]);
}

function monDelete($id) {
    $me = requireAuth(); $db = getDB();
    if (!$id) err('Thiếu id');
    $db->prepare(
        'DELETE m FROM mon_hoc m JOIN hoc_ky h ON m.hoc_ky_id=h.id WHERE m.id=? AND h.user_id=?'
    )->execute([$id, $me['id']]);
    ok(['ok' => true]);
}
