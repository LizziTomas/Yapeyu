<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ChatIAController extends Controller
{
    private const INSTRUCCIONES = <<<'PROMPT'
Sos el asistente interno del sistema de gestión de Casa Yacobone.

Tu tarea es explicar cómo utilizar las funcionalidades del sistema.
Respondé siempre en español, con explicaciones claras y prácticas.

Usá la documentación incluida en el mensaje del sistema como referencia.
No inventes pantallas, botones, permisos ni procedimientos.

No respondas consultas generales sobre temas ajenos al sistema.
Si la pregunta no se relaciona con Casa Yacobone, indicá amablemente
que tu función es ayudar con el sistema de gestión.

No afirmes tener acceso a datos actuales de stock, ventas, cajas o
usuarios. En esta versión no consultás esos datos en tiempo real.

No afirmes haber realizado operaciones en el sistema.

Respondé de forma concisa: si el tema requiere mucho detalle,
resumí los pasos principales y ofrecé ampliar la explicación.
PROMPT;

    private const MENSAJE_FUERA_DE_TEMA =
        'Soy el asistente de Casa Yacobone y estoy diseñado para ayudarte '
        . 'con el sistema de gestión. Puedo orientarte sobre productos, '
        . 'stock, cajas, ventas, movimientos, usuarios y permisos. '
        . '¿Qué necesitás hacer dentro del sistema?';

    public function index(): RedirectResponse
    {
        return redirect()->route('dashboard');
    }

    public function mensaje(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:12'],
            'messages.*' => ['required', 'array:role,content'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:2000'],
        ]);

        $mensajes = array_map(
            static fn (array $mensaje): array => [
                'role' => $mensaje['role'],
                'content' => $mensaje['content'],
            ],
            $datos['messages']
        );

        $indiceActual = array_key_last($mensajes);
        $mensajeActual = $mensajes[$indiceActual];

        if ($mensajeActual['role'] !== 'user') {
            return response()->json([
                'message' => 'El último mensaje debe ser una consulta del usuario.',
            ], 422);
        }

        $consulta = $mensajeActual['content'];
        $mensajesAnteriores = array_slice($mensajes, 0, $indiceActual);
        $url = rtrim((string) config('services.ollama.url'), '/');

        try {
            $documentacion = $this->leerDocumentacion();

            $relacionada = $this->consultaRelacionada(
                $consulta,
                $mensajesAnteriores,
                $documentacion,
                $url
            );
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => 'No se pudo conectar con Ollama para validar la consulta.',
            ], 503);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se pudo validar el tema de la consulta. '
                    . 'Verificá que Ollama y el modelo de embeddings estén disponibles.',
            ], 503);
        }

        if (! $relacionada) {
            return response()->json([
                'reply' => self::MENSAJE_FUERA_DE_TEMA,
                'in_scope' => false,
            ]);
        }

        $mensajes = array_slice($mensajes, -10);

        array_unshift($mensajes, [
            'role' => 'system',
            'content' => self::INSTRUCCIONES
                . "\n\nDOCUMENTACION DEL SISTEMA:\n"
                . $documentacion,
        ]);

        try {
            $respuesta = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(180)
                ->post($url . '/api/chat', [
                    'model' => config('services.ollama.model'),
                    'messages' => $mensajes,
                    'stream' => false,
                    'think' => false,
                    'keep_alive' => '10m',
                    'options' => [
                        'num_predict' => 380,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => 'No se pudo conectar con Ollama. '
                    . 'Verificá que el servicio esté funcionando.',
            ], 503);
        }

        if ($respuesta->failed()) {
            return response()->json([
                'message' => 'Ollama devolvió un error. Verificá el modelo configurado.',
            ], 502);
        }

        $texto = $respuesta->json('message.content');

        if (! is_string($texto) || trim($texto) === '') {
            return response()->json([
                'message' => 'La IA no devolvió una respuesta válida.',
            ], 502);
        }

        $texto = trim($texto);

        // Si el modelo se detuvo por el límite de tokens, avisamos al usuario
        // en lugar de devolver una respuesta cortada a la mitad.
        if ($respuesta->json('done_reason') === 'length') {
            $texto .= "\n\n"
                . '(La respuesta se cortó por límite de longitud. '
                . 'Pedime que continúe si necesitás más detalle.)';
        }

        return response()->json([
            'reply' => $texto,
            'in_scope' => true,
        ]);
    }

    private function leerDocumentacion(): string
    {
        $ruta = resource_path('ai/casa-yacobone.md');
        $documentacion = file_get_contents($ruta);

        if (! is_string($documentacion) || trim($documentacion) === '') {
            throw new RuntimeException(
                'No se pudo leer la documentacion del asistente.'
            );
        }

        return $documentacion;
    }

    private function consultaRelacionada(
        string $consulta,
        array $mensajesAnteriores,
        string $documentacion,
        string $url
    ): bool {
        $fragmentos = preg_split(
            '/(?=^##\s+)/m',
            trim($documentacion)
        ) ?: [];

        $fragmentos = array_values(array_filter(
            array_map('trim', $fragmentos),
            static fn (string $fragmento): bool => $fragmento !== ''
        ));

        if ($fragmentos === []) {
            throw new RuntimeException(
                'La documentacion no contiene secciones para comparar.'
            );
        }

        $modelo = config('services.ollama.embedding_model');

        $claveCache = 'chat_ia_document_embeddings_'
            . hash('sha256', $modelo . "\n" . $documentacion);

        $embeddingsDocumento = Cache::store('file')->rememberForever(
            $claveCache,
            function () use ($url, $fragmentos): array {
                return $this->solicitarEmbeddings($url, $fragmentos);
            }
        );

        if (
            ! is_array($embeddingsDocumento)
            || count($embeddingsDocumento) !== count($fragmentos)
        ) {
            throw new RuntimeException(
                'Los embeddings de la documentacion no son validos.'
            );
        }

        $embeddingConsulta = $this->solicitarEmbeddings(
            $url,
            $consulta
        )[0] ?? null;

        if (! is_array($embeddingConsulta)) {
            throw new RuntimeException(
                'No se pudo generar el embedding de la consulta.'
            );
        }

        $umbral = (float) config(
            'services.ollama.scope_threshold',
            0.40
        );

        $similitud = $this->mayorSimilitud(
            $embeddingConsulta,
            $embeddingsDocumento
        );

        if ($similitud >= $umbral) {
            return true;
        }

        // Para una pregunta corta, comprobamos tambien el contexto reciente.
        // Esto ayuda con seguimientos como "y despues que hago?".
        preg_match_all('/[\p{L}\p{N}]+/u', $consulta, $palabras);
        $cantidadPalabras = count($palabras[0]);

        if ($cantidadPalabras > 5 || $mensajesAnteriores === []) {
            return false;
        }

        $contexto = array_slice($mensajesAnteriores, -4);

        $textoContexto = implode(
            "\n",
            array_map(
                static fn (array $mensaje): string =>
                    $mensaje['role'] . ': ' . mb_substr($mensaje['content'], 0, 500),
                $contexto
            )
        );

        $consultaConContexto = $this->solicitarEmbeddings(
            $url,
            "Contexto reciente de la conversacion:\n"
                . $textoContexto
                . "\nPregunta actual: "
                . $consulta
        )[0] ?? null;

        if (! is_array($consultaConContexto)) {
            throw new RuntimeException(
                'No se pudo analizar el contexto de la consulta.'
            );
        }

        return $this->mayorSimilitud(
            $consultaConContexto,
            $embeddingsDocumento
        ) >= $umbral;
    }

    private function solicitarEmbeddings(
        string $url,
        string|array $entrada
    ): array {
        $respuesta = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(120)
            ->post($url . '/api/embed', [
                'model' => config('services.ollama.embedding_model'),
                'input' => $entrada,
                'keep_alive' => '5m',
            ]);

        if ($respuesta->failed()) {
            throw new RuntimeException(
                'La API de embeddings devolvio un error.'
            );
        }

        $embeddings = $respuesta->json('embeddings');

        if (! is_array($embeddings) || $embeddings === []) {
            throw new RuntimeException(
                'La API no devolvio embeddings validos.'
            );
        }

        return $embeddings;
    }

    private function mayorSimilitud(
        array $consulta,
        array $documentos
    ): float {
        $mayor = -1.0;

        foreach ($documentos as $documento) {
            if (! is_array($documento)) {
                continue;
            }

            $mayor = max(
                $mayor,
                $this->similitudCoseno($consulta, $documento)
            );
        }

        return $mayor;
    }

    private function similitudCoseno(
        array $a,
        array $b
    ): float {
        $largo = min(count($a), count($b));

        if ($largo === 0) {
            return 0.0;
        }

        $producto = 0.0;
        $normaA = 0.0;
        $normaB = 0.0;

        for ($i = 0; $i < $largo; $i++) {
            $valorA = (float) $a[$i];
            $valorB = (float) $b[$i];

            $producto += $valorA * $valorB;
            $normaA += $valorA * $valorA;
            $normaB += $valorB * $valorB;
        }

        if ($normaA <= 0 || $normaB <= 0) {
            return 0.0;
        }

        return $producto / (sqrt($normaA) * sqrt($normaB));
    }
}