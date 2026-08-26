<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Auth;

enum AuthMethod: string
{
    case OAuth2 = 'oauth2';
    case TokenBasedAuth = 'tba';
}
