<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $connection = 'pgsql';
    protected $table = 'activity_log';

    // Spatie Activity Log columns:
    // id, log_name, description, subject_type, subject_id,
    // causer_type, causer_id, properties, event, batch_uuid, created_at, updated_at
    protected $fillable = [
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
        'event',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    /**
     * Accessor: map 'log_name' or 'event' as 'type' for backward compat.
     */
    public function getTypeAttribute()
    {
        return $this->attributes['log_name'] ?? $this->attributes['event'] ?? 'activity';
    }

    /**
     * Accessor: map 'properties' as 'metadata' for backward compat.
     */
    public function getMetadataAttribute()
    {
        $props = $this->attributes['properties'] ?? '[]';
        return is_string($props) ? json_decode($props, true) : $props;
    }
}
