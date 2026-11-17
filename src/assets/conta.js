import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js";
import { initializeAppCheck, ReCaptchaEnterpriseProvider } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-app-check.js";
import { getAuth, onAuthStateChanged, signInWithEmailAndPassword, createUserWithEmailAndPassword, sendPasswordResetEmail, signOut }
  from "https://www.gstatic.com/firebasejs/10.12.0/firebase-auth.js";
import { getFirestore, doc, setDoc, addDoc, collection, query, where, orderBy, getDocs, deleteDoc, serverTimestamp }
  from "https://www.gstatic.com/firebasejs/10.12.0/firebase-firestore.js";

const app = initializeApp({
  apiKey: "AIzaSyAZzq-9mWORwpVRI0_INVQqtY2h8A7CRGU",
  authDomain: "oravel-org.firebaseapp.com",
  projectId: "oravel-org",
  appId: "1:588824747002:web:bbffcc368e227b605be243"
});
initializeAppCheck(app, { provider: new ReCaptchaEnterpriseProvider("6LerhRsuAAAAAANMO5ZjmR9IrpEWu3g5GPmfYyOs"), isTokenAutoRefreshEnabled: true });
const auth = getAuth(app);
const db = getFirestore(app);
const $ = (s) => document.querySelector(s);
const msg = (el, t, ok) => { if (!el) return; el.textContent = t; el.style.color = ok ? "#0b5f57" : "#9a3412"; };
const erro = (e) => ({
  "auth/email-already-in-use": "Este e-mail já está cadastrado. Use Entrar.",
  "auth/invalid-credential": "E-mail ou senha incorretos.",
  "auth/invalid-email": "E-mail inválido.",
  "auth/too-many-requests": "Muitas tentativas. Aguarde um pouco."
}[e.code] || "Não foi possível concluir. Tente novamente.");
const senhaAleatoria = () => { const a = new Uint8Array(24); crypto.getRandomValues(a); return btoa(String.fromCharCode(...a)); };

let perfilNome = "";
const icone = $(".entrar");
onAuthStateChanged(auth, (u) => {
  if (icone) icone.title = u ? "Minha conta (" + (u.email || "") + ")" : "Entrar";
  const logado = $("#form-comentario"), deslog = $("#comentar-deslogado");
  if (logado) logado.hidden = !u;
  if (deslog) deslog.hidden = !!u;
  const box = $("#conta-estado");
  if (box) { box.hidden = !u; const em = $("#conta-email"); if (em && u) em.textContent = u.email; }
  const form = $("#form-entrar"); if (form) form.hidden = !!u;
});

// Cadastro
const fc = $("#form-cadastro");
if (fc) fc.addEventListener("submit", async (ev) => {
  ev.preventDefault();
  const d = Object.fromEntries(new FormData(fc));
  if (d.site) return; // honeypot
  const out = $("#cadastro-msg"), btn = fc.querySelector("button"); btn.disabled = true;
  try {
    const cred = await createUserWithEmailAndPassword(auth, d.email.trim(), senhaAleatoria());
    await setDoc(doc(db, "usuarios", cred.user.uid), {
      nome: d.nome.trim(), sobrenome: d.sobrenome.trim(), empresa: d.empresa.trim(), whatsapp: d.whatsapp.trim(),
      cidade: d.cidade.trim(), estado: d.estado, email: d.email.trim(), criado: serverTimestamp()
    });
    await sendPasswordResetEmail(auth, d.email.trim(), { url: "https://oravel.org/?cadastro=ok" });
    await signOut(auth);
    fc.reset();
    msg(out, "Cadastro feito! Enviamos um e-mail para você definir sua senha. Seu usuário é o e-mail cadastrado. Confira também o spam.", true);
  } catch (e) { msg(out, erro(e)); btn.disabled = false; }
});

// Entrar
if (new URLSearchParams(location.search).get("senha") === "definida") msg($("#entrar-msg"), "Senha definida! Entre com seu e-mail e a nova senha.", true);
const fe = $("#form-entrar");
if (fe) fe.addEventListener("submit", async (ev) => {
  ev.preventDefault();
  const d = Object.fromEntries(new FormData(fe)); const out = $("#entrar-msg");
  try { await signInWithEmailAndPassword(auth, d.email.trim(), d.senha); msg(out, "", true); }
  catch (e) { msg(out, erro(e)); }
});
const esq = $("#esqueci");
if (esq) esq.addEventListener("click", async (ev) => {
  ev.preventDefault(); const em = fe.email.value.trim(), out = $("#entrar-msg");
  if (!em) return msg(out, "Digite seu e-mail e clique de novo.");
  try { await sendPasswordResetEmail(auth, em, { url: "https://oravel.org/entrar/?senha=definida" }); msg(out, "Enviamos um e-mail para redefinir a senha.", true); } catch (e) { msg(out, erro(e)); }
});
const sair = $("#sair"); if (sair) sair.addEventListener("click", () => signOut(auth));

// Comentários
const lista = $("#comentarios-lista");
if (lista) {
  const slug = lista.dataset.slug;
  const carregar = async () => {
    lista.textContent = "";
    try {
      const snap = await getDocs(query(collection(db, "comentarios"), where("slug", "==", slug), orderBy("criado", "desc")));
      if (snap.empty) { lista.innerHTML = '<p class="nota">Seja o primeiro a comentar.</p>'; return; }
      snap.forEach((s) => {
        const c = s.data(), el = document.createElement("div"); el.className = "comentario";
        const h = document.createElement("strong"); h.textContent = c.autor;
        const t = document.createElement("time"); t.textContent = c.criado ? " · " + c.criado.toDate().toLocaleString("pt-BR") : "";
        const p = document.createElement("p"); p.textContent = c.texto;
        el.append(h, t, p);
        if (auth.currentUser && auth.currentUser.uid === c.uid) {
          const b = document.createElement("button"); b.type = "button"; b.textContent = "Excluir"; b.className = "excluir";
          b.onclick = async () => { await deleteDoc(doc(db, "comentarios", s.id)); carregar(); }; el.append(b);
        }
        lista.append(el);
      });
    } catch (e) { lista.innerHTML = '<p class="nota">Não foi possível carregar os comentários.</p>'; }
  };
  onAuthStateChanged(auth, carregar);
  const fk = $("#form-comentario");
  fk.addEventListener("submit", async (ev) => {
    ev.preventDefault(); const texto = fk.texto.value.trim(); if (!texto) return;
    const u = auth.currentUser; if (!u) return;
    let autor = "Leitor";
    try { const { getDoc } = await import("https://www.gstatic.com/firebasejs/10.12.0/firebase-firestore.js");
      const p = (await getDoc(doc(db, "usuarios", u.uid))).data(); if (p) autor = p.nome + " " + p.sobrenome.charAt(0) + "." + (p.empresa ? " · " + p.empresa : ""); } catch (e) {}
    try { await addDoc(collection(db, "comentarios"), { slug, uid: u.uid, autor, texto, criado: serverTimestamp() }); fk.reset(); carregar(); }
    catch (e) { msg($("#comentario-msg"), "Não foi possível publicar o comentário."); }
  });
}
