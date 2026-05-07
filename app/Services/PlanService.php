<?php

namespace Clinic\Services;

use Clinic\Repositories\PlanRepository;
use InvalidArgumentException;
use Throwable;

final class PlanService
{
    public function __construct(private readonly PlanRepository $repository)
    {
    }

    public function save(?int $planId, array $input): array
    {
        try {
            if ($planId !== null && !$this->repository->find($planId)) {
                throw new InvalidArgumentException('Plano nao encontrado.');
            }

            $data = $this->normalize($input);

            if ($planId === null) {
                $savedId = $this->repository->create($data);

                return [
                    'ok' => true,
                    'message' => 'Plano cadastrado com sucesso.',
                    'id' => $savedId,
                ];
            }

            $this->repository->update($planId, $data);

            return [
                'ok' => true,
                'message' => 'Plano atualizado com sucesso.',
                'id' => $planId,
            ];
        } catch (InvalidArgumentException $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => 'Nao foi possivel salvar o plano.'];
        }
    }

    private function normalize(array $input): array
    {
        $name = trim((string) ($input['nome'] ?? ''));
        $rawValue = trim((string) ($input['valor_sessao'] ?? ''));
        $value = $this->normalizeMoney($rawValue);

        if ($name === '') {
            throw new InvalidArgumentException('Informe o nome do plano.');
        }

        if ($value <= 0) {
            throw new InvalidArgumentException('Informe um valor de sessao maior que zero.');
        }

        return [
            'nome' => $name,
            'valor_sessao' => $value,
        ];
    }

    private function normalizeMoney(string $value): float
    {
        $normalized = str_replace(['R$', ' '], '', $value);
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? round((float) $normalized, 2) : 0.0;
    }
}
