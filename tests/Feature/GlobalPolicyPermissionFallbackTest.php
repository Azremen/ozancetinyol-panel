<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use App\Policies\GlobalPolicy;
use Mockery;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Tests\TestCase;

class GlobalPolicyPermissionFallbackTest extends TestCase
{
    public function test_update_checks_alternative_permission_after_a_missing_permission(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasRole')->once()->andReturn(false);
        $user->shouldReceive('hasPermissionTo')
            ->once()
            ->with('write_page')
            ->andThrow(PermissionDoesNotExist::create('write_page', 'web'));
        $user->shouldReceive('hasPermissionTo')
            ->once()
            ->with('update_page')
            ->andReturn(true);

        $this->assertTrue(app(GlobalPolicy::class)->update($user, Page::class));
    }
}
