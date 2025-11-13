<?php

namespace App\Console\Commands;

use App\Models\Certificado;
use App\Models\EmpresaAutomacao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class VerificarVencimentoCertificadosCommand extends Command
{
    protected $signature = 'certificados:verificar-vencimento {--dias=30 : Dias de antecedência para alerta}';
    protected $description = 'Verifica certificados próximos do vencimento';

    public function handle(): int
    {
        $dias = (int) $this->option('dias');
        $dataLimite = now()->addDays($dias);

        $certificadosVencendo = Certificado::ativos()
            ->where('validade', '<=', $dataLimite)
            ->where('validade', '>=', now())
            ->with(['automacoesAtivas.empresa'])
            ->get();

        if ($certificadosVencendo->isEmpty()) {
            $this->info('✅ Nenhum certificado vencendo nos próximos ' . $dias . ' dias');
            return Command::SUCCESS;
        }

        $this->warn("⚠️  {$certificadosVencendo->count()} certificados vencendo:");

        foreach ($certificadosVencendo as $certificado) {
            $diasRestantes = now()->diffInDays($certificado->validade);
            $empresasAfetadas = $certificado->automacoesAtivas->count();

            $this->line("📋 {$certificado->nome} - Vence em {$diasRestantes} dias");
            $this->line("   💼 {$empresasAfetadas} automações ativas afetadas");

            // Pausar automações se certificado venceu
            if ($certificado->vencido) {
                $certificado->automacoesAtivas()->update([
                    'status' => 'pausada',
                    'pausada_ate' => now()->addMonth(),
                ]);
                $this->error("   ❌ Automações pausadas (certificado vencido)");
            }
        }

        // Aqui poderia enviar email para administradores
        // Mail::to(config('mail.admin_email'))->send(new CertificadosVencendoMail($certificadosVencendo));

        return Command::SUCCESS;
    }
}
