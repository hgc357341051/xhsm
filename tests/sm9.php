<?php
// SM9 算法测试脚本（Task 5 验证）
//
// 验证 Xhsm\Sm9 类的主密钥生成、用户私钥抽取、加解密、签名验签。
// 覆盖 encrypt→decrypt 与 sign→verify round-trip 自洽。

require __DIR__ . '/assert.php';

echo "===== SM9 测试开始 =====\n";

// ---------- 主密钥对生成 ----------
echo "--- 主密钥对生成 ---\n";
$kp = Xhsm\Sm9::generateMasterKeyPair();
assert_true(isset($kp['master_enc_private_key']), '应包含 master_enc_private_key');
assert_true(isset($kp['master_enc_public_key']), '应包含 master_enc_public_key');
assert_true(isset($kp['master_sig_private_key']), '应包含 master_sig_private_key');
assert_true(isset($kp['master_sig_public_key']), '应包含 master_sig_public_key');
assert_true(strlen($kp['master_enc_private_key']) > 0, '加密主私钥应非空');
assert_true(strlen($kp['master_enc_public_key']) > 0, '加密主公钥应非空');
assert_true(strlen($kp['master_sig_private_key']) > 0, '签名主私钥应非空');
assert_true(strlen($kp['master_sig_public_key']) > 0, '签名主公钥应非空');
echo "[OK] 主密钥对生成验证通过\n";
echo "  加密主私钥长度: " . strlen($kp['master_enc_private_key']) . " hex 字符\n";
echo "  加密主公钥长度: " . strlen($kp['master_enc_public_key']) . " hex 字符\n";
echo "  签名主私钥长度: " . strlen($kp['master_sig_private_key']) . " hex 字符\n";
echo "  签名主公钥长度: " . strlen($kp['master_sig_public_key']) . " hex 字符\n";

$enc_msk = $kp['master_enc_private_key'];
$enc_mpk = $kp['master_enc_public_key'];
$sig_msk = $kp['master_sig_private_key'];
$sig_mpk = $kp['master_sig_public_key'];

// ---------- 加密用户私钥抽取 ----------
echo "--- 加密用户私钥抽取 ---\n";
$id = 'Alice';
$enc_user_sk = Xhsm\Sm9::extractUserPrivateKey($enc_msk, $id, 'enc');
assert_true(strlen($enc_user_sk) > 0, '加密用户私钥应非空');
echo "[OK] 加密用户私钥抽取验证通过\n";
echo "  用户标识: {$id}\n";
echo "  加密用户私钥长度: " . strlen($enc_user_sk) . " hex 字符\n";

// 默认类型应为 enc
$enc_user_sk_default = Xhsm\Sm9::extractUserPrivateKey($enc_msk, $id);
assert_eq($enc_user_sk, $enc_user_sk_default, '默认 key_type 应为 enc');
echo "[OK] 默认 key_type=enc 验证通过\n";

// ---------- 加解密 round-trip ----------
echo "--- 加解密 round-trip ---\n";
$plaintext = 'Hello SM9!';
$hex_data = bin2hex($plaintext);
$ciphertext = Xhsm\Sm9::encrypt($enc_mpk, $id, $hex_data);
assert_true(strlen($ciphertext) > 0, 'SM9 密文应非空');
// SM9 密文 = C1(64字节) || C3(32字节) || C2(明文长度) = 96 + 明文长度
$expected_ct_len = (96 + strlen($plaintext)) * 2;
assert_eq(strlen($ciphertext), $expected_ct_len, 'SM9 密文长度应为 96+明文长度（字节）的 hex');
echo "[OK] SM9 加密验证通过\n";
echo "  明文: {$plaintext}\n";
echo "  密文长度: " . strlen($ciphertext) . " hex 字符\n";

$decrypted = Xhsm\Sm9::decrypt($enc_user_sk, $id, $ciphertext);
assert_eq($decrypted, $plaintext, 'SM9 加解密往返');
echo "[OK] SM9 加解密往返验证通过\n";
echo "  解密结果: {$decrypted}\n";

