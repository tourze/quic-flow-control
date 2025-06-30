<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Tests\Frame;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Enum\FrameType;
use Tourze\QUIC\FlowControl\Frame\MaxDataFrame;
use Tourze\QUIC\FlowControl\Exception\InvalidFrameParameterException;

class MaxDataFrameTest extends TestCase
{
    public function testConstruction(): void
    {
        $frame = new MaxDataFrame(1000);
        $this->assertEquals(FrameType::MAX_DATA, $frame->getType());
        $this->assertEquals(1000, $frame->getMaxData());
    }

    public function testConstructionWithNegativeMaxData(): void
    {
        $this->expectException(InvalidFrameParameterException::class);
        $this->expectExceptionMessage('最大数据量不能为负数');
        new MaxDataFrame(-1);
    }

    public function testEncode(): void
    {
        $frame = new MaxDataFrame(1000);
        $encoded = $frame->encode();
        $this->assertNotEmpty($encoded);
    }

    public function testDecode(): void
    {
        $frame = new MaxDataFrame(1000);
        $encoded = $frame->encode();
        
        [$decodedFrame, $consumed] = MaxDataFrame::decode($encoded);
        $this->assertInstanceOf(MaxDataFrame::class, $decodedFrame);
        $this->assertEquals(1000, $decodedFrame->getMaxData());
        $this->assertGreaterThan(0, $consumed);
    }

    public function testDecodeWithInsufficientData(): void
    {
        $this->expectException(InvalidFrameParameterException::class);
        $this->expectExceptionMessage('数据不足');
        MaxDataFrame::decode('');
    }

    public function testValidate(): void
    {
        $frame = new MaxDataFrame(1000);
        $this->assertTrue($frame->validate());
    }

    public function testGetPriority(): void
    {
        $frame = new MaxDataFrame(1000);
        $this->assertEquals(5, $frame->getPriority());
    }

    public function testRequiresImmediateTransmission(): void
    {
        $frame = new MaxDataFrame(1000);
        $this->assertTrue($frame->requiresImmediateTransmission());
    }

    public function testGetSize(): void
    {
        $frame = new MaxDataFrame(1000);
        $this->assertGreaterThan(0, $frame->getSize());
    }

    public function testToString(): void
    {
        $frame = new MaxDataFrame(1000);
        $this->assertEquals('MAX_DATA(max_data=1000)', $frame->__toString());
    }
} 