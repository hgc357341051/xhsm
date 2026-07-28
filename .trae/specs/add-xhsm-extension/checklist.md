# Checklist

- [x] 扩展基于 Rust + ext-php-rs 0.15.x 构建，`#[php_module]` 注册成功
- [x] PHP 8.0+ 环境下 `extension_loaded('xhsm')` 返回 `true`
- [x] 所有功能以 `Xhsm\` 命名空间下 OOP 类形式暴露
- [x] `Xhsm\Sm2` 支持密钥对生成（压缩/非压缩）、加解密（C1C2C3/C1C3C2）、签名/验签（ASN.1 DER / raw hex）
- [x] `Xhsm\Sm3` 支持 `hash` 与 `hmac`，输出 32 字节摘要 hex
- [x] `Xhsm\Sm4` 支持 ECB / CBC / CTR / GCM 四种模式，PKCS#7 填充与 GCM Tag 校验正确
- [x] `Xhsm\Sm9` 支持主密钥生成、用户私钥抽取、标识加解密、标识签名验签
- [x] `Xhsm\Signature` 内置 s2 / s3 / s4 三个版本，每个版本可配置算法/格式/编码/用户 ID
- [x] `Xhsm\Signature::register()` 支持注册自定义版本（如 s5）
- [x] `Xhsm\Signature::versions()` 返回已注册版本列表
- [x] `Xhsm\Scenario\Finance` / `Payment` / `Government` / `MiniProgram` 四类预设实现
- [x] `Xhsm\Exception` 异常类定义，Rust 层错误统一转换为异常抛出（含错误码与消息）
- [x] 扩展不链接系统 libssl/libcrypto，`ldd xhsm.so` 输出无 openssl 依赖
- [x] 全部国密算法使用纯 Rust 密码学 crate 实现（无系统 openssl 依赖）
- [x] `cargo build --release` 与 `cargo php install` 流程可用
- [x] ThinkPHP 8 ServiceProvider / Facade / `php think xhsm:publish` 配置发布命令可用
- [x] ThinkPHP 8 项目中可通过 `Xhsm::sm2()->sign(...)` 调用扩展能力
- [x] 各算法 / 各场景 / 各签名版本均有 PHP 测试覆盖且通过
