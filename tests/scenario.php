<?php
// 业务场景预设测试脚本（Task 7 验证）
//
// 验证 Xhsm\Scenario\ 命名空间下四个场景类（Finance/Payment/Government/MiniProgram）的：
// - sign/verify round-trip
// - encrypt/decrypt round-trip（还原原文）
// - hash 一致性与长度
// - 各场景输出格式特征（DER/RAW/base64）
// - 篡改数据验签失败
// - description() 返回值

require __DIR__ . '/assert.php';

echo "===== Scenario 测试开始 =====\n";

// ---------- 密钥对生成（复用 Sm2）----------
$kp = Xhsm\Sm2::generateKeyPair();
$sk = $kp['private_key'];
$pk = $kp['public_key'];
echo "私钥: " . $sk . "\n";
echo "公钥: " . $pk . "\n";

// 待签名的原始数据（普通字符串，非 hex）
$data = 'Hello Xhsm Scenario!';
$plain = '明文测试数据 scenario-1234';

// 辅助：判断字符串是否仅含 hex 字符（用于区分 base64 与 hex）
function is_pure_hex(string $s): bool
{
    return $s !== '' && preg_match('/^[0-9a-fA-F]+$/', $s) === 1;
}

// ---------- Finance（金融）：DER + hex ----------
echo "--- Finance（DER + hex）---\n";
assert_eq(
    Xhsm\Scenario\Finance::description(),
    '金融行业标准（GB/T 32918 + ASN.1 DER）',
    'Finance description 应正确'
);

$sig_fin = Xhsm\Scenario\Finance::sign($sk, $data);
assert_true(strlen($sig_fin) > 0, 'Finance 签名应非空');
// DER 签名以 0x30 SEQUENCE 开头 → hex 以 "30" 开头
assert_eq(substr($sig_fin, 0, 2), '30', 'Finance DER 签名应以 0x30 开头');
assert_true(is_pure_hex($sig_fin), 'Finance 签名应为纯 hex');
$ok = Xhsm\Scenario\Finance::verify($pk, $data, $sig_fin);
assert_true($ok, 'Finance 验签应通过');
echo "[OK] Finance sign→verify round-trip 通过，签名: " . $sig_fin . "\n";

$ct_fin = Xhsm\Scenario\Finance::encrypt($pk, $plain);
assert_true(strlen($ct_fin) > 0, 'Finance 密文应非空');
assert_true(is_pure_hex($ct_fin), 'Finance 密文应为 hex');
$pt_fin = Xhsm\Scenario\Finance::decrypt($sk, $ct_fin);
assert_eq($pt_fin, $plain, 'Finance 加解密应还原原文');
echo "[OK] Finance encrypt→decrypt round-trip 通过\n";

$h_fin = Xhsm\Scenario\Finance::hash($data);
assert_eq(strlen($h_fin), 64, 'Finance hash 应为 64 hex 字符');
assert_eq($h_fin, Xhsm\Sm3::hash($data), 'Finance hash 应等于 Sm3::hash');
echo "[OK] Finance hash 一致: " . $h_fin . "\n";

// 篡改数据验签失败
$ok = Xhsm\Scenario\Finance::verify($pk, 'tampered', $sig_fin);
assert_true(!$ok, 'Finance 篡改数据验签应失败');
echo "[OK] Finance 篡改数据验签失败验证通过\n";

// ---------- Payment（支付）：RAW + hex ----------
echo "--- Payment（RAW + hex）---\n";
assert_eq(
    Xhsm\Scenario\Payment::description(),
    '支付行业常用格式（RAW r||s）',
    'Payment description 应正确'
);

