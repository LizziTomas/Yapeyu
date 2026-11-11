<?php

namespace App\Http\Controllers;

use App\Ai\Agents\AsistenteCasaYacobone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Contracts\ConversationStore;
use Throwable;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Illuminate\Support\Facades\Log;

class ChatIAController extends Controller
{
    public function index()
    {
        return redirect()->route('dashboard');
    }

    public function mensaje(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'string', 'size:36'],
        ]);

        $usuario = $request->user();

        $agente = new AsistenteCasaYacobone($usuario);

	$inicio = microtime(true);

        try {
            if (! empty($datos['conversation_id'])) {
                $almacen = app(ConversationStore::class);

                $esPropia = $almacen->conversationBelongsTo(
                    $datos['conversation_id'],
                    $usuario->getMorphClass(),
                    $usuario->getKey()
                );

                if (! $esPropia) {
                    return response()->json([
                        'message' => 'No tenes permiso para acceder a esta conversacion.',
                    ], 403);
                }

                $respuesta = $agente
                    ->continue($datos['conversation_id'], as: $usuario)
                    ->prompt($datos['message']);
            } else {
                $respuesta = $agente
                    ->forUser($usuario)
                    ->prompt($datos['message']);
            }

            } catch (RateLimitedException $exception) {
            report($exception);

            $causa = $exception->getPrevious();

            $cabeceras = [];

            if ($causa instanceof RequestException) {
                $response = $causa->response;

                foreach ([
                    'retry-after',
                    'x-ratelimit-limit-requests',
                    'x-ratelimit-remaining-requests',
                    'x-ratelimit-reset-requests',
                    'x-ratelimit-limit-tokens',
                    'x-ratelimit-remaining-tokens',
                    'x-ratelimit-reset-tokens',
                ] as $nombre) {
                    $cabeceras[$nombre] = $response->header($nombre);
                }
            }

            Log::warning('Limite de Groq alcanzado', [
                'user_id' => $usuario->id,
                'status' => $causa instanceof RequestException
                    ? $causa->response->status()
                    : null,
                'headers' => $cabeceras,
            ]);

            $espera = $cabeceras['retry-after'] ?? null;

            $mensaje = 'Se alcanzo el limite temporal de solicitudes de Groq.';

            if (is_string($espera) && ctype_digit($espera)) {
                $mensaje .= " Volve a intentar en {$espera} segundos.";
            } else {
                $mensaje .= ' Espera un momento antes de volver a consultar.';
            }

            return response()->json([
                'message' => $mensaje,
            ], 429);

        } catch (RequestException $exception) {
            report($exception);

            return response()->json([
                'message' => 'El proveedor de IA devolvio un error al procesar la consulta.',
            ], 502);

        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se pudo obtener una respuesta del asistente. Verifica la configuracion del proveedor de IA.',
            ], 503);
        }

	$duracionMs = (int) round((microtime(true) - $inicio) * 1000);

	Log::info('Consumo del asistente IA', [
		'user_id' => $usuario->id,
		'conversation_id' => $respuesta->conversationId,
		'input_tokens' => $respuesta->usage->inputTokens,
		'output_tokens' => $respuesta->usage->outputTokens,
		'total_tokens' => $respuesta->usage->totalTokens(),
		'uncached_input_tokens' => $respuesta->usage->uncachedInputTokens(),
		'cache_read_input_tokens' => $respuesta->usage->cacheReadInputTokens,
		'cache_write_input_tokens' => $respuesta->usage->cacheWriteInputTokens,
		'reasoning_tokens' => $respuesta->usage->reasoningTokens,
		'model_steps' => $respuesta->steps->count(),
		'tool_calls' => $respuesta->toolCalls->count(),
		'duration_ms' => $duracionMs,
	]);

        $texto = $respuesta->text;

        if (! is_string($texto) || trim($texto) === '') {
            return response()->json([
                'message' => 'La IA no devolvio una respuesta valida.',
            ], 502);
        }

        return response()->json([
            'reply' => trim($texto),
            'conversation_id' => $respuesta->conversationId,
            'in_scope' => true,
        ]);
    }
}