@extends('layouts.app')

@section('content')
    <div class="d-flex flex-column gap-3">
        <div class="d-flex align-items-center gap-3">
            <span class="icon-tile">
                <i class="bi bi-chat-dots fs-4"></i>
            </span>

            <div>
                <h1 class="h4 mb-1">Asistente IA</h1>
                <p class="text-body-secondary mb-0">
                    Consultá cómo utilizar las funciones del sistema.
                </p>
            </div>
        </div>

        <div class="card card-panel">
            <div class="card-header d-flex align-items-center gap-2 py-3">
                <strong>Asistente de Casa Yacobone</strong>
            </div>

            <div class="card-body p-0">
                <div
                    id="chat-messages"
                    class="p-3 d-flex flex-column gap-3"
                    style="height: min(60vh, 560px); min-height: 320px; overflow-y: auto;"
                    role="log"
                    aria-live="polite"
                ></div>
            </div>

            <div class="card-footer bg-body p-3">
                <form id="chat-form">
                    <label for="chat-input" class="form-label">
                        Escribí tu consulta
                    </label>

                    <div class="d-flex gap-2 align-items-end">
                        <textarea
                            id="chat-input"
                            class="form-control"
                            rows="2"
                            maxlength="2000"
                            placeholder="Ej.: ¿Qué necesito para registrar una venta?"
                            required
                        ></textarea>

                        <button
                            id="send-button"
                            class="btn btn-primary"
                            type="submit"
                        >
                            <i class="bi bi-send me-1"></i>
                            Enviar
                        </button>
                    </div>
                </form>

                <div
                    id="chat-status"
                    class="small text-body-secondary mt-2"
                    aria-live="polite"
                >
                    Podés preguntar sobre productos, cajas, ventas
                    y movimientos de stock.
                </div>

                <p class="small text-body-secondary mt-2 mb-0">
                    La IA puede equivocarse. En esta versión no consulta
                    las cantidades reales de stock ni las ventas actuales.
                </p>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const form = document.getElementById('chat-form');
            const input = document.getElementById('chat-input');
            const sendButton = document.getElementById('send-button');
            const container = document.getElementById('chat-messages');
            const status = document.getElementById('chat-status');

            const endpoint = @json(route('chat-ia.mensaje'));
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                .getAttribute('content');

            let history = [];

            function showMessage(role, text) {
                const row = document.createElement('div');

                row.className = role === 'user'
                    ? 'd-flex justify-content-end'
                    : 'd-flex justify-content-start';

                const bubble = document.createElement('div');

                bubble.className = role === 'user'
                    ? 'rounded-3 px-3 py-2 bg-primary text-white'
                    : 'rounded-3 border px-3 py-2 bg-body-tertiary';

                bubble.style.maxWidth = '85%';
                bubble.style.whiteSpace = 'pre-wrap';

                bubble.textContent = text;

                row.appendChild(bubble);
                container.appendChild(row);

                container.scrollTop = container.scrollHeight;
            }

            showMessage(
                'assistant',
                '¡Hola! Puedo ayudarte a entender cómo utilizar el sistema. ' +
                'Preguntame sobre productos, cajas, ventas o movimientos ' +
                'de stock.'
            );

            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                const text = input.value.trim();

                if (!text || sendButton.disabled) {
                    return;
                }

                input.value = '';
                input.disabled = true;
                sendButton.disabled = true;

                showMessage('user', text);

                history.push({
                    role: 'user',
                    content: text,
                });

                status.textContent = 'La IA está preparando la respuesta...';

                try {
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            messages: history.slice(-12),
                        }),
                    });

                    let data = {};

                    try {
                        data = await response.json();
                    } catch {
                        // Si la respuesta no contiene JSON, se manejara
                        // con el mensaje genérico de error.
                    }

                    if (!response.ok) {
                        throw new Error(
                            data.message ||
                            'No se pudo obtener una respuesta de la IA.'
                        );
                    }

                    if (typeof data.reply !== 'string' || !data.reply.trim()) {
                        throw new Error(
                            'La IA no devolvio una respuesta valida.'
                        );
                    }

                    showMessage('assistant', data.reply);

                    history.push({
                        role: 'assistant',
                        content: data.reply,
                    });

                    history = history.slice(-10);

                    status.textContent =
                        'Podés seguir preguntando sobre el sistema.';
                } catch (error) {
                    history.pop();

                    showMessage(
                        'assistant',
                        error.message ||
                        'Ocurrió un error al comunicarse con la IA.'
                    );

                    status.textContent =
                        'Si el problema continúa, verificá que Ollama esté funcionando.';
                } finally {
                    input.disabled = false;
                    sendButton.disabled = false;
                    input.focus();
                }
            });
        })();
    </script>
@endsection