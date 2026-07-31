import './bootstrap';
import './offline/init'; // Offline-first initialization for field technician

import Alpine from 'alpinejs';
import SignaturePad from 'signature_pad';

window.Alpine = Alpine;
window.SignaturePad = SignaturePad;

Alpine.start();

/**
 * Compressao de foto antes do upload do Livewire -- pedido do usuario
 * 2026-07-29 ("na vistoria nao esta aceitando foto"). Causa real: os
 * componentes moveis de checklist (MaintenanceChecklistMobile,
 * RentalDispatchChecklistMobile, EquipmentPatioArrivalMobile -- todos com
 * <input wire:model="newPhoto">) validam ate 5MB (`max:5120`), mas o
 * upload_max_filesize do PHP so aceita 2MB -- uma foto de celular real
 * quase sempre passa disso e e rejeitada ANTES da validacao do Laravel
 * rodar. Em vez de depender de mudar configuracao de servidor (nao da' pra
 * fazer isso daqui, e teria que ser replicado em todo lugar que hospeda o
 * app), a foto e' redimensionada/recomprimida no proprio navegador antes
 * do Livewire processar o upload -- resolve o tamanho na raiz, funciona
 * em qualquer ambiente.
 *
 * Listener global no document (capture phase) em vez de directive Alpine
 * por input: os 3 componentes moveis nao tem x-data ancestral garantido, e
 * isso cobre os 3 (e qualquer outro que use a mesma convencao
 * wire:model="newPhoto") sem precisar editar nenhuma view.
 */
window.oravelCompressImageFile = async function (file) {
    const dataUrl = await new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (e) => resolve(e.target.result);
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });

    const img = await new Promise((resolve, reject) => {
        const el = new Image();
        el.onload = () => resolve(el);
        el.onerror = reject;
        el.src = dataUrl;
    });

    const maxSide = 1600;
    const scale = Math.min(1, maxSide / Math.max(img.width, img.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(img.width * scale);
    canvas.height = Math.round(img.height * scale);
    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.72));

    if (!blob) {
        return file;
    }

    return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
};

document.addEventListener('change', async function (event) {
    const input = event.target;

    if (!(input instanceof HTMLInputElement) || input.type !== 'file') {
        return;
    }

    if (input.getAttribute('wire:model') !== 'newPhoto') {
        return;
    }

    if (input.dataset.oravelCompressing === '1') {
        // Evento sintetico que a gente mesmo disparou depois de comprimir --
        // deixa passar direto pro listener do Livewire, sem reprocessar.
        delete input.dataset.oravelCompressing;
        return;
    }

    const file = input.files && input.files[0];
    if (!file) {
        return;
    }

    // Impede o Livewire de comecar o upload do arquivo original (grande) --
    // so' deve ver a versao comprimida, disparada abaixo.
    event.stopImmediatePropagation();
    event.preventDefault();

    let compressedFile = file;
    try {
        compressedFile = await window.oravelCompressImageFile(file);
    } catch (e) {
        // Falha na compressao: segue com o arquivo original em vez de
        // travar a captura da foto.
    }

    const dataTransfer = new DataTransfer();
    dataTransfer.items.add(compressedFile);
    input.files = dataTransfer.files;
    input.dataset.oravelCompressing = '1';
    input.dispatchEvent(new Event('change', { bubbles: true }));
}, true);
