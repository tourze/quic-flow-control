<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl;

use Tourze\QUIC\Core\Constants;

/**
 * 流量控制管理器
 *
 * 统一管理连接级和流级流量控制，提供高级接口
 * 参考：https://tools.ietf.org/html/rfc9000#section-4
 */
class FlowControlManager
{
    private ConnectionFlowControl $connectionController;

    /** @var array<int, array{type: string, data: mixed}> 待发送的流控制帧 */
    private array $pendingFrames = [];

    /**
     * @param int $initialMaxData            初始连接级最大数据量
     * @param int $localInitialMaxData       本地初始连接级最大数据量
     * @param int $initialMaxStreamData      初始流级最大数据量
     * @param int $localInitialMaxStreamData 本地初始流级最大数据量
     */
    public function __construct(
        int $initialMaxData = Constants::DEFAULT_MAX_DATA,
        int $localInitialMaxData = Constants::DEFAULT_MAX_DATA,
        private readonly int $initialMaxStreamData = Constants::DEFAULT_MAX_STREAM_DATA,
        private readonly int $localInitialMaxStreamData = Constants::DEFAULT_MAX_STREAM_DATA,
    ) {
        $this->connectionController = new ConnectionFlowControl(
            $initialMaxData,
            $localInitialMaxData
        );
    }

    /**
     * 创建新流的流量控制器
     */
    public function createStream(int $streamId): StreamFlowControl
    {
        $streamController = new StreamFlowControl(
            $streamId,
            $this->initialMaxStreamData,
            $this->localInitialMaxStreamData
        );

        $this->connectionController->registerStream($streamController);

        return $streamController;
    }

    /**
     * 关闭流的流量控制器
     */
    public function closeStream(int $streamId): void
    {
        $this->connectionController->unregisterStream($streamId);
    }

    /**
     * 检查是否可以发送数据
     */
    public function canSendData(int $streamId, int $bytes): bool
    {
        return $this->connectionController->canStreamSend($streamId, $bytes);
    }

    /**
     * 发送数据
     *
     * @param int $streamId 流ID
     * @param int $bytes    要发送的字节数
     *
     * @return bool 是否成功发送
     */
    public function sendData(int $streamId, int $bytes): bool
    {
        $success = $this->connectionController->send($streamId, $bytes);

        // 检查是否需要生成阻塞帧
        $this->checkAndGenerateBlockedFrames();

        return $success;
    }

    /**
     * 接收数据
     *
     * @param int $streamId 流ID
     * @param int $bytes    接收的字节数
     *
     * @return bool 是否成功接收
     */
    public function receiveData(int $streamId, int $bytes): bool
    {
        $success = $this->connectionController->receive($streamId, $bytes);

        // 检查是否需要发送窗口更新
        $this->checkAndGenerateMaxDataFrames();

        return $success;
    }

    /**
     * 处理收到的 MAX_DATA 帧
     */
    public function handleMaxDataFrame(int $maxData): void
    {
        $this->connectionController->updateSendWindow($maxData);
    }

    /**
     * 处理收到的 MAX_STREAM_DATA 帧
     */
    public function handleMaxStreamDataFrame(int $streamId, int $maxStreamData): void
    {
        $streamController = $this->connectionController->getStreamController($streamId);
        if (null !== $streamController) {
            $streamController->updateSendWindow($maxStreamData);
        }
    }

    /**
     * 处理收到的 DATA_BLOCKED 帧
     */
    public function handleDataBlockedFrame(int $offset): void
    {
        // 对方被连接级流量控制阻塞，可能需要发送 MAX_DATA 更新
        if ($this->connectionController->shouldSendMaxData()) {
            $this->addPendingFrame('MAX_DATA', [
                'max_data' => $this->connectionController->getNextMaxData(),
            ]);
        }
    }

    /**
     * 处理收到的 STREAM_DATA_BLOCKED 帧
     */
    public function handleStreamDataBlockedFrame(int $streamId, int $offset): void
    {
        // 对方被流级流量控制阻塞，可能需要发送 MAX_STREAM_DATA 更新
        $streamController = $this->connectionController->getStreamController($streamId);
        if (null !== $streamController && $streamController->shouldSendMaxStreamData()) {
            $this->addPendingFrame('MAX_STREAM_DATA', [
                'stream_id' => $streamId,
                'max_stream_data' => $streamController->getNextMaxStreamData(),
            ]);
        }
    }

    /**
     * 检查并生成阻塞帧
     */
    private function checkAndGenerateBlockedFrames(): void
    {
        $this->checkConnectionLevelBlocking();
        $this->checkStreamLevelBlocking();
    }

    /**
     * 检查连接级阻塞
     */
    private function checkConnectionLevelBlocking(): void
    {
        if (!$this->connectionController->isSendBlocked()) {
            return;
        }

        $offset = $this->connectionController->getDataBlockedOffset();
        if (null !== $offset) {
            $this->addPendingFrame('DATA_BLOCKED', [
                'data_limit' => $offset,
            ]);
        }
    }

    /**
     * 检查流级阻塞
     */
    private function checkStreamLevelBlocking(): void
    {
        foreach ($this->connectionController->getBlockedStreams() as $streamId) {
            $streamController = $this->connectionController->getStreamController($streamId);
            if (null === $streamController) {
                continue;
            }

            $offset = $streamController->getStreamDataBlockedOffset();
            if (null !== $offset) {
                $this->addPendingFrame('STREAM_DATA_BLOCKED', [
                    'stream_id' => $streamId,
                    'stream_data_limit' => $offset,
                ]);
            }
        }
    }

