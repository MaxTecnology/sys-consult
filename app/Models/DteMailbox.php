<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\EmpresaScoped;

class DteMailbox extends Model
{
    use HasFactory, EmpresaScoped;

    protected $fillable = [
        'empresa_id',
        'canal',
        'tipo',
        'identificador_externo',
        'ultima_sincronizacao',
        'ultima_mensagem_recebida_em',
        'total_mensagens',
        'total_nao_lidas',
        'configuracoes',
        'ativo',
    ];

    protected $casts = [
        'ultima_sincronizacao' => 'datetime',
        'ultima_mensagem_recebida_em' => 'datetime',
        'configuracoes' => 'array',
        'ativo' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(DteMessage::class);
    }

    public function mensagensNaoLidas(): HasMany
    {
        return $this->mensagens()->where('lida_sefaz', false);
    }
}
