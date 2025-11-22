<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\LogsModelChanges;

class Certificado extends Model
{
    use HasFactory, LogsModelChanges;

    protected $fillable = [
        'nome',
        'pkcs12_cert_encrypted',
        'pkcs12_pass_encrypted',
        'token_api',
        'chave_criptografia',
        'status',
        'ativo',
        'validade',
        'observacoes',
    ];

    protected $casts = [
        'validade' => 'date',
        'ativo' => 'boolean',
    ];

    protected $hidden = [
        'pkcs12_cert_encrypted',
        'pkcs12_pass_encrypted',
        'token_api',
        'chave_criptografia',
    ];

    public function consultasApi(): HasMany
    {
        return $this->hasMany(ConsultaApi::class);
    }

    public function automacoes(): HasMany
    {
        return $this->hasMany(EmpresaAutomacao::class);
    }

    public function automacoesAtivas(): HasMany
    {
        return $this->hasMany(EmpresaAutomacao::class)->ativas();
    }

    // Scope para certificados ativos
    public function scopeAtivos($query)
    {
        return $query->where('status', 'ativo');
    }

    // Scope para certificados válidos
    public function scopeValidos($query)
    {
        return $query->where('validade', '>', now())->orWhereNull('validade');
    }

    // Accessor para verificar se está vencido
    public function getVencidoAttribute(): bool
    {
        return $this->validade && $this->validade->isPast();
    }

    // Accessor para dias até vencer
    public function getDiasParaVencerAttribute(): ?int
    {
        return $this->validade ? now()->diffInDays($this->validade, false) : null;
    }

    public function getLoggableAttributes(): array
    {
        return ['status', 'ativo', 'validade', 'nome'];
    }
}
