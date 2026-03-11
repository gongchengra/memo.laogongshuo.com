<?php
// memo/index.php

require_once __DIR__ . '/db.php';

$db = get_db();   // 来自 db.php，返回 PDO 对象

// ================================================
// 参数获取（简单版：页码 + 每页条数）
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// ================================================
// 主查询：最近更新的卡片 + 标签
$stmt = $db->prepare("
    SELECT
        z.id,
        z.card_id,
        z.title,
        z.created_at,
        z.updated_at,
        z.reading_state,
        GROUP_CONCAT(t.name, '、') AS tags
    FROM zettel z
    LEFT JOIN zettel_tag zt ON zt.zettel_id = z.id
    LEFT JOIN tag t ON t.id = zt.tag_id
    GROUP BY z.id
    ORDER BY z.updated_at DESC
    LIMIT :limit OFFSET :offset
");

$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================================================
// 统计总条数（用于简单分页）
$count_stmt = $db->query("SELECT COUNT(*) FROM zettel");
$total = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total / $per_page));

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>卡片盒 - 首页</title>
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
    .tags span {
      background: #f0f4f8;
      color: #336;
      padding: 3px 8px;
      border-radius: 4px;
      margin-right: 0.5rem;
      font-size: 0.9em;
    }
    .actions a {
      margin-right: 1rem;
      color: #0066cc;
      text-decoration: none;
    }
    .actions a:hover { text-decoration: underline; }
    .pagination {
      margin: 2rem 0;
      text-align: center;
    }
    .pagination a {
      margin: 0 0.4rem;
      padding: 0.4rem 0.8rem;
      border: 1px solid #ccc;
      border-radius: 4px;
      text-decoration: none;
    }
    .pagination a.active {
      background: #0066cc;
      color: white;
      border-color: #0066cc;
    }
    .new-btn {
      display: inline-block;
      background: #28a745;
      color: white;
      padding: 0.6rem 1.2rem;
      border-radius: 6px;
      text-decoration: none;
      margin-bottom: 1.5rem;
    }
  </style>
</head>
<body>

<h1>卡片盒</h1>

<form action="search.php" method="get" style="margin-bottom:2rem;">
    <input type="search" name="q" placeholder="搜索卡片（支持全文搜索）" style="width:300px;padding:0.6em;">
    <button type="submit">搜索</button>
</form>

<p>
  <a href="edit.php" class="new-btn">+ 新建卡片</a>
</p>

<?php if (empty($cards)): ?>
  <p style="color:#777;">还没有任何卡片……</p>
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
        return '<span>' . htmlspecialchars(trim($t)) . '</span>';
      }, explode('、', $card['tags']))) ?>
    </div>
    <?php endif; ?>

    <div class="actions" style="margin-top:1rem;">
      <a href="view.php?id=<?= (int)$card['id'] ?>">查看</a>
      <a href="edit.php?id=<?= (int)$card['id'] ?>">编辑</a>
      <a href="delete.php?id=<?= (int)$card['id'] ?>"
         onclick="return confirm('确定要删除这张卡片吗？')">删除</a>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- 简单分页 -->
  <?php if ($total_pages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="?page=<?= $page-1 ?>">&laquo; 上一页</a>
    <?php endif; ?>

    <?php
    $start = max(1, $page - 2);
    $end   = min($total_pages, $page + 2);
    for ($i = $start; $i <= $end; $i++): ?>
      <a href="?page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>">
        <?= $i ?>
      </a>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
      <a href="?page=<?= $page+1 ?>">下一页 &raquo;</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

<?php endif; ?>

</body>
</html>
