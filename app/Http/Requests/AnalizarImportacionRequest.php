<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AnalizarImportacionRequest extends FormRequest
{
    /**
     * La importación reutiliza el permiso existente "crear productos".
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('crear productos');
    }

    /**
     * Reglas de validación del formulario de importación.
     *
     * Se acepta un archivo CSV o texto pegado desde Excel. La validación del
     * contenido (columnas, montos, filas) la hace el servicio de importación.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Se valida la extensión y no el MIME porque Excel suele reportar
            // los CSV como "application/vnd.ms-excel".
            'archivo' => ['nullable', 'required_without:texto', 'file', 'max:2048', 'extensions:csv,txt'],
            'texto' => ['nullable', 'required_without:archivo', 'string', 'max:2000000'],
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
            'archivo.required_without' => 'Subí un archivo CSV o pegá los datos desde Excel.',
            'archivo.file' => 'No se pudo leer el archivo subido.',
            'archivo.max' => 'El archivo no puede superar los 2 MB.',
            'archivo.extensions' => 'El archivo debe ser un CSV. En Excel usá "Guardar como" y elegí CSV.',
            'texto.required_without' => 'Subí un archivo CSV o pegá los datos desde Excel.',
            'texto.max' => 'El texto pegado es demasiado grande.',
        ];
    }

    /**
     * Contenido a importar: el archivo si se subió, o el texto pegado.
     */
    public function contenido(): string
    {
        if ($this->hasFile('archivo')) {
            $ruta = $this->file('archivo')->getRealPath();

            return $ruta !== false ? (string) file_get_contents($ruta) : '';
        }

        return (string) $this->input('texto', '');
    }
}
