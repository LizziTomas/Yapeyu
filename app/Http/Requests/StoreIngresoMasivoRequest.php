<?php

namespace App\Http\Requests;

use App\Models\Producto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreIngresoMasivoRequest extends FormRequest
{
    /**
     * La carga masiva reutiliza el permiso existente "crear ingresos stock".
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('crear ingresos stock');
    }

    /**
     * Reglas de validación de la carga masiva de stock.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cantidades' => ['required', 'array'],
            'cantidades.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Comprobaciones que dependen de varios campos: al menos una cantidad
     * mayor a cero y productos existentes y activos.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $cantidades = collect($this->input('cantidades', []))
                    ->filter(fn ($cantidad) => $cantidad !== null && $cantidad !== '')
                    ->map(fn ($cantidad) => (int) $cantidad);

                if ($cantidades->filter(fn (int $cantidad) => $cantidad > 0)->isEmpty()) {
                    $validator->errors()->add('cantidades', 'Ingresá al menos una cantidad mayor a 0.');

                    return;
                }

                $ids = $cantidades->keys()->map(fn ($id) => (int) $id)->all();

                $activos = Producto::whereIn('id', $ids)->where('activo', true)->count();

                if ($activos !== count($ids)) {
                    $validator->errors()->add('cantidades', 'Uno de los productos ya no está activo. Actualizá la pantalla e intentá de nuevo.');
                }
            },
        ];
    }

    /**
     * Mensajes de validación en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cantidades.required' => 'No se recibieron cantidades para ingresar.',
            'cantidades.array' => 'Las cantidades enviadas no son válidas.',
            'cantidades.*.integer' => 'Cada cantidad debe ser un número entero.',
            'cantidades.*.min' => 'Las cantidades no pueden ser negativas.',
            'cantidades.*.max' => 'La cantidad ingresada es demasiado grande.',
            'observacion.max' => 'La observación no puede superar los 255 caracteres.',
        ];
    }
}
