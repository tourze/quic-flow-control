<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Exception;

/**
 * 无效帧参数异常
 *
 * 当流量控制帧参数无效时抛出此异常
 */
class InvalidFrameParameterException extends FlowControlException {}
