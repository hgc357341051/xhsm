<?php
// Publish 命令 —— 发布 xhsm 配置文件
//
// 用法：php think xhsm:publish [--force]
//
// 将本包的 config/xhsm.php 复制到应用的 config/xhsm.php。
// 默认不覆盖已存在的文件，使用 --force 强制覆盖。

namespace Xhsm\Think\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class Publish extends Command
{
    /**
     * 配置命令
     */
    protected function configure(): void
    {
        $this->setName('xhsm:publish')
            ->setDescription('发布 xhsm 配置文件到应用的 config/xhsm.php')
            ->addOption('force', 'f', Option::VALUE_NONE, '强制覆盖已存在的配置文件');
    }

    /**
     * 执行命令
     */
    protected function execute(Input $input, Output $output): int
    {
        // 源文件：本包 config/xhsm.php（src/command/ 上溯两级到包根目录）
        $source = dirname(__DIR__, 2) . '/config/xhsm.php';
        // 目标：应用根目录下的 config/xhsm.php
        $target = $this->app->getRootPath() . 'config/xhsm.php';

        if (!is_file($source)) {
            $output->error("找不到源配置文件: {$source}");
            return 1;
        }

        if (is_file($target) && !$input->getOption('force')) {
            $output->warning("配置文件已存在: {$target}（使用 --force 强制覆盖）");
            return 0;
        }

        // 确保目标目录存在
        $targetDir = dirname($target);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if (copy($source, $target) === false) {
            $output->error("复制配置文件失败: {$source} -> {$target}");
            return 1;
        }

        $output->info("xhsm 配置已发布到: {$target}");
        return 0;
    }
}
