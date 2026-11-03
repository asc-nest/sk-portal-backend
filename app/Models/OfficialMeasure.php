<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialMeasure extends Model
{
    use HasFactory;

    protected $table = 'official_measures';

    protected $fillable = [
        'official_profile_id',
        'resolution_number',
        'title',
        'category',
        'authorship_role',
        'status',
        'date_approved',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'date_approved' => 'date',
        ];
    }

    protected $appends = [
        'authorship_label',
        'status_badge',
    ];

    public function getAuthorshipLabelAttribute(): string
    {
        return match ($this->authorship_role) {
            'principal_author' => 'Principal Author',
            'co_author' => 'Co-Author',
            'sponsor' => 'Sponsor',
            'co_sponsor' => 'Co-Sponsor',
            default => 'Author',
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'passed' => 'Passed',
            'adopted' => 'Adopted',
            'enacted' => 'Enacted',
            'under_review' => 'Under Review',
            'tabled' => 'Tabled',
            default => ucfirst($this->status),
        };
    }

    public function officialProfile(): BelongsTo
    {
        return $this->belongsTo(SkOfficialProfile::class, 'official_profile_id');
    }
}

