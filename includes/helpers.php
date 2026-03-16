<?php
// ── JWT ───────────────────────────────────────────────────────
function b64url_enc($d){ return rtrim(strtr(base64_encode($d),'+/','-_'),'='); }
function b64url_dec($d){ return base64_decode(strtr($d,'-_','+/').str_repeat('=',(4-strlen($d)%4)%4)); }

function jwt_create($payload) {
    $h = b64url_enc(json_encode(['alg'=>'HS256','typ'=>'JWT']));
    $payload['exp'] = time() + JWT_EXPIRE;
    $p = b64url_enc(json_encode($payload));
    $s = b64url_enc(hash_hmac('sha256', "$h.$p", JWT_SECRET, true));
    return "$h.$p.$s";
}

function jwt_verify($token) {
    if (!$token) return false;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    [$h, $p, $s] = $parts;
    $expected = b64url_enc(hash_hmac('sha256', "$h.$p", JWT_SECRET, true));
    if (!hash_equals($expected, $s)) return false;
    $data = json_decode(b64url_dec($p), true);
    if (!$data || (isset($data['exp']) && $data['exp'] < time())) return false;
    return $data;
}

// ── Lấy token - InfinityFree fix ─────────────────────────────
// InfinityFree dùng PHP 8 + PHP-FPM:
//   - getallheaders() bị tắt
//   - Authorization header không vào $_SERVER tự động
//   - Cần .htaccess RewriteRule để chuyển vào HTTP_AUTHORIZATION
//   - Fallback: gửi token qua URL ?token=xxx
function getToken() {
    // 1. Thử các biến $_SERVER — sẽ hoạt động sau khi .htaccess fix
    foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $k) {
        if (!empty($_SERVER[$k])) {
            $v = $_SERVER[$k];
            if (stripos($v, 'Bearer ') === 0) return trim(substr($v, 7));
            return trim($v);
        }
    }

    // 2. URL param ?token=xxx — LUÔN hoạt động trên mọi hosting
    if (!empty($_GET['token'])) return trim($_GET['token']);

    // 3. JSON body _token
    static $b = null;
    if ($b === null) {
        $raw = file_get_contents('php://input');
        $b = ($raw !== '' && $raw !== false) ? (json_decode($raw, true) ?? []) : [];
    }
    if (!empty($b['_token'])) return trim($b['_token']);

    return '';
}

function requireAuth() {
    $payload = jwt_verify(getToken());
    if (!$payload) {
        http_response_code(401);
        echo json_encode(['error' => 'Chưa đăng nhập hoặc phiên hết hạn']);
        exit;
    }
    return $payload;
}

// ── Tính điểm HP theo quy định VLUTE ─────────────────────────
function tinhDiem($loai, $hs1, $hs2arr, $hs3, $thArr) {
    if ($loai === 'thuchanh') {
        $arr = array_values(array_filter((array)$thArr, fn($x) => $x !== null && $x !== '' && is_numeric($x)));
        if (!count($arr)) return [null, null, null];
        $diem = round(array_sum($arr) / count($arr), 1);
    } else {
        if ($hs1 === null || $hs1 === '' || $hs3 === null || $hs3 === '') return [null, null, null];
        $arr  = array_map('floatval', array_filter((array)$hs2arr, fn($x) => $x !== null && $x !== ''));
        $m    = count($arr);
        $n    = 1 + 2 * $m + 3;
        $diem = round((floatval($hs1) + 2 * array_sum($arr) + 3 * floatval($hs3)) / $n, 1);
    }
    [$chu, $gpa4] = getGrade($diem);
    return [$diem, $chu, $gpa4];
}

function getGrade($d) {
    $t = [[8.5,'A',4.0],[7.8,'B+',3.5],[7.0,'B',3.0],[6.3,'C+',2.5],
          [5.5,'C',2.0],[4.8,'D+',1.5],[4.0,'D',1.0],[0,'F',0.0]];
    foreach ($t as [$min,$chu,$gpa]) if ($d >= $min) return [$chu,$gpa];
    return ['F', 0.0];
}

function ok($data = []) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function err($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
