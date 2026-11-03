<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CouncilSession extends Model
{
    use HasFactory;

    protected $table = 'council_sessions';

    protected $fillable = [
        'barangay_name',
        'session_number',
        'session_title',
        'session_type',
        'session_date',
        'venue',
        'presided_by',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
        ];
    }

    protected $appends = [
        'formatted_type',
        'attendance_stats',
    ];

    public function getFormattedTypeAttribute(): string
    {
        return $this->session_type === 'special' ? 'Special Session' : 'Regular Session';
    }

    public function getAttendanceStatsAttribute(): array
    {
        $attendances = $this->attendances;
        return [
            'total' => $attendances->count(),
            'present' => $attendances->where('status', 'present')->count(),
            'absent' => $attendances->where('status', 'absent')->count(),
            'excused' => $attendances->where('status', 'excused')->count(),
            'late' => $attendances->where('status', 'late')->count(),
        ];
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(SessionAttendance::class, 'council_session_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

