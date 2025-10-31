<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\QUIC\FlowControl\Exception\FlowControlException;
use Tourze\QUIC\FlowControl\Exception\InvalidFrameParameterException;

/**
 * @internal
 */
#[CoversClass(InvalidFrameParameterException::class)]
final class InvalidFrameParameterExceptionTest extends AbstractExceptionTestCase
{
    public function testInheritance(): void
    {
        $exception = new InvalidFrameParameterException();
        $this->assertInstanceOf(FlowControlException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testConstructionWithMessage(): void
    {
        $message = '无效帧参数';
        $exception = new InvalidFrameParameterException($message);
        $this->assertEquals($message, $exception->getMessage());
    }

    public function testConstructionWithMessageAndCode(): void
    {
        $message = '无效帧参数';
        $code = 1003;
        $exception = new InvalidFrameParameterException($message, $code);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testConstructionWithPrevious(): void
    {
        $previous = new \Exception('原始异常');
        $exception = new InvalidFrameParameterException('无效帧参数', 0, $previous);
        $this->assertEquals($previous, $exception->getPrevious());
    }
}
