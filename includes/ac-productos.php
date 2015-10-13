<?php
/* TODO:
 * */


session_start();

require 'PHPMailerAutoload.php';

// Token
$decoded_token = null;

if (file_exists('../../../includes/MyDBi.php')) {
    require_once '../../../includes/MyDBi.php';
    require_once '../../../includes/config.php';
} else {
    require_once 'MyDBi.php';
}

$data = file_get_contents("php://input");

// Decode data from js
$decoded = json_decode($data);


// Si la seguridad está activa
if ($jwt_enabled) {

    // Carga el jwt_helper
    if (file_exists('../../../jwt_helper.php')) {
        require_once '../../../jwt_helper.php';
    } else {
        require_once 'jwt_helper.php';
    }


    // Las funciones en el if no necesitan usuario logged
    if ($decoded != null &&
        ($decoded->function == 'getProductos' ||
            $decoded->function == 'getCategorias') ||
        $decoded->function == 'getCarritos'
    ) {
        $token = '';
    } else {
        checkSecurity();
    }

}


if ($decoded != null) {
    if ($decoded->function == 'createProducto') {
        createProducto($decoded->producto);
    } else if ($decoded->function == 'createCategoria') {
        createCategoria($decoded->categoria);
    } else if ($decoded->function == 'createCarrito') {
        createCarrito($decoded->carrito);
    } else if ($decoded->function == 'updateProducto') {
        updateProducto($decoded->producto);
    } else if ($decoded->function == 'updateCategoria') {
        updateCategoria($decoded->categoria);
    } else if ($decoded->function == 'updateCarrito') {
        updateCarrito($decoded->carrito);
    } else if ($decoded->function == 'removeProducto') {
        removeProducto($decoded->producto_id);
    } else if ($decoded->function == 'removeCategoria') {
        removeCategoria($decoded->categoria_id);
    } else if ($decoded->function == 'removeCarrito') {
        removeCarrito($decoded->carrito_id);
    }
} else {
    $function = $_GET["function"];
    if ($function == 'getProductos') {
        getProductos();
    } elseif ($function == 'getCategorias') {
        getCategorias();
    } elseif ($function == 'getCarritos') {
        getCarritos($_GET["usuario_id"]);
    }
}


/////// INSERT ////////
/**
 * @description Crea un producto, sus fotos, precios y le asigna las categorias
 * @param $product
 */
function createProducto($product)
{
    $db = new MysqliDb();
    $db->startTransaction();
    $product_decoded = checkProducto(json_decode($product));

    $data = array(
        'nombre' => $product_decoded->nombre,
        'descripcion' => $product_decoded->descripcion,
        'pto_repo' => $product_decoded->pto_repo,
        'sku' => $product_decoded->sku,
        'status' => $product_decoded->status,
        'vendidos' => $product_decoded->vendidos,
        'destacado' => $product_decoded->destacado,
        'en_slider' => $product_decoded->en_slider,
        'en_oferta' => $product_decoded->en_oferta,
        'producto_tipo' => $product_decoded->producto_tipo
    );

    $result = $db->insert('productos', $data);
    if ($result > -1) {

        foreach ($product_decoded->precios as $precio) {
            if (createPrecios($precio, $result, $db)) {
                $db->rollback();
                echo json_encode(-1);
                return;
            }
        }
        foreach ($product_decoded->categorias as $categoria) {
            if (createCategorias($categoria, $result, $db)) {
                $db->rollback();
                echo json_encode(-1);
                return;
            }
        }
        foreach ($product_decoded->fotos as $foto) {
            if (createFotos($foto, $result, $db)) {
                $db->rollback();
                echo json_encode(-1);
                return;
            }
        }

        // Solo para cuando es kit
        if ($product_decoded->producto_tipo == 2) {
            foreach ($product_decoded->productos_kit as $producto_kit) {
                if (createKits($producto_kit, $result, $db)) {
                    $db->rollback();
                    echo json_encode(-1);
                    return;
                }
            }
        }

        $db->commit();
        echo json_encode($result);
    } else {
        $db->rollback();
        echo json_encode(-1);
    }
}

/**
 * @description Crea un precio para un producto determinado
 * @param $precio
 * @param $producto_id
 * @param $db
 * @return bool
 */
