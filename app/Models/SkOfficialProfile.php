<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SkOfficialProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sk_officials_profiles';

    protected $fillable = [
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'nickname',
        'gender',
        'birthdate',
        'contact_number',
        'position',
        'committee_assigned',
        'term_start',
        'term_end',
        'is_active_term',
        'bio_advocacy',
        'avatar_path',
        'official_id_number',
        'barangay_name',
        'purok_sitio',
        'educational_attainment',
        'education_degree',
        'education_school',
        'skills',
        'civil_status',
        'blood_type',
        'passed_measures_count',
        'session_attendance_rate',
        'skmt_compliant',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'term_start' => 'date',
            'term_end' => 'date',
            'is_active_term' => 'boolean',
            'passed_measures_count' => 'integer',
            'session_attendance_rate' => 'float',
            'skmt_compliant' => 'boolean',
            'skills' => 'array',
        ];
    }

    protected $appends = [
        'full_name',
        'avatar_url',
        'position_title',
    ];

    /**
     * Computed full name with middle initial and suffix
     */
    public function getFullNameAttribute(): string
    {
        $name = $this->first_name;
        if (!empty($this->middle_name)) {
            $name .= ' ' . strtoupper(substr($this->middle_name, 0, 1)) . '.';
        }
        $name .= ' ' . $this->last_name;
        if (!empty($this->suffix)) {
            $name .= ' ' . $this->suffix;
        }
        return $name;
    }

    /**
     * Computed avatar public URL
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar_path) {
            return asset('storage/' . $this->avatar_path);
        }
        return null;
    }

    /**
     * Formatted official position title
     */
    public function getPositionTitleAttribute(): string
    {
        if ($this->position === 'super_admin' || ($this->user && $this->user->hasRole('super_admin'))) {
            return 'System Administrator';
        }
        return match ($this->position) {
            'chairperson' => 'SK Chairperson',
            'kagawad' => 'SK Kagawad',
            'secretary' => 'SK Secretary',
            'treasurer' => 'SK Treasurer',
            default => 'SK Official',
        };
    }

    /**
     * Owning user account
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Authored measures and passed resolutions
     */
    public function measures(): HasMany
    {
        return $this->hasMany(OfficialMeasure::class, 'official_profile_id')->orderBy('date_approved', 'desc');
    }

    /**
     * Roll call attendance history for council sessions
     */
    public function sessionAttendances(): HasMany
    {
        return $this->hasMany(SessionAttendance::class, 'official_profile_id')
            ->join('council_sessions', 'session_attendances.council_session_id', '=', 'council_sessions.id')
            ->select('session_attendances.*', 'council_sessions.session_date', 'council_sessions.session_title', 'council_sessions.session_number', 'council_sessions.session_type')
            ->orderBy('council_sessions.session_date', 'desc');
    }

    /**
     * Recalculate and persist passed measures count
     */
    public function syncPassedMeasuresCount(): int
    {
        $count = $this->measures()
            ->whereIn('status', ['passed', 'adopted', 'enacted'])
            ->count();
        
        $this->update(['passed_measures_count' => $count]);
        return $count;
    }

    /**
     * Recalculate and persist attendance rate percentage based on roll calls
     */
    public function syncSessionAttendanceRate(): float
    {
        $total = SessionAttendance::where('official_profile_id', $this->id)->count();
        if ($total === 0) {
            return $this->session_attendance_rate ?? 100.0;
        }

        $attended = SessionAttendance::where('official_profile_id', $this->id)
            ->whereIn('status', ['present', 'late'])
            ->count();

        $rate = round(($attended / $total) * 100, 1);
        $this->update(['session_attendance_rate' => $rate]);
        return $rate;
    }
}
