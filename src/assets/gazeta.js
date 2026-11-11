import { initializeApp } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js";
import { initializeAppCheck, ReCaptchaEnterpriseProvider } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-app-check.js";
import { getFirestore, doc, setDoc, serverTimestamp } from "https://www.gstatic.com/firebasejs/10.12.0/firebase-firestore.js";

const app = initializeApp({ apiKey: "AIzaSyAZzq-9mWORwpVRI0_INVQqtY2h8A7CRGU", authDomain: "oravel-org.firebaseapp.com", projectId: "oravel-org", appId: "1:588824747002:web:bbffcc368e227b605be243" });
initializeAppCheck(app, { provider: new ReCaptchaEnterpriseProvider("6LerhRsuAAAAAANMO5ZjmR9IrpEWu3g5GPmfYyOs"), isTokenAutoRefreshEnabled: true });
const db = getFirestore(app);

const form = document.getElementById("form-gazeta"), msg = document.getElementById("gazeta-msg");
const say = (t, ok) => { msg.textContent = t; msg.style.color = ok ? "#9ad7cd" : "#fbbf24"; };
form.addEventListener("submit", async (ev) => {
  ev.preventDefault();
  const d = Object.fromEntries(new FormData(form));
  if (d.site) return; // honeypot
  const email = String(d.email || "").trim().toLowerCase();
  if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email) || email.length > 254) return say("Digite um e-mail válido.");
  const btn = form.querySelector("button"); btn.disabled = true;
  try {
    await setDoc(doc(db, "gazeta_assinantes", email), { email, criado: serverTimestamp() });
    form.reset(); say("Inscrição feita! Você vai receber a Gazeta Oravel neste e-mail.", true);
  } catch (e) {
    say(e && e.code === "permission-denied" ? "Este e-mail já está inscrito (ou não foi aceito)." : "Não foi possível inscrever agora. Tente novamente.");
  }
  btn.disabled = false;
});
