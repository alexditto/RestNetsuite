<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Auth;

use Psr\Http\Message\RequestInterface;

interface AuthStrategy
{
    /**
     * Return a copy of $request with authorization applied (e.g. an `Authorization` header).
     */
    public function applyToRequest(RequestInterface $request): RequestInterface;
}
