<div id="section-settings" class="tab-content container mx-auto px-4 py-10 hidden space-y-8">
    <h2 class="text-3xl font-bold text-center text-baby-text flex items-center justify-center gap-3">
        <i class="fa-solid fa-sliders text-baby-pink"></i>
        Ajustes de la Tienda
    </h2>

    <div class="grid lg:grid-cols-2 gap-8">
        <div class="bg-white p-6 rounded-3xl shadow-lg border-2 border-baby-blue-light space-y-3">
            <h3 class="text-xl font-bold">Branding y contacto</h3>
            <input id="settings-app-name" type="text" class="w-full p-3 border rounded-full" placeholder="Nombre de la app">
            <input id="settings-app-logo" type="text" class="w-full p-3 border rounded-full" placeholder="URL del logo">
            <input id="settings-app-icon" type="text" class="w-full p-3 border rounded-full" placeholder="Clase icono FontAwesome (ej: fa-solid fa-store)">
            <input id="settings-whatsapp" type="text" class="w-full p-3 border rounded-full" placeholder="WhatsApp sin + ni espacios">
            <input id="settings-address" type="text" class="w-full p-3 border rounded-full" placeholder="Dirección">
            <textarea id="settings-google-maps" class="w-full p-3 border rounded-2xl" rows="4" placeholder="URL embed de Google Maps"></textarea>
            <button onclick="saveSettings()" class="w-full bg-baby-pink text-white py-3 rounded-full font-bold">Guardar ajustes</button>
        </div>

        <div class="bg-white p-6 rounded-3xl shadow-lg border-2 border-baby-blue-light space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold">Redes sociales</h3>
                <button onclick="addSocialLinkRow()" class="px-3 py-2 rounded-full bg-baby-blue-light font-semibold text-sm">+ Agregar</button>
            </div>
            <div id="settings-social-links" class="space-y-3"></div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-3xl shadow-lg border-2 border-baby-blue-light space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-xl font-bold">Slides del home</h3>
            <button onclick="createSlideFromForm()" class="px-4 py-2 rounded-full bg-baby-green font-semibold text-sm">Agregar slide</button>
        </div>
        <div class="grid lg:grid-cols-4 gap-3">
            <input id="slide-form-image" type="text" class="p-3 border rounded-full lg:col-span-2" placeholder="URL de imagen">
            <input id="slide-form-text" type="text" class="p-3 border rounded-full" placeholder="Texto del slide">
            <input id="slide-form-order" type="number" class="p-3 border rounded-full" placeholder="Orden">
        </div>
        <div id="settings-slides-list" class="space-y-3"></div>
    </div>
</div>
