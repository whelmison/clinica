<?php
$paginaAtual = basename($_SERVER['PHP_SELF'] ?? '');
$usuarioAtual = app_current_user();
$perfilAtual = app_current_profile();
$menuFlashMode = $menuFlashMode ?? 'inline';
$flash = app_take_flash();

if (!function_exists('menuAtivo')) {
    function menuAtivo(array $paginas, string $paginaAtual): string
    {
        return in_array($paginaAtual, $paginas, true) ? 'active' : '';
    }
}

if (!function_exists('menuDropdownAtivo')) {
    function menuDropdownAtivo(array $link, string $paginaAtual): string
    {
        return menuAtivo($link['pages'] ?? [], $paginaAtual);
    }
}

if (!function_exists('menuHrefPage')) {
    function menuHrefPage(string $href): string
    {
        return basename((string) parse_url($href, PHP_URL_PATH));
    }
}

if (!function_exists('menuChildAtivo')) {
    function menuChildAtivo(array $child, string $paginaAtual): string
    {
        $hrefPage = menuHrefPage((string) ($child['href'] ?? ''));

        if (array_key_exists('tab', $child)) {
            $currentTab = (string) ($_GET['tab'] ?? '');
            $expectedTab = (string) $child['tab'];

            if ($expectedTab === '') {
                return $paginaAtual === $hrefPage && ($currentTab === '' || $currentTab === 'servicos') ? 'active' : '';
            }

            return $paginaAtual === $hrefPage && $currentTab === $expectedTab ? 'active' : '';
        }

        return menuAtivo($child['pages'] ?? [$hrefPage], $paginaAtual);
    }
}

if (!function_exists('menuPaginaPermitida')) {
    function menuPaginaPermitida(string $pagina, array $accessMap): bool
    {
        $access = $accessMap[$pagina] ?? ['auth'];

        if (in_array('public', $access, true) || in_array('auth', $access, true)) {
            return true;
        }

        return function_exists('app_profile_matches_access')
            ? app_profile_matches_access($access)
            : app_has_any_role($access);
    }
}

if (!function_exists('menuFiltrarPorPermissao')) {
    function menuFiltrarPorPermissao(array $links, array $accessMap): array
    {
        $filtered = [];

        foreach ($links as $link) {
            if (!empty($link['children'])) {
                $children = [];

                foreach ($link['children'] as $child) {
                    if (menuPaginaPermitida(menuHrefPage($child['href']), $accessMap)) {
                        $children[] = $child;
                    }
                }

                if ($children === []) {
                    continue;
                }

                $link['children'] = $children;

                if (!menuPaginaPermitida(menuHrefPage($link['href']), $accessMap)) {
                    $link['href'] = $children[0]['href'];
                }

                $filtered[] = $link;
                continue;
            }

            if (menuPaginaPermitida(menuHrefPage($link['href']), $accessMap)) {
                $filtered[] = $link;
            }
        }

        return $filtered;
    }
}