function createPrecios($precio, $producto_id, $db)
{
    $data = array(
        'precio_tipo_id' => $precio->precio_tipo_id,
        'producto_id' => $producto_id,
        'precio' => $precio->precio
    );
    $pre = $db->insert('precios', $data);
    return ($pre > -1) ? true : false;
}

/**
 * @description Crea la relación entre un producto y una categoría
 * @param $categoria
 * @param $producto_id
 * @param $db
 * @return bool
 */
function createCategorias($categoria, $producto_id, $db)
{
    $data = array(
        'categoria_id' => $categoria->categoria_id,
        'producto_id' => $producto_id
    );

    $cat = $db->insert('productos_categorias', $data);
    return ($cat > -1) ? true : false;
}


/**
 * @description Crea una foto para un producto determinado, main == 1 significa que la foto es la principal
 * @param $foto
 * @param $producto_id
 * @param $db
 * @return bool
 */
function createFotos($foto, $producto_id, $db)
{
    $data = array(
        'main' => $foto->main,
        'nombre' => $foto->nombre,
        'producto_id' => $producto_id
    );

    $fot = $db->insert('productos_fotos', $data);
    return ($fot > -1) ? true : false;
}

/**
 * @description Crea la agrupación de productos que representan al kit
 * @param $kit
 * @param $producto_id
 * @param $db
 * @return bool
 */
function createKits($kit, $producto_id, $db)
{
    $data = array(
        'producto_cantidad' => $kit->producto_cantidad,
        'producto_id' => $producto_id
    );

    $kit = $db->insert('productos_kits', $data);
    return ($kit > -1) ? true : false;
}

/**
 * @description Crea una categoría, esta es la tabla paramétrica, la funcion createCategoriaS crea las relaciones
 * @param $categoria
 */
function createCategoria($categoria)
{
    $db = new MysqliDb();
    $db->startTransaction();
    $categoria_decoded = checkCategoria(json_decode($categoria));

    $data = array(
        'nombre' => $categoria_decoded->nombre,
        'parent_id' => $categoria_decoded->parent_id
    );

    $result = $db->insert('categorias', $data);
    if ($result > -1) {
        $db->commit();
        echo json_encode($result);
    } else {
        $db->rollback();
        echo json_encode(-1);
    }
}

/**
 * @description Crea un carrito y su detalle
 * @param $carrito
 */
function createCarrito($carrito)
{
    $db = new MysqliDb();
    $db->startTransaction();
    $carrito_decoded = checkCarrito(json_decode($carrito));

    $data = array(
        'status' => $carrito_decoded->status,
        'total' => $carrito_decoded->total,
        'fecha' => $carrito_decoded->fecha,
        'usuario_id' => $carrito_decoded->usuario_id
    );

    $result = $db->insert('carritos', $data);
    if ($result > -1) {

        foreach ($carrito_decoded->detalles as $detalle) {
            $data = array(
                'carrito_id' => $result,
                'producto_id' => $detalle->producto_id,
                'cantidad' => $detalle->cantidad,
                'en_oferta' => $detalle->en_oferta,
                'precio_unitario' => $detalle->precio_unitario
            );

            $pre = $db->insert('carrito_detalles', $data);
            if ($pre > -1) {
                $db->rollback();
                echo json_encode(-1);
                return;
            }
        }

        $db->commit();
        echo json_encode($result);
    } else {
        $db->rollback();
        echo json_encode(-1);
    }
}


/////// UPDATE ////////

/**
 * @description Modifica un producto, sus fotos, precios y le asigna las categorias
 * @param $product
 */
