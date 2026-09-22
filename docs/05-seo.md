# SEO 与内容运营

## 已实现与需配置的部分

主题使用服务端渲染、语义化 main/nav/article、单一页面 H1、WordPress `title-tag`、清晰分类链接、响应式布局、本地 CSS/JS/字体和 SVG。Hero 图不懒加载，其他演示图懒加载；不引入大型页面构建器。真实产品图使用 WooCommerce/WordPress 图片尺寸和响应式输出。

Product schema 由 WooCommerce 负责；询价商品移除 Offer，以免演示“可购买价格”。SEO 页面标题、描述、Open Graph、canonical 和 sitemap 交给安装脚本提供的 Yoast 统一管理，不在主题里重复输出。正式商品必须有真实图片、描述、价格、库存和标识，不能以演示数据生成商家推广 feed。

## 上线配置

1. 替换英文品牌故事、真实公司信息、联系信息、营业地址、服务国家、政策。不存在的认证、评价、销量、续航和“零排放”结论不得虚构。
2. Yoast 初始向导填写真实组织名称 Shadowalker、站点 Logo、社交资料；设置网站标题格式。
3. 首页标题示例：`Shadowalker | Electric Bikes, Golf Carts & Electric Boats`；描述应按实际产品范围重新编辑，保持独特、自然、与落地页一致。
4. 每个产品写独立标题、摘要、兼容性、技术规格、包装清单、运输限制、维护和售后；SKU/GTIN/MPN 仅填真实值。
5. 翻译所有语言页面标题、描述、slug、图片 alt、分类介绍。通过 WPML/SEO 兼容扩展输出正确 hreflang 和 sitemap；检查互相引用及自引用。
6. 核实 `/sitemap_index.xml`（Yoast 启用后）可访问；无 Yoast 时 WordPress 基础 sitemap 通常为 `/wp-sitemap.xml`。只提交当前生效的索引，不要求两个同时存在。
7. 购物车、结账、账户、内部搜索、询价结果等非内容页面不收录。Yoast/WooCommerce 的默认行为需实测。筛选组合使用规范链接和可控抓取，避免大量低价值参数页。
8. 全面审核后，在 WordPress 阅读设置关闭“建议搜索引擎不索引本站”，并将 WooCommerce 可见性设为上线。预发布仍需认证保护及 noindex；robots.txt 不是访问控制。
9. 在 Google Search Console 与 Bing Webmaster Tools 验证域名、提交 sitemap、检查覆盖和抓取错误。

多语言遵循 [Google 本地化页面指南](https://developers.google.com/search/docs/specialty/international/localized-versions)：稳定语言 URL 与关联标记，不能仅依赖 cookie 或语言切换按钮生成同一地址的不同内容。

## URL 与内容规划

| 分类 | 示例核心内容 | 可扩展支持文章 |
|---|---|---|
| Golf carts | 场景、座位、电池、载重、充电、交付 | 如何选择园区车辆；车队维护 |
| Electric boats | 船体、推进系统、容量、适用水域 | 电动船充电规划；航行前检查 |
| Electric bikes | 电助力系统、尺寸、扭矩、续航测试条件 | 通勤路线规划；电池保养 |
| Conversion kits | 电压、轮径、轴/花鼓、刹车、接口 | 改装兼容清单；电机选择 |

上述文章需要技术人员审核，不将任何功率配置描述为全球道路合法。法规依国家和车辆类别，产品页链接当地适用说明并更新。避免复制供应商一模一样的介绍到所有产品。

## 性能计划与验收

首屏真实产品图尽量优化为 WebP/AVIF，准备正确尺寸，不把 6000px 原图当缩略图。通过对象缓存、页面缓存/CDN 优化匿名内容；WooCommerce 会话、购物车、结账、账户、Store API、回调不缓存。WPML+多币种场景先确保内容正确，再开启整页缓存。

预发布用 Lighthouse / PageSpeed Insights 测移动网络，记录 LCP/INP/CLS。以良好体验阈值作为优化目标，不承诺未经测试的分数；真实用户数据与本地跑分分别记录。测速时覆盖首页、分类、图片最重的产品页、购物车、结账和语言切换。

## 日常运营

每周检查抓取错误、无库存高流量产品、失败页面和商品 feed；每月查看自然流量、搜索词、转化与用户国家。更新产品时保留旧 URL 或设置准确 301；删除商品优先提供替代品和合理状态，不批量跳转所有 404 到首页。评价只展示真实用户反馈，不写虚假 Review/AggregateRating。

当前默认禁止索引，演示政策和插画不能作为正式上架材料。SEO 是持续运营与数据验证过程，本交付不承诺搜索排名。
