(function () {
    'use strict';

    var scripts = document.getElementsByTagName("script");
    var currentScriptPath = scripts[scripts.length - 1].src;

    angular.module('acProductos', ['ngCookies'])
        .factory('ProductService', ProductService)
        .service('ProductVars', ProductVars)
        .factory('CategoryService', CategoryService)
        .service('CategoryVars', CategoryVars)
        .factory('CartService', CartService)
        .service('CartVars', CartVars)
    ;


    ProductService.$inject = ['$http', '$cookieStore', 'store', 'ProductVars', '$cacheFactory'];
    function ProductService($http, $cookieStore, store, ProductVars, $cacheFactory) {
        //Variables
        var service = {};

        var url = currentScriptPath.replace('ac-productos.js', '/includes/ac-productos.php');

        //Function declarations
        service.get = get;
        //service.getCategorias = getCategorias;
        //service.getCarritos = getCarritos;
        //
        //service.getProductosByParams = getProductosByParams;
        //service.getCategoriasByParams = getCategoriasByParams;
        //service.getCarritosByParams = getCarritosByParams;
        //
        //service.createProducto = createProducto;
        //service.createCategoria = createCategoria;
        //service.createCarrito = createCarrito;
        //
        //service.updateProducto = updateProducto;
        //service.updateCategoria = updateCategoria;
        //service.updateCarrito = updateCarrito;
        //
        //service.removeProducto = removeProducto;
        //service.removeCategoria = removeCategoria;
        //service.removeCarrito = removeCarrito;

        service.goToPagina = goToPagina;
        service.next = next;
        service.prev = prev;

        return service;

        //Functions
        /**
         * @description Obtiene todos los productos
         * @param callback
         * @returns {*}
         */
        function get(callback) {
            var urlGet = url + '?function=getProductos';
            var $httpDefaultCache = $cacheFactory.get('$http');
            var cachedData = [];


            // Verifica si existe el cache de productos
            if ($httpDefaultCache.get(urlGet) != undefined) {
                if (ProductVars.clearCache) {
                    $httpDefaultCache.remove(urlGet);
                }
                else {
                    cachedData = $httpDefaultCache.get(urlGet);
                    callback(cachedData);
                    return;
                }
            }


            return $http.get(urlGet, {cache: true})
                .success(function (data) {
                    $httpDefaultCache.put(urlGet, data);
                    ProductVars.clearCache = false;
                    ProductVars.paginas = (data.length % ProductVars.paginacion == 0) ? parseInt(data.length / ProductVars.paginacion) : parseInt(data.length / ProductVars.paginacion) + 1;
                    callback(data);
                })
                .error(function (data) {
                    callback(data);
                    ProductVars.clearCache = false;
                })
        }


        /**
         * @description Retorna la lista filtrada de productos
         * @param param -> String, separado por comas (,) que contiene la lista de parámetros de búsqueda, por ej: nombre, sku
         * @param value
         * @param callback
         */
        function getByParams(param, value, callback) {
            get(function (data) {
                var parametros = param.split(',');
                var response = data.filter(function (elem, index, array) {

                    var columns = Object.keys(elem);

                    var respuesta = [];
                    for(var i = 0; i<columns.length; i++){
                        for(var x = 0; x<parametros.length; x++){
                            if(columns[i] == parametros[x]){
                                if(elem[i]== value){
                                    respuesta.push(elem);
                                }
                            }
                        }
                    }

                    return respuesta;
                });

                callback(response);
            })
        }


        /** @name: remove
         * @param usuario_id, callback
         * @description: Elimina el usuario seleccionado.
         */
        function remove(usuario_id, callback) {
            return $http.post(url,
                {function: 'remove', 'usuario_id': usuario_id})
                .success(function (data) {
                    //console.log(data);
                    if (data !== 'false') {

                        callback(data);
                    }
                })
                .error(function (data) {
                    callback(data);
                })
        }


        /** @name: getById
         * @param usuario_id, callback
         * @description: Retorna el usuario que tenga el id enviado.
         */
        function getById(id, callback) {
            get(function (data) {
                var response = data.filter(function (elem, index, array) {
                    return elem.usuario_id == id;
                })[0];
                callback(response);
            });
        }

        /**
         * todo: Hay que definir si vale la pena
         */
        function checkLastLogin() {

        }


        /** @name: productExist
         * @param mail
         * @description: Verifica que el mail no exista en la base.
         */
        function productExist(mail, callback) {
            return $http.post(url,
                {'function': 'existeUsuario', 'mail': mail})
                .success(function (data) {
                    callback(data);
                })
                .error(function (data) {
                })
        }

        /**@name: logout
         @description: Logout
         */
        function logout() {
            store.remove('jwt');
            $cookieStore.remove('product');
            ProductVars.clearCache = true;
        }


        /**
         *
         * @description: realiza login
         * @param mail
         * @param password
         * @param sucursal_id
         * @param callback
         * @returns {*}
         */
        function login(mail, password, sucursal_id, callback) {
            return $http.post(url,
                {'function': 'login', 'mail': mail, 'password': password, 'sucursal_id': sucursal_id})
                .success(function (data) {
                    if (data != -1) {
                        $cookieStore.put('product', data.product);
                        store.set('jwt', data.token);
                    }
                    callback(data);
                })
                .error(function (data) {
                    callback(data);
                })
        }

        /**
         * @description: Crea un usuario.
         * @param usuario
         * @param callback
         * @returns {*}
         */
        function create(usuario, callback) {

            return $http.post(url,
                {
                    'function': 'create',
                    'product': JSON.stringify(usuario)
                })
                .success(function (data) {
                    ProductVars.clearCache = true;
                    callback(data);
                })
                .error(function (data) {
                    ProductVars.clearCache = true;
                    callback(data);
                });
        }

        /** @name: getLogged
         * @description: Retorna si existe una cookie de usuario.
         */
        function getLogged() {
            var globals = $cookieStore.get('product');

            if (globals !== undefined && globals.product !== undefined) {
                return globals;
            } else {
                return false;
            }
        }

        /** @name: setLogged
         * @param product
         * @description: Setea al usuario en una cookie. No está agregado al login ya que no en todos los casos se necesita cookie.
         */
        function setLogged(product) {
            $cookieStore.set('product', product);
        }

        function changePassword(usuario_id, pass_old, pass_new, callback) {

            return $http.post(url,
                {
                    function: 'changePassword',
                    usuario_id: usuario_id,
                    pass_old: pass_old,
                    pass_new: pass_new
                })
                .success(function (data) {
                    ProductVars.clearCache = true;
                    callback(data);
                })
                .error(function (data) {
                    callback(data);
                })
        }

        /** @name: getByEmail
         * @param mail
         * @param callback
         * @description: Obtiene al usuario filtrado por mail del cache completo.
         */
        function getByEmail(mail, callback) {
            get(function (data) {
                var response = data.filter(function (elem, index, array) {
                    return elem.mail == mail;
                })[0];
                callback(response);
            });
        }

        /** @name: update
         * @param usuario
         * @param callback
         * @description: Realiza update al usuario.
         */
        function update(usuario, callback) {
            return $http.post(url,
                {
                    'function': 'update',
                    'product': JSON.stringify(usuario)
                })
                .success(function (data) {
                    ProductVars.clearCache = true;
                    callback(data);
                })
                .error(function (data) {
                    callback(data);
                });
        }


        /** @name: forgotPassword
         * @param email
         * @description: Genera y reenvia el pass al usuario.
         */
        function forgotPassword(email, callback) {

            return $http.post(url,
                {
                    'function': 'forgotPassword',
                    'email': email
                })
                .success(function (data) {
                    callback(data);
                })
                .error(function (data) {
                    callback(data);
                });
        }

        /**
         * Para el uso de la páginación, definir en el controlador las siguientes variables:
         *
         vm.start = 0;
         vm.pagina = ProductVars.pagina;
         ProductVars.paginacion = 5; Cantidad de registros por página
         vm.end = ProductVars.paginacion;


         En el HTML, en el ng-repeat agregar el siguiente filtro: limitTo:appCtrl.end:appCtrl.start;

         Agregar un botón de next:
         <button ng-click="appCtrl.next()">next</button>

         Agregar un botón de prev:
         <button ng-click="appCtrl.prev()">prev</button>

         Agregar un input para la página:
         <input type="text" ng-keyup="appCtrl.goToPagina()" ng-model="appCtrl.pagina">

         */


        /**
         * @description: Ir a página
         * @param pagina
         * @returns {*}
         * uso: agregar un método
         vm.goToPagina = function () {
                vm.start= ProductService.goToPagina(vm.pagina).start;
            };
         */
        function goToPagina(pagina) {

            if (isNaN(pagina) || pagina < 1) {
                ProductVars.pagina = 1;
                return ProductVars;
            }

            if (pagina > ProductVars.paginas) {
                ProductVars.pagina = ProductVars.paginas;
                return ProductVars;
            }

            ProductVars.pagina = pagina - 1;
            ProductVars.start = ProductVars.pagina * ProductVars.paginacion;
            return ProductVars;

        }

        /**
         * @name next
         * @description Ir a próxima página
         * @returns {*}
         * uso agregar un metodo
         vm.next = function () {
                vm.start = ProductService.next().start;
                vm.pagina = ProductVars.pagina;
            };
         */
        function next() {

            if (ProductVars.pagina + 1 > ProductVars.paginas) {
                return ProductVars;
            }
            ProductVars.start = (ProductVars.pagina * ProductVars.paginacion);
            ProductVars.pagina = ProductVars.pagina + 1;
            //ProductVars.end = ProductVars.start + ProductVars.paginacion;
            return ProductVars;
        }

        /**
         * @name previous
         * @description Ir a página anterior
         * @returns {*}
         * uso, agregar un método
         vm.prev = function () {
                vm.start= ProductService.prev().start;
                vm.pagina = ProductVars.pagina;
            };
         */
        function prev() {


            if (ProductVars.pagina - 2 < 0) {
                return ProductVars;
            }

            //ProductVars.end = ProductVars.start;
            ProductVars.start = (ProductVars.pagina - 2 ) * ProductVars.paginacion;
            ProductVars.pagina = ProductVars.pagina - 1;
            return ProductVars;
        }


    }


    ProductVars.$inject = [];
    /**
     *
     * @constructor
     */
    function ProductVars() {
        // Cantidad de páginas total del recordset
        this.paginas = 1;
        // Página seleccionada
        this.pagina = 1;
        // Cantidad de registros por página
        this.paginacion = 10;
        // Registro inicial, no es página, es el registro
        this.start = 0;


        // Indica si se debe limpiar el caché la próxima vez que se solicite un get
        this.clearCache = true;

        // Path al login
        this.loginPath = '/login';
    }

})();