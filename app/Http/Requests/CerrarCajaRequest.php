<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CerrarCajaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('cerrar cajas');
    }

    /**
     * Reglas de validación para el cierre de caja.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'monto_real' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Mensajes de validación personalizados en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monto_real.required' => 'El monto real es obligatorio.',
            'monto_real.numeric' => 'El monto real debe ser un número válido.',
            'monto_real.min' => 'El monto real no puede ser un valor negativo.',
        ];
    }
}
