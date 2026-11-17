# Avatar Falante PT-BR

App web local (Gradio) que gera video de um avatar falando portugues, mantendo a identidade
da pessoa em cenarios enviados ou gerados por IA.

## Pipeline

| Modulo | Arquivo | O que faz |
|---|---|---|
| 1. Cenario | `gerador_cenario.py` | Fundo de `cenarios/`, upload ou IA (SDXL-Turbo na GPU -> HF Inference API -> gradiente) + bokeh (GaussianBlur) |
| 2. Composicao | `compositor.py` | Recorte com `rembg` (`u2net_human_seg` / `isnet-general-use`, alpha matting) e harmonizacao de cor |
| 3. Voz | `voz.py` | `edge-tts` (Antonio / Francisca), rate/pitch/volume, pausas `[pausa]`, `[pausa:1.5s]`, `...` |
| 4. Animacao | `animador.py` | SadTalker + GFPGAN; fallbacks abaixo |
| UI | `app.py` | Painel Gradio |

## Instalacao e uso local

```bash
uv venv .venv --python 3.12        # ou: python3 -m venv .venv
uv pip install -p .venv/bin/python -r requirements.txt
.venv/bin/python config.py         # mostra GPU / ffmpeg detectados
.venv/bin/python app.py            # http://127.0.0.1:7860
```

`ffmpeg`: usa o do sistema ou o binario do `imageio-ffmpeg` (instalado pelo requirements).
Na primeira composicao o `rembg` baixa o modelo (~176 MB).

## Modos de execucao (automatico)

1. **GPU NVIDIA >= 8 GB + SadTalker instalado**: animacao local. Instale com
   `python -c "import animador; animador.instalar_sadtalker()"` e instale `torch` com CUDA.
2. **Sem GPU suficiente, com Colab**: rode `colab_runner.ipynb` (GPU T4); a ultima celula abre
   o app com link publico (`share=True`). Para usar o Colab so como motor de animacao do app
   local: `REMOTE_ANIMADOR_URL=https://xxxx.gradio.live .venv/bin/python app.py`.
3. **Sem nada disso**: o app gera um video **estatico com o audio, sem sincronia labial**. Serve
   apenas para validar o fluxo; o status na interface informa isso.

Geracao de cenario por IA sem GPU usa a HF Inference API (defina `HF_TOKEN` para evitar limites);
se falhar, cai num gradiente que **nao** interpreta o prompt.

## Naturalidade (flags do SadTalker em `animador.flags_naturalidade`)

`--preprocess full` (cola o rosto na imagem inteira, sem distorcer o fundo), `--enhancer gfpgan`,
`--pose_style 0` (cabeca sutil), `--expression_scale 1.0`. Piscadas vem do proprio modelo; para
controle total use `--ref_eyeblink` com um video de referencia.

## Testes

```bash
.venv/bin/python -m pytest tests -v
```
Valida importacoes, pre-processador de pausas, bokeh/composicao e geracao real de audio
(pulado se nao houver internet, pois o `edge-tts` usa o servico da Microsoft).

## Problemas conhecidos

- O SadTalker fixa versoes antigas de numpy/torchvision; a instalacao no Colab e fragil e a
  `colab_runner.ipynb` aplica patches. **O notebook e o caminho do SadTalker nao foram executados
  aqui (maquina sem GPU)**; podem exigir ajustes.
- Use foto frontal, boa luz, rosto bem visivel. Use apenas imagens de pessoas com autorizacao.
