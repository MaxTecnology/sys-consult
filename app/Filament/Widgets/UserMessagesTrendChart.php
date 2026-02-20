<?php

namespace App\Filament\Widgets;

use App\Models\DteMessage;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class UserMessagesTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Mensagens nos últimos 14 dias';

    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && ($user->isAdmin() || $user->empresas()->exists());
    }

    protected function getData(): array
    {
        $inicio = now()->subDays(13)->startOfDay();

        $rows = DteMessage::where('created_at', '>=', $inicio)
            ->selectRaw('date(created_at) as dia, count(*) as total')
            ->groupBy('dia')
            ->orderBy('dia')
            ->pluck('total', 'dia')
            ->toArray();

        $labels = [];
        $data = [];
        for ($i = 0; $i < 14; $i++) {
            $dia = $inicio->copy()->addDays($i)->toDateString();
            $labels[] = Carbon::parse($dia)->format('d/m');
            $data[] = $rows[$dia] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Mensagens',
                    'data' => $data,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245,158,11,0.2)',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
