<?php

declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Tourze\QUIC\Core\Constants;
use Tourze\QUIC\FlowControl\FlowControlManager;

echo "=== QUIC Flow Control Demo ===\n\n";

// 1. 创建流量控制管理器
echo "1. 创建流量控制管理器\n";
$manager = new FlowControlManager(
    Constants::DEFAULT_MAX_DATA,     // 连接级最大数据量
    Constants::DEFAULT_MAX_DATA,     // 本地连接级最大数据量
    64 * 1024,                       // 流级最大数据量 64KB
    64 * 1024                        // 本地流级最大数据量 64KB
);
echo "   连接最大数据量: " . number_format($manager->getAvailableConnectionSendWindow()) . " 字节\n\n";

// 2. 创建多个流控制器
echo "2. 创建多个流控制器\n";
$streams = [];
for ($i = 1; $i <= 3; $i++) {
    $stream = $manager->createStream($i);
    $streams[$i] = $stream;
    echo "   流 #{$i}: 发送窗口 " . number_format($stream->getAvailableSendWindow()) . " 字节\n";
}
echo "\n";

// 3. 模拟数据发送
echo "3. 模拟数据发送\n";
$sendData = function($streamId, $bytes) use ($manager) {
    $success = $manager->sendData($streamId, $bytes);
    
    echo "   流 #{$streamId} 发送 " . number_format($bytes) . " 字节: " . 
         ($success ? "成功" : "失败（窗口不足）") . "\n";
    
    if ($success) {
        $remainingWindow = $manager->getAvailableStreamSendWindow($streamId);
        echo "     剩余发送窗口: " . number_format($remainingWindow) . " 字节\n";
        if ($manager->isStreamBlocked($streamId)) {
            echo "     ⚠️  流已被阻塞，需要等待 MAX_STREAM_DATA 帧\n";
        }
    }
};

$sendData(1, 32 * 1024); // 32KB
$sendData(1, 32 * 1024); // 另外32KB，应该耗尽流1的窗口
$sendData(1, 1024);      // 应该失败
$sendData(2, 16 * 1024); // 流2发送16KB
echo "\n";

// 4. 检查阻塞状态和生成控制帧
echo "4. 检查阻塞状态\n";
foreach ($streams as $streamId => $stream) {
    echo "   流 #{$streamId}:\n";
    echo "     发送阻塞: " . ($stream->isSendBlocked() ? "是" : "否") . "\n";
    echo "     接收阻塞: " . ($stream->isReceiveBlocked() ? "是" : "否") . "\n";
    
    if ($stream->isSendBlocked()) {
        $blockedOffset = $stream->getStreamDataBlockedOffset();
        echo "     阻塞偏移量: " . ($blockedOffset !== null ? number_format($blockedOffset) : "无") . "\n";
    }
}

// 检查待发送的控制帧
$pendingFrames = $manager->getPendingFrames();
if (!empty($pendingFrames)) {
    echo "   待发送的控制帧:\n";
    foreach ($pendingFrames as $frame) {
        echo "     " . $frame['type'] . ": " . json_encode($frame['data'], JSON_UNESCAPED_UNICODE) . "\n";
    }
}
echo "\n";

// 5. 模拟数据接收
echo "5. 模拟数据接收\n";
$receiveData = function($streamId, $bytes) use ($manager, $streams) {
    $success = $manager->receiveData($streamId, $bytes);
    
    echo "   流 #{$streamId} 接收 " . number_format($bytes) . " 字节: " . 
         ($success ? "成功" : "失败") . "\n";
    
    if ($success) {
        $stream = $streams[$streamId];
        echo "     剩余接收窗口: " . number_format($stream->getAvailableReceiveWindow()) . " 字节\n";
        echo "     接收窗口利用率: " . sprintf("%.1f%%", $stream->getReceiveWindow()->getReceiveUtilization() * 100) . "\n";
        
        if ($stream->shouldSendMaxStreamData()) {
            echo "     📤 需要发送 MAX_STREAM_DATA 帧更新窗口\n";
            $nextMaxData = $stream->getNextMaxStreamData();
            echo "     下一个最大数据量: " . number_format($nextMaxData) . " 字节\n";
        }
    }
};

$receiveData(2, 40 * 1024); // 流2接收40KB，应该触发窗口更新

// 检查接收后生成的控制帧
$pendingFrames = $manager->getPendingFrames();
if (!empty($pendingFrames)) {
    echo "   接收后生成的控制帧:\n";
    foreach ($pendingFrames as $frame) {
        echo "     " . $frame['type'] . ": " . json_encode($frame['data'], JSON_UNESCAPED_UNICODE) . "\n";
    }
}
echo "\n";

// 6. 获取统计信息
echo "6. 流量控制统计信息\n";
foreach ($streams as $streamId => $stream) {
    $stats = $stream->getStats();
    echo "   流 #{$streamId}:\n";
    echo "     发送窗口: " . number_format($stats['send_window']['sent_data']) . 
         "/" . number_format($stats['send_window']['max_data']) . 
         " 字节 (利用率: " . sprintf("%.1f%%", $stats['send_window']['utilization'] * 100) . ")\n";
    echo "     接收窗口: " . number_format($stats['receive_window']['consumed_data']) . 
         "/" . number_format($stats['receive_window']['max_data']) . 
         " 字节 (利用率: " . sprintf("%.1f%%", $stats['receive_window']['utilization'] * 100) . ")\n";
}
echo "\n";

// 7. 检查管理器状态
echo "7. 流量控制管理器状态\n";
$fullStats = $manager->getFullStats();
echo "   连接级统计:\n";
echo "     发送窗口利用率: " . sprintf("%.1f%%", $fullStats['connection']['send_window']['utilization'] * 100) . "\n";
echo "     接收窗口利用率: " . sprintf("%.1f%%", $fullStats['connection']['receive_window']['utilization'] * 100) . "\n";
echo "     总流数量: " . $fullStats['summary']['total_streams'] . "\n";
echo "     阻塞流数量: " . $fullStats['summary']['blocked_streams'] . "\n";
echo "     需要窗口更新的流: " . $fullStats['summary']['streams_needing_max_data'] . "\n";

$health = $manager->getHealthStatus();
echo "   健康状态: " . $health['status'] . "\n";
if (!empty($health['warnings'])) {
    echo "   警告: " . implode(", ", $health['warnings']) . "\n";
}
echo "   连接利用率: " . sprintf("%.1f%%", $health['connection_utilization'] * 100) . "\n";
echo "   阻塞流比例: " . sprintf("%.1f%%", $health['blocked_streams_ratio'] * 100) . "\n";

echo "\n=== Demo 完成 ===\n"; 