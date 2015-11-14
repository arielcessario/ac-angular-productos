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
    if (($decoded == null) && (($_GET["function"] != null) &&
            ($_GET["function"] == 'getProductos' ||
                $_GET["function"] == 'getCategorias' ||
                $_GET["function"] == 'getCarritos'))
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
    } else if ($decoded->function == 'createCarritoDetalle') {
        createCarritoDetalle($decoded->carrito_detalle);
    } else if ($decoded->function == 'updateProducto') {
        updateProducto($decoded->producto);
    } else if ($decoded->function == 'updateCategoria') {
        updateCategoria($decoded->categoria);
    } else if ($decoded->function == 'updateCarritoDetalle') {
        updateCarritoDetalle($decoded->carrito_detalle);
    } else if ($decoded->function == 'updateCarrito') {
        updateCarrito($decoded->carrito);
    } else if ($decoded->function == 'removeProducto') {
        removeProducto($decoded->producto_id);
    } else if ($decoded->function == 'removeCategoria') {
        removeCategoria($decoded->categoria_id);
    } else if ($decoded->function == 'removeCarritoDetalle') {
        removeCarritoDetalle($decoded->carrito_detalle_id);
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
            if (!createPrecios($precio, $result, $db)) {
                $db->rollback();
                echo json_encode(-1);
                return;
            }
        }
        foreach ($product_decoded->categorias as $categoria) {
            if (!createCategorias($categoria, $result, $db)) {
                $db->rollback();
                echo json_encode(-1);
                return;
            }
        }
        foreach ($product_decoded->fotos as $foto) {
            if (!createFotos($foto, $result, $db)) {
                $db->rollback();
                echo json_encode(-1);
                return;
            }
        }
        foreach ($product_decoded->proveedores as $proveedor) {
            if (!createProveedores($proveedor, $result, $db)) {
                $db->rollback();
                echo json_encode(-1);
                return;
            }
        }

        // Solo para cuando es kit
        if ($product_decoded->producto_tipo == 2) {
            foreach ($product_decoded->kits as $kit) {
                if (!createKits($kit, $result, $db)) {
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
 * @description Crea una relación entre producto y proveedor
 * @param $proveedor_id
 * @param $producto_id
 * @param $db
 * @return bool
 */
function createProveedores($proveedor, $producto_id, $db)
{
    $data = array(
        'proveedor_id' => $proveedor->proveedor_id,
        'producto_id' => $producto_id
    );

    $pro = $db->insert('productos_proveedores', $data);
    return ($pro > -1) ? true : false;
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
        'producto_id' => $kit->producto_id,
        'parent_id' => $producto_id
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
 * @description Crea un detalle de carrito
 * @param $carrito_detalle
 */
function createCarritoDetalle($carrito_detalle)
{
    $db = new MysqliDb();
    $db->startTransaction();
    $carrito_detalle_decoded = checkCarritoDetalle(json_decode($carrito_detalle));

    $data = array(
        'carrito_id' => $carrito_detalle_decoded->carrito_id,
        'producto_id' => $carrito_detalle_decoded->producto_id,
        'cantidad' => $carrito_detalle_decoded->cantidad,
        'en_oferta' => $carrito_detalle_decoded->en_oferta,
        'precio_unitario' => $carrito_detalle_decoded->precio_unitario
    );

    $result = $db->insert('carrito_detalles', $data);
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

//        foreach ($carrito_decoded->detalles as $detalle) {
//            $data = array(
//                'carrito_id' => $result,
//                'producto_id' => $detalle->producto_id,
//                'cantidad' => $detalle->cantidad,
//                'en_oferta' => $detalle->en_oferta,
//                'precio_unitario' => $detalle->precio_unitario
//            );
//
//            $pre = $db->insert('carrito_detalles', $data);
//            if ($pre > -1) {
//                $db->rollback();
//                echo json_encode(-1);
//                return;
//            }
//        }

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
    $db->where('producto_id', $product_decoded->producto_id);
    $db->delete('productos_fotos');
    $db->where('producto_id', $product_decoded->producto_id);
    $db->delete('productos_categorias');
    $db->where('producto_id', $product_decoded->producto_id);
    $db->delete('productos_kits');
    $db->where('producto_id', $product_decoded->producto_id);
    $db->delete('productos_proveedores');

    if ($result) {

        foreach ($product_decoded->precios as $precio) {
            if (!createPrecios($precio, $product_decoded->producto_id, $db)) {
                $db->rollback();
                echo json_encode(-1);
                return;
            }
        }
        foreach ($product_decoded->categorias as $categoria) {
            if (!createCategorias($categoria, $product_decoded->producto_id, $db)) {
                $db->rollback();
                echo json_encode(-1);
                return;
            }
        }
        foreach ($product_decoded->fotos as $foto) {

            if (!createFotos($foto, $product_decoded->producto_id, $db)) {
                $db->rollback();
                echo json_encode(-1);
                return;
            }
        }

        foreach ($product_decoded->proveedores as $proveedor) {
            if (!createProveedores($proveedor, $product_decoded->producto_id, $db)) {
                $db->rollback();
                echo json_encode(-1);
                return;
            }
        }

        // Solo para cuando es kit
        if ($product_decoded->producto_tipo == 2) {
            foreach ($product_decoded->kits as $producto_kit) {
                if (!createKits($producto_kit, $product_decoded->producto_id, $db)) {
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
    $categoria_decoded = checkCategoria(json_decode($categoria));
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
 * @description Modifica un detalle de carrito
 * @param $carrito_detalle
 */
function updateCarritoDetalle($carrito_detalle)
{
    $db = new MysqliDb();
    $db->startTransaction();
    $carrito_detalle_decoded = checkCarritoDetalle(json_decode($carrito_detalle));
    $db->where('carrito_detalle_id', $carrito_detalle_decoded->categoria_id);
    $data = array(
        'carrito_id' => $carrito_detalle_decoded->carrito_id,
        'producto_id' => $carrito_detalle_decoded->producto_id,
        'cantidad' => $carrito_detalle_decoded->cantidad,
        'en_oferta' => $carrito_detalle_decoded->en_oferta,
        'precio_unitario' => $carrito_detalle_decoded->precio_unitario
    );

    $result = $db->update('carrito_detalles', $data);
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
//        $db->where('carrito_id', $carrito_decoded->producto_id);
//        $result = $db->delete('carritos');
//        foreach ($carrito_decoded->detalles as $detalle) {
//            $data = array(
//                'carrito_id' => $result,
//                'producto_id' => $detalle->producto_id,
//                'cantidad' => $detalle->cantidad,
//                'en_oferta' => $detalle->en_oferta,
//                'precio_unitario' => $detalle->precio_unitario
//            );
//
//            $pre = $db->insert('carrito_detalles', $data);
//            if ($pre > -1) {
//                $db->rollback();
//                echo json_encode(-1);
//                return;
//            }
//        }

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
    $db->delete('productos_proveedores');

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
 * @description Elimina todas las relaciones entre producto y proveedor
 * @param $producto_id
 */
function removeProveedores($producto_id)
{
    $db = new MysqliDb();

    $db->where("producto_id", $producto_id);
    $results = $db->delete('productos_proveedores');

    if ($results) {

        echo json_encode(1);
    } else {
        echo json_encode(-1);

    }
}

/**
 * @description Elimina un detalle de carrito
 * @param $carrito_detalle_id
 */
function removeCarritoDetalle($carrito_detalle_id)
{
    $db = new MysqliDb();

    $db->where("carrito_detalle_id", $carrito_detalle_id);
    $results = $db->delete('carrito_detalles');

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

//    $results = $db->get('productos');
    $results = $db->rawQuery('SELECT
    p.producto_id,
    p.nombre nombreProducto,
    p.descripcion,
    p.pto_repo,
    p.sku,
    p.status,
    p.vendidos,
    p.destacado,
    p.producto_tipo,
    p.en_slider,
    p.en_oferta,
    c.categoria_id,
    c.nombre nombreCategoria,
    c.parent_id,
    ps.producto_kit_id,
    ps.producto_id productoKit,
    ps.producto_cantidad,
    pr.precio_id,
    pr.precio_tipo_id,
    pr.precio,
    f.producto_foto_id,
    f.main,
    f.nombre nombreFoto,
    u.usuario_id,
    u.nombre nombreUsuario,
    u.apellido
FROM
    productos p
        LEFT JOIN
    productos_categorias pc ON p.producto_id = pc.producto_id
        LEFT JOIN
    categorias c ON c.categoria_id = pc.categoria_id
        LEFT JOIN
    precios pr ON p.producto_id = pr.producto_id
        LEFT JOIN
    productos_fotos f ON p.producto_id = f.producto_id
        LEFT JOIN
    productos_kits ps ON p.producto_id = ps.parent_id
        LEFT JOIN
    productos_proveedores pro ON pro.producto_id = p.producto_id
        LEFT JOIN
    usuarios u ON u.usuario_id = pro.proveedor_id
GROUP BY p.producto_id , p.nombre , p.descripcion , p.pto_repo , p.sku , p.status , 
p.vendidos , p.destacado , p.producto_tipo , p.en_slider , p.en_oferta , c.categoria_id , 
c.nombre , c.parent_id , ps.producto_kit_id , ps.producto_id , ps.producto_cantidad , pr.precio_id , pr.precio_tipo_id , 
pr.precio, f.producto_foto_id, f.main, f.nombre, u.usuario_id, u.nombre, u.apellido
;');


    $final = array();
    foreach ($results as $row) {

        if (!isset($final[$row["producto_id"]])) {
            $final[$row["producto_id"]] = array(
                'producto_id' => $row["producto_id"],
                'nombre' => $row["nombreProducto"],
                'descripcion' => $row["descripcion"],
                'pto_repo' => $row["pto_repo"],
                'sku' => $row["sku"],
                'status' => $row["status"],
                'vendidos' => $row["vendidos"],
                'destacado' => $row["destacado"],
                'producto_tipo' => $row["producto_tipo"],
                'en_slider' => $row["en_slider"],
                'en_oferta' => $row["en_oferta"],
                'categorias' => array(),
                'precios' => array(),
                'fotos' => array(),
                'kits' => array(),
                'proveedores' => array()
            );
        }
        $have_cat = false;
        if ($row["categoria_id"] !== null) {

            if (sizeof($final[$row['producto_id']]['categorias']) > 0) {
                foreach ($final[$row['producto_id']]['categorias'] as $cat) {
                    if ($cat['categoria_id'] == $row["categoria_id"]) {
                        $have_cat = true;
                    }
                }
            } else {
                $final[$row['producto_id']]['categorias'][] = array(
                    'categoria_id' => $row['categoria_id'],
                    'nombre' => $row['nombreCategoria'],
                    'parent_id' => $row['parent_id']
                );

                $have_cat = true;
            }

            if (!$have_cat) {
                array_push($final[$row['producto_id']]['categorias'], array(
                    'categoria_id' => $row['categoria_id'],
                    'nombre' => $row['nombreCategoria'],
                    'parent_id' => $row['parent_id']
                ));
            }
        }


        $have_pre = false;
        if ($row["precio_id"] !== null) {

            if (sizeof($final[$row['producto_id']]['precios']) > 0) {
                foreach ($final[$row['producto_id']]['precios'] as $cat) {
                    if ($cat['precio_id'] == $row["precio_id"]) {
                        $have_pre = true;
                    }
                }
            } else {
                $final[$row['producto_id']]['precios'][] = array(
                    'precio_id' => $row['precio_id'],
                    'precio_tipo_id' => $row['precio_tipo_id'],
                    'precio' => $row['precio']
                );

                $have_pre = true;
            }

            if (!$have_pre) {
                array_push($final[$row['producto_id']]['precios'], array(
                    'precio_id' => $row['precio_id'],
                    'precio_tipo_id' => $row['precio_tipo_id'],
                    'precio' => $row['precio']
                ));
            }
        }


        $have_fot = false;
        if ($row["producto_foto_id"] !== null) {

            if (sizeof($final[$row['producto_id']]['fotos']) > 0) {
                foreach ($final[$row['producto_id']]['fotos'] as $cat) {
                    if ($cat['producto_foto_id'] == $row["producto_foto_id"]) {
                        $have_fot = true;
                    }
                }
            } else {
                $final[$row['producto_id']]['fotos'][] = array(
                    'producto_foto_id' => $row['producto_foto_id'],
                    'nombre' => $row['nombreFoto'],
                    'main' => $row['main']
                );

                $have_fot = true;
            }

            if (!$have_fot) {
                array_push($final[$row['producto_id']]['fotos'], array(
                    'producto_foto_id' => $row['producto_foto_id'],
                    'nombre' => $row['nombreFoto'],
                    'main' => $row['main']
                ));
            }
        }

        $have_kit = false;
        if ($row["producto_kit_id"] !== null) {

            if (sizeof($final[$row['producto_id']]['kits']) > 0) {
                foreach ($final[$row['producto_id']]['kits'] as $cat) {
                    if ($cat['producto_kit_id'] == $row["producto_kit_id"]) {
                        $have_kit = true;
                    }
                }
            } else {
                $final[$row['producto_id']]['kits'][] = array(
                    'producto_kit_id' => $row['producto_kit_id'],
                    'producto_id' => $row['productoKit'],
                    'producto_cantidad' => $row['producto_cantidad']
                );

                $have_kit = true;
            }

            if (!$have_kit) {
                array_push($final[$row['producto_id']]['kits'], array(
                    'producto_kit_id' => $row['producto_kit_id'],
                    'producto_id' => $row['productoKit'],
                    'producto_cantidad' => $row['producto_cantidad']
                ));
            }
        }


        $have_pro = false;
        if ($row["usuario_id"] !== null) {

            if (sizeof($final[$row['producto_id']]['proveedores']) > 0) {
                foreach ($final[$row['producto_id']]['proveedores'] as $cat) {
                    if ($cat['usuario_id'] == $row["usuario_id"]) {
                        $have_pro = true;
                    }
                }
            } else {
                $final[$row['producto_id']]['proveedores'][] = array(
                    'usuario_id' => $row['usuario_id'],
                    'nombre' => $row['nombreUsuario'],
                    'apellido' => $row['apellido']
                );

                $have_pro = true;
            }

            if (!$have_pro) {
                array_push($final[$row['producto_id']]['proveedores'], array(
                    'usuario_id' => $row['usuario_id'],
                    'nombre' => $row['nombreUsuario'],
                    'apellido' => $row['apellido']
                ));
            }
        }
    }
    echo json_encode(array_values($final));
}


/**
 * @descr Obtiene las categorias
 */
function getCategorias()
{
    $db = new MysqliDb();
    $results = $db->rawQuery('SELECT c.*, (select count(producto_id) from productos_categorias p where p.categoria_id= c.categoria_id) total, d.nombre nombrePadre FROM categorias c LEFT JOIN categorias d ON c.parent_id = d.categoria_id;');

    echo json_encode($results);
}


/**
 * @descr Obtiene los productos. En caso de enviar un usuario_id != -1, se traerán todos los carritos. Solo usar esta opción cuando se aplica en la parte de administración
 */
function getCarritos($usuario_id)
{
    $db = new MysqliDb();
    if ($usuario_id != -1) {
        $db->where('c.usuario_id', $usuario_id);
    }
    $db->join("usuarios u", "u.usuario_id=c.usuario_id", "LEFT");
    $results = $db->get('carritos c', null, 'c.carrito_id, c.status, c.total, c.fecha, c.usuario_id, u.nombre, u.apellido');

    foreach ($results as $key => $row) {
        $db = new MysqliDb();
        $db->where('carrito_id', $row['carrito_id']);
        $db->join("productos p", "p.producto_id=c.producto_id", "LEFT");
        $productos = $db->get('carrito_detalles c', null, 'c.carrito_detalle_id, c.carrito_id, c.producto_id, p.nombre, c.cantidad, c.en_oferta, c.precio_unitario');
        $results[$key]['productos'] = $productos;
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
    $producto->proveedores = (!array_key_exists("proveedores", $producto)) ? array() : checkProductosProveedores($producto->proveedores);

    // Ejecuta la verificación solo si es kit
    if ($producto->producto_tipo == 2) {
        $producto->kits = (!array_key_exists("kits", $producto)) ? array() : checkProductosKit($producto->kits);
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
    $productos_kit->parent_id = (!array_key_exists("parent_id", $productos_kit)) ? 0 : $productos_kit->parent_id;
    $productos_kit->producto_cantidad = (!array_key_exists("producto_cantidad", $productos_kit)) ? '' : $productos_kit->producto_cantidad;

    return $productos_kit;
}

/**
 * @description Verifica todos los campos de Proveedores y Productos existan
 * @param $productos_proveedores
 * @return mixed
 */
function checkProductosProveedores($productos_proveedores)
{
    foreach($productos_proveedores as $producto_proveedor) {
        $producto_proveedor->producto_id = (!array_key_exists("producto_id", $producto_proveedor)) ? 0 : $producto_proveedor->producto_id;
        $producto_proveedor->proveedor_id = (!array_key_exists("proveedor_id", $producto_proveedor)) ? '' : $producto_proveedor->proveedor_id;
    }
    return $productos_proveedores;
}


/**
 * @description Verifica todos los campos de fotos para que existan
 * @param $fotos
 * @return mixed
 */
function checkFotos($fotos)
{
    foreach ($fotos as $foto) {
        $foto->producto_id = (!array_key_exists("producto_id", $foto)) ? 0 : $foto->producto_id;
        $foto->nombre = (!array_key_exists("nombre", $foto)) ? '' : $foto->nombre;
        $foto->main = (!array_key_exists("main", $foto)) ? 0 : $foto->main;
    }
    return $fotos;
}

/**
 * @description Verifica todos los campos de precios para que existan
 * @param $precios
 * @return mixed
 */
function checkPrecios($precios)
{
    foreach ($precios as $precio) {
        $precio->producto_id = (!array_key_exists("producto_id", $precio)) ? 0 : $precio->producto_id;
        $precio->precio_tipo_id = (!array_key_exists("precio_tipo_id", $precio)) ? 0 : $precio->precio_tipo_id;
        $precio->precio = (!array_key_exists("precio", $precio)) ? 0 : $precio->precio;
    }

    return $precios;
}

/**
 * @description Verifica todos los campos de categoria del producto para que existan
 * @param $categorias
 * @return mixed
 */
function checkCategorias($categorias)
{
    foreach ($categorias as $categoria) {
        $categoria->producto_id = (!array_key_exists("producto_id", $categoria)) ? 0 : $categoria->producto_id;
        $categoria->categoria_id = (!array_key_exists("categoria_id", $categoria)) ? 0 : $categoria->categoria_id;
    }

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
    $now = new DateTime(null, new DateTimeZone('America/Argentina/Buenos_Aires'));

    $carrito->status = (!array_key_exists("status", $carrito)) ? 1 : $carrito->status;
    $carrito->total = (!array_key_exists("total", $carrito)) ? 0.0 : $carrito->total;
    $carrito->fecha = (!array_key_exists("fecha", $carrito)) ? $now->format('Y-m-d H:i:s') : $carrito->fecha;
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
