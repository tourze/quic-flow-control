<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl;

use Tourze\QUIC\Core\Constants;

/**
 * 流级流量控制器
 * 
 * 管理单个流的发送和接收流量控制窗口
 * 参考：https://tools.ietf.org/html/rfc9000#section-4.1
 */
class StreamFlowController
{
    private FlowControlWindow $sendWindow;
    private FlowControlWindow $receiveWindow;
    private bool $sendBlocked = false;
    private bool $receiveBlocked = false;

    /**
     * @param int $streamId 流ID
     * @param int $initialMaxStreamData 初始最大流数据量
     * @param int $localInitialMaxStreamData 本地初始最大流数据量
     */
    public function __construct(
        private readonly int $streamId,
        int $initialMaxStreamData = Constants::DEFAULT_MAX_STREAM_DATA,
        int $localInitialMaxStreamData = Constants::DEFAULT_MAX_STREAM_DATA
    ) {
        if ($streamId < 0) {
            throw new \InvalidArgumentException('流ID不能为负数');
        }

        $this->sendWindow = new FlowControlWindow($initialMaxStreamData);
        $this->receiveWindow = new FlowControlWindow($localInitialMaxStreamData);
    }

    /**
     * 获取流ID
     */
    public function getStreamId(): int
    {
        return $this->streamId;
    }

    /**
     * 检查是否可以发送指定字节数的数据
     */
    public function canSend(int $bytes): bool
    {
        return $this->sendWindow->canSend($bytes);
    }

    /**
     * 检查是否可以接收指定字节数的数据
     */
    public function canReceive(int $bytes): bool
    {
        return $this->receiveWindow->canReceive($bytes);
    }

    /**
     * 消费发送窗口
     *
     * @param int $bytes 要发送的字节数
     * @return bool 是否成功发送
     * @throws FlowControlException 当窗口不足时
     */
    public function send(int $bytes): bool
    {
        if ($bytes <= 0) {
            return true;
        }

        if (!$this->canSend($bytes)) {
            $this->sendBlocked = true;
            $this->sendWindow->setBlocked();
            return false;
        }

        $this->sendWindow->consumeSendWindow($bytes);
        
        // 检查发送后窗口是否耗尽
        if ($this->sendWindow->isExhausted()) {
            $this->sendBlocked = true;
            $this->sendWindow->setBlocked();
        } else {
            $this->sendBlocked = false;
        }
        
        return true;
    }

    /**
     * 消费接收窗口
     *
     * @param int $bytes 接收的字节数
     * @return bool 是否成功接收
     * @throws FlowControlException 当窗口不足时
     */
    public function receive(int $bytes): bool
    {
        if ($bytes <= 0) {
            return true;
        }

        if (!$this->canReceive($bytes)) {
            $this->receiveBlocked = true;
            return false;
        }

        $this->receiveWindow->consumeReceiveWindow($bytes);
        $this->receiveBlocked = false;
        return true;
    }

    /**
     * 更新发送窗口最大数据量（收到 MAX_STREAM_DATA 帧时）
     */
    public function updateSendWindow(int $maxStreamData): void
    {
        $this->sendWindow->updateMaxData($maxStreamData);
        
        // 如果之前被阻塞，现在可能可以继续发送
        if ($this->sendBlocked && $this->sendWindow->getAvailableSendWindow() > 0) {
            $this->sendBlocked = false;
        }
    }

    /**
     * 更新接收窗口最大数据量（发送 MAX_STREAM_DATA 帧时）
     */
    public function updateReceiveWindow(int $maxStreamData): void
    {
        $this->receiveWindow->updateMaxData($maxStreamData);
        
        // 如果之前被阻塞，现在可能可以继续接收
        if ($this->receiveBlocked && $this->receiveWindow->getAvailableReceiveWindow() > 0) {
            $this->receiveBlocked = false;
        }
    }

    /**
     * 检查发送是否被阻塞
     */
    public function isSendBlocked(): bool
    {
        return $this->sendBlocked || $this->sendWindow->isExhausted();
    }

    /**
     * 检查接收是否被阻塞
     */
    public function isReceiveBlocked(): bool
    {
        return $this->receiveBlocked || $this->receiveWindow->isReceiveExhausted();
    }

    /**
     * 获取需要发送的 STREAM_DATA_BLOCKED 帧的偏移量
     */
    public function getStreamDataBlockedOffset(): ?int
    {
        if (!$this->isSendBlocked()) {
            return null;
        }
        return $this->sendWindow->getBlockedAt();
    }

    /**
     * 获取发送窗口
     */
    public function getSendWindow(): FlowControlWindow
    {
        return $this->sendWindow;
    }

    /**
     * 获取接收窗口
     */
    public function getReceiveWindow(): FlowControlWindow
    {
        return $this->receiveWindow;
    }

    /**
     * 获取可用的发送窗口大小
     */
    public function getAvailableSendWindow(): int
    {
        return $this->sendWindow->getAvailableSendWindow();
    }

    /**
     * 获取可用的接收窗口大小
     */
    public function getAvailableReceiveWindow(): int
    {
        return $this->receiveWindow->getAvailableReceiveWindow();
    }

    /**
     * 检查是否需要发送 MAX_STREAM_DATA 帧更新接收窗口
     */
    public function shouldSendMaxStreamData(): bool
    {
        // 当接收窗口使用率超过一定阈值时发送更新
        $threshold = 0.5; // 50%
        return $this->receiveWindow->getReceiveUtilization() > $threshold;
    }

    /**
     * 计算下一个 MAX_STREAM_DATA 帧的值
     */
    public function getNextMaxStreamData(): int
    {
        $currentMax = $this->receiveWindow->getMaxData();
        $consumed = $this->receiveWindow->getConsumedData();
        
        // 增加一个合理的窗口大小
        $additionalWindow = max(
            Constants::DEFAULT_MAX_STREAM_DATA,
            $currentMax - $consumed
        );
        
        return $currentMax + $additionalWindow;
    }

    /**
     * 重置流量控制状态（用于流重置时）
     */
    public function reset(): void
    {
        $this->sendBlocked = false;
        $this->receiveBlocked = false;
        $this->sendWindow->resetSentData();
    }

    /**
     * 获取流量控制统计信息
     */
    public function getStats(): array
    {
        return [
            'stream_id' => $this->streamId,
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
        ];
    }
} 