<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace ZFTest\Console;

use Exception;
use Laminas\Console\Adapter\AdapterInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use ZF\Console\Application;
use ZF\Console\Dispatcher;

class ApplicationTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $console;

    public function setUp(): void
    {
        $this->version = uniqid();
        $this->console = $this->getMockBuilder(AdapterInterface::class)->getMock();
        $this->dispatcher = new Dispatcher();
        $this->application = new Application(
            'ZFConsoleApplication',
            $this->version,
            $this->getRoutes(),
            $this->console,
            $this->dispatcher
        );
        $this->application->setDebug(true);
    }

    public function getRoutes()
    {
        return [
            [
                'name'  => 'self-update',
                'route' => 'self-update',
                'description' => 'When executed via the Phar file, performs a self-update by querying
        the package repository. If successful, it will report the new version.',
                'short_description' => 'Perform a self-update of the script',
            ],
            [
                'name' => 'build',
                'route' => 'build <package> [--target=]',
                'description' => 'Build a package, using <package> as the package filename, and --target
        as the application directory to be packaged.',
                'short_description' => 'Build a package',
                'options_descriptions' => [
                    '<package>' => 'Package filename to build',
                    '--target'  => 'Name of the application directory to package; '
                                .  'defaults to current working directory',
                ],
                'defaults' => [
                    'target' => getcwd(), // default to current working directory
                ],
            ],
        ];
    }

    public function testRunWithEmptyArgumentsShowsUsageMessage()
    {
        $this->console->expects($this->atLeastOnce())
            ->method('colorize');

        $this->console->expects($this->atLeastOnce())
            ->method('writeLine');

        $this->console->expects($this->atLeastOnce())
            ->method('write');

        $this->application->run([]);
    }

    public function testRunThatDoesNotMatchRoutesDisplaysUnmatchedRouteMessage()
    {
        $this->console->expects($this->at(4))
            ->method('write')
            ->with($this->stringContains('Unrecognized command:'));

        $this->application->run(['should', 'not', 'match']);
    }

    public function testRunThatMatchesInvokesCallableForMatchedRoute()
    {
        $phpunit = $this;
        $this->dispatcher->map('self-update', function ($route, $console) use ($phpunit) {
            $phpunit->assertEquals('self-update', $route->getName());
            return 2;
        });

        $this->assertEquals(2, $this->application->run(['self-update']));
    }

    public function testRunThatMatchesFirstArgumentToARouteButFailsRoutingDisplaysHelpMessageForRoute()
    {
        $this->console->expects($writeLineSpy = $this->any())
            ->method('writeLine');
        $this->console->expects($writeSpy = $this->any())
            ->method('write');
        $return = $this->application->run(['build']);

        $this->assertEquals(1, $return);
    }

    /**
     * @group 9
     */
    public function testComposesExceptionHandlerByDefault()
    {
        $handler = $this->application->getExceptionHandler();
        $this->assertInstanceOf('ZF\Console\ExceptionHandler', $handler);
    }

    /**
     * @group 9
     */
    public function testAllowsSettingCustomExceptionHandler()
    {
        $handler = function ($e) {
        };
        $this->application->setExceptionHandler($handler);
        $this->assertSame($handler, $this->application->getExceptionHandler());
    }

    /**
     * @group 9
     *
     * @throws Exception
     */
    public function testDebugModeIsDisabledByDefault(): void
    {
        $application = new Application(
            'ZFConsoleApplication',
            $this->version,
            $this->getRoutes(),
            $this->console,
            $this->dispatcher
        );

        $reflectedClass = new ReflectionClass($application);

        $reflectedProperty = $reflectedClass->getProperty('debug');
        $reflectedProperty->setAccessible(true);

        self::assertEquals(false, $reflectedProperty->getValue($application));
    }

    /**
     * @group 9
     */
    public function testDebugModeIsMutable()
    {
        $application = new Application(
            'ZFConsoleApplication',
            $this->version,
            $this->getRoutes(),
            $this->console,
            $this->dispatcher
        );
        $application->setDebug(true);

        $reflectedClass = new ReflectionClass($application);

        $reflectedProperty = $reflectedClass->getProperty('debug');
        $reflectedProperty->setAccessible(true);

        self::assertEquals(true, $reflectedProperty->getValue($application));
    }

    /**
     * @group 9
     */
    public function testExceptionHandlerIsNotInitializedWhenDebugModeIsEnabled()
    {
        $this->markTestSkipped(
            'PHP does not allow introspection of the exception handler stack, '
            . 'making it impossible to test if the exception handler was specified'
        );
    }

    /**
     * @group 7
     */
    public function testCanInstantiateWithoutADispatcher()
    {
        $application = new Application(
            'ZFConsoleApplication',
            $this->version,
            $this->getRoutes(),
            $this->console
        );
        $this->assertInstanceOf('ZF\Console\Application', $application);
        $this->assertInstanceOf('ZF\Console\Dispatcher', $application->getDispatcher());
    }

    /**
     * @group 7
     */
    public function testCanPassHandlersToDefaultDispatcherViaRouteConfiguration()
    {
        $phpunit = $this;

        $routes = [
            [
                'name'  => 'test',
                'route' => 'test',
                'description' => 'Test handler capabilities',
                'short_description' => 'Test handler capabilities',
                'handler' => function ($route, $console) use ($phpunit) {
                    $phpunit->assertEquals('test', $route->getName());
                    return 2;
                },
            ],
        ];
        $application = new Application(
            'ZFConsoleApplication',
            $this->version,
            $routes,
            $this->console
        );
        $this->assertEquals(2, $application->run(['test']));
    }

    /**
     * @group 7
     */
    public function testHandlersConfiguredViaRoutesDoNotOverwriteThoseAlreadyInDispatcher()
    {
        $phpunit = $this;

        $dispatcher = new Dispatcher();
        $dispatcher->map('test', function ($route, $console) use ($phpunit) {
            $phpunit->assertEquals('test', $route->getName());
            return 2;
        });

        $routes = [
            [
                'name'  => 'test',
                'route' => 'test',
                'description' => 'Test handler capabilities',
                'short_description' => 'Test handler capabilities',
                'handler' => function ($route, $console) use ($phpunit) {
                    $phpunit->fail('Handler from route configuration was invoked when it should not be');
                    return 3;
                },
            ],
        ];
        $application = new Application(
            'ZFConsoleApplication',
            $this->version,
            $routes,
            $this->console,
            $dispatcher
        );
        $this->assertEquals(2, $application->run(['test']));
    }

    /**
     * @group 18
     */
    public function testCanRemoveAPreviouslyRegisteredRoute()
    {
        $r = new ReflectionProperty($this->application, 'routeCollection');
        $r->setAccessible(true);
        $collection = $r->getValue($this->application);

        $this->assertTrue($collection->hasRoute('build'));

        $this->application->removeRoute('build');

        $this->assertFalse($collection->hasRoute('build'));
    }

    /**
     * @group 18
     */
    public function testAttemptingToRemoveAnUnregisteredRouteRaisesAnException()
    {
        $this->expectException('DomainException');
        $this->expectExceptionMessage('registered');
        $this->application->removeRoute('does-not-exist');
    }

    public function testCanSetBannerToNull()
    {
        $application = new Application('test-name', 'foo-version', []);
        $application->setBanner(null);

        ob_start();

        $application->run();

        $buffer = ob_get_clean();

        $this->assertStringNotContainsString('test-name', $buffer);
        $this->assertStringNotContainsString('foo-version', $buffer);
    }

    public function testCanDisableBannerOnlyForCommands()
    {
        $application = new Application('test-app', 'test-version', [
            [
                'name'  => 'test',
                'route' => 'test',
                'description' => 'Test handler capabilities',
                'short_description' => 'Test handler capabilities',
                'handler' => function ($route, AdapterInterface $console) {
                    $console->write('test output');
                },
            ],
        ]);
        $application->setBannerDisabledForUserCommands();

        ob_start();

        $application->run([ 'test' ]);

        $buffer = ob_get_clean();

        $this->assertSame('test output', $buffer);
    }
}
