<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Tests\Frame;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\FlowControl\Frame\DataBlockedFrame;
use Tourze\QUIC\FlowControl\Exception\InvalidFrameParameterException;

class DataBlockedFrameTest extends TestCase
{
    public function testConstruction(): void
    {
        $frame = new DataBlockedFrame(1000);
        $this->assertEquals(FrameType::DATA_BLOCKED, $frame->getType());
        $this->assertEquals(1000, $frame->getDataLimit());
    }

    public function testConstructionWithNegativeDataLimit(): void
    {
        $this->expectException(InvalidFrameParameterException::class);
        $this->expectExceptionMessage('数据限制不能为负数');
        new DataBlockedFrame(-1);
    }

    public function testEncode(): void
    {
        $frame = new DataBlockedFrame(1000);
        $encoded = $frame->encode();
        $this->assertNotEmpty($encoded);
    }

    public function testDecode(): void
    {
        $frame = new DataBlockedFrame(1000);
        $encoded = $frame->encode();
        
        [$decodedFrame, $consumed] = DataBlockedFrame::decode($encoded);
        $this->assertInstanceOf(DataBlockedFrame::class, $decodedFrame);
        $this->assertEquals(1000, $decodedFrame->getDataLimit());
        $this->assertGreaterThan(0, $consumed);
    }

    public function testDecodeWithInsufficientData(): void
    {
        $this->expectException(InvalidFrameParameterException::class);
        $this->expectExceptionMessage('数据不足');
        DataBlockedFrame::decode('');
    }

    public function testValidate(): void
    {
        $frame = new DataBlockedFrame(1000);
        $this->assertTrue($frame->validate());
    }

    public function testGetPriority(): void
    {
        $frame = new DataBlockedFrame(1000);
        $this->assertEquals(6, $frame->getPriority());
    }

    public function testRequiresImmediateTransmission(): void
    {
        $frame = new DataBlockedFrame(1000);
        $this->assertTrue($frame->requiresImmediateTransmission());
    }

    public function testGetSize(): void
    {
        $frame = new DataBlockedFrame(1000);
        $this->assertGreaterThan(0, $frame->getSize());
    }

    public function testToString(): void
    {
        $frame = new DataBlockedFrame(1000);
        $this->assertEquals('DATA_BLOCKED(data_limit=1000)', $frame->__toString());
    }
} 