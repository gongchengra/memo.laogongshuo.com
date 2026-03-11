<?php
// func.php - 共同函数

require_once __DIR__ . '/db.php';

/**
 * 生成新的 card_id（时间戳 + 可选小写字母后缀）
 */
function generate_card_id(string $suffix = ''): string {
    return date('YmdHis') . $suffix;
}

/**
 * 获取所有标签（用于下拉或自动完成）
 */
function get_all_tags(PDO $db): array {
    $stmt = $db->query("SELECT id, name FROM tag ORDER BY name COLLATE NOCASE");
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // id => name
}

/**
 * 获取某张卡片的标签（数组形式）
 */
function get_card_tags(PDO $db, int $zettel_id): array {
    $stmt = $db->prepare("
        SELECT t.id, t.name
        FROM tag t
        JOIN zettel_tag zt ON zt.tag_id = t.id
        WHERE zt.zettel_id = ?
        ORDER BY t.name COLLATE NOCASE
    ");
    $stmt->execute([$zettel_id]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // id => name
}

/**
 * 保存标签（新增/更新时调用）
 * @param array $tag_names 用户输入的标签数组（trim后）
 */
function sync_card_tags(PDO $db, int $zettel_id, array $tag_names): void {
    $tag_names = array_filter(array_map('trim', $tag_names));
    if (empty($tag_names)) {
        $db->prepare("DELETE FROM zettel_tag WHERE zettel_id = ?")->execute([$zettel_id]);
        return;
    }

    // 不再开启新事务，依赖调用方的事务
    // $db->beginTransaction();   ← 删除这行
    try {
        $db->prepare("DELETE FROM zettel_tag WHERE zettel_id = ?")->execute([$zettel_id]);

        foreach ($tag_names as $name) {
            if ($name === '') continue;

            $stmt = $db->prepare("SELECT id FROM tag WHERE name = ? COLLATE NOCASE");
            $stmt->execute([$name]);
            $tag_id = $stmt->fetchColumn();

            if ($tag_id === false) {
                $ins = $db->prepare("INSERT INTO tag (name) VALUES (?)");
                $ins->execute([$name]);
                $tag_id = $db->lastInsertId();
            }

            $db->prepare("INSERT OR IGNORE INTO zettel_tag (zettel_id, tag_id) VALUES (?, ?)")
               ->execute([$zettel_id, $tag_id]);
        }

        // 不再 commit，交给外层
        // $db->commit();   ← 删除这行
    } catch (Exception $e) {
        // 也不 rollback，交给外层统一处理
        // $db->rollBack();   ← 删除这行
        throw $e;
    }
}

/**
 * 获取某张卡片的 outgoing links（我指向谁）
 */
function get_outgoing_links(PDO $db, int $zettel_id): array {
    $stmt = $db->prepare("
        SELECT 
            z.id, z.card_id, z.title, l.link_type, l.context_note
        FROM zettel_link l
        JOIN zettel z ON z.id = l.to_zettel_id
        WHERE l.from_zettel_id = ?
        ORDER BY z.card_id
    ");
    $stmt->execute([$zettel_id]);
    return $stmt->fetchAll();
}

/**
 * 获取某张卡片的 incoming links（谁指向我）
 */
function get_incoming_links(PDO $db, int $zettel_id): array {
    $stmt = $db->prepare("
        SELECT 
            z.id, z.card_id, z.title, l.link_type, l.context_note
        FROM zettel_link l
        JOIN zettel z ON z.id = l.from_zettel_id
        WHERE l.to_zettel_id = ?
        ORDER BY z.card_id
    ");
    $stmt->execute([$zettel_id]);
    return $stmt->fetchAll();
}
