"""Modulo 1 - Gerador de cenario: arquivo da pasta cenarios/ ou IA a partir de prompt."""
import io
import os
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter

from config import CENARIOS, TEMP, detectar_gpu

LARGURA, ALTURA = 768, 768
MODELO_LOCAL = "stabilityai/sdxl-turbo"
EXTENSOES = {".png", ".jpg", ".jpeg", ".webp"}
_pipe = None


def listar_cenarios() -> list[str]:
    return sorted(p.name for p in CENARIOS.iterdir() if p.suffix.lower() in EXTENSOES)


def carregar_cenario(origem) -> Image.Image:
    """Aceita nome de arquivo em cenarios/, caminho ou PIL.Image."""
    if isinstance(origem, Image.Image):
        img = origem
    else:
        caminho = Path(origem)
        if not caminho.exists():
            caminho = CENARIOS / str(origem)
        img = Image.open(caminho)
    return _ajustar(img.convert("RGB"))


def _ajustar(img: Image.Image) -> Image.Image:
    """Corta ao centro na proporcao alvo e redimensiona."""
    alvo = LARGURA / ALTURA
    w, h = img.size
    if w / h > alvo:
        nw = int(h * alvo)
        img = img.crop(((w - nw) // 2, 0, (w - nw) // 2 + nw, h))
    else:
        nh = int(w / alvo)
        img = img.crop((0, (h - nh) // 2, w, (h - nh) // 2 + nh))
    return img.resize((LARGURA, ALTURA), Image.LANCZOS)


def aplicar_bokeh(img: Image.Image, raio: float = 6.0) -> Image.Image:
    """Desfoque suave de profundidade + leve vinheta para simular lente profissional."""
    desfocada = img.filter(ImageFilter.GaussianBlur(radius=raio))
    mascara = Image.new("L", img.size, 0)
    d = ImageDraw.Draw(mascara)
    w, h = img.size
    d.ellipse((w * 0.2, h * 0.1, w * 0.8, h * 0.95), fill=255)
    mascara = mascara.filter(ImageFilter.GaussianBlur(radius=w * 0.12))
    # centro um pouco mais nitido que as bordas, mas todo o fundo recebe desfoque base
    base = img.filter(ImageFilter.GaussianBlur(radius=raio * 0.4))
    return Image.composite(base, desfocada, mascara)


def _gerar_local(prompt: str) -> Image.Image:
    global _pipe
    import torch
    from diffusers import AutoPipelineForText2Image
    if _pipe is None:
        _pipe = AutoPipelineForText2Image.from_pretrained(
            MODELO_LOCAL, torch_dtype=torch.float16, variant="fp16").to("cuda")
    return _pipe(prompt=prompt, num_inference_steps=2, guidance_scale=0.0,
                 width=512, height=512).images[0]


def _gerar_api(prompt: str) -> Image.Image:
    """HuggingFace Inference (token opcional em HF_TOKEN; limite maior com token)."""
    from huggingface_hub import InferenceClient
    cliente = InferenceClient(token=os.environ.get("HF_TOKEN"))
    return cliente.text_to_image(prompt, model="stabilityai/stable-diffusion-xl-base-1.0")


def _gerar_procedural(prompt: str) -> Image.Image:
    """Ultimo recurso offline: gradiente suave de estudio (nao interpreta o prompt)."""
    img = Image.new("RGB", (LARGURA, ALTURA))
    px = img.load()
    for y in range(ALTURA):
        t = y / ALTURA
        cor = (int(60 + 40 * t), int(75 + 35 * t), int(95 + 25 * t))
        for x in range(LARGURA):
            px[x, y] = cor
    return img


def gerar_cenario_ia(prompt: str) -> tuple[Image.Image, str]:
    """Retorna (imagem, metodo_usado). Tenta GPU local -> API -> gradiente procedural."""
    if not prompt or not prompt.strip():
        raise ValueError("Descreva o cenario.")
    prompt_full = prompt.strip() + ", photorealistic, professional photography, depth of field, empty room, no people"
    gpu = detectar_gpu()
    if gpu["suficiente"]:
        try:
            return _ajustar(_gerar_local(prompt_full).convert("RGB")), "sdxl-turbo (GPU local)"
        except Exception as e:  # noqa: BLE001
            print(f"[cenario] GPU falhou: {e}")
    try:
        return _ajustar(_gerar_api(prompt_full).convert("RGB")), "HuggingFace Inference API"
    except Exception as e:  # noqa: BLE001
        print(f"[cenario] API falhou: {e}")
    return _gerar_procedural(prompt), "gradiente procedural (sem IA - API indisponivel)"


def obter_cenario(arquivo=None, prompt: str | None = None, bokeh: float = 6.0):
    """Ponto de entrada: arquivo/upload OU prompt. Retorna (imagem_com_bokeh, metodo)."""
    if arquivo is not None:
        img, metodo = carregar_cenario(arquivo), "imagem enviada"
    elif prompt:
        img, metodo = gerar_cenario_ia(prompt)
    else:
        raise ValueError("Informe uma imagem de fundo ou um prompt.")
    return (aplicar_bokeh(img, bokeh) if bokeh > 0 else img), metodo
