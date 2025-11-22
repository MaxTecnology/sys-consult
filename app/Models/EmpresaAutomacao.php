<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use App\Traits\LogsModelChanges;

class EmpresaAutomacao extends Model
{
    use HasFactory, LogsModelChanges;

    protected $table = 'empresa_automacao';

    protected $fillable = [
        'empresa_id',
        'tipo_consulta',
        'certificado_id',
        'ativa',
        'status',
        'frequencia',
        'dias_personalizados',
        'dia_semana',
        'dia_mes',
        'horario',
        'proxima_execucao',
        'ultima_execucao',
        'tentativas_consecutivas_erro',
        'pausada_ate',
        'configuracoes_extras',
        'observacoes',
        'ativo',
        'criado_por',
        'atualizado_por',
    ];

    protected $casts = [
        'ativa' => 'boolean',
        'horario' => 'datetime:H:i',
        'proxima_execucao' => 'datetime',
        'ultima_execucao' => 'datetime',
        'pausada_ate' => 'datetime',
        'configuracoes_extras' => 'array',
        'ativo' => 'boolean',
    ];

    // Relacionamentos
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function certificado(): BelongsTo
    {
        return $this->belongsTo(Certificado::class);
    }

    public function automacaoTipo(): BelongsTo
    {
        return $this->belongsTo(AutomacaoTipo::class, 'tipo_consulta', 'tipo_consulta');
    }

    public function execucoes(): HasMany
    {
        return $this->hasMany(AutomacaoExecucao::class);
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public function atualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizado_por');
    }

    // Scopes
    public function scopeAtivas($query)
    {
        return $query->where('ativa', true)->where('status', 'ativa');
    }

    public function scopeProntas($query)
    {
        return $query->ativas()
            ->where('proxima_execucao', '<=', now())
            ->whereNull('pausada_ate');
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo_consulta', $tipo);
    }

    public function scopeComErro($query)
    {
        return $query->where('status', 'erro');
    }

    // Accessors
    public function getFrequenciaFormatadaAttribute(): string
    {
        $frequencias = [
            'diaria' => 'Diária',
            'semanal' => 'Semanal',
            'quinzenal' => 'Quinzenal',
            'mensal' => 'Mensal',
            'personalizada' => 'Personalizada'
        ];

        $base = $frequencias[$this->frequencia] ?? 'N/A';

        if ($this->frequencia === 'personalizada' && $this->dias_personalizados) {
            $base .= " ({$this->dias_personalizados} dias)";
        }

        return $base;
    }

    public function getStatusFormatadoAttribute(): string
    {
        $status = [
            'ativa' => 'Ativa',
            'pausada' => 'Pausada',
            'erro' => 'Com Erro',
            'desabilitada' => 'Desabilitada'
        ];

        return $status[$this->status] ?? 'Desconhecido';
    }

    public function getProximaExecucaoFormatadaAttribute(): ?string
    {
        return $this->proxima_execucao?->format('d/m/Y H:i');
    }

    public function getUltimaExecucaoFormatadaAttribute(): ?string
    {
        return $this->ultima_execucao?->format('d/m/Y H:i');
    }

    public function getEstaPausadaAttribute(): bool
    {
        return $this->pausada_ate && $this->pausada_ate->isFuture();
    }

    public function getDiasAteProximaExecucaoAttribute(): ?int
    {
        if (!$this->proxima_execucao) {
            return null;
        }

        return now()->diffInDays($this->proxima_execucao, false);
    }

    // Métodos utilitários
    public function calcularProximaExecucao(): Carbon
    {
        $agora = now();
        $horario = $this->horario;

        // Pegar apenas hora e minuto do horário configurado
        $hora = $horario->format('H');
        $minuto = $horario->format('i');

        return match($this->frequencia) {
            'diaria' => $this->calcularProximaDiaria($agora, $hora, $minuto),
            'semanal' => $this->calcularProximaSemanal($agora, $hora, $minuto),
            'quinzenal' => $this->calcularProximaQuinzenal($agora, $hora, $minuto),
            'mensal' => $this->calcularProximaMensal($agora, $hora, $minuto),
            'personalizada' => $this->calcularProximaPersonalizada($agora, $hora, $minuto),
            default => $agora->addDay()->setTime($hora, $minuto)
        };
    }

