# Tasks

- [x] Task 1: 初始化 Rust + ext-php-rs 0.15 扩展项目骨架
  - [x] SubTask 1.1: 创建 `Cargo.toml`，锁定 `ext-php-rs = "0.15"`（解析到 0.15.15），暂不引入国密 crate
  - [x] SubTask 1.2: 创建 `src/lib.rs`，定义 `#[php_module]` 入口与 `get_module`，注册 `xhsm_version` 冒烟函数
  - [x] SubTask 1.3: 验证 `cargo build --release` 产出 `libxhsm.so`（595KB）
  - [x] SubTask 1.4: `php -d extension=...` 加载后 `extension_loaded("xhsm")` 返回 `true`，`xhsm_version()` 返回 `0.1.0`

- [x] Task 2: 实现 SM3 摘要模块
  - [x] SubTask 2.1: 封装纯 Rust SM3 crate，实现 `Xhsm\Sm3` 类（`hash` / `hmac` 静态方法）
  - [x] SubTask 2.2: 编写 PHP 测试验证 32 字节摘要、已知向量与 HMAC 输出

- [x] Task 3: 实现 SM4 对称算法模块
  - [x] SubTask 3.1: 封装 SM4，支持 ECB / CBC / CTR / GCM 四种模式
  - [x] SubTask 3.2: 实现 PKCS#7 填充（CBC/ECB）与 GCM Tag 处理
  - [x] SubTask 3.3: 编写 PHP 测试验证各模式加解密往返与已知向量

- [x] Task 4: 实现 SM2 非对称算法模块
  - [x] SubTask 4.1: 实现 `Xhsm\Sm2` 类，密钥对生成（压缩/非压缩公钥）
  - [x] SubTask 4.2: 实现加解密，支持 C1C2C3 与 C1C3C2 两种顺序
  - [x] SubTask 4.3: 实现签名/验签，支持 ASN.1 DER 与 raw hex 编码
  - [x] SubTask 4.4: 编写 PHP 测试验证加解密与签名验签往返

- [x] Task 5: 实现 SM9 标识基算法模块
  - [x] SubTask 5.1: 选定并封装纯 Rust SM9 crate（实现阶段确认可用性与审计情况）
  - [x] SubTask 5.2: 实现主密钥生成、用户私钥抽取
  - [x] SubTask 5.3: 实现标识加解密与标识签名验签
  - [x] SubTask 5.4: 编写 PHP 测试验证标识加解密与签名往返

- [x] Task 6: 实现可扩展签名版本体系
  - [x] SubTask 6.1: 设计签名版本配置结构（算法、格式、编码、用户 ID 等字段）
  - [x] SubTask 6.2: 实现版本注册表（线程安全）与 `Xhsm\Signature` 类（`sign` / `verify` / `register` / `versions`）
  - [x] SubTask 6.3: 内置 s2 / s3 / s4 三个版本，明确各自配置差异
  - [x] SubTask 6.4: 实现自定义版本注册与版本列表查询
  - [x] SubTask 6.5: 编写 PHP 测试覆盖版本切换、注册、列表与签名验签

- [x] Task 7: 实现业务场景预设
  - [x] SubTask 7.1: 实现 `Xhsm\Scenario\Finance`（金融：ASN.1 DER + GB/T 标准）
  - [x] SubTask 7.2: 实现 `Xhsm\Scenario\Payment`（支付：主流支付协议格式）
  - [x] SubTask 7.3: 实现 `Xhsm\Scenario\Government`（政府：政务 PKI 标准）
  - [x] SubTask 7.4: 实现 `Xhsm\Scenario\MiniProgram`（小程序：主流小程序平台格式）
  - [x] SubTask 7.5: 编写 PHP 测试验证各场景输出格式与往返

- [x] Task 8: 异常处理与错误传播
  - [x] SubTask 8.1: 定义 `Xhsm\Exception` 异常类（错误码 + 消息字段）
  - [x] SubTask 8.2: 将 Rust 层 `Result::Err` 统一映射为 `Xhsm\Exception` 抛出
  - [x] SubTask 8.3: 编写 PHP 测试验证异常码与可读消息

- [x] Task 9: 不依赖 OpenSSL 验证
  - [x] SubTask 9.1: 审查 Cargo 依赖树（`cargo tree`），确认无 libssl/libcrypto 传递链接
  - [x] SubTask 9.2: 在无 openssl 开发库环境构建并加载扩展，跑通全部用例
  - [x] SubTask 9.3: 记录 `ldd xhsm.so` 输出证明无系统 openssl 依赖

- [x] Task 10: ThinkPHP 8 适配层
  - [x] SubTask 10.1: 创建 PHP composer 包目录 `php/` 与 `composer.json`（声明 `topthink/framework: ^8.0`）
  - [x] SubTask 10.2: 实现 ServiceProvider 与 Facade（`Xhsm` 门面）
  - [x] SubTask 10.3: 实现配置发布命令 `php think xhsm:publish`，生成 `config/xhsm.php`
  - [x] SubTask 10.4: 在 ThinkPHP 8 项目中集成验证调用链（`Xhsm::sm2()->sign(...)`）

- [x] Task 11: 文档与示例
  - [x] SubTask 11.1: 完善 README 使用示例（各算法、各场景、签名版本）
  - [x] SubTask 11.2: 补充构建与安装说明（cargo / cargo-php / php.ini）
  - [x] SubTask 11.3: 整理已知测试向量清单

# Task Dependencies
- Task 2 / Task 3 / Task 4 相互无依赖，可并行
- Task 5 依赖 Task 2 / Task 4 的基础封装模式（参考其类结构）
- Task 6 依赖 Task 4（SM2 签名能力）
- Task 7 依赖 Task 6（场景预设复用签名版本体系）
- Task 8 贯穿所有算法模块，建议在 Task 2-7 实现过程中同步推进
- Task 9 依赖 Task 1-7 完成（全量构建后统一验证）
- Task 10 依赖 Task 1（扩展可加载）即可开始
- Task 11 依赖 Task 1-10 完成
