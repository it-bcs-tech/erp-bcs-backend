<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnnouncementAccountMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    /**
     * Create a new message instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $title = $this->data['announcement_title'] ?? 'Pengumuman Resmi';
        return new Envelope(
            subject: $title . ' - BCS Logistics ERP',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.announcement-account',
            with: [
                'announcement_title' => $this->data['announcement_title'] ?? 'Pengumuman Resmi',
                'badge_label'        => $this->data['badge_label'] ?? 'Informasi Korporat',
                'announcement_body'  => $this->data['announcement_body'] ?? 'Berikut informasi terbaru dari manajemen.',
                'info_box_title'     => $this->data['info_box_title'] ?? null,
                'info_box_content'   => $this->data['info_box_content'] ?? null,
                'action_url'         => $this->data['action_url'] ?? config('app.url', 'https://erp.bcslabs.tech'),
                'button_text'        => $this->data['button_text'] ?? 'Buka Portal ERP BCS',
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
