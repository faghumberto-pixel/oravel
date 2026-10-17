#!/usr/bin/env bash
# Gera o pacote .zip da extensão em storage/app/extensao/ (usado pelo download em Integração / API).
# Uso: extensao-whatsapp/build.sh   (roda os testes antes e aborta se algum falhar)
set -euo pipefail
cd "$(dirname "$0")"
node --test test/ >/dev/null
python3 - <<'PY'
import json, os, zipfile
versao = json.load(open('manifest.json'))['version']
destino = os.path.join('..', 'storage', 'app', 'extensao')
os.makedirs(destino, exist_ok=True)
for antigo in os.listdir(destino):
    if antigo.startswith('oravel-crm-whatsapp-') and antigo.endswith('.zip'):
        os.remove(os.path.join(destino, antigo))
caminho = os.path.join(destino, f'oravel-crm-whatsapp-{versao}.zip')
incluir = ['manifest.json', 'background.js', 'content.js', 'panel.js', 'selectors.js', 'options.html', 'options.js', 'README.md']
with zipfile.ZipFile(caminho, 'w', zipfile.ZIP_DEFLATED) as z:
    for f in incluir + [os.path.join('lib', n) for n in sorted(os.listdir('lib'))]:
        z.write(f, f)
print(caminho)
PY
