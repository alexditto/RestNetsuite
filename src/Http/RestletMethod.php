<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Http;

enum RestletMethod: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
    case Delete = 'DELETE';
}
