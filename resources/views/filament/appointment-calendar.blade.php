<x-filament-panels::page>
    <div class="appointment-calendar">
        <x-filament::section>
            <div class="ac-toolbar">
                <div>
                    <p class="ac-eyebrow">{{ __('appointments.daily_overview') }}</p>
                    <h2 class="ac-date-heading">{{ $selectedDate }}</h2>
                    <p class="ac-muted">{{ __('appointments.timezone_hint', ['timezone' => config('app.timezone')]) }}</p>
                </div>
                <div class="ac-date-controls">
                    <x-filament::icon-button icon="heroicon-m-chevron-left" :label="__('appointments.previous_day')" wire:click="previousDay" wire:loading.attr="disabled" />
                    <div>
                        <label class="ac-date-label" for="calendar-date">{{ __('appointments.date') }}</label>
                        <x-filament::input.wrapper>
                            <x-filament::input id="calendar-date" type="date" wire:model.live="date" />
                        </x-filament::input.wrapper>
                    </div>
                    <x-filament::icon-button icon="heroicon-m-chevron-right" :label="__('appointments.next_day')" wire:click="nextDay" wire:loading.attr="disabled" />
                    <x-filament::button color="gray" wire:click="goToToday" wire:loading.attr="disabled">{{ __('panel.common.today') }}</x-filament::button>
                </div>
            </div>
            @error('date') <p class="ac-error" role="alert">{{ $message }}</p> @enderror
        </x-filament::section>

        @if($mailFailures)
            <div class="ac-warning" role="alert">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="ac-icon" />
                <p>{{ __('appointments.mail_failures', ['count' => $mailFailures]) }}</p>
            </div>
        @endif

        <div class="ac-stats">
            @foreach(['available' => 'heroicon-o-check-circle', 'busy' => 'heroicon-o-calendar-days', 'past' => 'heroicon-o-clock'] as $status => $icon)
                <div class="ac-stat ac-status-{{ $status }}">
                    <x-filament::icon :icon="$icon" class="ac-icon" />
                    <div>
                        <span class="ac-stat-number">{{ $slotCounts[$status] ?? 0 }}</span>
                        <span class="ac-stat-label">{{ __('appointments.'.$status) }}</span>
                    </div>
                </div>
            @endforeach
            <div class="ac-stat">
                <x-filament::icon icon="heroicon-o-user-group" class="ac-icon" />
                <div>
                    <span class="ac-stat-number">{{ $appointments->count() }}</span>
                    <span class="ac-stat-label">{{ __('physio.appointment_requests') }}</span>
                </div>
            </div>
        </div>

        <div class="ac-layout" wire:loading.class="ac-loading">
            <x-filament::section :heading="__('appointments.day_slots')" :description="__('appointments.calendar_hint')">
                <div class="ac-slots">
                    @forelse($appointmentSlots as $slot)
                        <div class="ac-slot ac-status-{{ $slot['status'] }}" wire:key="slot-{{ $date }}-{{ $slot['time'] }}">
                            <div class="ac-slot-time">{{ $slot['time'] }} <span>– {{ $slot['end'] }}</span></div>
                            <x-filament::badge :color="match($slot['status']) { 'busy' => 'danger', 'available' => 'success', default => 'gray' }">
                                {{ __('appointments.'.$slot['status']) }}
                            </x-filament::badge>
                        </div>
                    @empty
                        <div class="ac-empty">
                            <x-filament::icon icon="heroicon-o-calendar-days" class="ac-empty-icon" />
                            <p>{{ __('appointments.no_slots') }}</p>
                            @if(\App\Filament\Pages\AppointmentSettings::canAccess())
                                <x-filament::button color="gray" tag="a" :href="\App\Filament\Pages\AppointmentSettings::getUrl()" icon="heroicon-o-cog-6-tooth">
                                    {{ __('appointments.settings') }}
                                </x-filament::button>
                            @endif
                        </div>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section :heading="__('appointments.day_requests')">
                <div class="ac-requests">
                    @forelse($appointments as $appointment)
                        <article class="ac-request" wire:key="appointment-{{ $appointment->id }}">
                            <div class="ac-request-header">
                                <span class="ac-request-time">{{ $appointment->preferred_time ? substr($appointment->preferred_time, 0, 5) : __('appointments.time_missing') }}</span>
                                <x-filament::badge :color="match($appointment->status) { 'confirmed' => 'success', 'pending' => 'warning', 'contacted' => 'info', default => 'gray' }">
                                    {{ __('physio.status_'.$appointment->status) }}
                                </x-filament::badge>
                            </div>
                            <h3 class="ac-request-name">{{ $appointment->name }}</h3>
                            @if(\App\Filament\Resources\AppointmentRequests\AppointmentRequestResource::canEdit($appointment))
                                <x-filament::button color="gray" size="sm" tag="a" :href="\App\Filament\Resources\AppointmentRequests\AppointmentRequestResource::getUrl('edit', ['record' => $appointment])" icon="heroicon-m-arrow-top-right-on-square">
                                    {{ __('appointments.edit') }}
                                </x-filament::button>
                            @endif
                        </article>
                    @empty
                        <div class="ac-empty">
                            <x-filament::icon icon="heroicon-o-user-group" class="ac-empty-icon" />
                            <p>{{ __('appointments.no_requests') }}</p>
                        </div>
                    @endforelse
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