function updateProducto($product)
{
    $db = new MysqliDb();
    $db->startTransaction();
    $product_decoded = checkProducto(json_decode($product));

    $db->where('producto_id', $product_decoded->producto_id);
    $data = array(
        'nombre' => $product_decoded->nombre,
        'descripcion' => $product_decoded->descripcion,
        'pto_repo' => $product_decoded->pto_repo,
        'sku' => $product_decoded->sku,
        'status' => $product_decoded->status,
        'vendidos' => $product_decoded->vendidos,
        'destacado' => $product_decoded->destacado,
        'en_slider' => $product_decoded->en_slider,
        'en_oferta' => $product_decoded->en_oferta,
        'producto_tipo' => $product_decoded->producto_tipo
    );

    $result = $db->update('productos', $data);


    $db->where('producto_id', $product_decoded->producto_id);
    $db->delete('precios');
    $db->delete('productos_fotos');
    $db->delete('productos_categorias');
    $db->delete('productos_kits');

    if ($result > -1) {

        foreach ($product_decoded->precios as $precio) {
            if (createPrecios($precio, $result, $db)) {
                $db->rollback();
                echo json_encode(-1);
                return;
            }
        }
        foreach ($product_decoded->categorias as $categoria) {
            if (createCategorias($categoria, $result, $db)) {
                $db->rollback();
                echo json_encode(-1);
                return;
            }
        }
        foreach ($product_decoded->fotos as $foto) {

            if (createFotos($foto, $result, $db)) {
                $db->rollback();
                echo json_encode(-1);
                return;
            }
        }

        // Solo para cuando es kit
        if ($product_decoded->producto_tipo == 2) {
            foreach ($product_decoded->productos_kit as $producto_kit) {
                if (createKits($producto_kit, $result, $db)) {
                    $db->rollback();
                    echo json_encode(-1);
                    return;
                }
            }
        }

        $db->commit();
        echo json_encode($result);
    } else {
        $db->rollback();
        echo json_encode(-1);
    }
}

/**
 * @description Modifica una categoria
 * @param $categoria
 */
function updateCategoria($categoria)
{
    $db = new MysqliDb();
    $db->startTransaction();
    $categoria_decoded = checkCategorias(json_decode($categoria));
    $db->where('categoria_id', $categoria_decoded->categoria_id);
    $data = array(
        'nombre' => $categoria_decoded->nombre,
        'parent_id' => $categoria_decoded->parent_id
    );

    $result = $db->update('categorias', $data);
    if ($result) {
        $db->commit();
        echo json_encode($result);
    } else {
        $db->rollback();
        echo json_encode(-1);
    }
}


/**
 * @description Modifica un carrito
 * @param $carrito
 */
function updateCarrito($carrito)
{
    $db = new MysqliDb();
    $db->startTransaction();
    $carrito_decoded = checkCarrito(json_decode($carrito));
    $db->where('carrito_id', $carrito_decoded->carrito_id);
    $data = array(
        'status' => $carrito_decoded->status,
        'total' => $carrito_decoded->total,
        'fecha' => $carrito_decoded->fecha,
        'usuario_id' => $carrito_decoded->usuario_id
    );

    $result = $db->update('carritos', $data);
    if ($result) {
        $db->where('carrito_id', $carrito_decoded->producto_id);
        $result = $db->delete('carritos');
        foreach ($carrito_decoded->detalles as $detalle) {
            $data = array(
                'carrito_id' => $result,
                'producto_id' => $detalle->producto_id,
                'cantidad' => $detalle->cantidad,
                'en_oferta' => $detalle->en_oferta,
                'precio_unitario' => $detalle->precio_unitario
            );

            $pre = $db->insert('carrito_detalles', $data);
            if ($pre > -1) {
                $db->rollback();
                echo json_encode(-1);
                return;
            }
        }

        $db->commit();
        echo json_encode($result);
    } else {
        $db->rollback();
        echo json_encode(-1);
    }
}

/////// REMOVE ////////

/**
 * @description Elimina un producto, sus precios, sus fotos, sus categorias y sus kits
 * @param $producto_id
 */
function removeProducto($producto_id)
{
    $db = new MysqliDb();

    $db->where("producto_id", $producto_id);
    $results = $db->delete('productos');

    $db->where("producto_id", $producto_id);
    $db->delete('precios');
    $db->delete('productos_fotos');
    $db->delete('productos_categorias');
    $db->delete('productos_kits');

    if ($results) {

        echo json_encode(1);
    } else {
        echo json_encode(-1);

    }
}


/**
 * @description Elimina una categoria
 * @param $categoria_id
 */
function removeCategoria($categoria_id)
{
    $db = new MysqliDb();

    $db->where("categoria_id", $categoria_id);
    $results = $db->delete('categorias');

    if ($results) {

        echo json_encode(1);
    } else {
        echo json_encode(-1);

    }
}

/**
 * @description Elimina un carrito. Esta funcionalidad no tiene una función específica ya que un carrito se da de baja lógica unicamente, no física.
 * @param $carrito_id
 */
