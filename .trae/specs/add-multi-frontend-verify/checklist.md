# Checklist

## 后端场景预设
- [x] `src/scenario.rs` 新增 `UNIAPP_CONFIG`（RAW+hex）与 `WEB_CONFIG`（RAW+base64）常量
- [x] `src/scenario.rs` 新增 `Uniapp` 与 `Web` 两个 `#[php_class]` 类，命名空间分别为 `Xhsm\Scenario\Uniapp` / `Xhsm\Scenario\Web`
- [x] 两新类均实现 sign/verify/encrypt/decrypt/hash/description 六个静态方法
- [x] `src/lib.rs` 的 `get_module` 注册 `Uniapp` 与 `Web`，顶部 `use` 导入对应类型
- [x] `cargo build --release`（或 `cargo check`）通过
- [x] 六场景覆盖 DER+hex / RAW+hex / DER+base64 / RAW+base64 全部 4 组合

## 后端测试
- [x] `tests/scenario.php` 新增 Uniapp 与 Web 场景测试段
- [x] Uniapp 测试：description、sign→verify、128 hex 字符格式校验、encrypt→decrypt、hash、篡改失败
- [x] Web 测试：description、sign→verify、base64 解码后 64 字节校验、encrypt→decrypt、hash、篡改失败
- [x] Uniapp(RAW+hex) 与 Payment(RAW+hex) 交叉验签通过（同底层格式）
- [x] `php -d extension=... tests/scenario.php` 运行通过

## 前端 npm 包 xhsm-verify
- [x] `js/xhsm-verify/package.json` 存在，name=xhsm-verify，含 exports 子路径 ./miniprogram 与 ./web
- [x] `miniprogram-sm-crypto` 与 `sm-crypto` 声明为 peerDependencies
- [x] `src/codec.js` 实现 base64ToHex（含防溢出）与 hexToBase64
- [x] `src/verify.js` 实现 verifySignature，支持 format: der-hex/raw-hex/der-base64/raw-base64
- [x] `miniprogram.js` / `web.js` 多端入口，导出 verifySignature + base64ToHex + hexToBase64
- [x] `index.d.ts` TypeScript 类型声明存在
- [x] `test/codec.test.js`：base64ToHex/hexToBase64 互逆 + 边界 + Buffer 对照通过
- [x] `test/verify.test.js`：四种 format 真实向量验签全部 true，篡改 false
- [x] `npm test` 通过

## README 文档
- [x] 「业务场景预设」章节场景表与示例加入 Uniapp（RAW+hex）与 Web（RAW+base64）
- [x] 新增「多端验签总览」章节
- [x] 全 4 格式 × 3 端对照表（格式/体积/前端转换/推荐场景）
- [x] 选型建议（小程序/uniapp/Web 各推荐格式）
- [x] xhsm-verify npm 包用法（安装 + 引入 + verifySignature 示例）
- [x] 各端原生 sm-crypto 验签代码（小程序/uniapp/Web，覆盖 RAW+hex 与 DER+base64）
- [x] 联调测试与排查清单覆盖多格式

## 提交与推送
- [x] 改动已在本地 `main` 分支提交
- [x] 已推送到远程 `origin/main`