$sig_pay = Xhsm\Scenario\Payment::sign($sk, $data);
assert_true(strlen($sig_pay) > 0, 'Payment 签名应非空');
// RAW 签名 = r(32) || s(32) = 64 字节 = 128 hex 字符
assert_eq(strlen($sig_pay), 128, 'Payment RAW 签名应为 128 hex 字符（64 字节）');
assert_true(is_pure_hex($sig_pay), 'Payment 签名应为纯 hex');
$ok = Xhsm\Scenario\Payment::verify($pk, $data, $sig_pay);
assert_true($ok, 'Payment 验签应通过');
echo "[OK] Payment sign→verify round-trip 通过，签名: " . $sig_pay . "\n";

$ct_pay = Xhsm\Scenario\Payment::encrypt($pk, $plain);
$pt_pay = Xhsm\Scenario\Payment::decrypt($sk, $ct_pay);
assert_eq($pt_pay, $plain, 'Payment 加解密应还原原文');
echo "[OK] Payment encrypt→decrypt round-trip 通过\n";

$h_pay = Xhsm\Scenario\Payment::hash($data);
assert_eq(strlen($h_pay), 64, 'Payment hash 应为 64 hex 字符');
assert_eq($h_pay, $h_fin, 'Payment hash 应与 Finance hash 一致');
echo "[OK] Payment hash 一致: " . $h_pay . "\n";

$ok = Xhsm\Scenario\Payment::verify($pk, 'tampered', $sig_pay);
assert_true(!$ok, 'Payment 篡改数据验签应失败');
echo "[OK] Payment 篡改数据验签失败验证通过\n";

// ---------- Government（政府）：DER + hex ----------
echo "--- Government（DER + hex）---\n";
assert_eq(
    Xhsm\Scenario\Government::description(),
    '政务 PKI 标准（ASN.1 DER）',
    'Government description 应正确'
);

$sig_gov = Xhsm\Scenario\Government::sign($sk, $data);
assert_true(strlen($sig_gov) > 0, 'Government 签名应非空');
// DER 签名以 0x30 SEQUENCE 开头
assert_eq(substr($sig_gov, 0, 2), '30', 'Government DER 签名应以 0x30 开头');
assert_true(is_pure_hex($sig_gov), 'Government 签名应为纯 hex');
$ok = Xhsm\Scenario\Government::verify($pk, $data, $sig_gov);
assert_true($ok, 'Government 验签应通过');
echo "[OK] Government sign→verify round-trip 通过，签名: " . $sig_gov . "\n";

$ct_gov = Xhsm\Scenario\Government::encrypt($pk, $plain);
$pt_gov = Xhsm\Scenario\Government::decrypt($sk, $ct_gov);
assert_eq($pt_gov, $plain, 'Government 加解密应还原原文');
echo "[OK] Government encrypt→decrypt round-trip 通过\n";

$h_gov = Xhsm\Scenario\Government::hash($data);
assert_eq(strlen($h_gov), 64, 'Government hash 应为 64 hex 字符');
assert_eq($h_gov, $h_fin, 'Government hash 应与其他场景一致');
echo "[OK] Government hash 一致: " . $h_gov . "\n";

$ok = Xhsm\Scenario\Government::verify($pk, 'tampered', $sig_gov);
assert_true(!$ok, 'Government 篡改数据验签应失败');
echo "[OK] Government 篡改数据验签失败验证通过\n";

// ---------- MiniProgram（小程序）：DER + base64 ----------
echo "--- MiniProgram（DER + base64）---\n";
assert_eq(
    Xhsm\Scenario\MiniProgram::description(),
    '小程序平台传输格式（DER + base64）',
    'MiniProgram description 应正确'
);

