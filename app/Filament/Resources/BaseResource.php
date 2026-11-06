<?php

namespace App\Filament\Resources;

use App\Models\User;
use App\Policies\GlobalPolicy;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Base resource that wires every Filament authorization gate directly to
 * GlobalPolicy, bypassing the Gate::before() pipeline which Filament v5
 * may not invoke for all resource can*() calls.
 *
 * Resources that need custom rules (e.g. RoleResource) can override any
 * individual can*() method – this class's implementations are the fallback.
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
}
