<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Policies\GlobalPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class UserRoleAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['users', 'roles', 'model_has_roles', 'permissions', 'role_has_permissions'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->string('model_id', 26);
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        $policy = Mockery::mock(GlobalPolicy::class);
        $policy->shouldReceive('viewAny')->andReturn(true);
        $policy->shouldReceive('view')->andReturn(true);
        $policy->shouldReceive('create')->andReturn(true);
        $policy->shouldReceive('update')->andReturn(true);
        $policy->shouldReceive('delete')->andReturn(false);
        $policy->shouldReceive('deleteAny')->andReturn(false);
        $policy->shouldReceive('restore')->andReturn(false);
        $policy->shouldReceive('restoreAny')->andReturn(false);
        $policy->shouldReceive('forceDelete')->andReturn(false);
        $policy->shouldReceive('forceDeleteAny')->andReturn(false);
        app()->instance(GlobalPolicy::class, $policy);

        $this->loginAsDelegatedUser();
    }

    public function test_delegated_user_cannot_assign_roles_when_creating_a_user(): void
    {
        $adminRoleId = DB::table('roles')->insertGetId([
            'name' => 'Admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::test(CreateUser::class)
            ->set('data.name', 'New user')
            ->set('data.email', 'new@example.test')
            ->set('data.password', 'Password123!')
            ->set('data.roles', [$adminRoleId])
            ->call('create')
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
        $this->assertDatabaseCount('model_has_roles', 0);
    }

    public function test_delegated_user_cannot_assign_roles_when_editing_a_user(): void
    {
        $targetId = '01J00000000000000000000001';
        DB::table('users')->insert([
            'id' => $targetId,
            'name' => 'Existing user',
            'email' => 'existing@example.test',
            'password' => bcrypt('Password123!'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminRoleId = DB::table('roles')->insertGetId([
            'name' => 'Admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::test(EditUser::class, ['record' => $targetId])
            ->set('data.roles', [$adminRoleId])
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseMissing('model_has_roles', [
            'model_id' => $targetId,
            'role_id' => $adminRoleId,
        ]);
    }

    public function test_delegated_user_can_still_edit_an_unprivileged_account(): void
    {
        $targetId = '01J00000000000000000000001';
        DB::table('users')->insert([
            'id' => $targetId,
            'name' => 'Existing user',
            'email' => 'existing@example.test',
            'password' => bcrypt('Password123!'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::test(EditUser::class, ['record' => $targetId])
            ->set('data.name', 'Updated user')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $targetId,
            'name' => 'Updated user',
        ]);
    }

    public function test_delegated_user_cannot_edit_a_privileged_account(): void
    {
        $adminRoleId = DB::table('roles')->insertGetId([
            'name' => 'Admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $target = User::query()->forceCreate([
            'id' => '01J00000000000000000000002',
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'password' => 'Password123!',
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $adminRoleId,
            'model_type' => User::class,
            'model_id' => $target->id,
        ]);

        $this->assertFalse(UserResource::canEdit($target));

        Livewire::test(EditUser::class, ['record' => $target->id])
            ->assertForbidden();
    }

    public function test_admin_can_assign_roles_when_creating_a_user(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Manager',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->loginAsAdminUser();

        Livewire::test(CreateUser::class)
            ->set('data.name', 'New manager')
            ->set('data.email', 'manager@example.test')
            ->set('data.password', 'Password123!')
            ->set('data.roles', [$roleId])
            ->call('create')
            ->assertHasNoErrors();

        $user = DB::table('users')->where('email', 'manager@example.test')->first();
        $this->assertNotNull($user);
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $user->id,
            'role_id' => $roleId,
            'model_type' => User::class,
        ]);
    }

    public function test_admin_can_assign_roles_when_editing_a_user(): void
    {
        $targetId = str_pad('target', 26, '0', STR_PAD_LEFT);
        DB::table('users')->insert([
            'id' => $targetId,
            'name' => 'Existing user',
            'email' => 'existing@example.test',
            'password' => bcrypt('Password123!'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Manager',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->loginAsAdminUser();

        Livewire::test(EditUser::class, ['record' => $targetId])
            ->set('data.roles', [$roleId])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $targetId,
            'role_id' => $roleId,
            'model_type' => User::class,
        ]);
    }

    private function loginAsDelegatedUser(): void
    {
        /** @var User&MockInterface $user */
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => str_pad('delegated', 26, '0', STR_PAD_LEFT)]);
        $user->shouldReceive('hasRole')->andReturn(false);
        $user->shouldReceive('canAccessPanel')->andReturn(true);

        Auth::guard()->setUser($user);
    }

    private function loginAsAdminUser(): void
    {
        /** @var User&MockInterface $user */
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => str_pad('admin-actor', 26, '0', STR_PAD_LEFT)]);
        $user->shouldReceive('hasRole')->andReturn(true);
        $user->shouldReceive('canAccessPanel')->andReturn(true);

        Auth::guard()->setUser($user);
    }
}
