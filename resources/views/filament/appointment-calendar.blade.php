<x-filament-panels::page>
    <x-filament::section>
        <label for="calendar-date">{{ __('appointments.date') }}</label>
        <x-filament::input.wrapper>
            <x-filament::input id="calendar-date" type="date" wire:model.live="date" />
        </x-filament::input.wrapper>
        @error('date') <p>{{ $message }}</p> @enderror
        <p>{{ __('appointments.timezone_hint', ['timezone' => config('app.timezone')]) }}</p>
    </x-filament::section>
    @if($mailFailures)
        <x-filament::section>
            <p role="alert">{{ __('appointments.mail_failures', ['count' => $mailFailures]) }}</p>
        </x-filament::section>
    @endif
    <x-filament::section :heading="__('appointments.calendar')">
        <div class="flex flex-wrap gap-3">
            @forelse($appointmentSlots as $slot)
                <x-filament::badge :color="match($slot['status']) { 'busy' => 'danger', 'available' => 'success', default => 'gray' }">
                    {{ $slot['time'] }}–{{ $slot['end'] }}: {{ __('appointments.'.$slot['status']) }}
                </x-filament::badge>
            @empty
                <p>{{ __('appointments.no_slots') }}</p>
            @endforelse
        </div>
    </x-filament::section>
    <x-filament::section :heading="__('physio.appointment_requests')">
        @foreach($appointments as $appointment)
            <div class="py-2">
                {{ $appointment->preferred_time ?? __('appointments.time_missing') }} —
                {{ $appointment->name }} — {{ __('physio.status_'.$appointment->status) }}
                @if(\App\Filament\Resources\AppointmentRequests\AppointmentRequestResource::canEdit($appointment))
                    <a class="underline" href="{{ \App\Filament\Resources\AppointmentRequests\AppointmentRequestResource::getUrl('edit', ['record' => $appointment]) }}">{{ __('appointments.edit') }}</a>
                @endif
            </div>
        @endforeach
    </x-filament::section>
</x-filament-panels::page>
