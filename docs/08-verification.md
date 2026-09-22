# 交付验证记录

检查日期：2026-09-22。此记录区分已运行的检查与仍需账户/基础设施的验收。

## 已完成

- PHP 8.3.33 官方 Windows 便携运行时，下载后校验发布方 SHA-256；自有 PHP 文件语法检查通过。
- WordPress 7.1.1 + WooCommerce 11.1.1，使用官方 SQLite Database Integration 3.0.2 建立隔离本地验证环境。SQLite 仅用于本地验证；另有下述 Docker/MariaDB CI 验证。
- 演示导入执行两次，同一 SKU 没有重复，既有页面/商品不会被覆盖。
- 24 项真实 WordPress/WooCommerce 业务断言通过：普通配件购买、整车询价限制、变体继承、直接加购拒绝、结构化数据、输入校验、私有数据权限、四语言翻译加载等。
- HTTP 首页、商城、联系、品牌故事返回 200；不存在页面返回 404。
- HTTP 询价：错误 nonce 403、无效数量/未同意隐私 422、蜜罐 400、合法请求保存后跳转成功、重复请求 429。
- 四个翻译目录各 100 条字符串完整性、占位符和二进制 MO 读取校验通过。
- Playwright + Microsoft Edge 检查真实首页 1440px / 390px：无水平溢出、移动导航可以打开并用 Escape 关闭、页面无 JavaScript 异常；390px 商城无水平溢出。桌面和移动整页截图已导出。
- 浏览器验证整车详情没有购买按钮，询价按钮带入正确商品；配件加入购物车后会话保存产品与 399 USD 演示金额，原生区块结账显示同一商品；390px 结账无水平溢出。测试等待 WooCommerce 区块完成客户端加载后再断言，未尝试真实付款。

本地初始化曾因自制 CLI 引导器缺少 WP-CLI 的依赖类失败，已改为官方 WP-CLI 2.12.0 完成安装；固定链接和遗漏译文已修复。该临时引导器不在仓库交付中。主题切换命令触发过 WooCommerce 早期翻译加载 notice，后续 HTTP 检查未观察到致命错误。

## 自动化与待验收项

初始实现提交 `f032bffbb95fff8506fc7735f6780474f54349f5` 的 [GitHub Actions 检查已通过](https://github.com/yueyuefeng/WholeSaleWeb/actions/runs/35682129337)：Docker/MariaDB 启动、安装 WordPress/WooCommerce/Stripe/PayPal/Yoast、两次 seed、24 项业务断言、翻译完整性、HTTP 页面与询价流程均成功。后续提交若仅更新本记录，以各自 GitHub Actions 结果为准。插件成功安装不代表账户级支付联调已经完成。

尚未完成：真实域名与 HTTPS、生产主机部署、WPML 商业插件联调、五语言全部商品和政策的人工审校、WCML 汇率/多币种订单、Stripe/PayPal 商户连接与支付退款、SMTP 实际送达、真实物流/税费、生产恢复演练、Core Web Vitals 实测。

这些步骤需要域名/主机、许可证、真实商品数据、明确销售国家和商户主体。尚未启用真实收款或公开站点索引。所有演示商品、价格、插画和政策草稿须替换后才可正式销售。