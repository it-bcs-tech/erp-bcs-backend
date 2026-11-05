@extends('emails.master-layout')

@section('title', $announcement_title ?? 'Pengumuman ERP BCS Logistics')
@section('badge_header', $badge_label ?? 'Informasi Korporat')

@section('content')
  <h2 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 800; color: #0F172A;">
    {{ $announcement_title }}
  </h2>
  <p style="margin: 0 0 20px 0; font-size: 14px; color: #475569; line-height: 1.6;">
    Kepada Yth. Rekan Karyawan / Pengguna ERP BCS,
  </p>
  
  <div style="font-size: 14px; color: #334155; line-height: 1.7; margin-bottom: 24px;">
    {!! $announcement_body !!}
  </div>

  <!-- Information Box (Credentials / Important Notice) -->
  @if(!empty($info_box_content))
    <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #F1F5F9; border-left: 4px solid #1E3A8A; border-radius: 8px; margin-bottom: 28px; padding: 16px 20px;">
      <tr>
        <td>
          <p style="margin: 0 0 6px 0; font-size: 12px; font-weight: 800; text-transform: uppercase; color: #1E3A8A; letter-spacing: 0.05em;">
            {{ $info_box_title ?? 'Catatan Penting' }}
          </p>
          <p style="margin: 0; font-size: 13px; color: #475569; line-height: 1.6;">
            {!! $info_box_content !!}
          </p>
        </td>
      </tr>
    </table>
  @endif

  <!-- CTA Button Section -->
  <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-bottom: 24px;">
    <tr>
      <td align="center">
        <a href="{{ $action_url ?? url('/') }}" target="_blank" style="display: inline-block; background-color: #1E3A8A; color: #FFFFFF; font-weight: 700; font-size: 14px; text-decoration: none; padding: 14px 32px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(30, 58, 138, 0.25);">
          {{ $button_text ?? 'Buka Portal ERP BCS' }}
        </a>
      </td>
    </tr>
  </table>

  <p style="margin: 0; font-size: 12px; color: #64748B; text-align: center; line-height: 1.5;">
    Jaga kerahasiaan akun dan kata sandi Anda. Tim IT BCS tidak pernah meminta password melalui pesan pribadi.
  </p>
@endsection
