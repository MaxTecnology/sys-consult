<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\EmpresaScoped;

class DteMessage extends Model
{
    use HasFactory, EmpresaScoped;

    protected $fillable = [
        'dte_mailbox_id',
        'consulta_api_id',
        'automacao_execucao_id',
        'message_uid',
        'hash_unico',
        'remetente',
        'assunto',
        'protocolo',
        'categoria',
        'numero_documento',
        'lida_sefaz',
        'data_envio',
        'data_leitura_sefaz',
        'disponivel_ate',
        'status_interno',
        'responsavel_id',
        'primeira_visualizacao_em',
        'ultima_interacao_em',
        'resumo',
        'conteudo_texto',
        'conteudo_html',
        'anexos',
        'metadados',
        'requere_atencao',
        'visualizado_por',
        'visualizado_em',
    ];

    protected $casts = [
        'lida_sefaz' => 'boolean',
        'requere_atencao' => 'boolean',
        'data_envio' => 'datetime',
        'data_leitura_sefaz' => 'datetime',
        'disponivel_ate' => 'datetime',
        'primeira_visualizacao_em' => 'datetime',
        'ultima_interacao_em' => 'datetime',
        'visualizado_em' => 'datetime',
        'anexos' => 'array',
        'metadados' => 'array',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(DteMailbox::class, 'dte_mailbox_id');
    }

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(ConsultaApi::class, 'consulta_api_id');
    }

    public function automacaoExecucao(): BelongsTo
    {
        return $this->belongsTo(AutomacaoExecucao::class);
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(DteMessageEvent::class);
    }
}
