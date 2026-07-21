<?php

namespace Pionia\TestSuite;

use PHPUnit\Framework\TestCase;
use Pionia\Base\WebApplication;
use Pionia\Collections\Arrayable;
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

    /** @var array<string, mixed> */
    private array $switchContext = [];

    /** @var array<string, mixed> */
    private array $serviceContext = [];

    protected function setUp(): void
    {
        $this->application = $this->webApplication();
        $this->snapshotApiRegistry();
        $this->requestMock();
    }

    protected function tearDown(): void
    {
        $this->restoreApiRegistry();

        if (class_exists(\Pionia\Http\Background\DeferredWorkBuffer::class)) {
            \Pionia\Http\Background\DeferredWorkBuffer::reset();
        }

        $this->tearDownInMemoryDatabase();
        $this->application = null;
        $this->request = null;
    }

    /**
     * Preserve the singleton realm's API registry so tests may register temporary
     * switches without leaking them into later tests in the same PHPUnit process.
     */
    private function snapshotApiRegistry(): void
    {
        $switches = app()->getSilently(AppRealm::SWITCHES_TAGS);
        $services = app()->getSilently(AppRealm::SERVICES_TAG);

        $this->switchContext = $switches instanceof Arrayable ? $switches->all() : (array) ($switches ?? []);
        $this->serviceContext = $services instanceof Arrayable ? $services->all() : (array) ($services ?? []);
    }

    private function restoreApiRegistry(): void
    {
        $realm = app();
        $realm->set(AppRealm::SWITCHES_TAGS, arr($this->switchContext));
        $realm->set(AppRealm::SERVICES_TAG, arr($this->serviceContext));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function requestMock(array $data = [], ?string $requestType = 'POST', ?string $url = 'http://localhost:8003/api/v1/'): Request
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
