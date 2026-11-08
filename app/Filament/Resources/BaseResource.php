<?php

namespace App\Filament\Resources;

use App\Models\User;
use App\Policies\GlobalPolicy;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Base resource that wires every Filament authorization gate directly to
 * GlobalPolicy. Filament v5.11+ enforces page access through the authorize*()
 * methods while the can*() methods drive UI visibility; both are delegated to
 * GlobalPolicy here so a single policy remains the source of truth.
 *
 * Resources that need custom rules (e.g. RoleResource, UserResource) can
 * override any individual can*() method – this class's implementations are
 * the fallback.
 */
abstract class BaseResource extends Resource
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private static function authUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    private static function policy(): GlobalPolicy
    {
        return app(GlobalPolicy::class);
    }

    // -----------------------------------------------------------------------
    // Authorization overrides – directly delegate to GlobalPolicy
    // -----------------------------------------------------------------------

    public static function canViewAny(): bool
    {
        $user = static::authUser();

        return $user ? static::policy()->viewAny($user, static::getModel()) : false;
    }

    public static function canView(Model $record): bool
    {
        $user = static::authUser();

        return $user ? static::policy()->view($user, $record) : false;
    }

    public static function canCreate(): bool
    {
        $user = static::authUser();

        return $user ? static::policy()->create($user, static::getModel()) : false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = static::authUser();

        return $user ? static::policy()->update($user, $record) : false;
    }

    public static function canDelete(Model $record): bool
    {
        $user = static::authUser();

        return $user ? static::policy()->delete($user, $record) : false;
    }

    public static function canDeleteAny(): bool
    {
        $user = static::authUser();

        return $user ? static::policy()->deleteAny($user, static::getModel()) : false;
    }

    public static function canForceDelete(Model $record): bool
    {
        $user = static::authUser();

        return $user ? static::policy()->forceDelete($user, $record) : false;
    }

    public static function canForceDeleteAny(): bool
    {
        $user = static::authUser();

        return $user ? static::policy()->forceDeleteAny($user) : false;
    }

    public static function canRestore(Model $record): bool
    {
        $user = static::authUser();

        return $user ? static::policy()->restore($user, $record) : false;
    }

    public static function canRestoreAny(): bool
    {
        $user = static::authUser();

        return $user ? static::policy()->restoreAny($user) : false;
    }

    // -----------------------------------------------------------------------
    // Enforcement (Filament v5.11+ authorizes page access through authorize*()
    // rather than the can*() methods). Keep them bound to the same can*()
    // logic so per-resource overrides still take effect.
    // -----------------------------------------------------------------------

    public static function authorizeViewAny(): void
    {
        abort_unless(static::canViewAny(), 403);
    }

    public static function authorizeCreate(): void
    {
        abort_unless(static::canCreate(), 403);
    }

    public static function authorizeEdit(Model $record): void
    {
        abort_unless(static::canEdit($record), 403);
    }

    public static function authorizeView(Model $record): void
    {
        abort_unless(static::canView($record), 403);
    }
}
