<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomacaoExecucao extends Model
{
    use HasFactory;

    protected $table = 'automacao_execucoes';

    protected $fillable = [
        'empresa_automacao_id',
        'consulta_api_id',
        'status',
        'iniciada_em',
        'finalizada_em',
        'duracao_ms',
        'mensagem_erro',
        'detalhes_erro',
        'tentativa_numero',
        'job_id',
        'queue_name',
        'request_id',
        'custo_execucao',
        'metricas_extras',
    ];

    protected $casts = [
        'iniciada_em' => 'datetime',
        'finalizada_em' => 'datetime',
        'detalhes_erro' => 'array',
        'custo_execucao' => 'decimal:4',
        'metricas_extras' => 'array',
    ];

    // Relacionamentos
    public function empresaAutomacao(): BelongsTo
    {
        return $this->belongsTo(EmpresaAutomacao::class);
    }

    public function consultaApi(): BelongsTo
    {
        return $this->belongsTo(ConsultaApi::class);
    }

    public function dteMessages(): HasMany
    {
        return $this->hasMany(DteMessage::class);
    }

    // Relacionamentos indiretos através de empresaAutomacao
    public function empresa()
    {
        return $this->hasOneThrough(
            Empresa::class,
            EmpresaAutomacao::class,
            'id',
            'id',
            'empresa_automacao_id',
            'empresa_id'
        );
    }

    // Scopes
    public function scopeSucesso($query)
    {
        return $query->where('status', 'sucesso');
    }

    public function scopeErro($query)
    {
        return $query->where('status', 'erro');
    }

    public function scopeHoje($query)
    {
        return $query->whereDate('iniciada_em', today());
    }

    public function scopeUltimoMes($query)
    {
        return $query->where('iniciada_em', '>=', now()->subMonth());
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->whereHas('empresaAutomacao', function ($q) use ($tipo) {
            $q->where('tipo_consulta', $tipo);
        });
    }

    public function scopePorEmpresa($query, int $empresaId)
    {
        return $query->whereHas('empresaAutomacao', function ($q) use ($empresaId) {
            $q->where('empresa_id', $empresaId);
        });
    }

    // Accessors
    public function getStatusFormatadoAttribute(): string
    {
        $status = [
            'iniciada' => 'Em Execução',
            'sucesso' => 'Sucesso',
            'erro' => 'Erro',
            'timeout' => 'Timeout',
            'cancelada' => 'Cancelada'
        ];

        return $status[$this->status] ?? 'Desconhecido';
    }

    public function getDuracaoFormatadaAttribute(): ?string
    {
        if (!$this->duracao_ms) {
            return null;
        }

        if ($this->duracao_ms < 1000) {
            return $this->duracao_ms . 'ms';
        }

        $segundos = round($this->duracao_ms / 1000, 1);
        return $segundos . 's';
    }

    public function getIniciadaEmFormatadaAttribute(): string
    {
        return $this->iniciada_em->format('d/m/Y H:i:s');
    }

    public function getFinalizadaEmFormatadaAttribute(): ?string
    {
        return $this->finalizada_em?->format('d/m/Y H:i:s');
    }

    public function getFoiSucessoAttribute(): bool
    {
        return $this->status === 'sucesso';
    }

    public function getTemErroAttribute(): bool
    {
        return in_array($this->status, ['erro', 'timeout']);
    }

    public function getEstaRodandoAttribute(): bool
    {
        return $this->status === 'iniciada';
    }

    // Métodos utilitários
    public function marcarComoIniciada(?string $jobId = null, ?string $queueName = null): void
    {
        $this->update([
            'status' => 'iniciada',
            'iniciada_em' => now(),
            'job_id' => $jobId,
            'queue_name' => $queueName,
        ]);
    }

    public function marcarComoSucesso(?int $consultaApiId = null, ?float $custo = null): void
    {
        $this->update([
            'status' => 'sucesso',
            'finalizada_em' => now(),
            'duracao_ms' => $this->calcularDuracao(),
            'consulta_api_id' => $consultaApiId,
            'custo_execucao' => $custo,
        ]);
    }

    public function marcarComoErro(string $mensagem, ?array $detalhes = null): void
    {
        $this->update([
            'status' => 'erro',
            'finalizada_em' => now(),
            'duracao_ms' => $this->calcularDuracao(),
            'mensagem_erro' => $mensagem,
            'detalhes_erro' => $detalhes,
        ]);
    }

    public function marcarComoTimeout(): void
    {
        $this->update([
            'status' => 'timeout',
            'finalizada_em' => now(),
            'duracao_ms' => $this->calcularDuracao(),
            'mensagem_erro' => 'Execução excedeu o tempo limite',
        ]);
    }

    public function marcarComoCancelada(string $motivo = 'Cancelada pelo usuário'): void
    {
        $this->update([
            'status' => 'cancelada',
            'finalizada_em' => now(),
            'duracao_ms' => $this->calcularDuracao(),
            'mensagem_erro' => $motivo,
        ]);
    }

    private function calcularDuracao(): ?int
    {
        if (!$this->iniciada_em) {
            return null;
        }

        return now()->diffInMilliseconds($this->iniciada_em);
    }

    public function adicionarMetrica(string $chave, $valor): void
    {
        $metricas = $this->metricas_extras ?: [];
        $metricas[$chave] = $valor;

        $this->update(['metricas_extras' => $metricas]);
    }

    // Métodos estáticos para estatísticas
    public static function estatisticasHoje(): array
    {
        $hoje = static::hoje();

        return [
            'total' => $hoje->count(),
            'sucessos' => $hoje->sucesso()->count(),
            'erros' => $hoje->erro()->count(),
            'custo_total' => $hoje->sum('custo_execucao'),
            'tempo_medio' => $hoje->avg('duracao_ms'),
        ];
    }

    public static function estatisticasPorTipo(string $tipo): array
    {
        $query = static::porTipo($tipo)->ultimoMes();

        return [
            'total_execucoes' => $query->count(),
            'taxa_sucesso' => $query->count() > 0 ?
                ($query->sucesso()->count() / $query->count()) * 100 : 0,
            'custo_total' => $query->sum('custo_execucao'),
            'tempo_medio' => $query->avg('duracao_ms'),
        ];
    }
}
