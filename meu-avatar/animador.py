"""Modulo 4 - Animacao facial (SadTalker + GFPGAN) com fallbacks.

Ordem de tentativa (animar):
  1. SadTalker local        -> GPU NVIDIA com >= 8 GB e SadTalker instalado
  2. Instancia remota       -> app rodando no Colab (REMOTE_ANIMADOR_URL)
  3. Video estatico + audio -> SEM sincronia labial (apenas para testar o fluxo)
"""
import os
import shutil
import subprocess
import sys
import uuid
from pathlib import Path

from config import BASE, OUTPUT, TEMP, detectar_gpu, ffmpeg_path

SADTALKER_DIR = Path(os.environ.get("SADTALKER_DIR", BASE / "SadTalker"))


def sadtalker_instalado() -> bool:
    ckpt = SADTALKER_DIR / "checkpoints"
    return (SADTALKER_DIR / "inference.py").exists() and ckpt.exists() and any(ckpt.iterdir())


def instalar_sadtalker() -> str:
    """Clona o SadTalker e baixa checkpoints (rode no Colab/GPU; ~several GB)."""
    if not SADTALKER_DIR.exists():
        subprocess.run(["git", "clone", "--depth", "1", "https://github.com/OpenTalker/SadTalker.git",
                        str(SADTALKER_DIR)], check=True)
    script = SADTALKER_DIR / "scripts" / "download_models.sh"
    subprocess.run(["bash", str(script)], cwd=SADTALKER_DIR, check=True)
    return "SadTalker instalado."


def flags_naturalidade(movimento_cabeca: bool = True, restaurar_face: bool = True) -> list[str]:
    """Flags do SadTalker para preservar a naturalidade.

    --preprocess full : mantem o enquadramento e cola o rosto de volta na imagem
                        inteira, sem distorcer o fundo composto.
    --enhancer gfpgan : restauracao facial (remove borrao da boca/dentes).
    --pose_style 0    : movimentos de cabeca sutis (0-45; valores altos = mais agitado).
    --still           : reduz o movimento de cabeca (usado quando desmarcado).
    --expression_scale: 1.0 = expressividade natural (acima disso exagera a boca).
    Piscadas: o SadTalker gera piscadas proprias a partir do audio; para controle
    total, passe --ref_eyeblink com um video de referencia.
    """
    flags = ["--preprocess", "full", "--pose_style", "0", "--expression_scale", "1.0", "--size", "256"]
    if restaurar_face:
        flags += ["--enhancer", "gfpgan"]
    if not movimento_cabeca:
        flags += ["--still"]
    return flags


def _animar_sadtalker(imagem: Path, audio: Path, movimento: bool, restaurar: bool) -> Path:
    saida = TEMP / f"st_{uuid.uuid4().hex[:8]}"
    cmd = [sys.executable, "inference.py", "--driven_audio", str(audio.resolve()),
           "--source_image", str(imagem.resolve()), "--result_dir", str(saida.resolve()),
           *flags_naturalidade(movimento, restaurar)]
    subprocess.run(cmd, cwd=SADTALKER_DIR, check=True)
    videos = sorted(saida.rglob("*.mp4"), key=lambda p: p.stat().st_mtime)
    if not videos:
        raise RuntimeError("SadTalker nao gerou video.")
    return videos[-1]


def _animar_remoto(imagem: Path, audio: Path, movimento: bool, restaurar: bool) -> Path:
    from gradio_client import Client, handle_file
    cliente = Client(os.environ["REMOTE_ANIMADOR_URL"])
    resultado = cliente.predict(handle_file(str(imagem)), handle_file(str(audio)),
                                movimento, restaurar, api_name="/animar_api")
    return Path(resultado if isinstance(resultado, str) else resultado["video"])


def _video_estatico(imagem: Path, audio: Path) -> Path:
    destino = TEMP / f"estatico_{uuid.uuid4().hex[:8]}.mp4"
    subprocess.run(
        [ffmpeg_path(), "-y", "-loglevel", "error", "-loop", "1", "-i", str(imagem), "-i", str(audio),
         "-c:v", "libx264", "-tune", "stillimage", "-c:a", "aac", "-pix_fmt", "yuv420p",
         "-vf", "scale=trunc(iw/2)*2:trunc(ih/2)*2", "-shortest", str(destino)],
        check=True,
    )
    return destino


def animar(imagem: Path, audio: Path, movimento_cabeca: bool = True, restaurar_face: bool = True,
           nome_saida: str | None = None) -> tuple[Path, str]:
    """Retorna (caminho_do_mp4, metodo_usado)."""
    imagem, audio = Path(imagem), Path(audio)
    gpu = detectar_gpu()
    metodo, erro = None, None
    video = None
    if gpu["suficiente"] and sadtalker_instalado():
        try:
            video, metodo = _animar_sadtalker(imagem, audio, movimento_cabeca, restaurar_face), "SadTalker + GFPGAN (GPU local)"
        except Exception as e:  # noqa: BLE001
            erro = f"SadTalker falhou: {e}"
    if video is None and os.environ.get("REMOTE_ANIMADOR_URL"):
        try:
            video, metodo = _animar_remoto(imagem, audio, movimento_cabeca, restaurar_face), "SadTalker remoto (Colab)"
        except Exception as e:  # noqa: BLE001
            erro = f"Remoto falhou: {e}"
    if video is None:
        video = _video_estatico(imagem, audio)
        metodo = "VIDEO ESTATICO SEM SINCRONIA LABIAL (sem GPU/SadTalker). Use o Colab para animar" + (f" [{erro}]" if erro else "")
    destino = OUTPUT / f"{nome_saida or 'video_' + uuid.uuid4().hex[:8]}.mp4"
    shutil.copy(video, destino)
    return destino, metodo
