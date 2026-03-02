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
            <h3 class="text-xl font-bold text-baby-text mb-4"><i class="fa-solid fa-list text-baby-blue"></i> Productos Actuales</h3>
            <div id="admin-product-list" class="space-y-3 max-h-[500px] overflow-y-auto pr-2"></div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-3xl shadow-lg border-2 border-baby-blue-light">
        <h3 class="text-xl font-bold text-baby-text mb-4"><i class="fa-solid fa-box text-baby-pink"></i> Pedidos</h3>
        <div class="grid lg:grid-cols-4 gap-4" id="orders-kanban">
            <div class="bg-baby-cream rounded-2xl p-4 border border-baby-blue-light">
                <h4 class="font-bold mb-3">Nuevo</h4>
                <div id="orders-col-nuevo" class="space-y-3"></div>
            </div>
            <div class="bg-baby-cream rounded-2xl p-4 border border-baby-blue-light">
                <h4 class="font-bold mb-3">En preparación</h4>
                <div id="orders-col-en_preparacion" class="space-y-3"></div>
            </div>
            <div class="bg-baby-cream rounded-2xl p-4 border border-baby-blue-light">
                <h4 class="font-bold mb-3">En viaje</h4>
                <div id="orders-col-en_viaje" class="space-y-3"></div>
            </div>
            <div class="bg-baby-cream rounded-2xl p-4 border border-baby-blue-light">
                <h4 class="font-bold mb-3">Entregado</h4>
                <div id="orders-col-entregado" class="space-y-3"></div>
            </div>
        </div>
    </div>
</div>