$sig_mp = Xhsm\Scenario\MiniProgram::sign($sk, $data);
assert_true(strlen($sig_mp) > 0, 'MiniProgram 签名应非空');
// base64 输出：解码后首字节应为 0x30（DER SEQUENCE）
$decoded_mp = base64_decode($sig_mp);
assert_eq(ord($decoded_mp[0]), 0x30, 'MiniProgram base64 解码后应为 DER（0x30 开头）');
// base64 通常含非 hex 字符（大写字母 / + / = 等），且不应为纯 hex
assert_true(!is_pure_hex($sig_mp), 'MiniProgram base64 签名应含非 hex 字符');
$ok = Xhsm\Scenario\MiniProgram::verify($pk, $data, $sig_mp);
assert_true($ok, 'MiniProgram 验签应通过');
echo "[OK] MiniProgram sign→verify round-trip 通过，签名: " . $sig_mp . "\n";

$ct_mp = Xhsm\Scenario\MiniProgram::encrypt($pk, $plain);
$pt_mp = Xhsm\Scenario\MiniProgram::decrypt($sk, $ct_mp);
assert_eq($pt_mp, $plain, 'MiniProgram 加解密应还原原文');
echo "[OK] MiniProgram encrypt→decrypt round-trip 通过\n";

$h_mp = Xhsm\Scenario\MiniProgram::hash($data);
assert_eq(strlen($h_mp), 64, 'MiniProgram hash 应为 64 hex 字符');
assert_eq($h_mp, $h_fin, 'MiniProgram hash 应与其他场景一致');
echo "[OK] MiniProgram hash 一致: " . $h_mp . "\n";

$ok = Xhsm\Scenario\MiniProgram::verify($pk, 'tampered', $sig_mp);
assert_true(!$ok, 'MiniProgram 篡改数据验签应失败');
echo "[OK] MiniProgram 篡改数据验签失败验证通过\n";

// ---------- Uniapp（uniapp 跨端）：RAW + hex ----------
echo "--- Uniapp（RAW + hex）---\n";
assert_eq(
    Xhsm\Scenario\Uniapp::description(),
    'uniapp 跨端格式（RAW r||s + hex，sm-crypto 默认零转换）',
    'Uniapp description 应正确'
);

$sig_uni = Xhsm\Scenario\Uniapp::sign($sk, $data);
assert_true(strlen($sig_uni) > 0, 'Uniapp 签名应非空');
// RAW 签名 = r(32) || s(32) = 64 字节 = 128 hex 字符
assert_eq(strlen($sig_uni), 128, 'Uniapp RAW 签名应为 128 hex 字符（64 字节）');
assert_true(is_pure_hex($sig_uni), 'Uniapp 签名应为纯 hex');
$ok = Xhsm\Scenario\Uniapp::verify($pk, $data, $sig_uni);
assert_true($ok, 'Uniapp 验签应通过');
echo "[OK] Uniapp sign→verify round-trip 通过，签名: " . $sig_uni . "\n";

$ct_uni = Xhsm\Scenario\Uniapp::encrypt($pk, $plain);
$pt_uni = Xhsm\Scenario\Uniapp::decrypt($sk, $ct_uni);
assert_eq($pt_uni, $plain, 'Uniapp 加解密应还原原文');
echo "[OK] Uniapp encrypt→decrypt round-trip 通过\n";

$h_uni = Xhsm\Scenario\Uniapp::hash($data);
assert_eq(strlen($h_uni), 64, 'Uniapp hash 应为 64 hex 字符');
assert_eq($h_uni, $h_fin, 'Uniapp hash 应与其他场景一致');
echo "[OK] Uniapp hash 一致: " . $h_uni . "\n";

$ok = Xhsm\Scenario\Uniapp::verify($pk, 'tampered', $sig_uni);
assert_true(!$ok, 'Uniapp 篡改数据验签应失败');
echo "[OK] Uniapp 篡改数据验签失败验证通过\n";

// Uniapp(RAW+hex) 与 Payment(RAW+hex) 底层格式相同，交叉验签应通过
$ok_cross_uni = Xhsm\Scenario\Payment::verify($pk, $data, $sig_uni);
assert_true($ok_cross_uni, 'Uniapp(RAW+hex) 签名应可被 Payment(RAW+hex) 验签通过');
$ok_cross_uni2 = Xhsm\Scenario\Uniapp::verify($pk, $data, $sig_pay);
assert_true($ok_cross_uni2, 'Payment(RAW+hex) 签名应可被 Uniapp(RAW+hex) 验签通过');
echo "[OK] Uniapp 与 Payment 交叉验签通过（同 RAW+hex 配置）\n";

