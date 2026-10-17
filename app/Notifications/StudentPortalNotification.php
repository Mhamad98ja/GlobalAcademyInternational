<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class StudentPortalNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly ?string $actionUrl = null,
        private readonly ?int $subjectId = null,
        private readonly ?int $registrationId = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'audience' => 'student',
            'subject_id' => $this->subjectId,
            'student_registration_id' => $this->registrationId,
        ];
    }
}
