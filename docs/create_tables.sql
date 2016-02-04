
# PRODUCTOS
CREATE TABLE productos (
  producto_id int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(45) DEFAULT NULL,
  descripcion varchar(2000) DEFAULT NULL,
  pto_repo int(11) DEFAULT NULL,
  sku varchar(45) DEFAULT NULL,
  status int(11) DEFAULT NULL COMMENT '0 - Baja, 1 - Activo, 2 - XXX',
  vendidos int(11) DEFAULT NULL COMMENT 'Este campo ayuda a la b�squeda de los mas vendidos en caso de no tener control de stock, tambi�n ayuda para no recorrer toda la base cuando est� integrado con stock',
  destacado int(11) DEFAULT '0' COMMENT '0 - No, 1 - Si',
  en_slider int(1) DEFAULT '0' COMMENT '0 - No, 1 - Si',
  en_oferta int(1) DEFAULT '0' COMMENT '0 - No, 1 - Si',
  producto_tipo int(11) DEFAULT NULL COMMENT '0 - producto / 1 - insumo / 2 - kit / 3 - Servicio',
  iva decimal(8,2) DEFAULT 0.0,
  PRIMARY KEY (producto_id),
  KEY SKU (sku)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

# KITS PRODUCTOS - Un mismo kit puede tener muchos productos
CREATE TABLE productos_kits (
  producto_kit_id int(11) NOT NULL AUTO_INCREMENT COMMENT 'Este ID es el ID del producto en la tabla de productos que es tipo kit',
  producto_id int(11) NOT NULL,
  parent_id int(11) NOT NULL DEFAULT 0,
  producto_cantidad int(11) NOT NULL COMMENT 'Cantidad de unidades del producto en el kit u oferta, esto descuenta desde el stock',
  PRIMARY KEY (producto_kit_id),
  KEY KIT_PROD_IDX (producto_id)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

# CATEGORIAS -
CREATE TABLE categorias (
  categoria_id int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(100) NOT NULL,
  parent_id int(11) DEFAULT '-1',
  PRIMARY KEY (categoria_id)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


# CATEGORIAS DE PRODUCTOS -
CREATE TABLE productos_categorias (
  producto_categoria_id int(11) NOT NULL AUTO_INCREMENT,
  producto_id int(11) DEFAULT NULL,
  categoria_id int(11) DEFAULT 0,
  PRIMARY KEY (producto_categoria_id)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


# FOTOS DE PRODUCTOS -
CREATE TABLE productos_fotos (
  producto_foto_id int(11) NOT NULL AUTO_INCREMENT,
  producto_id int(11) DEFAULT NULL,
  main int(11) DEFAULT 0 COMMENT '0 - No Main 1- Foto principal',
  nombre varchar(45) DEFAULT NULL,
  PRIMARY KEY (producto_foto_id),
  KEY FOTOS_PROD_IDX (producto_id)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

# PRECIOS DE PRODUCTOS
CREATE TABLE precios (
  precio_id int(11) NOT NULL AUTO_INCREMENT,
  precio_tipo_id int(11) DEFAULT NULL COMMENT '0 - Minorista / 1 - Mayorista / 2 - Oferta / 3 - Kit',
  producto_id int(11) DEFAULT NULL,
  precio decimal(8,2) DEFAULT NULL,
  PRIMARY KEY (precio_id),
  KEY PRECIO_PROD_IDX (producto_id)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

# CARRITO - Encabezado de la compra
CREATE TABLE carritos (
  carrito_id int(11) NOT NULL AUTO_INCREMENT,
  status int(11) NOT NULL COMMENT '0 - Iniciado, 1 - Pedido, 2 - Confirmado, 3 - Entregado, 4 - Cancelado',
  total decimal(8,2) NOT NULL,
  fecha timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_id int(11) NOT NULL DEFAULT '-1',
  origen int(11) NOT NULL DEFAULT '-1',
  destino int(11) NOT NULL DEFAULT '-1',
  PRIMARY KEY (carrito_id)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

# DETALLE DE CARRITO - Detalle de la compra realizada
CREATE TABLE carrito_detalles (
  carrito_detalle_id int(11) NOT NULL AUTO_INCREMENT,
  carrito_id int(11) NOT NULL,
  producto_id int(11) NOT NULL DEFAULT '-1',
  cantidad int(11) NOT NULL,
  en_oferta int(1) DEFAULT '0' COMMENT '0 - No, 1 - Si',
  precio_unitario decimal(8,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (carrito_detalle_id)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;


# PROVEEDORES POR PRODUCTO - Relaci�n entre productos y proveedores
CREATE TABLE productos_proveedores (
  producto_id int(11) NOT NULL,
  proveedor_id int(11) NOT NULL,
  PRIMARY KEY (producto_id,proveedor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
