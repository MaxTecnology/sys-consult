@component('mail::message')
# Alertas DTE - Mensagens críticas/não lidas

Janela considerada: >= {{ $sinceHours }}h ou marcada como "requer atenção".  
Total de mensagens listadas: {{ $mensagens->count() }}

@component('mail::panel')
@foreach ($mensagens as $msg)
**Empresa:** {{ $msg->mailbox?->empresa?->razao_social ?? 'N/A' }}  
**Assunto:** {{ $msg->assunto ?? 'Sem assunto' }}  
**Protocolo:** {{ $msg->protocolo ?? 'Sem protocolo' }}  
**Envio:** {{ optional($msg->data_envio)?->format('d/m/Y H:i') ?? 'N/A' }}  
**Lida SEFAZ:** {{ $msg->lida_sefaz ? 'Sim' : 'Não' }}
@if(!$loop->last)
---
@endif
@endforeach
@endcomponent

@component('mail::button', ['url' => $ctaUrl])
Abrir Mensagens DTE
@endcomponent

Obrigado,
{{ config('app.name') }}
@endcomponent
