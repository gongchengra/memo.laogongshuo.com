<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/func.php';

$db = get_db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$card = null;

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM zettel WHERE id = ?");
    $stmt->execute([$id]);
    $card = $stmt->fetch();
    if (!$card) die("卡片不存在");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_id       = trim($_POST['card_id'] ?? '');
    $title         = trim($_POST['title'] ?? '');
    $content       = trim($_POST['content'] ?? '');
    $tag_names     = array_unique(array_filter(array_map('trim', explode(',', $_POST['tags'] ?? ''))));
    $link_ids      = array_unique(array_filter(array_map('trim', explode(',', $_POST['links'] ?? ''))));

    if ($content === '') {
        $error = "内容不能为空";
    } else {
        try {
            $db->beginTransaction();

            if ($id > 0) {
                // 更新模式
                $zettel_id = $id;
                
                // 获取旧数据用于比较
                $old_tags = array_values(get_card_tags($db, $id));
                $old_links = array_column(get_outgoing_links($db, $id), 'card_id');
                
                // 同步标签
                sync_card_tags($db, $zettel_id, $tag_names);

                // 同步链接
                $db->prepare("DELETE FROM zettel_link WHERE from_zettel_id = ?")->execute([$zettel_id]);
                $new_links = [];
                foreach ($link_ids as $target_card_id) {
                    if ($target_card_id === $card_id) continue;
                    $stmt = $db->prepare("SELECT id FROM zettel WHERE card_id = ?");
                    $stmt->execute([$target_card_id]);
                    $to_id = $stmt->fetchColumn();
                    if ($to_id && $to_id != $zettel_id) {
                        $db->prepare("INSERT OR IGNORE INTO zettel_link (from_zettel_id, to_zettel_id) VALUES (?, ?)")
                           ->execute([$zettel_id, $to_id]);
                        $new_links[] = $target_card_id;
                    }
                }

                // 检查内容或关联是否发生变化
                $new_tags = $tag_names;
                sort($old_tags);
                sort($new_tags);
                
                sort($old_links);
                sort($new_links);

                $changed = ($card['title'] !== $title) || 
                           ($card['content'] !== $content) || 
                           ($old_tags !== $new_tags) || 
                           ($old_links !== $new_links);

                if ($changed) {
                    $stmt = $db->prepare("UPDATE zettel SET title = ?, content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$title, $content, $zettel_id]);
                } else {
                    $stmt = $db->prepare("UPDATE zettel SET title = ?, content = ? WHERE id = ?");
                    $stmt->execute([$title, $content, $zettel_id]);
                }
            } else {
                // 新建模式
                if ($card_id === '') $card_id = generate_card_id();
                $stmt = $db->prepare("INSERT INTO zettel (card_id, title, content) VALUES (?, ?, ?)");
                $stmt->execute([$card_id, $title, $content]);
                $zettel_id = $db->lastInsertId();

                // 同步标签
                sync_card_tags($db, $zettel_id, $tag_names);

                // 同步链接
                foreach ($link_ids as $target_card_id) {
                    $stmt = $db->prepare("SELECT id FROM zettel WHERE card_id = ?");
                    $stmt->execute([$target_card_id]);
                    $to_id = $stmt->fetchColumn();
                    if ($to_id && $to_id != $zettel_id) {
                        $db->prepare("INSERT OR IGNORE INTO zettel_link (from_zettel_id, to_zettel_id) VALUES (?, ?)")
                           ->execute([$zettel_id, $to_id]);
                    }
                }
            }

            $db->commit();
            header("Location: view.php?id=$zettel_id");
            exit;
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error = "保存失败：" . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title><?= $card ? '编辑' : '新建' ?>卡片</title>
<style>
    body { font-family:system-ui; max-width:900px; margin:2rem auto; }
    textarea { width:100%; height:400px; font-family:monospace; padding:0.8em; }
    input[type=text] { width:100%; padding:0.6em; margin:0.5em 0; }
    label { display:block; margin:1.2em 0 0.3em; font-weight:bold; }
    .error { color:red; }
</style>
</head>
<body>

<h1><?= $card ? '编辑卡片 ' . htmlspecialchars($card['card_id']) : '新建卡片' ?></h1>

<?php if (isset($error)): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post">
    <label>卡片ID（建议自动生成）：</label>
    <input type="text" name="card_id" value="<?= htmlspecialchars($card['card_id'] ?? generate_card_id()) ?>" required>

    <label>标题（可选）：</label>
    <input type="text" name="title" value="<?= htmlspecialchars($card['title'] ?? '') ?>">

    <label>正文：</label>
    <textarea name="content" required><?= htmlspecialchars($card['content'] ?? '') ?></textarea>

    <label>链接到的卡片ID（用半角逗号分隔，例如：20260311092345,20260311092401）：</label>
    <input type="text" name="links" value="<?= htmlspecialchars(implode(',', array_column(get_outgoing_links($db, $id), 'card_id'))) ?>">

    <label>标签（用半角逗号分隔）：</label>
    <input type="text" name="tags" value="<?= htmlspecialchars(implode(',', get_card_tags($db, $id))) ?>">

    <div style="margin:2rem 0;">
        <button type="submit">保存</button>
        <a href="index.php" style="margin-left:2rem;">返回列表</a>
    </div>
</form>

</body>
</html>
