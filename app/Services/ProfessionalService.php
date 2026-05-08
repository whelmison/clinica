<?php

namespace Clinic\Services;

use Clinic\Repositories\ProfessionalRepository;
use InvalidArgumentException;
use PDO;
use Throwable;

final class ProfessionalService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ProfessionalRepository $repository
    ) {
    }

    public function saveProfessional(?int $professionalId, array $input): array
    {
        try {
            $data = $this->normalizeProfessional($input);
            $services = $this->normalizeServices($input['servicos'] ?? [], $input['duracoes'] ?? []);
            $this->pdo->beginTransaction();
            $savedId = $professionalId ?? $this->repository->createProfessional($data);

            if ($professionalId !== null) {
                $this->repository->updateProfessional($professionalId, $data);
            }

            $this->repository->syncProfessionalServices($savedId, $services);
            $this->pdo->commit();

            return [
                'ok' => true,
                'message' => $professionalId === null ? 'Profissional salvo com sucesso.' : 'Profissional atualizado com sucesso.',
                'id' => $savedId,
            ];
        } catch (InvalidArgumentException $exception) {
            $this->rollbackIfNeeded();

            return ['ok' => false, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();

            return ['ok' => false, 'message' => 'Nao foi possivel salvar o profissional.'];
        }
    }

    public function deleteProfessional(int $professionalId): array
    {
        $professional = $this->repository->findProfessional($professionalId);

        if (!$professional) {
            return ['ok' => false, 'message' => 'Profissional nao encontrado.'];
        }

        $dependencies = $this->repository->professionalDependencies($professionalId);

        foreach ($dependencies as $count) {
            if ($count > 0) {
                return ['ok' => false, 'message' => 'Nao e possivel excluir este profissional porque ele possui registros vinculados.'];
            }
        }

        $this->repository->deleteProfessional($professionalId);

        return ['ok' => true, 'message' => 'Profissional excluido com sucesso.'];
    }

    public function saveUser(?int $userId, array $input): array
    {
        try {
            $data = $this->normalizeUser($input, $userId);

            if ($userId === null) {
                $savedId = $this->repository->createUser($data);

                return ['ok' => true, 'message' => 'Usuario criado com sucesso.', 'id' => $savedId];
            }

            $this->repository->updateUser($userId, $data);

            return ['ok' => true, 'message' => 'Usuario atualizado com sucesso.', 'id' => $userId];
        } catch (InvalidArgumentException $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => 'Nao foi possivel salvar o usuario.'];
        }
    }

    public function deleteUser(int $userId, int $currentUserId): array
    {
        if ($userId === $currentUserId) {
            return ['ok' => false, 'message' => 'Nao e permitido excluir o usuario atualmente logado.'];
        }

        $user = $this->repository->findUser($userId);

        if (!$user) {
            return ['ok' => false, 'message' => 'Usuario nao encontrado.'];
        }

        $this->repository->deleteUser($userId);

        return ['ok' => true, 'message' => 'Usuario excluido com sucesso.'];
    }

    private function normalizeProfessional(array $input): array
    {
        $name = trim((string) ($input['nome'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Informe o nome do profissional.');
        }

        return [
            'nome' => $name,
            'endereco' => trim((string) ($input['endereco'] ?? '')),
            'telefone' => trim((string) ($input['telefone'] ?? '')),
            'profissao' => trim((string) ($input['profissao'] ?? '')),
            'permite_editar_guias' => isset($input['permite_editar_guias']) ? 1 : 0,
            'permite_secretaria_liberar_agenda' => isset($input['permite_secretaria_liberar_agenda']) ? 1 : 0,
        ];
    }

    private function normalizeServices(array $selectedServices, array $durations): array
    {
        $map = [];

        foreach ($selectedServices as $serviceId) {
            $serviceId = (int) $serviceId;

            if ($serviceId <= 0) {
                continue;
            }

            $minutes = max(1, (int) ($durations[$serviceId] ?? 1));
            $map[$serviceId] = $minutes;
        }

        return $map;
    }

    private function normalizeUser(array $input, ?int $userId = null): array
    {
        $login = trim((string) ($input['login'] ?? ''));
        $password = trim((string) ($input['senha'] ?? ''));
        $profile = trim((string) ($input['perfil'] ?? ''));
        $professionalId = !empty($input['profissional_relacionado']) ? (int) $input['profissional_relacionado'] : null;
        $displayName = trim((string) ($input['nome_exibicao'] ?? ''));
        $active = isset($input['ativo']) ? 1 : 0;

        if (strlen($login) < 3) {
            throw new InvalidArgumentException('Informe um login com ao menos 3 caracteres.');
        }

        if (!in_array($profile, ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'], true)) {
            throw new InvalidArgumentException('Selecione um perfil valido.');
        }

        if ($profile === 'profissional' && $professionalId === null) {
            throw new InvalidArgumentException('Usuarios do perfil profissional precisam estar vinculados a um profissional.');
        }

        if ($userId === null && strlen($password) < 6) {
            throw new InvalidArgumentException('Informe uma senha com ao menos 6 caracteres.');
        }

        if ($this->repository->userByLogin($login, $userId)) {
            throw new InvalidArgumentException('Ja existe um usuario com este login nesta clinica.');
        }

        return [
            'login' => $login,
            'senha_hash' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
            'perfil' => $profile,
            'profissional_id' => $professionalId,
            'nome_exibicao' => $displayName !== '' ? $displayName : $login,
            'ativo' => $active,
        ];
    }

    private function rollbackIfNeeded(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
