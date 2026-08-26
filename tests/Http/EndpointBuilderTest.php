<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests\Http;

use Ditto\NetSuiteClient\Auth\AuthMethod;
use Ditto\NetSuiteClient\Http\EndpointBuilder;
use Ditto\NetSuiteClient\NetSuiteConfig;
use Ditto\NetSuiteClient\Tests\TestCase;

final class EndpointBuilderTest extends TestCase
{
    public function test_it_builds_the_base_url_from_the_account_id(): void
    {
        $config = new NetSuiteConfig(
            accountId: '1234567_SB1',
            authMethod: AuthMethod::TokenBasedAuth,
            consumerKey: 'k',
            consumerSecret: 's',
            tokenId: 't',
            tokenSecret: 'ts',
        );

        $builder = new EndpointBuilder($config);

        $this->assertSame('https://1234567-sb1.suitetalk.api.netsuite.com', $builder->baseUrl());
        $this->assertSame(
            'https://1234567-sb1.suitetalk.api.netsuite.com/services/rest/auth/oauth2/v1/token',
            $builder->tokenUrl(),
        );
    }

    public function test_it_builds_record_urls(): void
    {
        $builder = new EndpointBuilder($this->config());

        $this->assertSame(
            'https://1234567-sb1.suitetalk.api.netsuite.com/services/rest/record/v1/customer',
            $builder->recordUrl('customer'),
        );
        $this->assertSame(
            'https://1234567-sb1.suitetalk.api.netsuite.com/services/rest/record/v1/customer/42',
            $builder->recordUrl('customer', '42'),
        );
        $this->assertSame(
            'https://1234567-sb1.suitetalk.api.netsuite.com/services/rest/record/v1/customrecord_foo/1',
            $builder->recordUrl('customrecord_foo', '1'),
        );
    }

    public function test_it_builds_the_query_url_with_pagination_params(): void
    {
        $builder = new EndpointBuilder($this->config());

        $this->assertSame(
            'https://1234567-sb1.suitetalk.api.netsuite.com/services/rest/query/v1/suiteql?limit=1000&offset=0',
            $builder->queryUrl(),
        );
        $this->assertSame(
            'https://1234567-sb1.suitetalk.api.netsuite.com/services/rest/query/v1/suiteql?limit=10&offset=20',
            $builder->queryUrl(limit: 10, offset: 20),
        );
    }

    private function config(): NetSuiteConfig
    {
        return new NetSuiteConfig(
            accountId: '1234567_SB1',
            authMethod: AuthMethod::TokenBasedAuth,
            consumerKey: 'k',
            consumerSecret: 's',
            tokenId: 't',
            tokenSecret: 'ts',
        );
    }
}
