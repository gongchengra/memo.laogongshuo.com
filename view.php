<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/func.php';

$db = get_db();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die("缺少有效ID");

$stmt = $db->prepare("SELECT * FROM zettel WHERE id = ?");
$stmt->execute([$id]);
$card = $stmt->fetch();
if (!$card) die("卡片不存在");

$tags     = get_card_tags($db, $id);
$outgoing = get_outgoing_links($db, $id);
$incoming = get_incoming_links($db, $id);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($card['card_id']) ?> - 卡片详情</title>
<style>
    body { font-family:system-ui; max-width:900px; margin:2rem auto; line-height:1.7; }
    .meta { color:#555; font-size:0.95em; margin:0.8em 0; }
    .tags span, .links a { background:#f0f4f8; padding:4px 9px; border-radius:5px; margin:0 0.4em 0.4em 0; display:inline-block; }
    .section { margin:2rem 0; border-top:1px solid #eee; padding-top:1.5rem; }
</style>
</head>
<body>

<h1><?= htmlspecialchars($card['title'] ?: '（无标题）') ?></h1>

<div class="meta">
    ID: <strong><?= htmlspecialchars($card['card_id']) ?></strong>　｜　
    创建：<?= $card['created_at'] ?>　｜　
    更新：<?= $card['updated_at'] ?>　｜　
    状态：<?= htmlspecialchars($card['reading_state']) ?>
</div>

<?php if ($tags): ?>
<div>
    标签：<?php foreach ($tags as $name): ?>
        <span><?= htmlspecialchars($name) ?></span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div style="margin:2rem 0; padding:1.2rem; background:#fafafa; border:1px solid #eee; border-radius:8px; white-space:pre-wrap;">
<?= nl2br(htmlspecialchars($card['content'])) ?>
</div>

<div class="section">
    <h3>我指向的卡片（Outgoing）</h3>
    <?php if ($outgoing): ?>
        <?php foreach ($outgoing as $link): ?>
            <a href="view.php?id=<?= $link['id'] ?>"><?= htmlspecialchars($link['card_id']) ?> - <?= htmlspecialchars($link['title'] ?: '(无标题)') ?></a>
        <?php endforeach; ?>
    <?php else: ?>
        <p>暂无</p>
    <?php endif; ?>
</div>

<div class="section">
    <h3>指向我的卡片（Incoming）</h3>
    <?php if ($incoming): ?>
        <?php foreach ($incoming as $link): ?>
            <a href="view.php?id=<?= $link['id'] ?>"><?= htmlspecialchars($link['card_id']) ?> - <?= htmlspecialchars($link['title'] ?: '(无标题)') ?></a>
        <?php endforeach; ?>
    <?php else: ?>
        <p>暂无</p>
    <?php endif; ?>
</div>

<div style="margin-top:3rem;">
    <a href="edit.php?id=<?= $id ?>">编辑</a> |
    <a href="index.php">返回列表</a>
</div>

</body>
</html>
