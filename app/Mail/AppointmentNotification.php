<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AppointmentNotification extends Mailable
{
    public function __construct(
        public array $details,
        public string $event,
        public string $audience,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('appointments.mail_'.$this->audience.'_'.$this->event.'_subject'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.appointment-notification');
    }
}
