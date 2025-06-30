<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Frame;

use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\Core\VariableInteger;
use Tourze\QUIC\Frames\Frame;
use Tourze\QUIC\FlowControl\Exception\InvalidFrameParameterException;

/**
 * STREAM_DATA_BLOCKED 帧
 *
 * 表示流级流量控制阻塞
 * 参考：https://tools.ietf.org/html/rfc9000#section-19.13
 */
class StreamDataBlockedFrame extends Frame
{
    /**
     * @param int $streamId 流ID
     * @param int $streamDataLimit 流数据限制
     */
    public function __construct(
        private readonly int $streamId,
        private readonly int $streamDataLimit
    ) {
        if ($streamId < 0) {
            throw new InvalidFrameParameterException('流ID不能为负数');
        }
        if ($streamDataLimit < 0) {
            throw new InvalidFrameParameterException('流数据限制不能为负数');
        }
    }

    /**
     * 获取帧类型
     */
    public function getType(): FrameType
    {
        return FrameType::STREAM_DATA_BLOCKED;
    }

    /**
     * 编码帧为二进制数据
     */
    public function encode(): string
    {
        return chr($this->getType()->value) 
            . VariableInteger::encode($this->streamId)
            . VariableInteger::encode($this->streamDataLimit);
    }

    /**
     * 从二进制数据解码帧
     */
    public static function decode(string $data, int $offset = 0): array
    {
        if (strlen($data) <= $offset) {
            throw new InvalidFrameParameterException('数据不足');
        }

        $frameType = ord($data[$offset]);
        if ($frameType !== FrameType::STREAM_DATA_BLOCKED->value) {
            throw new InvalidFrameParameterException('帧类型不匹配');
        }

        $offset++;
        
        // 解码流ID
        [$streamId, $consumed1] = VariableInteger::decode($data, $offset);
        $offset += $consumed1;
        
        // 解码流数据限制
        [$streamDataLimit, $consumed2] = VariableInteger::decode($data, $offset);
        
        return [new self($streamId, $streamDataLimit), $consumed1 + $consumed2 + 1];
    }

    /**
     * 验证帧数据的有效性
     */
    public function validate(): bool
    {
        return $this->streamId >= 0 && $this->streamDataLimit >= 0;
    }

    /**
     * 获取流ID
     */
    public function getStreamId(): int
    {
        return $this->streamId;
    }

    /**
     * 获取流数据限制
     */
    public function getStreamDataLimit(): int
    {
        return $this->streamDataLimit;
    }

    /**
     * 获取帧的优先级
     */
    public function getPriority(): int
    {
        return 6; // 阻塞信号帧优先级稍低于窗口更新帧
    }

    /**
     * 判断是否需要立即发送
     */
    public function requiresImmediateTransmission(): bool
    {
        return true; // 阻塞信号应该尽快发送
    }

    /**
     * 获取帧的大小（字节）
     */
    public function getSize(): int
    {
        return 1 
            + VariableInteger::getLength($this->streamId)
            + VariableInteger::getLength($this->streamDataLimit);
    }

    /**
     * 转换为字符串表示
     */
    public function __toString(): string
    {
        return "STREAM_DATA_BLOCKED(stream_id={$this->streamId}, stream_data_limit={$this->streamDataLimit})";
    }
} 