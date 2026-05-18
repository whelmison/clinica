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

    public function save(?int $patientId, array $input, ?array $user = null): array
    {
        try {
            if ($patientId !== null && !$this->repository->find($patientId)) {
                throw new InvalidArgumentException('Paciente nao encontrado.');
            }

            $data = $this->normalize($input);

            if ($data['cpf'] !== '') {
                $duplicate = $this->repository->findByDocument($data['cpf'], $patientId);

                if ($duplicate) {
                    throw new InvalidArgumentException('Ja existe paciente cadastrado com este CPF/CNPJ: ' . (string) $duplicate['nome'] . '.');
                }
            }

            if ($patientId === null) {
                $savedId = $this->repository->create($data);
                $this->linkProfessionalIfNeeded($savedId, $user, 'cadastro');

                return [
                    'ok' => true,
                    'message' => 'Paciente cadastrado com sucesso.',
                    'id' => $savedId,
                ];
            }

            $this->repository->update($patientId, $data);
            $this->linkProfessionalIfNeeded($patientId, $user, 'edicao');

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

    private function linkProfessionalIfNeeded(int $patientId, ?array $user, string $origin): void
    {
        if (($user['perfil'] ?? '') !== 'profissional') {
            return;
        }

        $professionalId = (int) ($user['profissional_id'] ?? 0);

        if ($professionalId <= 0) {
            return;
        }

        $this->repository->linkProfessional($patientId, $professionalId, $origin);
    }

    private function normalize(array $input): array
    {
        $name = trim((string) ($input['nome'] ?? ''));
        $document = trim((string) ($input['cpf'] ?? ''));
        $birthInput = trim((string) ($input['data_nascimento'] ?? ''));
        $birthDate = null;

        if ($name === '') {
            throw new InvalidArgumentException('Informe o nome do paciente.');
        }

        if ($document !== '' && !\app_cpf_cnpj_valid($document)) {
            throw new InvalidArgumentException('Informe um CPF ou CNPJ valido.');
        }

        if ($birthInput !== '') {
            $birthDate = \app_parse_date_br($birthInput);

            if ($birthDate === null) {
                throw new InvalidArgumentException('Informe a data de nascimento no formato dd/mm/aaaa.');
            }
        }

        $cepDigits = \app_only_digits((string) ($input['cep'] ?? ''));
        $cep = strlen($cepDigits) === 8
            ? substr($cepDigits, 0, 5) . '-' . substr($cepDigits, 5, 3)
            : trim((string) ($input['cep'] ?? ''));

        return [
            'nome' => $name,
            'telefone' => \app_format_phone_br((string) ($input['telefone'] ?? '')),
            'cpf' => $document !== '' ? \app_format_cpf_cnpj($document) : '',
            'data_nascimento' => $birthDate,
            'cep' => $cep,
            'endereco' => trim((string) ($input['endereco'] ?? '')),
            'numero' => trim((string) ($input['numero'] ?? '')),
            'complemento' => trim((string) ($input['complemento'] ?? '')),
            'bairro' => trim((string) ($input['bairro'] ?? '')),
            'cidade' => trim((string) ($input['cidade'] ?? '')),
            'estado' => strtoupper(substr(trim((string) ($input['estado'] ?? '')), 0, 2)),
            'telefone_emergencia' => \app_format_phone_br((string) ($input['telefone_emergencia'] ?? '')),
            'observacoes' => trim((string) ($input['observacoes'] ?? '')),
            'indicado_por' => trim((string) ($input['indicado_por'] ?? '')),
            'prontuario' => trim((string) ($input['prontuario'] ?? '')),
            'dia_preferencia' => trim((string) ($input['dia_preferencia'] ?? '')),
            'horario_preferencia' => trim((string) ($input['horario_preferencia'] ?? '')),
        ];
    }
}
