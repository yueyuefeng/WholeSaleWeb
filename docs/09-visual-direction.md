# Shadowalker 视觉升级：独行者与真实摄影

## 用户确认的价值主张

> 人生来孤独，唯有影为永恒伴侣，行者心态不虚此生。

首页唯一 H1 随当前站点语言切换；中文保留原句和标点。按三个语义单元排版，以冷青色强调“影为永恒伴侣”这一句。移除固定中文语言标记，继承页面的 `lang`；不再重复显示中文标题与外语副标题。长句会根据屏幕宽度自然换行。

### 翻译原则与定稿（1.1.1）

品牌方解释：“行者”是类似孙行者所象征的哲学精神——不惧艰险、勇往直前、不虚此生。因此不译为 walker、traveller、行人或旅行者，也不在面向顾客的口号中直接加入人物名字或宗教身份。外语采用语义转写，保留“孤独—影相伴—勇敢而充实地活”的递进。

| 语言 | 首屏价值主张 |
|---|---|
| 简体中文 | 人生来孤独，唯有影为永恒伴侣，行者心态不虚此生。 |
| English | Born into solitude. Only our shadow stays forever. Face every trial. Forge ahead. Make this life count. |
| Deutsch | In die Einsamkeit geboren. Nur unser Schatten bleibt für immer. Allen Widrigkeiten trotzen. Mutig vorangehen. Ein erfülltes Leben führen. |
| Français | Nous naissons seuls. Seule notre ombre nous accompagne à jamais. Affronter les épreuves. Avancer avec courage. Vivre pleinement. |
| Español | Nacemos en soledad. Solo nuestra sombra nos acompaña para siempre. Afrontar la adversidad. Avanzar con valentía. Vivir con plenitud. |

三个句段均使用 WordPress gettext、`shadowalker` text domain 和已编译的 PO/MO。译文只存纯文本，模板统一转义，不让翻译内容控制 HTML。WPML 切换页面语言或修改 WordPress 站点语言时使用相应目录；未支持的语言回退英语。维护时同时审阅三个句段，避免单句机器直译破坏哲学含义。正式上线前可再请目标市场母语文案审校。

## 视觉系统

- 碳黑底色 `#0a0d10`，面板 `#11171b`，冷青强调 `#74f4db`，主文本 `#edf3f2`。
- 首屏真实山野骑行摄影，叠加静态深色渐变保证文字对比，不使用视频、闪烁、粒子、自动轮播和持续动画。
- 收紧圆角，使用细线、分类序号、等宽辅助字体，突出机械与工程气质。
- 分类采用真实场景摄影，改装区域采用传动系统特写。
- 商品、表单、菜单、WooCommerce 购物车/结账同步调整为深色；移动端重新安排照片与宣言位置。
- 原始 SVG 留作旧版资源，不再用于本版首页和演示商品占位图。已上传的真实商品图片仍优先于占位摄影。

## 摄影来源与授权

以下为 Unsplash 免费摄影素材；照片版权仍归摄影作者，不按项目 PHP 代码的 GPL 授权。参考 [Unsplash License](https://unsplash.com/license)。素材按网站展示尺寸下载为 WebP 并保存到主题 `assets/photos`，前台不请求第三方图片域名。以下来源信息应随再分发保留。

| 文件 | 作者 | 原始作品 | 用途 |
|---|---|---|---|
| `hero.webp` | Dmitrii Vaccinium | [9qsK2QHidmg](https://unsplash.com/photos/9qsK2QHidmg) | 首屏骑行者与山野 |
| `cart.webp` | Dean | [5yxJpt_TcAo](https://unsplash.com/photos/5yxJpt_TcAo) | 高尔夫车场景 |
| `bike.webp` | Denago eBikes | [J1QzJXtF6O8](https://unsplash.com/photos/an-electric-bike-parked-on-top-of-a-rock-J1QzJXtF6O8) | 电动自行车演示场景 |
| `kit.webp` | Wayne Bishop | [7YUW7fvIYoQ](https://unsplash.com/photos/selective-focus-photo-of-bicycle-part-7YUW7fvIYoQ) | 自行车机械部件特写 |
| `boat.webp` | Ivan Ragozin | [o9oQaOGpLz0](https://unsplash.com/photos/o9oQaOGpLz0) | 水上出行场景 |

以上是实拍的场景/示例照片，不是已交付 Shadowalker 实物的产品摄影，也不证明画面中的船采用电动推进。网站分类区附有场景参考说明，演示横幅继续保留。品牌方取得自己的型号照片后，通过 WooCommerce 商品主图/图库替换；分类摄影通过主题资源或后续媒体字段维护。不要把其他品牌的照片当作 Shadowalker 的制造证明或认证依据。

## 开发和验证

此次调整包含首页结构、独立 `tech.css`、版本化缓存键、四套翻译目录、演示摄影占位和首页 HTTP 检查。保留既有询价、购物车、支付扩展接口和 SEO 集成。验证应覆盖照片能否加载、完整原句是否可见、桌面/手机无水平溢出、菜单焦点、深色表单可读性、商品加入购物车与结账。
