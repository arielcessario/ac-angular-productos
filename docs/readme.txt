TODO: Hacer un utils para todos con las funciones mas usadas:


Dependencies:
ac-angular-usuarios
ac-angular-utils


Comentarios:
Los kits son productos, y son contenedores de otros productos, es decir, el producto_id = 5, tipo = kit, contiene los productos 1, 2, 3.
El producto_id = 5, en la tabla productos_kits es producto_kit_id = 5


Notas:
En el raíz del sitio se debe crear una carpeta llamada includes y dentro de la misma crear un archivo llamado config.php

Dentro del mismo incluir las siguientes lineas

// JWT Secret Key
$secret = 'uiglp';
// JWT AUD
$serverName = 'serverName';
// false local / true production
$jwt_enabled = false;