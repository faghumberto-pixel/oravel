// Gazeta Oravel: envia a edição ao se inscrever e trata o cancelamento.
// Segredos (nunca no código): RESEND_API_KEY e UNSUB_SECRET -> `firebase functions:secrets:set NOME`
const { onDocumentCreated } = require("firebase-functions/v2/firestore");
const { onRequest } = require("firebase-functions/v2/https");
const { defineSecret, defineString } = require("firebase-functions/params");
const admin = require("firebase-admin");
const crypto = require("crypto");
const fs = require("fs");
const path = require("path");

admin.initializeApp();
const RESEND_API_KEY = defineSecret("RESEND_API_KEY");
const UNSUB_SECRET = defineSecret("UNSUB_SECRET");
const FROM = defineString("GAZETA_FROM", { default: "Gazeta Oravel <gazeta@oravel.org>" });
const REGION = "southamerica-east1";
const SITE = "https://oravel.org";

const token = (email) => crypto.createHmac("sha256", UNSUB_SECRET.value()).update(email).digest("hex").slice(0, 32);
const unsubUrl = (email, fnUrl) => `${fnUrl}?e=${encodeURIComponent(email)}&t=${token(email)}`;

exports.enviarGazeta = onDocumentCreated(
  { document: "gazeta_assinantes/{email}", region: REGION, secrets: [RESEND_API_KEY, UNSUB_SECRET] },
  async (event) => {
    const email = event.params.email;
    const base = process.env.GAZETA_CANCELAR_URL || `https://${REGION}-${process.env.GCLOUD_PROJECT}.cloudfunctions.net/cancelarGazeta`;
    const link = unsubUrl(email, base);
    const html = fs.readFileSync(path.join(__dirname, "gazeta.html"), "utf8").replace(/\{\{CANCELAR\}\}/g, link);
    const r = await fetch("https://api.resend.com/emails", {
      method: "POST",
      headers: { Authorization: `Bearer ${RESEND_API_KEY.value()}`, "Content-Type": "application/json" },
      body: JSON.stringify({
        from: FROM.value(), to: [email], subject: "Gazeta Oravel · sua primeira edição", html,
        headers: { "List-Unsubscribe": `<${link}>`, "List-Unsubscribe-Post": "List-Unsubscribe=One-Click" },
      }),
    });
    const ok = r.ok; const corpo = await r.text();
    await event.data.ref.set({ envio: ok ? "enviado" : "falhou", envioEm: admin.firestore.FieldValue.serverTimestamp(), envioDetalhe: ok ? "" : corpo.slice(0, 300) }, { merge: true });
    if (!ok) console.error("Resend falhou", r.status, corpo);
  }
);

exports.cancelarGazeta = onRequest({ region: REGION, secrets: [UNSUB_SECRET] }, async (req, res) => {
  const email = String(req.query.e || "").toLowerCase(), t = String(req.query.t || "");
  const esperado = email ? token(email) : "";
  const valido = t.length === esperado.length && crypto.timingSafeEqual(Buffer.from(t), Buffer.from(esperado));
  res.set("Content-Type", "text/html; charset=utf-8");
  if (!valido) return res.status(400).send("<p>Link inválido.</p>");
  await admin.firestore().doc(`gazeta_assinantes/${email}`).delete().catch(() => {});
  res.send(`<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:Inter,Arial,sans-serif;max-width:520px;margin:12vh auto;padding:0 20px;color:#1b2230"><h1 style="font-weight:600">Inscrição cancelada</h1><p>Você não receberá mais a Gazeta Oravel. Se foi sem querer, inscreva-se de novo em <a href="${SITE}" style="color:#0b5f57">oravel.org</a>.</p></body>`);
});
