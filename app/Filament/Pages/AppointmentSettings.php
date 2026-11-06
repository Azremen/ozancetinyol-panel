<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\User;
use App\Services\AppointmentSchedule;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AppointmentSettings extends Page
{
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasRole(['Admin', 'admin', 'Developer', 'developer']);
    }

    public static function getNavigationGroup(): ?string
    {
        return __('physio.navigation_group');
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-clock';
    }

    public static function getNavigationLabel(): string
    {
        return __('appointments.settings');
    }

    public function getTitle(): string
    {
        return __('appointments.settings');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(app(AppointmentSchedule::class)->settings());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('duration')->label(__('appointments.duration'))
                ->numeric()->minValue(15)->maxValue(180)->required()
                ->helperText(__('appointments.timezone_hint', ['timezone' => config('app.timezone')])),
            Repeater::make('days')->label(__('appointments.working_hours'))
                ->schema([
                    Select::make('day')->label(__('appointments.day'))->options([
                        1 => __('appointments.monday'), 2 => __('appointments.tuesday'),
                        3 => __('appointments.wednesday'), 4 => __('appointments.thursday'),
                        5 => __('appointments.friday'), 6 => __('appointments.saturday'),
                        7 => __('appointments.sunday'),
                    ])->required(),
                    Toggle::make('enabled')->label(__('appointments.open'))->required(),
                    TimePicker::make('start')->label(__('appointments.start'))->seconds(false)->format('H:i')->required(),
                    TimePicker::make('end')->label(__('appointments.end'))->seconds(false)->format('H:i')->required(),
                ])->columns(4)->addable(false)->deletable(false)->reorderable(false)->minItems(7)->maxItems(7),
            Repeater::make('closed_dates')->label(__('appointments.closed_dates'))
                ->schema([DatePicker::make('date')->label(__('appointments.date'))->required()]),
        ])->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')->livewireSubmitHandler('save')
                ->footer([Actions::make([
                    Action::make('save')->label(__('panel.common.save'))->submit('save'),
                ])]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        Validator::make(['data' => $data], [
            'data.duration' => ['required', 'integer', 'min:15', 'max:180'],
            'data.days' => ['required', 'array', 'size:7'],
            'data.days.*.day' => ['required', 'integer', Rule::in(range(1, 7)), 'distinct'],
            'data.days.*.enabled' => ['required', 'boolean'],
            'data.days.*.start' => ['required', 'date_format:H:i'],
            'data.days.*.end' => ['required', 'date_format:H:i', 'after:data.days.*.start'],
            'data.closed_dates' => ['present', 'array'],
            'data.closed_dates.*.date' => ['required', 'date_format:Y-m-d', 'distinct'],
        ])->validate();
        $data['days'] = array_values($data['days']);
        $data['closed_dates'] = array_values($data['closed_dates']);
        DB::transaction(function () use ($data): void {
            DB::table('appointment_schedule_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
            Setting::updateOrCreate(['key' => 'appointment_schedule'], ['value' => $data, 'type' => 'json']);
        });
        Notification::make()->success()->title(__('panel.common.settings_saved_successfully'))->send();
    }
}
