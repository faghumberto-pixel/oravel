@props(['modelName', 'label' => 'Nota de voz'])

<div x-data="voiceRecorder()" class="space-y-2">
    <label class="text-xs font-bold uppercase tracking-wide text-zinc-400">{{ $label }}</label>

    {{-- Controles --}}
    <div class="flex gap-2">
        <button
            type="button"
            @click="toggleRecording()"
            :class="recording ? 'bg-red-500 hover:bg-red-600' : 'bg-emerald-500 hover:bg-emerald-600'"
            class="flex-1 rounded-xl px-4 py-2 text-sm font-bold text-white transition-colors"
        >
            <span x-show="!recording">🎤 Começar</span>
            <span x-show="recording">■ Parando...</span>
        </button>

        {{-- Mostrar duração durante gravação --}}
        <div x-show="recording" class="flex items-center gap-2 rounded-xl border border-zinc-700 px-3 py-2">
            <span class="text-[11px] font-bold text-zinc-400" x-text="formatTime(duration)"></span>
        </div>
    </div>

    {{-- Onda sonora (só mostra durante gravação) --}}
    <div x-show="recording" class="flex items-center justify-center gap-1 rounded-xl border border-zinc-800 bg-zinc-900/50 py-4">
        <template x-for="i in 30" :key="i">
            <div
                class="bg-emerald-500 rounded-full transition-all duration-100"
                :style="`height: ${(analyzerValues[i % analyzerValues.length] || 0) * 0.8 + 4}px; width: 3px;`"
            ></div>
        </template>
    </div>

    {{-- Transcrição (quando houver) --}}
    <div x-show="transcript" class="space-y-2">
        <div class="rounded-xl border border-zinc-700 bg-zinc-900 p-3">
            <p class="text-sm text-zinc-100" x-text="transcript"></p>
        </div>

        <div class="flex gap-2">
            <button
                type="button"
                @click="clearTranscript()"
                class="flex-1 rounded-xl border border-zinc-700 px-3 py-2 text-xs font-bold text-zinc-300 hover:bg-zinc-800"
            >
                Descartar
            </button>
            <button
                type="button"
                @click="useTranscript()"
                class="flex-1 rounded-xl bg-emerald-500 px-3 py-2 text-xs font-bold text-zinc-950 hover:bg-emerald-600"
            >
                Usar este texto
            </button>
        </div>
    </div>

    {{-- Aviso para navegadores sem suporte --}}
    <div x-show="!browserSupported" class="rounded-xl border border-red-800 bg-red-900/20 p-3 text-xs text-red-400">
        Gravação de voz não é suportada neste navegador. Você pode digitar manualmente.
    </div>

    <script>
        function voiceRecorder() {
            return {
                recording: false,
                transcript: '',
                duration: 0,
                browserSupported: true,
                analyzerValues: new Array(30).fill(0),
                mediaRecorder: null,
                audioContext: null,
                analyzer: null,
                recognition: null,
                chunks: [],
                durationInterval: null,

                init() {
                    this.checkBrowserSupport();
                    if (this.browserSupported) {
                        this.setupAudioContext();
                        this.setupSpeechRecognition();
                    }
                },

                checkBrowserSupport() {
                    const hasWebAudio = !!(
                        window.AudioContext ||
                        window.webkitAudioContext ||
                        window.mozAudioContext
                    );
                    const hasSpeechRecognition = !!(
                        window.webkitSpeechRecognition ||
                        window.SpeechRecognition
                    );
                    this.browserSupported = hasWebAudio && hasSpeechRecognition;
                },

                setupAudioContext() {
                    try {
                        const AudioContext = window.AudioContext || window.webkitAudioContext;
                        this.audioContext = new AudioContext();
                        this.analyzer = this.audioContext.createAnalyser();
                        this.analyzer.fftSize = 256;
                    } catch (e) {
                        console.error('Audio Context error:', e);
                        this.browserSupported = false;
                    }
                },

                setupSpeechRecognition() {
                    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                    if (!SpeechRecognition) return;

                    this.recognition = new SpeechRecognition();
                    this.recognition.lang = 'pt-BR';
                    this.recognition.continuous = true;
                    this.recognition.interimResults = true;

                    this.recognition.onresult = (event) => {
                        let interim = '';
                        for (let i = event.resultIndex; i < event.results.length; i++) {
                            const transcript = event.results[i][0].transcript;
                            if (event.results[i].isFinal) {
                                this.transcript += transcript + ' ';
                            } else {
                                interim += transcript;
                            }
                        }
                        if (interim) {
                            this.transcript = this.transcript.trim() + (this.transcript ? ' ' : '') + interim;
                        }
                    };

                    this.recognition.onerror = (event) => {
                        console.error('Speech recognition error:', event.error);
                    };

                    this.recognition.onend = () => {
                        this.recording = false;
                        this.stopDurationTimer();
                    };
                },

                async toggleRecording() {
                    if (!this.recording) {
                        await this.startRecording();
                    } else {
                        this.stopRecording();
                    }
                },

                async startRecording() {
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        this.recording = true;
                        this.transcript = '';
                        this.duration = 0;
                        this.chunks = [];
                        this.startDurationTimer();

                        // Setup MediaRecorder para visualização de onda
                        this.mediaRecorder = new MediaRecorder(stream);
                        this.mediaRecorder.ondataavailable = (e) => {
                            this.chunks.push(e.data);
                        };

                        // Connect stream ao analyzer para visualização
                        const source = this.audioContext.createMediaStreamSource(stream);
                        source.connect(this.analyzer);

                        // Começar análise da onda
                        this.animateWaveform();

                        // Start recognition e recording
                        if (this.recognition) {
                            this.recognition.start();
                        }
                        this.mediaRecorder.start();
                    } catch (e) {
                        console.error('Recording error:', e);
                        alert('Não foi possível acessar o microfone. Verifique as permissões.');
                        this.recording = false;
                    }
                },

                stopRecording() {
                    this.recording = false;
                    if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                        this.mediaRecorder.stop();
                    }
                    if (this.recognition) {
                        this.recognition.stop();
                    }
                    this.stopDurationTimer();
                },

                animateWaveform() {
                    if (!this.recording || !this.analyzer) return;

                    const dataArray = new Uint8Array(this.analyzer.frequencyBinCount);
                    this.analyzer.getByteFrequencyData(dataArray);

                    // Pega 30 valores representativos
                    const step = Math.floor(dataArray.length / 30);
                    for (let i = 0; i < 30; i++) {
                        this.analyzerValues[i] = dataArray[i * step] / 255;
                    }

                    requestAnimationFrame(() => this.animateWaveform());
                },

                startDurationTimer() {
                    this.durationInterval = setInterval(() => {
                        this.duration++;
                    }, 1000);
                },

                stopDurationTimer() {
                    if (this.durationInterval) {
                        clearInterval(this.durationInterval);
                    }
                },

                formatTime(seconds) {
                    const mins = Math.floor(seconds / 60);
                    const secs = seconds % 60;
                    return `${mins}:${secs.toString().padStart(2, '0')}`;
                },

                clearTranscript() {
                    this.transcript = '';
                },

                useTranscript() {
                    // Emite um evento Alpine que o Livewire pode escutar
                    // ou diretamente chama dispatch para o Livewire
                    this.$dispatch('voice-text', { text: this.transcript.trim(), model: '{{ $modelName }}' });
                    this.clearTranscript();
                },
            };
        }
    </script>
</div>
