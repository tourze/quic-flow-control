<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\QUIC\Core\Constants;
use Tourze\QUIC\FlowControl\FlowControlManager;

/**
 * @internal
 */
#[CoversClass(FlowControlManager::class)]
final class FlowControlManagerTest extends TestCase
{
    private FlowControlManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new FlowControlManager();
    }

    public function testConstruction(): void
    {
        $manager = new FlowControlManager();
        $this->assertInstanceOf(FlowControlManager::class, $manager);
    }

    public function testCreateStream(): void
    {
        $streamController = $this->manager->createStream(1);

        $this->assertEquals(1, $streamController->getStreamId());
        $this->assertEquals(Constants::DEFAULT_MAX_STREAM_DATA, $streamController->getAvailableSendWindow());
        $this->assertEquals(Constants::DEFAULT_MAX_STREAM_DATA, $streamController->getAvailableReceiveWindow());
    }

    public function testCloseStream(): void
    {
        $streamController = $this->manager->createStream(1);
        $this->assertNotNull($this->manager->getStreamController(1));

        $this->manager->closeStream(1);
        $this->assertNull($this->manager->getStreamController(1));
    }

    public function testCanSendData(): void
    {
        $this->manager->createStream(1);

        $this->assertTrue($this->manager->canSendData(1, 1000));
        $this->assertFalse($this->manager->canSendData(1, Constants::DEFAULT_MAX_DATA + 1));
        $this->assertFalse($this->manager->canSendData(999, 100)); // 流不存在
    }

    public function testSendData(): void
    {
        $this->manager->createStream(1);

        $this->assertTrue($this->manager->sendData(1, 1000));
        $this->assertEquals(Constants::DEFAULT_MAX_DATA - 1000, $this->manager->getAvailableConnectionSendWindow());
        $this->assertEquals(Constants::DEFAULT_MAX_STREAM_DATA - 1000, $this->manager->getAvailableStreamSendWindow(1));
    }

    public function testReceiveData(): void
    {
        $this->manager->createStream(1);

        $this->assertTrue($this->manager->receiveData(1, 1000));

        // 验证是否生成了MAX_DATA帧（如果达到阈值）
        $frames = $this->manager->getPendingFrames();
        // 由于接收了较少数据，可能不会立即生成帧
        $this->assertIsArray($frames);
    }

    public function testHandleMaxDataFrame(): void
    {
        // 创建一个有足够大窗口的流控制管理器
        $manager = new FlowControlManager(1000, 1000, 2000, 2000);
        $manager->createStream(1);

        // 发送至连接级窗口极限，确保连接被阻塞
        $manager->sendData(1, 1000);

        $this->assertTrue($manager->isConnectionBlocked());

        // 处理MAX_DATA帧，增加连接级窗口
        $manager->handleMaxDataFrame(2000);
        $this->assertFalse($manager->isConnectionBlocked());
    }

    public function testHandleMaxStreamDataFrame(): void
    {
        // 创建一个有合适窗口的流控制管理器
        $manager = new FlowControlManager(10000, 10000, 1000, 1000);
        $manager->createStream(1);

        // 发送至流级窗口极限，确保流被阻塞
        $manager->sendData(1, 1000);

        $this->assertTrue($manager->isStreamBlocked(1));

        // 处理MAX_STREAM_DATA帧，增加流级窗口
        $manager->handleMaxStreamDataFrame(1, 2000);
        $this->assertFalse($manager->isStreamBlocked(1));
    }

    public function testHandleDataBlockedFrame(): void
    {
        // 测试handleDataBlockedFrame方法被正确调用
        $this->manager->handleDataBlockedFrame(1000);

        // 由于默认状态下可能不满足发送MAX_DATA的条件，
        // 我们主要验证方法调用没有异常
        $frames = $this->manager->getPendingFrames();
        $this->assertIsArray($frames);
    }

    public function testHandleStreamDataBlockedFrame(): void
    {
        $this->manager->createStream(1);

        // 测试handleStreamDataBlockedFrame方法被正确调用
        $this->manager->handleStreamDataBlockedFrame(1, 1000);

        // 验证方法调用没有异常
        $frames = $this->manager->getPendingFrames();
        $this->assertIsArray($frames);
    }

    public function testReset(): void
    {
        $this->manager->createStream(1);
        $this->manager->sendData(1, 1000);

        // 验证发送窗口已经被消费
        $this->assertEquals(Constants::DEFAULT_MAX_DATA - 1000, $this->manager->getAvailableConnectionSendWindow());

        $this->manager->reset();

        // 验证重置后发送窗口恢复
        $this->assertEquals(Constants::DEFAULT_MAX_DATA, $this->manager->getAvailableConnectionSendWindow());
        $this->assertEmpty($this->manager->getPendingFrames());
    }
}
