<?php

namespace App\Filament\Resources\AppointmentRequests\Pages;

use App\Filament\Resources\AppointmentRequests\AppointmentRequestResource;
use App\Models\AppointmentRequest;
use App\Services\AppointmentSchedule;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class AppointmentCalendar extends Page
{
    protected static string $resource = AppointmentRequestResource::class;

    protected string $view = 'filament.appointment-calendar';

    #[Url]
    public string $date = '';

    public static function canAccess(array $parameters = []): bool
    {
        return AppointmentRequestResource::canViewAny();
    }

    public function getTitle(): string
    {
        return __('appointments.calendar');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->date = $this->date ?: app(AppointmentSchedule::class)->today();
    }

    public function previousDay(): void
    {
        $this->moveDay(-1);
    }

    public function nextDay(): void
    {
        $this->moveDay(1);
    }

    public function goToToday(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->date = app(AppointmentSchedule::class)->today();
    }

    private function moveDay(int $days): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $this->date = CarbonImmutable::parse($this->date, app(AppointmentSchedule::class)->timezone())
            ->addDays($days)->toDateString();
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['date' => ['required', 'date_format:Y-m-d']]);

        $slots = app(AppointmentSchedule::class)->slots($this->date);

        return [
            'appointmentSlots' => $slots,
            'slotCounts' => collect($slots)->countBy('status'),
            'selectedDate' => CarbonImmutable::parse($this->date, app(AppointmentSchedule::class)->timezone())
                ->locale(app()->getLocale())->isoFormat('D MMMM YYYY, dddd'),
            'appointments' => AppointmentRequest::query()->whereDate('preferred_date', $this->date)->orderBy('preferred_time')->get(),
            'mailFailures' => DB::table('appointment_notifications')->whereNull('sent_at')->whereNotNull('last_error')->count(),
        ];
    }
}
