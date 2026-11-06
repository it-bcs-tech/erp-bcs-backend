<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApprovalResultMail extends Mailable
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
        $status = ($this->data['is_approved'] ?? true) ? '[DISETUJUI]' : '[DITOLAK]';
        return new Envelope(
            subject: $status . ' Hasil Pengajuan: ' . $docNum . ' - BCS Logistics ERP',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $isApproved = $this->data['is_approved'] ?? true;

        return new Content(
            view: 'emails.approval-result',
            with: [
                'document_number'    => $this->data['document_number'] ?? '-',
                'status_badge_text'  => $this->data['status_badge_text'] ?? ($isApproved ? 'Disetujui' : 'Ditolak'),
                'requester_name'     => $this->data['requester_name'] ?? 'Karyawan',
                'is_approved'        => $isApproved,
                'decision_title'     => $this->data['decision_title'] ?? ($isApproved ? 'Pengajuan Disetujui (Approved)' : 'Pengajuan Ditolak (Rejected)'),
                'decision_summary'   => $this->data['decision_summary'] ?? ($isApproved ? 'Pengajuan Anda telah disetujui dan saat ini sedang diproses ke tahap selanjutnya.' : 'Pengajuan Anda belum dapat disetujui. Silakan periksa catatan reviewer di bawah.'),
                'document_type'      => $this->data['document_type'] ?? 'Permohonan',
                'reviewer_name'      => $this->data['reviewer_name'] ?? 'Tim HR / Atasan',
                'decision_date'      => $this->data['decision_date'] ?? date('d M Y H:i'),
                'reviewer_notes'     => $this->data['reviewer_notes'] ?? '-',
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
