# Como usar o Avatar Falante

## Parte A: Ligar o sistema no Google Colab

- [ ] Acesse https://colab.research.google.com e entre com sua conta Google.
- [ ] Menu `Arquivo > Fazer upload de notebook` e escolha `colab_runner.ipynb`.
- [ ] Menu `Ambiente de execução > Alterar tipo de ambiente de execução > GPU T4 > Salvar`.
- [ ] Rode as células **em ordem** (botão play de cada uma):
  1. Verificação da GPU (deve imprimir `CUDA: True`).
  2. Upload: clique em "Escolher arquivos" e envie o `meu-avatar.zip`.
  3. Instalação de pacotes.
  4. Instalação do SadTalker (demora vários minutos; baixa alguns GB).
  5. Correções de compatibilidade.
  6. Última célula: sobe o app.
- [ ] Se aparecer erro de Pillow ou "restart session", reinicie a sessão
  (`Ambiente de execução > Reiniciar sessão`) e rode de novo a partir da célula 2.
- [ ] Na saída da última célula, abra o link `https://xxxx.gradio.live`.
  O link vale enquanto a célula estiver rodando.

## Parte B: Configurar Avatar e Cenário na Tela

Coluna da **esquerda** (Configuração visual):

- [ ] **Foto base do avatar**: caixa de cima, `Foto base do avatar (rosto frontal)`.
  Use foto frontal, rosto inteiro, boa luz.
- [ ] **Cenário**: escolha uma opção em `Cenário`:
  - `Enviar fundo`: envie a imagem do escritório na caixa **`Enviar fundo`**
    (é a segunda caixa de imagem, abaixo do seletor; **não** a do avatar).
    Também dá para escolher um arquivo da pasta `cenarios/` no dropdown.
  - `Descrever cenário por texto`: escreva em `Descreva o cenário`
    (ex.: `escritório moderno, luz natural de janela`).
- [ ] Clique em **Pré-visualizar composição** e confira o resultado.
  Se estiver estranho, ajuste em `Ajustes finos`: tamanho do avatar, desfoque do fundo,
  harmonização de cor. Repita a pré-visualização até gostar.

## Parte C: Gerar a Voz Natural e o Vídeo

Coluna da **direita** (Áudio e vídeo):

- [ ] Escreva a fala em `Fala em português`.
- [ ] Dicas de pontuação:
  - `[pausa]` e `...` = respiro natural dentro da frase; `[pausa:1.5s]` = silêncio de 1,5 s.
  - Símbolos como `*`, `#`, `_` são removidos automaticamente (não são mais lidos em voz alta).
  - Frases curtas, vírgulas e pontos soam mais naturais.
- [ ] Escolha a voz: `Antonio (masculina)` = `pt-BR-AntonioNeural`,
  `Francisca (feminina)` = `pt-BR-FranciscaNeural`,
  `Thalita` = `pt-BR-ThalitaMultilingualNeural` (mais expressiva).
- [ ] Ajuste velocidade, tom e volume (comece em 0; velocidade -5% a -10% costuma soar mais natural).
- [ ] Deixe marcadas **Movimentos de cabeça** e **Restauração facial (GFPGAN)**.
- [ ] Clique em **Gerar Vídeo Falante** e aguarde (minutos).
- [ ] Confira o campo `Status`: deve dizer `SadTalker + GFPGAN`. Se aparecer um
  erro vermelho dizendo que o SadTalker ou os checkpoints não foram encontrados, volte à Parte A
  (células de instalação). Deixe **desmarcado** "Permitir vídeo estático".
- [ ] Para baixar o `.mp4`: no player de vídeo, use o ícone de download no canto superior direito.
