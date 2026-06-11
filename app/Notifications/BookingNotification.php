<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly Appointment $appointment,
        private readonly string $event
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return match ($this->event) {
            'booked' => (new MailMessage)
                ->subject('Appointment Confirmed')
                ->line('Your appointment has been confirmed.')
                ->line('Reference: ' . $this->appointment->reference_number)
                ->line('Date: ' . $this->appointment->slot->date->format('d M Y'))
                ->line('Time: ' . $this->appointment->slot->start_time),

            'cancelled' => (new MailMessage)
                ->subject('Appointment Cancelled')
                ->line('Your appointment has been cancelled.')
                ->line('Reference: ' . $this->appointment->reference_number),

            'rescheduled' => (new MailMessage)
                ->subject('Appointment Rescheduled')
                ->line('Your appointment has been rescheduled.')
                ->line('Reference: ' . $this->appointment->reference_number)
                ->line('New Date: ' . $this->appointment->slot->date->format('d M Y'))
                ->line('New Time: ' . $this->appointment->slot->start_time),
        };
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'appointment_id'   => $this->appointment->id,
            'reference_number' => $this->appointment->reference_number,
            'event'            => $this->event,
        ];
    }
}
