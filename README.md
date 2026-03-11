# memo.laogongshuo.com

这是一个轻量级的个人卡片盒（Zettelkasten）知识管理系统，基于 PHP 和 SQLite3 开发。

## 主要功能
- **卡片管理**：创建、编辑、查看和删除卡片。
- **标签系统**：支持多对多标签关联。
- **双向链接**：自动识别卡片间的引用（Outgoing/Incoming Links）。
- **全文搜索**：基于 SQLite FTS5 的高性能搜索。
- **Markdown 支持**：正文支持 Markdown 渲染。

## 环境要求
- PHP 7.4+ (建议 PHP 8.1+)
- PHP 扩展：`pdo_sqlite`, `mbstring`
- SQLite 3.24.0+ (支持 FTS5)
- Nginx / Apache

## 初始化步骤

1. **准备目录和权限**
   ```bash
   mkdir -p data
   # 确保 Web 服务器对 data 目录有读写权限
   chmod -R 777 data
   ```

2. **初始化数据库**
   使用提供的 SQL 文件初始化 SQLite 数据库：
   ```bash
   sqlite3 data/zettel.db < data/zettel.sql
   ```

3. **配置 Nginx**
   将以下配置添加到您的 Nginx 站点配置中（或参考下方的完整配置示例）：
   - 根目录指向项目文件夹。
   - 确保 `data/` 目录被禁止直接访问。
   - 开启 PHP-FPM 支持。

## Nginx 配置示例 (Nginx.conf)

```nginx
# upstream 定义
upstream memo {
    server unix:/run/php/php8.3-fpm.sock; # 根据实际 php-fpm 版本调整
    keepalive 16;
}

server {
    listen 80;
    server_name memo.laogongshuo.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name memo.laogongshuo.com;

    ssl_certificate     /etc/letsencrypt/live/memo.laogongshuo.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/memo.laogongshuo.com/privkey.pem;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    
    root /var/www/memo.laogongshuo.com;
    index index.php;

    # 安全限制：禁止访问隐藏文件和数据目录
    location ~ /\. { deny all; return 404; }
    location ~* \.(db|sqlite|sql|log)$ { deny all; return 404; }
    location ^~ /data/ { deny all; return 404; }

    # 只允许执行特定的入口文件
    location ~ ^/(index\.php|edit\.php|view\.php|delete\.php|search\.php|tag\.php)$ {
        try_files $uri =404;
        fastcgi_pass memo;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 默认禁止其他 .php 文件
    location ~ \.php$ { return 403; }
}
```

## 文件说明
- `index.php`: 首页列表，支持分页和最近更新排序。
- `edit.php`: 新建和编辑卡片。
- `view.php`: 查看卡片详情（含渲染后的 Markdown 内容）。
- `tag.php`: 查看特定标签下的所有卡片。
- `search.php`: 搜索功能。
- `func.php`: 核心业务逻辑函数。
- `db.php`: 数据库连接配置。
- `data/zettel.sql`: 数据库表结构定义。
