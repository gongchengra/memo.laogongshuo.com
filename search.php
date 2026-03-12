<?php
// search.php - 优化版（2026-03-11）

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/func.php';
require_once __DIR__ . '/vendor/autoload.php';  // 必须有这一行！

use Fukuball\Jieba\Jieba;

$db = get_db();

$q = trim($_GET['q'] ?? '');
$results = [];
$error_msg = null;

if ($q !== '') {
    try {
        init_jieba();

        // 对查询词进行分词
        $words = Jieba::cutForSearch($q);
        $words = array_filter($words, fn($w) => mb_strlen($w) >= 1);

        if (empty($words)) {
            $fts_query = $q;  // 兜底：直接用原词
        } else {
            // 构建更宽松的查询：每个词 OR + 前缀匹配
            $parts = [];
            foreach ($words as $w) {
                $parts[] = '"' . addcslashes($w, '"') . '"';  // 精确短语
                $parts[] = addcslashes($w, '"') . '*';        // 前缀匹配
            }
            $fts_query = implode(' OR ', $parts);
        }

        // 调试用：显示实际送进 MATCH 的字串（上线可注释）
        // echo "<pre>查询词分词结果: " . htmlspecialchars($fts_query) . "</pre>";

        $stmt = $db->prepare("
            SELECT 
                z.id,
                z.card_id,
                z.title,
                snippet(zettel_fts, 2, '<mark>', '</mark>', ' ... ', 35) AS content_snippet
            FROM zettel_fts
            JOIN zettel z ON z.id = zettel_fts.rowid
            WHERE zettel_fts MATCH ?
            LIMIT 30
        ");

        $stmt->execute([$fts_query]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error_msg = "搜索出错：" . htmlspecialchars($e->getMessage()) . 
                     "<br>文件：" . htmlspecialchars($e->getFile()) . 
                     "<br>行号：" . $e->getLine();
        error_log("search.php 错误: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>搜索 - <?= htmlspecialchars($q) ?></title>
    <style>
        body { 
            font-family: system-ui, sans-serif; 
            max-width: 960px; 
            margin: 2rem auto; 
            padding: 0 1rem; 
            line-height: 1.6;
        }
        h1 { margin-bottom: 1.5rem; }
        form { margin-bottom: 2rem; }
        input[type="search"] { 
            width: 400px; 
            padding: 0.7em; 
            font-size: 1.1em; 
        }
        button { 
            padding: 0.7em 1.4em; 
            background: #1976d2; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
        }
        .result {
            margin: 1.5rem 0;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        mark {
            background: #fff176;
            padding: 2px 4px;
        }
    </style>
</head>
<body>

<h1>搜索<?= $q ? '：' . htmlspecialchars($q) : '' ?></h1>

<form method="get">
    <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="输入关键词..." required>
    <button type="submit">搜索</button>
</form>

<?php if ($error_msg): ?>
    <div class="error"><?= $error_msg ?></div>
<?php endif; ?>

<?php if ($q): ?>
    <p>找到 <?= count($results) ?> 条结果</p>

    <?php if (!empty($results)): ?>
        <?php foreach ($results as $r): ?>
        <div class="result">
            <a href="view.php?id=<?= (int)$r['id'] ?>">
                <strong><?= htmlspecialchars($r['card_id']) ?></strong> - 
                <?= htmlspecialchars($r['title'] ?: '(无标题)') ?>
            </a>
            <div style="margin-top:0.6em; color:#555;">
                <?= $r['content_snippet'] ?: '...' ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>没有找到匹配的内容。</p>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>
