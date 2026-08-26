<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Auth;

use Psr\Http\Message\RequestInterface;

final class OAuth2Strategy implements AuthStrategy
{
    public function __construct(private readonly TokenManager $tokenManager)
    {
    }

    public function applyToRequest(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('Authorization', 'Bearer ' . $this->tokenManager->getAccessToken());
    }
}
