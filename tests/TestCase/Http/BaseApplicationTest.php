<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.5.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Test\TestCase\Http;

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\Routing\RouteBuilder;
use Cake\Routing\RouteCollection;
use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use TestPlugin\Plugin as TestPlugin;

/**
 * Base application test.
 */
class BaseApplicationTest extends TestCase
{
    /**
     * Setup
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        static::setAppNamespace();
        $this->path = dirname(dirname(__DIR__));
    }

    public function tearDown()
    {
        parent::tearDown();
        Plugin::unload();
    }

    /**
     * Integration test for a simple controller.
     *
     * @return void
     */
    public function testInvoke()
    {
        $next = function ($req, $res) {
            return $res;
        };
        $response = new Response();
        $request = ServerRequestFactory::fromGlobals(['REQUEST_URI' => '/cakes']);
        $request = $request->withAttribute('params', [
            'controller' => 'Cakes',
            'action' => 'index',
            'plugin' => null,
            'pass' => []
        ]);

        $app = $this->getMockForAbstractClass('Cake\Http\BaseApplication', [$this->path]);
        $result = $app($request, $response, $next);
        $this->assertInstanceOf('Psr\Http\Message\ResponseInterface', $result);
        $this->assertEquals('Hello Jane', '' . $result->getBody());
    }

    public function testAddPluginUnknownClass()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be found');
        $app = $this->getMockForAbstractClass(BaseApplication::class, [$this->path]);
        $app->addPlugin('SomethingBad');
    }

    public function testAddPluginBadClass()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not implement');
        $app = $this->getMockForAbstractClass(BaseApplication::class, [$this->path]);
        $app->addPlugin(__CLASS__);
    }

    public function testAddPluginValidShortName()
    {
        $app = $this->getMockForAbstractClass(BaseApplication::class, [$this->path]);
        $app->addPlugin('TestPlugin');

        $this->assertCount(1, $app->getPlugins());
        $this->assertTrue($app->getPlugins()->has('TestPlugin'));

        $app->addPlugin('Company/TestPluginThree');
        $this->assertCount(2, $app->getPlugins());
        $this->assertTrue($app->getPlugins()->has('Company/TestPluginThree'));
    }

    public function testAddPluginValid()
    {
        $app = $this->getMockForAbstractClass(BaseApplication::class, [$this->path]);
        $app->addPlugin(TestPlugin::class);

        $this->assertCount(1, $app->getPlugins());
        $this->assertTrue($app->getPlugins()->has('TestPlugin'));
    }

    public function testPluginMiddleware()
    {
        $start = new MiddlewareQueue();
        $app = $this->getMockForAbstractClass(
            BaseApplication::class,
            [$this->path]
        );
        $app->addPlugin(TestPlugin::class);

        $after = $app->pluginMiddleware($start);
        $this->assertSame($start, $after);
        $this->assertCount(1, $after);
    }

    public function testPluginRoutes()
    {
        $collection = new RouteCollection();
        $routes = new RouteBuilder($collection, '/');
        $app = $this->getMockForAbstractClass(
            BaseApplication::class,
            [$this->path]
        );
        $app->addPlugin(TestPlugin::class);

        $result = $app->pluginRoutes($routes);
        $this->assertSame($routes, $result);
        $url = [
            'plugin' => 'TestPlugin',
            'controller' => 'TestPlugin',
            'action' => 'index',
            '_method' => 'GET'
        ];
        $this->assertNotEmpty($collection->match($url, []));
    }

    public function testPluginBootstrap()
    {
        $app = $this->getMockForAbstractClass(
            BaseApplication::class,
            [$this->path]
        );
        $app->addPlugin(TestPlugin::class);

        $this->assertFalse(Configure::check('PluginTest.test_plugin.bootstrap'));
        $this->assertNull($app->pluginBootstrap());
        $this->assertTrue(Configure::check('PluginTest.test_plugin.bootstrap'));
    }
}