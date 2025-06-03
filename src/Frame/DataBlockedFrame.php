<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Frame;

use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\Core\VariableInteger;
use Tourze\QUIC\Frames\Frame;

/**
 * DATA_BLOCKED 帧
 * 
 * 表示连接级流量控制阻塞
 * 参考：https://tools.ietf.org/html/rfc9000#section-19.12
 */
class DataBlockedFrame extends Frame
{
    /**
     * @param int $dataLimit 数据限制
     */
    public function __construct(
        private readonly int $dataLimit
    ) {
        if ($dataLimit < 0) {
            throw new \InvalidArgumentException('数据限制不能为负数');
        }
    }

    /**
     * 获取帧类型
     */
    public function getType(): FrameType
    {
        return FrameType::DATA_BLOCKED;
    }

    /**
     * 编码帧为二进制数据
     */
    public function encode(): string
    {
        return chr($this->getType()->value) . VariableInteger::encode($this->dataLimit);
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
        if ($frameType !== FrameType::DATA_BLOCKED->value) {
            throw new \InvalidArgumentException('帧类型不匹配');
        }

        $offset++;
        [$dataLimit, $consumed] = VariableInteger::decode($data, $offset);
        
        return [new self($dataLimit), $consumed + 1];
    }

    /**
     * 验证帧数据的有效性
     */
    public function validate(): bool
    {
        return $this->dataLimit >= 0;
    }

    /**
     * 获取数据限制
     */
    public function getDataLimit(): int
    {
        return $this->dataLimit;
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
        return 1 + VariableInteger::getLength($this->dataLimit);
    }

    /**
     * 转换为字符串表示
     */
    public function __toString(): string
    {
        return "DATA_BLOCKED(data_limit={$this->dataLimit})";
    }
} 