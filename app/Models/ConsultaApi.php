<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\EmpresaScoped;

class ConsultaApi extends Model
{
    use HasFactory, EmpresaScoped;

    protected $table = 'consultas_api';

    protected $fillable = [
        'empresa_id',
        'certificado_id',
        'tipo_consulta',
        'parametro_consulta',
        'resposta_data',
        'resposta_header',
        'site_receipts',
        'response_code',
        'code_message',
        'request_id',
        'errors',
        'sucesso',
        'preco',
        'tempo_resposta_ms',
        'consultado_em',
    ];

    protected $casts = [
        'resposta_data' => 'array',
        'resposta_header' => 'array',
        'site_receipts' => 'array',
        'errors' => 'array',
        'sucesso' => 'boolean',
        'preco' => 'decimal:2',
        'consultado_em' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function certificado(): BelongsTo
    {
        return $this->belongsTo(Certificado::class);
    }

    public function automacaoExecucao(): BelongsTo
    {
        return $this->belongsTo(AutomacaoExecucao::class);
    }

    public function dteMessages(): HasMany
    {
        return $this->hasMany(DteMessage::class);
    }

    // Scope para consultas bem-sucedidas
    public function scopeSucesso($query)
    {
        return $query->where('sucesso', true);
    }

    // Scope por tipo de consulta
    public function scopeTipo($query, string $tipo)
    {
        return $query->where('tipo_consulta', $tipo);
    }
}
