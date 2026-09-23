# Shadowalker · Electric mobility

基于 WordPress + WooCommerce 的出行产品独立站：高尔夫车、电动船、电动自行车、自行车电动改装配件。采用原创 Shadowalker 主题与独立业务插件，支持整车询价、配件购买和国际化扩展。

## 快速开始

需要 Docker Engine / Docker Desktop（Linux containers）与 Docker Compose v2。

```sh
cp .env.example .env
# 修改 .env 中所有密码、管理员邮箱
docker compose up -d --wait
docker compose --profile tools run --rm cli sh /workspace/scripts/setup.sh
docker compose --profile tools run --rm cli wp shadowalker seed --allow-root
```

打开 http://localhost:8080 ，后台 http://localhost:8080/wp-admin/ 。Windows PowerShell 使用 `Copy-Item .env.example .env`，其余 docker 命令相同。初始化不会导入演示商品，必须显式执行 seed。

## 交付内容与边界

- 响应式品牌主题：首页、原生 WooCommerce 商品列表/详情/购物车/结账/账户、搜索、文章与内容页。
- Shadowalker Core：整车询价模式、后台询价管理、服务端表单校验与防刷、商品技术规格、幂等演示数据命令。
- 联系页直接聊天：匿名会话、消息记录、后台人工回复、转人工、访客删除；预留服务端机器人钩子与 HTTPS 适配协议。未配置机器人时只显示真实的人工消息状态。
- 英文默认主题，简中/德/法/西主题翻译包；WPML 语言切换、WCML 货币切换集成。**完整多语言商品/结账需购买并配置 WPML CMS、String Translation、WCML，翻译商品及政策；翻译包不等同于全站已翻译。**
- 官方 Stripe、PayPal 插件安装脚本，未自动启用真实支付。渠道显示由官方网关根据商户资格、币种、设备及客户国家决定。
- WooCommerce Product 结构化数据、WordPress 标题/语义化页面、Yoast 安装与多语言 SEO 操作手册。
- Docker 开发环境、HTTPS 生产部署样例、CI、验收脚本、完整中文部署与运维文档。

所有价格、产品参数、插画都是演示素材，不代表真实商品规格、认证、库存或销售承诺。默认站点禁止搜索引擎收录，不启用运费/支付。上线前按验收清单替换并验证。

## 文档

1. [开发过程与架构](docs/01-development.md)
2. [本地运行与生产部署](docs/02-deployment.md)
3. [多语言与多币种](docs/03-localization.md)
4. [国家支付配置](docs/04-payments.md)
5. [SEO 与内容运营](docs/05-seo.md)
6. [资源、预算与资料清单](docs/06-resources.md)
7. [测试、上线与运维](docs/07-operations.md)
8. [交付测试记录](docs/08-verification.md)
9. [科技风视觉升级与摄影来源](docs/09-visual-direction.md)
10. [聊天页面、人工客服与机器人接入](docs/10-chat-and-bot.md)

源代码：`wp-content/themes/shadowalker`、`wp-content/plugins/shadowalker-core`。WordPress 核心、第三方插件和付费许可证不纳入版本库。授权见 LICENSE。