function removeCarrito($carrito_id)
{
    $db = new MysqliDb();

    $db->where("carrito_id", $carrito_id);
    $results = $db->delete('carritos');
    $db->where("carrito_id", $carrito_id);
    $results = $db->delete('carrito_detalles');

    if ($results) {

        echo json_encode(1);
    } else {
        echo json_encode(-1);

    }
}

/////// GET ////////

/**
 * @descr Obtiene los productos
 */
function getProductos()
{
    $db = new MysqliDb();
    $results = $db->get('productos');

    foreach ($results as $key => $row) {

        $db = new MysqliDb();
        $db->where('producto_id', $row['producto_id']);
        $db->join("categorias c", "p.categoria_id=c.categoria_id", "LEFT");
        $categorias = $db->get('productos_categorias p', null, 'c.categoria_id, c.nombre, c.parent_id');
        $results[$key]['categorias'] = $categorias;

        $db = new MysqliDb();
        $db->where('producto_id', $row['producto_id']);
        $precios = $db->get('precios');
        $results[$key]['precios'] = $precios;

        $db = new MysqliDb();
        $db->where('producto_id', $row['producto_id']);
        $precios = $db->get('fotos');
        $results[$key]['fotos'] = $precios;

        $db = new MysqliDb();
        $db->where('producto_id', $row['producto_id']);
        $db->join("productos c", "p.producto_id=c.producto_id", "LEFT");
        $kit = $db->get('productos_kits p', null, 'p.producto_kit_id, p.producto_id, p.producto_cantidad, c.producto.nombre');
        $results[$key]['productos_kit'] = $kit;


    }
    echo json_encode($results);
}


/**
 * @descr Obtiene las categorias
 */
function getCategorias()
{
    $db = new MysqliDb();
    $results = $db->get('categorias');

    echo json_encode($results);
}


/**
 * @descr Obtiene los productos. En caso de enviar un usuario_id != -1, se traerán todos los carritos. Solo usar esta opción cuando se aplica en la parte de administración
 */
function getCarritos($usuario_id)
{
    $db = new MysqliDb();
    if($usuario_id != -1){
        $db->where('usuario_id', $usuario_id);
    }
    $db->join("usuarios u", "u.usuario_id=c.usuario_id", "LEFT");
    $results = $db->get('carritos c', null, 'c.carrito_id, c.status, c.total, c.fecha, c.usuario_id, u.nombre, u.apellido');

    foreach ($results as $key => $row) {

        $db = new MysqliDb();
        $db->where('carrito_id', $row['carrito_id']);
        $db->join("productos p", "p.producto_id=c.producto_id", "LEFT");
        $categorias = $db->get('carrito_detalles c', null, 'c.carrito_detalle_id, c.carrito_id, c.producto_id, p.nombre, c.cantidad, c.en_oferta, c.precio_unitario');
        $results[$key]['categorias'] = $categorias;

    }
    echo json_encode($results);
}

/**
 * @description Verifica todos los campos de producto para que existan
 * @param $producto
 * @return mixed
 */
function checkProducto($producto)
{


    $producto->nombre = (!array_key_exists("nombre", $producto)) ? '' : $producto->nombre;
    $producto->descripcion = (!array_key_exists("descripcion", $producto)) ? '' : $producto->descripcion;
    $producto->pto_repo = (!array_key_exists("pto_repo", $producto)) ? 0 : $producto->pto_repo;
    $producto->sku = (!array_key_exists("sku", $producto)) ? '' : $producto->sku;
    $producto->status = (!array_key_exists("status", $producto)) ? 1 : $producto->status;
    $producto->vendidos = (!array_key_exists("vendidos", $producto)) ? 0 : $producto->vendidos;
    $producto->destacado = (!array_key_exists("destacado", $producto)) ? 0 : $producto->destacado;
    $producto->en_slider = (!array_key_exists("en_slider", $producto)) ? 0 : $producto->en_slider;
    $producto->en_oferta = (!array_key_exists("en_oferta", $producto)) ? 0 : $producto->en_oferta;
    $producto->producto_tipo = (!array_key_exists("producto_tipo", $producto)) ? 0 : $producto->producto_tipo;
    $producto->precios = (!array_key_exists("precios", $producto)) ? array() : checkPrecios($producto->precios);
    $producto->fotos = (!array_key_exists("fotos", $producto)) ? array() : checkFotos($producto->fotos);
    $producto->categorias = (!array_key_exists("categorias", $producto)) ? array() : checkCategorias($producto->categorias);

    // Ejecuta la verificación solo si es kit
    if ($producto->producto_tipo == 2) {
        $producto->productos_kit = (!array_key_exists("productos_kit", $producto)) ? array() : checkProductosKit($producto->productos_kit);
    }
    return $producto;
}

