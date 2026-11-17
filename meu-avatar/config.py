"""Configuracao central: diretorios, deteccao de hardware e localizacao do ffmpeg."""
import os
import shutil
import subprocess
from pathlib import Path

BASE = Path(__file__).resolve().parent
AVATARES = BASE / "avatares"
CENARIOS = BASE / "cenarios"
OUTPUT = BASE / "output"
TEMP = BASE / "temp"
for _d in (AVATARES, CENARIOS, OUTPUT, TEMP):
    _d.mkdir(exist_ok=True)

MIN_VRAM_GB = 8.0


def ffmpeg_path() -> str | None:
    """ffmpeg do sistema, ou o binario embutido do imageio-ffmpeg."""
    sistema = shutil.which("ffmpeg")
    if sistema:
        return sistema
    try:
        import imageio_ffmpeg
        return imageio_ffmpeg.get_ffmpeg_exe()
    except Exception:
        return None


def detectar_gpu() -> dict:
    """Retorna {'tem_gpu', 'nome', 'vram_gb', 'suficiente'} via nvidia-smi."""
    info = {"tem_gpu": False, "nome": None, "vram_gb": 0.0, "suficiente": False}
    if not shutil.which("nvidia-smi"):
        return info
    try:
        saida = subprocess.run(
            ["nvidia-smi", "--query-gpu=name,memory.total", "--format=csv,noheader,nounits"],
            capture_output=True, text=True, timeout=10, check=True,
        ).stdout.strip().splitlines()[0]
        nome, mem = [p.strip() for p in saida.split(",")]
        info.update(tem_gpu=True, nome=nome, vram_gb=round(float(mem) / 1024, 1))
        info["suficiente"] = info["vram_gb"] >= MIN_VRAM_GB
    except Exception:
        pass
    return info


def resumo_ambiente() -> str:
    gpu = detectar_gpu()
    if gpu["tem_gpu"]:
        gpu_txt = f"{gpu['nome']} ({gpu['vram_gb']} GB)" + ("" if gpu["suficiente"] else " - VRAM insuficiente")
    else:
        gpu_txt = "nenhuma GPU NVIDIA"
    ff = ffmpeg_path() or "NAO ENCONTRADO"
    return f"GPU: {gpu_txt} | ffmpeg: {ff}"


if __name__ == "__main__":
    print(resumo_ambiente())
