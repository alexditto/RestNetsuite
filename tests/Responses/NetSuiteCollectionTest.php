<?php

declare(strict_types=1);

namespace Ditto\NetSuiteClient\Tests\Responses;

use Ditto\NetSuiteClient\Responses\NetSuiteCollection;
use Ditto\NetSuiteClient\Tests\TestCase;

final class NetSuiteCollectionTest extends TestCase
{
    public function test_it_is_iterable_and_countable(): void
    {
        $collection = new NetSuiteCollection(
            items: [['id' => '1'], ['id' => '2']],
            count: 2,
            hasMore: true,
            offset: 0,
            totalResults: 5,
            links: [['rel' => 'next', 'href' => 'https://example.com/next']],
        );

        $this->assertCount(2, $collection);
        $this->assertSame([['id' => '1'], ['id' => '2']], iterator_to_array($collection));
        $this->assertTrue($collection->hasMore);
        $this->assertSame(5, $collection->totalResults);
        $this->assertSame(0, $collection->offset);
    }
}
