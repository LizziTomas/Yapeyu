<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnularVentaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para anular la venta.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('anular ventas');
    }

    /**
     * Reglas de validación para la anulación de una venta.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:255'],
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
            'motivo.required' => 'Debes indicar el motivo de la anulación.',
            'motivo.string' => 'El motivo de la anulación es inválido.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
            'motivo.max' => 'El motivo no puede superar los 255 caracteres.',
        ];
    }
}
