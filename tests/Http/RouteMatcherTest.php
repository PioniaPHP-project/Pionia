<?php

namespace Http;

use Pionia\Http\Routing\Exception\MethodNotAllowedException;
use Pionia\Http\Routing\Exception\RouteNotFoundException;
use Pionia\Http\Routing\RouteDefinition;
use Pionia\Http\Routing\RouteMatcher;
use Pionia\Http\Routing\RouteTable;
use Pionia\TestSuite\PioniaTestCase;

class RouteMatcherTest extends PioniaTestCase
{
    private function matcher(): RouteMatcher
    {
        $table = new RouteTable();
        $table->add('home', new RouteDefinition('/', ['_controller' => 'Home::index'], [], ['GET']));
        $table->add('api', new RouteDefinition('/api/v1/', ['_controller' => 'Switch::processor'], [], ['POST']));
        $table->add('ping', new RouteDefinition('/api/v1/ping', ['_controller' => 'Switch::ping'], [], ['GET']));
        $table->add('action', new RouteDefinition(
            '/api/v1/{service}/{action}/',
            ['_controller' => 'Switch::processor'],
            ['service' => '[^/]+', 'action' => '[^/]+'],
            ['GET'],
        ));
        $table->add('static', new RouteDefinition(
            '/static/{path}',
            ['_controller' => 'Static::serve'],
            ['path' => '.+'],
            ['GET'],
        ));

        return new RouteMatcher($table);
    }

    public function testMatchesExactRoute(): void
    {
        $match = $this->matcher()->match('/', 'GET');

        $this->assertSame('Home::index', $match['_controller']);
        $this->assertSame('home', $match['_route']);
    }

    public function testMatchesParameterizedRoute(): void
    {
        $match = $this->matcher()->match('/api/v1/auth/list_auth/', 'GET');

        $this->assertSame('auth', $match['service']);
        $this->assertSame('list_auth', $match['action']);
    }

    public function testMatchesGreedyStaticPath(): void
    {
        $match = $this->matcher()->match('/static/css/app.css', 'GET');

        $this->assertSame('css/app.css', $match['path']);
    }

    public function testThrowsWhenRouteMissing(): void
    {
        $this->expectException(RouteNotFoundException::class);

        $this->matcher()->match('/missing', 'GET');
    }

    public function testThrowsWhenMethodNotAllowed(): void
    {
        try {
            $this->matcher()->match('/api/v1/ping', 'POST');
            $this->fail('Expected MethodNotAllowedException');
        } catch (MethodNotAllowedException $e) {
            $this->assertContains('GET', $e->getAllowedMethods());
        }
    }
}
