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

/**
 * Central exception handling for HTTP and programmatic Moonlight dispatch.
 *
 * Configure via `$app->exceptions()` or a provider's `configureExceptions()` hook:
 *
 * - {@see handler()} — replace the default {@see GlobalExceptionHandler}
 * - {@see dontReport()} — skip logging for expected exceptions (e.g. validation)
 * - {@see reportable()} — add Sentry/monitoring callbacks
 * - {@see map()} — render specific exception classes to API envelopes
 *
 * {@see handle()} runs report (unless suppressed) then render. Use `report($e)` from
 * helpers when you only need logging without a response.
 */
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

    /**
     * Replace the default exception handler class.
     *
     * @param class-string<ExceptionHandlerContract> $handlerClass
     */
    public function handler(string $handlerClass): static
    {
        $this->handlerClass = $handlerClass;

        if (method_exists($this->app, 'setErrorHandler')) {
            $this->app->setErrorHandler($handlerClass);
        }

        return $this;
    }

    /**
     * Register a callback invoked after the handler's `report()` for every reported exception.
     */
    public function reportable(Closure $callback): static
    {
        $this->reportables[] = $callback;

        return $this;
    }

    /**
     * Suppress logging for an exception class (and subclasses).
     *
     * @param class-string<Throwable> $exceptionClass
     */
    public function dontReport(string $exceptionClass): static
    {
        $this->dontReport[] = $exceptionClass;

        return $this;
    }

    /**
     * Map an exception class to a custom API response (checked before the default handler).
     *
     * @param class-string<Throwable> $exceptionClass
     */
    public function map(string $exceptionClass, Closure $mapper): static
    {
        $this->mappers[$exceptionClass] = $mapper;

        return $this;
    }

    /**
     * Report (if allowed) and render an exception to a Moonlight API envelope.
     */
    public function handle(Throwable $e, ?Request $request = null): ApiResponse
    {
        if (!$this->shouldntReport($e)) {
            $this->report($e);
        }

        return $this->render($e, $request ?? Request::createFromGlobals());
    }

    /** Log via the resolved handler plus any {@see reportable()} callbacks. */
    public function report(Throwable $e): void
    {
        $this->resolveHandler()->report($e);

        foreach ($this->reportables as $reportable) {
            $reportable($e);
        }
    }

    /**
     * Convert an exception to an API response without reporting.
     *
     * Order: registered {@see map()} closures → {@see RenderableException} → handler.
     */
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
