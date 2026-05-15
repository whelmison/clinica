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

    public function savePrice(?int $priceId, array $input): array
    {
        try {
            $data = $this->normalizePrice($input);
            if ($this->repository->priceExistsForPlan($data['servico_id'], $data['plano_id'], $priceId)) {
                throw new InvalidArgumentException('Este servico ja possui preco cadastrado para este plano.');
            }

            if ($priceId === null) {
                $savedId = $this->repository->createPrice($data);

                return ['ok' => true, 'message' => 'Preco salvo com sucesso.', 'id' => $savedId];
            }

            if (!$this->repository->findPrice($priceId)) {
                return ['ok' => false, 'message' => 'Preco nao encontrado.'];
            }

            $this->repository->updatePrice($priceId, $data);

            return ['ok' => true, 'message' => 'Preco atualizado com sucesso.', 'id' => $priceId];
        } catch (InvalidArgumentException $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => 'Nao foi possivel salvar o preco.'];
        }
    }

    public function deletePrice(int $priceId): array
    {
        if (!$this->repository->findPrice($priceId)) {
            return ['ok' => false, 'message' => 'Preco nao encontrado.'];
        }

        $this->repository->deletePrice($priceId);

        return ['ok' => true, 'message' => 'Preco excluido com sucesso.'];
    }

    private function normalize(array $input): array
    {
        $name = trim((string) ($input['nome'] ?? ''));
        $minutes = (int) ($input['tempo_minutos'] ?? 0);
        $type = trim((string) ($input['tipo_agendamento'] ?? 'individual'));
        $capacity = (int) ($input['capacidade_agendamento'] ?? 1);

        if ($name === '' || $minutes <= 0) {
            throw new InvalidArgumentException('Informe nome e duracao valida para o servico.');
        }

        if (!in_array($type, ['individual', 'grupo'], true)) {
            throw new InvalidArgumentException('Selecione se o servico e individual ou em grupo.');
        }

        if ($type === 'individual') {
            $capacity = 1;
        }

        if ($capacity <= 0) {
            throw new InvalidArgumentException('Informe uma capacidade valida para o servico.');
        }

        return [
            'nome' => $name,
            'tempo_minutos' => $minutes,
            'tipo_agendamento' => $type,
            'capacidade_agendamento' => $capacity,
            'ativo' => isset($input['ativo']) ? 1 : 0,
        ];
    }

    private function normalizePrice(array $input): array
    {
        $serviceId = (int) ($input['servico_id'] ?? 0);
        $planId = (int) ($input['plano_id'] ?? 0);
        $payment = trim((string) ($input['forma_pagamento'] ?? 'Tabela'));
        $value = app_parse_money((string) ($input['valor'] ?? '0'));
        $notes = trim((string) ($input['observacoes'] ?? ''));

        if ($serviceId <= 0 || !$this->repository->find($serviceId)) {
            throw new InvalidArgumentException('Selecione um servico valido para o preco.');
        }

        if ($planId <= 0) {
            throw new InvalidArgumentException('Selecione o plano do preco.');
        }

        if (strlen($payment) > 80) {
            throw new InvalidArgumentException('A identificacao do preco deve ter ate 80 caracteres.');
        }

        if ($value <= 0) {
            throw new InvalidArgumentException('Informe um valor maior que zero.');
        }

        return [
            'servico_id' => $serviceId,
            'plano_id' => $planId,
            'forma_pagamento' => $payment !== '' ? $payment : 'Tabela',
            'valor' => $value,
            'ativo' => isset($input['ativo']) ? 1 : 0,
            'permite_alterar_guia' => isset($input['permite_alterar_guia']) ? 1 : 0,
            'observacoes' => $notes !== '' ? $notes : null,
        ];
    }
}
