<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl;

use Tourze\QUIC\Core\Constants;

/**
 * 连接级流量控制器
 * 
 * 管理整个连接的发送和接收流量控制窗口
 * 参考：https://tools.ietf.org/html/rfc9000#section-4.1
 */
class ConnectionFlowController
{
    private FlowControlWindow $sendWindow;
    private FlowControlWindow $receiveWindow;
    private bool $sendBlocked = false;
    private bool $receiveBlocked = false;
    
    /** @var array<int, StreamFlowController> 流控制器映射 */
    private array $streamControllers = [];

    /**
     * @param int $initialMaxData 初始最大数据量
     * @param int $localInitialMaxData 本地初始最大数据量
     */
    public function __construct(
        int $initialMaxData = Constants::DEFAULT_MAX_DATA,
        int $localInitialMaxData = Constants::DEFAULT_MAX_DATA
    ) {
        $this->sendWindow = new FlowControlWindow($initialMaxData);
        $this->receiveWindow = new FlowControlWindow($localInitialMaxData);
    }

    /**
     * 注册流控制器
     */
    public function registerStream(StreamFlowController $streamController): void
    {
        $this->streamControllers[$streamController->getStreamId()] = $streamController;
    }

    /**
     * 注销流控制器
     */
    public function unregisterStream(int $streamId): void
    {
        unset($this->streamControllers[$streamId]);
    }

    /**
     * 获取流控制器
     */
    public function getStreamController(int $streamId): ?StreamFlowController
    {
        return $this->streamControllers[$streamId] ?? null;
    }

    /**
     * 检查连接级是否可以发送指定字节数的数据
     */
    public function canSend(int $bytes): bool
    {
        return $this->sendWindow->canSend($bytes);
    }

    /**
     * 检查连接级是否可以接收指定字节数的数据
     */
    public function canReceive(int $bytes): bool
    {
        return $this->receiveWindow->canReceive($bytes);
    }

    /**
     * 检查流和连接级是否都可以发送数据
     */
    public function canStreamSend(int $streamId, int $bytes): bool
    {
        // 检查连接级窗口
        if (!$this->canSend($bytes)) {
            return false;
        }

        // 检查流级窗口
        $streamController = $this->getStreamController($streamId);
        if ($streamController === null) {
            return false;
        }

        return $streamController->canSend($bytes);
    }

    /**
     * 发送数据（同时消费连接级和流级窗口）
     * 
     * @param int $streamId 流ID
     * @param int $bytes 要发送的字节数
     * @return bool 是否成功发送
     */
    public function send(int $streamId, int $bytes): bool
    {
        if ($bytes <= 0) {
            return true;
        }

        $streamController = $this->getStreamController($streamId);
        if ($streamController === null) {
            throw new FlowControlException("流 {$streamId} 未注册");
        }

        // 检查连接级窗口
        if (!$this->canSend($bytes)) {
            $this->sendBlocked = true;
            $this->sendWindow->setBlocked();
            return false;
        }

        // 检查流级窗口
        if (!$streamController->canSend($bytes)) {
            return false;
        }

        // 消费两级窗口
        $this->sendWindow->consumeSendWindow($bytes);
        $streamController->send($bytes);
        
        $this->sendBlocked = false;
        return true;
    }

    /**
     * 接收数据（同时消费连接级和流级窗口）
     * 
     * @param int $streamId 流ID
     * @param int $bytes 接收的字节数
     * @return bool 是否成功接收
     */
    public function receive(int $streamId, int $bytes): bool
    {
        if ($bytes <= 0) {
            return true;
        }

        $streamController = $this->getStreamController($streamId);
        if ($streamController === null) {
            throw new FlowControlException("流 {$streamId} 未注册");
        }

        // 检查连接级窗口
        if (!$this->canReceive($bytes)) {
            $this->receiveBlocked = true;
            return false;
        }

        // 检查流级窗口
        if (!$streamController->canReceive($bytes)) {
            return false;
        }

        // 消费两级窗口
        $this->receiveWindow->consumeReceiveWindow($bytes);
        $streamController->receive($bytes);
        
        $this->receiveBlocked = false;
        return true;
    }

    /**
     * 更新连接级发送窗口（收到 MAX_DATA 帧时）
     */
    public function updateSendWindow(int $maxData): void
    {
        $this->sendWindow->updateMaxData($maxData);
        
        // 如果之前被阻塞，现在可能可以继续发送
        if ($this->sendBlocked && $this->sendWindow->getAvailableSendWindow() > 0) {
            $this->sendBlocked = false;
        }
    }

