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

    public function saveProfessional(?int $professionalId, array $input, ?array $photoFile = null): array
    {
        $uploadedPhoto = null;
        $photoToDelete = null;

        try {
            $existingProfessional = $professionalId !== null ? $this->repository->findProfessional($professionalId) : null;

            if ($professionalId !== null && !$existingProfessional) {
                throw new InvalidArgumentException('Profissional nao encontrado.');
            }

            $data = $this->normalizeProfessional($input);
            $currentPhoto = trim((string) ($existingProfessional['foto'] ?? ''));
            $data['foto'] = $currentPhoto !== '' ? $currentPhoto : null;
            $services = $this->normalizeServices($input['servicos'] ?? [], $input);
            $this->pdo->beginTransaction();
            $savedId = $professionalId ?? $this->repository->createProfessional($data);
            $uploadedPhoto = $this->storePhoto($photoFile ?? [], $savedId);

            if ($uploadedPhoto !== null) {
                $data['foto'] = $uploadedPhoto;
                $photoToDelete = $currentPhoto;
            } elseif (!empty($input['remover_foto'])) {
                $data['foto'] = null;
                $photoToDelete = $currentPhoto;
            }

            if ($professionalId !== null || $uploadedPhoto !== null || !empty($input['remover_foto'])) {
                $this->repository->updateProfessional($savedId, $data);
            }

            $this->repository->syncProfessionalServices($savedId, $services);
            $this->pdo->commit();
            $this->deletePhoto($photoToDelete);

            return [
                'ok' => true,
                'message' => $professionalId === null ? 'Profissional salvo com sucesso.' : 'Profissional atualizado com sucesso.',
                'id' => $savedId,
            ];
        } catch (InvalidArgumentException $exception) {
            $this->rollbackIfNeeded();
            $this->deletePhoto($uploadedPhoto);

            return ['ok' => false, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            $this->deletePhoto($uploadedPhoto);

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
        $this->deletePhoto($professional['foto'] ?? null);

        return ['ok' => true, 'message' => 'Profissional excluido com sucesso.'];
    }

    public function saveUser(?int $userId, array $input): array
    {
        try {
            $existingUser = $userId !== null ? $this->repository->findUser($userId) : null;

            if ($existingUser && (int) ($existingUser['usuario_padrao'] ?? 0) === 1) {
                return ['ok' => false, 'message' => 'Altere o usuario padrao pelo painel do desenvolvedor para sincronizar todas as clinicas.'];
            }

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

        if ((int) ($user['usuario_padrao'] ?? 0) === 1) {
            return ['ok' => false, 'message' => 'Nao e permitido excluir o usuario padrao das clinicas.'];
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
            'foto' => null,
            'permite_editar_guias' => isset($input['permite_editar_guias']) ? 1 : 0,
            'permite_secretaria_liberar_agenda' => isset($input['permite_secretaria_liberar_agenda']) ? 1 : 0,
        ];
    }

    private function normalizeServices(array $selectedServices, array $input): array
    {
        $map = [];
        $durations = is_array($input['duracoes'] ?? null) ? $input['duracoes'] : [];
        $chargeTypes = is_array($input['cobranca_tipo'] ?? null) ? $input['cobranca_tipo'] : [];
        $chargeValues = is_array($input['cobranca_valor'] ?? null) ? $input['cobranca_valor'] : [];
        $taxFlags = is_array($input['cobra_imposto'] ?? null) ? $input['cobra_imposto'] : [];
        $taxPercentages = is_array($input['imposto_percentual'] ?? null) ? $input['imposto_percentual'] : [];

        foreach ($selectedServices as $serviceId) {
            $serviceId = (int) $serviceId;

            if ($serviceId <= 0) {
                continue;
            }

            $minutes = max(1, (int) ($durations[$serviceId] ?? 1));
            $chargeType = (string) ($chargeTypes[$serviceId] ?? 'percentual');
            $chargeType = $chargeType === 'valor' ? 'valor' : 'percentual';
            $chargeValue = app_parse_money((string) ($chargeValues[$serviceId] ?? '0'));
            $taxEnabled = !empty($taxFlags[$serviceId]) ? 1 : 0;
            $taxPercentage = app_parse_money((string) ($taxPercentages[$serviceId] ?? '0'));

            if ($chargeValue < 0) {
                $chargeValue = 0;
            }

            if ($taxPercentage < 0) {
                $taxPercentage = 0;
            }

            if ($chargeType === 'percentual' && $chargeValue > 100) {
                throw new InvalidArgumentException('A porcentagem de cobranca do servico nao pode ser maior que 100%.');
            }

            if ($taxPercentage > 100) {
                throw new InvalidArgumentException('A porcentagem de imposto nao pode ser maior que 100%.');
            }

            $map[$serviceId] = [
                'tempo_minutos' => $minutes,
                'cobranca_tipo' => $chargeType,
                'cobranca_valor' => $chargeValue,
                'cobra_imposto' => $taxEnabled,
                'imposto_percentual' => $taxEnabled ? $taxPercentage : 0,
            ];
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

    private function storePhoto(array $file, int $professionalId): ?string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Nao foi possivel enviar a foto. Tente selecionar a imagem novamente.');
        }

        if ((int) ($file['size'] ?? 0) > 3 * 1024 * 1024) {
            throw new InvalidArgumentException('A foto deve ter no maximo 3 MB.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new InvalidArgumentException('Arquivo de foto invalido.');
        }

        $imageInfo = @getimagesize($tmpPath);
        $mime = (string) ($imageInfo['mime'] ?? '');
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($extensions[$mime])) {
            throw new InvalidArgumentException('Envie a foto em JPG, PNG ou WEBP.');
        }

        $uploadDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profissionais';

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new InvalidArgumentException('Nao foi possivel criar a pasta de fotos.');
        }

        $filename = 'profissional_' . $professionalId . '_foto_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
        $relativePath = 'uploads/profissionais/' . $filename;
        $targetPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (!move_uploaded_file($tmpPath, $targetPath)) {
            throw new InvalidArgumentException('Nao foi possivel salvar a foto do profissional.');
        }

        return $relativePath;
    }

    private function deletePhoto(?string $path): void
    {
        $photo = ltrim(str_replace('\\', '/', trim((string) $path)), '/');

        if ($photo === '' || !str_starts_with($photo, 'uploads/profissionais/') || str_contains($photo, '..')) {
            return;
        }

        $fullPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $photo);

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
