@extends('emails.master-layout')

@section('title', 'Notifikasi Persetujuan: ' . ($document_number ?? 'Dokumen ERP'))
@section('badge_header', 'Menunggu Approval')

@section('content')
  <h2 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 800; color: #0F172A;">
    Permohonan Persetujuan Baru
  </h2>
  <p style="margin: 0 0 20px 0; font-size: 14px; color: #475569; line-height: 1.6;">
    Halo <strong>{{ $approver_name }}</strong>, terdapat pengajuan baru di sistem ERP yang membutuhkan tinjauan dan persetujuan dari Anda:
  </p>

  <!-- Document Highlight Card -->
  <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 12px; margin-bottom: 24px; padding: 16px 20px;">
    <tr>
      <td>
        <p style="margin: 0 0 4px 0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; font-weight: 700;">Nomor Dokumen</p>
        <p style="margin: 0; font-size: 16px; font-weight: 800; color: #1E3A8A; font-family: monospace;">{{ $document_number }}</p>
      </td>
      <td align="right">
        <p style="margin: 0 0 4px 0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748B; font-weight: 700;">Jenis Dokumen</p>
        <span style="font-size: 12px; font-weight: 700; color: #0F172A; background-color: #E2E8F0; padding: 4px 10px; border-radius: 6px;">{{ $document_type }}</span>
      </td>
    </tr>
  </table>

  <!-- Metadata Table -->
  <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border: 1px solid #E2E8F0; border-radius: 12px; margin-bottom: 28px; overflow: hidden; font-size: 13px;">
    <tr style="background-color: #F8FAFC; border-bottom: 1px solid #E2E8F0;">
      <td style="padding: 10px 16px; font-weight: 600; color: #64748B; width: 38%;">Nama Pemohon</td>
      <td style="padding: 10px 16px; font-weight: 700; color: #0F172A;">{{ $requester_name }} ({{ $requester_nik }})</td>
    </tr>
    <tr style="border-bottom: 1px solid #E2E8F0;">
      <td style="padding: 10px 16px; font-weight: 600; color: #64748B;">Departemen / Divisi</td>
      <td style="padding: 10px 16px; color: #334155;">{{ $department }}</td>
    </tr>
    <tr style="background-color: #F8FAFC; border-bottom: 1px solid #E2E8F0;">
      <td style="padding: 10px 16px; font-weight: 600; color: #64748B;">Tanggal Pengajuan</td>
      <td style="padding: 10px 16px; color: #334155;">{{ $submission_date }}</td>
    </tr>
    <tr style="border-bottom: 1px solid #E2E8F0;">
      <td style="padding: 10px 16px; font-weight: 600; color: #64748B;">Durasi / Nilai</td>
      <td style="padding: 10px 16px; font-weight: 700; color: #0F172A;">{{ $amount_or_duration }}</td>
    </tr>
    <tr style="background-color: #F8FAFC;">
      <td style="padding: 10px 16px; font-weight: 600; color: #64748B; vertical-align: top;">Keterangan / Alasan</td>
      <td style="padding: 10px 16px; color: #334155;">{{ $notes }}</td>
    </tr>
  </table>

  <!-- CTA Button Section -->
  <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-bottom: 24px;">
    <tr>
      <td align="center">
        <a href="{{ $action_url }}" target="_blank" style="display: inline-block; background-color: #EA580C; color: #FFFFFF; font-weight: 700; font-size: 14px; text-decoration: none; padding: 14px 32px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(234, 88, 12, 0.3);">
          Tinjau & Beri Keputusan di ERP
        </a>
      </td>
    </tr>
  </table>

  <p style="margin: 0; font-size: 12px; color: #64748B; text-align: center; line-height: 1.5;">
    Tautan di atas akan mengarahkan Anda langsung ke lembar approval di portal ERP BCS Logistics.
  </p>
@endsection
