<?php

namespace App\Providers;

use App\Http\Middleware\SetLocale;
use App\Models\Slider;
use App\Models\User;
use App\Observers\RoleObserver;
use App\Observers\SliderObserver;
use App\Policies\GlobalPolicy;
use App\Support\UploadRules;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::addPersistentMiddleware([SetLocale::class]);
        $this->bootObservers();
        $this->bootEventListeners();
        $this->bootGates();
        $this->bootFilamentDefaults();
        $this->bootDatabaseTimezone();
    }

    private function bootObservers(): void
    {
        Slider::observe(SliderObserver::class);
        Role::observe(RoleObserver::class);
    }

    private function bootEventListeners(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                activity('auth')->causedBy($event->user)->log('logged_in');
            }
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user instanceof User) {
                activity('auth')->causedBy($event->user)->log('logged_out');
            }
        });
    }

    private function bootGates(): void
    {
        Gate::before(function (User $user, string $ability, array $args = []): ?bool {
            if ($user->hasRole(['Admin', 'admin', 'Developer', 'developer'])) {
                return true;
            }

            $crudAbilities = [
                'viewAny', 'view', 'create', 'update', 'delete',
                'deleteAny', 'restore', 'restoreAny', 'forceDelete', 'forceDeleteAny',
            ];

            if (in_array($ability, $crudAbilities, strict: true) && ! empty($args)) {
                $model = $args[0] ?? null;
                $class = is_object($model) ? get_class($model) : $model;

                if (is_string($class) && (
                    str_starts_with($class, 'App\\Models\\')
                    || str_starts_with($class, 'Spatie\\Permission\\Models\\')
                )) {
                    return app(GlobalPolicy::class)->{$ability}($user, $model);
                }
            }

            return null;
        });

        Gate::guessPolicyNamesUsing(fn (string $modelClass): string => GlobalPolicy::class);
    }

    private function bootFilamentDefaults(): void
    {
        $slugifyFileName = function (TemporaryUploadedFile $file): string {
            $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $ext = strtolower($file->getClientOriginalExtension());

            return Str::slug($name).'-'.uniqid().'.'.$ext;
        };

        // Fail-safe defaults: every upload is limited to raster images unless a component
        // explicitly declares another allow-list (e.g. the favicon). Never use `->image()`.
        FileUpload::configureUsing(
            fn (FileUpload $fileUpload) => $fileUpload
                ->getUploadedFileNameForStorageUsing($slugifyFileName)
                ->acceptedFileTypes(UploadRules::IMAGE_MIME_TYPES)
                ->maxSize(UploadRules::MAX_IMAGE_KILOBYTES)
        );

        SpatieMediaLibraryFileUpload::configureUsing(
            fn (SpatieMediaLibraryFileUpload $fileUpload) => $fileUpload
                ->getUploadedFileNameForStorageUsing($slugifyFileName)
                ->acceptedFileTypes(UploadRules::IMAGE_MIME_TYPES)
                ->maxSize(UploadRules::MAX_IMAGE_KILOBYTES)
        );
    }

    private function bootDatabaseTimezone(): void
    {
        try {
            $tz = config('app.timezone') ?? date_default_timezone_get();
            $offset = now()->setTimezone($tz)->format('P');
            DB::statement(sprintf("SET time_zone = '%s'", $offset));
        } catch (\Throwable $exception) {
            if (! $this->app->runningUnitTests()) {
                report($exception);
            }
        }
    }
}
