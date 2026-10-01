{{--
    Anexo I do Contrato de Assinatura -- Plano de Implantação (período e o que
    será feito). Compartilhado entre o PDF e a tela de assinatura, igual ao
    parcial de cláusulas. Só aparece quando o contrato prevê taxa de
    implantação. Espera $contract (o Tenant). Prazo e limites vêm de
    config('oravel.implementation') -- valores padrão PROPOSTOS (decisão
    comercial do dono do produto, a confirmar), não escritos no texto.
--}}
@php
    $impl = config('oravel.implementation');
    $days = (int) $impl['days'];
    $trainingHours = (int) $impl['training_hours'];
    $assistedDays = (int) $impl['assisted_days'];
    $dataDays = (int) $impl['data_deadline_business_days'];
    $d = fn (float $fraction) => max(1, (int) round($days * $fraction));
@endphp

@if ($contract->implementationAmount() > 0)
    <div style="margin-top:14px; padding:10px 12px; border-left:4px solid #ea580c; background:#fff7ed;">
        <div class="clause-title" style="font-size:12px;">ANEXO I — PLANO DE IMPLANTAÇÃO</div>
        <div class="clause" style="margin-top:6px;">
            <strong>Período:</strong> a implantação será realizada em até <strong>{{ $days }} dias corridos</strong>,
            contados da confirmação do primeiro pagamento e do recebimento, pela Oravel, das informações indicadas
            nas "Responsabilidades do Contratante" abaixo. A taxa de implantação remunera exclusivamente as
            etapas descritas neste Anexo.
        </div>

        <div class="clause"><span class="clause-title">Etapa 1 — Alinhamento e levantamento (dias 1 a {{ $d(0.17) }}).</span>
            Reunião de início, levantamento do funcionamento atual do Contratante (frota, contratos, manutenção,
            estoque) e definição do responsável do Contratante pela implantação.
        </div>
        <div class="clause"><span class="clause-title">Etapa 2 — Configuração e parametrização (dias {{ $d(0.1) }} a {{ $d(0.5) }}).</span>
            Preparação do ambiente do Contratante; cadastro dos usuários e perfis de acesso; unidades/filiais,
            categorias e demais parâmetros; ativação e ajuste dos módulos contratados.
        </div>
        <div class="clause"><span class="clause-title">Etapa 3 — Carga inicial de dados (dias {{ $d(0.17) }} a {{ $d(0.67) }}).</span>
            Cadastro/importação inicial de ativos (frota), clientes, fornecedores e materiais, a partir de
            planilhas fornecidas pelo Contratante no modelo indicado pela Oravel.
        </div>
        <div class="clause"><span class="clause-title">Etapa 4 — Treinamento (dias {{ $d(0.5) }} a {{ $d(0.83) }}).</span>
            Treinamento remoto das equipes do Contratante (gestão, manutenção e técnicos em campo), em até
            <strong>{{ $trainingHours }} horas</strong> no total, com material de apoio.
        </div>
        <div class="clause"><span class="clause-title">Etapa 5 — Início da operação e acompanhamento (dias {{ $d(0.83) }} a {{ $days }}).</span>
            Início do uso em produção e acompanhamento assistido por até <strong>{{ $assistedDays }} dias</strong>
            após o início da operação, para esclarecimento de dúvidas e ajustes finos.
        </div>

        <div class="clause"><span class="clause-title">Responsabilidades do Contratante.</span>
            Designar um responsável pela implantação; fornecer as informações e planilhas solicitadas em até
            {{ $dataDays }} dias úteis da solicitação; e disponibilizar as equipes para o treinamento. Atrasos do
            Contratante nessas entregas suspendem a contagem do prazo, que volta a correr quando regularizados.
        </div>
        <div class="clause"><span class="clause-title">Fora do escopo.</span>
            Desenvolvimentos ou customizações específicas, integrações com sistemas de terceiros, migração de
            bases complexas e treinamentos adicionais além do limite acima não estão incluídos e serão
            orçados à parte, mediante aprovação do Contratante.
        </div>
        <div class="clause"><span class="clause-title">Conclusão.</span>
            A implantação é considerada concluída ao final da Etapa 5. Não havendo manifestação fundamentada do
            Contratante em até 5 (cinco) dias após o término do acompanhamento, os serviços consideram-se aceitos.
        </div>
    </div>
@endif
