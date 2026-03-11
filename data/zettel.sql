-- zettel.sql
-- 盧曼卡片盒 SQLite 資料庫初始化腳本（2026-03-11 精簡版，去除 FTS5）
-- 執行方式：sqlite3 data/zettel.db < zettel.sql

-- =========================================================================
-- 核心卡片表
-- =========================================================================
CREATE TABLE IF NOT EXISTS zettel (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    card_id           TEXT NOT NULL UNIQUE,               -- 永久唯一識別，例如 202603101947a
    title             TEXT DEFAULT '',
    content           TEXT NOT NULL,
    content_format    TEXT DEFAULT 'markdown',            -- markdown / plain / html / org 等未來擴展
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    word_count        INTEGER DEFAULT 0,                  -- 可選：統計用
    reading_state     TEXT DEFAULT 'new',                 -- new / reading / processed / evergreen / archive ...

    CHECK (card_id != ''),
    CHECK (content != '')
);

-- =========================================================================
-- 標籤（獨立表）
-- =========================================================================
CREATE TABLE IF NOT EXISTS tag (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL COLLATE NOCASE,
    description TEXT DEFAULT '',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(name)
);

-- 卡片 ↔ 標籤 多對多關聯
CREATE TABLE IF NOT EXISTS zettel_tag (
    zettel_id   INTEGER NOT NULL,
    tag_id      INTEGER NOT NULL,
    added_at    DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (zettel_id, tag_id),
    FOREIGN KEY (zettel_id) REFERENCES zettel(id)   ON DELETE CASCADE,
    FOREIGN KEY (tag_id)    REFERENCES tag(id)      ON DELETE CASCADE
);

-- =========================================================================
-- 連結（只存單向，靠視圖實現雙向）
-- =========================================================================
CREATE TABLE IF NOT EXISTS zettel_link (
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
-- 常用視圖（方便雙向連結查詢）
-- =========================================================================

CREATE VIEW IF NOT EXISTS v_outgoing_links AS
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

CREATE VIEW IF NOT EXISTS v_incoming_links AS
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
CREATE INDEX IF NOT EXISTS idx_zettel_card_id     ON zettel(card_id);
CREATE INDEX IF NOT EXISTS idx_zettel_updated     ON zettel(updated_at DESC);
CREATE INDEX IF NOT EXISTS idx_tag_name           ON tag(name COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS idx_zettel_tag_zid     ON zettel_tag(zettel_id);
CREATE INDEX IF NOT EXISTS idx_zettel_tag_tid     ON zettel_tag(tag_id);
CREATE INDEX IF NOT EXISTS idx_link_from          ON zettel_link(from_zettel_id);
CREATE INDEX IF NOT EXISTS idx_link_to            ON zettel_link(to_zettel_id);