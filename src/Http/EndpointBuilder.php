<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Http;

use Ditto\NetSuiteClient\NetSuiteConfig;

final class EndpointBuilder
{
    public function __construct(private readonly NetSuiteConfig $config)
    {
    }

    public function baseUrl(): string
    {
        return "https://{$this->accountSubdomain()}.suitetalk.api.netsuite.com";
    }

    public function tokenUrl(): string
    {
        return $this->baseUrl() . '/services/rest/auth/oauth2/v1/token';
    }

    public function recordUrl(string $recordType, ?string $id = null): string
    {
        $url = $this->baseUrl() . '/services/rest/record/v1/' . rawurlencode($recordType);

        if ($id !== null) {
            $url .= '/' . rawurlencode($id);
        }

        return $url;
    }

    public function queryUrl(int $limit = 1000, int $offset = 0): string
    {
        return $this->baseUrl() . '/services/rest/query/v1/suiteql?' . http_build_query([
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    private function accountSubdomain(): string
    {
        return strtolower(str_replace('_', '-', $this->config->accountId));
    }
}
