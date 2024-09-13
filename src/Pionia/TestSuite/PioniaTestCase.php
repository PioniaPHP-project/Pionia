<?php

namespace Pionia\TestSuite;

use DI\Container;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Pionia\Base\PioniaApplication;
use Pionia\Events\PioniaEventDispatcher;
use Pionia\Http\Request\Request;
use Pionia\Logging\PioniaLogger;
use Pionia\TestSuite\Helpers\HelperMocksTrait;
use Pionia\Utils\PioniaApplicationType;
use Symfony\Component\EventDispatcher\EventDispatcher;

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
    public function applicationMock(): PioniaApplication
    {
        $application = $this->createMock(PioniaApplication::class);
        $application->context = $this->createMock(Container::class);
        $application->dispatcher = $this->createMock(PioniaEventDispatcher::class);
        $application->setLogger($this->createMock(Logger::class));

        $application->powerUp(PioniaApplicationType::TEST);
        $this->application = $application;
        return $application;
    }
}
