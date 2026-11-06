<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Roles\Tables\RolesTable;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\BaseResource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RoleResource extends BaseResource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    /** Returns the authenticated user typed as App\Models\User, or null. */
    private static function authUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /** Only Admin / Developer roles may list roles in the panel. */
    public static function canViewAny(): bool
    {
        $user = static::authUser();

        if ($user?->hasRole(['Admin', 'admin', 'Developer', 'developer'])) {
            return true;
        }

        try {
            return $user?->hasPermissionTo('list_role')
                || $user?->hasPermissionTo('view_any_role')
                ?: false;
        } catch (\Exception) {
            return false;
        }
    }

    /** Only Admin / Developer roles may create new roles. */
    public static function canCreate(): bool
    {
        return static::authUser()?->hasRole(['Admin', 'admin', 'Developer', 'developer']) ?? false;
    }

    /** Only Admin / Developer roles may edit a role record. */
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::authUser()?->hasRole(['Admin', 'admin', 'Developer', 'developer']) ?? false;
    }

    /** Only Admin / Developer roles may delete a single role. */
    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::authUser()?->hasRole(['Admin', 'admin', 'Developer', 'developer']) ?? false;
    }

    /** Only Admin / Developer roles may bulk-delete roles. */
    public static function canDeleteAny(): bool
    {
        return static::authUser()?->hasRole(['Admin', 'admin', 'Developer', 'developer']) ?? false;
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('panel.common.authorization');
    }

    public static function getModelLabel(): string
    {
        return __('panel.common.role');
    }

    public static function getPluralModelLabel(): string
    {
        return __('panel.common.roles');
    }

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
