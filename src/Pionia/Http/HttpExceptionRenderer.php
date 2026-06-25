<?php

namespace Pionia\Http;

use Pionia\Http\Pages\HttpErrorPage;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

class HttpExceptionRenderer
{
    public function render(Throwable $e, Request $request): Response
    {
        if ($e instanceof ResourceNotFoundException) {
            return HttpErrorPage::respond(
                $request,
                (int) env('NOT_FOUND_CODE', 404),
                'The page you requested was not found.',
            );
        }

        if ($e instanceof MethodNotAllowedException) {
            $allowed = implode(', ', $e->getAllowedMethods());

            return HttpErrorPage::respond(
                $request,
                405,
                $allowed !== ''
                    ? "Method not allowed. Try: {$allowed}"
                    : 'Method not allowed.',
            );
        }

        return Response::fromEnvelope(pionia_handle_exception($e, $request));
    }
}
