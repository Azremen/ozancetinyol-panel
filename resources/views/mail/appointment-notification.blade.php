<x-mail::layout>
<x-slot:header>
<tr>
<td align="center" style="padding: 32px 16px 24px;">
<p style="margin: 0; color: #285c59; font-size: 24px; font-weight: 700; letter-spacing: 1px;">Ozan Çetinyol</p>
<p style="margin: 8px 0 0; color: #64748b; font-size: 13px;">{{ __('appointments.mail_brand_subtitle') }}</p>
</td>
</tr>
</x-slot:header>

<p style="margin: 0 0 18px; color: {{ $event === 'confirmed' ? '#285c59' : '#9a6500' }}; font-size: 12px; font-weight: 700; letter-spacing: 1px;">{{ __('appointments.mail_status_'.$event) }}</p>

# {{ __('appointments.mail_'.$audience.'_'.$event.'_subject') }}

{{ __('appointments.mail_'.$audience.'_'.$event.'_body', ['name' => $details['name']]) }}

<x-mail::panel>
**{{ __('appointments.mail_details') }}**

<table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 12px; font-size: 14px;">
@if($details['date'])
<tr><th align="left" style="padding: 8px 12px 8px 0; font-weight: 400;">{{ __('appointments.date') }}</th><td style="padding: 8px 0; font-weight: 700;">{{ $details['date'] }}</td></tr>
@endif
@if($details['time'])
<tr><th align="left" style="padding: 8px 12px 8px 0; font-weight: 400;">{{ __('appointments.time') }}</th><td style="padding: 8px 0; font-weight: 700;">{{ $details['time'] }}</td></tr>
<tr><th align="left" style="padding: 8px 12px 8px 0; font-weight: 400;">{{ __('appointments.mail_timezone') }}</th><td style="padding: 8px 0;">{{ $details['timezone'] ?? config('app.timezone') }}</td></tr>
@endif
@if($audience === 'admin')
<tr><th align="left" style="padding: 8px 12px 8px 0; font-weight: 400;">{{ __('physio.name') }}</th><td style="padding: 8px 0;">{{ $details['name'] }}</td></tr>
<tr><th align="left" style="padding: 8px 12px 8px 0; font-weight: 400;">{{ __('physio.phone') }}</th><td style="padding: 8px 0;">{{ $details['phone'] }}</td></tr>
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
