<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarIngresoRapidoRequest extends FormRequest
{
    /**
     * Autoriza el ingreso rápido de mercadería desde la pantalla de venta.
     *
     * Se apoya en el permiso acotado 'ingreso rapido en venta' y NO reutiliza
     * ProductoPolicy::create ni MovimientoStockPolicy::create, que siguen
     * reservados al administrador.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('ingreso rapido en venta');
    }

    /**
     * Reglas de validación del ingreso rápido.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $decimales = configuracion()->decimales();

        return [
            'modo' => ['required', 'in:existente,nuevo'],
            'producto_id' => ['required_if:modo,existente', 'nullable', 'integer', 'exists:productos,id'],
            'nombre' => ['required_if:modo,nuevo', 'nullable', 'string', 'max:255'],
            'precio_costo' => ['required_if:modo,nuevo', 'nullable', 'numeric', 'min:0', 'decimal:0,'.$decimales],
            'precio_venta' => ['required_if:modo,nuevo', 'nullable', 'numeric', 'min:0', 'decimal:0,'.$decimales],
            'stock_minimo' => ['required_if:modo,nuevo', 'nullable', 'integer', 'min:0'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'observacion' => ['nullable', 'string', 'max:255'],
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
            'modo.required' => 'Debes indicar si el producto ya existe o es nuevo.',
            'modo.in' => 'La opción seleccionada no es válida.',
            'producto_id.required_if' => 'Debes seleccionar el producto que llegó.',
            'producto_id.exists' => 'El producto seleccionado no existe.',
            'nombre.required_if' => 'El nombre del producto nuevo es obligatorio.',
            'precio_costo.required_if' => 'El precio de costo es obligatorio para un producto nuevo.',
            'precio_costo.decimal' => 'El monto tiene más decimales de los que permite la configuración del local.',
            'precio_venta.required_if' => 'El precio de venta es obligatorio para un producto nuevo.',
            'precio_venta.decimal' => 'El monto tiene más decimales de los que permite la configuración del local.',
            'stock_minimo.required_if' => 'El stock mínimo es obligatorio para un producto nuevo.',
            'cantidad.required' => 'Debes indicar la cantidad que llegó.',
            'cantidad.min' => 'La cantidad que llegó debe ser al menos 1.',
        ];
    }
}
