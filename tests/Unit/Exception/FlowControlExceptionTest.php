<?php

declare(strict_types=1);

namespace Tourze\QUIC\FlowControl\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\QUIC\FlowControl\Exception\FlowControlException;

class FlowControlExceptionTest extends TestCase
{
    public function testInheritance(): void
    {
        $exception = new FlowControlException();
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testConstructionWithMessage(): void
    {
        $message = '流量控制错误';
        $exception = new FlowControlException($message);
        $this->assertEquals($message, $exception->getMessage());
    }

    public function testConstructionWithMessageAndCode(): void
    {
        $message = '流量控制错误';
        $code = 1001;
        $exception = new FlowControlException($message, $code);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testConstructionWithPrevious(): void
    {
        $previous = new \Exception('原始异常');
        $exception = new FlowControlException('流量控制错误', 0, $previous);
        $this->assertEquals($previous, $exception->getPrevious());
    }
}