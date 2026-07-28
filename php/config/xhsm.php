<?php
// xhsm 默认配置文件
//
// 由 `php think xhsm:publish` 发布到应用的 config/xhsm.php。
// 可通过 env() 覆盖敏感字段（如密钥路径）。

return [
    // 默认签名版本（内置 s2/s3/s4，可注册自定义版本）
    'default_signature_version' => 's2',

    // 默认业务场景（finance/payment/government/miniprogram）
    'default_scenario' => 'finance',

    // SM2 默认密文顺序：C1C3C2（GB/T 32918 标准）或 C1C2C3
    'sm2' => [
        'mode' => 'C1C3C2',
    ],

    // SM4 默认工作模式：ECB / CBC / CTR / GCM
    'sm4' => [
        'mode' => 'CBC',
    ],

    // 密钥路径（可选，用于从文件加载密钥）
    'keys' => [
        // 'private' => env('XHSM_PRIVATE_KEY'),
        // 'public'  => env('XHSM_PUBLIC_KEY'),
    ],
];
