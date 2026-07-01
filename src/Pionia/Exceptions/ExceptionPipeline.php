<?php

namespace Pionia\Exceptions;

use Closure;
use Pionia\Contracts\ExceptionHandlerContract;
use Pionia\Contracts\RenderableException;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\ApiResponse;
use Pionia\Realm\GlobalExceptionHandler;
use Pionia\Realm\RealmContract;
use Throwable;

class ExceptionPipeline
{
    /** @var list<class-string<Throwable>> */
    private array $dontReport = [];

    /** @var list<Closure(Throwable): void> */
    private array $reportables = [];

    /** @var array<class-string<Throwable>, Closure(Throwable): ApiResponse> */
    private array $mappers = [];

    private string $handlerClass = GlobalExceptionHandler::class;

    public function __construct(private readonly RealmContract $app)
    {
        $settings = $app->env('exceptions', []);
        if (is_array($settings) && !empty($settings['DONT_REPORT'])) {
            $this->dontReport = array_map(
                'trim',
                explode(',', (string) $settings['DONT_REPORT'])
            );
        }
    }

    public function handler(string $handlerClass): static
    {
        $this->handlerClass = $handlerClass;

        if (method_exists($this->app, 'setErrorHandler')) {
            $this->app->setErrorHandler($handlerClass);
        }

        return $this;
    }

    public function reportable(Closure $callback): static
    {
        $this->reportables[] = $callback;

        return $this;
    }

    /**
     * @param class-string<Throwable> $exceptionClass
     */
    public function dontReport(string $exceptionClass): static
    {
        $this->dontReport[] = $exceptionClass;

        return $this;
    }

    /**
     * @param class-string<Throwable> $exceptionClass
     */
    public function map(string $exceptionClass, Closure $mapper): static
    {
        $this->mappers[$exceptionClass] = $mapper;

        return $this;
    }

    public function handle(Throwable $e, ?Request $request = null): ApiResponse
    {
        if (!$this->shouldntReport($e)) {
            $this->report($e);
        }

        return $this->render($e, $request ?? Request::createFromGlobals());
    }

    public function report(Throwable $e): void
    {
        $this->resolveHandler()->report($e);

        foreach ($this->reportables as $reportable) {
            $reportable($e);
        }
    }

    public function render(Throwable $e, Request $request): ApiResponse
    {
        foreach ($this->mappers as $exceptionClass => $mapper) {
            if ($e instanceof $exceptionClass) {
                return $mapper($e);
            }
        }

        if ($e instanceof RenderableException) {
            return $e->render($request);
        }

        return $this->resolveHandler()->render($e, $request);
    }

    private function shouldntReport(Throwable $e): bool
    {
        foreach ($this->dontReport as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }

    private function resolveHandler(): ExceptionHandlerContract
    {
        $handler = method_exists($this->app, 'getErrorHandler')
            ? $this->app->getErrorHandler()
            : $this->handlerClass;

        if (is_object($handler) && $handler instanceof ExceptionHandlerContract) {
            return $handler;
        }

        $handlerClass = is_string($handler) ? $handler : $this->handlerClass;
        $resolved = $this->app->make($handlerClass);

        if (!$resolved instanceof ExceptionHandlerContract) {
            throw new \RuntimeException($handlerClass . ' must implement ' . ExceptionHandlerContract::class);
        }

        return $resolved;
    }
}