// ---------- Web（Web 端）：RAW + base64 ----------
echo "--- Web（RAW + base64）---\n";
assert_eq(
    Xhsm\Scenario\Web::description(),
    'Web 端格式（RAW r||s + base64，体积小）',
    'Web description 应正确'
);

$sig_web = Xhsm\Scenario\Web::sign($sk, $data);
assert_true(strlen($sig_web) > 0, 'Web 签名应非空');
// RAW 签名 base64 编码：解码后应为 64 字节（r||s）
assert_eq(strlen(base64_decode($sig_web)), 64, 'Web base64 解码后应为 64 字节（RAW r||s）');
// base64 通常含非 hex 字符（大写字母 / + / = 等），不应为纯 hex
assert_true(!is_pure_hex($sig_web), 'Web base64 签名应含非 hex 字符');
$ok = Xhsm\Scenario\Web::verify($pk, $data, $sig_web);
assert_true($ok, 'Web 验签应通过');
echo "[OK] Web sign→verify round-trip 通过，签名: " . $sig_web . "\n";

$ct_web = Xhsm\Scenario\Web::encrypt($pk, $plain);
$pt_web = Xhsm\Scenario\Web::decrypt($sk, $ct_web);
assert_eq($pt_web, $plain, 'Web 加解密应还原原文');
echo "[OK] Web encrypt→decrypt round-trip 通过\n";

$h_web = Xhsm\Scenario\Web::hash($data);
assert_eq(strlen($h_web), 64, 'Web hash 应为 64 hex 字符');
assert_eq($h_web, $h_fin, 'Web hash 应与其他场景一致');
echo "[OK] Web hash 一致: " . $h_web . "\n";

$ok = Xhsm\Scenario\Web::verify($pk, 'tampered', $sig_web);
assert_true(!$ok, 'Web 篡改数据验签应失败');
echo "[OK] Web 篡改数据验签失败验证通过\n";

// ---------- 场景间输出格式差异验证 ----------
echo "--- 场景间输出格式差异 ---\n";
// Finance 与 Government 都是 DER+hex，但 SM2 签名使用随机 nonce，两次签名结果不同
assert_true($sig_fin !== $sig_gov, 'Finance 与 Government 签名应不同（随机 nonce）');
// Finance 是 hex DER，Payment 是 hex RAW，编码不同，输出应不同
assert_true($sig_fin !== $sig_pay, 'Finance(DER+hex) 与 Payment(RAW+hex) 输出应不同');
// Finance 是 hex，MiniProgram 是 base64，外层编码不同
assert_true($sig_fin !== $sig_mp, 'Finance(hex) 与 MiniProgram(base64) 输出应不同');
// Payment 是 hex RAW，MiniProgram 是 base64 DER，明显不同
assert_true($sig_pay !== $sig_mp, 'Payment(RAW+hex) 与 MiniProgram(DER+base64) 输出应不同');
echo "[OK] 四个场景签名输出两两不同\n";

// Finance(DER+hex) 与 Government(DER+hex) 底层都是 DER 编码。
// 交叉验证：Finance 签名 → Government 验签应通过（同 DER+hex 配置）。
$ok_cross = Xhsm\Scenario\Government::verify($pk, $data, $sig_fin);
assert_true($ok_cross, 'Finance(DER+hex) 签名应可被 Government(DER+hex) 验签通过');
$ok_cross2 = Xhsm\Scenario\Finance::verify($pk, $data, $sig_gov);
assert_true($ok_cross2, 'Government(DER+hex) 签名应可被 Finance(DER+hex) 验签通过');
echo "[OK] Finance 与 Government 交叉验签通过（同 DER+hex 配置）\n";

