<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', 'BCS Logistics ERP Notification')</title>
  <!--[if mso]>
  <style type="text/css">
    body, table, td {font-family: Arial, Helvetica, sans-serif !important;}
  </style>
  <![endif]-->
</head>
<body style="margin: 0; padding: 0; background-color: #F8FAFC; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1E293B; -webkit-font-smoothing: antialiased; line-height: 1.6;">
  <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #F8FAFC; padding: 24px 12px;">
    <tr>
      <td align="center">
        <!-- Main Container Card (Max-Width 600px) -->
        <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 600px; background-color: #FFFFFF; border-radius: 16px; overflow: hidden; border: 1px solid #E2E8F0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);">
          
          <!-- Top Accent Bar (BCS Navy & Orange) -->
          <tr>
            <td style="height: 6px; background: linear-gradient(90deg, #0F172A 0%, #1E3A8A 65%, #EA580C 100%);"></td>
          </tr>

          <!-- Header Section -->
          <tr>
            <td style="padding: 28px 32px 20px 32px; border-bottom: 1px solid #F1F5F9;">
              <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td align="left" valign="middle">
                    <img src="https://bcs-logistics.co.id/assets/images/logoo.png" alt="BCS Logistics Logo" height="36" style="height: 36px; max-width: 180px; object-contain: contain; display: block; border: 0;" />
                  </td>
                  <td align="right" valign="middle">
                    <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #64748B; background-color: #F1F5F9; padding: 6px 12px; border-radius: 9999px; border: 1px solid #E2E8F0;">
                      @yield('badge_header', 'ERP Notification')
                    </span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Content Body -->
          <tr>
            <td style="padding: 32px;">
              @yield('content')
            </td>
          </tr>

          <!-- Footer Section -->
          <tr>
            <td style="padding: 24px 32px; background-color: #F8FAFC; border-top: 1px solid #E2E8F0; text-align: center;">
              <p style="margin: 0 0 8px 0; font-size: 12px; font-weight: 700; color: #0F172A; text-transform: uppercase; letter-spacing: 0.05em;">
                PT Buana Centra Swakarsa (BCS Logistics)
              </p>
              <p style="margin: 0 0 12px 0; font-size: 11px; color: #64748B; line-height: 1.5;">
                Jl. Raya Merak KM 115, Gerem, Grogol, Kota Cilegon, Banten 42438<br>
                Telp: (0254) 571234 &bull; Web: <a href="https://bcs-logistics.co.id" style="color: #EA580C; text-decoration: none;">bcs-logistics.co.id</a>
              </p>
              <div style="height: 1px; background-color: #E2E8F0; margin: 12px 0;"></div>
              <p style="margin: 0; font-size: 10px; color: #94A3B8; line-height: 1.4;">
                Email ini dikirim secara otomatis oleh sistem <strong>ERP BCS</strong>. Harap tidak membalas langsung ke alamat pengirim ini.<br>
                Jika Anda memiliki pertanyaan teknis, hubungi Departemen IT di <a href="mailto:it-support@bcs-logistics.co.id" style="color: #64748B; text-decoration: underline;">it-support@bcs-logistics.co.id</a>.
              </p>
            </td>
          </tr>
        </table>
        
        <!-- Bottom Sub-Footer -->
        <table role="presentation" width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 600px; margin-top: 16px;">
          <tr>
            <td align="center" style="font-size: 11px; color: #94A3B8;">
              &copy; {{ date('Y') }} PT Buana Centra Swakarsa. All Rights Reserved.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
