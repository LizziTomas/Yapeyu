<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDescarteStockRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a registrar un descarte.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('crear descartes stock');
    }

    /**
     * Reglas de validación para el descarte de mercadería.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
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
            'producto_id.required' => 'Debes seleccionar un producto.',
            'producto_id.integer' => 'El identificador del producto es inválido.',
            'producto_id.exists' => 'El producto seleccionado no existe.',
            'cantidad.required' => 'La cantidad a descartar es obligatoria.',
            'cantidad.integer' => 'La cantidad debe ser un número entero.',
            'cantidad.min' => 'La cantidad a descartar debe ser mayor a 0.',
            'motivo.required' => 'Debes indicar el motivo del descarte.',
            'motivo.string' => 'El motivo del descarte es inválido.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
            'motivo.max' => 'El motivo no puede superar los 255 caracteres.',
        ];
    }
}
