<?php

namespace App\Http\Controllers\Api\V1\Fms;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverController extends Controller
{
    use ApiResponseTrait;

    /**
     * GET /api/v1/fms/drivers
     *
     * Drivers Directory — paginated list with metrics.
     * Query params: page, per_page, search, status
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $perPage = min(max($perPage, 1), 100); // clamp 1–100

        // ── Build query with karyawan join ──────────────
        $query = Driver::query()
            ->select([
                'm_drivers.id',
                'm_drivers.karyawan_id',
                'm_drivers.driver_category',
                'm_drivers.sim_type',
                'm_drivers.sim_expiry_date',
                'm_drivers.status',
                'k.nama_karyawan',
                'k.telp1',
                'k.telp2',
                'k.no_sim_b2_umum',
                'k.no_sim_b2_umum_expiredate',
                'k.no_sim_b1',
                'k.no_sim_b1_expiredate',
                'k.no_sim_a',
                'k.no_sim_a_expiredate',
                'k.foto',
                'k.photo',
                'k.payroll_id',
            ])
            ->leftJoin('m_karyawan as k', 'k.id', '=', 'm_drivers.karyawan_id')
            ->whereNull('m_drivers.deleted_at');

        // ── Filter by status ────────────────────────────
        if ($status = $request->get('status')) {
            $dbStatus = $this->mapApiStatusToDb($status);
            if ($dbStatus) {
                $query->where('m_drivers.status', $dbStatus);
            }
        }

        // ── Search (ILIKE for Postgres) ─────────────────
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('k.nama_karyawan', 'ILIKE', "%{$search}%")
                  ->orWhere('k.telp1', 'ILIKE', "%{$search}%")
                  ->orWhere('k.telp2', 'ILIKE', "%{$search}%")
                  ->orWhere('k.payroll_id', 'ILIKE', "%{$search}%")
                  ->orWhere(DB::raw("CAST(m_drivers.id AS TEXT)"), 'ILIKE', "%{$search}%");
            });
        }

        // ── Metrics (unfiltered counts) ─────────────────
        $metrics = $this->getMetrics();

        // ── Paginate ────────────────────────────────────
        $paginator = $query->orderBy('k.nama_karyawan')->paginate($perPage);

        // ── Transform rows ──────────────────────────────
        $drivers = collect($paginator->items())->map(function ($driver) {
            $name = $driver->nama_karyawan ?? 'Unknown';
            $phone = $driver->telp1 ?? $driver->telp2 ?? '-';

            // Determine best SIM info
            $licenseType = $this->resolveLicenseType($driver);
            $licenseExpiry = $this->resolveLicenseExpiry($driver);

            return [
                'id'              => 'DRV-' . str_pad($driver->id, 3, '0', STR_PAD_LEFT),
                'name'            => $name,
                'phone'           => $phone,
                'licenseType'     => $licenseType,
                'licenseExpiry'   => $licenseExpiry,
                'status'          => $this->mapDbStatusToApi($driver->status),
                'driverCategory'  => $driver->driver_category ?? '-',
                'assignedVehicle' => '-', // Will integrate with vehicle module later
                'totalTrips'      => 0,   // Will integrate with trip module later
                'rating'          => 0,   // Will integrate with rating module later
                'avatar'          => $driver->foto
                    ?? $driver->photo
                    ?? 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=dbeafe&color=1e40af',
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Drivers retrieved successfully',
            'data'    => [
                'metrics' => $metrics,
                'drivers' => $drivers,
            ],
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'total'        => $paginator->total(),
                'per_page'     => $paginator->perPage(),
            ],
        ]);
    }

    // ── Private helpers ─────────────────────────────────

    /**
     * Get driver status metrics (counts across all drivers).
     */
    private function getMetrics(): array
    {
        $counts = Driver::whereNull('deleted_at')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'ACTIVE' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN status = 'ON_DUTY' THEN 1 ELSE 0 END) as on_duty,
                SUM(CASE WHEN status = 'ON_LEAVE' THEN 1 ELSE 0 END) as on_leave
            ")
            ->first();

        return [
            'totalDrivers' => (int) ($counts->total ?? 0),
            'onDuty'       => (int) ($counts->on_duty ?? 0),
            'available'    => (int) ($counts->available ?? 0),
            'onLeave'      => (int) ($counts->on_leave ?? 0),
        ];
    }

    /**
     * Map API status string → DB status.
     */
    private function mapApiStatusToDb(string $apiStatus): ?string
    {
        return match ($apiStatus) {
            'Available' => 'ACTIVE',
            'On Duty'   => 'ON_DUTY',
            'On Leave'  => 'ON_LEAVE',
            'Off Duty'  => 'INACTIVE',
            default     => null,
        };
    }

    /**
     * Map DB status → API status string.
     */
    private function mapDbStatusToApi(string $dbStatus): string
    {
        return match (strtoupper($dbStatus)) {
            'ACTIVE'   => 'Available',
            'ON_DUTY'  => 'On Duty',
            'ON_LEAVE' => 'On Leave',
            default    => 'Off Duty',
        };
    }

    /**
     * Resolve the best license type from m_karyawan SIM columns.
     * Priority: B2 Umum > B1 > A
     */
    private function resolveLicenseType($driver): string
    {
        if ($driver->sim_type) {
            return $driver->sim_type;
        }
        if (!empty($driver->no_sim_b2_umum)) return 'SIM B2 Umum';
        if (!empty($driver->no_sim_b1))      return 'SIM B1';
        if (!empty($driver->no_sim_a))       return 'SIM A';
        return '-';
    }

    /**
     * Resolve the best license expiry date.
     * Matches priority of resolveLicenseType.
     */
    private function resolveLicenseExpiry($driver): ?string
    {
        if ($driver->sim_expiry_date) {
            return $driver->sim_expiry_date;
        }
        if (!empty($driver->no_sim_b2_umum) && $driver->no_sim_b2_umum_expiredate) {
            return $driver->no_sim_b2_umum_expiredate;
        }
        if (!empty($driver->no_sim_b1) && $driver->no_sim_b1_expiredate) {
            return $driver->no_sim_b1_expiredate;
        }
        if (!empty($driver->no_sim_a) && $driver->no_sim_a_expiredate) {
            return $driver->no_sim_a_expiredate;
        }
        return null;
    }
}
