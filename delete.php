<?php
require_once __DIR__ . '/db.php';

$db = get_db();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("缺少ID");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare("DELETE FROM zettel WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: index.php");
    exit;
}

$stmt = $db->prepare("SELECT card_id, title FROM zettel WHERE id = ?");
$stmt->execute([$id]);
$card = $stmt->fetch();
if (!$card) die("卡片不存在");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="UTF-8"><title>删除确认</title></head>
<body>
<h2>确认删除这张卡片？</h2>
<p>
    <strong><?= htmlspecialchars($card['card_id']) ?></strong>  
    <?= htmlspecialchars($card['title'] ?: '(无标题)') ?>
</p>

<form method="post">
    <button type="submit" style="background:#dc3545;color:white;padding:0.6em 1.2em;border:none;border-radius:6px;">确认删除</button>
    <a href="view.php?id=<?= $id ?>" style="margin-left:2rem;">取消</a>
</form>
</body>
</html>
