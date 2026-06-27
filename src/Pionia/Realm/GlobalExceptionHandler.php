<?php

namespace Pionia\Realm;

use Pionia\Contracts\ExceptionHandlerContract;
use Pionia\Exceptions\UserUnauthenticatedException;
use Pionia\Exceptions\UserUnauthorizedException;
use Pionia\Exceptions\ResourceNotFoundException;
use Pionia\Http\Routing\Exception\RouteNotFoundException;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\BaseResponse;
use Throwable;

class GlobalExceptionHandler implements ExceptionHandlerContract
{
    public function __invoke(Throwable $e, Request $request): BaseResponse
    {
        $this->report($e);

        return $this->render($e, $request);
    }

    public function report(Throwable $e): void
    {
        logger()->error($e->getMessage(), [
            'exception' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    public function render(Throwable $e, Request $request): BaseResponse
    {
        $statusCode = $this->resolveStatusCode($e);
        $message = realm()->isDebug() ? $e->getMessage() : 'An unexpected error occurred.';
        $returnData = $this->debugPayload($e);

        return response($statusCode, $message, $returnData);
    }

    private function resolveStatusCode(Throwable $e): int
    {
        return match (true) {
            $e instanceof ResourceNotFoundException,
            $e instanceof RouteNotFoundException => (int) env('not_found_code', 404),
            $e instanceof UserUnauthenticatedException => (int) env('unauthenticated_code', 401),
            $e instanceof UserUnauthorizedException => (int) env('unauthorized_code', 403),
            method_exists($e, 'getStatusCode') => (int) $e->getStatusCode(),
            method_exists($e, 'returnCode') => (int) $e->returnCode(),
            default => (int) env('server_error_code', 500),
        };
    }

    private function debugPayload(Throwable $e): ?array
    {
        if (!realm()->isDebug()) {
            return null;
        }

        $settings = env('exceptions', []);
        $renderTraces = filter_var($settings['RENDER_TRACES'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $payload = [
            'exception' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];

        if ($renderTraces) {
            $payload['trace'] = explode("\n", $e->getTraceAsString());
        }

        return $payload;
    }
}
