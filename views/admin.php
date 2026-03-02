<div id="section-admin" class="tab-content container mx-auto px-4 py-10 hidden space-y-8">
    <h2 class="text-3xl font-bold text-center text-baby-text flex items-center justify-center gap-3">
        <i class="fa-solid fa-user-gear text-baby-pink"></i>
        Panel de Administración
    </h2>

    <div class="grid md:grid-cols-3 gap-8">
        <div class="bg-white p-6 rounded-3xl shadow-lg border-2 border-baby-blue-light space-y-4">
            <h3 class="text-xl font-bold text-baby-text"><i class="fa-solid fa-plus-circle text-baby-green"></i> Agregar Nuevo Producto</h3>
            <input type="text" id="admin-prod-name" placeholder="Nombre del producto" class="w-full p-3 border rounded-full bg-baby-cream focus:ring-2 focus:ring-baby-pink outline-none">
            <input type="number" id="admin-prod-price" placeholder="Precio (ej: 12500)" class="w-full p-3 border rounded-full bg-baby-cream focus:ring-2 focus:ring-baby-pink outline-none">
            <input type="text" id="admin-prod-img" placeholder="URL de la imagen" class="w-full p-3 border rounded-full bg-baby-cream focus:ring-2 focus:ring-baby-pink outline-none">
            <button onclick="adminAddProduct()" class="w-full bg-baby-pink text-white py-3 rounded-full font-bold hover:bg-pink-400 transition flex items-center justify-center gap-2">
                <i class="fa-solid fa-save"></i> Guardar Producto
            </button>
        </div>

        <div class="md:col-span-2 bg-white p-6 rounded-3xl shadow-lg border-2 border-baby-blue-light">
            <h3 class="text-xl font-bold text-baby-text mb-4"><i class="fa-solid fa-list text-baby-blue"></i> Gestión de Productos</h3>
            <div class="grid md:grid-cols-2 gap-4" id="products-kanban">
                <div class="bg-baby-cream rounded-2xl p-4 border border-baby-blue-light">
                    <h4 class="font-bold mb-3">Activos</h4>
                    <div id="admin-product-list-active" data-product-status="1" class="space-y-3 min-h-[180px]"></div>
                </div>
                <div class="bg-baby-cream rounded-2xl p-4 border border-baby-blue-light">
                    <h4 class="font-bold mb-3">Archivados</h4>
                    <div id="admin-product-list-archived" data-product-status="0" class="space-y-3 min-h-[180px]"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-3xl shadow-lg border-2 border-baby-blue-light">
        <h3 class="text-xl font-bold text-baby-text mb-4"><i class="fa-solid fa-box text-baby-pink"></i> Pedidos</h3>
        <div class="grid lg:grid-cols-5 gap-4" id="orders-kanban">
            <div class="bg-baby-cream rounded-2xl p-4 border border-baby-blue-light">
                <h4 class="font-bold mb-3">Nuevo</h4>
                <div id="orders-col-nuevo" data-status="nuevo" class="space-y-3 min-h-[220px]"></div>
            </div>
            <div class="bg-baby-cream rounded-2xl p-4 border border-baby-blue-light">
                <h4 class="font-bold mb-3">En preparación</h4>
                <div id="orders-col-en_preparacion" data-status="en_preparacion" class="space-y-3 min-h-[220px]"></div>
            </div>
            <div class="bg-baby-cream rounded-2xl p-4 border border-baby-blue-light">
                <h4 class="font-bold mb-3">En viaje</h4>
                <div id="orders-col-en_viaje" data-status="en_viaje" class="space-y-3 min-h-[220px]"></div>
            </div>
            <div class="bg-baby-cream rounded-2xl p-4 border border-baby-blue-light">
                <h4 class="font-bold mb-3">Entregado</h4>
                <div id="orders-col-entregado" data-status="entregado" class="space-y-3 min-h-[220px]"></div>
            </div>

            <div class="bg-baby-cream rounded-2xl p-4 border border-baby-blue-light">
                <h4 class="font-bold mb-3">Archivados</h4>
                <div id="orders-col-archived" data-status="archived" class="space-y-3 min-h-[220px]"></div>
            </div>
        </div>
    </div>
</div>
