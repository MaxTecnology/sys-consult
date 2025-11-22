<?php

namespace App\Console\Commands;

use App\Models\Certificado;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class CertificadosAlertCommand extends Command
{
    protected $signature = 'certificados:alerta-vencimento
                            {--dias=30 : Dias de antecedência para alerta}';

    protected $description = 'Envia alerta por e-mail de certificados vencidos ou vencendo';

    public function handle(): int
    {
        $dias = (int) $this->option('dias');
        $destinatarios = $this->destinatarios();

        if (empty($destinatarios)) {
            $this->warn('Nenhum destinatário configurado. Defina DTE_ALERT_EMAILS ou emails de empresas/certificados.');
            return Command::SUCCESS;
        }

        $criticos = Certificado::ativos()
            ->whereNotNull('validade')
            ->where('validade', '<=', now()->addDays($dias))
            ->orderBy('validade')
            ->get();

        if ($criticos->isEmpty()) {
            $this->info('Nenhum certificado vencendo em ' . $dias . ' dias.');
            return Command::SUCCESS;
        }

        $subject = 'Alerta: Certificados vencidos/vencendo';
        $body = $this->montarResumo($criticos, $dias);

        Mail::raw($body, function ($message) use ($destinatarios, $subject) {
            $message->to($destinatarios)->subject($subject);
        });

        $this->info('Alerta enviado para: ' . implode(', ', $destinatarios));
        Log::info('Alerta de certificados enviado', [
            'destinatarios' => $destinatarios,
            'total' => $criticos->count(),
        ]);

        return Command::SUCCESS;
    }

    private function destinatarios(): array
    {
        $env = collect(explode(',', (string) env('DTE_ALERT_EMAILS')))
            ->map(fn ($email) => trim($email))
            ->filter();

        // e-mails das empresas associadas a certificações ativas
        $certs = Certificado::ativos()->with(['automacoes.empresa'])->get();
        $empresas = $certs->pluck('automacoes.*.empresa.email')->flatten()->filter();

        // usuários delegados às empresas dos certificados
        $empresaIds = $certs->pluck('automacoes.*.empresa_id')->flatten()->filter()->unique();

        $delegados = User::where('ativo', true)
            ->whereNotNull('email')
            ->whereHas('empresas', fn ($q) => $q->whereIn('empresas.id', $empresaIds))
            ->pluck('email');

        $admins = User::where('role', 'admin')->whereNotNull('email')->pluck('email');

        return $env->merge($empresas)->merge($delegados)->merge($admins)
            ->unique()
            ->values()
            ->all();
    }

    private function montarResumo($certificados, int $dias): string
    {
        $linhas = [];
        $linhas[] = "Certificados vencidos ou vencendo em {$dias} dias:";
        $linhas[] = str_repeat('-', 60);

        foreach ($certificados as $cert) {
            $linhas[] = sprintf(
                '[%s] Vencimento: %s | Status: %s',
                $cert->nome,
                optional($cert->validade)?->format('d/m/Y') ?? 'Sem data',
                $cert->status
            );
        }

        $linhas[] = str_repeat('-', 60);
        $linhas[] = 'Acesse o painel para gerenciar certificados e automações vinculadas.';

        return implode(PHP_EOL, $linhas);
    }
}
