<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserActivityLogResource\Pages;
use App\Models\UserActivityLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;

class UserActivityLogResource extends Resource
{
    protected static ?string $model = UserActivityLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Logs de Usuário';
    protected static ?string $modelLabel = 'Log de Usuário';
    protected static ?string $pluralModelLabel = 'Logs de Usuário';
    protected static ?int $navigationSort = 99;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Usuário')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('route_name')
                    ->label('Rota')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('method')
                    ->label('Método')
                    ->badge()
                    ->color(fn ($state) => $state === 'GET' ? 'info' : 'warning'),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->sortable(),
                TextColumn::make('path')
                    ->label('Path')
                    ->limit(40),
                TextColumn::make('metadata.response_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => ($state && $state >= 400) ? 'danger' : 'success'),
                TextColumn::make('metadata.request_id')
                    ->label('Request ID')
                    ->copyable()
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Usuário')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('method')
                    ->label('Método')
                    ->options([
                        'GET' => 'GET',
                        'POST' => 'POST',
                        'PUT' => 'PUT',
                        'DELETE' => 'DELETE',
                        'PATCH' => 'PATCH',
                    ]),
                SelectFilter::make('action')
                    ->label('Ação')
                    ->options(fn () => UserActivityLog::query()
                        ->select('action')
                        ->distinct()
                        ->pluck('action', 'action')
                        ->filter()),
                Filter::make('erro')
                    ->label('Erros (>=400)')
                    ->query(fn ($query) => $query->where('metadata->response_status', '>=', 400)),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserActivityLogs::route('/'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Somente admins (Gate 'viewUserActivityLogs') ou users com permissão explícita.
        return Gate::allows('viewUserActivityLogs');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('viewUserActivityLogs');
    }

    public static function canView($record): bool
    {
        return Gate::allows('viewUserActivityLogs');
    }
}
