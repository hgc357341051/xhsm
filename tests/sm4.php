<?php
// SM4 算法测试脚本（Task 3 验证）
//
// 验证 Xhsm\Sm4 类的 encrypt/decrypt 方法，
// 覆盖 ECB/CBC/CTR/GCM 四种模式的加解密往返与已知向量。

require __DIR__ . '/assert.php';

echo "===== SM4 测试开始 =====\n";

// 标准 SM4 测试密钥与数据（GB/T 32907-2016 示例）
$key_hex    = '0123456789abcdeffedcba9876543210';
// ECB 单块明文与标准密文
$plain_hex  = '0123456789abcdeffedcba9876543210';
$ecb_ct_hex = '681edf34d206965e86b3e94f536e4246';

// ---------- ECB 模式 ----------
echo "--- ECB 模式 ---\n";

// 已知向量：明文恰好为 16 字节单块，PKCS#7 会补一整块填充
// 验证密文第一块等于标准 SM4 单块加密结果
$ecb_enc = Xhsm\Sm4::encrypt($key_hex, '', $plain_hex, 'ECB');
assert_eq(
    strtolower(substr($ecb_enc, 0, 32)),
    $ecb_ct_hex,
    'SM4-ECB 已知向量加密（第一块）'
);
echo "[OK] SM4-ECB 已知向量加密验证通过\n";

// 往返解密
$ecb_dec = Xhsm\Sm4::decrypt($key_hex, '', $ecb_enc, 'ECB');
assert_eq(strtolower($ecb_dec), $plain_hex, 'SM4-ECB 往返解密');
echo "[OK] SM4-ECB 往返解密验证通过\n";

// 多块 + PKCS#7 填充往返
$multi_hex = 'aabbccddeeff00112233445566778899aabbccddeeff00112233445566778899';
$enc = Xhsm\Sm4::encrypt($key_hex, '', $multi_hex, 'ECB');
$dec = Xhsm\Sm4::decrypt($key_hex, '', $enc, 'ECB');
assert_eq(strtolower($dec), $multi_hex, 'SM4-ECB 多块 PKCS#7 往返');
echo "[OK] SM4-ECB 多块 PKCS#7 填充往返验证通过\n";

// ---------- CBC 模式 ----------
echo "--- CBC 模式 ---\n";
$iv_hex = '000102030405060708090a0b0c0d0e0f';

// 往返加解密
$cbc_enc = Xhsm\Sm4::encrypt($key_hex, $iv_hex, $plain_hex, 'CBC');
$cbc_dec = Xhsm\Sm4::decrypt($key_hex, $iv_hex, $cbc_enc, 'CBC');
assert_eq(strtolower($cbc_dec), $plain_hex, 'SM4-CBC 往返解密');
echo "[OK] SM4-CBC 往返解密验证通过\n";

// 默认模式为 CBC
$cbc_default = Xhsm\Sm4::encrypt($key_hex, $iv_hex, $plain_hex);
assert_eq($cbc_default, $cbc_enc, 'SM4 默认模式应为 CBC');
echo "[OK] SM4 默认 CBC 模式验证通过\n";

// 多块往返
$enc = Xhsm\Sm4::encrypt($key_hex, $iv_hex, $multi_hex, 'CBC');
$dec = Xhsm\Sm4::decrypt($key_hex, $iv_hex, $enc, 'CBC');
assert_eq(strtolower($dec), $multi_hex, 'SM4-CBC 多块往返');
echo "[OK] SM4-CBC 多块往返验证通过\n";

// ---------- CTR 模式 ----------
echo "--- CTR 模式 ---\n";

// CTR 是流密码模式，密文长度等于明文长度
$ctr_enc = Xhsm\Sm4::encrypt($key_hex, $iv_hex, $plain_hex, 'CTR');
$ctr_dec = Xhsm\Sm4::decrypt($key_hex, $iv_hex, $ctr_enc, 'CTR');
assert_eq(strtolower($ctr_dec), $plain_hex, 'SM4-CTR 往返解密');
echo "[OK] SM4-CTR 往返解密验证通过\n";

