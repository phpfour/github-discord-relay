<?php

namespace App\Models;

use Database\Factories\MemberIdentityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberIdentity extends Model
{
    /** @use HasFactory<MemberIdentityFactory> */
    use HasFactory;

    protected $fillable = [
        'member_id',
        'source',
        'external_id',
    ];

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
