<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\FlowControl\Exception\FlowControlException;
use Tourze\QUIC\FlowControl\Exception\InvalidFlowControlWindowException;
use Tourze\QUIC\FlowControl\FlowControlWindow;

class FlowControlWindowTest extends TestCase
{
    public function testConstruction(): void
    {
        $window = new FlowControlWindow(1000);
        $this->assertEquals(1000, $window->getMaxData());
        $this->assertEquals(0, $window->getConsumedData());
        $this->assertEquals(0, $window->getSentData());
        $this->assertFalse($window->isBlocked());
    }

    public function testConstructionWithInvalidMaxData(): void
    {
        $this->expectException(InvalidFlowControlWindowException::class);
        $this->expectExceptionMessage('最大数据量不能为负数');
        new FlowControlWindow(-1);
    }

    public function testConstructionWithNegativeConsumedData(): void
    {
        $this->expectException(InvalidFlowControlWindowException::class);
        $this->expectExceptionMessage('已消费数据量不能为负数');
        new FlowControlWindow(1000, -1);
    }

    public function testConstructionWithNegativeSentData(): void
    {
        $this->expectException(InvalidFlowControlWindowException::class);
        $this->expectExceptionMessage('已发送数据量不能为负数');
        new FlowControlWindow(1000, 0, -1);
    }

    public function testGetAvailableSendWindow(): void
    {
        $window = new FlowControlWindow(1000, 0, 300);
        $this->assertEquals(700, $window->getAvailableSendWindow());
    }

    public function testGetAvailableReceiveWindow(): void
    {
        $window = new FlowControlWindow(1000, 400);
        $this->assertEquals(600, $window->getAvailableReceiveWindow());
    }

    public function testCanSend(): void
    {
        $window = new FlowControlWindow(1000, 0, 300);
        $this->assertTrue($window->canSend(500));
        $this->assertTrue($window->canSend(700));
        $this->assertFalse($window->canSend(800));
    }

    public function testCanReceive(): void
    {
        $window = new FlowControlWindow(1000, 400);
        $this->assertTrue($window->canReceive(500));
        $this->assertTrue($window->canReceive(600));
        $this->assertFalse($window->canReceive(700));
    }

    public function testConsumeSendWindow(): void
    {
        $window = new FlowControlWindow(1000);
        $window->consumeSendWindow(300);
        $this->assertEquals(300, $window->getSentData());
        $this->assertEquals(700, $window->getAvailableSendWindow());
    }

    public function testConsumeSendWindowWithInsufficientSpace(): void
    {
        $window = new FlowControlWindow(1000, 0, 800);
        $this->expectException(FlowControlException::class);
        $this->expectExceptionMessage('发送窗口不足：需要 300 字节，可用 200 字节');
        $window->consumeSendWindow(300);
    }

    public function testConsumeSendWindowWithNegativeBytes(): void
    {
        $window = new FlowControlWindow(1000);
        $this->expectException(InvalidFlowControlWindowException::class);
        $this->expectExceptionMessage('字节数不能为负数');
        $window->consumeSendWindow(-100);
    }

    public function testConsumeReceiveWindow(): void
    {
        $window = new FlowControlWindow(1000);
        $window->consumeReceiveWindow(300);
        $this->assertEquals(300, $window->getConsumedData());
        $this->assertEquals(700, $window->getAvailableReceiveWindow());
    }

    public function testConsumeReceiveWindowWithInsufficientSpace(): void
    {
        $window = new FlowControlWindow(1000, 800);
        $this->expectException(FlowControlException::class);
        $this->expectExceptionMessage('接收窗口不足：需要 300 字节，可用 200 字节');
        $window->consumeReceiveWindow(300);
    }

    public function testConsumeReceiveWindowWithNegativeBytes(): void
    {
        $window = new FlowControlWindow(1000);
        $this->expectException(InvalidFlowControlWindowException::class);
        $this->expectExceptionMessage('字节数不能为负数');
        $window->consumeReceiveWindow(-100);
    }

    public function testUpdateMaxData(): void
    {
        $window = new FlowControlWindow(1000);
        $window->updateMaxData(1500);
        $this->assertEquals(1500, $window->getMaxData());
    }

    public function testUpdateMaxDataWithSmallerValue(): void
    {
        $window = new FlowControlWindow(1000);
        $this->expectException(FlowControlException::class);
        $this->expectExceptionMessage('不能降低最大数据量：当前 1000，新值 800');
        $window->updateMaxData(800);
    }

    public function testBlockedState(): void
    {
        $window = new FlowControlWindow(1000);
        $this->assertFalse($window->isBlocked());
        $this->assertNull($window->getBlockedAt());

        $window->setBlocked();
        $this->assertTrue($window->isBlocked());
        $this->assertEquals(0, $window->getBlockedAt());
    }

    public function testBlockedStateClearOnUpdate(): void
    {
        $window = new FlowControlWindow(1000, 0, 1000);
        $window->setBlocked();
        $this->assertTrue($window->isBlocked());

        $window->updateMaxData(1500);
        $this->assertFalse($window->isBlocked());
    }

    public function testIsExhausted(): void
    {
        $window = new FlowControlWindow(1000);
        $this->assertFalse($window->isExhausted());

        $window->consumeSendWindow(1000);
        $this->assertTrue($window->isExhausted());
    }

    public function testGetUtilization(): void
    {
        $window = new FlowControlWindow(1000);
        $this->assertEquals(0.0, $window->getUtilization());

        $window->consumeSendWindow(500);
        $this->assertEquals(0.5, $window->getUtilization());

        $window->consumeSendWindow(500);
        $this->assertEquals(1.0, $window->getUtilization());
    }

    public function testGetUtilizationWithZeroMaxData(): void
    {
        $window = new FlowControlWindow(0);
        $this->assertEquals(0.0, $window->getUtilization());
    }

    public function testResetSentData(): void
    {
        $window = new FlowControlWindow(1000);
        $window->consumeSendWindow(500);
        $this->assertEquals(500, $window->getSentData());

        $window->resetSentData(200);
        $this->assertEquals(200, $window->getSentData());

        $window->resetSentData();
        $this->assertEquals(0, $window->getSentData());
    }

    public function testResetSentDataWithNegativeValue(): void
    {
        $window = new FlowControlWindow(1000);
        $this->expectException(InvalidFlowControlWindowException::class);
        $this->expectExceptionMessage('已发送数据量不能为负数');
        $window->resetSentData(-100);
    }

    public function testCreateReceiveWindow(): void
    {
        $window = new FlowControlWindow(1000);
        $receiveWindow = $window->createReceiveWindow(2000);
        
        $this->assertEquals(2000, $receiveWindow->getMaxData());
        $this->assertEquals(0, $receiveWindow->getConsumedData());
        $this->assertEquals(0, $receiveWindow->getSentData());
    }
} 