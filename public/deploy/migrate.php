<?php
/**
 * ONE-TIME DB migration endpoint — chỉ chạy được 1 LẦN DUY NHẤT.
 * Sau khi chạy thành công, tạo file khoá (.migrated) — mọi lần gọi sau sẽ bị từ chối,
 * trừ khi gọi kèm &force=1 (dùng khi cố ý muốn reset lại toàn bộ DB).
 *
 * Chạy: https://coffeegame.plt.pro.vn/deploy/migrate.php?token=XXXX
 * Reset thủ công (XOÁ SẠCH DATA): .../migrate.php?token=XXXX&force=1
 *
 * QUAN TRỌNG:
 * - Đổi MIGRATE_TOKEN bên dưới thành chuỗi bí mật riêng (hoặc set qua biến môi trường MIGRATE_TOKEN).
 * - File này đặt trong /public để truy cập được qua URL, ví dụ: public/deploy/migrate.php
 */

require_once dirname(__DIR__, 2) . '/app/config/config.php';

define('MIGRATE_TOKEN', getenv('MIGRATE_TOKEN') ?: 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET');
define('SCHEMA_FILE', ROOT_PATH . '/database/schema.sql');
define('LOCK_FILE', ROOT_PATH . '/database/.migrated');

header('Content-Type: text/plain; charset=utf-8');

// ==== BẢO VỆ BẰNG TOKEN ====
$token = $_GET['token'] ?? '';
if (!hash_equals(MIGRATE_TOKEN, $token)) {
    http_response_code(403);
    exit("Forbidden.\n");
}

// ==== CHỐNG CHẠY LẶP LẠI ====
$force = ($_GET['force'] ?? '') === '1';
if (file_exists(LOCK_FILE) && !$force) {
    $when = file_get_contents(LOCK_FILE);
    http_response_code(409);
    exit("Da migrate roi (luc: {$when}). Bo qua de tranh xoa data.\n" .
         "Neu that su muon chay lai va XOA SACH DATA HIEN TAI, goi voi &force=1\n");
}

if (!file_exists(SCHEMA_FILE)) {
    http_response_code(500);
    exit("Schema file not found at: " . SCHEMA_FILE . "\n");
}

// ==== KẾT NỐI & CHẠY SCHEMA ====
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $sql = file_get_contents(SCHEMA_FILE);

    // Bỏ CREATE DATABASE / USE vì DB đã tồn tại sẵn, user DB shared hosting
    // thường không có quyền CREATE DATABASE
    $sql = preg_replace('/CREATE DATABASE.*?;/is', '', $sql);
    $sql = preg_replace('/USE\s+\w+\s*;/i', '', $sql);

    $statements = array_filter(array_map('trim', explode(";\n", $sql)));

    $pdo->beginTransaction();
    $count = 0;
    foreach ($statements as $stmt) {
        if ($stmt === '' || str_starts_with($stmt, '--')) continue;
        $pdo->exec($stmt);
        $count++;
    }
    $pdo->commit();

    // Ghi file khoá — mọi lần gọi sau sẽ bị chặn
    file_put_contents(LOCK_FILE, date('Y-m-d H:i:s'));

    echo "OK. Da chay {$count} cau lenh SQL thanh cong.\n";
    echo "Database: " . DB_NAME . "\n";
    echo "Da tao file khoa tai: " . LOCK_FILE . "\n";
    echo "Cac lan goi tiep theo se bi tu choi (tru khi dung &force=1).\n";

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo "LOI: " . $e->getMessage() . "\n";
}
