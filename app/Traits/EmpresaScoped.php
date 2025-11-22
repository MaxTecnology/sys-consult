<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait EmpresaScoped
{
    protected static function bootEmpresaScoped(): void
    {
        static::addGlobalScope('empresa_scoped', function (Builder $builder) {
            $user = Auth::user();

            if (!$user || $user->isAdmin()) {
                return;
            }

            $empresaIds = $user->empresas()->pluck('empresas.id')->all();
            if (empty($empresaIds)) {
                // Retorna vazio se usuário não tem empresas vinculadas
                $builder->whereRaw('1 = 0');
                return;
            }

            if ($builder->getModel()->getTable() === 'empresas') {
                $builder->whereIn('empresas.id', $empresaIds);
            } elseif ($builder->getModel()->getTable() === 'empresa_automacao') {
                $builder->whereIn('empresa_id', $empresaIds);
            } elseif ($builder->getModel()->getTable() === 'dte_mailboxes') {
                $builder->whereIn('empresa_id', $empresaIds);
            } elseif ($builder->getModel()->getTable() === 'dte_messages') {
                $builder->whereHas('mailbox', fn ($q) => $q->whereIn('empresa_id', $empresaIds));
            } elseif ($builder->getModel()->getTable() === 'consultas_api') {
                $builder->whereIn('empresa_id', $empresaIds);
            } elseif ($builder->getModel()->getTable() === 'automacao_execucoes') {
                $builder->whereHas('empresaAutomacao', fn ($q) => $q->whereIn('empresa_id', $empresaIds));
            } elseif ($builder->getModel()->getTable() === 'certificados') {
                $builder->whereHas('automacoes', fn ($q) => $q->whereIn('empresa_id', $empresaIds));
            }
        });
    }
}
