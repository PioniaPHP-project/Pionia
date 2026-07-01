<?php

namespace Documentation;

use Pionia\Documentation\MoonlightDocParser;
use Pionia\Http\Response\ApiResponse;
use Pionia\Http\Services\Service;
use Pionia\TestSuite\PioniaTestCase;
use Pionia\Collections\Arrayable;
use ReflectionClass;

class MoonlightDocParserTest extends PioniaTestCase
{
    private MoonlightDocParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new MoonlightDocParser();
    }

    public function testParsesMoonlightTagsFromDocblock(): void
    {
        $reflection = new ReflectionClass(SampleMoonlightService::class);
        $actions = $this->parser->parseServiceActions($reflection);
        $greet = array_values(array_filter($actions, static fn ($a) => $a->name === 'greet'));

        $this->assertCount(1, $greet);
        $this->assertSame('Returns a greeting', $greet[0]->summary);
        $this->assertSame('none', $greet[0]->auth);
        $this->assertArrayHasKey('name', $greet[0]->params);
        $this->assertSame('{"service":"demo","action":"greet","name":"Ada"}', $greet[0]->example);
    }

    public function testInfersActionNameFromMethodWhenTagMissing(): void
    {
        $reflection = new ReflectionClass(SampleMoonlightService::class);
        $actions = $this->parser->parseServiceActions($reflection);
        $fallback = array_values(array_filter($actions, static fn ($a) => $a->name === 'ping'));

        $this->assertCount(1, $fallback);
        $this->assertSame('pingAction', $fallback[0]->methodName);
    }

    public function testParseServiceTagsFromClassDocblock(): void
    {
        $tags = $this->parser->parseServiceTags(new ReflectionClass(SampleMoonlightService::class));

        $this->assertSame('demo', $tags['service']);
        $this->assertSame('v1', $tags['version']);
        $this->assertSame('Demo service for parser tests', $tags['summary']);
    }

    public function testParsesMoonlightActionAttribute(): void
    {
        $reflection = new ReflectionClass(SampleAttributedMoonlightService::class);
        $actions = $this->parser->parseServiceActions($reflection);
        $named = array_values(array_filter($actions, static fn ($a) => $a->name === 'status'));

        $this->assertCount(1, $named);
        $this->assertSame('Health check', $named[0]->summary);
        $this->assertSame('none', $named[0]->auth);
    }
}

/**
 * @moonlight-service demo
 * @moonlight-version v1
 * @moonlight-summary Demo service for parser tests
 */
class SampleMoonlightService extends Service
{
    /**
     * @moonlight-action greet
     * @moonlight-summary Returns a greeting
     * @moonlight-auth none
     * @moonlight-param string name Person to greet
     * @moonlight-example {"service":"demo","action":"greet","name":"Ada"}
     */
    protected function greetAction(Arrayable $data): ApiResponse
    {
        return response(0, 'hi');
    }

    /** Ping health check. */
    protected function pingAction(Arrayable $data): ApiResponse
    {
        return response(0, 'pong');
    }
}

/**
 * @moonlight-service attributed
 */
class SampleAttributedMoonlightService extends Service
{
    #[\Pionia\Documentation\Attributes\MoonlightAction(name: 'status', summary: 'Health check', auth: 'none')]
    protected function statusAction(Arrayable $data): ApiResponse
    {
        return response(0, 'ok');
    }
}
