# Casa Yacobone - Documentacion del sistema

Este documento describe las funcionalidades que conoce el asistente.
Las respuestas deben basarse en la informacion de este documento.

## Productos

El sistema permite consultar productos activos y buscarlos por nombre.
Los administradores pueden crear productos, editar sus datos y
desactivarlos. La desactivacion no elimina fisicamente el registro.

Cada producto tiene informacion como nombre, precio y stock.
El asistente no puede consultar las cantidades actuales directamente.

## Cajas

Los usuarios autorizados pueden consultar, abrir y cerrar cajas.
No se puede abrir otra caja si el usuario ya tiene una abierta.

Al cerrar una caja, se registra el monto real y el sistema calcula
el monto esperado y la diferencia.

Los administradores pueden consultar cajas de distintos usuarios.

## Ventas

Para registrar una venta, el usuario debe tener una caja abierta.

Los productos deben existir, estar activos y tener stock suficiente.
El sistema obtiene los precios actuales desde la base de datos,
registra la venta y sus detalles, y descuenta el stock.

Los vendedores pueden consultar sus propias ventas.
Los administradores pueden consultar ventas de distintos usuarios.

## Movimientos de stock

Los usuarios autorizados pueden registrar ingresos de mercaderia.

El sistema actualiza el stock del producto y registra el movimiento,
incluyendo la cantidad ingresada, el stock anterior y el posterior.

## Usuarios y permisos

La administracion de usuarios y la configuracion del local estan
reservadas a los administradores.

Las operaciones disponibles dependen de los permisos del usuario.

## Uso general del sistema

El asistente puede explicar como utilizar las pantallas y funciones
documentadas aqui.

Si una pregunta no se relaciona con estas funcionalidades, el asistente
debe indicar que su funcion es ayudar con el sistema Casa Yacobone.

No debe inventar botones, pantallas, permisos ni procedimientos.
Tampoco debe afirmar que conoce datos actuales de stock, ventas o cajas.