<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Tests;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Constants;
use Tourze\QUIC\FlowControl\StreamFlowController;

class StreamFlowControllerTest extends TestCase
{
    public function testConstruction(): void
    {
        $controller = new StreamFlowController(1);
        $this->assertEquals(1, $controller->getStreamId());
        $this->assertEquals(Constants::DEFAULT_MAX_STREAM_DATA, $controller->getAvailableSendWindow());
        $this->assertEquals(Constants::DEFAULT_MAX_STREAM_DATA, $controller->getAvailableReceiveWindow());
    }

    public function testConstructionWithCustomValues(): void
    {
        $controller = new StreamFlowController(1, 1000, 2000);
        $this->assertEquals(1, $controller->getStreamId());
        $this->assertEquals(1000, $controller->getAvailableSendWindow());
        $this->assertEquals(2000, $controller->getAvailableReceiveWindow());
    }

    public function testConstructionWithNegativeStreamId(): void
    {
        $this->expectException(\Tourze\QUIC\FlowControl\Exception\InvalidStreamControllerException::class);
        $this->expectExceptionMessage('流ID不能为负数');
        new StreamFlowController(-1);
    }

    public function testCanSend(): void
    {
        $controller = new StreamFlowController(1, 1000, 1000);
        $this->assertTrue($controller->canSend(500));
        $this->assertTrue($controller->canSend(1000));
        $this->assertFalse($controller->canSend(1001));
    }

    public function testCanReceive(): void
    {
        $controller = new StreamFlowController(1, 1000, 1000);
        $this->assertTrue($controller->canReceive(500));
        $this->assertTrue($controller->canReceive(1000));
        $this->assertFalse($controller->canReceive(1001));
    }

    public function testSend(): void
    {
        $controller = new StreamFlowController(1, 1000, 1000);
        
        $this->assertTrue($controller->send(500));
        $this->assertEquals(500, $controller->getAvailableSendWindow());
        $this->assertFalse($controller->isSendBlocked());
        
        $this->assertTrue($controller->send(500));
        $this->assertEquals(0, $controller->getAvailableSendWindow());
        $this->assertTrue($controller->isSendBlocked());
        
        $this->assertFalse($controller->send(1));
        $this->assertTrue($controller->isSendBlocked());
    }

    public function testSendWithZeroBytes(): void
    {
        $controller = new StreamFlowController(1, 1000, 1000);
        $this->assertTrue($controller->send(0));
        $this->assertEquals(1000, $controller->getAvailableSendWindow());
    }

    public function testReceive(): void
    {
        $controller = new StreamFlowController(1, 1000, 1000);
        
        $this->assertTrue($controller->receive(500));
        $this->assertEquals(500, $controller->getAvailableReceiveWindow());
        $this->assertFalse($controller->isReceiveBlocked());
        
        $this->assertTrue($controller->receive(500));
        $this->assertEquals(0, $controller->getAvailableReceiveWindow());
        $this->assertTrue($controller->isReceiveBlocked());
        
        $this->assertFalse($controller->receive(1));
        $this->assertTrue($controller->isReceiveBlocked());
    }

    public function testReceiveWithZeroBytes(): void
    {
        $controller = new StreamFlowController(1, 1000, 1000);
        $this->assertTrue($controller->receive(0));
        $this->assertEquals(1000, $controller->getAvailableReceiveWindow());
    }

    public function testUpdateSendWindow(): void
    {
        $controller = new StreamFlowController(1, 1000, 1000);
        $controller->send(1000);
        $this->assertTrue($controller->isSendBlocked());
        
        $controller->updateSendWindow(2000);
        $this->assertFalse($controller->isSendBlocked());
        $this->assertEquals(1000, $controller->getAvailableSendWindow());
    }

    public function testUpdateReceiveWindow(): void
    {
        $controller = new StreamFlowController(1, 1000, 1000);
        $controller->receive(1000);
        $this->assertTrue($controller->isReceiveBlocked());
        
        $controller->updateReceiveWindow(2000);
        $this->assertFalse($controller->isReceiveBlocked());
        $this->assertEquals(1000, $controller->getAvailableReceiveWindow());
    }

    public function testGetStreamDataBlockedOffset(): void
    {
        $controller = new StreamFlowController(1, 1000, 1000);
        $this->assertNull($controller->getStreamDataBlockedOffset());
        
        $controller->send(1000);
        $this->assertNotNull($controller->getStreamDataBlockedOffset());
        $this->assertEquals(1000, $controller->getStreamDataBlockedOffset());
    }

    public function testShouldSendMaxStreamData(): void
    {
        $controller = new StreamFlowController(1, 1000, 1000);
        $this->assertFalse($controller->shouldSendMaxStreamData());
        
        // 接收超过50%的数据
        $controller->receive(600);
        $this->assertTrue($controller->shouldSendMaxStreamData());
    }

    public function testGetNextMaxStreamData(): void
    {
        $controller = new StreamFlowController(1, 1000, 1000);
        $controller->receive(500);
        
        $nextMaxData = $controller->getNextMaxStreamData();
        $this->assertGreaterThan(1000, $nextMaxData);
        
        // 计算期望值：currentMax(1000) + max(DEFAULT_MAX_STREAM_DATA, remaining_space)
        // remaining_space = 1000 - 500 = 500
        // 所以：1000 + max(262144, 500) = 1000 + 262144 = 263144
        $expectedValue = 1000 + max(Constants::DEFAULT_MAX_STREAM_DATA, 500);
        $this->assertEquals($expectedValue, $nextMaxData);
    }

    public function testReset(): void
    {
        $controller = new StreamFlowController(1, 1000, 1000);
        $controller->send(500);
        
        // 验证发送了数据后的状态
        $this->assertEquals(500, $controller->getSendWindow()->getSentData());
        
        $controller->reset();
        $this->assertFalse($controller->isSendBlocked());
        $this->assertFalse($controller->isReceiveBlocked());
        $this->assertEquals(0, $controller->getSendWindow()->getSentData());
    }

    public function testGetStats(): void
    {
        $controller = new StreamFlowController(1, 1000, 2000);
        $controller->send(300);
        $controller->receive(400);
        
        $stats = $controller->getStats();
        
        $this->assertEquals(1, $stats['stream_id']);
        $this->assertEquals(1000, $stats['send_window']['max_data']);
        $this->assertEquals(300, $stats['send_window']['sent_data']);
        $this->assertEquals(700, $stats['send_window']['available']);
        $this->assertEquals(0.3, $stats['send_window']['utilization']);
        
        $this->assertEquals(2000, $stats['receive_window']['max_data']);
        $this->assertEquals(400, $stats['receive_window']['consumed_data']);
        $this->assertEquals(1600, $stats['receive_window']['available']);
        $this->assertEquals(0.2, $stats['receive_window']['utilization']);
    }

    public function testGetSendWindow(): void
    {
        $controller = new StreamFlowController(1, 1000, 1000);
        $sendWindow = $controller->getSendWindow();
        $this->assertEquals(1000, $sendWindow->getMaxData());
    }

    public function testGetReceiveWindow(): void
    {
        $controller = new StreamFlowController(1, 1000, 2000);
        $receiveWindow = $controller->getReceiveWindow();
        $this->assertEquals(2000, $receiveWindow->getMaxData());
    }
} 