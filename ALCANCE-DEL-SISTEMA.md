# Alcance del sistema

Resumen funcional de la plataforma, pensado como insumo para un informe de
costos. Describe **qué hace el sistema**, no cómo está construido por dentro;
la guía técnica de instalación está en [README.md](README.md).

Las cifras de la sección *Dimensiones* están medidas sobre el código, no
estimadas.

---

## 1. Qué es

Plataforma web para un centro médico ocupacional. Cubre dos frentes:

- Un **sitio público** donde los clientes conocen los servicios, dejan
  solicitudes de contacto y **verifican la autenticidad** de cualquier
  documento que el centro haya emitido.
- Un **panel administrativo** donde el personal diligencia formularios y
  obtiene, en PDF y listo para imprimir, cuatro tipos de documento distintos.

Todo documento emitido lleva un **consecutivo único** y un **código QR** que
conduce a una página pública de verificación. Ningún documento se borra: se
anula, de modo que un QR ya impreso sigue respondiendo e informa que el
documento perdió validez.

---

## 2. Documentos que genera

| Documento | Formato | Qué contiene |
|---|---|---|
| **Examen médico ocupacional** | 2 páginas carta | Evaluación para trabajo en alturas y espacios confinados: datos del trabajador y del empleador, examen físico por sistemas, paraclínicos, tres conceptos de aptitud, consentimiento informado, firma del trabajador, sello del médico, marca de agua y QR |
| **Paquete de manipulación de alimentos** | 5 páginas carta + carné | Resultados de laboratorio (frotis de garganta, KOH, coprológico, serología VDRL, uroanálisis), certificado médico de aptitud, certificación de capacitación y carné de manipulador en tamaño tarjeta con foto y QR |
| **Certificado de capacitación** | 1 página horizontal | Réplica del diseño oficial de trabajo en alturas, con el consecutivo y un QR que lleva a una página de confirmación propia |
| **Cuenta de cobro** | 1 página carta | Cobro a empresas, con ítems, total en números y en letras, y los datos y la firma del emisor |

Cada documento se puede **previsualizar antes de descargar**, **corregir**
después de emitido conservando su consecutivo, y **anular** dejando constancia
del motivo.

---

## 3. Módulos

### 3.1 Sitio público

- **Servicios**: página comercial con carrusel de imágenes, catálogo de
  servicios y banner de presentación.
- **Contáctenos**: formulario de contacto con los datos del centro. Los
  mensajes llegan a una bandeja dentro del panel.
- **Consulta pública**: búsqueda por número de cédula que confirma los
  documentos emitidos a esa persona. Los datos personales salen enmascarados
  (iniciales y últimos cuatro dígitos) por protección de datos.
- **Verificación por QR**: página a la que conduce el código impreso en cada
  documento. Informa si es auténtico, si fue anulado o si el código no existe.

### 3.2 Panel administrativo

Acceso con usuario y contraseña. Siete secciones, agrupadas en un menú
desplegable que muestra solo lo que la cuenta tiene permitido:

| Sección | Qué permite |
|---|---|
| **Exámenes ocupacionales** | Emitir, corregir, anular y descargar la evaluación médica. El examen físico y los paraclínicos vienen precargados con valores dentro de rangos normales para que el médico solo corrija lo que no corresponda |
| **Manipulación de alimentos** | Emitir el paquete de cinco páginas y el carné. Cada examen de laboratorio llega precargado con su valor normal |
| **Certificados de capacitación** | Emitir certificados de formación con su consecutivo y su página de verificación |
| **Cuentas de cobro** | Facturar a empresas. Guarda las empresas para reutilizarlas, calcula subtotales y total en pantalla, y lleva el historial con búsqueda |
| **Mensajes de contacto** | Bandeja de las solicitudes recibidas desde el sitio público, con marcado de atendidas |
| **Configuración** | Identidad visual y datos del centro, sin tocar código (ver 3.3) |
| **Uso de la plataforma** | Tablero de métricas, restringido a cuentas de desarrollo (ver 3.4) |

Los cuatro listados de documentos comparten: búsqueda, filtro entre vigentes y
anulados, **ordenamiento por cualquier columna** en ambos sentidos, selector de
cuántos registros mostrar y paginación.

### 3.3 Configuración sin programador

Desde el panel se cambian, y se aplican de inmediato en toda la plataforma y en
los documentos emitidos:

- Colores de marca y de acento, tipografías, redondeo de bordes, logo, nombre
  de la plataforma y eslogan.
