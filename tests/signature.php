<?php
// 可扩展签名版本体系测试脚本（Task 6 验证）
//
// 验证 Xhsm\Signature 类的内置版本 s2/s3/s4 签名验签往返、
// 自定义版本注册、版本列表、版本描述、异常处理、不同版本输出差异。

require __DIR__ . '/assert.php';

echo "===== Signature 测试开始 =====\n";

// ---------- 密钥对生成（复用 Sm2）----------
$kp = Xhsm\Sm2::generateKeyPair();
$sk = $kp['private_key'];
$pk = $kp['public_key'];
echo "私钥: " . $sk . "\n";
echo "公钥: " . $pk . "\n";

// 待签名的原始数据（普通字符串，非 hex）
$data = 'Hello Xhsm Signature!';

// ---------- 版本列表（初始应含 s2/s3/s4）----------
echo "--- 版本列表（初始）---\n";
$vs = Xhsm\Signature::versions();
assert_true(in_array('s2', $vs), '初始版本列表应含 s2');
assert_true(in_array('s3', $vs), '初始版本列表应含 s3');
assert_true(in_array('s4', $vs), '初始版本列表应含 s4');
echo "[OK] 初始版本列表: " . implode(',', $vs) . "\n";

// ---------- describe 内置版本 ----------
echo "--- describe(s2) ---\n";
$desc = Xhsm\Signature::describe('s2');
assert_eq($desc['algorithm'], 'SM2', 's2 algorithm 应为 SM2');
assert_eq($desc['encoding'], 'DER', 's2 encoding 应为 DER');
assert_eq($desc['output'], 'hex', 's2 output 应为 hex');
assert_eq($desc['user_id'], '1234567812345678', 's2 user_id 应为默认值');
assert_eq($desc['description'], '经典 ASN.1 DER + hex 编码', 's2 description 应正确');
echo "[OK] describe(s2): " . json_encode($desc, JSON_UNESCAPED_UNICODE) . "\n";

// ---------- s2 签名验签往返 ----------
echo "--- s2 round-trip (DER + hex) ---\n";
$sig_s2 = Xhsm\Signature::sign('s2', $sk, $data);
assert_true(strlen($sig_s2) > 0, 's2 签名应非空');
// s2 output=hex，DER 签名以 0x30 SEQUENCE 开头 → hex 以 "30" 开头
assert_eq(substr($sig_s2, 0, 2), '30', 's2 DER 签名应以 0x30 开头');
$ok = Xhsm\Signature::verify('s2', $pk, $data, $sig_s2);
assert_true($ok, 's2 验签应通过');
echo "[OK] s2 round-trip 通过，签名: " . $sig_s2 . "\n";

// ---------- s3 签名验签往返 ----------
echo "--- s3 round-trip (RAW + hex) ---\n";
$sig_s3 = Xhsm\Signature::sign('s3', $sk, $data);
assert_true(strlen($sig_s3) > 0, 's3 签名应非空');
// s3 output=hex，RAW 签名 = r(32) || s(32) = 64 字节 = 128 hex 字符
assert_eq(strlen($sig_s3), 128, 's3 RAW 签名应为 128 hex 字符（64 字节）');
$ok = Xhsm\Signature::verify('s3', $pk, $data, $sig_s3);
assert_true($ok, 's3 验签应通过');
echo "[OK] s3 round-trip 通过，签名: " . $sig_s3 . "\n";

// ---------- s4 签名验签往返 ----------
echo "--- s4 round-trip (DER + base64) ---\n";
$sig_s4 = Xhsm\Signature::sign('s4', $sk, $data);
assert_true(strlen($sig_s4) > 0, 's4 签名应非空');
// s4 output=base64，base64 解码后应为 DER 编码，首字节 0x30
$decoded_s4 = base64_decode($sig_s4);
assert_eq(ord($decoded_s4[0]), 0x30, 's4 base64 解码后应为 DER（0x30 开头）');
$ok = Xhsm\Signature::verify('s4', $pk, $data, $sig_s4);
assert_true($ok, 's4 验签应通过');
echo "[OK] s4 round-trip 通过，签名: " . $sig_s4 . "\n";

// ---------- 不同版本输出确实不同 ----------
echo "--- 版本输出差异验证 ---\n";
// s2 与 s3 都是 hex，但编码不同（DER vs RAW），输出应不同
assert_true($sig_s2 !== $sig_s3, 's2(hex DER) 与 s3(hex RAW) 输出应不同');
// s2 是 hex，s4 是 base64，编码不同，输出应不同
assert_true($sig_s2 !== $sig_s4, 's2(hex) 与 s4(base64) 输出应不同');
assert_true($sig_s3 !== $sig_s4, 's3(hex) 与 s4(base64) 输出应不同');
echo "[OK] 三个版本输出两两不同\n";

// s2(hex DER) 与 s4(base64 DER) 底层都是 DER 编码，仅外层编码不同。
// 由于 SM2 签名使用随机 nonce（非确定性），两次签名结果不同，
// 故通过交叉验证证明编码一致性：s2 签名转 base64 后可用 s4 验签通过。
$s2_as_b64 = base64_encode(hex2bin($sig_s2));
$ok_cross = Xhsm\Signature::verify('s4', $pk, $data, $s2_as_b64);
assert_true($ok_cross, 's2(hex DER) 签名转 base64 后应可被 s4(base64 DER) 验签通过');
// 反向：s4 签名转 hex 后可用 s2 验签通过
$s4_as_hex = bin2hex(base64_decode($sig_s4));
$ok_cross2 = Xhsm\Signature::verify('s2', $pk, $data, $s4_as_hex);
assert_true($ok_cross2, 's4(base64 DER) 签名转 hex 后应可被 s2(hex DER) 验签通过');
echo "[OK] s2 与 s4 交叉验签通过（同 DER 不同外层编码）\n";

