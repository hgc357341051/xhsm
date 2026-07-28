<?php
// 异常处理专用测试脚本（Task 8 验证）
//
// 验证 Xhsm\Exception 类的错误码常量在 PHP 侧可访问，
// 并触发各类错误路径断言 getCode() 返回对应语义码、getMessage() 非空可读。
//
// 覆盖的错误码类别：
// - ERR_INVALID_FORMAT (1001)：hex 解码失败
// - ERR_INVALID_PARAM  (1003)：不支持的模式 / 未知版本 / 重复注册
// - ERR_UNSUPPORTED    (1006)：非法 algorithm
// - ERR_DECODE         (1004)：GCM Tag 校验失败 / DER 解析失败
// 同时验证 Xhsm\Exception 继承 \Exception。

require __DIR__ . '/assert.php';

echo "===== Exception 测试开始 =====\n";

// ---------- 1. 错误码常量在 PHP 侧可访问 ----------
echo "--- 错误码常量访问 ---\n";
assert_eq(Xhsm\Exception::ERR_INVALID_FORMAT, 1001, 'ERR_INVALID_FORMAT 应为 1001');
assert_eq(Xhsm\Exception::ERR_INVALID_KEY, 1002, 'ERR_INVALID_KEY 应为 1002');
assert_eq(Xhsm\Exception::ERR_INVALID_PARAM, 1003, 'ERR_INVALID_PARAM 应为 1003');
assert_eq(Xhsm\Exception::ERR_DECODE, 1004, 'ERR_DECODE 应为 1004');
assert_eq(Xhsm\Exception::ERR_INTERNAL, 1005, 'ERR_INTERNAL 应为 1005');
assert_eq(Xhsm\Exception::ERR_UNSUPPORTED, 1006, 'ERR_UNSUPPORTED 应为 1006');
echo "[OK] 六个错误码常量均可访问且数值正确\n";

// ---------- 2. Xhsm\Exception 继承 \Exception ----------
echo "--- 继承关系验证 ---\n";
assert_true(is_subclass_of('Xhsm\\Exception', 'Exception'), 'Xhsm\\Exception 应继承 \\Exception');
echo "[OK] Xhsm\\Exception 继承 \\Exception\n";

// 辅助：捕获 Xhsm\Exception 并断言 code 与 message
function assert_exception_code(callable $fn, int $expectedCode, string $caseName): void
{
    try {
        $fn();
        assert_true(false, "{$caseName} 应抛出异常");
    } catch (Xhsm\Exception $e) {
        assert_eq($e->getCode(), $expectedCode, "{$caseName} code 应为 {$expectedCode}");
        assert_true(strlen($e->getMessage()) > 0, "{$caseName} message 应非空");
        echo "[OK] {$caseName}: code={$e->getCode()}, msg=" . $e->getMessage() . "\n";
    }
}

// ---------- 3. ERR_INVALID_FORMAT: hex 解码失败 ----------
echo "--- ERR_INVALID_FORMAT: hex 解码失败 ---\n";
$sk = Xhsm\Sm2::generateKeyPair()['private_key'];

// Sm2::sign 传 'zz' 数据 → hex 解码失败
assert_exception_code(
    fn() => Xhsm\Sm2::sign($sk, 'zz', 'DER'),
    Xhsm\Exception::ERR_INVALID_FORMAT,
    'Sm2::sign 非法 hex 数据'
);

// Sm4::encrypt 传非法 hex 数据 → hex 解码失败
assert_exception_code(
    fn() => Xhsm\Sm4::encrypt('0123456789abcdeffedcba9876543210', '000102030405060708090a0b0c0d0e0f', 'zz', 'CBC'),
    Xhsm\Exception::ERR_INVALID_FORMAT,
    'Sm4::encrypt 非法 hex 数据'
);

// ---------- 4. ERR_INVALID_PARAM: 不支持的模式 ----------
echo "--- ERR_INVALID_PARAM: 不支持的模式 ---\n";
$pk = Xhsm\Sm2::generateKeyPair()['public_key'];

// Sm2::encrypt 传 mode='XYZ' → 不支持的模式
assert_exception_code(
    fn() => Xhsm\Sm2::encrypt($pk, 'aabb', 'XYZ'),
    Xhsm\Exception::ERR_INVALID_PARAM,
    'Sm2::encrypt 不支持的模式 XYZ'
);

// Sm4::encrypt 传 mode='INVALID' → 不支持的模式
assert_exception_code(
    fn() => Xhsm\Sm4::encrypt('0123456789abcdeffedcba9876543210', '000102030405060708090a0b0c0d0e0f', 'aabbcc', 'INVALID'),
    Xhsm\Exception::ERR_INVALID_PARAM,
    'Sm4::encrypt 不支持的模式 INVALID'
);

// Sm2::sign 传 format='P1363' → 不支持的签名格式
assert_exception_code(
    fn() => Xhsm\Sm2::sign($sk, 'aabb', 'P1363'),
    Xhsm\Exception::ERR_INVALID_PARAM,
    'Sm2::sign 不支持的签名格式 P1363'
);

// ---------- 5. ERR_INVALID_PARAM: 未知签名版本 ----------
echo "--- ERR_INVALID_PARAM: 未知签名版本 ---\n";
assert_exception_code(
    fn() => Xhsm\Signature::sign('unknown_version', $sk, 'data'),
    Xhsm\Exception::ERR_INVALID_PARAM,
    'Signature::sign 未知版本'
);

