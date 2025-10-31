<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\QUIC\FlowControl\Exception\FlowControlException;
use Tourze\QUIC\FlowControl\Exception\InvalidFlowControlWindowException;

/**
 * @internal
 */
#[CoversClass(InvalidFlowControlWindowException::class)]
final class InvalidFlowControlWindowExceptionTest extends AbstractExceptionTestCase
{
    public function testInheritance(): void
    {
        $exception = new InvalidFlowControlWindowException();
        $this->assertInstanceOf(FlowControlException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testConstructionWithMessage(): void
    {
        $message = '流量控制窗口无效';
        $exception = new InvalidFlowControlWindowException($message);
        $this->assertEquals($message, $exception->getMessage());
    }

    public function testConstructionWithMessageAndCode(): void
    {
        $message = '流量控制窗口无效';
        $code = 1002;
        $exception = new InvalidFlowControlWindowException($message, $code);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testConstructionWithPrevious(): void
    {
        $previous = new \Exception('原始异常');
        $exception = new InvalidFlowControlWindowException('流量控制窗口无效', 0, $previous);
        $this->assertEquals($previous, $exception->getPrevious());
    }
}