$linksPorPerfil = [
    'profissional' => [
        ['label' => 'Dashboard', 'href' => 'index.php', 'pages' => ['index.php']],
        ['label' => 'Agenda', 'href' => 'secretaria_agenda.php', 'pages' => ['secretaria_agenda.php']],
        ['label' => 'Liberacao de agenda', 'href' => 'agenda_liberacao.php', 'pages' => ['agenda_liberacao.php']],
        ['label' => 'Pacientes', 'href' => 'pacientes.php', 'pages' => ['pacientes.php', 'novo_paciente.php', 'editar_paciente.php', 'paciente_historico.php', 'paciente_fichas.php']],
        ['label' => 'Atendimentos', 'href' => 'atendimentos.php', 'pages' => ['atendimentos.php']],
        ['label' => 'Guias', 'href' => 'guias.php', 'pages' => ['guias.php']],
        ['label' => 'Financeiro', 'href' => 'financeiro_profissional.php', 'pages' => ['financeiro_profissional.php']],
        ['label' => 'Meu cadastro', 'href' => 'meu_cadastro.php', 'pages' => ['meu_cadastro.php']],
    ],
    'secretaria' => [
        ['label' => 'Painel', 'href' => 'secretaria.php', 'pages' => ['secretaria.php']],
        [
            'label' => 'Agenda',
            'href' => 'secretaria_agenda.php',
            'pages' => ['secretaria_agenda.php', 'secretaria_agenda_grupo.php', 'agenda_liberacao.php', 'agenda_lista_agendamentos.php', 'agenda_relatorio_gerencial.php'],
            'children' => [
                ['label' => 'Abrir agenda', 'href' => 'secretaria_agenda.php', 'pages' => ['secretaria_agenda.php', 'secretaria_agenda_grupo.php']],
                ['label' => 'Lista de agendamentos', 'href' => 'agenda_lista_agendamentos.php', 'pages' => ['agenda_lista_agendamentos.php']],
                ['label' => 'Liberar horarios', 'href' => 'agenda_liberacao.php', 'pages' => ['agenda_liberacao.php']],
                ['label' => 'Relatorio da agenda', 'href' => 'agenda_relatorio_gerencial.php', 'pages' => ['agenda_relatorio_gerencial.php']],
            ],
        ],
        [
            'label' => 'Servicos',
            'href' => 'secretaria_servicos.php',
            'pages' => ['secretaria_servicos.php', 'novo_servico.php', 'editar_servico.php'],
            'children' => [
                ['label' => 'Cadastro de servicos', 'href' => 'secretaria_servicos.php', 'pages' => ['secretaria_servicos.php', 'novo_servico.php', 'editar_servico.php'], 'tab' => ''],
                ['label' => 'Relatorio de precos', 'href' => 'secretaria_servicos.php?tab=precos', 'pages' => ['secretaria_servicos.php'], 'tab' => 'precos'],
            ],
        ],
        ['label' => 'Profissionais', 'href' => 'administrativo_profissionais.php', 'pages' => ['administrativo_profissionais.php', 'novo_profissional.php', 'editar_profissional.php']],
        ['label' => 'Atendimentos', 'href' => 'atendimentos.php', 'pages' => ['atendimentos.php', 'novo_atendimento.php', 'editar_atendimento.php']],
        ['label' => 'Guias', 'href' => 'guias.php', 'pages' => ['guias.php', 'nova_guia.php', 'editar_guia.php']],
        ['label' => 'Pacientes', 'href' => 'pacientes.php', 'pages' => ['pacientes.php', 'novo_paciente.php', 'editar_paciente.php', 'paciente_historico.php', 'paciente_fichas.php']],
        ['label' => 'Faturamento', 'href' => 'administrativo_lotes.php', 'pages' => ['administrativo_lotes.php']],
        ['label' => 'Relatorios', 'href' => 'relatorios.php', 'pages' => ['relatorios.php', 'agenda_relatorio_gerencial.php']],
    ],
    'administrativo' => [
        ['label' => 'Painel', 'href' => 'administrativo.php', 'pages' => ['administrativo.php']],
        ['label' => 'Profissionais', 'href' => 'administrativo_profissionais.php', 'pages' => ['administrativo_profissionais.php']],
        ['label' => 'Usuarios', 'href' => 'administrativo_usuarios.php', 'pages' => ['administrativo_usuarios.php']],
        ['label' => 'Permissoes', 'href' => 'administrativo_permissoes.php', 'pages' => ['administrativo_permissoes.php']],
        ['label' => 'Financeiro', 'href' => 'administrativo_financeiro.php', 'pages' => ['administrativo_financeiro.php', 'financeiro_plano_contas.php', 'financeiro_centros_custo.php', 'financeiro_contas_financeiras.php', 'financeiro_contas_pagar.php', 'financeiro_contas_receber.php', 'relatorio_financeiro_fechamento.php', 'novo_plano_contas.php', 'editar_plano_contas.php', 'nova_conta_pagar.php', 'editar_conta_pagar.php', 'nova_conta_receber.php', 'editar_conta_receber.php']],
        ['label' => 'Guias', 'href' => 'guias.php', 'pages' => ['guias.php', 'nova_guia.php', 'editar_guia.php']],
        ['label' => 'Pacientes', 'href' => 'pacientes.php', 'pages' => ['pacientes.php', 'novo_paciente.php', 'editar_paciente.php', 'paciente_historico.php', 'paciente_fichas.php']],
        ['label' => 'Planos', 'href' => 'planos.php', 'pages' => ['planos.php', 'editar_plano.php']],
        ['label' => 'Faturamento', 'href' => 'administrativo_lotes.php', 'pages' => ['administrativo_lotes.php']],
        ['label' => 'Relatorios', 'href' => 'relatorios.php', 'pages' => ['relatorios.php']],
    ],
    'desenvolvedor' => [
        ['label' => 'Painel dev', 'href' => 'desenvolvedor.php', 'pages' => ['desenvolvedor.php']],
        ['label' => 'Profissional', 'href' => 'index.php', 'pages' => ['index.php', 'agenda_liberacao.php', 'atendimentos.php', 'novo_atendimento.php', 'editar_atendimento.php', 'guias.php', 'financeiro_profissional.php', 'financeiro.php', 'financeiro_mensal.php', 'recebimentos.php', 'meu_cadastro.php']],
        ['label' => 'Secretaria', 'href' => 'secretaria.php', 'pages' => ['secretaria.php', 'secretaria_agenda.php', 'secretaria_agenda_grupo.php', 'agenda_liberacao.php', 'agenda_lista_agendamentos.php', 'agenda_relatorio_gerencial.php', 'secretaria_servicos.php', 'novo_servico.php', 'editar_servico.php', 'atendimentos.php', 'novo_atendimento.php', 'editar_atendimento.php', 'guias.php', 'relatorios.php']],
        ['label' => 'Administrativo', 'href' => 'administrativo.php', 'pages' => ['administrativo.php', 'administrativo_clinica.php', 'administrativo_profissionais.php', 'administrativo_usuarios.php', 'administrativo_permissoes.php', 'administrativo_financeiro.php', 'financeiro_plano_contas.php', 'financeiro_centros_custo.php', 'financeiro_contas_financeiras.php', 'financeiro_contas_pagar.php', 'financeiro_contas_receber.php', 'relatorio_financeiro_fechamento.php', 'administrativo_lotes.php', 'relatorios.php']],
        ['label' => 'Permissoes', 'href' => 'administrativo_permissoes.php', 'pages' => ['administrativo_permissoes.php']],
        ['label' => 'Legado', 'href' => 'pacientes.php', 'pages' => ['pacientes.php', 'novo_paciente.php', 'editar_paciente.php', 'paciente_historico.php', 'paciente_fichas.php', 'planos.php', 'editar_plano.php', 'nova_guia.php', 'editar_guia.php']],
    ],
];