// ---------- 6. ERR_INVALID_PARAM: 重复注册版本 ----------
echo "--- ERR_INVALID_PARAM: 重复注册版本 ---\n";
assert_exception_code(
    fn() => Xhsm\Signature::register('s2', ['algorithm' => 'SM2', 'encoding' => 'DER', 'output' => 'hex']),
    Xhsm\Exception::ERR_INVALID_PARAM,
    'Signature::register 重复注册 s2'
);

// ---------- 7. ERR_UNSUPPORTED: 非法 algorithm ----------
echo "--- ERR_UNSUPPORTED: 非法 algorithm ---\n";
assert_exception_code(
    fn() => Xhsm\Signature::register('newver_err_algo', ['algorithm' => 'RSA', 'encoding' => 'DER', 'output' => 'hex']),
    Xhsm\Exception::ERR_UNSUPPORTED,
    'Signature::register 非法 algorithm RSA'
);

// ---------- 8. ERR_DECODE: GCM Tag 校验失败 ----------
echo "--- ERR_DECODE: GCM Tag 校验失败 ---\n";
$keyHex = '0123456789abcdeffedcba9876543210';
$gcmIv  = '000102030405060708090a0b';
$aadHex = 'deadbeef';
$plainHex = '0123456789abcdeffedcba9876543210';
$gcmEnc = Xhsm\Sm4::encrypt($keyHex, $gcmIv, $plainHex, 'GCM', $aadHex);
// 篡改最后一字节（Tag 末字节）→ GCM Tag 校验失败
$tamperedGcm = substr($gcmEnc, 0, -2) . 'ff';
assert_exception_code(
    fn() => Xhsm\Sm4::decrypt($keyHex, $gcmIv, $tamperedGcm, 'GCM', $aadHex),
    Xhsm\Exception::ERR_DECODE,
    'Sm4 GCM Tag 校验失败'
);

// ---------- 9. ERR_INVALID_PARAM: 非法 encoding/output（Signature 注册）----------
echo "--- ERR_INVALID_PARAM: 非法 encoding/output ---\n";
assert_exception_code(
    fn() => Xhsm\Signature::register('bad_enc', ['algorithm' => 'SM2', 'encoding' => 'P1363', 'output' => 'hex']),
    Xhsm\Exception::ERR_INVALID_PARAM,
    'Signature::register 非法 encoding P1363'
);
assert_exception_code(
    fn() => Xhsm\Signature::register('bad_out', ['algorithm' => 'SM2', 'encoding' => 'DER', 'output' => 'bin']),
    Xhsm\Exception::ERR_INVALID_PARAM,
    'Signature::register 非法 output bin'
);

// ---------- 10. ERR_INVALID_PARAM: SM9 不支持的密钥类型 ----------
echo "--- ERR_INVALID_PARAM: SM9 不支持的密钥类型 ---\n";
$kp9 = Xhsm\Sm9::generateMasterKeyPair();
$encMsk = $kp9['master_enc_private_key'];
assert_exception_code(
    fn() => Xhsm\Sm9::extractUserPrivateKey($encMsk, 'Alice', 'invalid_type'),
    Xhsm\Exception::ERR_INVALID_PARAM,
    'Sm9::extractUserPrivateKey 不支持的密钥类型'
);

// ---------- 11. ERR_INVALID_FORMAT: SM9 非法 hex 数据 ----------
echo "--- ERR_INVALID_FORMAT: SM9 非法 hex 数据 ---\n";
$encMpk = $kp9['master_enc_public_key'];
assert_exception_code(
    fn() => Xhsm\Sm9::encrypt($encMpk, 'Alice', 'zz_invalid_hex'),
    Xhsm\Exception::ERR_INVALID_FORMAT,
    'Sm9::encrypt 非法 hex 数据'
);

// ---------- 12. ERR_INTERNAL: SM9 非法主公钥（catch_unwind panic）----------
echo "--- ERR_INTERNAL: SM9 非法主公钥触发 panic ---\n";
// 用一个能 hex 解码但不是有效 PEM 的字符串作为主公钥，触发 sm9 crate panic
$badMpk = bin2hex('not-a-valid-pem-key');
assert_exception_code(
    fn() => Xhsm\Sm9::encrypt($badMpk, 'Alice', 'aabb'),
    Xhsm\Exception::ERR_INTERNAL,
    'Sm9::encrypt 非法主公钥触发内部错误'
);

// ---------- 13. 异常对象方法可用性验证 ----------
echo "--- 异常对象方法可用性 ---\n";
try {
    Xhsm\Sm2::sign($sk, 'zz', 'DER');
    assert_true(false, '应抛出异常');
} catch (Xhsm\Exception $e) {
    // 继承 \Exception 的方法可用：getCode / getMessage / getFile / getLine / getTrace
    assert_true(is_int($e->getCode()), 'getCode 应返回 int');
    assert_true(is_string($e->getMessage()), 'getMessage 应返回 string');
    assert_true(is_string($e->getFile()), 'getFile 应返回 string');
    assert_true(is_int($e->getLine()), 'getLine 应返回 int');
    assert_true(is_array($e->getTrace()), 'getTrace 应返回 array');
    echo "[OK] 异常对象 getCode/getMessage/getFile/getLine/getTrace 均可用\n";
}

echo "===== Exception 测试全部通过 =====\n";
