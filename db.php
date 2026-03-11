<?php
// db.php - 数据库连接与基本初始化

define('DB_PATH', __DIR__ . '/data/zettel.db');

function get_db(): PDO {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // 确保数据库文件存在（SQLite 会自动创建）
        if (!file_exists(DB_PATH)) {
            touch(DB_PATH);
            chmod(DB_PATH, 0666); // 根据服务器权限调整
        }
    }
    return $db;
}
