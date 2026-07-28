<?php
// ThinkPHP 8 适配层功能测试（Task 10 验证）
//
// 本测试不依赖 ThinkPHP 框架，直接实例化 Wrapper 与 Manager（它们已解耦 think\*），
// 验证原始字符串↔hex 转换语义正确、各算法 round-trip 通过、Manager 工厂方法返回正确类型。
//
// 运行：php -d extension=/workspace/target/release/libxhsm.so tests/thinkphp_adapter.php

require __DIR__ . '/assert.php';

// 简易 PSR-4 自动加载：Xhsm\Think\ => php/src/
spl_autoload_register(function (string $class): void {
    $prefix = 'Xhsm\\Think\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/../php/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

echo "===== ThinkPHP 8 适配层功能测试开始 =====\n";

// ---------- 扩展加载检查 ----------
echo "--- 扩展加载检查 ---\n";
assert_true(extension_loaded('xhsm'), 'xhsm 扩展应已加载');
assert_true(class_exists(\Xhsm\Sm2::class), 'Xhsm\Sm2 类应存在');
echo "[OK] xhsm 扩展已加载\n";

// ---------- Manager 基础 ----------
echo "--- Manager 基础 ---\n";
$manager = new \Xhsm\Think\Manager();
assert_eq($manager->getDefaultSignatureVersion(), 's2', '默认签名版本应为 s2');
assert_eq($manager->getDefaultScenario(), 'finance', '默认场景应为 finance');
assert_eq($manager->config('sm2.mode'), 'C1C3C2', 'sm2.mode 配置应为 C1C3C2');

// 自定义配置注入
$manager2 = new \Xhsm\Think\Manager(['default_signature_version' => 's3', 'default_scenario' => 'payment']);
assert_eq($manager2->getDefaultSignatureVersion(), 's3', '自定义配置默认签名版本应为 s3');
assert_eq($manager2->getDefaultScenario(), 'payment', '自定义配置默认场景应为 payment');
echo "[OK] Manager 配置注入验证通过\n";

// ---------- Wrapper\Sm2 ----------
echo "--- Wrapper\\Sm2 ---\n";
$sm2 = new \Xhsm\Think\Wrapper\Sm2();

// 密钥对生成
$kp = $sm2->generateKeyPair();
assert_true(is_array($kp), 'generateKeyPair 应返回数组');
assert_true(isset($kp['private_key']), '密钥对应含 private_key');
assert_true(isset($kp['public_key']), '密钥应对含 public_key');
assert_eq(strlen($kp['private_key']), 64, '私钥应为 64 hex 字符');
assert_eq(strlen($kp['public_key']), 130, '公钥应为 130 hex 字符');
$priv = $kp['private_key'];
$pub = $kp['public_key'];
echo "[OK] Sm2 generateKeyPair 验证通过\n";

// 签名验签（原始字符串 'hello'）
$sig = $sm2->sign($priv, 'hello');
assert_true(strlen($sig) > 0, 'Sm2 sign 应非空');
assert_eq(substr($sig, 0, 2), '30', 'Sm2 默认 DER 签名应以 30 开头');
$ok = $sm2->verify($pub, 'hello', $sig);
assert_true($ok, 'Sm2 verify 应通过（原始字符串语义）');
echo "[OK] Sm2 sign/verify round-trip 通过（原始字符串 'hello'）\n";

// 篡改数据验签失败
$ok = $sm2->verify($pub, 'world', $sig);
assert_true(!$ok, 'Sm2 篡改数据验签应失败');
echo "[OK] Sm2 篡改数据验签失败验证通过\n";

// RAW 格式签名
$sigRaw = $sm2->sign($priv, 'hello', 'RAW');
assert_eq(strlen($sigRaw), 128, 'Sm2 RAW 签名应为 128 hex 字符');
$ok = $sm2->verify($pub, 'hello', $sigRaw, 'RAW');
assert_true($ok, 'Sm2 RAW 验签应通过');
echo "[OK] Sm2 RAW 格式签名验签通过\n";

// 加解密 round-trip（原始字符串 'hello'）
$ct = $sm2->encrypt($pub, 'hello');
assert_true(strlen($ct) > 0, 'Sm2 encrypt 密文应非空');
$pt = $sm2->decrypt($priv, $ct);
assert_eq($pt, 'hello', 'Sm2 decrypt 应还原原始字符串 hello');
echo "[OK] Sm2 encrypt/decrypt round-trip 通过（还原 hello）\n";

// 中文原始字符串加解密
$ctCn = $sm2->encrypt($pub, '你好国密');
$ptCn = $sm2->decrypt($priv, $ctCn);
assert_eq($ptCn, '你好国密', 'Sm2 中文字符串加解密应还原');
echo "[OK] Sm2 中文字符串加解密 round-trip 通过\n";

// ---------- Wrapper\Sm3 ----------
echo "--- Wrapper\\Sm3 ---\n";
$sm3 = new \Xhsm\Think\Wrapper\Sm3();
// 已知向量：SM3("abc") = 66c7f0f4...
$expected = '66c7f0f462eeedd9d1f2d46bdc10e4e24167c4875cf2f7a2297da02b8f4ba8e0';
$h = $sm3->hash('abc');
assert_eq(strlen($h), 64, 'Sm3 hash 长度应为 64 hex 字符');
assert_eq(strtolower($h), $expected, 'Sm3 hash("abc") 应匹配标准向量');
echo "[OK] Sm3 hash 已知向量验证通过: {$h}\n";

// HMAC round-trip
$hmac = $sm3->hmac('secret-key', 'data');
assert_eq(strlen($hmac), 64, 'Sm3 hmac 长度应为 64 hex 字符');
$hmac2 = $sm3->hmac('secret-key', 'data');
assert_eq($hmac, $hmac2, '相同输入 Sm3 hmac 应一致');
$hmacDiff = $sm3->hmac('other-key', 'data');
assert_true($hmac !== $hmacDiff, '不同密钥 Sm3 hmac 应不同');
echo "[OK] Sm3 hmac 一致性与密钥敏感性验证通过\n";

// ---------- Wrapper\Sm4 ----------
echo "--- Wrapper\\Sm4 ---\n";
$sm4 = new \Xhsm\Think\Wrapper\Sm4();
// 密钥/IV 保持 hex（与扩展层一致），明文为原始字符串（Wrapper 内部 bin2hex）
$keyHex = '0123456789abcdeffedcba9876543210'; // 32 hex 字符 = 16 字节
$ivHex = '000102030405060708090a0b0c0d0e0f';  // 32 hex 字符 = 16 字节

// CBC round-trip
$plain = 'Hello SM4 Wrapper!';
$ct4 = $sm4->encrypt($keyHex, $ivHex, $plain, 'CBC');
assert_true(strlen($ct4) > 0, 'Sm4 CBC 密文应非空');
$pt4 = $sm4->decrypt($keyHex, $ivHex, $ct4, 'CBC');
assert_eq($pt4, $plain, 'Sm4 CBC 应还原原始字符串');
echo "[OK] Sm4 CBC round-trip 通过（原始字符串明文）\n";

// ECB round-trip
$ct4Ecb = $sm4->encrypt($keyHex, '', $plain, 'ECB');
$pt4Ecb = $sm4->decrypt($keyHex, '', $ct4Ecb, 'ECB');
assert_eq($pt4Ecb, $plain, 'Sm4 ECB 应还原原始字符串');
echo "[OK] Sm4 ECB round-trip 通过\n";

// GCM round-trip（带 AAD 原始字符串）
$aadRaw = 'associated-data';
$ct4Gcm = $sm4->encrypt($keyHex, '000102030405060708090a0b', $plain, 'GCM', $aadRaw);
$pt4Gcm = $sm4->decrypt($keyHex, '000102030405060708090a0b', $ct4Gcm, 'GCM', $aadRaw);
assert_eq($pt4Gcm, $plain, 'Sm4 GCM 应还原原始字符串');
echo "[OK] Sm4 GCM round-trip 通过（带 AAD）\n";

// ---------- Wrapper\Sm9 ----------
echo "--- Wrapper\\Sm9 ---\n";
$sm9 = new \Xhsm\Think\Wrapper\Sm9();
$mkp = $sm9->generateMasterKeyPair();
assert_true(isset($mkp['master_enc_private_key']), '应含 master_enc_private_key');
assert_true(isset($mkp['master_enc_public_key']), '应含 master_enc_public_key');
$encMsk = $mkp['master_enc_private_key'];
$encMpk = $mkp['master_enc_public_key'];
$sigMsk = $mkp['master_sig_private_key'];
$sigMpk = $mkp['master_sig_public_key'];
echo "[OK] Sm9 generateMasterKeyPair 验证通过\n";

// 标识加解密 round-trip（原始字符串明文）
$id = 'Alice';
$encUsk = $sm9->extractUserPrivateKey($encMsk, $id, 'enc');
$ct9 = $sm9->encrypt($encMpk, $id, 'Hello SM9!');
assert_true(strlen($ct9) > 0, 'Sm9 密文应非空');
$pt9 = $sm9->decrypt($encUsk, $id, $ct9);
assert_eq($pt9, 'Hello SM9!', 'Sm9 加解密应还原原始字符串');
echo "[OK] Sm9 标识加解密 round-trip 通过（还原原始字符串）\n";

// 标识签名验签 round-trip（原始字符串数据）
$sigUsk = $sm9->extractUserPrivateKey($sigMsk, $id, 'sig');
$sig9 = $sm9->sign($sigUsk, $id, 'message-to-sign');
assert_true(strlen($sig9) > 0, 'Sm9 签名应非空');
$ok = $sm9->verify($sigMpk, $id, 'message-to-sign', $sig9);
assert_true($ok, 'Sm9 验签应通过');
echo "[OK] Sm9 标识签名验签 round-trip 通过\n";

// ---------- Wrapper\Signature ----------
echo "--- Wrapper\\Signature ---\n";
$sigWrapper = new \Xhsm\Think\Wrapper\Signature();
// 版本列表
$vs = $sigWrapper->versions();
assert_true(in_array('s2', $vs), '版本列表应含 s2');
assert_true(in_array('s3', $vs), '版本列表应含 s3');
assert_true(in_array('s4', $vs), '版本列表应含 s4');
echo "[OK] Signature 版本列表: " . implode(',', $vs) . "\n";

// s2 签名验签 round-trip（原始字符串数据）
$sigS2 = $sigWrapper->sign('s2', $priv, 'hello');
assert_true(strlen($sigS2) > 0, 's2 签名应非空');
assert_eq(substr($sigS2, 0, 2), '30', 's2 DER 签名应以 30 开头');
$ok = $sigWrapper->verify('s2', $pub, 'hello', $sigS2);
assert_true($ok, 's2 验签应通过（原始字符串）');
echo "[OK] Signature s2 round-trip 通过\n";

// 篡改数据验签失败
$ok = $sigWrapper->verify('s2', $pub, 'tampered', $sigS2);
assert_true(!$ok, 's2 篡改数据验签应失败');
echo "[OK] Signature s2 篡改数据验签失败验证通过\n";

// 注册自定义版本
$sigWrapper->register('sx', ['algorithm' => 'SM2', 'encoding' => 'RAW', 'output' => 'hex']);
$vs = $sigWrapper->versions();
assert_true(in_array('sx', $vs), '注册后版本列表应含 sx');
$sigSx = $sigWrapper->sign('sx', $priv, 'hello');
$ok = $sigWrapper->verify('sx', $pub, 'hello', $sigSx);
assert_true($ok, 'sx 自定义版本验签应通过');
echo "[OK] Signature 自定义版本注册与 round-trip 通过\n";

// ---------- Wrapper\Scenario ----------
echo "--- Wrapper\\Scenario ---\n";
$scn = new \Xhsm\Think\Wrapper\Scenario('finance');
assert_eq($scn->name(), 'finance', 'Scenario 名称应为 finance');
$sigFin = $scn->sign($priv, 'scenario-data');
assert_true(strlen($sigFin) > 0, 'Finance 签名应非空');
$ok = $scn->verify($pub, 'scenario-data', $sigFin);
assert_true($ok, 'Finance 验签应通过');
$ctFin = $scn->encrypt($pub, 'plain-data');
$ptFin = $scn->decrypt($priv, $ctFin);
assert_eq($ptFin, 'plain-data', 'Finance 加解密应还原');
$hFin = $scn->hash('abc');
assert_eq(strtolower($hFin), $expected, 'Finance hash 应等于 SM3("abc")');
echo "[OK] Scenario(finance) sign/verify/encrypt/decrypt/hash 全通过\n";

// 未知场景异常
try {
    new \Xhsm\Think\Wrapper\Scenario('unknown');
    assert_true(false, '未知场景应抛出异常');
} catch (\InvalidArgumentException $e) {
    echo "[OK] 未知场景异常捕获: " . $e->getMessage() . "\n";
}

// ---------- Manager 工厂方法返回类型 ----------
echo "--- Manager 工厂方法返回类型 ---\n";
assert_true($manager->sm2() instanceof \Xhsm\Think\Wrapper\Sm2, 'sm2() 应返回 Wrapper\\Sm2');
assert_true($manager->sm3() instanceof \Xhsm\Think\Wrapper\Sm3, 'sm3() 应返回 Wrapper\\Sm3');
assert_true($manager->sm4() instanceof \Xhsm\Think\Wrapper\Sm4, 'sm4() 应返回 Wrapper\\Sm4');
assert_true($manager->sm9() instanceof \Xhsm\Think\Wrapper\Sm9, 'sm9() 应返回 Wrapper\\Sm9');
assert_true($manager->signature() instanceof \Xhsm\Think\Wrapper\Signature, 'signature() 应返回 Wrapper\\Signature');
assert_true($manager->scenario() instanceof \Xhsm\Think\Wrapper\Scenario, 'scenario() 应返回 Wrapper\\Scenario');
assert_eq($manager->scenario()->name(), 'finance', '默认 scenario 应为 finance');
echo "[OK] Manager 工厂方法返回类型全部正确\n";

// Manager 缓存单例验证（同一方法多次调用返回同一实例）
assert_true($manager->sm2() === $manager->sm2(), 'sm2() 应返回缓存单例');
assert_true($manager->signature() === $manager->signature(), 'signature() 应返回缓存单例');
echo "[OK] Manager 单例缓存验证通过\n";

// 通过 Manager 链式调用（模拟 Xhsm::sm2()->sign(...)）
$sigViaManager = $manager->sm2()->sign($priv, 'via-manager');
$ok = $manager->sm2()->verify($pub, 'via-manager', $sigViaManager);
assert_true($ok, 'Manager 链式 sign/verify 应通过');
echo "[OK] Manager 链式调用 sign/verify 通过\n";

echo "===== ThinkPHP 8 适配层功能测试全部通过 =====\n";
