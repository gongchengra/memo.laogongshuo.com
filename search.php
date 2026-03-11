<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/func.php';

$db = get_db();

$q = trim($_GET['q'] ?? '');
$results = [];

if ($q !== '') {
    // 使用 LIKE 模糊搜尋 title + content + tags_joined
    // %q% = 包含 q 的任意位置
    $like_term = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

    $stmt = $db->prepare("
        SELECT 
            z.id, z.card_id, z.title, z.content,
            CASE 
                WHEN z.title LIKE ? THEN 1
                WHEN z.content LIKE ? THEN 2
                ELSE 3
            END AS match_priority
        FROM zettel z
        LEFT JOIN zettel_fts f ON f.rowid = z.id
        WHERE z.title LIKE ? 
           OR z.content LIKE ? 
           OR COALESCE(f.tags_joined, '') LIKE ?
        ORDER BY match_priority ASC, z.updated_at DESC
        LIMIT 30
    ");

    $stmt->execute([$like_term, $like_term, $like_term, $like_term, $like_term]);
    $results = $stmt->fetchAll();

    // 簡單高亮（可選）
    foreach ($results as &$r) {
        $r['content_snippet'] = preg_replace(
            '/(' . preg_quote($q, '/') . ')/iu',
            '<mark>$1</mark>',
            substr($r['content'], 0, 200) . (strlen($r['content']) > 200 ? '...' : '')
        );
    }
    unset($r);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>搜索 - <?= htmlspecialchars($q) ?></title>
<style>
    body { font-family:system-ui; max-width:900px; margin:2rem auto; }
    mark { background:#ffff99; padding:1px 3px; }
    .result { margin:1.5rem 0; padding-bottom:1rem; border-bottom:1px solid #eee; }
</style>
</head>
<body>

<h1>搜索：<?= htmlspecialchars($q) ?></h1>

<form method="get">
    <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" style="width:400px;padding:0.6em;">
    <button type="submit">搜索</button>
</form>

<?php if ($q): ?>
    <p>找到 <?= count($results) ?> 条结果</p>

    <?php foreach ($results as $r): ?>
    <div class="result">
        <a href="view.php?id=<?= $r['id'] ?>">
            <strong><?= htmlspecialchars($r['card_id']) ?></strong> - 
            <?= htmlspecialchars($r['title'] ?: '(无标题)') ?>
        </a>
        <div style="margin-top:0.6em;color:#555;">
            <?= $r['content_snippet'] ?: '...' ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($results)): ?>
    <p>没有找到匹配内容。</p>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>