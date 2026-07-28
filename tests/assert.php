<?php
// 测试断言辅助函数
//
// 提供简单的断言函数，失败时输出错误信息并退出（exit 1）。

function assert_eq($actual, $expected, string $message): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, "\n[FAIL] {$message}\n");
        fwrite(STDERR, "  期望: " . var_export($expected, true) . "\n");
        fwrite(STDERR, "  实际: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assert_true($value, string $message): void
{
    if ($value !== true) {
        fwrite(STDERR, "\n[FAIL] {$message}\n");
        fwrite(STDERR, "  期望 true, 实际: " . var_export($value, true) . "\n");
        exit(1);
    }
}

function assert_false($value, string $message): void
{
    if ($value !== false) {
        fwrite(STDERR, "\n[FAIL] {$message}\n");
        fwrite(STDERR, "  期望 false, 实际: " . var_export($value, true) . "\n");
        exit(1);
    }
}