/**
 * @description Verifica todos los campos de Productos en un kit para que existan
 * @param $productos_kit
 * @return mixed
 */
function checkProductosKit($productos_kit)
{
    $productos_kit->producto_id = (!array_key_exists("producto_id", $productos_kit)) ? 0 : $productos_kit->producto_id;
    $productos_kit->producto_cantidad = (!array_key_exists("producto_cantidad", $productos_kit)) ? '' : $productos_kit->producto_cantidad;

    return $productos_kit;
}


/**
 * @description Verifica todos los campos de fotos para que existan
 * @param $fotos
 * @return mixed
 */
function checkFotos($fotos)
{
    $fotos->producto_id = (!array_key_exists("producto_id", $fotos)) ? 0 : $fotos->producto_id;
    $fotos->nombre = (!array_key_exists("nombre", $fotos)) ? '' : $fotos->nombre;
    $fotos->main = (!array_key_exists("main", $fotos)) ? 0 : $fotos->main;

    return $fotos;
}

/**
 * @description Verifica todos los campos de precios para que existan
 * @param $precios
 * @return mixed
 */
function checkPrecios($precios)
{
    $precios->producto_id = (!array_key_exists("producto_id", $precios)) ? 0 : $precios->producto_id;
    $precios->precio_tipo_id = (!array_key_exists("precio_tipo_id", $precios)) ? 0 : $precios->precio_tipo_id;
    $precios->precio = (!array_key_exists("precio", $precios)) ? 0 : $precios->precio;

    return $precios;
}

/**
 * @description Verifica todos los campos de categoria del producto para que existan
 * @param $categorias
 * @return mixed
 */
function checkCategorias($categorias)
{
    $categorias->producto_id = (!array_key_exists("producto_id", $categorias)) ? 0 : $categorias->producto_id;
    $categorias->categoria_id = (!array_key_exists("categoria_id", $categorias)) ? 0 : $categorias->categoria_id;

    return $categorias;
}


/**
 * @description Verifica todos los campos de categoria para que existan
 * @param $categoria
 * @return mixed
 */
function checkCategoria($categoria)
{
    $categoria->nombre = (!array_key_exists("nombre", $categoria)) ? '' : $categoria->nombre;
    $categoria->parent_id = (!array_key_exists("parent_id", $categoria)) ? -1 : $categoria->parent_id;

    return $categoria;
}

/**
 * @description Verifica todos los campos de carrito para que existan
 * @param $carrito
 * @return mixed
 */
function checkCarrito($carrito)
{
    $carrito->status = (!array_key_exists("status", $carrito)) ? 1 : $carrito->status;
    $carrito->total = (!array_key_exists("total", $carrito)) ? 0.0 : $carrito->total;
    $carrito->fecha = (!array_key_exists("fecha", $carrito)) ? '' : $carrito->fecha;
    $carrito->usuario_id = (!array_key_exists("usuario_id", $carrito)) ? -1 : $carrito->usuario_id;
    $carrito->carrito_detalle = (!array_key_exists("carrito_detalle", $carrito)) ? array() : checkCarritoDetalle($carrito->carrito_detalle);

    return $carrito;
}

/**
 * @description Verifica todos los campos de detalle del carrito para que existan
 * @param $detalle
 * @return mixed
 */
function checkCarritoDetalle($detalle)
{
    $detalle->carrito_id = (!array_key_exists("carrito_id", $detalle)) ? 0 : $detalle->carrito_id;
    $detalle->producto_id = (!array_key_exists("producto_id", $detalle)) ? 0 : $detalle->producto_id;
    $detalle->cantidad = (!array_key_exists("cantidad", $detalle)) ? 0 : $detalle->cantidad;
    $detalle->en_oferta = (!array_key_exists("en_oferta", $detalle)) ? 0 : $detalle->en_oferta;
    $detalle->precio_unitario = (!array_key_exists("precio_unitario", $detalle)) ? 0 : $detalle->precio_unitario;

    return $detalle;
}
