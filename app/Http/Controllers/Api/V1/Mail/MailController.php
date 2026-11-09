<?php

namespace App\Http\Controllers\Api\V1\Mail;

use App\Http\Controllers\Controller;
use App\Mail\AnnouncementAccountMail;
use App\Mail\ApprovalNotificationMail;
use App\Mail\ApprovalResultMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class MailController extends Controller
{
    /**
     * POST /api/v1/mail/test
     * Endpoint untuk melakukan live test pengiriman email dari server
     */
    public function testSend(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to'   => 'required|email',
            'type' => 'nullable|in:approval_notification,approval_result,announcement',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $to = $request->input('to');
        $type = $request->input('type', 'approval_notification');
        $frontendUrl = config('app.frontend_url', 'https://erp.bcslabs.tech');

        try {
            switch ($type) {
                case 'approval_result':
                    $mailable = new ApprovalResultMail([
                        'document_number'   => 'CUTI/2026/XI/TEST',
                        'status_badge_text' => 'Disetujui',
                        'requester_name'    => 'Pengguna Uji Coba ERP',
                        'is_approved'       => true,
                        'decision_title'    => 'Pengajuan Disetujui (Approved)',
                        'decision_summary'  => 'Permohonan Anda telah disetujui oleh manajemen.',
                        'document_type'     => 'Permohonan Cuti Tahunan',
                        'reviewer_name'     => auth('api')->user()->name ?? 'HRD Manager',
                        'decision_date'     => now()->translatedFormat('d F Y H:i') . ' WIB',
                        'reviewer_notes'    => 'Disetujui untuk keperluan verifikasi email server Biznet.',
                        'action_url'        => $frontendUrl . '/hris/leave',
                    ]);
                    break;

                case 'announcement':
                    $mailable = new AnnouncementAccountMail([
                        'announcement_title' => 'Pengujian Layanan Email ERP BCS Logistics',
                        'badge_label'        => 'Notifikasi Server',
                        'announcement_body'  => '<p>Email ini dikirim secara otomatis dari server Biznet ERP BCS Logistics untuk memverifikasi fungsionalitas pengiriman email dan keabsahan template HTML korporat.</p>',
                        'info_box_title'     => 'Informasi Server & Layanan',
                        'info_box_content'   => '<strong>Server:</strong> Biznet Cloud<br><strong>Status:</strong> Terkoneksi & Aktif<br><strong>Pengirim:</strong> ' . config('mail.from.address'),
                        'action_url'         => $frontendUrl,
                        'button_text'        => 'Buka Portal ERP',
                    ]);
                    break;

                case 'approval_notification':
                default:
                    $mailable = new ApprovalNotificationMail([
                        'approver_name'      => 'Bambang Sudibyo (Manager Operasional)',
                        'document_number'    => 'CUTI/2026/XI/TEST',
                        'document_type'      => 'Permohonan Cuti Tahunan (Test Server)',
                        'requester_name'     => 'Ahmad Subarjo',
                        'requester_nik'      => '20240911',
                        'department'         => 'Fleet Operations — Cilegon',
                        'submission_date'    => now()->translatedFormat('d F Y'),
                        'amount_or_duration' => '3 Hari Kerja',
                        'notes'              => 'Pengujian pengiriman live dari server ERP BCS Logistics.',
                        'action_url'         => $frontendUrl . '/hris/leave',
                    ]);
                    break;
            }

            Mail::to($to)->send($mailable);

            return response()->json([
                'status'  => 'success',
                'message' => "Email uji coba ({$type}) berhasil dikirim ke {$to}",
                'data'    => [
                    'recipient' => $to,
                    'type'      => $type,
                    'mailer'    => config('mail.default'),
                    'host'      => config('mail.mailers.smtp.host'),
                    'port'      => config('mail.mailers.smtp.port'),
                    'username'  => config('mail.mailers.smtp.username'),
                ],
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengirim email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/auth/mail/debug
     * Diagnosa status konfigurasi email dan log mailer server Biznet
     */
    public function debugConfig(): JsonResponse
    {
        $logFile = storage_path('logs/laravel.log');
        $recentLogs = [];
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $recentLines = array_slice($lines, -50);
            foreach ($recentLines as $line) {
                if (stripos($line, 'mail') !== false || stripos($line, 'error') !== false || stripos($line, 'warning') !== false) {
                    $recentLogs[] = $line;
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'config' => [
                'default'   => config('mail.default'),
                'host'      => config('mail.mailers.smtp.host'),
                'port'      => config('mail.mailers.smtp.port'),
                'username'  => config('mail.mailers.smtp.username'),
                'from'      => config('mail.from'),
            ],
            'recent_mail_logs' => $recentLogs,
        ]);
    }
}
