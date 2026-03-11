-- =========================================================================
-- 核心卡片表
-- =========================================================================
CREATE TABLE zettel (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    card_id           TEXT NOT NULL UNIQUE,               -- 永久唯一識別，例如 202603101947a、202603101947b
    title             TEXT DEFAULT '',
    content           TEXT NOT NULL,
    content_format    TEXT DEFAULT 'markdown',            -- markdown / plain / html / org 等未來擴展
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    word_count        INTEGER DEFAULT 0,                  -- 可選：統計用
    reading_state     TEXT DEFAULT 'new',                 -- new / reading / processed / evergreen / archive ...

    -- 未來可能擴展的欄位（可先不加）
    -- type              TEXT DEFAULT 'permanent',        -- fleeting / literature / permanent / structure ...
    -- source            TEXT,                            -- 出處、書目、URL
    -- language          TEXT DEFAULT 'zh',

    CHECK (card_id != ''),
    CHECK (content != '')
);

-- =========================================================================
-- 標籤（獨立表）
-- =========================================================================
CREATE TABLE tag (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL COLLATE NOCASE,
    description TEXT DEFAULT '',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(name)
);

-- 卡片 ↔ 標籤 多對多關聯
CREATE TABLE zettel_tag (
    zettel_id   INTEGER NOT NULL,
    tag_id      INTEGER NOT NULL,
    added_at    DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (zettel_id, tag_id),
    FOREIGN KEY (zettel_id) REFERENCES zettel(id)   ON DELETE CASCADE,
    FOREIGN KEY (tag_id)    REFERENCES tag(id)      ON DELETE CASCADE
);

-- =========================================================================
-- 連結（只存單向，靠查詢 / 視圖實現雙向）
-- =========================================================================
CREATE TABLE zettel_link (
    from_zettel_id  INTEGER NOT NULL,
    to_zettel_id    INTEGER NOT NULL,
    link_type       TEXT DEFAULT 'related',           -- related / follows / supports / contradicts / cites ...
    context_note    TEXT DEFAULT '',                  -- 連結時的上下文說明（可選）
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (from_zettel_id, to_zettel_id),
    FOREIGN KEY (from_zettel_id) REFERENCES zettel(id) ON DELETE CASCADE,
    FOREIGN KEY (to_zettel_id)   REFERENCES zettel(id) ON DELETE CASCADE,

    CHECK (from_zettel_id != to_zettel_id)
);

-- =========================================================================
-- 全文搜尋引擎（FTS5 external content 模式）
-- =========================================================================
CREATE VIRTUAL TABLE zettel_fts USING fts5(
    title         UNINDEXED,
    content,
    tags_joined   UNINDEXED,          -- 用空格分隔的所有 tag 名稱

    tokenize      = 'unicode61'       -- 適合中文、日文等多語言
);

-- =========================================================================
-- 同步觸發器（保持 FTS 與 zettel 內容一致）
-- =========================================================================

-- 新增卡片時同步到 FTS
CREATE TRIGGER zettel_insert_fts AFTER INSERT ON zettel BEGIN
    INSERT INTO zettel_fts(rowid, title, content, tags_joined)
    VALUES (
        new.id,
        new.title,
        new.content,
        (
            SELECT GROUP_CONCAT(tag.name, ' ')
            FROM zettel_tag zt
            JOIN tag ON zt.tag_id = tag.id
            WHERE zt.zettel_id = new.id
        )
    );
END;

-- 更新卡片時同步（標題或內容變更）
CREATE TRIGGER zettel_update_fts AFTER UPDATE OF title, content ON zettel BEGIN
    -- 先刪除舊索引
    INSERT INTO zettel_fts(zettel_fts, rowid) VALUES('delete', old.id);

    -- 插入新索引
    INSERT INTO zettel_fts(rowid, title, content, tags_joined)
    VALUES (
        new.id,
        new.title,
        new.content,
        (
            SELECT GROUP_CONCAT(tag.name, ' ')
            FROM zettel_tag zt
            JOIN tag ON zt.tag_id = tag.id
            WHERE zt.zettel_id = new.id
        )
    );
END;

-- 刪除卡片時同步移除 FTS 索引
CREATE TRIGGER zettel_delete_fts AFTER DELETE ON zettel BEGIN
    INSERT INTO zettel_fts(zettel_fts, rowid) VALUES('delete', old.id);
END;

-- 當標籤關聯變更時，也需要更新 FTS 的 tags_joined 欄位
-- （這部分較複雜，可選擇在應用層處理，或新增以下觸發器）

CREATE TRIGGER zettel_tag_insert_fts AFTER INSERT ON zettel_tag BEGIN
    -- 更新該卡片的 tags_joined
    UPDATE zettel_fts
    SET tags_joined = (
        SELECT GROUP_CONCAT(tag.name, ' ')
        FROM zettel_tag zt
        JOIN tag ON zt.tag_id = tag.id
        WHERE zt.zettel_id = new.zettel_id
    )
    WHERE rowid = new.zettel_id;
END;

CREATE TRIGGER zettel_tag_delete_fts AFTER DELETE ON zettel_tag BEGIN
    UPDATE zettel_fts
    SET tags_joined = (
        SELECT GROUP_CONCAT(tag.name, ' ')
        FROM zettel_tag zt
        JOIN tag ON zt.tag_id = tag.id
        WHERE zt.zettel_id = old.zettel_id
    )
    WHERE rowid = old.zettel_id;
END;

-- =========================================================================
-- 常用視圖（方便雙向連結查詢）
-- =========================================================================

CREATE VIEW v_outgoing_links AS
SELECT
    z.id            AS from_id,
    z.card_id       AS from_card_id,
    z.title         AS from_title,
    t.id            AS to_id,
    t.card_id       AS to_card_id,
    t.title         AS to_title,
    l.link_type,
    l.context_note,
    l.created_at
FROM zettel z
JOIN zettel_link l ON l.from_zettel_id = z.id
JOIN zettel t      ON t.id = l.to_zettel_id;

CREATE VIEW v_incoming_links AS
SELECT
    z.id            AS to_id,
    z.card_id       AS to_card_id,
    z.title         AS to_title,
    f.id            AS from_id,
    f.card_id       AS from_card_id,
    f.title         AS from_title,
    l.link_type,
    l.context_note,
    l.created_at
FROM zettel z
JOIN zettel_link l ON l.to_zettel_id = z.id
JOIN zettel f      ON f.id = l.from_zettel_id;

-- =========================================================================
-- 索引（加速常見查詢）
-- =========================================================================
CREATE INDEX idx_zettel_card_id     ON zettel(card_id);
CREATE INDEX idx_zettel_updated     ON zettel(updated_at DESC);
CREATE INDEX idx_tag_name           ON tag(name COLLATE NOCASE);
CREATE INDEX idx_zettel_tag_zid     ON zettel_tag(zettel_id);
CREATE INDEX idx_zettel_tag_tid     ON zettel_tag(tag_id);
CREATE INDEX idx_link_from          ON zettel_link(from_zettel_id);
CREATE INDEX idx_link_to            ON zettel_link(to_zettel_id);
