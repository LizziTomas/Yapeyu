@auth
<style>
    #chat-ia-launcher {
        position: fixed;
        right: 1.25rem;
        bottom: 1.25rem;
        z-index: 1040;
        width: 58px;
        height: 58px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.22);
    }

    #chat-ia-panel {
        --bs-offcanvas-width: 420px;
    }

    #chat-ia-panel .offcanvas-body {
        min-height: 0;
        overflow: hidden;
    }

    #chat-ia-messages {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        scroll-behavior: smooth;
    }

    .chat-ia-message {
        max-width: 88%;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    .chat-ia-typing {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        min-height: 1.1rem;
    }

    .chat-ia-typing span {
        width: 0.45rem;
        height: 0.45rem;
        border-radius: 50%;
        background: var(--bs-secondary-color);
        animation: chat-ia-typing 1.1s infinite ease-in-out;
    }

    .chat-ia-typing span:nth-child(2) {
        animation-delay: 0.15s;
    }

    .chat-ia-typing span:nth-child(3) {
        animation-delay: 0.3s;
    }

    @keyframes chat-ia-typing {
        0%, 60%, 100% {
            transform: translateY(0);
            opacity: 0.4;
        }
        30% {
            transform: translateY(-4px);
            opacity: 1;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .chat-ia-typing span {
            animation: none;
            opacity: 0.7;
        }
    }

    @media (max-width: 576px) {
        #chat-ia-panel {
            --bs-offcanvas-width: 100vw;
        }

        #chat-ia-launcher {
            right: 1rem;
            bottom: 1rem;
        }
    }
</style>

<button
    id="chat-ia-launcher"
    class="btn btn-warning"
    type="button"
    data-bs-toggle="offcanvas"
    data-bs-target="#chat-ia-panel"
    aria-controls="chat-ia-panel"
    aria-label="Abrir asistente de inteligencia artificial"
    title="Asistente IA"
>
    <i class="bi bi-chat-dots-fill fs-3"></i>
</button>

<div
    class="offcanvas offcanvas-end"
    tabindex="-1"
    id="chat-ia-panel"
    aria-labelledby="chat-ia-title"
