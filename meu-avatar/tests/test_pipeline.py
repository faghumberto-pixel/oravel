"""Valida importacoes, pre-processador de pausas e geracao real de audio."""
import sys
from pathlib import Path

import pytest
from PIL import Image

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))


def test_importacoes():
    import animador, app, compositor, config, gerador_cenario, voz  # noqa: F401, E401


def test_pausas():
    import voz
    t = voz.dividir_em_trechos("Ola. [pausa] Tudo bem... sim [pausa:1.5s] fim")
    assert [p for _, p in t] == [700, 350, 1500, 0]
    assert t[1][0].endswith(".")


def test_bokeh_e_composicao():
    import compositor, gerador_cenario
    fundo = gerador_cenario._gerar_procedural("x")
    assert gerador_cenario.aplicar_bokeh(fundo).size == fundo.size
    av = Image.new("RGBA", (200, 400), (200, 150, 120, 255))
    quadro = compositor.compor(av, fundo)
    assert quadro.size == fundo.size


def test_geracao_audio():
    import voz
    try:
        mp3 = voz.gerar_audio("Ola, isto e um teste. [pausa] Funcionou.", "Francisca (feminina)", rate=-5)
    except Exception as e:  # sem internet: edge-tts depende do servico da Microsoft
        pytest.skip(f"edge-tts indisponivel: {e}")
    assert mp3.exists() and mp3.stat().st_size > 2000
