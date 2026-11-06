<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApprovalNotificationMail extends Mailable
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
        $docNum = $this->data['document_number'] ?? 'Dokumen ERP';
        return new Envelope(
            subject: 'Notifikasi Persetujuan: ' . $docNum . ' - BCS Logistics ERP',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.approval-notification',
            with: [
                'approver_name'      => $this->data['approver_name'] ?? 'Bapak/Ibu Atasan',
                'document_number'    => $this->data['document_number'] ?? '-',
                'document_type'      => $this->data['document_type'] ?? 'Permohonan',
                'requester_name'     => $this->data['requester_name'] ?? 'Karyawan',
                'requester_nik'      => $this->data['requester_nik'] ?? '-',
                'department'         => $this->data['department'] ?? 'Operasional',
                'submission_date'    => $this->data['submission_date'] ?? date('d M Y'),
                'amount_or_duration' => $this->data['amount_or_duration'] ?? '-',
                'notes'              => $this->data['notes'] ?? '-',
                'action_url'         => $this->data['action_url'] ?? config('app.url', 'https://erp.bcslabs.tech'),
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
