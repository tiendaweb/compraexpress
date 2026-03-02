<div id="section-store" class="tab-content">
    <section id="slider-container" class="relative w-full h-56 md:h-96 overflow-hidden bg-baby-blue-light">
        <div id="slider-wrapper" class="flex transition-transform duration-700 ease-in-out h-full"></div>
    </section>

    <main class="container mx-auto px-4 py-10">
        <h2 class="text-3xl font-bold mb-8 text-center text-baby-text flex items-center justify-center gap-3">
            <i class="fa-solid fa-teddy-bear text-baby-blue"></i>
            Nuestros Productos
            <i class="fa-solid fa-teddy-bear text-baby-blue"></i>
        </h2>
        <div class="max-w-md mx-auto mb-6">
            <label for="store-category-filter" class="block text-sm font-semibold mb-2">Filtrar por categoría</label>
            <select id="store-category-filter" class="w-full p-3 border rounded-full bg-white focus:ring-2 focus:ring-baby-pink outline-none"></select>
        </div>
        <div id="product-grid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6"></div>
    </main>

    <section class="container mx-auto px-4 pb-12">
        <div class="bg-white rounded-3xl border-2 border-baby-blue-light p-6 grid md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-xl font-bold mb-3"><i class="fa-solid fa-address-card text-baby-pink"></i> Contacto</h3>
                <p id="store-contact-address" class="text-sm text-gray-700">Dirección no configurada.</p>
                <div id="store-social-links" class="mt-4 flex flex-wrap gap-2"></div>
            </div>
            <div>
                <iframe id="store-google-maps" class="w-full h-56 rounded-2xl border hidden" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                <p id="store-google-maps-empty" class="text-sm text-gray-500">Mapa no configurado.</p>
            </div>
        </div>
    </section>
</div>
