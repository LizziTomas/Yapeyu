<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCajaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('abrir cajas');
    }

    /**
     * Reglas de validación para la apertura de caja.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'monto_inicial' => ['required', 'numeric', 'min:0'],
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
            'monto_inicial.required' => 'El monto inicial es obligatorio.',
            'monto_inicial.numeric' => 'El monto inicial debe ser un número válido.',
            'monto_inicial.min' => 'El monto inicial no puede ser un valor negativo.',
        ];
    }
}
