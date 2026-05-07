<?php

namespace Clinic\Services;

use Clinic\Repositories\PatientRepository;
use InvalidArgumentException;
use Throwable;

final class PatientService
{
    public function __construct(private readonly PatientRepository $repository)
    {
    }

    public function save(?int $patientId, array $input): array
    {
        try {
            if ($patientId !== null && !$this->repository->find($patientId)) {
                throw new InvalidArgumentException('Paciente nao encontrado.');
            }

            $data = $this->normalize($input);

            if ($patientId === null) {
                $savedId = $this->repository->create($data);

                return [
                    'ok' => true,
                    'message' => 'Paciente cadastrado com sucesso.',
                    'id' => $savedId,
                ];
            }

            $this->repository->update($patientId, $data);

            return [
                'ok' => true,
                'message' => 'Paciente atualizado com sucesso.',
                'id' => $patientId,
            ];
        } catch (InvalidArgumentException $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => 'Nao foi possivel salvar o paciente.'];
        }
    }

    private function normalize(array $input): array
    {
        $name = trim((string) ($input['nome'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Informe o nome do paciente.');
        }

        return [
            'nome' => $name,
            'telefone' => trim((string) ($input['telefone'] ?? '')),
            'prontuario' => trim((string) ($input['prontuario'] ?? '')),
            'dia_preferencia' => trim((string) ($input['dia_preferencia'] ?? '')),
            'horario_preferencia' => trim((string) ($input['horario_preferencia'] ?? '')),
        ];
    }
}
