import { WorkerMailer } from "worker-mailer";

const JWKS_URL = "https://www.googleapis.com/service_accounts/v1/jwk/securetoken@system.gserviceaccount.com";
const b64u = (s) => Uint8Array.from(atob(s.replace(/-/g, "+").replace(/_/g, "/")), (c) => c.charCodeAt(0));
const json = (o, status, cors) => new Response(JSON.stringify(o), { status, headers: { "Content-Type": "application/json", ...cors } });
const limpa = (v, max) => String(v ?? "").replace(/[\r\n]+/g, " ").trim().slice(0, max);

// Confere o ID token do Firebase (RS256) para que só quem acabou de criar conta consiga disparar o aviso
async function verificaToken(token, projeto) {
  const [h, p, s] = token.split(".");
  if (!h || !p || !s) throw new Error("token");
  const header = JSON.parse(new TextDecoder().decode(b64u(h)));
  const claims = JSON.parse(new TextDecoder().decode(b64u(p)));
  const agora = Math.floor(Date.now() / 1000);
  if (header.alg !== "RS256" || claims.aud !== projeto || claims.iss !== "https://securetoken.google.com/" + projeto
      || claims.exp < agora || claims.iat > agora + 60 || !claims.sub) throw new Error("claims");
  const jwks = await (await fetch(JWKS_URL, { cf: { cacheTtl: 3600, cacheEverything: true } })).json();
  const jwk = jwks.keys.find((k) => k.kid === header.kid);
  if (!jwk) throw new Error("kid");
  const key = await crypto.subtle.importKey("jwk", jwk, { name: "RSASSA-PKCS1-v1_5", hash: "SHA-256" }, false, ["verify"]);
  const ok = await crypto.subtle.verify("RSASSA-PKCS1-v1_5", key, b64u(s), new TextEncoder().encode(h + "." + p));
  if (!ok) throw new Error("assinatura");
  return claims;
}

export default {
  async fetch(req, env) {
    const origin = req.headers.get("Origin") || "";
    const permitidas = env.ALLOWED_ORIGINS.split(",");
    const cors = permitidas.includes(origin)
      ? { "Access-Control-Allow-Origin": origin, "Vary": "Origin", "Access-Control-Allow-Headers": "Authorization, Content-Type", "Access-Control-Allow-Methods": "POST, OPTIONS" }
      : {};
    if (req.method === "OPTIONS") return new Response(null, { status: 204, headers: cors });
    if (req.method !== "POST" || !cors["Access-Control-Allow-Origin"]) return json({ erro: "não permitido" }, 403, cors);

    let claims;
    try {
      const auth = req.headers.get("Authorization") || "";
      claims = await verificaToken(auth.replace(/^Bearer\s+/i, ""), env.FIREBASE_PROJECT);
    } catch { return json({ erro: "não autorizado" }, 401, cors); }

    let d;
    try { d = await req.json(); } catch { return json({ erro: "json inválido" }, 400, cors); }
    const nome = limpa(d.nome, 80), sobrenome = limpa(d.sobrenome, 80);
    if (!nome || !sobrenome) return json({ erro: "dados incompletos" }, 400, cors);

    const texto = [
      "Novo cadastro no oravel.org", "",
      "Nome: " + nome + " " + sobrenome,
      "E-mail: " + limpa(claims.email || d.email, 120),
      "Empresa: " + limpa(d.empresa, 120),
      "WhatsApp: " + limpa(d.whatsapp, 20),
      "Cidade/UF: " + limpa(d.cidade, 80) + "/" + limpa(d.estado, 2),
      "Origem: " + (limpa(d.origem, 60) || "direto"),
      "", "UID: " + claims.sub
    ].join("\n");

    try {
      await WorkerMailer.send(
        { host: env.SMTP_HOST, port: Number(env.SMTP_PORT), secure: true, credentials: { username: env.SMTP_USER, password: env.SMTP_PASS }, authType: "plain" },
        { from: { name: "oravel.org", email: env.SMTP_USER }, to: env.MAIL_TO, reply: limpa(claims.email || d.email, 120) || undefined,
          subject: "Novo cadastro no oravel.org: " + nome + " " + sobrenome, text: texto }
      );
    } catch (e) { return json({ erro: "falha no envio" }, 502, cors); }
    return json({ ok: true }, 200, cors);
  }
};
