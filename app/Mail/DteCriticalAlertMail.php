<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;

class DteCriticalAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Collection $mensagens;
    public int $sinceHours;
    public string $ctaUrl;
    public string $assunto;

    /**
     * @param \Illuminate\Support\Collection<int,\App\Models\DteMessage> $mensagens
     */
    public function __construct(Collection $mensagens, int $sinceHours, string $ctaUrl)
    {
        $this->mensagens = $mensagens;
        $this->sinceHours = $sinceHours;
        $this->ctaUrl = $ctaUrl;
        $this->assunto = 'Alertas DTE - Mensagens críticas/não lidas';
    }

    public function build(): self
    {
        return $this
            ->subject($this->assunto)
            ->markdown('mails.dte_critical_alert');
    }
}
