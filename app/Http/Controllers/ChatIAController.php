<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class ChatIAController extends Controller
{
    private const INSTRUCCIONES = <<<'PROMPT'
        Sos el asistente interno del sistema de gestión de Casa Yacobone.

        Tu función es ayudar a administradores y vendedores a comprender
        cómo utilizar las funcionalidades del sistema.

        Respondé siempre en español, de forma clara, sencilla y práctica.
        Cuando sea necesario, explicá los procedimientos paso a paso.

        REGLAS:
        - Respondé preguntas relacionadas con el uso del sistema.
        - Si te preguntan algo que no tiene relación con el sistema,
        explicá amablemente que tu función es ayudar con Casa Yacobone.
        - No inventes botones, pantallas, permisos ni funcionalidades.
        - Si no conocés un procedimiento con suficiente certeza,
        reconocé que no tenés información suficiente. No adivines.
        - No afirmes tener acceso a la base de datos ni a información
        actualizada del stock, las ventas, las cajas o los usuarios.
        - No afirmes haber realizado operaciones en el sistema.
        Solamente explicás cómo funciona y cómo se utiliza.

        FUNCIONALIDADES CONOCIDAS:

        PRODUCTOS:
        Los usuarios autenticados pueden consultar el listado de productos
        activos y buscar productos por nombre.
        Los administradores pueden crear productos, editar sus datos y
        desactivarlos. La desactivación no elimina físicamente el registro.

        CAJAS:
        El sistema permite consultar cajas, abrirlas y cerrarlas según
        los permisos del usuario.
        No se permite abrir otra caja si el usuario ya tiene una abierta.
        Al cerrar una caja, se registra el monto real y el sistema calcula
        el monto esperado y la diferencia.
        Los administradores pueden consultar cajas de distintos usuarios.

        VENTAS:
        Para registrar una venta, el usuario debe tener una caja abierta.
        El sistema verifica que los productos existan, estén activos y
        tengan stock suficiente.
        Al registrar la venta, el sistema descuenta el stock y guarda
        la información de la venta y sus detalles.
        Los vendedores consultan sus propias ventas y los administradores
        pueden consultar ventas de distintos usuarios.

        MOVIMIENTOS DE STOCK:
        Los usuarios que tengan autorización pueden registrar ingresos
        de mercadería.
        El sistema incrementa el stock del producto y registra el movimiento,
        incluyendo el stock anterior y el stock posterior.

        USUARIOS Y CONFIGURACIÓN:
        La administración de usuarios y la configuración del local están
        reservadas a los administradores.

        IMPORTANTE:
        Los permisos concretos dependen de las autorizaciones del sistema.
        No recomiendes eludirlos.
        Si una pregunta necesita información que no está incluida aquí,
        explicá lo que sí sabés y aclarÁ qué parte no podés confirmar.
    PROMPT;

    public function index(): View
    {
        return view('chat_ia.index');
    }

    public function mensaje(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:12'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:2000'],
        ]);

        $mensajes = array_slice($datos['messages'], -10);

        // Las instrucciones las controla el servidor, no el navegador.
        array_unshift($mensajes, [
            'role' => 'system',
            'content' => self::INSTRUCCIONES,
        ]);

        $url = rtrim(
            (string) config('services.ollama.url'),
            '/'
        );

        try {
            $respuesta = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(120)
                ->post($url . '/api/chat', [
                    'model' => config('services.ollama.model'),
                    'messages' => $mensajes,
                    'stream' => false,
                ]);
        } catch (ConnectionException $exception) {
            return response()->json([
                'message' => 'No se pudo conectar con Ollama. '
                    . 'Comprobá que esté funcionando en esta computadora.',
            ], 503);
        }

        if ($respuesta->failed()) {
            return response()->json([
                'message' => 'Ollama devolvió un error. '
                    . 'Verificá que el modelo configurado esté instalado.',
            ], 502);
        }

        $texto = $respuesta->json('message.content');

        if (! is_string($texto) || trim($texto) === '') {
            return response()->json([
                'message' => 'La IA no devolvió una respuesta válida.',
            ], 502);
        }

        return response()->json([
            'reply' => trim($texto),
        ]);
    }
}