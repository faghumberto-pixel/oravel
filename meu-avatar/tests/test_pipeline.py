"""Valida importacoes, pre-processador de pausas e geracao real de audio."""
import sys
from pathlib import Path

import pytest
from PIL import Image

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))


def test_importacoes():
    import animador, app, compositor, config, gerador_cenario, voz  # noqa: F401, E401


def test_limpeza_e_pausas():
    import voz
    sujo = "# Ola\n**Tudo** bem? [pausa] Vamos `falar_de` ~isso~ • sim [pausa:1.5s] fim"
    limpo = voz.limpar_texto(sujo)
    assert not any(c in limpo for c in "*#_~`•")
    assert "[pausa]" not in limpo and "..." in limpo
    trechos = voz.dividir_em_trechos(sujo)
    assert [p for _, p in trechos] == [1500, 0]


def test_vozes():
    import voz
    assert "pt-BR-ThalitaMultilingualNeural" in voz.VOZES.values()
    assert "pt-BR-AntonioNeural" in voz.AJUSTE_BASE


def test_animador_sem_gpu_nao_gera_estatico_em_silencio(tmp_path):
    import animador
    img = tmp_path / "i.png"; Image.new("RGB", (64, 64)).save(img)
    aud = tmp_path / "a.mp3"; aud.write_bytes(b"x")
    if animador.detectar_gpu()["suficiente"] and animador.sadtalker_instalado():
        pytest.skip("ambiente com GPU e SadTalker")
    with pytest.raises(animador.AnimadorErro) as e:
        animador.animar(img, aud)
    assert "Diagnostico" in str(e.value)


def test_localizar_sadtalker(tmp_path, monkeypatch):
    import animador
    (tmp_path / "inference.py").write_text("")
    monkeypatch.setenv("SADTALKER_DIR", str(tmp_path))
    assert animador.localizar_sadtalker() == tmp_path.resolve()
    assert not animador.checkpoints_ok(tmp_path)


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
