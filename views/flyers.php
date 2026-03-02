<div id="section-flyers" class="tab-content container mx-auto px-4 py-10 hidden">
    <h2 class="text-3xl font-bold mb-8 text-center text-baby-text flex items-center justify-center gap-3">
        <i class="fa-solid fa-image text-baby-pink"></i>
        Generador de Flyers
    </h2>

    <div class="flex justify-center mb-6">
        <div class="inline-flex bg-white rounded-full p-1 border-2 border-baby-blue-light shadow-sm gap-1">
            <button id="flyer-subtab-btn-editor" onclick="showFlyerSubTab('editor')" class="px-5 py-2 rounded-full font-bold bg-baby-blue">Editor</button>
            <button id="flyer-subtab-btn-explorer" onclick="showFlyerSubTab('explorer')" class="px-5 py-2 rounded-full font-bold">Explorador</button>
        </div>
    </div>

    <div id="flyer-subtab-editor" class="grid lg:grid-cols-3 gap-6">
        <div class="bg-white p-5 rounded-3xl shadow-lg border-2 border-baby-blue-light space-y-3">
            <input id="flyer-title" type="text" placeholder="Título del flyer" class="w-full p-3 border rounded-full bg-baby-cream">
            <select id="flyer-product-select" class="w-full p-3 border rounded-full bg-baby-cream"></select>
            <select id="flyer-template-select" class="w-full p-3 border rounded-full bg-baby-cream">
                <option value="custom">-- Diseño libre --</option>
            </select>
            <div class="grid grid-cols-2 gap-2">
                <button onclick="flyerAddElement('text')" class="bg-baby-blue py-2 rounded-full font-bold">+ Texto</button>
                <button onclick="flyerAddElement('image')" class="bg-baby-green py-2 rounded-full font-bold">+ Imagen</button>
            </div>
            <button onclick="flyerApplyProductToSelected()" class="w-full bg-baby-pink text-white py-2 rounded-full font-bold">Autocompletar elemento</button>
            <p class="text-xs text-gray-500">Tip: al crear un elemento de imagen, por defecto quedará listo para cargar archivo.</p>
            <button onclick="flyerSave()" class="w-full bg-baby-green py-3 rounded-full font-bold">Guardar flyer</button>
            <button onclick="flyerExportCurrent()" id="flyer-export-btn" class="w-full bg-baby-blue py-3 rounded-full font-bold">Exportar PNG</button>
            <button onclick="flyerNew()" class="w-full border py-2 rounded-full font-bold">Nuevo</button>
            <p class="text-xs text-gray-500">Rol actual: <span id="flyer-current-role">admin</span></p>
        </div>

        <div class="lg:col-span-2 bg-white p-5 rounded-3xl shadow-lg border-2 border-baby-blue-light">
            <div id="flyer-canvas" class="relative w-full h-[520px] bg-baby-cream rounded-2xl border border-baby-blue-light"></div>
            <div id="flyer-elements" class="mt-4 space-y-2 max-h-48 overflow-y-auto"></div>
        </div>
    </div>

    <div id="flyer-subtab-explorer" class="hidden grid lg:grid-cols-2 gap-6">
        <div class="bg-white p-5 rounded-3xl shadow-lg border-2 border-baby-blue-light">
            <h3 class="font-bold text-lg mb-3">Flyers guardados</h3>
            <div id="flyer-project-list" class="space-y-2 text-sm text-gray-600">
                <p>No hay flyers guardados.</p>
            </div>
        </div>

        <div class="bg-white p-5 rounded-3xl shadow-lg border-2 border-baby-blue-light">
            <h3 class="font-bold text-lg mb-3">Exportaciones previas</h3>
            <div id="flyer-export-list" class="space-y-2 text-sm text-gray-600">
                <p>No hay exportaciones registradas.</p>
            </div>
        </div>
    </div>
</div>
