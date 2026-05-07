<?php

namespace Clinic\Services;

use Clinic\Repositories\ServiceCatalogRepository;
use InvalidArgumentException;
use Throwable;

final class ServiceCatalogService
{
    public function __construct(private readonly ServiceCatalogRepository $repository)
    {
    }

    public function save(?int $serviceId, array $input): array
    {
        try {
            $data = $this->normalize($input);

            if ($serviceId === null) {
                $savedId = $this->repository->create($data);

                return ['ok' => true, 'message' => 'Servico salvo com sucesso.', 'id' => $savedId];
            }

            $this->repository->update($serviceId, $data);

            return ['ok' => true, 'message' => 'Servico atualizado com sucesso.', 'id' => $serviceId];
        } catch (InvalidArgumentException $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => 'Nao foi possivel salvar o servico.'];
        }
    }

    public function delete(int $serviceId): array
    {
        $service = $this->repository->find($serviceId);

        if (!$service) {
            return ['ok' => false, 'message' => 'Servico nao encontrado.'];
        }

        if ($this->repository->dependencies($serviceId) > 0) {
            return ['ok' => false, 'message' => 'Este servico esta vinculado a profissionais e nao pode ser excluido.'];
        }

        $this->repository->delete($serviceId);

        return ['ok' => true, 'message' => 'Servico excluido com sucesso.'];
    }

    private function normalize(array $input): array
    {
        $name = trim((string) ($input['nome'] ?? ''));
        $minutes = (int) ($input['tempo_minutos'] ?? 0);

        if ($name === '' || $minutes <= 0) {
            throw new InvalidArgumentException('Informe nome e duracao valida para o servico.');
        }

        return [
            'nome' => $name,
            'tempo_minutos' => $minutes,
            'ativo' => isset($input['ativo']) ? 1 : 0,
        ];
    }
}
