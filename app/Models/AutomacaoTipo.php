<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomacaoTipo extends Model
{
    use HasFactory;

    protected $table = 'automacao_tipos';

    protected $fillable = [
        'tipo_consulta',
        'nome_exibicao',
        'descricao',
        'habilitada',
        'permite_automacao',
        'frequencia_padrao',
        'dia_semana_padrao',
        'horario_padrao',
        'intervalo_empresas_segundos',
        'custo_por_consulta',
        'timeout_segundos',
        'max_tentativas',
        'configuracoes_extras',
        'ativa',
    ];

    protected $casts = [
        'habilitada' => 'boolean',
        'permite_automacao' => 'boolean',
        'horario_padrao' => 'datetime:H:i',
        'custo_por_consulta' => 'decimal:4',
        'configuracoes_extras' => 'array',
        'ativa' => 'boolean',
    ];

    // Relacionamentos
    public function empresaAutomacoes(): HasMany
    {
        return $this->hasMany(EmpresaAutomacao::class, 'tipo_consulta', 'tipo_consulta');
    }

    public function consultasApi(): HasMany
    {
        return $this->hasMany(ConsultaApi::class, 'tipo_consulta', 'tipo_consulta');
    }

    // Scopes
    public function scopeHabilitadas($query)
    {
        return $query->where('habilitada', true);
    }

    public function scopeAtivas($query)
    {
        return $query->where('ativa', true);
    }

    public function scopePermiteAutomacao($query)
    {
        return $query->where('permite_automacao', true);
    }

    // Accessors
    public function getFrequenciaFormatadaAttribute(): string
    {
        $frequencias = [
            'diaria' => 'Diária',
            'semanal' => 'Semanal',
            'quinzenal' => 'Quinzenal',
            'mensal' => 'Mensal'
        ];

        return $frequencias[$this->frequencia_padrao] ?? 'N/A';
    }

    public function getDiaSemanaNomeAttribute(): string
    {
        $dias = [
            1 => 'Domingo',
            2 => 'Segunda-feira',
            3 => 'Terça-feira',
            4 => 'Quarta-feira',
            5 => 'Quinta-feira',
            6 => 'Sexta-feira',
            7 => 'Sábado'
        ];

        return $dias[$this->dia_semana_padrao] ?? 'N/A';
    }

    public function getCustoMensalEstimadoAttribute(): float
    {
        if (!$this->custo_por_consulta) {
            return 0;
        }

        // Estima baseado na frequência padrão
        $execucoesPorMes = match($this->frequencia_padrao) {
            'diaria' => 30,
            'semanal' => 4,
            'quinzenal' => 2,
            'mensal' => 1,
            default => 4
        };

        return $this->custo_por_consulta * $execucoesPorMes;
    }

    // Métodos utilitários
    public function podeSerAutomatizada(): bool
    {
        return $this->ativa && $this->permite_automacao && $this->habilitada;
    }

    public function getEmpresasAtivasCount(): int
    {
        return $this->empresaAutomacoes()
            ->where('ativa', true)
            ->where('status', 'ativa')
            ->count();
    }

    public function getCustoMensalReal(): float
    {
        // Calcula baseado nas execuções reais do último mês
        $execucoes = $this->consultasApi()
            ->where('consultado_em', '>=', now()->subMonth())
            ->where('sucesso', true)
            ->sum('preco');

        return (float) $execucoes;
    }

    public function getProximaExecucaoGeral(): ?string
    {
        $proximaExecucao = $this->empresaAutomacoes()
            ->where('ativa', true)
            ->where('status', 'ativa')
            ->whereNotNull('proxima_execucao')
            ->orderBy('proxima_execucao')
            ->first();

        return $proximaExecucao?->proxima_execucao?->format('d/m/Y H:i');
    }
}
