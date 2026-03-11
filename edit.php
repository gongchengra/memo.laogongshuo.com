<?php
// edit.php - 新建 / 编辑卡片

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/func.php';

$db = get_db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_new = ($id === 0);
$card = null;

if (!$is_new) {
    $stmt = $db->prepare("SELECT * FROM zettel WHERE id = ?");
    $stmt->execute([$id]);
    $card = $stmt->fetch();
    if (!$card) {
        die("卡片不存在");
    }
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card_id   = trim($_POST['card_id']   ?? '');
    $title     = trim($_POST['title']     ?? '');
    $content   = trim($_POST['content']   ?? '');
    $tags_str  = trim($_POST['tags']      ?? '');
    $links_str = trim($_POST['links']     ?? '');

    $tag_names = array_unique(array_filter(array_map('trim', explode(',', $tags_str))));
    $link_ids  = array_unique(array_filter(array_map('trim', explode(',', $links_str))));

    if ($content === '') {
        $error = "内容不能为空";
    } else {
        try {
            $db->beginTransaction();

            if (!$is_new) {
                // ── 更新模式 ────────────────────────────────────────
                $zettel_id = $id;

                // 同步标签（函数内部不再开启事务）
                sync_card_tags($db, $zettel_id, $tag_names);

                // 清空旧链接
                $db->prepare("DELETE FROM zettel_link WHERE from_zettel_id = ?")
                   ->execute([$zettel_id]);

                // 插入新链接
                foreach ($link_ids as $target_card_id) {
                    if ($target_card_id === $card_id) continue; // 防自链

                    $stmt = $db->prepare("SELECT id FROM zettel WHERE card_id = ?");
                    $stmt->execute([$target_card_id]);
                    $to_id = $stmt->fetchColumn();

                    if ($to_id && $to_id != $zettel_id) {
                        $db->prepare("
                            INSERT OR IGNORE INTO zettel_link 
                            (from_zettel_id, to_zettel_id, link_type) 
                            VALUES (?, ?, 'related')
                        ")->execute([$zettel_id, $to_id]);
                    }
                }

                // 更新卡片（每次保存都更新时间戳）
                $stmt = $db->prepare("
                    UPDATE zettel 
                    SET title = ?, content = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$title, $content, $zettel_id]);
            } else {
                // ── 新建模式 ────────────────────────────────────────
                if ($card_id === '') {
                    $card_id = generate_card_id();
                }

                $stmt = $db->prepare("
                    INSERT INTO zettel (card_id, title, content) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$card_id, $title, $content]);
                $zettel_id = $db->lastInsertId();

                // 同步标签
                sync_card_tags($db, $zettel_id, $tag_names);

                // 添加链接
                foreach ($link_ids as $target_card_id) {
                    $stmt = $db->prepare("SELECT id FROM zettel WHERE card_id = ?");
                    $stmt->execute([$target_card_id]);
                    $to_id = $stmt->fetchColumn();

                    if ($to_id && $to_id != $zettel_id) {
                        $db->prepare("
                            INSERT OR IGNORE INTO zettel_link 
                            (from_zettel_id, to_zettel_id, link_type) 
                            VALUES (?, ?, 'related')
                        ")->execute([$zettel_id, $to_id]);
                    }
                }
            }

            $db->commit();
            header("Location: view.php?id=$zettel_id");
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            // 记录详细错误到日志（方便排查）
            error_log("保存卡片失败:\n" .
                      "Message: " . $e->getMessage() . "\n" .
                      "File: " . $e->getFile() . "\n" .
                      "Line: " . $e->getLine() . "\n" .
                      "Trace: " . $e->getTraceAsString());

            $error = "保存失败：" . htmlspecialchars($e->getMessage());
        }
    }
}

// ── 准备表单默认值 ────────────────────────────────────────────────
// 新建时不调用依赖 id 的函数，避免 SQL 错误
$default_card_id = $is_new ? generate_card_id() : ($card['card_id'] ?? '');
$default_title   = $card['title']   ?? '';
$default_content = $card['content'] ?? '';
$default_links   = '';
$default_tags    = '';

if (!$is_new && $id > 0) {
    // 只有编辑模式才读取链接和标签
    $out_links = get_outgoing_links($db, $id);
    $default_links = implode(',', array_column($out_links, 'card_id'));

    $tags_arr = get_card_tags($db, $id);
    $default_tags = implode(',', array_values($tags_arr));
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_new ? '新建卡片' : '编辑卡片 ' . htmlspecialchars($default_card_id) ?></title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            max-width: 960px;
            margin: 2rem auto;
            padding: 0 1rem;
            line-height: 1.6;
        }
        h1 { margin-bottom: 1.5rem; }
        label { 
            display: block; 
            margin: 1.5rem 0 0.4rem; 
            font-weight: 600; 
        }
        input[type="text"],
        textarea {
            width: 100%;
            padding: 0.7em;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }
        textarea {
            height: 420px;
            font-family: monospace;
            font-size: 1.05em;
            line-height: 1.5;
        }
        .error {
            color: #d32f2f;
            background: #ffebee;
            padding: 1rem;
            border-radius: 6px;
            margin: 1rem 0;
        }
        .actions {
            margin-top: 2.5rem;
        }
        button {
            padding: 0.8em 1.8em;
            background: #1976d2;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.05em;
        }
        button:hover { background: #1565c0; }
        a { color: #1976d2; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>

<h1><?= $is_new ? '新建卡片' : '编辑卡片' ?></h1>

<?php if ($error): ?>
    <div class="error"><?= $error ?></div>
<?php endif; ?>

<form method="post">
    <label>卡片ID（建议保持自动生成格式）：</label>
    <input type="text" name="card_id" value="<?= htmlspecialchars($default_card_id) ?>" required>

    <label>标题（可选）：</label>
    <input type="text" name="title" value="<?= htmlspecialchars($default_title) ?>">

    <label>正文（核心内容，用自己的话写）：</label>
    <textarea name="content" required><?= htmlspecialchars($default_content) ?></textarea>

    <label>链接到的卡片ID（用半角逗号分隔）：</label>
    <input type="text" name="links" value="<?= htmlspecialchars($default_links) ?>">

    <label>标签（用半角逗号分隔，例如：系统理论,二阶控制,沟通）：</label>
    <input type="text" name="tags" value="<?= htmlspecialchars($default_tags) ?>">

    <div class="actions">
        <button type="submit">保存</button>
            <a href="index.php">返回列表</a>
    </div>
</form>

</body>
</html>