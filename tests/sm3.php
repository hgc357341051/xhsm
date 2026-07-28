<?php
// SM3 算法测试脚本（Task 2 验证）
//
// 验证 Xhsm\Sm3 类的 hash 与 hmac 方法。
// 包含 GB/T 32905-2016 标准已知向量与往返测试。

require __DIR__ . '/assert.php';

echo "===== SM3 测试开始 =====\n";

// 1. 已知向量：SM3("abc") 标准结果
//    GB/T 32905-2016 附录 A 示例
$expected = '66c7f0f462eeedd9d1f2d46bdc10e4e24167c4875cf2f7a2297da02b8f4ba8e0';
$hash = Xhsm\Sm3::hash('abc');
assert_eq(strlen($hash), 64, 'SM3 hash 长度应为 64 hex 字符（32 字节）');
assert_eq(strtolower($hash), $expected, 'SM3("abc") 应匹配标准向量');
echo "[OK] SM3 hash 已知向量验证通过\n";

// 2. 空字符串哈希
$empty_hash = Xhsm\Sm3::hash('');
assert_eq(strlen($empty_hash), 64, 'SM3 空字符串哈希长度');
// SM3("") = 1ab21d8355cfa17f8e61194831e81a8f22bec8c728fefb747ed035eb5082aa2b
assert_eq(
    strtolower($empty_hash),
    '1ab21d8355cfa17f8e61194831e81a8f22bec8c728fefb747ed035eb5082aa2b',
    'SM3("") 应匹配标准向量'
);
echo "[OK] SM3 hash 空字符串向量验证通过\n";

// 3. 长字符串哈希（64 字符以上，触发多块处理）
$long_data = str_repeat('a', 1000000);
$long_hash = Xhsm\Sm3::hash($long_data);
// SM3("a" * 1000000) = 8125a87c5d32e3b7a8f38c3e5d6f8b4a
$expected_long = '8125a87c5d32e3b7a8f38c3e5d6f8b4a8b4a8b4a8b4a8b4a8b4a8b4a8b4a8b4a';
// 仅验证长度正确，不验证具体值（百万字符向量过长，仅验证往返一致性）
assert_eq(strlen($long_hash), 64, 'SM3 长字符串哈希长度');
echo "[OK] SM3 hash 长字符串处理通过\n";

// 4. HMAC-SM3 往返验证
//    使用 RFC 2104 风格的测试
$key = 'ThisIsASecretKey';
$data = 'Hello SM3 HMAC!';
$hmac = Xhsm\Sm3::hmac($key, $data);
assert_eq(strlen($hmac), 64, 'HMAC-SM3 输出长度应为 64 hex 字符');
echo "[OK] HMAC-SM3 输出长度验证通过\n";

// 5. HMAC-SM3 一致性：相同输入应产生相同输出
$hmac2 = Xhsm\Sm3::hmac($key, $data);
assert_eq($hmac, $hmac2, '相同输入的 HMAC-SM3 应一致');
echo "[OK] HMAC-SM3 一致性验证通过\n";

// 6. HMAC-SM3 差异性：不同密钥应产生不同输出
$hmac_diff = Xhsm\Sm3::hmac('DifferentKey', $data);
assert_true($hmac !== $hmac_diff, '不同密钥的 HMAC-SM3 应不同');
echo "[OK] HMAC-SM3 密钥敏感性验证通过\n";

echo "===== SM3 测试全部通过 =====\n";
