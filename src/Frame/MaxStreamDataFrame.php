<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Frame;

use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\Core\VariableInteger;
use Tourze\QUIC\Frames\Frame;
use Tourze\QUIC\FlowControl\Exception\InvalidFrameParameterException;

/**
 * MAX_STREAM_DATA 帧
 *
 * 用于更新流级流量控制窗口
 * 参考：https://tools.ietf.org/html/rfc9000#section-19.10
 */
class MaxStreamDataFrame extends Frame
{
    /**
     * @param int $streamId 流ID
     * @param int $maxStreamData 最大流数据量
     */
    public function __construct(
        private readonly int $streamId,
        private readonly int $maxStreamData
    ) {
        if ($streamId < 0) {
            throw new InvalidFrameParameterException('流ID不能为负数');
        }
        if ($maxStreamData < 0) {
            throw new InvalidFrameParameterException('最大流数据量不能为负数');
        }
    }

    /**
     * 获取帧类型
     */
    public function getType(): FrameType
    {
        return FrameType::MAX_STREAM_DATA;
    }

    /**
     * 编码帧为二进制数据
     */
    public function encode(): string
    {
        return chr($this->getType()->value) 
            . VariableInteger::encode($this->streamId)
            . VariableInteger::encode($this->maxStreamData);
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
        if ($frameType !== FrameType::MAX_STREAM_DATA->value) {
            throw new InvalidFrameParameterException('帧类型不匹配');
        }

        $offset++;
        
        // 解码流ID
        [$streamId, $consumed1] = VariableInteger::decode($data, $offset);
        $offset += $consumed1;
        
        // 解码最大流数据量
        [$maxStreamData, $consumed2] = VariableInteger::decode($data, $offset);
        
        return [new self($streamId, $maxStreamData), $consumed1 + $consumed2 + 1];
    }

    /**
     * 验证帧数据的有效性
     */
    public function validate(): bool
    {
        return $this->streamId >= 0 && $this->maxStreamData >= 0;
    }

    /**
     * 获取流ID
     */
    public function getStreamId(): int
    {
        return $this->streamId;
    }

    /**
     * 获取最大流数据量
     */
    public function getMaxStreamData(): int
    {
        return $this->maxStreamData;
    }

    /**
     * 获取帧的优先级
     */
    public function getPriority(): int
    {
        return 5; // 流量控制帧具有较高优先级
    }

    /**
     * 判断是否需要立即发送
     */
    public function requiresImmediateTransmission(): bool
    {
        return true; // MAX_STREAM_DATA 帧应该尽快发送
    }

    /**
     * 获取帧的大小（字节）
     */
    public function getSize(): int
    {
        return 1 
            + VariableInteger::getLength($this->streamId)
            + VariableInteger::getLength($this->maxStreamData);
    }

    /**
     * 转换为字符串表示
     */
    public function __toString(): string
    {
        return "MAX_STREAM_DATA(stream_id={$this->streamId}, max_stream_data={$this->maxStreamData})";
    }
}