>
    <div class="offcanvas-header border-bottom">
        <div>
            <h5 class="offcanvas-title" id="chat-ia-title">
                <i class="bi bi-robot me-2"></i>Asistente IA
            </h5>
            <small class="text-body-secondary">
                Ayuda sobre Casa Yacobone
            </small>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button
                type="button"
                id="chat-ia-clear"
                class="btn btn-sm btn-outline-secondary"
                title="Borrar la conversación"
            >
                Nueva conversación
            </button>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="offcanvas"
                aria-label="Cerrar"
            ></button>
        </div>
    </div>

    <div class="offcanvas-body d-flex flex-column p-0">
        <div
            id="chat-ia-messages"
            class="p-3 d-flex flex-column gap-3"
            role="log"
            aria-live="polite"
        ></div>

        <div class="border-top p-3">
            <form id="chat-ia-form">
                <label for="chat-ia-input" class="form-label">
                    Escribí tu consulta
                </label>

                <div class="d-flex align-items-end gap-2">
                    <textarea
                        id="chat-ia-input"
                        class="form-control"
                        rows="2"
                        maxlength="2000"
                        placeholder="Ej.: ¿Cómo abro una caja?"
                        required
                    ></textarea>

                    <button
                        type="submit"
                        id="chat-ia-send"
                        class="btn btn-primary"
                        aria-label="Enviar consulta"
                    >
                        <i class="bi bi-send"></i>
                    </button>
                </div>
            </form>

            <div
                id="chat-ia-status"
                class="small text-body-secondary mt-2"
                aria-live="polite"
            >
                Consultá sobre productos, cajas, ventas y movimientos.
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const container = document.getElementById('chat-ia-messages');
    const form = document.getElementById('chat-ia-form');
    const input = document.getElementById('chat-ia-input');
    const sendButton = document.getElementById('chat-ia-send');
    const clearButton = document.getElementById('chat-ia-clear');
    const status = document.getElementById('chat-ia-status');

    const endpoint = @json(route('chat-ia.mensaje'));
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        .getAttribute('content');

    const storageKey = @json('chat_ia_history_' . auth()->id());

    const greeting =
        '¡Hola! Puedo ayudarte a utilizar Casa Yacobone. ' +
        'Preguntame sobre productos, cajas, ventas o movimientos de stock.';

    function loadHistory() {
        try {
            const saved = JSON.parse(
                localStorage.getItem(storageKey) || '[]'
            );

            if (!Array.isArray(saved)) {
                return [];
            }

            return saved
                .filter(message =>
                    message &&
                    ['user', 'assistant'].includes(message.role) &&
                    typeof message.content === 'string'
                )
                .slice(-10);
        } catch {
            return [];
        }
    }

    let history = loadHistory();

    function saveHistory() {
        try {
            localStorage.setItem(
                storageKey,
                JSON.stringify(history.slice(-10))
            );
        } catch {
            status.textContent =
                'No se pudo guardar el historial en este navegador.';
        }
    }

    function appendMessage(role, text) {
        const row = document.createElement('div');

        row.className = role === 'user'
            ? 'd-flex justify-content-end'
            : 'd-flex justify-content-start';

        const bubble = document.createElement('div');

        bubble.className = role === 'user'
            ? 'chat-ia-message rounded-3 px-3 py-2 bg-primary text-white'
            : 'chat-ia-message rounded-3 px-3 py-2 border bg-body-tertiary';

        // Mostramos el texto sin interpretarlo como código HTML.
        bubble.textContent = text;

        row.appendChild(bubble);
        container.appendChild(row);

        container.scrollTop = container.scrollHeight;
    }

    function mostrarEscribiendo() {
        const row = document.createElement('div');

        row.className = 'd-flex justify-content-start';

        const bubble = document.createElement('div');

        bubble.className =
            'chat-ia-message rounded-3 px-3 py-2 border bg-body-tertiary';

        const dots = document.createElement('span');

        dots.className = 'chat-ia-typing';
        dots.setAttribute('role', 'status');
        dots.setAttribute('aria-label', 'El asistente está escribiendo');

        for (let i = 0; i < 3; i++) {
            dots.appendChild(document.createElement('span'));
        }

        bubble.appendChild(dots);
        row.appendChild(bubble);
        container.appendChild(row);
        container.scrollTop = container.scrollHeight;
        container.setAttribute('aria-busy', 'true');

        return row;
    }

    function ocultarEscribiendo(row) {
        row.remove();
        container.removeAttribute('aria-busy');
    }

    function renderHistory() {
        container.replaceChildren();

        if (history.length === 0) {
            appendMessage('assistant', greeting);
            return;
        }

        history.forEach(message => {
            appendMessage(message.role, message.content);
        });
    }

    renderHistory();

    clearButton.addEventListener('click', () => {
        history = [];

        try {
            localStorage.removeItem(storageKey);
        } catch {
            // El historial en memoria igualmente queda vacio.
        }

        renderHistory();

        status.textContent = 'Nueva conversación iniciada.';
        input.value = '';
        input.focus();
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();

        const text = input.value.trim();

        if (!text || sendButton.disabled) {
            return;
        }

        appendMessage('user', text);

        const outgoingHistory = [
            ...history,
            { role: 'user', content: text }
        ].slice(-10);

        input.value = '';
        input.disabled = true;
        sendButton.disabled = true;
        status.textContent = 'El asistente está escribiendo...';

        const typing = mostrarEscribiendo();

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    messages: outgoingHistory
                })
            });

            let data = {};

            try {
                data = await response.json();
            } catch {
                // Gestionamos abajo las respuestas que no sean JSON.
            }

            if (!response.ok) {
                throw new Error(
                    data.message ||
                    'No se pudo obtener una respuesta del asistente.'
                );
            }

            if (typeof data.reply !== 'string' || !data.reply.trim()) {
                throw new Error(
                    'El asistente no devolvió una respuesta válida.'
                );
            }

            history = [
                ...outgoingHistory,
                { role: 'assistant', content: data.reply }
            ].slice(-10);

            saveHistory();
            appendMessage('assistant', data.reply);

            status.textContent =
                'Podés seguir consultando sobre el sistema.';
        } catch (error) {
            appendMessage(
                'assistant',
                error.message ||
                'No se pudo conectar con la inteligencia artificial.'
            );

            status.textContent =
                'Verificá que Ollama esté funcionando.';
        } finally {
            ocultarEscribiendo(typing);

            input.disabled = false;
            sendButton.disabled = false;
            input.focus();
        }
    });
})();
</script>
@endauth