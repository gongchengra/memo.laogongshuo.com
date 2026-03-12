<?php
// rebuild_fts.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/func.php';

$db = get_db();

echo "开始重建 FTS 索引...\n";

$db->exec("DELETE FROM zettel_fts");

$stmt = $db->query("
    SELECT id, title, content
    FROM zettel
    ORDER BY id
");

$count = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $search_content = generate_search_content($row['title'], $row['content']);

    // 更新 zettel.search_content
    $db->prepare("UPDATE zettel SET search_content = ? WHERE id = ?")
       ->execute([$search_content, $row['id']]);

    // 插入 FTS
    $db->prepare("
        INSERT INTO zettel_fts (rowid, title, search_content)
        VALUES (?, ?, ?)
    ")->execute([$row['id'], $row['title'], $search_content]);

    $count++;
    echo "已处理 $count 条\n";
}

$db->exec("INSERT INTO zettel_fts(zettel_fts) VALUES('optimize')");
echo "重建完成，共 $count 条记录。\n";