// Finance(DER+hex) 与 MiniProgram(DER+base64) 同底层 DER，仅外层编码不同。
// 交叉验证：Finance 的 hex 签名转 base64 后应可被 MiniProgram 验签通过。
$sig_fin_as_b64 = base64_encode(hex2bin($sig_fin));
$ok_cross3 = Xhsm\Scenario\MiniProgram::verify($pk, $data, $sig_fin_as_b64);
assert_true($ok_cross3, 'Finance(hex DER) 转 base64 后应可被 MiniProgram(base64 DER) 验签通过');
// 反向：MiniProgram 签名转 hex 后可用 Finance 验签通过
$sig_mp_as_hex = bin2hex(base64_decode($sig_mp));
$ok_cross4 = Xhsm\Scenario\Finance::verify($pk, $data, $sig_mp_as_hex);
assert_true($ok_cross4, 'MiniProgram(base64 DER) 转 hex 后应可被 Finance(hex DER) 验签通过');
echo "[OK] Finance 与 MiniProgram 交叉验签通过（同 DER 不同外层编码）\n";

// Payment(RAW+hex) 与其他场景底层编码不同（RAW vs DER），无法交叉验签
// 同理 Payment 签名转 base64 后也不能被 MiniProgram 验签通过（底层 RAW vs DER）
$sig_pay_as_b64 = base64_encode(hex2bin($sig_pay));
$ok_cross5 = Xhsm\Scenario\MiniProgram::verify($pk, $data, $sig_pay_as_b64);
assert_true(!$ok_cross5, 'Payment(RAW) 转 base64 后不应被 MiniProgram(DER) 验签通过（底层编码不同）');
echo "[OK] Payment 与 MiniProgram 因底层编码不同（RAW vs DER）交叉验签失败\n";

// ---------- 公钥格式兼容（无 04 前缀）----------
echo "--- 公钥格式兼容（无 04 前缀）---\n";
$pk_no_prefix = substr($pk, 2);
$ct_np = Xhsm\Scenario\Finance::encrypt($pk_no_prefix, $plain);
$pt_np = Xhsm\Scenario\Finance::decrypt($sk, $ct_np);
assert_eq($pt_np, $plain, 'Finance 无 04 前缀公钥加解密应还原原文');
$ok = Xhsm\Scenario\Finance::verify($pk_no_prefix, $data, $sig_fin);
assert_true($ok, 'Finance 无 04 前缀公钥验签应通过');
echo "[OK] 无 04 前缀公钥兼容验证通过\n";

// ---------- 异常处理 ----------
echo "--- 异常处理 ---\n";
try {
    Xhsm\Scenario\Finance::decrypt($sk, 'zz_invalid_hex');
    assert_true(false, '非法 hex 密文应抛出异常');
} catch (Xhsm\Exception $e) {
    echo "[OK] 非法 hex 密文异常捕获: " . $e->getMessage() . "\n";
}

try {
    // 非法 UTF-8 字节：构造一个不可能为 UTF-8 的明文，加解密后还原会失败
    // 直接构造一个能解密但非 UTF-8 的场景较复杂，这里用更直接的方式：
    // 传入空数据签名/验签是否合理 — 这里改为测试用错误密钥解密会失败
    $wrong_kp = Xhsm\Sm2::generateKeyPair();
    $wrong_sk = $wrong_kp['private_key'];
    Xhsm\Scenario\Finance::decrypt($wrong_sk, $ct_fin);
    assert_true(false, '错误私钥解密应抛出异常或产生无效 UTF-8');
} catch (Xhsm\Exception $e) {
    echo "[OK] 错误私钥解密异常捕获: " . $e->getMessage() . "\n";
}

echo "===== Scenario 测试全部通过 =====\n";
