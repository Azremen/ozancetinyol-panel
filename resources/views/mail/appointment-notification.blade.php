<x-mail::layout>
<x-slot:header>
<tr>
<td align="center" class="appointment-header">
<table cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center" valign="middle" width="44" height="44" class="appointment-brand-mark">OC</td>
<td class="appointment-brand">
<p class="appointment-brand-name">Ozan Çetinyol</p>
<p class="appointment-brand-subtitle">{{ __('appointments.mail_brand_subtitle') }}</p>
</td>
</tr>
</table>
</td>
</tr>
</x-slot:header>

<p class="appointment-status appointment-status--{{ $event === 'confirmed' ? 'confirmed' : 'received' }}">{{ __('appointments.mail_status_'.$event) }}</p>

# {{ __('appointments.mail_'.$audience.'_'.$event.'_subject') }}

{{ __('appointments.mail_'.$audience.'_'.$event.'_body', ['name' => $details['name']]) }}

<x-mail::panel>
**{{ __('appointments.mail_details') }}**

<table width="100%" cellpadding="0" cellspacing="0" class="appointment-details">
@if($details['date'])
<tr><th align="left">{{ __('appointments.date') }}</th><td class="appointment-detail-strong">{{ $details['date'] }}</td></tr>
@endif
@if($details['time'])
<tr><th align="left">{{ __('appointments.time') }}</th><td class="appointment-detail-strong">{{ $details['time'] }}</td></tr>
<tr><th align="left">{{ __('appointments.mail_timezone') }}</th><td>{{ $details['timezone'] ?? config('app.timezone') }}</td></tr>
@endif
@if($audience === 'admin')
<tr><th align="left">{{ __('physio.name') }}</th><td>{{ $details['name'] }}</td></tr>
<tr><th align="left">{{ __('physio.phone') }}</th><td>{{ $details['phone'] }}</td></tr>
@endif
</table>
</x-mail::panel>

{{ __('appointments.mail_next_'.$audience.'_'.$event) }}

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} Ozan Çetinyol<br>
{{ __('appointments.mail_footer') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
