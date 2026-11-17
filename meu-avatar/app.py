"""Interface Gradio: avatar falante em portugues, com cenario por upload ou IA."""
import os
import uuid

import gradio as gr
from PIL import Image

import animador
import compositor
import gerador_cenario
import voz
from config import TEMP, resumo_ambiente


def _imagem_composta(avatar_img, cenario_modo, cenario_upload, cenario_prompt, cenario_salvo,
                     bokeh, escala, forca_cor, modelo_recorte):
    if avatar_img is None:
        raise gr.Error("Envie a foto base do avatar.")
    compositor.salvar_avatar(avatar_img, "avatar_atual")
    if cenario_modo == "Enviar fundo":
        origem = cenario_upload if cenario_upload is not None else cenario_salvo
        if origem is None:
            raise gr.Error("Envie um fundo ou escolha um da pasta cenarios/.")
        fundo, metodo = gerador_cenario.obter_cenario(arquivo=origem, bokeh=bokeh)
    else:
        fundo, metodo = gerador_cenario.obter_cenario(prompt=cenario_prompt, bokeh=bokeh)
    quadro = compositor.preparar(avatar_img, fundo, modelo_recorte, escala, forca_cor)
    return quadro, f"Cenario: {metodo}"


def previsualizar(*args):
    quadro, info = _imagem_composta(*args)
    return quadro, info


def gerar_video(avatar_img, cenario_modo, cenario_upload, cenario_prompt, cenario_salvo,
                bokeh, escala, forca_cor, modelo_recorte,
                texto, voz_nome, velocidade, tom, volume, movimento, restaurar, estatico,
                progress=gr.Progress()):
    progress(0.1, "Compondo cenario...")
    quadro, info = _imagem_composta(avatar_img, cenario_modo, cenario_upload, cenario_prompt,
                                    cenario_salvo, bokeh, escala, forca_cor, modelo_recorte)
    caminho_img = TEMP / f"composta_{uuid.uuid4().hex[:8]}.png"
    quadro.save(caminho_img)
    progress(0.4, "Sintetizando voz...")
    audio = voz.gerar_audio(texto, voz_nome, velocidade, tom, volume)
    progress(0.6, "Animando rosto (pode levar minutos)...")
    try:
        video, metodo = animador.animar(caminho_img, audio, movimento, restaurar,
                                        permitir_estatico=estatico)
    except animador.AnimadorErro as e:
        raise gr.Error(str(e), duration=None) from e
    return quadro, str(audio), str(video), f"{info}\nAnimacao: {metodo}"


def animar_api(imagem: str, audio: str, movimento: bool = True, restaurar: bool = True) -> str:
    """Endpoint usado pela instancia local para delegar a animacao ao Colab."""
    try:
        video, _ = animador.animar(imagem, audio, movimento, restaurar)
    except animador.AnimadorErro as e:
        raise gr.Error(str(e)) from e
    return str(video)


def construir() -> gr.Blocks:
    with gr.Blocks(title="Avatar Falante PT-BR") as demo:
        gr.Markdown("# Avatar Falante PT-BR\n" + resumo_ambiente())
        with gr.Row():
            with gr.Column():
                gr.Markdown("### Configuracao visual")
                avatar = gr.Image(label="Foto base do avatar (rosto frontal)", type="pil")
                modo = gr.Radio(["Enviar fundo", "Descrever cenario por texto"],
                                value="Enviar fundo", label="Cenario")
                upload = gr.Image(label="Enviar fundo", type="pil")
                salvo = gr.Dropdown(gerador_cenario.listar_cenarios(), label="ou escolher da pasta cenarios/")
                prompt = gr.Textbox(label="Descreva o cenario", placeholder="escritorio moderno, luz natural de janela")
                with gr.Accordion("Ajustes finos", open=False):
                    bokeh = gr.Slider(0, 15, value=6, step=0.5, label="Desfoque do fundo (bokeh)")
                    escala = gr.Slider(0.5, 1.0, value=0.85, step=0.05, label="Tamanho do avatar")
                    cor = gr.Slider(0, 1, value=0.35, step=0.05, label="Harmonizacao de cor")
                    modelo = gr.Dropdown(list(compositor.MODELOS), value="u2net_human_seg", label="Modelo de recorte")
                btn_prev = gr.Button("Pre-visualizar composicao")
                previa = gr.Image(label="Composicao", interactive=False)
            with gr.Column():
                gr.Markdown("### Audio e video")
                texto = gr.Textbox(label="Fala em portugues", lines=6,
                                   placeholder="Ola, tudo bem? [pausa] Hoje vou apresentar...")
                gr.Markdown("Pausas: `[pausa]` (0,7s), `[pausa:1.5s]` ou `...`")
                voz_sel = gr.Dropdown(list(voz.VOZES), value="Francisca (feminina)", label="Voz")
                vel = gr.Slider(-50, 50, value=0, step=5, label="Velocidade (%)")
                tom = gr.Slider(-30, 30, value=0, step=2, label="Tom (Hz)")
                vol = gr.Slider(-50, 50, value=0, step=5, label="Volume (%)")
                with gr.Row():
                    mov = gr.Checkbox(value=True, label="Movimentos de cabeca")
                    rest = gr.Checkbox(value=True, label="Restauracao facial (GFPGAN)")
                estatico = gr.Checkbox(value=False, label="Permitir video estatico (sem animacao do rosto) se o SadTalker nao estiver disponivel")
                btn_gerar = gr.Button("Gerar Video Falante", variant="primary")
                info = gr.Textbox(label="Status", interactive=False, lines=2)
                audio_out = gr.Audio(label="Audio gerado", type="filepath")
                video_out = gr.Video(label="Video (.mp4)")

        visual = [avatar, modo, upload, prompt, salvo, bokeh, escala, cor, modelo]
        btn_prev.click(previsualizar, visual, [previa, info])
        btn_gerar.click(gerar_video, visual + [texto, voz_sel, vel, tom, vol, mov, rest, estatico],
                        [previa, audio_out, video_out, info])
        gr.api(animar_api, api_name="animar_api")
    return demo


if __name__ == "__main__":
    compartilhar = os.environ.get("GRADIO_SHARE") == "1"
    construir().queue().launch(server_name="127.0.0.1", share=compartilhar)
