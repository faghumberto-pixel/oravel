<style>
    /* Login do Portal do Cliente: mesma aparência de vidro do login do app
       (cartão translúcido, campos transparentes em formato de pílula, texto
       branco) sobre a foto do pátio. CSS puro via renderHook, só afeta a
       página "simples" (login e recuperação), nunca o painel autenticado. */
    .fi-simple-layout {
        background-image: linear-gradient(135deg, rgba(0, 0, 0, 0.62) 0%, rgba(0, 0, 0, 0.38) 100%), url('{{ asset('images/login-bg-cliente.jpg') }}?v=2');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
    }

    .fi-simple-layout .fi-simple-main {
        background-color: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 1.5rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.45);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        --tw-ring-color: transparent;
    }

    /* Texto sobre o vidro */
    .fi-simple-layout .fi-simple-header-heading,
    .fi-simple-layout .fi-simple-main .fi-fo-field-wrp-label,
    .fi-simple-layout .fi-simple-main .fi-fo-field-wrp-label span,
    .fi-simple-layout .fi-simple-main label,
    .fi-simple-layout .fi-simple-main label span {
        color: #fff !important;
    }

    .fi-simple-layout .fi-simple-main .fi-fo-field-wrp-error-message {
        color: #fca5a5 !important;
    }

    /* Campos transparentes, em pílula */
    .fi-simple-layout .fi-simple-main .fi-input-wrp {
        border-radius: 9999px;
        background-color: rgba(255, 255, 255, 0.12) !important;
        border-color: rgba(255, 255, 255, 0.25);
        --tw-ring-color: rgba(255, 255, 255, 0.25);
        box-shadow: none;
    }

    .fi-simple-layout .fi-simple-main .fi-input-wrp:focus-within {
        --tw-ring-color: rgba(37, 99, 235, 0.7);
    }

    .fi-simple-layout .fi-simple-main .fi-input-wrp input {
        background: transparent !important;
        color: #fff !important;
    }

    .fi-simple-layout .fi-simple-main .fi-input-wrp input::placeholder {
        color: rgba(255, 255, 255, 0.6);
    }

    .fi-simple-layout .fi-simple-main .fi-input-wrp svg,
    .fi-simple-layout .fi-simple-main .fi-input-wrp button {
        color: rgba(255, 255, 255, 0.75) !important;
    }

    /* O navegador pinta o preenchimento automático de branco: mantém o campo transparente */
    .fi-simple-layout .fi-simple-main input:-webkit-autofill {
        -webkit-text-fill-color: #fff;
        transition: background-color 600000s 0s;
    }

    .fi-simple-layout .fi-simple-main input[type="checkbox"] {
        border-color: rgba(255, 255, 255, 0.45);
        background-color: rgba(255, 255, 255, 0.12);
    }

    /* Links (esqueci a senha, entrar sem senha) */
    .fi-simple-layout .fi-simple-main a:not(.fi-btn) {
        color: rgba(255, 255, 255, 0.9) !important;
    }
</style>