    /**
     * 更新连接级接收窗口（发送 MAX_DATA 帧时）
     */
    public function updateReceiveWindow(int $maxData): void
    {
        $this->receiveWindow->updateMaxData($maxData);
        
        // 如果之前被阻塞，现在可能可以继续接收
        if ($this->receiveBlocked && $this->receiveWindow->getAvailableReceiveWindow() > 0) {
            $this->receiveBlocked = false;
        }
    }

    /**
     * 检查连接级发送是否被阻塞
     */
    public function isSendBlocked(): bool
    {
        return $this->sendBlocked || $this->sendWindow->isExhausted();
    }

    /**
     * 检查连接级接收是否被阻塞
     */
    public function isReceiveBlocked(): bool
    {
        return $this->receiveBlocked || $this->receiveWindow->isExhausted();
    }

    /**
     * 获取需要发送的 DATA_BLOCKED 帧的偏移量
     */
    public function getDataBlockedOffset(): ?int
    {
        if (!$this->isSendBlocked()) {
            return null;
        }
        return $this->sendWindow->getBlockedAt();
    }

    /**
     * 获取连接级发送窗口
     */
    public function getSendWindow(): FlowControlWindow
    {
        return $this->sendWindow;
    }

    /**
     * 获取连接级接收窗口
     */
    public function getReceiveWindow(): FlowControlWindow
    {
        return $this->receiveWindow;
    }

    /**
     * 获取可用的连接级发送窗口大小
     */
    public function getAvailableSendWindow(): int
    {
        return $this->sendWindow->getAvailableSendWindow();
    }

    /**
     * 获取可用的连接级接收窗口大小
     */
    public function getAvailableReceiveWindow(): int
    {
        return $this->receiveWindow->getAvailableReceiveWindow();
    }

    /**
     * 检查是否需要发送 MAX_DATA 帧更新连接级接收窗口
     */
    public function shouldSendMaxData(): bool
    {
        // 当接收窗口使用率超过一定阈值时发送更新
        $threshold = 0.5; // 50%
        return $this->receiveWindow->getReceiveUtilization() > $threshold;
    }

    /**
     * 计算下一个 MAX_DATA 帧的值
     */
    public function getNextMaxData(): int
    {
        $currentMax = $this->receiveWindow->getMaxData();
        $consumed = $this->receiveWindow->getConsumedData();
        
        // 增加一个合理的窗口大小
        $additionalWindow = max(
            Constants::DEFAULT_MAX_DATA,
            $currentMax - $consumed
        );
        
        return $currentMax + $additionalWindow;
    }

    /**
     * 获取所有被阻塞的流ID
     */
    public function getBlockedStreams(): array
    {
        $blockedStreams = [];
        
        foreach ($this->streamControllers as $streamId => $controller) {
            if ($controller->isSendBlocked()) {
                $blockedStreams[] = $streamId;
            }
        }
        
        return $blockedStreams;
    }

    /**
     * 获取所有需要发送 MAX_STREAM_DATA 的流
     */
    public function getStreamsNeedingMaxData(): array
    {
        $streams = [];
        
        foreach ($this->streamControllers as $streamId => $controller) {
            if ($controller->shouldSendMaxStreamData()) {
                $streams[$streamId] = $controller->getNextMaxStreamData();
            }
        }
        
        return $streams;
    }

    /**
     * 重置所有流量控制状态
     */
    public function reset(): void
    {
        $this->sendBlocked = false;
        $this->receiveBlocked = false;
        $this->sendWindow->resetSentData();
        
        foreach ($this->streamControllers as $controller) {
            $controller->reset();
        }
    }

    /**
     * 获取连接级流量控制统计信息
     */
    public function getConnectionStats(): array
    {
        return [
            'connection' => [
                'send_window' => [
                    'max_data' => $this->sendWindow->getMaxData(),
                    'sent_data' => $this->sendWindow->getSentData(),
                    'available' => $this->sendWindow->getAvailableSendWindow(),
                    'utilization' => $this->sendWindow->getUtilization(),
                    'blocked' => $this->sendBlocked,
                ],
                'receive_window' => [
                    'max_data' => $this->receiveWindow->getMaxData(),
                    'consumed_data' => $this->receiveWindow->getConsumedData(),
                    'available' => $this->receiveWindow->getAvailableReceiveWindow(),
                    'utilization' => $this->receiveWindow->getReceiveUtilization(),
                    'blocked' => $this->receiveBlocked,
                ],
            ],
            'streams' => array_map(
                fn(StreamFlowController $controller) => $controller->getStats(),
                $this->streamControllers
            ),
            'summary' => [
                'total_streams' => count($this->streamControllers),
                'blocked_streams' => count($this->getBlockedStreams()),
                'streams_needing_max_data' => count($this->getStreamsNeedingMaxData()),
            ],
        ];
    }
}