// ---------- 篡改数据验签失败 ----------
echo "--- 篡改数据验签失败 ---\n";
$ok = Xhsm\Signature::verify('s2', $pk, 'tampered data', $sig_s2);
assert_true(!$ok, 's2 篡改数据后验签应失败');
echo "[OK] 篡改数据验签失败验证通过\n";

// ---------- 自定义版本注册 s5 ----------
echo "--- 注册自定义版本 s5 (RAW + base64) ---\n";
Xhsm\Signature::register('s5', [
    'algorithm' => 'SM2',
    'encoding' => 'RAW',
    'output' => 'base64',
    'description' => '自定义版本 r||s + base64',
]);
$vs = Xhsm\Signature::versions();
assert_true(in_array('s5', $vs), '注册后版本列表应含 s5');
echo "[OK] s5 注册成功，版本列表: " . implode(',', $vs) . "\n";

// s5 签名验签往返
$sig_s5 = Xhsm\Signature::sign('s5', $sk, $data);
assert_true(strlen($sig_s5) > 0, 's5 签名应非空');
// s5 output=base64，RAW = 64 字节，base64 编码后应为 88 字符（含填充）或 86 字符
$decoded_s5 = base64_decode($sig_s5);
assert_eq(strlen($decoded_s5), 64, 's5 base64 解码后应为 64 字节（RAW r||s）');
$ok = Xhsm\Signature::verify('s5', $pk, $data, $sig_s5);
assert_true($ok, 's5 验签应通过');
echo "[OK] s5 round-trip 通过，签名: " . $sig_s5 . "\n";

// describe s5
$desc5 = Xhsm\Signature::describe('s5');
assert_eq($desc5['encoding'], 'RAW', 's5 encoding 应为 RAW');
assert_eq($desc5['output'], 'base64', 's5 output 应为 base64');
assert_eq($desc5['description'], '自定义版本 r||s + base64', 's5 description 应正确');
echo "[OK] describe(s5): " . json_encode($desc5, JSON_UNESCAPED_UNICODE) . "\n";

// ---------- 异常：重复注册已存在版本 ----------
echo "--- 异常：重复注册 s2 ---\n";
try {
    Xhsm\Signature::register('s2', ['algorithm' => 'SM2', 'encoding' => 'DER', 'output' => 'hex']);
    assert_true(false, '重复注册 s2 应抛出异常');
} catch (Xhsm\Exception $e) {
    echo "[OK] 重复注册异常捕获: " . $e->getMessage() . "\n";
}

// ---------- 异常：非法 algorithm ----------
echo "--- 异常：非法 algorithm ---\n";
try {
    Xhsm\Signature::register('s6', ['algorithm' => 'RSA', 'encoding' => 'DER', 'output' => 'hex']);
    assert_true(false, '非法 algorithm 应抛出异常');
} catch (Xhsm\Exception $e) {
    echo "[OK] 非法 algorithm 异常捕获: " . $e->getMessage() . "\n";
}

// ---------- 异常：非法 encoding ----------
echo "--- 异常：非法 encoding ---\n";
try {
    Xhsm\Signature::register('s7', ['algorithm' => 'SM2', 'encoding' => 'P1363', 'output' => 'hex']);
    assert_true(false, '非法 encoding 应抛出异常');
} catch (Xhsm\Exception $e) {
    echo "[OK] 非法 encoding 异常捕获: " . $e->getMessage() . "\n";
}

// ---------- 异常：非法 output ----------
echo "--- 异常：非法 output ---\n";
try {
    Xhsm\Signature::register('s8', ['algorithm' => 'SM2', 'encoding' => 'DER', 'output' => 'bin']);
    assert_true(false, '非法 output 应抛出异常');
} catch (Xhsm\Exception $e) {
    echo "[OK] 非法 output 异常捕获: " . $e->getMessage() . "\n";
}

// ---------- 异常：未知版本 ----------
echo "--- 异常：未知版本 ---\n";
try {
    Xhsm\Signature::sign('unknown_version', $sk, $data);
    assert_true(false, '未知版本签名应抛出异常');
} catch (Xhsm\Exception $e) {
    echo "[OK] 未知版本异常捕获: " . $e->getMessage() . "\n";
}

// ---------- 默认值注册（仅指定部分字段）----------
echo "--- 默认值注册（仅 algorithm）---\n";
Xhsm\Signature::register('s9', ['algorithm' => 'SM2']);
$desc9 = Xhsm\Signature::describe('s9');
assert_eq($desc9['encoding'], 'DER', 's9 缺省 encoding 应为 DER');
assert_eq($desc9['output'], 'hex', 's9 缺省 output 应为 hex');
assert_eq($desc9['user_id'], '1234567812345678', 's9 缺省 user_id 应为默认值');
$sig_s9 = Xhsm\Signature::sign('s9', $sk, $data);
$ok = Xhsm\Signature::verify('s9', $pk, $data, $sig_s9);
assert_true($ok, 's9 默认配置 round-trip 应通过');
echo "[OK] 默认值注册 s9 round-trip 通过\n";

// ---------- 最终版本列表 ----------
echo "--- 最终版本列表 ---\n";
$vs = Xhsm\Signature::versions();
assert_true(in_array('s2', $vs) && in_array('s3', $vs) && in_array('s4', $vs) && in_array('s5', $vs), '最终版本列表应含 s2/s3/s4/s5');
echo "[OK] 最终版本列表: " . implode(',', $vs) . "\n";

echo "===== Signature 测试全部通过 =====\n";
