@extends('emails.master-layout')

@section('title', 'Hasil Approval: ' . ($document_number ?? 'Dokumen ERP'))
@section('badge_header', $status_badge_text ?? 'Status Approval')

@section('content')
  <h2 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 800; color: #0F172A;">
    Konfirmasi Status Pengajuan Anda
  </h2>
  <p style="margin: 0 0 20px 0; font-size: 14px; color: #475569; line-height: 1.6;">
    Halo <strong>{{ $requester_name }}</strong>, pengajuan Anda dengan nomor <strong>{{ $document_number }}</strong> telah selesai ditinjau oleh atasan / bagian berwenang:
  </p>

  <!-- Decision Banner -->
  @if(($is_approved ?? true))
    <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 12px; margin-bottom: 24px; padding: 16px 20px;">
      <tr>
        <td width="36" valign="top">
          <span style="display: inline-block; width: 28px; height: 28px; background-color: #059669; color: #FFFFFF; border-radius: 50%; text-align: center; line-height: 28px; font-weight: 900; font-size: 16px;">&check;</span>
        </td>
        <td>
          <p style="margin: 0 0 2px 0; font-size: 15px; font-weight: 800; color: #166534;">
            {{ $decision_title ?? 'Pengajuan Disetujui (Approved)' }}
          </p>
          <p style="margin: 0; font-size: 13px; color: #15803D;">
            {{ $decision_summary ?? 'Pengajuan Anda telah disetujui dan saat ini sedang diproses ke tahap selanjutnya.' }}
          </p>
        </td>
      </tr>
    </table>
  @else
    <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #FFF1F2; border: 1px solid #FECDD3; border-radius: 12px; margin-bottom: 24px; padding: 16px 20px;">
      <tr>
        <td width="36" valign="top">
          <span style="display: inline-block; width: 28px; height: 28px; background-color: #E11D48; color: #FFFFFF; border-radius: 50%; text-align: center; line-height: 28px; font-weight: 900; font-size: 16px;">&times;</span>
        </td>
        <td>
          <p style="margin: 0 0 2px 0; font-size: 15px; font-weight: 800; color: #9F1239;">
            {{ $decision_title ?? 'Pengajuan Ditolak (Rejected)' }}
          </p>
          <p style="margin: 0; font-size: 13px; color: #BE123C;">
            {{ $decision_summary ?? 'Pengajuan Anda belum dapat disetujui. Silakan periksa catatan reviewer di bawah.' }}
          </p>
        </td>
      </tr>
    </table>
  @endif

  <!-- Metadata Table -->
  <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="border: 1px solid #E2E8F0; border-radius: 12px; margin-bottom: 28px; overflow: hidden; font-size: 13px;">
    <tr style="background-color: #F8FAFC; border-bottom: 1px solid #E2E8F0;">
      <td style="padding: 10px 16px; font-weight: 600; color: #64748B; width: 38%;">Jenis Pengajuan</td>
      <td style="padding: 10px 16px; font-weight: 700; color: #0F172A;">{{ $document_type }}</td>
    </tr>
    <tr style="border-bottom: 1px solid #E2E8F0;">
      <td style="padding: 10px 16px; font-weight: 600; color: #64748B;">Nomor Dokumen</td>
      <td style="padding: 10px 16px; font-family: monospace; font-weight: 700; color: #1E3A8A;">{{ $document_number }}</td>
    </tr>
    <tr style="background-color: #F8FAFC; border-bottom: 1px solid #E2E8F0;">
      <td style="padding: 10px 16px; font-weight: 600; color: #64748B;">Ditinjau Oleh</td>
      <td style="padding: 10px 16px; font-weight: 700; color: #0F172A;">{{ $reviewer_name }}</td>
    </tr>
    <tr style="border-bottom: 1px solid #E2E8F0;">
      <td style="padding: 10px 16px; font-weight: 600; color: #64748B;">Tanggal Keputusan</td>
      <td style="padding: 10px 16px; color: #334155;">{{ $decision_date }}</td>
    </tr>
    <tr style="background-color: #F8FAFC;">
      <td style="padding: 10px 16px; font-weight: 600; color: #64748B; vertical-align: top;">Catatan Atasan / Reviewer</td>
      <td style="padding: 10px 16px; color: #334155;">{{ $reviewer_notes ?? '-' }}</td>
    </tr>
  </table>

  <!-- CTA Button Section -->
  <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-bottom: 24px;">
    <tr>
      <td align="center">
        <a href="{{ $action_url }}" target="_blank" style="display: inline-block; background-color: #0F172A; color: #FFFFFF; font-weight: 700; font-size: 14px; text-decoration: none; padding: 14px 32px; border-radius: 12px;">
          Lihat Rincian Pengajuan di ERP
        </a>
      </td>
    </tr>
  </table>

  <p style="margin: 0; font-size: 12px; color: #64748B; text-align: center; line-height: 1.5;">
    Dokumen resmi telah dicatat ke dalam database riwayat operasional ERP BCS Logistics.
  </p>
@endsection
