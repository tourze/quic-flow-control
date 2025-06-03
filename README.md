# QUIC Flow Control

QUIC协议流量控制实现，提供连接级和流级流量控制机制，完全符合RFC 9000规范。

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

## 基本使用

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
