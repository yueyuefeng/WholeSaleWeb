# 国际在线支付配置

## 先确认商户资格

要确认公司注册国家/地区、法人资料、结算银行、产品类型、交易币种、销售国家与预计客单价，再确定支付机构。中文网站或中国顾客并不代表商户可在任何地方开通同一种收款账户。Stripe、PayPal 的可用性以商户后台审核及 [Stripe 全球可用地区](https://stripe.com/global)、[PayPal 官方商业站点](https://www.paypal.com/business/) 为准。

代码提供两个官方插件的安装流程与 WooCommerce 原生结账兼容，不自制支付页、卡数据存储或回调。没有商户账户/密钥时，不能启用真实收款。不要将 API secret、Webhook secret、许可证或客户资料提交到仓库。

## 目标市场候选方案

以下是渠道选型起点，**不是所有商户都可开通的承诺**。语言不会决定用户国籍；由官方网关根据账户资格、账单国家、币种、金额、设备及产品限制计算可用方式。表中地方支付方法以当前 Stripe WooCommerce 插件列出的能力为依据，需逐项沙箱与小额验证。参考 [官方附加支付方式说明](https://woocommerce.com/document/stripe/setup-and-configuration/additional-payment-methods/)。

| 市场 | 首选通用渠道 | 可评估的当地方式 | 核验项 |
|---|---|---|---|
| 美国/加拿大 | 信用卡、PayPal、Apple Pay/Google Pay | 美国 ACH；加拿大 PADs | 商户国家、币种、延迟到账与退款规则 |
| 英国 | 卡、PayPal、钱包 | BACS；具备资格时 Klarna | GBP、客户地址与授权流程 |
| 德国/法国/西班牙 | 卡、PayPal | SEPA；具备资格时 Klarna | EUR、延迟支付、金额与产品限制 |
| 荷兰 | 卡、PayPal | iDEAL / Wero（按平台当前名称） | EUR、商户审核与插件版本 |
| 比利时 | 卡、PayPal | Bancontact | EUR 与买家资格 |
| 奥地利 | 卡、PayPal | EPS | EUR 与商户资格 |
| 波兰 | 卡、PayPal | Przelewy24、BLIK | 支持币种、波兰账单地址 |
| 葡萄牙 | 卡、PayPal | Multibanco | 异步支付状态、过期订单 |
| 澳大利亚 | 卡、PayPal、钱包 | BECS、Afterpay（符合资格时） | AUD、到账与订单状态 |
| 中国相关买家 | 商户实际支持的卡或钱包 | Alipay、WeChat Pay（Stripe 插件列出支持，仍需账户资格） | 境外/境内主体可用性、币种与行业审核 |
| 巴西/墨西哥 | 卡、PayPal（当地账户可用时） | Boleto / OXXO（符合资格时） | 当地币种、异步确认与过期规则 |

不要只因 Stripe 仪表板显示某方法便认为 WooCommerce 扩展也支持；优先核对插件当前官方列表。若主体不符合 Stripe 条件，可单独评估具备官方 WooCommerce 扩展的其他当地 PSP；本版未集成这些额外网关。

## Stripe

1. WooCommerce → 设置 → 支付 → Stripe，按官方连接流程连接商户。
2. 先使用测试模式，确认测试账户、测试 Webhook endpoint 和签名一致；使用插件实际显示的回调 URL，不猜写地址。
3. 启用卡支付及需要的本地方式；Apple Pay/Google Pay 按官方网关进行域名和设备验证。参考 [钱包与快捷结账](https://woocommerce.com/document/stripe/setup-and-configuration/express-checkouts/)。
4. 测试普通成功、失败、3DS、取消、重复回调、异步支付、超时、全额退款及部分退款。
5. 异步支付必须等待网关确认后才履约，不能以客户访问“感谢页”作为已付款依据。
6. 正式启用前再次核对生产账户、生产 Webhook、HTTPS、邮件、税费和运费。以受控真实商品做小额付款退款后再扩大上线。

## PayPal

安装 WooCommerce PayPal Payments 后，连接商户沙箱账号与测试买家账号，测试批准/取消、订单捕获、库存变化、退款和重复回调。完成验证后连接生产账户。PayPal 本地支付方式按资格动态显示，参考 [官方 PayPal 本地方式](https://woocommerce.com/document/woocommerce-paypal-payments/local-payment-methods/)。不要同时保留多个重复的 PayPal 扩展导致结账重复按钮。

## 订单、税费和履约

- 收款成功的订单应在网关与 WooCommerce 金额、币种、交易 ID 上一致；库存扣减只发生一次。
- 询价商品不能因启用快捷钱包绕过购买限制。大件先报价，后通过确认过的 WooCommerce 可付款订单链接收款；本版不自动生成报价订单。
- 未付款/拒付/异步处理中不出库；银行转账需要人工核账。
- 高尔夫车/电动船和锂电池运费按实际物流报价；如需定金/尾款，另评估经过验证的 deposits 扩展。
- 税率、VAT/GST、进口关税与 DDP/DDU 等贸易安排由具备资格的业务人员确认；仓库没有预置法务或税务结论。
- PCI 范围、风控策略和支付平台审查遵循所选提供商要求；本项目不储存卡号。

## 失败排查与记录

后台 WooCommerce → 状态 → 日志，检查网关日志与 Scheduled Actions。日志不能公开发布或直接上传 issue，先去掉客户身份信息、token 和密钥。发生支付成功但订单未更新，先核对平台支付与 Webhook，再修复状态，避免让客户重复付款。

验收记录须列明：国家/账单地址、语言、结账币种、设备、插件版本、模式、订单号、交易状态、退款状态。未通过的渠道保持关闭。
