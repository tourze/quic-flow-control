<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl;

use Tourze\QUIC\FlowControl\Exception\InvalidFlowControlWindowException;

/**
 * 流量控制窗口
 *
 * 管理发送和接收窗口，用于连接级和流级流量控制
 * 参考：https://tools.ietf.org/html/rfc9000#section-4
 */
class FlowControlWindow
{
    /**
     * @param int $maxData      最大数据量
     * @param int $consumedData 已消费的数据量
     * @param int $sentData     已发送的数据量
     * @param int $blockedAt    阻塞位置（用于发送 BLOCKED 帧）
     */
    public function __construct(
        private int $maxData,
        private int $consumedData = 0,
        private int $sentData = 0,
        private ?int $blockedAt = null,
    ) {
        if ($maxData < 0) {
            throw new InvalidFlowControlWindowException('最大数据量不能为负数');
        }
        if ($consumedData < 0) {
            throw new InvalidFlowControlWindowException('已消费数据量不能为负数');
        }
        if ($sentData < 0) {
            throw new InvalidFlowControlWindowException('已发送数据量不能为负数');
        }
    }

    /**
     * 获取可用的发送窗口大小
     */
    public function getAvailableSendWindow(): int
    {
        return max(0, $this->maxData - $this->sentData);
    }

    /**
     * 获取可用的接收窗口大小
     */
    public function getAvailableReceiveWindow(): int
    {
        return max(0, $this->maxData - $this->consumedData);
    }

    /**
     * 检查是否有足够的发送窗口
     */
    public function canSend(int $bytes): bool
    {
        return $bytes <= $this->getAvailableSendWindow();
    }

    /**
     * 检查是否可以接收指定字节数
     */
    public function canReceive(int $bytes): bool
    {
        return $bytes <= $this->getAvailableReceiveWindow();
    }

    /**
     * 消费发送窗口
     *
     * @param int $bytes 要发送的字节数
     *
     * @throws InvalidFlowControlWindowException 当窗口不足时
     */
    public function consumeSendWindow(int $bytes): void
    {
        if ($bytes < 0) {
            throw new InvalidFlowControlWindowException('字节数不能为负数');
        }

        if (!$this->canSend($bytes)) {
            throw new InvalidFlowControlWindowException("发送窗口不足：需要 {$bytes} 字节，可用 {$this->getAvailableSendWindow()} 字节");
        }

        $this->sentData += $bytes;
    }

    /**
     * 消费接收窗口
     *
     * @param int $bytes 接收的字节数
     *
     * @throws InvalidFlowControlWindowException 当窗口不足时
     */
    public function consumeReceiveWindow(int $bytes): void
    {
        if ($bytes < 0) {
            throw new InvalidFlowControlWindowException('字节数不能为负数');
        }

        if (!$this->canReceive($bytes)) {
            throw new InvalidFlowControlWindowException("接收窗口不足：需要 {$bytes} 字节，可用 {$this->getAvailableReceiveWindow()} 字节");
        }

        $this->consumedData += $bytes;
    }

    /**
     * 更新最大数据量（当收到 MAX_DATA 或 MAX_STREAM_DATA 帧时）
     */
    public function updateMaxData(int $maxData): void
    {
        if ($maxData < $this->maxData) {
            throw new InvalidFlowControlWindowException("不能降低最大数据量：当前 {$this->maxData}，新值 {$maxData}");
        }

        $this->maxData = $maxData;

        // 清除阻塞状态
        if (null !== $this->blockedAt && $this->maxData > $this->blockedAt) {
            $this->blockedAt = null;
        }
    }

    /**
     * 设置阻塞位置
     */
    public function setBlocked(): void
    {
        $this->blockedAt = $this->sentData;
    }

    /**
     * 检查是否被阻塞
     */
    public function isBlocked(): bool
    {
        return null !== $this->blockedAt;
    }

    /**
     * 获取阻塞位置
     */
    public function getBlockedAt(): ?int
    {
        return $this->blockedAt;
    }

    /**
     * 获取最大数据量
     */
    public function getMaxData(): int
    {
        return $this->maxData;
    }

    /**
     * 获取已消费的数据量
     */
    public function getConsumedData(): int
    {
        return $this->consumedData;
    }

    /**
     * 获取已发送的数据量
     */
    public function getSentData(): int
    {
        return $this->sentData;
    }

    /**
     * 重置已发送数据量（用于重传时）
     */
    public function resetSentData(int $sentData = 0): void
    {
        if ($sentData < 0) {
            throw new InvalidFlowControlWindowException('已发送数据量不能为负数');
        }
        $this->sentData = $sentData;
    }

    /**
     * 检查窗口是否耗尽
     */
    public function isExhausted(): bool
    {
        return 0 === $this->getAvailableSendWindow();
    }

    /**
     * 检查接收窗口是否耗尽
     */
    public function isReceiveExhausted(): bool
    {
        return 0 === $this->getAvailableReceiveWindow();
    }

    /**
     * 获取发送窗口利用率
     */
    public function getUtilization(): float
    {
        if (0 === $this->maxData) {
            return 0.0;
        }

        return (float) $this->sentData / $this->maxData;
    }

    /**
     * 获取接收窗口利用率
     */
    public function getReceiveUtilization(): float
    {
        if (0 === $this->maxData) {
            return 0.0;
        }

        return (float) $this->consumedData / $this->maxData;
    }

    /**
     * 创建用于接收的窗口副本
     */
    public function createReceiveWindow(int $maxData): self
    {
        return new self($maxData, 0, 0);
    }
}
