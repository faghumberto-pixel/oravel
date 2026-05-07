<div class="mb-2">
    <button type="button" onclick="iniciarGravacao()" class="flex items-center gap-2 text-sm text-primary-600 hover:text-primary-700 cursor-pointer">
        <x-heroicon-o-microphone class="w-5 h-5"/>
        <span>Gravar Apontamento por Voz</span>
    </button>
</div>

<script>
function iniciarGravacao() {
    const recognition = new (window.SpeechRecognition || window.webkitSpeechRecognition)();
    recognition.lang = 'pt-BR';
    
    recognition.onstart = () => {
        alert('Ouvindo... Pode falar.');
    };

    recognition.onresult = (event) => {
        const transcript = event.results[0][0].transcript;
        const editor = document.querySelector('.tiptap-editor .ProseMirror');
        if(editor) {
            editor.innerHTML += `<p>${transcript}</p>`;
        }
    };

    recognition.onerror = (event) => {
        console.error("Erro de reconhecimento de voz: ", event.error);
        alert('Erro ao processar voz: ' + event.error);
    };

    recognition.start();
}
</script>
