const fs = require('fs');
const { open, go } = require('./lib');
const OUT = process.env.OUT || __dirname + '/out';

// destaques numerados: {n, sel} ou {n, from, to} (união de dois elementos); pad = folga em px
async function highlight(page, items) {
  for (const it of items) {
    const a = page.locator(it.sel || it.from).first();
    if (!(await a.count())) { console.log('  ! não achei:', it.sel || it.from); continue; }
    await a.scrollIntoViewIfNeeded().catch(() => {});
  }
  const rects = [];
  for (const it of items) {
    const a = page.locator(it.sel || it.from).first();
    if (!(await a.count())) continue;
    let r = await a.boundingBox();
    if (it.to) { const b = await page.locator(it.to).first().boundingBox(); if (b && r) { const x = Math.min(r.x, b.x), y = Math.min(r.y, b.y); r = { x, y, width: Math.max(r.x + r.width, b.x + b.width) - x, height: Math.max(r.y + r.height, b.y + b.height) - y }; } }
    if (r) rects.push({ ...r, n: it.n, pad: it.pad ?? 4, inside: !!it.inside });
  }
  await page.evaluate((rects) => {
    for (const r of rects) {
      const sx = window.scrollX, sy = window.scrollY;
      const box = document.createElement('div');
      box.style.cssText = `position:absolute;z-index:99999;pointer-events:none;border:3px solid #ef4444;border-radius:10px;box-shadow:0 0 0 3px rgba(239,68,68,.25);left:${r.x + sx - r.pad}px;top:${r.y + sy - r.pad}px;width:${r.width + r.pad * 2}px;height:${r.height + r.pad * 2}px`;
      const b = document.createElement('div');
      b.textContent = r.n;
      const pos = r.inside ? `left:${r.width + r.pad * 2 - 44}px;top:${(r.height + r.pad * 2 - 28) / 2 - 3}px` : 'left:-14px;top:-16px';
      b.style.cssText = `position:absolute;${pos};width:28px;height:28px;border-radius:50%;background:#ef4444;color:#fff;font:700 15px/28px Inter,Arial,sans-serif;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,.4)`;
      box.appendChild(b); document.body.appendChild(box);
    }
  }, rects);
}

async function clean(page, side) {
  // a palavra "tenant" não aparece nas imagens da Academia: vira "empresa"
  await page.evaluate(() => {
    const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    for (let n = w.nextNode(); n; n = w.nextNode()) {
      if (/tenant/i.test(n.nodeValue)) n.nodeValue = n.nodeValue.replace(/no tenant/gi, 'na empresa').replace(/do tenant/gi, 'da empresa').replace(/tenants?/gi, 'empresa');
    }
  });
  if (!side) await page.addStyleTag({ content: '.fi-sidebar{display:none!important}.fi-main-ctn{padding-inline-start:0!important}.fi-sidebar-close-overlay{display:none!important}' }).catch(() => {});
  await page.addStyleTag({ content: '[class*="fi-"] .fi-notification{display:none!important}' }).catch(() => {});
  await page.evaluate(() => {
    for (const el of document.querySelectorAll('div,section,aside')) {
      const t = (el.innerText || '').trim();
      if (/^(Informativo|Aviso)/.test(t) && t.includes('Dispensar') && el.getBoundingClientRect().height < 80) el.style.display = 'none';
      const cs = getComputedStyle(el);
      if (cs.position === 'fixed' && /Mensagens/.test(t) && el.getBoundingClientRect().height < 120) el.style.display = 'none';
    }
  });
}

exports.run = async (shots, { w = 1366, h = 820 } = {}) => {
  const { browser, page } = await open(w, h);
  for (const s of shots) {
    try {
      await page.setViewportSize({ width: s.w || w, height: s.h || h });
      await go(page, s.path, s.wait ?? 2500);
      if (s.before) await s.before(page);
      await clean(page, s.side);
      if (s.hl) await highlight(page, s.hl);
      const file = `${OUT}/${s.id}.jpg`;
      await page.screenshot({ path: file, type: 'jpeg', quality: 78, fullPage: !!s.full, clip: s.clip });
      console.log('ok', s.id, Math.round(fs.statSync(file).size / 1024) + 'KB');
    } catch (e) { console.log('ERRO', s.id, e.message.split('\n')[0]); }
  }
  await browser.close();
};
