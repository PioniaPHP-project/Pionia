<?php

namespace Pionia\TestSuite;

use PHPUnit\Framework\TestCase;
use Pionia\Base\WebApplication;
use Pionia\Http\Request\Request;
use Pionia\Realm\AppRealm;
use Pionia\TestSuite\Concerns\CreatesApplication;
use Pionia\TestSuite\Concerns\InteractsWithConsole;
use Pionia\TestSuite\Concerns\MakesHttpRequests;
use Pionia\TestSuite\Concerns\UsesInMemoryDatabase;
use Pionia\TestSuite\Helpers\HelperMocksTrait;

class PioniaTestCase extends TestCase
{
    use AssertsPioniaResponses;
    use CreatesApplication;
    use HelperMocksTrait;
    use InteractsWithConsole;
    use MakesHttpRequests;
    use UsesInMemoryDatabase;

    public WebApplication | null $application = null;

    public ?Request $request;

    protected function setUp(): void
    {
        $this->application = $this->webApplication();
        $this->requestMock();
    }

    protected function tearDown(): void
    {
        if (class_exists(\Pionia\Http\Background\DeferredWorkBuffer::class)) {
            \Pionia\Http\Background\DeferredWorkBuffer::reset();
        }

        $this->tearDownInMemoryDatabase();
        $this->application = null;
        $this->request = null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function requestMock(array $data = [], ?string $requestType = 'POST', ?string $url = 'http://localhost:8000/api/v1/'): Request
    {
        if (!$data) {
            $data['service'] = 'test';
            $data['action'] = 'testAction';
            $data['foo'] = 'bar';
        }
        $this->request = Request::create($url, $requestType, $data);

        return $this->request;
    }
}
