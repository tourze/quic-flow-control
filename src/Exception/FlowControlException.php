<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Exception;

/**
 * 流量控制异常基类
 *
 * 当流量控制相关操作失败时抛出此异常
 */
class FlowControlException extends \Exception {}
