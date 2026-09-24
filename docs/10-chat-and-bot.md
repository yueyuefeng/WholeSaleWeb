# 聊天页面、人工客服与智能机器人接口

1.3.0 新增选购方案上下文：创建聊天可传 `plan`，机器人请求增加 `selection`（无方案为 null）。客服后台会显示同一方案摘要和资料更新提示。字段、分享与保留期区别详见 [选购工具文档](11-selection-tools.md)。

## 交付行为

联系入口 `/contact/` 直接打开深色聊天页，不要求访客先填姓名、邮箱或国家。左侧为车型话题，右侧为消息记录与输入框；移动端上下排列。Enter 发送，Shift + Enter 换行，输入法组词时 Enter 不会误发送。原有详细询价表保留在下方折叠区。

主题 `page-contact.php` 同时是可选择的 **Shadowalker Chat** 页面模板。新安装 seed 自动为 Contact 设置此模板；既有英文 `/contact/` 自动匹配主题模板，原页面内容不被覆盖。WPML 翻译后的联系页要选择同一模板。其他页面也可使用 `[shadowalker_chat]` 简码；每页只放一个聊天组件。

访客首次发消息或请求人工时创建会话，信息保存到本网站 WordPress 数据库。浏览器每 6 秒获取新消息，隐藏标签页暂停轮询。没有 WebSocket、打字状态、在线坐席判断或即时回复时效承诺。未配置机器人时不会伪装 AI 回复或显示“客服在线”，而是提示已保存并等待人工。

会话密钥仅存当前标签页的 `sessionStorage`，按语言和所咨询商品区分；刷新同一标签页可以恢复，关闭标签页后通常无法恢复。跨设备不互通。无须邮箱，也不会发送邮件通知，访客应保留页面等待回复。断网失败保留当前输入框草稿；刷新前应复制尚未发送的草稿。

## 人工客服操作

1. 使用管理员或 WooCommerce `shop_manager` 账户登录 WordPress。
2. 打开侧栏 **Chat inbox / 聊天收件箱**，刷新列表查看待回复会话。
3. 点会话编号，可看访客语言、关联商品与消息记录。输入回复后点击发送。
4. 访客仍打开的标签页会自动获取回复，并标注 Shadowalker 团队。客服回复或访客“转人工”均将会话设为人工模式，机器人不再继续接管该会话。

收件箱按最后活动时间排序，每页 30 条。客服需安排值守或定期检查；本版不包含外部工单通知、坐席分配或客服移动 App。普通顾客/订阅者无权查看或回复其他人的聊天。

## 接入方式 A：HTTPS 机器人适配服务

本版已经实现调用入口，不绑定具体模型供应商。适配服务负责把下述通用格式转换成所选机器人平台/模型的格式。**URL 必须是实现本协议的适配接口，不能直接填任意模型的原始接口地址。**

Docker 部署在服务器 `.env` 中配置以下两项，然后重建 WordPress/CLI 容器；密钥不要提交 Git，也不要放入页面 JS、短代码或主题设置：

```dotenv
SW_CHAT_BOT_URL=https://your-bot.example.com/shadowalker/chat
SW_CHAT_BOT_KEY=your-server-side-secret
```

非 Docker 部署可在受保护的 `wp-config.php` 中定义同名 PHP 常量，优先从主机的密钥环境变量读取。两项都留空时只启用人工消息通道。更换配置后清理联系页面缓存。

服务端在访客消息持久化后发送 POST，请求头为 `Content-Type: application/json` 和 `Authorization: Bearer <key>`。示例请求：

```json
{
  "conversation_id": 123,
  "locale": "zh_CN",
  "product_id": 42,
  "messages": [
    {"id":124,"role":"visitor","text":"我的自行车能改装吗？","time":"2026-09-23T01:00:00+00:00"}
  ]
}
```

`messages` 最多最近 20 条，角色为 `visitor`、`assistant`、`agent`。适配器自行映射到供应商的 user/assistant 角色；消息内容始终是不可信输入。`locale` 为创建会话时的语言，商品 ID 只代表用户关注的已发布商品。无需也不会发送访客密钥、IP、邮箱或 WordPress 凭据。用户自己写进聊天中的个人资料仍可能包含在正文中。

成功响应必须 HTTP 200，JSON 顶层含非空字符串 `reply`，最多 2000 字符：

```json
{"reply":"请告诉我轮径、后叉开档尺寸和现有刹车类型，我可以帮你梳理需要确认的兼容条件。"}
```

返回纯文本，不返回 HTML/Markdown 可执行内容。页面标记为“智能助手”，通过 `textContent` 显示。若需人工处理，可返回 `{"reply":null}` 或非 200 状态；超时、无效 JSON、空回复、超长回复、调用异常均保留访客消息并转入人工模式，不把供应商报错或密钥展示给访客。

HTTP 超时 8 秒，禁止重定向，响应体上限 16 KiB，只允许 HTTPS，并使用 WordPress `wp_safe_remote_post` 的目标地址校验。私网/localhost 不作为此接口的部署目标。接口同步返回完整文本，本版不支持流式或后台 webhook 回推。规模扩大或推理时间较长时，应另行实现任务队列与受认证的回调协议。

