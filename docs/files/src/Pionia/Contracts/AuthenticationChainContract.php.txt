<?php

namespace Pionia\Contracts;

use Pionia\Http\Request\Request;

interface AuthenticationChainContract
{
    /**
     * Adds an authentication backend to the chain
     */
    public function addAuthenticationBackend(string $authenticationContract);

    /**
     * Get the authentications in chain
     */
    public function getAuthentications(): array;

    /**
     * Add an authentication backend before another
     */
    public function addBefore(string $authToPoint, string $authToAdd): static;

    /**
     * Add an authentication after another
     */
    public function addAfter(string $authToPoint, string $authToAdd): static;

    /**
     * Run the authentication chain on a request
     */
    public function handle(Request $request);

    /**
     * Run the next authentication backend in the chain.
     */
    public function next(Request $request, AuthenticationContract $next);

}
