<div id="section-admin" class="tab-content container mx-auto px-4 py-10 hidden space-y-8">
    <h2 class="text-3xl font-bold text-center text-baby-text flex items-center justify-center gap-3">
        <i class="fa-solid fa-user-gear text-baby-pink"></i>
        Panel de Administración
    </h2>

    <div id="admin-products-section" class="grid md:grid-cols-3 gap-8">
        <div class="bg-white p-6 rounded-3xl shadow-lg border-2 border-baby-blue-light space-y-4">
            <h3 class="text-xl font-bold text-baby-text"><i class="fa-solid fa-plus-circle text-baby-green"></i> Agregar Nuevo Producto</h3>
            <input type="text" id="admin-prod-name" placeholder="Nombre del producto" class="w-full p-3 border rounded-full bg-baby-cream focus:ring-2 focus:ring-baby-pink outline-none">
            <input type="number" id="admin-prod-price" placeholder="Precio (ej: 12500)" class="w-full p-3 border rounded-full bg-baby-cream focus:ring-2 focus:ring-baby-pink outline-none">
            <select id="admin-prod-category" class="w-full p-3 border rounded-full bg-baby-cream focus:ring-2 focus:ring-baby-pink outline-none"></select>
            <div class="flex gap-2">
                <input type="text" id="admin-new-category-name" placeholder="Nueva categoría" class="flex-1 p-3 border rounded-full bg-baby-cream focus:ring-2 focus:ring-baby-pink outline-none">
                <button onclick="adminCreateCategory()" class="px-4 py-2 bg-baby-blue rounded-full font-bold">Agregar</button>
            </div>
            <select id="admin-prod-image-source" class="w-full p-3 border rounded-full bg-baby-cream focus:ring-2 focus:ring-baby-pink outline-none">
                <option value="url" selected>Usar URL</option>
                <option value="upload">Subir archivo</option>
            </select>
            <input type="text" id="admin-prod-img-url" placeholder="URL de la imagen" class="w-full p-3 border rounded-full bg-baby-cream focus:ring-2 focus:ring-baby-pink outline-none">
            <input type="file" id="admin-prod-img-file" accept="image/jpeg,image/png,image/webp,image/gif" class="w-full p-3 border rounded-full bg-baby-cream focus:ring-2 focus:ring-baby-pink outline-none hidden">
            <button type="button" onclick="openMediaPicker((item) => { document.getElementById('admin-prod-img-url').value = item.file_path; document.getElementById('admin-prod-image-source').value = 'url'; toggleAdminImageSource(); })" class="w-full border border-baby-blue-light py-2 rounded-full font-semibold text-sm">
                <i class="fa-regular fa-folder-open"></i> Elegir desde File Manager
            </button>
            <button onclick="adminAddProduct()" class="w-full bg-baby-pink text-white py-3 rounded-full font-bold hover:bg-pink-400 transition flex items-center justify-center gap-2">
                <i class="fa-solid fa-save"></i> Guardar Producto
            </button>
        </div>

        <div class="md:col-span-2 bg-white p-6 rounded-3xl shadow-lg border-2 border-baby-blue-light">
            <h3 class="text-xl font-bold text-baby-text mb-4"><i class="fa-solid fa-list text-baby-blue"></i> Gestión de Productos</h3>
            <div class="max-w-xs mb-4">
                <select id="admin-category-filter" class="w-full p-3 border rounded-full bg-baby-cream focus:ring-2 focus:ring-baby-pink outline-none"></select>
            </div>
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

</div>

<div id="media-picker-modal" class="fixed inset-0 bg-black/50 z-[90] hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl p-5 w-full max-w-4xl max-h-[85vh] overflow-hidden shadow-xl flex flex-col">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-bold">File Manager</h3>
            <button type="button" onclick="closeMediaPicker()" class="text-2xl leading-none">&times;</button>
        </div>
        <div class="mb-3 text-sm text-gray-500">Selecciona una imagen para usarla en productos o flyers.</div>
        <div id="media-library-grid" class="grid grid-cols-2 md:grid-cols-4 gap-3 overflow-y-auto"></div>
    </div>
</div>
