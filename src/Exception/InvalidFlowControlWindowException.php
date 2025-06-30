<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Exception;

/**
 * 流量控制窗口无效异常
 *
 * 当流量控制窗口参数无效时抛出此异常
 */
class InvalidFlowControlWindowException extends FlowControlException {}
