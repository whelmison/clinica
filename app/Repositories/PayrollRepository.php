<?php

namespace Clinic\Repositories;

use PDO;

final class PayrollRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    private function clinicId(): int
    {
        return app_active_clinic_id();
    }

    public function generateReport(string $startDate, string $endDate): array
    {
        // Pega todos os profissionais ativos (ou todos que produziram)
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.nome, p.salario_fixo, p.comissao_percentual, p.imposto_fixo, p.imposto_percentual,
                    COALESCE(SUM(a.valor), 0) AS total_produzido,
                    COUNT(a.id) AS total_atendimentos
             FROM profissionais p
             LEFT JOIN guias g ON g.profissional_id = p.id AND g.clinica_id = p.clinica_id
             LEFT JOIN atendimentos a ON a.guia_id = g.id AND a.clinica_id = g.clinica_id AND a.status_atendimento = "Realizado" AND a.data BETWEEN :start AND :end
             WHERE p.clinica_id = :clinic_id
             GROUP BY p.id
             ORDER BY p.nome'
        );
        $stmt->execute([':clinic_id' => $this->clinicId(), ':start' => $startDate, ':end' => $endDate]);
        $professionals = $stmt->fetchAll();

        $report = [];
        foreach ($professionals as $row) {
            $produzido = (float) $row['total_produzido'];
            $salarioFixo = (float) $row['salario_fixo'];
            $comissaoPerc = (float) $row['comissao_percentual'];
            $impostoFixo = (float) $row['imposto_fixo'];
            $impostoPerc = (float) $row['imposto_percentual'];

            // Se produziu algo ou tem salario fixo
            if ($produzido > 0 || $salarioFixo > 0) {
                $valorComissao = $produzido * ($comissaoPerc / 100);
                $bruto = $salarioFixo + $valorComissao;
                $descontoImposto = $impostoFixo + ($bruto * ($impostoPerc / 100));
                $liquido = $bruto - $descontoImposto;

                $report[] = [
                    'id' => $row['id'],
                    'nome' => $row['nome'],
                    'total_atendimentos' => (int) $row['total_atendimentos'],
                    'total_produzido' => $produzido,
                    'salario_fixo' => $salarioFixo,
                    'valor_comissao' => $valorComissao,
                    'comissao_percentual' => $comissaoPerc,
                    'desconto_imposto' => $descontoImposto,
                    'liquido_pagar' => max(0, $liquido),
                ];
            }
        }

        return $report;
    }
}
