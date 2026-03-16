<?php
// =====================================================
// ĐỔI 5 DÒNG NÀY THEO HOSTING CỦA BẠN
// =====================================================
define('DB_HOST', 'sql210.infinityfree.com');
define('DB_NAME', 'if0_40445480_vlute');   // vd: mysite_gpa
define('DB_USER', 'if0_40445480');       // vd: mysite_user
define('DB_PASS', 'btruongga12');
define('JWT_SECRET', 'THAY_BANG_CHUOI_BAT_KY_DAI_IT_NHAT_32_KY_TU');
// =====================================================

define('JWT_EXPIRE', 30 * 24 * 3600); // token sống 30 ngày

function getDB() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Lỗi kết nối database: ' . $e->getMessage()]);
        exit;
    }
    return $pdo;
}
