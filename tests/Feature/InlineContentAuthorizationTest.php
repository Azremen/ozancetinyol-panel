<?php

namespace Tests\Feature;

use App\Filament\Resources\Facilities\Pages\ListFacilities;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Testimonials\Pages\ListTestimonials;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class InlineContentAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['posts', 'testimonials', 'facilities', 'activity_log'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('posts', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('image')->nullable();
            $table->json('title')->nullable();
            $table->json('category_name')->nullable();
            $table->date('publish_date')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('testimonials', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('image')->nullable();
            $table->string('client_name')->nullable();
            $table->string('client_title')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('facilities', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('icon')->nullable();
            $table->json('title')->nullable();
            $table->json('description')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('event')->nullable();
            $table->string('causer_type')->nullable();
            $table->string('causer_id')->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
        });
    }

    public function test_delegated_user_cannot_mutate_inline_active_toggles(): void
    {
        $this->loginWithPermissions([
            'list_post', 'list_testimonial', 'list_facility',
        ]);

        $records = [
            [ListPosts::class, 'posts', ['title' => json_encode(['tr' => 'Haber'])]],
            [ListTestimonials::class, 'testimonials', ['client_name' => 'Danışan']],
            [ListFacilities::class, 'facilities', ['title' => json_encode(['tr' => 'Tesis'])]],
        ];

        foreach ($records as [$component, $table, $attributes]) {
            $id = str_pad((string) (count($records) + strlen($table)), 26, '0', STR_PAD_LEFT);
            DB::table($table)->insert(array_merge([
                'id' => $id,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ], $attributes));

            Livewire::test($component)
                ->call('updateTableColumnState', 'is_active', $id, false)
                ->assertStatus(200);

            $this->assertTrue((bool) DB::table($table)->where('id', $id)->value('is_active'));
        }
    }

    public function test_authorized_user_can_mutate_inline_active_toggles(): void
    {
        $this->loginWithPermissions(['list_post', 'write_post']);

        $id = str_pad('authorized', 26, '0', STR_PAD_LEFT);
        DB::table('posts')->insert([
            'id' => $id,
            'title' => json_encode(['tr' => 'Haber']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::test(ListPosts::class)
            ->call('updateTableColumnState', 'is_active', $id, false)
            ->assertStatus(200);

        $this->assertFalse((bool) DB::table('posts')->where('id', $id)->value('is_active'));
    }

    private function loginWithPermissions(array $permissions): void
    {
        /** @var User&MockInterface $user */
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => str_pad('delegated', 26, '0', STR_PAD_LEFT)]);
        $user->shouldReceive('hasRole')->andReturn(false);
        $user->shouldReceive('hasPermissionTo')
            ->andReturnUsing(static fn (string $permission): bool => in_array($permission, $permissions, true));
        $user->shouldReceive('canAccessPanel')->andReturn(true);

        Auth::guard()->setUser($user);
    }
}