- Datos del centro médico: razón social, NIT, dirección, teléfono, correo y
  horario de atención.
- Textos fijos del paquete de alimentos: nombre del curso, intensidad horaria,
  resolución aplicable, autorización del carné y nombres de quienes firman.
- Datos del emisor de las cuentas de cobro, incluida su firma.
- Conceptos facturables de las cuentas de cobro: se agregan, renombran y
  retiran de la lista.
- Galería de fotografías del sitio público.

### 3.4 Tablero de uso (cuentas de desarrollo)

Métricas de uso de la plataforma, con acceso restringido por rol:

- Totales de documentos emitidos, por tipo.
- Serie temporal agrupable **por hora, día, semana o mes**, con rango de fechas.
- Gráfica de **las horas del día con más registros** y reparto por día de la
  semana.
- Los mismos datos en tabla.

---

## 4. Características transversales

- **Numeración consecutiva** independiente por tipo de documento, a prueba de
  emisiones simultáneas. Un consecutivo anulado no se reutiliza.
- **Anulación en vez de borrado**, con motivo obligatorio y constancia de quién
  y cuándo.
- **Verificación pública** de los cuatro tipos de documento mediante QR o
  búsqueda por cédula.
- **Enmascaramiento de datos personales** en todo lo que se expone sin sesión.
- **Respaldo de la base de datos** enviado por correo electrónico.
- **Roles de cuenta**: administrador y desarrollo.
- **Trazabilidad**: cada documento registra quién lo creó y cuándo, dato
  independiente de la fecha que escribe el usuario en el formulario.

---

## 5. Dimensiones

Medidas sobre el código fuente del proyecto.

| Concepto | Cantidad |
|---|---|
| Tipos de documento PDF | 4 |
| Endpoints de la API | 57 (8 de acceso público) |
| Pantallas de la aplicación | 24 rutas de navegación |
| Vistas y componentes de interfaz | 20 vistas + 14 componentes |
| Tablas en la base de datos | 25 (17 del negocio, 8 de infraestructura) |
| Migraciones de base de datos | 24 |
| Modelos de datos | 16 |
| Plantillas de documento | 6 |
| **Pruebas automatizadas** | **208 pruebas, 1.043 comprobaciones** |
| Líneas de código — backend | ~8.800 |
| Líneas de código — interfaz | ~8.700 |
| Líneas de código — pruebas | ~3.700 |
| **Total de código propio** | **~21.200 líneas** |

La suite de pruebas cubre la emisión de los cuatro documentos, la numeración
consecutiva, la anulación, la verificación pública, el control de acceso por
rol, el ordenamiento y la paginación de los listados, y la fidelidad de los PDF
frente a sus documentos de referencia.

---

## 6. Infraestructura

| Componente | Función |
|---|---|
| Sitio estático | Interfaz de usuario (Vue) |
| Servicio web en contenedor | API y generación de los PDF (Laravel) |
| Base de datos PostgreSQL | Almacenamiento |
| Servidor de correo (SMTP) | Envío de los respaldos de la base |

Consideraciones de costo recurrente a tener en cuenta en el informe:

- Los servicios en plan gratuito **se suspenden por inactividad** y tardan en
  responder la primera petición tras un periodo sin uso.
- El almacenamiento de los contenedores es **efímero**: por eso las imágenes
  (logos, fotografías, firmas) se guardan en la base de datos y no en disco, lo
  que incrementa el tamaño de la base frente a una solución con almacenamiento
  de archivos.
- El envío de correo requiere una cuenta SMTP propia.

---

## 7. Pendientes conocidos

Estado al momento de redactar este documento:

- **Paquete de manipulación de alimentos**: faltan las imágenes de encabezado y
  pie de la plantilla oficial; mientras tanto el documento usa un encabezado de
  reemplazo con los datos del centro. Queda pendiente ajustar la redacción y la
  maqueta al documento de referencia entregado.
- **Certificado de capacitación**: usa tipografías libres de aspecto similar a
  las del diseño original, que son comerciales. El resto del diseño —fondo,
  posiciones, colores y tamaños— reproduce el original punto por punto.
- **Correo saliente**: requiere configurar las credenciales SMTP para que
  salgan los respaldos de la base.
- Los archivos de referencia entregados por el cliente (un PDF y dos documentos
  de Word con nombres y cédulas reales) están dentro del repositorio y conviene
  retirarlos.
