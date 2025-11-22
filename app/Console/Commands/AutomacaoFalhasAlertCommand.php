<?php

namespace App\Console\Commands;

use App\Models\AutomacaoExecucao;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AutomacaoFalhasAlertCommand extends Command
{
    protected $signature = 'automacao:alerta-falhas
                            {--hours=24 : Janela em horas para buscar falhas}
                            {--limit=50 : Máximo de execuções a listar}';

    protected $description = 'Envia alerta por e-mail de execuções de automação com erro/timeout.';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $limit = (int) $this->option('limit');

        $execucoes = AutomacaoExecucao::with(['empresaAutomacao.empresa', 'empresaAutomacao.automacaoTipo'])
            ->whereIn('status', ['erro', 'timeout'])
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        if ($execucoes->isEmpty()) {
            $this->info('Nenhuma execução com falha na janela de ' . $hours . 'h.');
            return Command::SUCCESS;
        }

        $destinatarios = $this->destinatarios($execucoes);
        if (empty($destinatarios)) {
            $this->warn('Nenhum destinatário configurado. Preencha e-mails em empresas, usuários ativos ou DTE_ALERT_EMAILS.');
            return Command::SUCCESS;
        }

        $subject = 'Alerta: falhas em execuções de automação';
        $body = $this->montarResumo($execucoes, $hours);

        Mail::raw($body, function ($message) use ($destinatarios, $subject) {
            $message->to($destinatarios)->subject($subject);
        });

        $this->info('Alerta de falhas enviado para: ' . implode(', ', $destinatarios));
        Log::info('Alerta de falhas de automação enviado', [
            'destinatarios' => $destinatarios,
            'total' => $execucoes->count(),
        ]);

        return Command::SUCCESS;
    }

    private function destinatarios($execucoes): array
    {
        $env = collect(explode(',', (string) env('DTE_ALERT_EMAILS')))
            ->map(fn ($email) => trim($email))
            ->filter();

        $empresas = collect($execucoes)
            ->map(fn ($exe) => $exe->empresaAutomacao?->empresa?->email)
            ->filter();

        $usuarios = User::where('ativo', true)
            ->whereNotNull('email')
            ->pluck('email');

        return $env->merge($empresas)->merge($usuarios)
            ->unique()
            ->values()
            ->all();
    }

    private function montarResumo($execucoes, int $hours): string
    {
        $linhas = [];
        $linhas[] = "Execuções com falha nas últimas {$hours}h:";
        $linhas[] = str_repeat('-', 70);

        foreach ($execucoes as $exe) {
            $linhas[] = sprintf(
                '[%s] Empresa: %s | Tipo: %s | Status: %s | Início: %s | Request: %s',
                $exe->id,
                $exe->empresaAutomacao?->empresa?->razao_social ?? 'N/A',
                $exe->empresaAutomacao?->automacaoTipo?->nome_exibicao ?? $exe->empresaAutomacao?->tipo_consulta,
                $exe->status,
                optional($exe->iniciada_em)?->format('d/m/Y H:i'),
                $exe->request_id ?? '-'    
            );
        }

        $linhas[] = str_repeat('-', 70);
        $linhas[] = 'Acesse o painel /app para detalhes e tratamento.';

        return implode(PHP_EOL, $linhas);
    }
}
