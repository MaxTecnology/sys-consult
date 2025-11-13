<?php

namespace App\Filament\Resources\EmpresaAutomacaoResource\Pages;

use App\Filament\Resources\EmpresaAutomacaoResource;
use App\Models\EmpresaAutomacao;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;

class ListEmpresaAutomacaos extends ListRecords
{
    protected static string $resource = EmpresaAutomacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'todas' => Tab::make('Todas')
                ->badge(EmpresaAutomacao::count()),

            'ativas' => Tab::make('Ativas')
                ->badge(EmpresaAutomacao::ativas()->count())
                ->modifyQueryUsing(fn ($query) => $query->ativas()),

            'prontas' => Tab::make('Prontas')
                ->badge(EmpresaAutomacao::prontas()->count())
                ->modifyQueryUsing(fn ($query) => $query->prontas()),

            'com_erro' => Tab::make('Com Erro')
                ->badge(EmpresaAutomacao::comErro()->count())
                ->modifyQueryUsing(fn ($query) => $query->comErro()),

            'pausadas' => Tab::make('Pausadas')
                ->badge(EmpresaAutomacao::where('status', 'pausada')->count())
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'pausada')),
        ];
    }
}