// ---------- 加解密（不同标识）----------
echo "--- 加解密（不同标识）---\n";
$id2 = 'Bob';
$enc_user_sk2 = Xhsm\Sm9::extractUserPrivateKey($enc_msk, $id2, 'enc');
$ct2 = Xhsm\Sm9::encrypt($enc_mpk, $id2, $hex_data);
$pt2 = Xhsm\Sm9::decrypt($enc_user_sk2, $id2, $ct2);
assert_eq($pt2, $plaintext, 'SM9 不同标识加解密往返');
echo "[OK] SM9 不同标识加解密往返验证通过\n";

// 用 Alice 的私钥解密 Bob 的密文应失败
try {
    Xhsm\Sm9::decrypt($enc_user_sk, $id2, $ct2);
    assert_true(false, '用错误标识的私钥解密应失败');
} catch (Xhsm\Exception $e) {
    echo "[OK] 错误标识解密失败（符合预期）: " . $e->getMessage() . "\n";
}

// ---------- 加解密（中文标识/中文明文）----------
echo "--- 加解密（中文标识/中文明文）---\n";
$id_cn = '张三';
$plaintext_cn = '国密SM9标识加密测试';
$enc_user_sk_cn = Xhsm\Sm9::extractUserPrivateKey($enc_msk, $id_cn, 'enc');
$ct_cn = Xhsm\Sm9::encrypt($enc_mpk, $id_cn, bin2hex($plaintext_cn));
$pt_cn = Xhsm\Sm9::decrypt($enc_user_sk_cn, $id_cn, $ct_cn);
assert_eq($pt_cn, $plaintext_cn, 'SM9 中文标识/中文明文加解密往返');
echo "[OK] SM9 中文标识/中文明文加解密往返验证通过\n";

// ---------- 签名用户私钥抽取（捆绑格式）----------
echo "--- 签名用户私钥抽取（捆绑格式）---\n";
$sig_user_sk = Xhsm\Sm9::extractUserPrivateKey($sig_msk, $id, 'sig');
assert_true(strpos($sig_user_sk, ':') !== false, '签名用户私钥应为捆绑格式（含冒号分隔符）');
$parts = explode(':', $sig_user_sk, 2);
assert_eq(count($parts), 2, '签名用户私钥应含 2 个 hex 段');
assert_true(strlen($parts[0]) > 0, '用户签名私钥段应非空');
assert_true(strlen($parts[1]) > 0, '主签名公钥段应非空');
echo "[OK] 签名用户私钥抽取（捆绑格式）验证通过\n";
echo "  捆绑格式: hex(uspk):hex(mspk)\n";

// ---------- 签名验签 round-trip（捆绑格式）----------
echo "--- 签名验签 round-trip（捆绑格式）---\n";
$msg = 'Message to sign with SM9';
$hex_msg = bin2hex($msg);
$signature = Xhsm\Sm9::sign($sig_user_sk, $id, $hex_msg);
assert_true(strlen($signature) > 0, 'SM9 签名应非空');
// SM9 签名 = h(32字节) || s(65字节) = 97 字节 = 194 hex 字符
assert_eq(strlen($signature), 194, 'SM9 签名应为 194 hex 字符（97 字节：h||s）');
echo "[OK] SM9 签名验证通过\n";
echo "  消息: {$msg}\n";
echo "  签名长度: " . strlen($signature) . " hex 字符\n";

$ok = Xhsm\Sm9::verify($sig_mpk, $id, $hex_msg, $signature);
assert_true($ok, 'SM9 签名验签应通过');
echo "[OK] SM9 签名验签往返验证通过\n";

// ---------- 签名验签（分离主公钥参数）----------
echo "--- 签名验签（分离主公钥参数）---\n";
$uspk_only = $parts[0]; // 纯用户签名私钥 hex
$signature2 = Xhsm\Sm9::sign($uspk_only, $id, $hex_msg, $sig_mpk);
assert_true(strlen($signature2) > 0, 'SM9 签名（分离参数）应非空');
$ok2 = Xhsm\Sm9::verify($sig_mpk, $id, $hex_msg, $signature2);
assert_true($ok2, 'SM9 签名验签（分离参数）应通过');
echo "[OK] SM9 签名验签（分离主公钥参数）验证通过\n";

