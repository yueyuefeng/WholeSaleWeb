# 多语言与多币种

## 本版实际能力

默认语言为英语，提供简体中文、德语、法语、西班牙语各 158 条主题/询价/聊天文案翻译，源码位于 `translations`，编译产物位于主题 `languages`。这是界面文案，不包含商品、政策、WooCommerce 自身或支付插件的全部内容。各语言联系页选择 Shadowalker Chat 页面模板，机器人收到会话语言后应使用该语言回答。

主题已集成 WPML 语言选择器（仅列出当前页面已有翻译）和 WCML 货币选择器，`wpml-config.xml` 将询价开关同步、技术规格设置为翻译。未安装 WPML 时不显示误导性的语言下拉框。安装相应 WordPress 核心语言包并设置站点语言可验证单一语言的主题翻译（命令示例：`wp language core install zh_CN`，然后 `wp option update WPLANG zh_CN`）。

首屏品牌价值主张随当前语言切换。中文保留原句；“行者”按不惧艰险、勇往直前的人生态度翻译，不按行走/旅行的字面意思翻译。五语言定稿及维护规则见 [视觉与品牌说明](09-visual-direction.md)。

完整五语言商城需要 **WPML Multilingual CMS、String Translation、WPML Multilingual & Multicurrency for WooCommerce（WCML）**。商业许可证由站点所有者购买；本仓库不分发付费插件、不包含许可证和机器翻译额度。只有 WCML 免费插件不能完成本方案的全部多语言内容管理。参考 [WPML WooCommerce 官方说明](https://wpml.org/documentation/wpml-core-and-add-on-plugins/woocommerce-multilingual/)。

## 安装配置顺序

1. 先完成英文商品、分类、页面、税费和配送设置，备份。
2. 从 WPML 账户下载并安装所需插件，注册站点许可证；预发布使用相应开发站点注册类型。
3. 运行 WPML 向导，默认 English；添加 Chinese (Simplified)、German、French、Spanish。Locale 分别核对为 `en_US`、`zh_CN`、`de_DE`、`fr_FR`、`es_ES`。
4. 采用语言目录 URL：英文 `/`，其他语言 `/zh-hans/`、`/de/`、`/fr/`、`/es/`。中文实际目录以前台 WPML 配置为准，不手工拼 URL。
5. WPML → 主题和插件本地化中扫描主题和 Shadowalker Core。翻译包可直接载入；有自定义覆盖时通过 String Translation 管理。
6. 翻译 Home、Contact、Shop、Cart、Checkout、My account、品牌故事、配送、隐私、条款。联系页每个语言保留 `[shadowalker_inquiry]`。首页页面本身须有对应语言版本，首页组件文本由 gettext 负责。
7. 使用 WCML 产品翻译工作流翻译产品名称、长短描述、URL slug、分类、属性、变体标签、技术规格、图片 alt 和 SEO 元数据。不要手工复制出独立库存产品。
8. 同步菜单。主题默认分类与页面链接会通过 `wpml_object_id` 获取相应语言对象，语言缺失时保留默认内容入口；正式站应完成核心页面翻译。
9. WooCommerce 和支付插件安装官方语言包，检查购物车、结账、错误提示、订单邮件、退款邮件与账户页。
10. 各语言完成后保存固定链接，清缓存并检查页面源码的 `lang`、canonical、hreflang。

## 多币种

语言、收货国家、显示币种、实际扣款币种和商户结算币种是不同概念。不能把切换语言简单等同于货币符号替换。

在 WCML 多币种设置中启用 USD/EUR/GBP，若经营对应市场再开启 CAD/AUD 等；基础币种需要业务确认。可设人工固定价格或受控汇率更新，明确小数/舍入和优惠券换算策略。启用自动汇率需要服务商与调度配置。参考 [WCML 多币种和支付网关](https://wpml.org/documentation/translating-your-contents/multi-currency-and-payment-gateways-for-woocommerce/)。

一次只让一个系统管理结账币种；本方案使用 WCML 时，不要未经联调同时开启其他多币种插件或 Stripe Adaptive Pricing 来二次换汇。先在沙箱验证优惠券、运费、税、订单金额、网关付款、退款币种与小数位都一致。

## SEO 与缓存

- 各语言必须拥有稳定、可访问且可索引的 URL；不强制按 IP 跳转，也不为机器人输出不同内容。
- 翻译标题/描述和产品内容，不能只翻导航。
- 采用 Yoast + WPML SEO 兼容扩展（如当前版本要求），由插件协调 sitemap/hreflang/canonical，不额外手写第二套标签。
- CDN 缓存至少区分语言路径；多币种页面必须正确区分货币状态，否则先禁用相关页面整页缓存。
- Cart、Checkout、My account、`wc-ajax`、Store API 和支付回调不缓存。
- 将 cookie banner 文案也翻译，非必要分析脚本只在用户做出相应选择后加载。

## 必测用例

同一 SKU 英文/德文共享库存；从法文商品加购物车后转西班牙文库存与行项目不重复；改变币种后税和运费重算；不同语言订单邮件正确；缺失译文不产生错误 hreflang；移动端语言菜单可使用；客户不会因 IP 跳转陷入循环。

本次没有获得付费 WPML 插件及许可证，所以**WPML/WCML 实际联调留待安装后的预发布验收**，不能将代码集成点视为已通过商业扩展端到端测试。
