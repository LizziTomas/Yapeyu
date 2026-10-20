<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket de Caja #{{ $caja->id }} — {{ configuracion()->nombre_local }}</title>

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #e9edef;
            font-family: 'IBM Plex Sans', system-ui, sans-serif;
        }

        .ticket {
            max-width: 460px;
            margin: 24px auto;
            background: #ffffff;
            color: #1f2a30;
            border: 1px solid #d9dfe2;
            border-radius: .5rem;
            padding: 28px;
        }

        .ticket h1 {
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            margin: 0;
        }

        .ticket-subtitulo {
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #6c757d;
        }

        .ticket-seccion {
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #6c757d;
            margin-bottom: .35rem;
        }

        .ticket-linea {
            border-top: 1px dashed #9aa5ab;
        }

        .ticket-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            font-size: .875rem;
            padding: .18rem 0;
        }

        .ticket-row .valor {
            font-variant-numeric: tabular-nums;
            font-weight: 600;
            text-align: right;
        }

        .ticket-total {
            border-top: 2px solid #1f2a30;
            border-bottom: 2px solid #1f2a30;
            padding: .5rem 0;
            margin: .5rem 0;
            font-size: 1rem;
            font-weight: 700;
        }

        .ticket-meta {
            font-size: .8rem;
            color: #495057;
        }

        .firma {
            border-top: 1px solid #adb5bd;
            font-size: .75rem;
            color: #6c757d;
            width: 70%;
        }

        @media print {
            @page {
                margin: 10mm;
            }

            body {
                background: #ffffff;
            }

            .no-print {
                display: none !important;
            }

            .ticket {
                max-width: 100%;
                margin: 0;
                padding: 0;
                border: 0;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Barra de acciones: no se imprime -->
    <div class="container no-print" style="max-width: 460px">
        <div class="d-flex justify-content-between align-items-center py-3 gap-2">
            <a href="{{ route('cajas.show', $caja) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Volver a Caja #{{ $caja->id }}
            </a>
            <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Imprimir
            </button>
        </div>
    </div>

    <div class="ticket">
        {{-- Encabezado del ticket --}}
        <div class="text-center mb-3">
            <div class="mb-1" style="font-size: 1.4rem">
                <i class="bi bi-shop"></i>
            </div>
            <h1>{{ configuracion()->nombre_local }}</h1>
            <div class="ticket-subtitulo mt-1">Ticket de Cierre de Caja</div>
        </div>

        <div class="ticket-linea mb-3"></div>

        {{-- Identificación de la sesión --}}
        <div class="ticket-seccion">Sesión de Caja</div>
        <div class="ticket-row">
            <span>Número de caja</span>
            <span class="valor">#{{ $caja->id }}</span>
        </div>
        <div class="ticket-row">
            <span>Estado</span>
            <span class="valor">{{ $caja->estaCerrada() ? 'CERRADA' : 'ABIERTA' }}</span>
        </div>
        <div class="ticket-row">
            <span>Vendedor responsable</span>
            <span class="valor">{{ $caja->user->name ?? 'Usuario Eliminado' }}</span>
        </div>
        <div class="ticket-row">
            <span>Apertura</span>
            <span class="valor">{{ $caja->fecha_apertura->setTimezone('America/Argentina/Buenos_Aires')->format('d/m/Y H:i') }} hs</span>
        </div>
        <div class="ticket-row">
            <span>Cierre</span>
            <span class="valor">
                @if ($caja->fecha_cierre)
                    {{ $caja->fecha_cierre->setTimezone('America/Argentina/Buenos_Aires')->format('d/m/Y H:i') }} hs
                @else
                    En curso
                @endif
            </span>
        </div>

        <div class="ticket-linea my-3"></div>

        {{-- Movimiento del turno --}}
        <div class="ticket-seccion">Movimiento del Turno</div>
        <div class="ticket-row">
            <span>Ventas completadas</span>
            <span class="valor">{{ $caja->ventas()->where('estado', 'completada')->count() }}</span>
        </div>
        <div class="ticket-row">
            <span>Ventas anuladas</span>
            <span class="valor">{{ $caja->ventas()->where('estado', 'anulada')->count() }}</span>
        </div>
        <div class="ticket-row">
            <span>Total facturado</span>
            <span class="valor">$ {{ monto($caja->totalVendido()) }}</span>
        </div>

        <div class="ticket-linea my-3"></div>

        <div class="ticket-seccion">Desglose por Medio de Pago</div>
        <div class="ticket-row">
            <span>Efectivo</span>
            <span class="valor">$ {{ monto($caja->totalVentasEfectivo()) }}</span>
        </div>
        <div class="ticket-row">
            <span>Transferencia</span>
            <span class="valor">$ {{ monto($caja->totalVentasTransferencia()) }}</span>
        </div>
        <div class="ticket-row">
            <span>Tarjeta</span>
            <span class="valor">$ {{ monto($caja->totalVentasTarjeta()) }}</span>
        </div>

        <div class="ticket-linea my-3"></div>

        {{-- Arqueo de efectivo --}}
        <div class="ticket-seccion">Arqueo de Efectivo</div>
        <div class="ticket-row">
            <span>Fondo inicial</span>
            <span class="valor">$ {{ monto($caja->monto_inicial) }}</span>
        </div>
        <div class="ticket-row">
            <span>(+) Cobros en efectivo</span>
            <span class="valor">$ {{ monto($caja->totalVentasEfectivo()) }}</span>
        </div>
        <div class="ticket-row ticket-total">
            <span>Efectivo esperado</span>
            <span class="valor">$ {{ monto($caja->dineroEsperado()) }}</span>
        </div>

        @if ($caja->estaCerrada())
            <div class="ticket-row">
                <span>Efectivo contado</span>
                <span class="valor">$ {{ monto($caja->monto_real) }}</span>
            </div>
            <div class="ticket-row">
                <span>Diferencia</span>
                <span class="valor">
                    @if ($caja->diferencia == 0)
                        $ {{ monto(0) }} (Exacto)
                    @elseif ($caja->diferencia > 0)
                        +$ {{ monto($caja->diferencia) }} (Sobrante)
                    @else
                        -$ {{ monto(abs($caja->diferencia)) }} (Faltante)
                    @endif
                </span>
            </div>
        @endif

        <div class="ticket-linea my-4"></div>

        {{-- Pie del ticket --}}
        <div class="d-flex justify-content-between align-items-end gap-3 mb-4">
            <div class="firma pt-1">Firma del responsable</div>
            <div class="ticket-meta text-end">Sello / Firma<br>de supervisión</div>
        </div>

        <div class="ticket-meta text-center">
            Documento no válido como comprobante fiscal<br>
            Generado el {{ now('America/Argentina/Buenos_Aires')->format('d/m/Y H:i') }} hs
        </div>
    </div>
</body>
</html>
