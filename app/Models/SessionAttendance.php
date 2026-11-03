<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionAttendance extends Model
{
    use HasFactory;

    protected $table = 'session_attendances';

    protected $fillable = [
        'council_session_id',
        'official_profile_id',
        'status',
        'remarks',
    ];

    protected $appends = [
        'status_label',
    ];

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'present' => 'Present',
            'absent' => 'Absent',
            'excused' => 'Excused',
            'late' => 'Late',
            default => ucfirst($this->status),
        };
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CouncilSession::class, 'council_session_id');
    }

    public function official(): BelongsTo
    {
        return $this->belongsTo(SkOfficialProfile::class, 'official_profile_id');
    }
}

