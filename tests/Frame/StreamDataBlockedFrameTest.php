<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Tests\Frame;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\FlowControl\Frame\StreamDataBlockedFrame;
use Tourze\QUIC\FlowControl\Exception\InvalidFrameParameterException;

class StreamDataBlockedFrameTest extends TestCase
{
    public function testConstruction(): void
    {
        $frame = new StreamDataBlockedFrame(1, 1000);
        $this->assertEquals(FrameType::STREAM_DATA_BLOCKED, $frame->getType());
        $this->assertEquals(1, $frame->getStreamId());
        $this->assertEquals(1000, $frame->getStreamDataLimit());
    }

    public function testConstructionWithNegativeStreamId(): void
    {
        $this->expectException(InvalidFrameParameterException::class);
        $this->expectExceptionMessage('流ID不能为负数');
        new StreamDataBlockedFrame(-1, 1000);
    }

    public function testConstructionWithNegativeStreamDataLimit(): void
    {
        $this->expectException(InvalidFrameParameterException::class);
        $this->expectExceptionMessage('流数据限制不能为负数');
        new StreamDataBlockedFrame(1, -1);
    }

    public function testEncode(): void
    {
        $frame = new StreamDataBlockedFrame(1, 1000);
        $encoded = $frame->encode();
        $this->assertNotEmpty($encoded);
    }

    public function testDecode(): void
    {
        $frame = new StreamDataBlockedFrame(1, 1000);
        $encoded = $frame->encode();
        
        [$decodedFrame, $consumed] = StreamDataBlockedFrame::decode($encoded);
        $this->assertInstanceOf(StreamDataBlockedFrame::class, $decodedFrame);
        $this->assertEquals(1, $decodedFrame->getStreamId());
        $this->assertEquals(1000, $decodedFrame->getStreamDataLimit());
        $this->assertGreaterThan(0, $consumed);
    }

    public function testDecodeWithInsufficientData(): void
    {
        $this->expectException(InvalidFrameParameterException::class);
        $this->expectExceptionMessage('数据不足');
        StreamDataBlockedFrame::decode('');
    }

    public function testValidate(): void
    {
        $frame = new StreamDataBlockedFrame(1, 1000);
        $this->assertTrue($frame->validate());
    }

    public function testGetPriority(): void
    {
        $frame = new StreamDataBlockedFrame(1, 1000);
        $this->assertEquals(6, $frame->getPriority());
    }

    public function testRequiresImmediateTransmission(): void
    {
        $frame = new StreamDataBlockedFrame(1, 1000);
        $this->assertTrue($frame->requiresImmediateTransmission());
    }

    public function testGetSize(): void
    {
        $frame = new StreamDataBlockedFrame(1, 1000);
        $this->assertGreaterThan(0, $frame->getSize());
    }

    public function testToString(): void
    {
        $frame = new StreamDataBlockedFrame(1, 1000);
        $this->assertEquals('STREAM_DATA_BLOCKED(stream_id=1, stream_data_limit=1000)', $frame->__toString());
    }
} 