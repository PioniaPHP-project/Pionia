<?php

namespace Pionia\Pionia\TestSuite;

use DI\Container;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Pionia\Pionia\Base\PioniaApplication;
use Pionia\Pionia\Http\Request\Request;
use Pionia\Pionia\TestSuite\Helpers\HelperMocksTrait;
use Pionia\Pionia\Utils\PioniaApplicationType;

class PioniaTestCase extends TestCase
{
    use HelperMocksTrait;

    public ?PioniaApplication $application = null;

    public ?Request $request;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->applicationMock();
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

    /**
     * @throws Exception
     */
    public function containerMock(): Container
    {
        return $this->createMock(Container::class);
    }

    /**
     * @throws Exception
     */
    public function applicationMock(): PioniaApplication
    {
        $application = new PioniaApplication($this->containerMock());
        $application->setLogger($this->createMock(Logger::class));
        $application->powerUp(PioniaApplicationType::TEST);
        $this->application = $application;
        return $application;
    }
}
