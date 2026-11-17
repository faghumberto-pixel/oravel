"""Modulo 3 - Voz natural em portugues com edge-tts."""
import asyncio
import re
import subprocess
import uuid
from pathlib import Path

import edge_tts

from config import TEMP, ffmpeg_path

VOZES = {
    "Antonio (masculina)": "pt-BR-AntonioNeural",
    "Francisca (feminina)": "pt-BR-FranciscaNeural",
    "Thalita (feminina, multilingue/expressiva)": "pt-BR-ThalitaMultilingualNeural",
}
# Ajuste base por voz (somado aos sliders): deixa a fala do Antonio menos "locutor".
AJUSTE_BASE = {"pt-BR-AntonioNeural": {"rate": 3, "pitch": -2}}

# So pausas com duracao explicita viram silencio real; [pausa] e ... viram respiro inline.
_TOKEN = re.compile(r"\[pausa:(\d+(?:\.\d+)?)s?\]", re.IGNORECASE)
_PAUSA_SIMPLES = re.compile(r"\[pausa\]", re.IGNORECASE)
_SIMBOLOS = re.compile(r"[*#_~`\[\]•]")
_EMOJI = re.compile("[\U0001F000-\U0001FAFF\u2600-\u27BF\uFE0F]")


def limpar_texto(texto: str) -> str:
    """Remove Markdown/simbolos que o TTS leria em voz alta ("asterisco") e normaliza pausas.

    `[pausa]` vira ", ... " (respiro; sem virgula extra se ja houver pontuacao).
    `[pausa:Ns]` e preservado para virar silencio real em dividir_em_trechos.
    Mantem letras, numeros e pontuacao natural (. , ! ? : ; - ... % R$ aspas, parenteses).
    """
    t = re.sub(r"\[pausa:([^\]]*)\]", lambda m: f"\x01{m.group(1)}\x02", texto, flags=re.IGNORECASE)
    t = _PAUSA_SIMPLES.sub("\x00", t)
    t = re.sub(r"(?m)^\s*[-•*]\s+", "", t)               # marcadores de lista
    t = _EMOJI.sub("", t)
    t = t.replace("_", " ")                              # snake_case / italico
    t = _SIMBOLOS.sub("", t)                             # * # ~ ` [ ] •
    # quebra de linha = fim de frase (sem duplicar pontuacao existente)
    t = re.sub(r"([.!?:,;])?[ \t]*\n+[ \t]*", lambda m: (m.group(1) or ".") + " ", t)
    # respiro natural
    t = re.sub(r"([.!?:,;])?\s*\x00\s*",
               lambda m: (m.group(1) if m.group(1) else ",") + " ... ", t)
    t = re.sub(r"\x01([^\x02]*)\x02", lambda m: f"[pausa:{m.group(1)}]", t)
    t = re.sub(r"\s{2,}", " ", t)
    return t.strip(" ,")


def fmt_rate(pct: int) -> str:
    return f"{int(pct):+d}%"


def fmt_pitch(hz: int) -> str:
    return f"{int(hz):+d}Hz"


def fmt_volume(pct: int) -> str:
    return f"{int(pct):+d}%"


def dividir_em_trechos(texto: str) -> list[tuple[str, int]]:
    """Limpa o texto e quebra em (trecho, pausa_ms_apos) apenas nas pausas `[pausa:Ns]`."""
    texto = limpar_texto(texto)
    trechos: list[tuple[str, int]] = []
    pos = 0
    for m in _TOKEN.finditer(texto):
        pedaco = texto[pos:m.start()].strip(" ,")
        pausa = int(float(m.group(1)) * 1000)
        if pedaco:
            trechos.append((pedaco, pausa))
        elif trechos:
            trechos[-1] = (trechos[-1][0], trechos[-1][1] + pausa)
        pos = m.end()
    resto = texto[pos:].strip(" ,")
    if resto:
        trechos.append((resto, 0))
    return trechos


async def _sintetizar(texto, voz, rate, pitch, volume, destino: Path):
    com = edge_tts.Communicate(texto, voz, rate=rate, pitch=pitch, volume=volume)
    await com.save(str(destino))


def _silencio(ms: int, destino: Path):
    ff = ffmpeg_path()
    subprocess.run(
        [ff, "-y", "-loglevel", "error", "-f", "lavfi", "-i", "anullsrc=r=24000:cl=mono",
         "-t", f"{ms / 1000:.3f}", "-q:a", "9", str(destino)],
        check=True,
    )


def gerar_audio(texto: str, voz: str = "pt-BR-FranciscaNeural", rate: int = 0,
                pitch: int = 0, volume: int = 0, destino: Path | None = None) -> Path:
    """Gera um .mp3 com a fala. Retorna o caminho do arquivo."""
    if not texto or not texto.strip():
        raise ValueError("Texto vazio.")
    voz = VOZES.get(voz, voz)
    destino = Path(destino) if destino else TEMP / f"fala_{uuid.uuid4().hex[:8]}.mp3"
    trechos = dividir_em_trechos(texto)
    if not trechos:
        raise ValueError("Texto sem conteudo falavel.")
    base = AJUSTE_BASE.get(voz, {})
    r = fmt_rate(rate + base.get("rate", 0))
    p = fmt_pitch(pitch + base.get("pitch", 0))
    v = fmt_volume(volume)

    if len(trechos) == 1 and trechos[0][1] == 0:
        asyncio.run(_sintetizar(trechos[0][0], voz, r, p, v, destino))
        return destino

    partes: list[Path] = []
    for i, (trecho, pausa) in enumerate(trechos):
        pf = TEMP / f"{destino.stem}_p{i}.mp3"
        asyncio.run(_sintetizar(trecho, voz, r, p, v, pf))
        partes.append(pf)
        if pausa:
            sf = TEMP / f"{destino.stem}_s{i}.mp3"
            _silencio(pausa, sf)
            partes.append(sf)
    lista = TEMP / f"{destino.stem}_lista.txt"
    lista.write_text("".join(f"file '{p.resolve()}'\n" for p in partes))
    subprocess.run(
        [ffmpeg_path(), "-y", "-loglevel", "error", "-f", "concat", "-safe", "0",
         "-i", str(lista), "-ar", "24000", "-ac", "1", "-q:a", "4", str(destino)],
        check=True,
    )
    for p in partes + [lista]:
        p.unlink(missing_ok=True)
    return destino
