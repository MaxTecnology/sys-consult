<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DteMessageEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'dte_message_id',
        'user_id',
        'tipo_evento',
        'descricao',
        'payload',
        'registrado_em',
    ];

    protected $casts = [
        'payload' => 'array',
        'registrado_em' => 'datetime',
    ];

    public function mensagem(): BelongsTo
    {
        return $this->belongsTo(DteMessage::class, 'dte_message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
