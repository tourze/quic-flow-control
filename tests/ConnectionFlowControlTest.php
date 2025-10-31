<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Constants;
use Tourze\QUIC\FlowControl\ConnectionFlowControl;
use Tourze\QUIC\FlowControl\Exception\InvalidStreamControllerException;
use Tourze\QUIC\FlowControl\StreamFlowControl;

/**
 * @internal
 */
#[CoversClass(ConnectionFlowControl::class)]
final class ConnectionFlowControlTest extends TestCase
{
    public function testConstruction(): void
    {
        $controller = new ConnectionFlowControl();
        $this->assertEquals(Constants::DEFAULT_MAX_DATA, $controller->getAvailableSendWindow());
        $this->assertEquals(Constants::DEFAULT_MAX_DATA, $controller->getAvailableReceiveWindow());
        $this->assertFalse($controller->isSendBlocked());
        $this->assertFalse($controller->isReceiveBlocked());
    }

    public function testConstructionWithCustomValues(): void
    {
        $controller = new ConnectionFlowControl(1000, 2000);
        $this->assertEquals(1000, $controller->getAvailableSendWindow());
        $this->assertEquals(2000, $controller->getAvailableReceiveWindow());
    }

    public function testRegisterAndGetStreamController(): void
    {
        $controller = new ConnectionFlowControl();
        $streamController = new StreamFlowControl(1);

        $controller->registerStream($streamController);
        $this->assertSame($streamController, $controller->getStreamController(1));
        $this->assertNull($controller->getStreamController(2));
    }

    public function testUnregisterStream(): void
    {
        $controller = new ConnectionFlowControl();
        $streamController = new StreamFlowControl(1);

        $controller->registerStream($streamController);
        $this->assertNotNull($controller->getStreamController(1));

        $controller->unregisterStream(1);
        $this->assertNull($controller->getStreamController(1));
    }

    public function testCanSend(): void
    {
        $controller = new ConnectionFlowControl(1000);
        $this->assertTrue($controller->canSend(500));
        $this->assertTrue($controller->canSend(1000));
        $this->assertFalse($controller->canSend(1001));
    }

    public function testCanReceive(): void
    {
        $controller = new ConnectionFlowControl(1000, 1000);
        $this->assertTrue($controller->canReceive(500));
        $this->assertTrue($controller->canReceive(1000));
        $this->assertFalse($controller->canReceive(1001));
    }

    public function testCanStreamSend(): void
    {
        $controller = new ConnectionFlowControl(1000);
        $streamController = new StreamFlowControl(1, 500);
        $controller->registerStream($streamController);

        $this->assertTrue($controller->canStreamSend(1, 400));
        $this->assertFalse($controller->canStreamSend(1, 600)); // 超过流限制
        $this->assertFalse($controller->canStreamSend(2, 100)); // 流不存在
    }

    public function testSendWithUnregisteredStream(): void
    {
        $controller = new ConnectionFlowControl(1000);

        $this->expectException(InvalidStreamControllerException::class);
        $this->expectExceptionMessage('流 1 未注册');
        $controller->send(1, 100);
    }

    public function testSendWithZeroBytes(): void
    {
        $controller = new ConnectionFlowControl(1000);
        $streamController = new StreamFlowControl(1, 500);
        $controller->registerStream($streamController);

        $this->assertTrue($controller->send(1, 0));
        $this->assertEquals(1000, $controller->getAvailableSendWindow());
    }

    public function testSendSuccess(): void
    {
        $controller = new ConnectionFlowControl(1000);
        $streamController = new StreamFlowControl(1, 500);
        $controller->registerStream($streamController);

        $this->assertTrue($controller->send(1, 300));
        $this->assertEquals(700, $controller->getAvailableSendWindow());
        $this->assertEquals(200, $streamController->getAvailableSendWindow());
    }

