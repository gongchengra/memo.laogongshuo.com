<?php
// tag.php - 按标签查看卡片

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/func.php';

$db = get_db();

$tag_name = trim($_GET['name'] ?? '');

if ($tag_name === '') {
    header("Location: index.php");
    exit;
}

// ================================================
// 查询该标签下的所有卡片
$stmt = $db->prepare("
    SELECT
        z.id,
        z.card_id,
        z.title,
        z.created_at,
        z.updated_at,
        z.reading_state,
        (SELECT GROUP_CONCAT(t2.name, '、') 
         FROM zettel_tag zt2 
         JOIN tag t2 ON t2.id = zt2.tag_id 
         WHERE zt2.zettel_id = z.id) AS tags
    FROM zettel z
    JOIN zettel_tag zt ON zt.zettel_id = z.id
    JOIN tag t ON t.id = zt.tag_id
    WHERE t.name = ? COLLATE NOCASE
    ORDER BY z.updated_at DESC
");
$stmt->execute([$tag_name]);
$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>标签: <?= htmlspecialchars($tag_name) ?> - 卡片盒</title>
  <style>
    body {
      font-family: system-ui, -apple-system, sans-serif;
      max-width: 960px;
      margin: 2rem auto;
      line-height: 1.6;
      padding: 0 1rem;
    }
    h1 { margin-bottom: 1.5rem; }
    .card {
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 1.2rem;
      margin-bottom: 1.2rem;
      background: #fff;
    }
    .card-id {
      font-family: monospace;
      color: #666;
      font-size: 0.95em;
      margin-bottom: 0.4rem;
    }
    .meta {
      color: #777;
      font-size: 0.92em;
      margin: 0.6rem 0;
    }
    .tags span, .tags a {
      background: #f0f4f8;
      color: #336;
      padding: 3px 8px;
      border-radius: 4px;
      margin-right: 0.5rem;
      font-size: 0.9em;
      text-decoration: none;
    }
    .tags a:hover {
      background: #e0e8f0;
    }
    .actions a {
      margin-right: 1rem;
      color: #0066cc;
      text-decoration: none;
    }
    .actions a:hover { text-decoration: underline; }
  </style>
</head>
<body>

<h1>标签：<?= htmlspecialchars($tag_name) ?></h1>

<p>
  <a href="index.php" style="color: #666; text-decoration: none;">&larr; 返回首页</a>
</p>

<?php if (empty($cards)): ?>
  <p style="color:#777;">该标签下还没有任何卡片……</p>
<?php else: ?>

  <?php foreach ($cards as $card): ?>
  <div class="card">
    <div class="card-id"><?= htmlspecialchars($card['card_id']) ?></div>

    <h3 style="margin: 0.4rem 0;">
      <a href="view.php?id=<?= (int)$card['id'] ?>" style="color:#1a1a1a; text-decoration:none;">
        <?= htmlspecialchars($card['title'] ?: '(无标题)') ?>
      </a>
    </h3>

    <div class="meta">
      创建：<?= date('Y-m-d H:i', strtotime($card['created_at'])) ?>　｜　
      更新：<?= date('Y-m-d H:i', strtotime($card['updated_at'])) ?>　｜　
      状态：<?= htmlspecialchars($card['reading_state'] ?? 'new') ?>
    </div>

    <?php if ($card['tags']): ?>
    <div class="tags">
      标签：<?= implode(' ', array_map(function($t){
        $t = trim($t);
        return '<a href="tag.php?name=' . urlencode($t) . '">' . htmlspecialchars($t) . '</a>';
      }, explode('、', $card['tags']))) ?>
    </div>
    <?php endif; ?>

    <div class="actions" style="margin-top:1rem;">
      <a href="view.php?id=<?= (int)$card['id'] ?>">查看</a>
      <a href="edit.php?id=<?= (int)$card['id'] ?>">编辑</a>
    </div>
  </div>
  <?php endforeach; ?>

<?php endif; ?>

</body>
</html>
