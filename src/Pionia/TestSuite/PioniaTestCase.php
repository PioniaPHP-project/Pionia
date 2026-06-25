<?php

namespace Pionia\TestSuite;

use PHPUnit\Framework\TestCase;
use Pionia\Base\WebApplication;
use Pionia\Http\Request\Request;
use Pionia\Realm\AppRealm;
use Pionia\TestSuite\Helpers\HelperMocksTrait;

class PioniaTestCase extends TestCase
{
    use HelperMocksTrait;

    public WebApplication | null $application = null;

    public ?Request $request;

    protected function setUp(): void
    {
        $this->application = app()->make(AppRealm::WEB_APP_TAG);
        $this->requestMock();
    }


    protected function tearDown(): void
    {
        $this->application = null;
        $this->request = null;
    }

    /**
     * mock a post request
     */
    public function requestMock(array $data = [], ?string $requestType='POST', ?string $url = 'http://localhost:8000/api/v1/'): Request
    {
        if (!$data){
            $data['service'] = 'test';
            $data['action'] = 'testAction';
            $data['foo'] = 'bar';
        }
        $this->request = Request::create($url, $requestType, $data);
        return $this->request;
    }
}
