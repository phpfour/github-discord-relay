<?php

namespace App\Models;

use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'discord_user_id',
    ];

    /**
     * @return HasMany<MemberIdentity, $this>
     */
    public function identities(): HasMany
    {
        return $this->hasMany(MemberIdentity::class);
    }

    /**
     * @return HasMany<MemberIdentity, $this>
     */
    public function githubIdentities(): HasMany
    {
        return $this->identities()->where('source', 'github');
    }

    /**
     * @return HasMany<MemberIdentity, $this>
     */
    public function linearIdentities(): HasMany
    {
        return $this->identities()->where('source', 'linear');
    }

    /**
     * The Discord mention tag (e.g. "<@538057585698537506>").
     */
    public function discordTag(): string
    {
        return "<@{$this->discord_user_id}>";
    }
}
