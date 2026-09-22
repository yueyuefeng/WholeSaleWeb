# 开发过程与架构

## 需求与决策记录

2026-09-22 从空仓库 `yueyuefeng/WholeSaleWeb` 建立项目。用户指定 WordPress，品牌确认为 **Shadowalker**。目标产品为高尔夫车、电动船、电动自行车、自行车电动改装配件；要求多语言、本地常用在线支付、SEO、部署与资源文档。

尚待业务确认：主要国家、收款主体注册地、结算银行、域名、真实商品目录、运费、税费、保修与退货政策。实现中不把假设当成已确认事实。

1. 选择经典 WordPress 主题 + WooCommerce：后台可直接管理商品、订单、库存、文章和页面，不重复开发交易系统。
2. 品牌视觉：深森林绿、米白、浅橄榄绿；首页使用自制 SVG 出行插画。图片无第三方追踪请求、无外部字体依赖。正式商品页替换为真实摄影。
3. 内容结构：品牌首页 → 四类产品 → 商品详情 → 配件购买 / 整车询价；配套品牌故事、联系、配送退换、隐私、条款、搜索和文章。
4. 将业务逻辑放入 `shadowalker-core` 插件，主题只负责展示。更换主题不会删除询价资料或商品元数据。
5. 多语言采用 WordPress gettext + WPML/WCML 集成。避免自行复制商品库存或用浏览器机器翻译替代可索引语言页面。
6. 支付使用官方 Stripe / PayPal 扩展，不自行接触卡号，不实现伪支付回调。方法资格由官方插件及商户平台判断。
7. 提供本地 Docker、生产 Caddy HTTPS、初始化脚本、测试脚本、中文操作文档。

## 目录与职责

| 路径 | 职责 |
|---|---|
| `wp-content/themes/shadowalker` | WordPress 主题和 WooCommerce 容器模板 |
| `assets/css/store.css` | 响应式页面、原生 WooCommerce 样式、键盘焦点与减少动画支持 |
| `assets/js/store.js` | 移动导航开合、Escape 关闭与焦点恢复 |
| `assets/images` | 五幅原创 SVG 演示插画 |
| `languages` / `translations` | 四套 gettext 翻译包与 JSON 源文件 |
| `plugins/shadowalker-core/includes/products.php` | 规格、询价商品购买限制、询价按钮、结构化数据调整 |
| `includes/inquiries.php` | 询价接收、后台管理、个人数据导出和删除 |
| `includes/seed.php` | 显式 WP-CLI 演示数据导入命令 |
| `scripts` | 初始化与翻译编译 |
| `tests` | PHP/WooCommerce 业务集成断言和 HTTP 检查 |
| `deploy` / `compose*.yaml` | 开发和生产基础设施 |

## 商品与交易模型

WooCommerce 负责价格、币种、税、库存、订单、退款、购物车和结账。规格保存为 `_sw_specs`，每行 `参数 | 值`，输出时进行 HTML 转义。商品可以使用原生属性、变体、图片、库存和分类。

`_sw_quote_only=yes` 时：隐藏可成交价格、禁止普通商品及其变体购买、阻止直接加入购物车、显示询价链接、不在 WooCommerce Product JSON-LD 中声称存在可购买的 Offer。恢复正常销售时，在商品编辑页取消该选项并填写正式价格、库存、物流等信息。

大件商品不预置免费运费；未配置目标地区配送时，结账应阻止发货订单。整车默认询价，配件样品有演示价格。演示数据并不适合接入真实支付。

## 询价数据流

1. `contact` 页面渲染 `[shadowalker_inquiry]`，商品链接可携带公开商品 ID。
2. 表单 POST 到 WordPress `admin-post.php`；验证方法、nonce、蜜罐、姓名、邮箱、国家代码、整数量、留言长度和隐私勾选。
3. 以 HMAC 临时键进行同来源限速（每小时 10 次）和同邮箱 60 秒间隔。它是基本防刷，不保证并发原子限额；高流量时使用边缘 WAF/挑战机制。
4. 创建私有 `sw_inquiry` 记录，只授予具备 `manage_woocommerce` 能力的管理员/店铺经理访问；不开放 REST 读取与公开搜索。
5. 保存成功后才尝试邮件通知；失败标记在后台“Notification”列。邮件成功不代表最终到达收件箱，应通过 SMTP 日志验证。
6. 用 303 跳转到同站成功页，避免刷新重复提交。
7. WordPress“工具 → 导出/删除个人数据”涵盖询价记录；邮件副本、备份、第三方邮件日志由运营另行处理。

目前没有 CRM、自动回复报价、附件上传、自动运费询价或客服消息发送；不假装这些能力已经完成。

## 可扩展点

- 更换站点菜单：外观 → 菜单，分配 Main navigation / Footer navigation；无菜单时使用主题默认链接。
- 商品摄影：上传 JPG/WebP/AVIF，设置商品主图和图库。演示 SVG 只在没有商品主图时使用。
- 改装适配：可按轮径、电压、轴制式、电机功率、刹车类型增加 WooCommerce 属性和变体；跨零件适配需要真实规格表后再建立规则。
- 批发：本版询价适合 B2B 线索收集。若需阶梯价、经销商审批、净账期、VAT ID，另评估成熟扩展。
- 国际化：主题文案使用 `shadowalker` text domain。修改 JSON 后运行 `python scripts/build-translations.py`，提交 `.po` 和 `.mo`；新增文案需同步四种译文。

## 开发验证原则

先 PHP 语法检查，再在真实 WordPress/WooCommerce 中验证购买限制、询价权限、翻译加载和数据幂等，再做桌面/移动 UI 检查。Docker/MariaDB、付费 WPML、真实网关和域名 HTTPS 的验证状态单独记录，不能用静态页面截图替代真实订单验收。
