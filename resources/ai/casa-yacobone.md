# Casa Yacobone - Documentación del sistema

Este documento describe las funcionalidades que conoce el asistente.
Las respuestas deben basarse en la información de este documento.

## Productos

El sistema permite consultar productos activos y buscarlos por nombre.
Los administradores pueden crear productos, editar sus datos y
desactivarlos. La desactivación no elimina físicamente el registro.

Cada producto tiene información como nombre, precio y stock.
El asistente no puede consultar las cantidades actuales directamente.

Un producto recién ingresado puede quedar disponible para la venta aunque
todavía no tenga stock cargado, mediante el ingreso rápido (ver Ventas).

## Cajas

Los usuarios autorizados pueden consultar, abrir y cerrar cajas.
Solo puede haber una caja abierta a la vez en todo el negocio: nadie puede
abrir otra hasta que quien la abrió la cierre.

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

Si la mercadería acaba de llegar y todavía no está cargada en el sistema,
un usuario autorizado puede usar el ingreso rápido desde la pantalla de nueva
venta: el sistema crea el producto si es nuevo, registra su ingreso de stock y
luego continúa con la venta normal.

## Movimientos de stock

Los usuarios autorizados pueden registrar ingresos de mercadería.

El sistema actualiza el stock del producto y registra el movimiento,
incluyendo la cantidad ingresada, el stock anterior y el posterior.

Existe además una vista de resumen por producto, que agrupa los movimientos
y permite ver en qué momento ingresó cada producto y consultar su historial.
Por defecto muestra el mes en curso, desde el día 1 hasta hoy, y las fechas
Desde y Hasta se pueden cambiar para consultar otros períodos.

Dentro del historial de un producto se puede consultar el stock entre dos
fechas: el sistema reconstruye el stock al inicio y al final del período a
partir de los movimientos registrados.

## Avisos de stock bajo

El sistema puede enviar avisos por correo a los administradores cuando un
producto queda con poco stock.

Cuando una venta o un descarte deja un producto en su stock mínimo o por
debajo, se envía un mail automático a todos los usuarios con rol
administrador. Solo se avisa en el momento en que el producto cruza el
mínimo: mientras siga bajo, el aviso no se repite.

Además, cada cierta cantidad de días (configurable entre 1 y 30) se envía
un resumen por correo con todos los productos que siguen por debajo del
mínimo.

La frecuencia y el encendido o apagado de los avisos se manejan desde la
pantalla de Configuración del local. Los mails se envían después de
confirmar la operación, así que un error de correo no afecta la venta ni el
descarte.

## Usuarios y permisos

La administración de usuarios y la configuración del local están
reservadas a los administradores.

Las operaciones disponibles dependen de los permisos del usuario.

## Uso general del sistema

El asistente puede explicar cómo utilizar las pantallas y funciones
documentadas aquí.

Si una pregunta no se relaciona con estas funcionalidades, el asistente
debe indicar que su función es ayudar con el sistema Casa Yacobone.

No debe inventar botones, pantallas, permisos ni procedimientos.
Tampoco debe afirmar que conoce datos actuales de stock, ventas o cajas.