    public function testSendConnectionBlocked(): void
    {
        $controller = new ConnectionFlowControl(200);
        $streamController = new StreamFlowControl(1, 500);
        $controller->registerStream($streamController);

        $this->assertFalse($controller->send(1, 300));
        $this->assertTrue($controller->isSendBlocked());
    }

    public function testReceiveWithUnregisteredStream(): void
    {
        $controller = new ConnectionFlowControl(1000, 1000);

        $this->expectException(InvalidStreamControllerException::class);
        $this->expectExceptionMessage('流 1 未注册');
        $controller->receive(1, 100);
    }

    public function testReceiveSuccess(): void
    {
        $controller = new ConnectionFlowControl(1000, 1000);
        $streamController = new StreamFlowControl(1, 500, 500);
        $controller->registerStream($streamController);

        $this->assertTrue($controller->receive(1, 300));
        $this->assertEquals(700, $controller->getAvailableReceiveWindow());
        $this->assertEquals(200, $streamController->getAvailableReceiveWindow());
    }

    public function testUpdateSendWindow(): void
    {
        $controller = new ConnectionFlowControl(1000);
        $streamController = new StreamFlowControl(1, 2000); // 给流一个更大的窗口
        $controller->registerStream($streamController);

        // 发送所有可用数据让连接级窗口耗尽
        $controller->send(1, 1000);
        $this->assertTrue($controller->isSendBlocked());

        // 更新连接级发送窗口
        $controller->updateSendWindow(2000);
        $this->assertFalse($controller->isSendBlocked());
        $this->assertEquals(1000, $controller->getAvailableSendWindow()); // 2000 - 1000 = 1000
    }

    public function testUpdateReceiveWindow(): void
    {
        $controller = new ConnectionFlowControl(1000, 1000);
        $controller->updateReceiveWindow(2000);
        $this->assertEquals(2000, $controller->getAvailableReceiveWindow());
    }

    public function testShouldSendMaxData(): void
    {
        $controller = new ConnectionFlowControl(1000, 1000);
        $this->assertFalse($controller->shouldSendMaxData());

        // 模拟接收大量数据，超过50%阈值
        $streamController = new StreamFlowControl(1, 500, 2000); // 给流一个更大的接收窗口
        $controller->registerStream($streamController);
        $controller->receive(1, 600); // 接收60%的数据 (600/1000 = 0.6 > 0.5)
        $this->assertTrue($controller->shouldSendMaxData());
    }

    public function testGetNextMaxData(): void
    {
        $controller = new ConnectionFlowControl(1000, 1000);
        $nextMaxData = $controller->getNextMaxData();
        $this->assertGreaterThan(1000, $nextMaxData);
    }

    public function testReset(): void
    {
        $controller = new ConnectionFlowControl(1000);
        $streamController = new StreamFlowControl(1, 500);
        $controller->registerStream($streamController);
        $controller->send(1, 300);

        $controller->reset();
        $this->assertFalse($controller->isSendBlocked());
        $this->assertFalse($controller->isReceiveBlocked());
        $this->assertEquals(0, $controller->getSendWindow()->getSentData());
    }

    public function testGetConnectionStats(): void
    {
        $controller = new ConnectionFlowControl(1000, 2000);
        $stats = $controller->getConnectionStats();

        $this->assertArrayHasKey('connection', $stats);
        $this->assertArrayHasKey('streams', $stats);
        $this->assertArrayHasKey('summary', $stats);

        $this->assertArrayHasKey('send_window', $stats['connection']);
        $this->assertArrayHasKey('receive_window', $stats['connection']);
        $this->assertEquals(0, $stats['summary']['total_streams']);
    }

    public function testRegisterStream(): void
    {
        $controller = new ConnectionFlowControl();
        $streamController = new StreamFlowControl(1);

        $controller->registerStream($streamController);

        $this->assertSame($streamController, $controller->getStreamController(1));
        $this->assertNull($controller->getStreamController(2));
    }
}