    private function calcularProximaDiaria(Carbon $agora, int $hora, int $minuto): Carbon
    {
        $proxima = $agora->copy()->setTime($hora, $minuto);

        if ($proxima->isPast()) {
            $proxima->addDay();
        }

        return $proxima;
    }

    private function calcularProximaSemanal(Carbon $agora, int $hora, int $minuto): Carbon
    {
        $diaSemana = $this->dia_semana ?: Carbon::MONDAY; // Default: Segunda
        $dias = [
            1 => 'Sunday',
            2 => 'Monday',
            3 => 'Tuesday',
            4 => 'Wednesday',
            5 => 'Thursday',
            6 => 'Friday',
            7 => 'Saturday',
        ];

        // Se o dia atual é o mesmo e horário ainda não passou, usa hoje
        if ($agora->dayOfWeek === $diaSemana && $agora->format('H:i') < sprintf('%02d:%02d', $hora, $minuto)) {
            return $agora->copy()->setTime($hora, $minuto);
        }

        $weekdayName = $dias[$diaSemana] ?? 'Monday';
        return Carbon::createFromTimestamp($agora->timestamp)->next($weekdayName)->setTime($hora, $minuto);
    }

    private function calcularProximaQuinzenal(Carbon $agora, int $hora, int $minuto): Carbon
    {
        $diaSemana = $this->dia_semana ?: Carbon::MONDAY;
        $dias = [
            1 => 'Sunday',
            2 => 'Monday',
            3 => 'Tuesday',
            4 => 'Wednesday',
            5 => 'Thursday',
            6 => 'Friday',
            7 => 'Saturday',
        ];
        $weekdayName = $dias[$diaSemana] ?? 'Monday';
        $proxima = Carbon::createFromTimestamp($agora->timestamp)->next($weekdayName)->setTime($hora, $minuto);

        // Se é o mesmo dia da semana mas ainda não passou o horário
        if ($agora->dayOfWeek === $diaSemana && $agora->format('H:i') < sprintf('%02d:%02d', $hora, $minuto)) {
            $proxima = $agora->copy()->setTime($hora, $minuto);
        } else {
            // Adicionar mais uma semana para ficar quinzenal
            $proxima->addWeek();
        }

        return $proxima;
    }

    private function calcularProximaMensal(Carbon $agora, int $hora, int $minuto): Carbon
    {
        $diaMes = $this->dia_mes ?: 1;
        $proxima = $agora->copy()->day($diaMes)->setTime($hora, $minuto);

        if ($proxima->isPast()) {
            $proxima->addMonth();
        }

        return $proxima;
    }

    private function calcularProximaPersonalizada(Carbon $agora, int $hora, int $minuto): Carbon
    {
        $dias = $this->dias_personalizados ?: 7;
        return $agora->copy()->addDays($dias)->setTime($hora, $minuto);
    }

    public function atualizarProximaExecucao(): void
    {
        $this->update([
            'proxima_execucao' => $this->calcularProximaExecucao()
        ]);
    }

    public function marcarComoExecutada(): void
    {
        $this->update([
            'ultima_execucao' => now(),
            'tentativas_consecutivas_erro' => 0,
            'proxima_execucao' => $this->calcularProximaExecucao()
        ]);
    }

    public function incrementarErro(): void
    {
        $tentativas = $this->tentativas_consecutivas_erro + 1;
        $maxTentativas = $this->automacaoTipo->max_tentativas ?? 3;

        $update = ['tentativas_consecutivas_erro' => $tentativas];

        // Se excedeu máximo de tentativas, pausar por 24h
        if ($tentativas >= $maxTentativas) {
            $update['status'] = 'erro';
            $update['pausada_ate'] = now()->addDay();
        }

        $this->update($update);
    }

    public function reativar(): void
    {
        $this->update([
            'status' => 'ativa',
            'tentativas_consecutivas_erro' => 0,
            'pausada_ate' => null,
            'proxima_execucao' => $this->calcularProximaExecucao()
        ]);
    }

    public function pausar(?Carbon $ate = null): void
    {
        $this->update([
            'status' => 'pausada',
            'pausada_ate' => $ate ?: now()->addWeek()
        ]);
    }

    public function getLoggableAttributes(): array
    {
        return ['status', 'ativa', 'ativo', 'proxima_execucao', 'ultima_execucao'];
    }
}
