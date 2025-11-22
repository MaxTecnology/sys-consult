<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\LogsModelChanges;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, LogsModelChanges;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
        'ativo',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'ativo' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return (bool) $this->ativo;
    }

    public function getLoggableAttributes(): array
    {
        return ['name', 'email', 'role', 'ativo'];
    }

    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_user')->withPivot('role')->withTimestamps();
    }

    public function empresaIds(): array
    {
        return $this->empresas()->pluck('empresas.id')->all();
    }

    public function hasEmpresa(int $empresaId, array $roles = ['owner', 'editor', 'viewer']): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->empresas()
            ->where('empresas.id', $empresaId)
            ->whereIn('empresa_user.role', $roles)
            ->exists();
    }
}
