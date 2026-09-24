# 本地运行与生产部署

## 方案选择

可选支持 SSH/WP-CLI 的托管 WordPress 主机，或自有 Linux VPS + Docker。本仓库提供后者的配置。无需 WordPress.com 订阅。建议 PHP 8.3+、MariaDB 10.11+ 或 MySQL 8.0+、HTTPS；依据 [WordPress 官方要求](https://wordpress.org/about/requirements/)。不要将站点当成静态网页部署到 GitHub Pages。

本地 Docker 使用 WordPress Apache PHP 8.3、MariaDB 11.4、WP-CLI；默认镜像标签是更新通道而非固定生产版本。正式上线前在预发布环境验证具体版本并将三个镜像变量改为 `镜像@sha256:...`，保留测试记录和回滚版本。

## A. 本地 Docker

1. 安装 Git、Docker Desktop（启用 Linux containers，Windows 配置 WSL2）。建议至少 4 GB 可用内存、10 GB 磁盘。
2. 克隆本次交付分支或合并后的 main：

```sh
git clone https://github.com/yueyuefeng/WholeSaleWeb.git
cd WholeSaleWeb
# 如果代码仍在 PR 分支：git checkout feat/shadowalker-storefront
cp .env.example .env
```

PowerShell 把最后一行换成 `Copy-Item .env.example .env`。用编辑器修改 `.env` 中数据库、管理员的三个不同强密码与邮箱；不把 `.env` 提交到 Git。用户名不要使用 `admin`。密码建议用随机的字母数字长串，避免 Compose 的 `$` 插值歧义。

```sh
docker compose config --quiet
docker compose up -d --wait
docker compose --profile tools run --rm cli sh /workspace/scripts/setup.sh
docker compose --profile tools run --rm cli wp shadowalker seed
```

`seed` 是可选演示导入。首次设置首页、演示横幅和禁止索引；同 SKU 商品和同路径页面不会重复导入或覆盖。已有真实网站请先备份并在副本测试，不建议向现有生产站导入演示。

访问 `http://localhost:8080` 和 `/wp-admin/`。若 8080 被占用，**同时**修改 `WP_PORT` 与 `WP_URL`；已有数据库地址变更应通过下文 search-replace 完成。

安装器自动下载 WooCommerce、Stripe、PayPal、Yoast 并激活，不主动升级已经安装的插件。支付账户未连接时不能收款。安装完成后检查 WooCommerce → 设置 → 站点可见性；演示/预发布应保留“即将推出”，本地仅查看设计时可以切为“公开上线”，且继续保持搜索引擎禁止收录。

## B. 生产服务器首次部署

假设 Ubuntu LTS VPS 已安装 Docker Engine 和 Compose v2。系统安装步骤依服务商及架构，遵循 [Docker 官方安装文档](https://docs.docker.com/engine/install/ubuntu/)，不要把未知脚本直接以 root 执行。

1. 购买域名，在 DNS 中创建商店域名 A 记录指向 VPS；只有服务器配置 IPv6 时才添加 AAAA。
2. 放行 80/TCP、443/TCP（可选 443/UDP）；SSH 只开放给管理 IP。数据库没有发布端口；WordPress 8080 只绑定回环地址。
3. 克隆仓库到 `/srv/shadowalker`，确认采用已验收的提交。复制 `.env.example` 为 `.env`，执行 `chmod 600 .env`。
4. `.env` 设置真实 `DOMAIN=shop.yourdomain.com`、`WP_URL=https://shop.yourdomain.com`、`ACME_EMAIL`、管理员邮箱及强密码，固定已测试镜像。

```sh
cd /srv/shadowalker
docker compose -f compose.yaml -f compose.production.yaml config --quiet
docker compose -f compose.yaml -f compose.production.yaml up -d --wait
docker compose -f compose.yaml -f compose.production.yaml --profile tools run --rm cli sh /workspace/scripts/setup.sh
```

Caddy 自动申请并续签证书，首次要求 DNS 已生效、80/443 可访问。示例不设置 HSTS preload，以便初次部署故障恢复。WordPress 官方 Docker 配置支持代理传入的 HTTPS 协议头。不要在公网另开 Apache 容器端口。

5. 若需要演示，在预发布执行 seed；生产应导入审核过的内容，或者导入后完整替换/下架所有演示商品、政策草稿。不要在演示价格上连接生产网关。
6. 按第三至第五篇配置 WPML/WCML、支付、SEO；建立仓库/商品/配送区/税率。按第七篇验收。
7. 生产配置已禁用访客触发的 WP-Cron，必须在宿主机 `crontab -e` 添加：

```cron
*/5 * * * * cd /srv/shadowalker && /usr/bin/flock -n /tmp/shadowalker-cron.lock /usr/bin/docker compose -f compose.yaml -f compose.production.yaml --profile tools run --rm -T cli wp cron event run --due-now >> /var/log/shadowalker-cron.log 2>&1
```

根据 `command -v docker` 调整路径。为日志配置 logrotate，验证 Action Scheduler 队列没有持续失败/积压。

## C. 不使用 Docker 的托管主机

1. 在主机面板新建 WordPress、数据库及 HTTPS，保留独立预发布环境。
2. 上传 `wp-content/themes/shadowalker` 和 `wp-content/plugins/shadowalker-core` 到对应目录，或使用交付 ZIP 在后台安装。
3. 安装 WooCommerce、WooCommerce Stripe Gateway、WooCommerce PayPal Payments、Yoast SEO；激活 Shadowalker Core 和主题。
4. 通过 SSH 到 WordPress 根目录执行 `wp shadowalker seed`（可选）。没有 WP-CLI 可手动建立页面和分类：Home、Shop、Cart、Checkout、My account、Contact，以及 Compare、Build、Compatibility。Contact 选择 **Shadowalker Chat** 页面模板（路径为 `contact` 时也会自动匹配该模板），会显示聊天及折叠询价表单；若使用普通页面模板，正文填入 `[shadowalker_chat]`，可另加 `[shadowalker_inquiry]`。选购工具页面分别填入 `[shadowalker_compare]`、`[shadowalker_build]`、`[shadowalker_fit]`。
5. 阅读设置指定静态 Home 为首页。WooCommerce 设置指定商城、购物车、结账和账户页面；设置固定链接为文章名。
6. 联系、品牌、政策页面路径见 seed.php；使用自定义菜单可覆盖默认导航。无 seed 时需创建这些页面，否则相应默认链接会是 404。

## 地址迁移与发布更新

迁移前备份数据库和 `wp-content/uploads`、第三方插件、配置。数据库含序列化字段，不可直接全文替换 SQL。

```sh
docker compose --profile tools run --rm cli wp search-replace 'http://localhost:8080' 'https://shop.yourdomain.com' --all-tables-with-prefix --skip-columns=guid --dry-run
# 核对后同命令移除 --dry-run；生产命令加两份 -f 配置
docker compose --profile tools run --rm cli wp rewrite flush --hard
```

生产先在副本验证更新，再部署已审查的 Git 提交。主题与业务插件使用只读挂载，通过 Git 更新；官方插件在持久卷内，通过后台/WP-CLI 管理。不要把旧数据库覆盖到有新订单的站点。

## 持久化、备份和回滚

`db_data` 保存业务数据库，`wp_data` 保存 WordPress 核心、上传及第三方插件，`caddy_data` 保存证书。`docker compose down` 不删数据；**不要用 `down -v` 作为重启方法**。

数据库备份示例（Linux，压缩后上传到加密异地备份仓库）：

```sh
mkdir -p backups
docker compose exec -T db sh -c 'exec mariadb-dump -u root -p"$MARIADB_ROOT_PASSWORD" --single-transaction "$MARIADB_DATABASE"' > backups/database.sql
docker compose exec -T wordpress tar -czf - -C /var/www/html wp-content > backups/wp-content.tar.gz
```

低流量维护窗口执行，以免文件和数据库时间不一致；高订单量采用事务日志/一致性快照及更短 RPO。备份文件含隐私信息，不上传 Git 或公开目录。恢复时先停流量，先在隔离库导入 SQL、还原文件、验证完整性，再切换流量。代码回滚用之前已测试提交；数据库结构迁移后不能仅回滚 PHP，须按照插件回滚文档和一致性备份操作。上线前实际演练一次恢复。

## 常见问题

- 502：查看 `docker compose logs --tail=100 caddy wordpress db`，检查容器健康和 DNS。
- HTTPS 循环：检查 CDN 设为端到端严格 TLS、代理协议头及 WordPress home/siteurl。
- 404：保存固定链接，检查 Apache rewrite / 主机规则。
- 安装失败：检查容器对 wordpress.org 的网络访问；不要通过禁用 TLS 校验解决。
- 无支付方式：账户未连接、测试/生产模式混用、币种/客户国家不符或插件未启用。
- 无运费：没有匹配配送区/运输方式，或商品重量尺寸未填；不能默认所有国家免费送达。
- 询价限速误伤：代理后 REMOTE_ADDR 可能相同。应在可信反向代理/Apache 配置真实客户端 IP；不要直接信任访客传入的 X-Forwarded-For。
