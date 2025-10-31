# QUIC Flow Control

[![PHP Version Require](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://packagist.org/packages/tourze/quic-flow-control)
[![License](https://img.shields.io/github/license/tourze/quic-flow-control.svg)](https://github.com/tourze/quic-flow-control/blob/main/LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?branch=main)](https://github.com/tourze/php-monorepo/actions)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/php-monorepo)](https://codecov.io/gh/tourze/php-monorepo)

[English](README.md) | [中文](README.zh-CN.md)

QUIC协议流量控制实现，提供连接级和流级流量控制机制，完全符合RFC 9000规范。

## 目录

- [功能特性](#功能特性)
- [安装](#安装)
- [Dependencies](#dependencies)
- [Quick Start](#quick-start)
- [运行演示](#运行演示)
- [Advanced Usage](#advanced-usage)
- [测试](#测试)
- [RFC 9000 合规性](#rfc-9000-合规性)
- [许可证](#许可证)

## 功能特性

### 核心组件

- **FlowControlWindow** - 流量控制窗口管理
- **StreamFlowController** - 流级流量控制器
- **ConnectionFlowController** - 连接级流量控制器  
- **FlowControlManager** - 统一流量控制管理器

### 流控制帧支持

- **MAX_DATA** - 连接级窗口更新帧
- **MAX_STREAM_DATA** - 流级窗口更新帧
- **DATA_BLOCKED** - 连接级阻塞信号帧
- **STREAM_DATA_BLOCKED** - 流级阻塞信号帧

### 主要功能

- ✅ 连接级和流级双重流量控制
- ✅ 自动阻塞检测和信号生成
- ✅ 智能窗口更新机制
- ✅ 完整的统计和健康监控
- ✅ RFC 9000完全兼容

## 安装

```bash
composer require tourze/quic-flow-control
```

## Dependencies

本包依赖以下组件：

### 运行时依赖
- **tourze/quic-core** - QUIC 协议核心组件，提供常量和基础类型定义
- **tourze/quic-frames** - QUIC 帧实现，提供流控制帧的结构定义

### 开发依赖
- **phpstan/phpstan** ^2.1 - 静态分析工具
- **phpunit/phpunit** ^10.0 - 单元测试框架

### 系统要求
- PHP 8.1 或更高版本
- 内存：建议 64MB 以上
- 支持的操作系统：Linux, macOS, Windows

## Quick Start

```php
use Tourze\QUIC\FlowControl\FlowControlManager;

// 创建流量控制管理器
$manager = new FlowControlManager(
    1048576,  // 连接级最大数据量 (1MB)
    1048576,  // 本地连接级最大数据量
    65536,    // 流级最大数据量 (64KB)
    65536     // 本地流级最大数据量
);

// 创建流
$stream = $manager->createStream(1);

// 发送数据
$success = $manager->sendData(1, 1024);
if (!$success) {
    echo "发送失败，窗口不足\n";
}

// 接收数据
$manager->receiveData(1, 512);

// 检查健康状态
$health = $manager->getHealthStatus();
echo "健康状态: " . $health['status'] . "\n";

// 获取待发送的控制帧
$frames = $manager->getPendingFrames();
foreach ($frames as $frame) {
    echo "待发送: " . $frame['type'] . "\n";
}
```

## 运行演示

```bash
cd packages/quic-flow-control
php examples/demo.php
```

演示脚本展示了完整的流量控制流程，包括：
- 窗口管理
- 阻塞检测
- 控制帧生成
- 健康监控

## Advanced Usage

### 自定义流量控制配置

```php
use Tourze\QUIC\FlowControl\FlowControlManager;
use Tourze\QUIC\Core\Constants;

// 创建高性能配置的流量控制管理器
$manager = new FlowControlManager(
    16 * 1024 * 1024,  // 16MB 连接窗口 - 适合高带宽场景
    16 * 1024 * 1024,  // 16MB 本地连接窗口
    1024 * 1024,       // 1MB 流窗口 - 适合大文件传输
    1024 * 1024        // 1MB 本地流窗口
);
```

### 流量控制监控和诊断

```php
// 获取详细统计信息
$stats = $manager->getFullStats();

// 连接级统计
$connectionStats = $stats['connection'];
echo "连接窗口利用率: " . ($connectionStats['send_window']['utilization'] * 100) . "%\n";
echo "已发送字节: " . $connectionStats['send_window']['bytes_sent'] . "\n";
echo "窗口大小: " . $connectionStats['send_window']['window_size'] . "\n";

// 流级统计
foreach ($stats['streams'] as $streamId => $streamStats) {
    echo "流 {$streamId} 状态: " . ($streamStats['blocked'] ? '阻塞' : '正常') . "\n";
    echo "流 {$streamId} 利用率: " . ($streamStats['send_window']['utilization'] * 100) . "%\n";
}

// 健康状态监控
$health = $stats['health'];
if ($health['status'] !== 'healthy') {
    echo "警告: 流量控制状态异常 - " . $health['status'] . "\n";
    foreach ($health['warnings'] as $warning) {
        echo "- " . $warning . "\n";
    }
}
```

### 流控制帧处理

```php
// 处理接收到的流控制帧
foreach ($receivedFrames as $frame) {
    switch ($frame['type']) {
        case 'MAX_DATA':
            $manager->handleMaxDataFrame($frame['max_data']);
            break;
            
        case 'MAX_STREAM_DATA':
            $manager->handleMaxStreamDataFrame(
                $frame['stream_id'], 
                $frame['max_stream_data']
            );
            break;
            
        case 'DATA_BLOCKED':
            $manager->handleDataBlockedFrame($frame['data_limit']);
            break;
            
        case 'STREAM_DATA_BLOCKED':
            $manager->handleStreamDataBlockedFrame(
                $frame['stream_id'], 
                $frame['stream_data_limit']
            );
            break;
    }
}

// 获取需要发送的控制帧
$pendingFrames = $manager->getPendingFrames();
foreach ($pendingFrames as $frame) {
    // 发送到网络层
    sendToNetwork($frame);
}
```

### 性能优化建议

```php
// 1. 批量操作多个流
$streamIds = [1, 3, 5, 7, 9];
foreach ($streamIds as $streamId) {
    if ($manager->canSendData($streamId, 1024)) {
        $manager->sendData($streamId, 1024);
    }
}

// 2. 定期检查健康状态
$healthCheckInterval = 30; // 30秒
if (time() % $healthCheckInterval === 0) {
    $health = $manager->getHealthStatus();
    if ($health['status'] !== 'healthy') {
        // 记录警告或采取纠正措施
        logWarning('Flow control health issue', $health);
    }
}

// 3. 动态调整窗口大小（根据网络条件）
$connectionStats = $manager->getConnectionController()->getConnectionStats();
$utilization = $connectionStats['connection']['send_window']['utilization'];

if ($utilization > 0.8) {
    // 高利用率 - 考虑请求更大窗口
    echo "建议请求更大的连接窗口\n";
}
```

### 错误处理和恢复

```php
use Tourze\QUIC\FlowControl\Exception\FlowControlException;
use Tourze\QUIC\FlowControl\Exception\InvalidFlowControlWindowException;

try {
    $success = $manager->sendData($streamId, $dataSize);
    if (!$success) {
        // 发送失败，检查原因
        if ($manager->isConnectionBlocked()) {
            echo "连接级流量控制阻塞\n";
        } elseif ($manager->isStreamBlocked($streamId)) {
            echo "流级流量控制阻塞\n";
        }
    }
} catch (FlowControlException $e) {
    echo "流量控制错误: " . $e->getMessage() . "\n";
    
    // 重置流量控制状态（极端情况）
    $manager->reset();
} catch (InvalidFlowControlWindowException $e) {
    echo "无效的流量控制窗口: " . $e->getMessage() . "\n";
}
```

## 测试

运行单元测试：

```bash
./vendor/bin/phpunit packages/quic-flow-control/tests/
```

测试覆盖：
- ✅ 42个测试用例
- ✅ 115个断言
- ✅ 100%通过率

## RFC 9000 合规性

本实现严格遵循QUIC RFC 9000第4节流量控制规范：

- 连接级流量控制 (Section 4.1)
- 流级流量控制 (Section 4.1) 
- 流控制帧格式 (Section 19.9-19.13)
- 阻塞信号机制
- 窗口更新策略

## 许可证

MIT License
