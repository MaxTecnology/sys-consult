<?php

namespace App\Filament\Widgets;

use App\Models\DteMessage;
use Filament\Widgets\ChartWidget;

class UserMessagesStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Mensagens por status';

    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && ($user->isAdmin() || $user->empresas()->exists());
    }

    protected function getData(): array
    {
        $base = DteMessage::selectRaw('status_interno, count(*) as total')
            ->groupBy('status_interno')
            ->pluck('total', 'status_interno')
            ->toArray();

        $labels = ['novo', 'em_andamento', 'concluido', 'ignorado'];
        $data = array_map(fn ($label) => $base[$label] ?? 0, $labels);

        return [
            'datasets' => [
                [
                    'label' => 'Qtd',
                    'data' => $data,
                    'backgroundColor' => ['#f59e0b', '#0ea5e9', '#22c55e', '#9ca3af'],
                ],
            ],
            'labels' => ['Novo', 'Em andamento', 'Concluído', 'Ignorado'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