// ---------- 签名验签（不同标识）----------
echo "--- 签名验签（不同标识）---\n";
$sig_user_sk2 = Xhsm\Sm9::extractUserPrivateKey($sig_msk, $id2, 'sig');
$signature3 = Xhsm\Sm9::sign($sig_user_sk2, $id2, $hex_msg);
$ok3 = Xhsm\Sm9::verify($sig_mpk, $id2, $hex_msg, $signature3);
assert_true($ok3, 'SM9 不同标识签名验签应通过');
echo "[OK] SM9 不同标识签名验签验证通过\n";

// ---------- 验签失败场景 ----------
echo "--- 验签失败场景 ---\n";

// 篡改数据
$ok = Xhsm\Sm9::verify($sig_mpk, $id, bin2hex('tampered message'), $signature);
assert_true(!$ok, 'SM9 篡改数据后验签应失败');
echo "[OK] SM9 篡改数据验签失败验证通过\n";

// 篡改签名 h 分量（前32字节），确保验签失败
// 注意：篡改 s 分量的 y 坐标末字节可能不改变压缩点表示（压缩仅保留 x+1bit 奇偶性），
// 故篡改 h 分量以可靠触发验签失败
$first_byte = hexdec(substr($signature, 0, 2));
$tampered_h = str_pad(dechex($first_byte ^ 0x01), 2, '0', STR_PAD_LEFT);
$tampered_sig = $tampered_h . substr($signature, 2);
$ok = Xhsm\Sm9::verify($sig_mpk, $id, $hex_msg, $tampered_sig);
assert_true(!$ok, 'SM9 篡改签名后验签应失败');
echo "[OK] SM9 篡改签名验签失败验证通过\n";

// 错误标识验签
$ok = Xhsm\Sm9::verify($sig_mpk, 'WrongID', $hex_msg, $signature);
assert_true(!$ok, 'SM9 错误标识验签应失败');
echo "[OK] SM9 错误标识验签失败验证通过\n";

// ---------- 异常处理 ----------
echo "--- 异常处理 ---\n";

// 非法 hex 数据
try {
    Xhsm\Sm9::encrypt($enc_mpk, $id, 'zz_invalid_hex');
    assert_true(false, '非法 hex 应抛出异常');
} catch (Xhsm\Exception $e) {
    echo "[OK] 非法 hex 异常捕获: " . $e->getMessage() . "\n";
}

// 非法密钥类型
try {
    Xhsm\Sm9::extractUserPrivateKey($enc_msk, $id, 'invalid_type');
    assert_true(false, '不支持的密钥类型应抛出异常');
} catch (Xhsm\Exception $e) {
    echo "[OK] 不支持密钥类型异常捕获: " . $e->getMessage() . "\n";
}

// 非法密钥
try {
    Xhsm\Sm9::encrypt('aabbccdd', $id, $hex_data);
    assert_true(false, '非法主公钥应抛出异常');
} catch (Xhsm\Exception $e) {
    echo "[OK] 非法主公钥异常捕获: " . $e->getMessage() . "\n";
}

// 签名私钥格式错误（无冒号且无第四参数）
try {
    Xhsm\Sm9::sign('aabbccdd', $id, $hex_msg);
    assert_true(false, '签名私钥格式错误应抛出异常');
} catch (Xhsm\Exception $e) {
    echo "[OK] 签名私钥格式错误异常捕获: " . $e->getMessage() . "\n";
}

// 非法签名长度
try {
    Xhsm\Sm9::verify($sig_mpk, $id, $hex_msg, 'aabb');
    assert_true(false, '非法签名长度应抛出异常');
} catch (Xhsm\Exception $e) {
    echo "[OK] 非法签名长度异常捕获: " . $e->getMessage() . "\n";
}

echo "===== SM9 测试全部通过 =====\n";
