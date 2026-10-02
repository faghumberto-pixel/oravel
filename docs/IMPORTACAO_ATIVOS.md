# Importação de ativos por Excel

Ativos > **Modelo de importação (Excel)** baixa a planilha; **Importar Excel** envia a preenchida.

- Colunas definidas em um só lugar: `App\Support\AssetImport\AssetImportColumns` (modelo para download e importador leem dele). Sem dados financeiros.
- Importador: `App\Services\AssetExcelImporter` (OpenSpout). Aba "Ativos" (ou a primeira); cabeçalho pelo título da coluna, em qualquer ordem.
- Obrigatórias: Nº Patrimônio e Nome/Modelo. Linhas com erro são puladas e listadas; as demais gravam.
- "Apenas validar" (padrão ligado) roda tudo e desfaz — nada é gravado.
- Nº Patrimônio já existente: ignorado, ou atualizado se "Atualizar" estiver ligado (células em branco não apagam).
- Categoria inexistente é criada; Unidade/Filial Base e Posição no Pátio precisam existir (senão a linha dá erro).
- Blocos Empilhadeira/Plataforma/Gerador/NR-13 só criam a especificação se algum campo do bloco vier preenchido.
- Testes: `tests/Feature/AssetExcelImportTest.php` (usar banco descartável + DatabaseTransactions).
