<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Frame;

use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\Core\VariableInteger;
use Tourze\QUIC\Frames\Frame;

/**
 * MAX_DATA 帧
 *
 * 用于更新连接级流量控制窗口
 * 参考：https://tools.ietf.org/html/rfc9000#section-19.9
 */
class MaxDataFrame extends Frame
{
    /**
     * @param int $maxData 最大数据量
     */
    public function __construct(
        private readonly int $maxData
    ) {
        if ($maxData < 0) {
            throw new \InvalidArgumentException('最大数据量不能为负数');
        }
    }

    /**
     * 获取帧类型
     */
    public function getType(): FrameType
    {
        return FrameType::MAX_DATA;
    }

    /**
     * 编码帧为二进制数据
     */
    public function encode(): string
    {
        return chr($this->getType()->value) . VariableInteger::encode($this->maxData);
    }

    /**
     * 从二进制数据解码帧
     */
    public static function decode(string $data, int $offset = 0): array
    {
        if (strlen($data) <= $offset) {
            throw new \InvalidArgumentException('数据不足');
        }

        $frameType = ord($data[$offset]);
        if ($frameType !== FrameType::MAX_DATA->value) {
            throw new \InvalidArgumentException('帧类型不匹配');
        }

        $offset++;
        [$maxData, $consumed] = VariableInteger::decode($data, $offset);
        
        return [new self($maxData), $consumed + 1];
    }

    /**
     * 验证帧数据的有效性
     */
    public function validate(): bool
    {
        return $this->maxData >= 0;
    }

    /**
     * 获取最大数据量
     */
    public function getMaxData(): int
    {
        return $this->maxData;
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
        return true; // MAX_DATA 帧应该尽快发送
    }

    /**
     * 获取帧的大小（字节）
     */
    public function getSize(): int
    {
        return 1 + VariableInteger::getLength($this->maxData);
    }

    /**
     * 转换为字符串表示
     */
    public function __toString(): string
    {
        return "MAX_DATA(max_data={$this->maxData})";
    }
}