$accessMapMenu = isset($conn) && function_exists('app_effective_page_access_map')
    ? app_effective_page_access_map($conn)
    : app_page_access_map();
$links = menuFiltrarPorPermissao($linksPorPerfil[$perfilAtual] ?? [], $accessMapMenu);
$canEditClinic = menuPaginaPermitida('administrativo_clinica.php', $accessMapMenu);
?>

<style>
.topbar-clinica {
    background: linear-gradient(135deg, #0f4c5c, #1f7a8c);
}

.topbar-clinica .navbar-brand,
.topbar-clinica .nav-link,
.topbar-clinica .navbar-text {
    color: #fff;
}

.topbar-clinica .nav-link {
    border-radius: 999px;
    padding: 0.5rem 0.9rem;
    transition: background-color 0.2s ease, color 0.2s ease;
}

.topbar-clinica .nav-link:hover,
.topbar-clinica .nav-link:focus,
.topbar-clinica .nav-link.active {
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
}

.topbar-clinica .dropdown-menu {
    border-radius: 16px;
    border: 0;
    padding: 0.45rem;
    background: rgba(255, 255, 255, 0.98);
    box-shadow: 0 18px 36px rgba(18, 51, 62, 0.14);
}

.topbar-clinica .dropdown-item {
    border-radius: 12px;
    padding: 0.45rem 0.75rem;
    font-size: 0.86rem;
    color: #1f3642;
}

.topbar-clinica .dropdown-item:hover,
.topbar-clinica .dropdown-item:focus,
.topbar-clinica .dropdown-item.active {
    background: rgba(31, 122, 140, 0.1);
    color: #0f4c5c;
}

.topbar-clinica .navbar-toggler {
    border-color: rgba(255, 255, 255, 0.35);
}

.topbar-clinica .navbar-toggler-icon {
    filter: brightness(0) invert(1);
}

.topbar-clinica .badge-perfil {
    background: rgba(255, 255, 255, 0.16);
    border-radius: 999px;
    padding: 0.35rem 0.75rem;
    font-size: 0.8rem;
}
</style>

<nav class="navbar navbar-expand-lg topbar-clinica shadow-sm">
<div class="container">

<a class="navbar-brand fw-bold" href="<?= app_profile_home() ?>"><?= app_h(app_current_clinic_name()) ?></a>

<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal" aria-controls="menuPrincipal" aria-expanded="false" aria-label="Abrir menu">
<span class="navbar-toggler-icon"></span>
</button>

<div class="collapse navbar-collapse" id="menuPrincipal">

<ul class="navbar-nav me-auto gap-lg-2">
<?php foreach ($links as $link): ?>
<?php if (!empty($link['children'])): ?>
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle <?= menuDropdownAtivo($link, $paginaAtual) ?>" href="<?= app_h($link['href']) ?>" role="button" data-bs-toggle="dropdown" aria-expanded="false"><?= app_h($link['label']) ?></a>
<ul class="dropdown-menu">
<?php foreach ($link['children'] as $child): ?>
<li><a class="dropdown-item <?= menuChildAtivo($child, $paginaAtual) ?>" href="<?= app_h($child['href']) ?>"><?= app_h($child['label']) ?></a></li>
<?php endforeach; ?>
</ul>
</li>
<?php else: ?>
<li class="nav-item">
<a class="nav-link <?= menuAtivo($link['pages'], $paginaAtual) ?>" href="<?= app_h($link['href']) ?>"><?= app_h($link['label']) ?></a>
</li>
<?php endif; ?>
<?php endforeach; ?>
</ul>

<div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 mt-3 mt-lg-0">
<?php if ($usuarioAtual): ?>
<span class="navbar-text badge-perfil">
<?= app_h($usuarioAtual['nome_exibicao']) ?> | <?= app_h(ucfirst((string) $perfilAtual)) ?>
</span>
<?php endif; ?>
<?php if ($canEditClinic): ?>
<a class="btn btn-outline-light btn-sm rounded-pill px-3" href="administrativo_clinica.php">Dados da clinica</a>
<?php endif; ?>
<a class="btn btn-light btn-sm rounded-pill px-3" href="logout.php">Sair</a>
</div>

</div>
</div>
</nav>

<?php if ($flash && $menuFlashMode !== 'manual'): ?>
<div class="container mt-3">
<div class="alert alert-<?= app_h($flash['type']) ?> mb-0"><?= app_h($flash['message']) ?></div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
