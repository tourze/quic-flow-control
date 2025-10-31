<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Tests\Frame;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\FlowControl\Exception\InvalidFrameParameterException;
use Tourze\QUIC\FlowControl\Frame\MaxStreamDataFrame;

/**
 * @internal
 */
#[CoversClass(MaxStreamDataFrame::class)]
final class MaxStreamDataFrameTest extends TestCase
{
    public function testConstruction(): void
    {
        $frame = new MaxStreamDataFrame(1, 1000);
        $this->assertEquals(FrameType::MAX_STREAM_DATA, $frame->getType());
        $this->assertEquals(1, $frame->getStreamId());
        $this->assertEquals(1000, $frame->getMaxStreamData());
    }

    public function testConstructionWithNegativeStreamId(): void
    {
        $this->expectException(InvalidFrameParameterException::class);
        $this->expectExceptionMessage('流ID不能为负数');
        new MaxStreamDataFrame(-1, 1000);
    }

    public function testConstructionWithNegativeMaxStreamData(): void
    {
        $this->expectException(InvalidFrameParameterException::class);
        $this->expectExceptionMessage('最大流数据量不能为负数');
        new MaxStreamDataFrame(1, -1);
    }

    public function testEncode(): void
    {
        $frame = new MaxStreamDataFrame(1, 1000);
        $encoded = $frame->encode();
        $this->assertNotEmpty($encoded);
    }

    public function testDecode(): void
    {
        $frame = new MaxStreamDataFrame(1, 1000);
        $encoded = $frame->encode();

        [$decodedFrame, $consumed] = MaxStreamDataFrame::decode($encoded);
        $this->assertInstanceOf(MaxStreamDataFrame::class, $decodedFrame);
        $this->assertEquals(1, $decodedFrame->getStreamId());
        $this->assertEquals(1000, $decodedFrame->getMaxStreamData());
        $this->assertGreaterThan(0, $consumed);
    }

    public function testDecodeWithInsufficientData(): void
    {
        $this->expectException(InvalidFrameParameterException::class);
        $this->expectExceptionMessage('数据不足');
        MaxStreamDataFrame::decode('');
    }

    public function testValidate(): void
    {
        $frame = new MaxStreamDataFrame(1, 1000);
        $this->assertTrue($frame->validate());
    }

    public function testGetPriority(): void
    {
        $frame = new MaxStreamDataFrame(1, 1000);
        $this->assertEquals(5, $frame->getPriority());
    }

    public function testRequiresImmediateTransmission(): void
    {
        $frame = new MaxStreamDataFrame(1, 1000);
        $this->assertTrue($frame->requiresImmediateTransmission());
    }

    public function testGetSize(): void
    {
        $frame = new MaxStreamDataFrame(1, 1000);
        $this->assertGreaterThan(0, $frame->getSize());
    }

    public function testToString(): void
    {
        $frame = new MaxStreamDataFrame(1, 1000);
        $this->assertEquals('MAX_STREAM_DATA(stream_id=1, max_stream_data=1000)', $frame->__toString());
    }
}
