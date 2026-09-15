<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVentaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para registrar una venta.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('crear ventas');
    }

    /**
     * Reglas de validación para la creación de una venta.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'medio_pago' => ['required', 'string', 'in:efectivo,transferencia,tarjeta'],
            'productos' => ['required', 'array', 'min:1'],
            'productos.*.id' => ['required', 'integer', 'exists:productos,id'],
            'productos.*.cantidad' => ['required', 'integer', 'min:1'],
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
            'medio_pago.required' => 'El medio de pago es obligatorio.',
            'medio_pago.in' => 'El medio de pago seleccionado no es válido.',
            'productos.required' => 'Debes incluir al menos un producto en la venta.',
            'productos.array' => 'La lista de productos tiene un formato inválido.',
            'productos.min' => 'Debes agregar al menos un producto a la venta.',
            'productos.*.id.required' => 'El identificador del producto es obligatorio.',
            'productos.*.id.integer' => 'El identificador del producto debe ser un número entero.',
            'productos.*.id.exists' => 'Uno de los productos seleccionados no existe.',
            'productos.*.cantidad.required' => 'La cantidad de cada producto es obligatoria.',
            'productos.*.cantidad.integer' => 'La cantidad debe ser un número entero.',
            'productos.*.cantidad.min' => 'La cantidad mínima a vender de cada producto es 1.',
        ];
    }
}
