"""Modulo 2 - Composicao do avatar sobre o cenario (rembg + harmonizacao de cor)."""
from pathlib import Path

import numpy as np
from PIL import Image, ImageFilter

from config import AVATARES, TEMP

MODELOS = ("u2net_human_seg", "isnet-general-use")
_sessoes: dict = {}


def listar_avatares() -> list[str]:
    return sorted(p.name for p in AVATARES.iterdir() if p.suffix.lower() in {".png", ".jpg", ".jpeg", ".webp"})


def salvar_avatar(img: Image.Image, nome: str = "avatar") -> Path:
    destino = AVATARES / f"{nome}.png"
    img.convert("RGBA").save(destino)
    return destino


def recortar(img: Image.Image, modelo: str = "u2net_human_seg") -> Image.Image:
    """Recorta a pessoa. alpha_matting refina fios de cabelo."""
    from rembg import new_session, remove
    if modelo not in _sessoes:
        _sessoes[modelo] = new_session(modelo)
    return remove(
        img.convert("RGB"), session=_sessoes[modelo], alpha_matting=True,
        alpha_matting_foreground_threshold=240, alpha_matting_background_threshold=10,
        alpha_matting_erode_size=8,
    ).convert("RGBA")


def harmonizar(avatar: Image.Image, cenario: Image.Image, forca: float = 0.35) -> Image.Image:
    """Aproxima brilho e balanco de cor do avatar ao cenario (media por canal).

    forca 0 = sem ajuste, 1 = iguala totalmente as medias. Valores altos deixam
    a pele artificial; 0.25-0.45 e um bom intervalo.
    """
    a = np.asarray(avatar, dtype=np.float32)
    alpha = a[..., 3] > 128
    if not alpha.any():
        return avatar
    cen = np.asarray(cenario.convert("RGB"), dtype=np.float32)
    media_a = a[..., :3][alpha].mean(axis=0)
    media_c = cen.reshape(-1, 3).mean(axis=0)
    ganho = 1.0 + forca * (media_c / np.maximum(media_a, 1.0) - 1.0)
    ganho = np.clip(ganho, 0.8, 1.2)  # evita desvios extremos de cor
    a[..., :3] = np.clip(a[..., :3] * ganho, 0, 255)
    return Image.fromarray(a.astype(np.uint8), "RGBA")


def compor(avatar_rgba: Image.Image, cenario: Image.Image, escala: float = 0.85,
           forca_cor: float = 0.35, destino: Path | None = None) -> Image.Image:
    """Cola o avatar (ja recortado) no cenario, centralizado na base, com borda suave."""
    cenario = cenario.convert("RGB")
    cw, ch = cenario.size
    alvo_h = int(ch * escala)
    ratio = alvo_h / avatar_rgba.height
    alvo_w = int(avatar_rgba.width * ratio)
    if alvo_w > cw:  # avatar muito largo: limita pela largura
        alvo_w = cw
        alvo_h = int(avatar_rgba.height * alvo_w / avatar_rgba.width)
    av = avatar_rgba.resize((alvo_w, alvo_h), Image.LANCZOS)
    av = harmonizar(av, cenario, forca_cor)
    # suaviza levemente a borda do alpha para evitar halo
    r, g, b, al = av.split()
    al = al.filter(ImageFilter.GaussianBlur(0.8))
    av = Image.merge("RGBA", (r, g, b, al))
    quadro = cenario.copy()
    quadro.paste(av, ((cw - alvo_w) // 2, ch - alvo_h), av)
    if destino:
        quadro.save(destino)
    return quadro


def preparar(avatar_img: Image.Image, cenario: Image.Image, modelo: str = "u2net_human_seg",
             escala: float = 0.85, forca_cor: float = 0.35) -> Image.Image:
    return compor(recortar(avatar_img, modelo), cenario, escala, forca_cor)
