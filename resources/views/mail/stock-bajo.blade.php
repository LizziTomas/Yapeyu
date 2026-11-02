<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Aviso de stock bajo</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f5f7; font-family: Arial, Helvetica, sans-serif; color: #1f2933;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f5f7; padding: 24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 620px; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e3e6ea;">
                    <!-- Encabezado -->
                    <tr>
                        <td style="background-color: #b45309; padding: 20px 24px;">
                            <p style="margin: 0; font-size: 12px; letter-spacing: 1px; text-transform: uppercase; color: #fde68a;">Casa Yacobone</p>
                            <h1 style="margin: 4px 0 0; font-size: 20px; color: #ffffff;">Aviso de stock bajo</h1>
                        </td>
                    </tr>

                    <!-- Datos de la operación -->
                    <tr>
                        <td style="padding: 24px 24px 8px;">
                            <p style="margin: 0 0 4px; font-size: 14px;">
                                <strong>Motivo:</strong> {{ $motivo }}
                            </p>
                            @if ($usuario)
                                <p style="margin: 0 0 4px; font-size: 14px;">
                                    <strong>Registrado por:</strong> {{ $usuario }}
                                </p>
                            @endif
                            <p style="margin: 0; font-size: 14px; color: #52606d;">
                                <strong>Fecha:</strong> {{ $fecha }} (hora de Buenos Aires)
                            </p>
                        </td>
                    </tr>

                    <!-- Tabla de productos -->
                    <tr>
                        <td style="padding: 16px 24px 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; font-size: 14px;">
                                <thead>
                                    <tr style="background-color: #f4f5f7;">
                                        <th align="left" style="padding: 10px 12px; border-bottom: 2px solid #e3e6ea;">Producto</th>
                                        <th align="right" style="padding: 10px 12px; border-bottom: 2px solid #e3e6ea; width: 90px;">Stock</th>
                                        <th align="right" style="padding: 10px 12px; border-bottom: 2px solid #e3e6ea; width: 110px;">Mínimo</th>
                                        <th align="right" style="padding: 10px 12px; border-bottom: 2px solid #e3e6ea; width: 100px;">Faltante</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($productos as $producto)
                                        <tr>
                                            <td style="padding: 10px 12px; border-bottom: 1px solid #eef1f4;">{{ $producto['nombre'] }}</td>
                                            <td align="right" style="padding: 10px 12px; border-bottom: 1px solid #eef1f4; font-weight: bold; color: {{ $producto['stock'] <= 0 ? '#b91c1c' : '#b45309' }};">
                                                {{ $producto['stock'] }}
                                            </td>
                                            <td align="right" style="padding: 10px 12px; border-bottom: 1px solid #eef1f4; color: #52606d;">{{ $producto['stock_minimo'] }}</td>
                                            <td align="right" style="padding: 10px 12px; border-bottom: 1px solid #eef1f4; color: #52606d;">
                                                {{ max(0, $producto['stock_minimo'] - $producto['stock']) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>

                    <!-- Pie -->
                    <tr>
                        <td style="padding: 16px 24px 24px; border-top: 1px solid #eef1f4;">
                            <p style="margin: 0; font-size: 13px; color: #52606d;">
                                Conviene reponer estos productos para no frenar las ventas.
                                Ingresá al sistema y revisá la sección de Movimientos de stock.
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="margin: 16px 0 0; font-size: 11px; color: #9aa5b1;">
                    Este aviso lo genera automáticamente el sistema de gestión de Casa Yacobone.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
