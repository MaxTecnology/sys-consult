<?php

namespace App\Console\Commands;

use App\Models\DteMessage;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DteAlertCriticalCommand extends Command
{
    protected $signature = 'dte:alert-critical
                            {--limit=50 : Máximo de mensagens críticas no relatório}
                            {--since-hours=6 : Considerar mensagens não lidas nas últimas X horas}';

    protected $description = 'Envia alerta por e-mail com mensagens DTE críticas/não lidas.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $sinceHours = (int) $this->option('since-hours');

        $criticas = DteMessage::with(['mailbox.empresa'])
            ->where(function ($query) use ($sinceHours) {
                $query->where('requere_atencao', true)
                    ->orWhere(function ($q) use ($sinceHours) {
                        $q->where('lida_sefaz', false)
                            ->where('data_envio', '<=', now()->subHours($sinceHours));
                    });
            })
            ->orderBy('data_envio', 'asc')
            ->limit($limit)
            ->get();

        if ($criticas->isEmpty()) {
            $this->info('Nenhuma mensagem crítica encontrada para notificar.');
            return Command::SUCCESS;
        }

        $recipients = $this->recipients($criticas);

        if (empty($recipients)) {
            $this->warn('Nenhum destinatário configurado. Preencha DTE_ALERT_EMAILS ou e-mails nas empresas.');
            return Command::SUCCESS;
        }

        $subject = 'Alertas DTE - Mensagens críticas/não lidas';
        $body = $this->montarResumo($criticas, $sinceHours);

        Mail::raw($body, function ($message) use ($recipients, $subject) {
            $message->to($recipients)->subject($subject);
        });

        $this->info("Notificação enviada para: " . implode(', ', $recipients));
        $this->info("Total de mensagens reportadas: " . $criticas->count());

        Log::info('Alerta DTE crítico disparado', [
            'destinatarios' => $recipients,
            'mensagens' => $criticas->count(),
        ]);

        return Command::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function recipients($mensagens): array
    {
        $envEmails = collect(explode(',', (string) env('DTE_ALERT_EMAILS')))
            ->map(fn ($email) => trim($email))
            ->filter();

        $empresaEmails = collect($mensagens)
            ->map(fn ($msg) => $msg->mailbox?->empresa?->email)
            ->filter();

        $userEmails = User::where('ativo', true)
            ->whereNotNull('email')
            ->pluck('email');

        return $envEmails
            ->merge($empresaEmails)
            ->merge($userEmails)
            ->unique()
            ->values()
            ->all();
    }

    private function montarResumo($mensagens, int $sinceHours): string
    {
        $linhas = [];
        $linhas[] = "Mensagens críticas/não lidas (janela: >= {$sinceHours}h ou requere_atencao=true)";
        $linhas[] = "Total: " . $mensagens->count();
        $linhas[] = str_repeat('-', 60);

        foreach ($mensagens as $msg) {
            $linhas[] = sprintf(
                '[%s] %s | %s | Envio: %s | Lida SEFAZ: %s',
                $msg->mailbox?->empresa?->razao_social ?? 'N/A',
                $msg->assunto ?? 'Sem assunto',
                $msg->protocolo ?? 'Sem protocolo',
                optional($msg->data_envio)?->format('d/m/Y H:i') ?? 'N/A',
                $msg->lida_sefaz ? 'Sim' : 'Não'
            );
        }

        $linhas[] = str_repeat('-', 60);
        $linhas[] = 'Acesse o painel para detalhes e tratamento.';

        return implode(PHP_EOL, $linhas);
    }
}
