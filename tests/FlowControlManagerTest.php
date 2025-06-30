<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\FlowControl\FlowControlManager;

class FlowControlManagerTest extends TestCase
{
    public function testConstruction(): void
    {
        $manager = new FlowControlManager();
        $this->assertInstanceOf(FlowControlManager::class, $manager);
    }

    // 这里可以添加更多的测试方法
    // 但由于我们没有看到 FlowControlManager 的具体实现
    // 先创建一个基本的测试类结构
} 