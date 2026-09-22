# Shadowalker 视觉升级：独行者与真实摄影

## 用户确认的价值主张

> 人生来孤独，唯有影为永恒伴侣，行者心态不虚此生。

将这句话完整置于首页唯一 H1，保留原句、标点和中文，使用 `lang="zh-CN"` 标记。按语义拆为三行，以冷青色强调“影”。下方提供英/中/德/法/西的释义。中文原句属于品牌宣言，切换网站语言时仍保留；解释文本随语言切换。

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
