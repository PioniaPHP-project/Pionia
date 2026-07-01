<?php

namespace TestSuite;

use Pionia\Http\Response\ApiResponse;
use Pionia\TestSuite\AssertsPioniaResponses;
use Pionia\TestSuite\PioniaTestCase;
use Pionia\TestSuite\TestResponse;
use PHPUnit\Framework\AssertionFailedError;

class AssertsPioniaResponsesTest extends PioniaTestCase
{
    use AssertsPioniaResponses;

    public function testAssertPioniaOkPassesForReturnCodeZero(): void
    {
        $response = new TestResponse(new \Pionia\Http\Response\Response(
            response(0, 'ok')->getPrettyResponse(),
            200
        ));

        $this->assertPioniaOk($response);
    }

    public function testAssertPioniaOkFailsForNonZeroReturnCode(): void
    {
        $response = new TestResponse(new \Pionia\Http\Response\Response(
            response(404, 'missing')->getPrettyResponse(),
            200
        ));

        $this->expectException(AssertionFailedError::class);
        $this->assertPioniaOk($response);
    }

    public function testAssertPioniaErrorMatchesReturnCode(): void
    {
        $response = response(403, 'denied');
        $this->assertPioniaError($response, 403);
    }

    public function testAssertJsonStructureChecksEnvelopeKeys(): void
    {
        $response = new TestResponse(new \Pionia\Http\Response\Response(
            response(0, 'ok', ['id' => 1])->getPrettyResponse(),
            200
        ));

        $this->assertJsonStructure(['returnCode', 'returnMessage', 'returnData', 'extraData'], $response);
    }

    public function testDecodeApiResponseRoundTripsJson(): void
    {
        $original = response(0, 'hello', ['n' => 2]);
        $decoded = $this->decodeApiResponse($original);

        $this->assertSame(0, $decoded['returnCode']);
        $this->assertSame('hello', $decoded['returnMessage']);
        $this->assertSame(['n' => 2], $decoded['returnData']);
    }
}
