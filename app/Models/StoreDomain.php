<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StoreDomain extends Model
{
    use HasFactory;

    /** DNS does not point at us yet. Nothing is served for this hostname. */
    public const STATUS_PENDING = 'pending';

    /** DNS resolves to us; the hostname is served and may hold a certificate. */
    public const STATUS_ACTIVE = 'active';

    /** Checked repeatedly and still wrong. Not terminal — the merchant retries. */
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'store_id',
        'domain',
        'is_primary',
        'status',
        'verification_token',
        'verified_at',
        'last_checked_at',
        'check_attempts',
        'last_error',
        'ssl_issued_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'verified_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'ssl_issued_at' => 'datetime',
        'check_attempts' => 'integer',
    ];

    protected $hidden = ['verification_token'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public static function mintToken(): string
    {
        return 'matgarpro-verify=' . Str::random(32);
    }

    /** Is this hostname actually serving the store right now? */
    public function isServing(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Apex domains cannot hold a CNAME, so they get different instructions. */
    public function isApex(): bool
    {
        return substr_count($this->domain, '.') === 1;
    }

    /**
     * What the merchant has to paste into their DNS panel. Apex gets an A
     * record (a CNAME at the apex is invalid); anything deeper gets a CNAME,
     * which survives us changing IPs.
     */
    public function dnsInstructions(): array
    {
        $ips = config('storefront.dns.a');

        if ($this->isApex()) {
            return [
                [
                    'type' => 'A',
                    'name' => '@',
                    'value' => $ips,
                    'note' => 'سجل A على الدومين الأساسي',
                ],
                [
                    'type' => 'CNAME',
                    'name' => 'www',
                    'value' => config('storefront.dns.cname'),
                    'note' => 'عشان www تشتغل كمان',
                ],
            ];
        }

        return [
            [
                'type' => 'CNAME',
                'name' => Str::before($this->domain, '.'),
                'value' => config('storefront.dns.cname'),
                'note' => 'سجل CNAME على النطاق الفرعي',
            ],
        ];
    }
}
