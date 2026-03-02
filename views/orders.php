<div id="section-orders" class="tab-content container mx-auto px-4 py-10 hidden">
    <h2 class="text-3xl font-bold text-center text-baby-text flex items-center justify-center gap-3 mb-8">
        <i class="fa-solid fa-box text-baby-pink"></i>
        Gestión de Pedidos
    </h2>

    <div id="admin-orders-section" class="bg-white p-6 rounded-3xl shadow-lg border-2 border-baby-blue-light">
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
