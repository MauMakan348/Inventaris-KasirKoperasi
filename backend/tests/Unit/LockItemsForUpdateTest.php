<?php

namespace Tests\Unit;

use App\Repositories\ItemRepository;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LockItemsForUpdateTest extends TestCase
{
    #[Test]
    public function it_requires_an_active_database_transaction(): void
    {
        $this->expectException(LogicException::class);

        $this->app->make(ItemRepository::class)->lockItemsForUpdate([1]);
    }
}
