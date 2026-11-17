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

class AnimadorErro(RuntimeError):
    """Falha de animacao que deve ser mostrada ao usuario (nunca engolida)."""


def _candidatos() -> list[Path]:
    env = os.environ.get("SADTALKER_DIR")
    cands = [Path(env)] if env else []
    cands += [Path("/content/meu-avatar/SadTalker"), Path("./SadTalker"), Path("./engine/SadTalker"),
              BASE / "SadTalker", BASE / "engine" / "SadTalker"]
    return [c.resolve() for c in cands]


def localizar_sadtalker() -> Path | None:
    """Primeiro diretorio com inference.py. Resolvido a cada chamada (nao no import)."""
    for c in _candidatos():
        if (c / "inference.py").exists():
            return c
    return None


def checkpoints_ok(raiz: Path) -> bool:
    ckpt = raiz / "checkpoints"
    if not ckpt.exists():
        return False
    return any(ckpt.glob("*.safetensors")) or any(ckpt.glob("*.pth*"))


def sadtalker_instalado() -> bool:
    raiz = localizar_sadtalker()
    return bool(raiz and checkpoints_ok(raiz))


def diagnostico() -> str:
    gpu = detectar_gpu()
    raiz = localizar_sadtalker()
    linhas = [f"GPU: {gpu['nome']} ({gpu['vram_gb']} GB)" if gpu["tem_gpu"] else "GPU: nenhuma",
              f"SadTalker: {raiz or 'NAO ENCONTRADO em ' + ', '.join(map(str, _candidatos()[:4]))}"]
    if raiz:
        linhas.append(f"Checkpoints: {'ok' if checkpoints_ok(raiz) else 'AUSENTES em ' + str(raiz / 'checkpoints')}")
    return " | ".join(linhas)


def instalar_sadtalker() -> str:
    """Clona o SadTalker e baixa checkpoints (rode no Colab/GPU; ~several GB)."""
    destino = localizar_sadtalker() or BASE / "SadTalker"
    if not destino.exists():
        subprocess.run(["git", "clone", "--depth", "1", "https://github.com/OpenTalker/SadTalker.git",
                        str(destino)], check=True)
    subprocess.run(["bash", str(destino / "scripts" / "download_models.sh")], cwd=destino, check=True)
    return "SadTalker instalado."


def flags_naturalidade(movimento_cabeca: bool = True, restaurar_face: bool = True) -> list[str]:
    """Flags do SadTalker para preservar a naturalidade.

    --preprocess full : mantem o enquadramento e cola o rosto de volta na imagem
                        inteira, sem distorcer o fundo composto.
    --enhancer gfpgan : restauracao facial (remove borrao da boca/dentes).
    --still desativado (movimento ligado) = cabeca se move; o SadTalker recomenda
                        --still com --preprocess full se aparecer emenda do rosto no fundo.
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


def _animar_sadtalker(raiz: Path, imagem: Path, audio: Path, movimento: bool, restaurar: bool) -> Path:
    saida = TEMP / f"st_{uuid.uuid4().hex[:8]}"
    cmd = [sys.executable, "inference.py", "--driven_audio", str(audio.resolve()),
           "--source_image", str(imagem.resolve()), "--result_dir", str(saida.resolve()),
           *flags_naturalidade(movimento, restaurar)]
    proc = subprocess.run(cmd, cwd=raiz, capture_output=True, text=True)
    if proc.returncode != 0:
        cauda = "\n".join((proc.stderr or proc.stdout).strip().splitlines()[-12:])
        raise AnimadorErro(f"SadTalker retornou erro (codigo {proc.returncode}):\n{cauda}")
    videos = sorted(saida.rglob("*.mp4"), key=lambda p: p.stat().st_mtime)
    if not videos:
        raise AnimadorErro("SadTalker terminou sem gerar .mp4.")
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
           nome_saida: str | None = None, permitir_estatico: bool = False) -> tuple[Path, str]:
    """Retorna (caminho_do_mp4, metodo_usado). Levanta AnimadorErro se nao puder animar.

    O video estatico (sem sincronia labial) so e gerado com permitir_estatico=True.
    """
    imagem, audio = Path(imagem), Path(audio)
    gpu = detectar_gpu()
    raiz = localizar_sadtalker()
    video, metodo, motivos = None, None, []

    if not gpu["suficiente"]:
        motivos.append("GPU NVIDIA com >= 8 GB nao detectada")
    elif raiz is None:
        motivos.append("SadTalker nao encontrado (procurei em /content/meu-avatar/SadTalker, ./SadTalker, ./engine/SadTalker)")
    elif not checkpoints_ok(raiz):
        motivos.append(f"checkpoints do SadTalker ausentes em {raiz / 'checkpoints'} - rode a celula de instalacao "
                       "(scripts/download_models.sh)")
    else:
        # erro do SadTalker propaga com o log: nao ha fallback silencioso
        video, metodo = _animar_sadtalker(raiz, imagem, audio, movimento_cabeca, restaurar_face), \
            "SadTalker + GFPGAN (GPU local)"

    if video is None and os.environ.get("REMOTE_ANIMADOR_URL"):
        try:
            video, metodo = _animar_remoto(imagem, audio, movimento_cabeca, restaurar_face), "SadTalker remoto (Colab)"
        except Exception as e:  # noqa: BLE001
            motivos.append(f"instancia remota falhou: {e}")

    if video is None:
        if not permitir_estatico:
            raise AnimadorErro("Nao foi possivel animar o rosto: " + "; ".join(motivos)
                               + f"\nDiagnostico: {diagnostico()}"
                               + "\n(Para gerar so imagem parada + audio, marque 'Permitir video estatico'.)")
        video = _video_estatico(imagem, audio)
        metodo = "VIDEO ESTATICO SEM SINCRONIA LABIAL (" + "; ".join(motivos) + ")"

    destino = OUTPUT / f"{nome_saida or 'video_' + uuid.uuid4().hex[:8]}.mp4"
    shutil.copy(video, destino)
    return destino, metodo
