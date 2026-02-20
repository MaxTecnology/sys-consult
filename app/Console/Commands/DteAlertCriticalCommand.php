<?php

namespace App\Console\Commands;

use App\Mail\DteCriticalAlertMail;
use App\Models\DteMessage;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Collection;

class DteAlertCriticalCommand extends Command
{
    protected $signature = 'dte:alert-critical
                            {--limit=50 : Máximo de mensagens críticas no relatório}
                            {--since-hours=1 : Considerar mensagens não lidas nas últimas X horas}';

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
            $this->warn('Nenhum destinatário configurado (e-mails de empresas/delegados/admins).');
            return Command::SUCCESS;
        }

        // Envia em lotes menores para evitar limites de SMTP e usa queue
        $lotes = array_chunk($recipients, 50);
        $ctaUrl = rtrim(config('app.url'), '/') . '/app/dte-messages';
        foreach ($lotes as $lote) {
            Mail::to($lote)->queue(new DteCriticalAlertMail($criticas, $sinceHours, $ctaUrl));
        }

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
        $empresaEmails = collect($mensagens)
            ->map(fn ($msg) => $msg->mailbox?->empresa?->email)
            ->filter();

        // Usuários delegados às empresas das mensagens
        $empresaIds = collect($mensagens)
            ->map(fn ($msg) => $msg->mailbox?->empresa_id)
            ->filter()
            ->unique();

        $delegados = User::where('ativo', true)
            ->whereNotNull('email')
            ->whereHas('empresas', fn ($q) => $q->whereIn('empresas.id', $empresaIds))
            ->pluck('email');

        // Admin (role=admin) recebe sempre
        $admins = User::where('role', 'admin')
            ->whereNotNull('email')
            ->pluck('email');

        return collect()
            ->merge($empresaEmails)
            ->merge($delegados)
            ->merge($admins)
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    private function montarResumo($mensagens, int $sinceHours): string
    {
        // Mantido apenas para compatibilidade; usamos Mailable para renderizar
        return '';
    }
}