// CTR 密文长度 = 明文长度（无填充）
assert_eq(strlen($ctr_enc), strlen($plain_hex), 'SM4-CTR 密文长度应等于明文长度');
echo "[OK] SM4-CTR 无填充长度验证通过\n";

// 变长明文
$odd_hex = 'aabbcc';
$enc = Xhsm\Sm4::encrypt($key_hex, $iv_hex, $odd_hex, 'CTR');
$dec = Xhsm\Sm4::decrypt($key_hex, $iv_hex, $enc, 'CTR');
assert_eq(strtolower($dec), $odd_hex, 'SM4-CTR 变长明文往返');
echo "[OK] SM4-CTR 变长明文往返验证通过\n";

// ---------- GCM 模式 ----------
echo "--- GCM 模式 ---\n";
$gcm_iv  = '000102030405060708090a0b'; // 12 字节 IV
$aad_hex = 'deadbeef';                  // 附加认证数据

// 往返加解密
$gcm_enc = Xhsm\Sm4::encrypt($key_hex, $gcm_iv, $plain_hex, 'GCM', $aad_hex);
$gcm_dec = Xhsm\Sm4::decrypt($key_hex, $gcm_iv, $gcm_enc, 'GCM', $aad_hex);
assert_eq(strtolower($gcm_dec), $plain_hex, 'SM4-GCM 往返解密');
echo "[OK] SM4-GCM 往返解密验证通过\n";

// GCM 输出 = 密文 + 16 字节 Tag
$plain_bytes = strlen($plain_hex) / 2;
$expected_ct_len = $plain_bytes * 2 + 32; // hex: 密文 + 16字节Tag
assert_eq(strlen($gcm_enc), $expected_ct_len, 'SM4-GCM 输出长度应为 密文+16字节Tag');
echo "[OK] SM4-GCM 输出长度验证通过\n";

// GCM 变长明文
$enc = Xhsm\Sm4::encrypt($key_hex, $gcm_iv, $odd_hex, 'GCM', $aad_hex);
$dec = Xhsm\Sm4::decrypt($key_hex, $gcm_iv, $enc, 'GCM', $aad_hex);
assert_eq(strtolower($dec), $odd_hex, 'SM4-GCM 变长明文往返');
echo "[OK] SM4-GCM 变长明文往返验证通过\n";

// GCM 无 AAD
$enc = Xhsm\Sm4::encrypt($key_hex, $gcm_iv, $plain_hex, 'GCM');
$dec = Xhsm\Sm4::decrypt($key_hex, $gcm_iv, $enc, 'GCM');
assert_eq(strtolower($dec), $plain_hex, 'SM4-GCM 无 AAD 往返');
echo "[OK] SM4-GCM 无 AAD 往返验证通过\n";

// GCM Tag 篡改应解密失败
$tampered = substr($gcm_enc, 0, -2) . 'ff';
try {
    Xhsm\Sm4::decrypt($key_hex, $gcm_iv, $tampered, 'GCM', $aad_hex);
    assert_true(false, 'SM4-GCM 篡改 Tag 应解密失败');
} catch (Xhsm\Exception $e) {
    echo "[OK] SM4-GCM Tag 篡改检测通过: " . $e->getMessage() . "\n";
}

// ---------- 异常处理 ----------
echo "--- 异常处理 ---\n";

// 密钥长度错误
try {
    Xhsm\Sm4::encrypt('short', '', $plain_hex, 'ECB');
    assert_true(false, '短密钥应抛出异常');
} catch (Xhsm\Exception $e) {
    echo "[OK] 短密钥异常捕获: " . $e->getMessage() . "\n";
}

// 不支持的模式
try {
    Xhsm\Sm4::encrypt($key_hex, $iv_hex, $plain_hex, 'INVALID');
    assert_true(false, '不支持的模式应抛出异常');
} catch (Xhsm\Exception $e) {
    echo "[OK] 不支持模式异常捕获: " . $e->getMessage() . "\n";
}

echo "===== SM4 测试全部通过 =====\n";
