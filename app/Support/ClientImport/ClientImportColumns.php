<?php

namespace App\Support\ClientImport;

use App\Models\Client;
use App\Support\AssetImport\AssetImportColumns;

/**
 * Colunas da planilha de importação de clientes (modelo para download + ClientExcelImporter).
 * Fora de propósito: acesso ao portal/senha (concedido depois, cliente a cliente) e a análise
 * de risco/documentos anexados (preenchidos pela equipe).
 */
class ClientImportColumns
{
    public const SHEET = 'Clientes';

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        $c = fn (string $group, string $title, string $field, string $type = 'text', string $help = '', string $example = '', bool $required = false, ?array $enum = null) => [
            'group' => $group, 'title' => $title, 'target' => 'client', 'field' => $field, 'type' => $type,
            'help' => $help, 'example' => $example, 'required' => $required, 'enum' => $enum,
        ];
        $G1 = 'Identificação';
        $G2 = 'Endereço de Faturamento';
        $G3 = 'Entrega / Obra';
        $G4 = 'Contatos';
        $G5 = 'Representante Legal';

        return [
            $c($G1, 'Razão Social', 'name', 'text', 'Nome da empresa (ou da pessoa).', 'Construtora Exemplo Ltda', true),
            $c($G1, 'Nome Fantasia', 'fantasy_name', 'text', '', 'Exemplo Construções'),
            $c($G1, 'CNPJ', 'document', 'doc', 'CNPJ (14 dígitos) ou CPF (11), com ou sem pontuação. Usado para não duplicar clientes.', '12.345.678/0001-90'),
            $c($G1, 'Inscrição Estadual', 'state_registration', 'text'),
            $c($G1, 'Inscrição Municipal', 'municipal_registration', 'text'),
            $c($G1, 'Regime Tributário', 'tax_regime', 'text', 'Ex.: Simples Nacional, Lucro Presumido, Lucro Real.', 'Simples Nacional'),
            $c($G1, 'Nicho', 'activity_type', 'enum', 'Eventos, Industrial / Hospitalar, Construção Civil, Locação de Equipamentos ou Outro.', 'Construção Civil', false, array_flip(Client::nicheLabels())),
            $c($G2, 'Logradouro e Nº', 'address', 'text', '', 'Av. Brasil, 1000'),
            $c($G2, 'Complemento', 'address_complement', 'text'),
            $c($G2, 'Bairro', 'neighborhood', 'text'),
            $c($G2, 'Cidade', 'city', 'text', '', 'Campinas'),
            $c($G2, 'UF', 'state', 'uf', 'Sigla do estado, 2 letras.', 'SP'),
            $c($G2, 'CEP', 'zip_code', 'text', '', '13010-000'),
            $c($G3, 'Endereço Completo da Obra', 'delivery_address', 'text', 'Local de entrega / canteiro, se diferente do faturamento.'),
            $c($G3, 'Responsável na Obra', 'site_manager', 'text'),
            $c($G3, 'Telefone do Canteiro', 'site_phone', 'text'),
            $c($G4, 'Pessoa de Contato', 'contact_name', 'text', '', 'Maria Souza'),
            $c($G4, 'E-mail Principal', 'email', 'email', 'E-mail de contato principal.', 'contato@exemplo.com.br'),
            $c($G4, 'Telefone Comercial', 'phone', 'text', '', '(19) 3333-4444'),
            $c($G4, 'WhatsApp', 'whatsapp', 'text', '', '(19) 99999-8888'),
            $c($G4, 'E-mail Financeiro', 'email_financial', 'email'),
            $c($G4, 'E-mail Suprimentos', 'email_purchasing', 'email'),
            $c($G5, 'Representante Legal', 'legal_name', 'text'),
            $c($G5, 'CPF do Representante', 'legal_cpf', 'text'),
            $c($G5, 'RG do Representante', 'legal_rg', 'text'),
            $c($G5, 'Cargo do Representante', 'legal_role', 'text'),
        ];
    }

    public static function writeTemplate(string $target): void
    {
        AssetImportColumns::writeTemplate($target, self::all(), self::SHEET, [
            'Modelo de cadastro de clientes - Oravel',
            'Preencha a aba "Clientes" a partir da linha 3, uma linha por cliente. Não altere os títulos das colunas.',
            'Colunas com * são obrigatórias. Campos sem informação podem ficar em branco.',
            'O CNPJ/CPF evita duplicidade: um cliente que já existe com o mesmo documento (ou, sem documento, o mesmo nome) é ignorado ou atualizado, conforme a opção escolhida na importação.',
            'Acesso ao portal do cliente não faz parte deste modelo (é concedido depois, cliente a cliente).',
            '',
            '',
        ]);
    }
}
