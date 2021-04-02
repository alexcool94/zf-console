<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Console;

use DomainException;
use Exception;
use Laminas\Console\Adapter\AdapterInterface;
use ReflectionClass;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use ZF\Console\ExceptionHandler;

/**
 * @group 9
 */
class ExceptionHandlerTest extends TestCase
{
    public function setUp(): void
    {
        $this->console = $this->getMockBuilder(AdapterInterface::class)->getMock();
        $this->handler = new ExceptionHandler($this->console);
    }

    /**
     * @throws Exception
     */
    public function testMessageTemplateIsPopulatedByDefault(): void
    {
        $reflectedClass = new ReflectionClass($this->handler);

        $reflectedProperty = $reflectedClass->getProperty('messageTemplate');
        $reflectedProperty->setAccessible(true);

        $actualValue = $reflectedProperty->getValue($this->handler);

        self::assertStringContainsString(':className', $actualValue);
        self::assertStringContainsString(':message', $actualValue);
    }

    /**
     * @throws Exception
     */
    public function testCanSetCustomMessageTemplate(): void
    {
        $this->handler->setMessageTemplate('testing');

        $reflectedClass = new ReflectionClass($this->handler);
        $reflectedProperty = $reflectedClass->getProperty('messageTemplate');
        $reflectedProperty->setAccessible(true);

        self::assertEquals('testing', $reflectedProperty->getValue($this->handler));
    }

    public function testCreateMessageFillsExpectedVariablesForExceptionWithoutPrevious()
    {
        $this->handler->setMessageTemplate(
            "ClassName: :className\nMessage: :message\nCode: :code\nFile: :file\n"
            . "Line: :line\nStack: :stack\nPrevious: :previous"
        );
        $exception = new Exception('testing', 127);
        $message = $this->handler->createMessage($exception);
        $this->assertStringContainsString('ClassName: ' . get_class($exception), $message);
        $this->assertStringContainsString('Message: ' . $exception->getMessage(), $message);
        $this->assertStringContainsString('Code: ' . $exception->getCode(), $message);
        $this->assertStringContainsString('File: ' . $exception->getFile(), $message);
        $this->assertStringContainsString('Line: ' . $exception->getLine(), $message);
        $this->assertStringContainsString('Stack: ' . $exception->getTraceAsString(), $message);
        $this->assertStringNotContainsString('Previous: :previous', $message);
    }

    public function testCreateMessageFillsExpectedVariablesForExceptionWithPrevious()
    {
        $this->handler->setMessageTemplate(
            "ClassName: :className\nMessage: :message\nCode: :code\nPrevious: :previous"
        );

        $first = new DomainException('initial exception', 1);
        $second = new RuntimeException('second exception', 2, $first);
        $third = new Exception('thrown exception', 3, $second);
        $message = $this->handler->createMessage($third);

        foreach ([$first, $second, $third] as $exception) {
            $this->assertStringContainsString('ClassName: ' . get_class($exception), $message);
            $this->assertStringContainsString('Message: ' . $exception->getMessage(), $message);
            $this->assertStringContainsString('Code: ' . $exception->getCode(), $message);
        }
        $this->assertStringNotContainsString('Previous: :previous', $message);
    }
}
