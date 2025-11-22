<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Console\Command;

class AtribuirUsuarioEmpresaCommand extends Command
{
    protected $signature = 'empresa:atribuir-usuario {user_id} {--empresa_id=* : IDs de empresa (se vazio, atribui todas)} {--role=viewer : owner|editor|viewer}';

    protected $description = 'Vincula um usuário a empresas específicas ou a todas, com papel (owner/editor/viewer).';

    public function handle(): int
    {
        $userId = (int) $this->argument('user_id');
        $role = $this->option('role');
        $empresaIds = $this->option('empresa_id');

        $user = User::find($userId);
        if (!$user) {
            $this->error('Usuário não encontrado');
            return Command::FAILURE;
        }

        $validRoles = ['owner', 'editor', 'viewer'];
        if (!in_array($role, $validRoles, true)) {
            $this->error('Role inválida. Use: owner, editor ou viewer.');
            return Command::FAILURE;
        }

        if (empty($empresaIds)) {
            $empresaIds = Empresa::pluck('id')->all();
        }

        $syncData = [];
        foreach ($empresaIds as $id) {
            $syncData[$id] = ['role' => $role];
        }

        $user->empresas()->syncWithoutDetaching($syncData);

        $this->info('Usuário vinculado com sucesso a ' . count($syncData) . ' empresa(s) como ' . $role . '.');

        return Command::SUCCESS;
    }
}
