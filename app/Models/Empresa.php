<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\LogsModelChanges;
use App\Traits\EmpresaScoped;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Empresa extends Model
{
    use HasFactory, LogsModelChanges, EmpresaScoped;

    protected $fillable = [
        'razao_social',
        'nome_fantasia',
        'cnpj',
        'inscricao_estadual',
        'inscricao_municipal',
        'email',
        'telefone',
        'cep',
        'endereco',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'status',
        'ativo',
        'ultima_consulta_api',
    ];

    protected $casts = [
        'ultima_consulta_api' => 'datetime',
        'ativo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Empresa $empresa) {
            // Manter consistência entre status (enum legado) e flag ativo
            $empresa->status = $empresa->ativo ? 'ativo' : 'inativo';
        });
    }

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

    public function activityLogs(): HasMany
    {
        return $this->hasMany(UserActivityLog::class, 'user_id');
    }

    public function dteMailboxes(): HasMany
    {
        return $this->hasMany(DteMailbox::class);
    }

    public function ultimaConsultaApi()
    {
        return $this->consultasApi()->latest('consultado_em')->first();
    }

    // Accessor para formatar CNPJ
    public function getCnpjFormatadoAttribute(): string
    {
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $this->cnpj);
    }

    // Mutator para limpar CNPJ
    public function setCnpjAttribute($value): void
    {
        $this->attributes['cnpj'] = preg_replace('/\D/', '', $value);
    }

    // Scope para empresas ativas
    public function scopeAtivas($query)
    {
        return $query->where('status', 'ativo')->where('ativo', true);
    }

    // Scope para ativo boolean (preferível para desativar sem deletar)
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }

    public function getLoggableAttributes(): array
    {
        return ['razao_social', 'cnpj', 'status', 'ativo', 'ultima_consulta_api'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'empresa_user')->withPivot('role')->withTimestamps();
    }
}