## 接入方式 B：WordPress 扩展钩子

将自定义适配器放到独立插件或 `wp-content/mu-plugins/`，通过以下过滤器返回回复，不直接修改 Core 文件：

```php
add_filter('sw_chat_bot_reply', function ($reply, array $context) {
    // 用你自己的服务端 SDK/客户端发送 $context；配置超时并验证返回值。
    // return $your_plain_text_reply; // 非空纯文本，最多 2000 字符。
    return null; // 本示例不模拟智能回答：交给人工。
}, 10, 2);
```

安装该钩子即视为机器人模式已启用。钩子优先于通用 HTTPS 适配器；返回 `null` 或 `WP_Error` 转人工。异常会被捕获，但插件作者仍应给自己的网络请求设置超时；Core 无法中断一个无限阻塞的自定义 PHP 回调。

为实际机器人配置：使用当前语言回复，仅以已审核产品资料回答，不把用户输入当系统指令，不凭空承诺库存、认证、交付日期或报价。此接口只有文本回复权限，没有改价、下单、退款、修改商品或运行工具的权限。

## 访客接口与访问控制

基路径 `/wp-json/shadowalker/v1/chat`（固定链接关闭时使用 WordPress 提供的 `rest_url` 查询形式）：

| 方法与路径 | 行为 | 认证 |
|---|---|---|
| POST `/chat` | 创建，返回 id 与一次性发放的 token | 页面 `X-Shadowalker-Nonce`；浏览器 Origin 校验 |
| GET `/chat/{id}` | 读取本会话消息 | `X-Shadowalker-Token` |
| POST `/chat/{id}/messages` | 发送 `text` 与 `request_id` | 同上 |
| POST `/chat/{id}/handoff` | 请求人工 | 同上 |
| DELETE `/chat/{id}` | 删除本会话和消息 | 同上 |

前端会为登录用户同时提交 WordPress REST nonce。创建 nonce 不等于访客身份认证；真正的会话所有权由 256 位随机 token 校验，数据库仅存 SHA-256。token 只放请求头，不放 URL。不要记录这个请求头或把浏览器会话数据分享到公开场合。

每次发送生成 `request_id`，失败重试同一草稿保持相同 ID，服务端在会话锁内去重，避免重复入库或重复调用机器人。锁超时 60 秒；多实例部署应共用数据库。每个会话最多 100 条记录（为客服保留末尾回复容量），每条最多 2000 字符；达到上限可开启新会话。

限流按固定时间窗口：每个直连 IP 每小时最多创建 12 个会话、发送 120 条消息；每会话每分钟最多发送 30 条、读取 120 次。transient 限流用于基础防刷，并非严格原子计数；大流量上线需配合网关/WAF 限速。只有服务器直连地址参与 IP 限流，不信任任意转发头；反向代理必须正确传递真实地址，否则多人可能共用限额。

会话与消息为非公开 CPT，没有通用 REST 列表，不能通过猜编号读取。所有聊天 API 成功或错误响应使用 `Cache-Control: no-store, private`。CDN/缓存插件必须排除 `/contact/`、各语言联系页、所有聊天 REST 路径和带 `rest_route` 的等效请求。外站访问、缺失 token、跨会话 token、过期 token 应被拒绝。

## 保留期、清理与资源

会话从创建起 30 天后失效，访客可以提前删除。每日 `sw_chat_cleanup` 清理过期会话及消息，批量 100 个，积压时继续安排下一批。实际删除时间取决于调度是否正常；后台备份和第三方机器人服务的保留期须另行设置。本地环境默认关闭自动 WP-Cron，可执行 `wp cron event run sw_chat_cleanup` 验证；生产沿用部署文档的系统 cron 执行 `wp cron event run --due-now`。

匿名会话没有邮箱字段，因此不能可靠地把 WordPress 按邮箱导出/删除工具绑定到这些会话。访客可在原标签页用删除按钮处理；关闭标签页丢失 token 后，由运营依据可核实的信息协助处理，或等待到期清理。需要登录顾客历史/按邮箱导出时，应明确增加账户绑定流程，不能仅凭任意邮箱查询聊天。

人工聊天只使用既有 WordPress/PHP/数据库资源，无需独立消息服务器。机器人模式另外需要：HTTPS 适配服务、模型账户/额度、产品知识库、密钥管理、超时与用量监控，以及客服接管安排。不要在未指定供应商、模型和用量前给出确定 AI 月费。

## 验证与上线

执行 `wp eval-file tests/chat.php` 和 `python3 tests/chat-http.py <站点URL>`。测试仅用于可丢弃环境，包含临时会话写入和删除。浏览器验收：直接发消息、刷新恢复、另一标签页无记录、后台客服真实登录回复、访客自动获取、断网草稿重试、人工接管、删除、手机端、不同语言。

正式接入后再验证实际供应商返回、失败降级、中文及其他语言回答质量、提示注入防护、费用限额、隐私页中的处理商与数据说明。当前代码不包含真实机器人凭据，也没有宣称实际模型已经联网工作。

实现依据：[WordPress 自定义 REST 接口](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/)、[安全 HTTP POST](https://developer.wordpress.org/reference/functions/wp_safe_remote_post/)。
