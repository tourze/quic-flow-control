<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\QUIC\FlowControl\Exception\FlowControlException;
use Tourze\QUIC\FlowControl\Exception\InvalidStreamControllerException;

/**
 * @internal
 */
#[CoversClass(InvalidStreamControllerException::class)]
final class InvalidStreamControllerExceptionTest extends AbstractExceptionTestCase
{
    public function testInheritance(): void
    {
        $exception = new InvalidStreamControllerException();
        $this->assertInstanceOf(FlowControlException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testConstructionWithMessage(): void
    {
        $message = '无效流控制器';
        $exception = new InvalidStreamControllerException($message);
        $this->assertEquals($message, $exception->getMessage());
    }

    public function testConstructionWithMessageAndCode(): void
    {
        $message = '无效流控制器';
        $code = 1004;
        $exception = new InvalidStreamControllerException($message, $code);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testConstructionWithPrevious(): void
    {
        $previous = new \Exception('原始异常');
        $exception = new InvalidStreamControllerException('无效流控制器', 0, $previous);
        $this->assertEquals($previous, $exception->getPrevious());
    }
}