    /**
     * 检查并生成 MAX_DATA 和 MAX_STREAM_DATA 帧
     */
    private function checkAndGenerateMaxDataFrames(): void
    {
        // 检查连接级窗口更新
        if ($this->connectionController->shouldSendMaxData()) {
            $this->addPendingFrame('MAX_DATA', [
                'max_data' => $this->connectionController->getNextMaxData(),
            ]);
        }

        // 检查流级窗口更新
        foreach ($this->connectionController->getStreamsNeedingMaxData() as $streamId => $maxStreamData) {
            $this->addPendingFrame('MAX_STREAM_DATA', [
                'stream_id' => $streamId,
                'max_stream_data' => $maxStreamData,
            ]);
        }
    }

    /**
     * 添加待发送的流控制帧
     * @param array<string, mixed> $data
     */
    private function addPendingFrame(string $type, array $data): void
    {
        $this->pendingFrames[] = [
            'type' => $type,
            'data' => $data,
        ];
    }

    /**
     * 获取并清空待发送的流控制帧
     * @return array<int, array<string, mixed>>
     */
    public function getPendingFrames(): array
    {
        $frames = $this->pendingFrames;
        $this->pendingFrames = [];

        return $frames;
    }

    /**
     * 获取连接控制器
     */
    public function getConnectionController(): ConnectionFlowControl
    {
        return $this->connectionController;
    }

    /**
     * 获取流控制器
     */
    public function getStreamController(int $streamId): ?StreamFlowControl
    {
        return $this->connectionController->getStreamController($streamId);
    }

    /**
     * 获取连接级可用发送窗口
     */
    public function getAvailableConnectionSendWindow(): int
    {
        return $this->connectionController->getAvailableSendWindow();
    }

    /**
     * 获取流级可用发送窗口
     */
    public function getAvailableStreamSendWindow(int $streamId): int
    {
        $streamController = $this->connectionController->getStreamController($streamId);

        return $streamController?->getAvailableSendWindow() ?? 0;
    }

    /**
     * 检查连接是否被阻塞
     */
    public function isConnectionBlocked(): bool
    {
        return $this->connectionController->isSendBlocked();
    }

    /**
     * 检查流是否被阻塞
     */
    public function isStreamBlocked(int $streamId): bool
    {
        $streamController = $this->connectionController->getStreamController($streamId);

        return $streamController?->isSendBlocked() ?? false;
    }

    /**
     * 重置所有流量控制状态
     */
    public function reset(): void
    {
        $this->connectionController->reset();
        $this->pendingFrames = [];
    }

    /**
     * 获取流量控制健康状态
     * @return array<string, mixed>
     */
    public function getHealthStatus(): array
    {
        $stats = $this->connectionController->getConnectionStats();
        $connectionUtilization = $stats['connection']['send_window']['utilization'];
        $blockedStreams = $stats['summary']['blocked_streams'];
        $totalStreams = $stats['summary']['total_streams'];

        // 检查连接利用率
        $utilizationResult = $this->checkConnectionUtilization($connectionUtilization);

        // 检查阻塞流比例
        $blockedStreamsResult = $this->checkBlockedStreamsRatio($blockedStreams, $totalStreams);

        // 合并状态和警告
        $status = 'healthy';
        $warnings = [];

        // 应用连接利用率检查结果
        if ('healthy' !== $utilizationResult['status']) {
            $status = $utilizationResult['status'];
            $warnings = array_merge($warnings, $utilizationResult['warnings']);
        }

        // 应用阻塞流检查结果
        if ('healthy' !== $blockedStreamsResult['status']) {
            if ('critical' === $blockedStreamsResult['status'] || 'critical' !== $status) {
                $status = $blockedStreamsResult['status'];
            }
            $warnings = array_merge($warnings, $blockedStreamsResult['warnings']);
        }

        return [
            'status' => $status,
            'warnings' => $warnings,
            'connection_utilization' => $connectionUtilization,
            'blocked_streams_ratio' => $totalStreams > 0 ? $blockedStreams / $totalStreams : 0,
            'pending_frames' => count($this->pendingFrames),
        ];
    }

    /**
     * 检查连接利用率状态
     * @return array{status: string, warnings: string[]}
     */
    private function checkConnectionUtilization(float $utilization): array
    {
        if ($utilization > 0.9) {
            return [
                'status' => 'critical',
                'warnings' => ['连接级发送窗口接近耗尽'],
            ];
        }
        if ($utilization > 0.7) {
            return [
                'status' => 'warning',
                'warnings' => ['连接级发送窗口使用率较高'],
            ];
        }

        return [
            'status' => 'healthy',
            'warnings' => [],
        ];
    }

    /**
     * 检查阻塞流比例状态
     * @return array{status: string, warnings: string[]}
     */
    private function checkBlockedStreamsRatio(int $blockedStreams, int $totalStreams): array
    {
        if (0 === $totalStreams) {
            return [
                'status' => 'healthy',
                'warnings' => [],
            ];
        }

        $blockedRatio = $blockedStreams / $totalStreams;
        if ($blockedRatio > 0.5) {
            return [
                'status' => 'critical',
                'warnings' => ['超过一半的流被阻塞'],
            ];
        }
        if ($blockedRatio > 0.2) {
            return [
                'status' => 'warning',
                'warnings' => ['较多流被阻塞'],
            ];
        }

        return [
            'status' => 'healthy',
            'warnings' => [],
        ];
    }

    /**
     * 获取完整的流量控制统计信息
     * @return array<string, mixed>
     */
    public function getFullStats(): array
    {
        $connectionStats = $this->connectionController->getConnectionStats();
        $healthStatus = $this->getHealthStatus();

        return [
            'health' => $healthStatus,
            'connection' => $connectionStats['connection'],
            'streams' => $connectionStats['streams'],
            'summary' => $connectionStats['summary'],
            'pending_frames' => $this->pendingFrames,
        ];
    }
}
