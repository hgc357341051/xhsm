<?php
// SM2 算法测试脚本（Task 4 验证）
//
// 验证 Xhsm\Sm2 类的密钥对生成、加解密（C1C3C2/C1C2C3/ASN1）、签名验签（DER/RAW）。

require __DIR__ . '/assert.php';

echo "===== SM2 测试开始 =====\n";

// ---------- 密钥对生成 ----------
echo "--- 密钥对生成 ---\n";
$kp = Xhsm\Sm2::generateKeyPair();
assert_true(isset($kp['private_key']), '密钥对应包含 private_key');
assert_true(isset($kp['public_key']), '密钥对应包含 public_key');
assert_eq(strlen($kp['private_key']), 64, '私钥应为 64 hex 字符（32 字节）');
assert_eq(strlen($kp['public_key']), 130, '公钥应为 130 hex 字符（04 + 64 字节）');
assert_eq(substr($kp['public_key'], 0, 2), '04', '公钥应以 04 非压缩前缀开头');
echo "[OK] 密钥对生成验证通过\n";
echo "  私钥: " . $kp['private_key'] . "\n";
echo "  公钥: " . $kp['public_key'] . "\n";

$sk = $kp['private_key'];
$pk = $kp['public_key'];

// ---------- 加解密（C1C3C2 默认）----------
echo "--- 加解密 C1C3C2 ---\n";
$plaintext = '48656c6c6f20534d3221'; // "Hello SM2!" 的 hex
$ct = Xhsm\Sm2::encrypt($pk, $plaintext);
$pt = Xhsm\Sm2::decrypt($sk, $ct);
assert_eq(strtolower($pt), strtolower($plaintext), 'SM2 C1C3C2 加解密往返');
echo "[OK] SM2 C1C3C2 加解密往返验证通过\n";

// 默认模式应为 C1C3C2
$ct_default = Xhsm\Sm2::encrypt($pk, $plaintext);
$pt_default = Xhsm\Sm2::decrypt($sk, $ct_default);
assert_eq($pt_default, $pt, 'SM2 默认模式应为 C1C3C2');
echo "[OK] SM2 默认 C1C3C2 模式验证通过\n";

// ---------- 加解密（C1C2C3）----------
echo "--- 加解密 C1C2C3 ---\n";
$ct2 = Xhsm\Sm2::encrypt($pk, $plaintext, 'C1C2C3');
$pt2 = Xhsm\Sm2::decrypt($sk, $ct2, 'C1C2C3');
assert_eq(strtolower($pt2), strtolower($plaintext), 'SM2 C1C2C3 加解密往返');
echo "[OK] SM2 C1C2C3 加解密往返验证通过\n";

// C1C3C2 与 C1C2C3 密文不同（排列顺序不同）
assert_true($ct !== $ct2, 'C1C3C2 与 C1C2C3 密文应不同');
echo "[OK] C1C3C2 与 C1C2C3 密文差异验证通过\n";

// ---------- 加解密（ASN1）----------
echo "--- 加解密 ASN1 ---\n";
$ct3 = Xhsm\Sm2::encrypt($pk, $plaintext, 'ASN1');
$pt3 = Xhsm\Sm2::decrypt($sk, $ct3, 'ASN1');
assert_eq(strtolower($pt3), strtolower($plaintext), 'SM2 ASN1 加解密往返');
echo "[OK] SM2 ASN1 加解密往返验证通过\n";

// ---------- 签名验签（DER）----------
echo "--- 签名验签 DER ---\n";
$sig = Xhsm\Sm2::sign($sk, $plaintext);
assert_true(strlen($sig) > 0, 'SM2 DER 签名应非空');
$ok = Xhsm\Sm2::verify($pk, $plaintext, $sig);
assert_true($ok, 'SM2 DER 验签应通过');
echo "[OK] SM2 DER 签名验签验证通过\n";
echo "  签名(DER): " . $sig . "\n";

// 默认格式应为 DER：签名以 0x30（SEQUENCE）标签开头
$sig_default = Xhsm\Sm2::sign($sk, $plaintext);
assert_eq(substr($sig_default, 0, 2), '30', 'SM2 默认签名应为 DER 格式（0x30 SEQUENCE 开头）');
$ok = Xhsm\Sm2::verify($pk, $plaintext, $sig_default);
assert_true($ok, 'SM2 默认格式签名验签应通过');
echo "[OK] SM2 默认 DER 签名格式验证通过\n";

// ---------- 签名验签（RAW）----------
echo "--- 签名验签 RAW ---\n";
$sig_raw = Xhsm\Sm2::sign($sk, $plaintext, 'RAW');
// RAW 签名 = r(32字节) || s(32字节) = 64 字节 = 128 hex 字符
assert_eq(strlen($sig_raw), 128, 'SM2 RAW 签名应为 128 hex 字符（64 字节）');
$ok = Xhsm\Sm2::verify($pk, $plaintext, $sig_raw, 'RAW');
assert_true($ok, 'SM2 RAW 验签应通过');
echo "[OK] SM2 RAW 签名验签验证通过\n";
echo "  签名(RAW): " . $sig_raw . "\n";

// DER 与 RAW 互转一致性：各自签名验签均应通过
$ok_der = Xhsm\Sm2::verify($pk, $plaintext, $sig);
$ok_raw = Xhsm\Sm2::verify($pk, $plaintext, $sig_raw, 'RAW');
assert_true($ok_der && $ok_raw, 'SM2 DER 与 RAW 签名格式验签均应通过');
echo "[OK] SM2 DER/RAW 签名格式互转验证通过\n";

// ---------- 验签失败场景 ----------
echo "--- 验签失败场景 ---\n";

// 篡改数据
$ok = Xhsm\Sm2::verify($pk, 'aabbcc', $sig);
assert_true(!$ok, 'SM2 篡改数据后验签应失败');
echo "[OK] SM2 篡改数据验签失败验证通过\n";

// 篡改签名
$tampered_sig = substr($sig, 0, -2) . 'ff';
$ok = Xhsm\Sm2::verify($pk, $plaintext, $tampered_sig);
assert_true(!$ok, 'SM2 篡改签名后验签应失败');
echo "[OK] SM2 篡改签名验签失败验证通过\n";

// ---------- 公钥格式兼容 ----------
echo "--- 公钥格式兼容 ---\n";
// 不带 04 前缀的公钥也应可用
$pk_no_prefix = substr($pk, 2);
$ct_np = Xhsm\Sm2::encrypt($pk_no_prefix, $plaintext);
$pt_np = Xhsm\Sm2::decrypt($sk, $ct_np);
assert_eq(strtolower($pt_np), strtolower($plaintext), 'SM2 无前缀公钥加解密');
echo "[OK] SM2 无 04 前缀公钥兼容验证通过\n";

// ---------- 异常处理 ----------
echo "--- 异常处理 ---\n";

try {
    Xhsm\Sm2::encrypt($pk, 'zz_invalid_hex');
    assert_true(false, '非法 hex 应抛出异常');
} catch (Xhsm\Exception $e) {
    echo "[OK] 非法 hex 异常捕获: " . $e->getMessage() . "\n";
}

try {
    Xhsm\Sm2::encrypt($pk, $plaintext, 'INVALID_MODE');
    assert_true(false, '不支持的模式应抛出异常');
} catch (Xhsm\Exception $e) {
    echo "[OK] 不支持模式异常捕获: " . $e->getMessage() . "\n";
}

echo "===== SM2 测试全部通过 =====\n";
