# 📊 API - Resumen de Alumnos por Curso (Moodle 3.10.1)

Este script permite obtener un resumen detallado en formato JSON de los alumnos matriculados en un curso de Moodle, incluyendo:

- Nombre completo del usuario  
- Último acceso al curso  
- Porcentaje de finalización del curso  
- Cantidad de actividades completadas  
- Calificación final del curso  

---

## ⚙️ Requisitos

- **Moodle**: versión **3.10.1**  
- **PHP**: versión **7.4**

> Se utilizó esta versión porque en mi caso ya tenía una plataforma Moodle operativa con datos reales y pruebas funcionales disponibles.  
> Si se requiere compatibilidad con otra versión de Moodle o PHP, el script puede reestructurarse según la necesidad. No dudes en indicarmelo.

---

## 📦 Instalación

1. Copiar copiar la carpeta "api_gest_alum" de este repositorio dentro de la carpeta raiz de moodle:

```
/moodle/api_gest_alum/
```

Tu estructura de archivos debería quedar así:

```
/moodle/api_gest_alum/
│
├── student_summary.php
├── env_example
```

---

## 🔐 Configuración del Token

1. Renombra el archivo env_example a `.env` que está dentro del directorio `/api_gest_alum/` con el siguiente contenido:

ACCESS_TOKEN=mi_token_seguro
```

> Este token será requerido para cada llamada a la API como medida de autenticación.

---

## 🚀 Uso de la API

### Llamada con `curl`

```bash
curl "https://tusitio.com/moodle/api_gest_alum/index.php?token=mi_token_seguro&courseid=123"
```

- `token`: (obligatorio) debe coincidir con el token definido en `.env`
- `courseid`: (obligatorio) ID numérico del curso en Moodle

---

## 📤 Ejemplo de salida esperada (JSON)

```json
[
  {
    "userid": 25,
    "fullname": "Juan Pérez",
    "lastaccess": "Viernes, 7 de junio de 2024, 10:15",
    "completionpercentage": 85.71,
    "activitiescompleted": 6,
    "finalgrade": "85.00"
  },
  {
    "userid": 31,
    "fullname": "María García",
    "lastaccess": null,
    "completionpercentage": null,
    "activitiescompleted": null,
    "finalgrade": "-"
  }
]
```

> Si el usuario nunca accedió al curso o no tiene calificación/finalización registrada, los campos pueden devolver `null` o valores vacíos.

---
Desarrollado por:  
**Nancy Portillo**  
📅 Junio 2025  
✉️ portillo.nancy77@hotmail.com