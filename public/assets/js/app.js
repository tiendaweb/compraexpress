const storeState = {
    products: [],
    slides: [],
    config: {
        whatsappNumber: '573001234567',
        currency: '$'
    },
    flyers: [],
    orders: []
};

const flyerState = {
    id: null,
    title: '',
    productId: null,
    elements: [],
    selectedElementId: null
};

let cart = [];

async function fetchJSON(url, options = {}) {
    const response = await fetch(url, {
        headers: { 'Content-Type': 'application/json' },
        ...options
    });

    const payload = await response.json();
    if (!response.ok) throw new Error(payload.error || 'Error de servidor');
    return payload;
}

async function initStore() {
    const data = await fetchJSON('/api/bootstrap');
    storeState.products = data.products || [];
    storeState.slides = data.slides || [];
    storeState.config = { ...storeState.config, ...(data.config || {}) };

    await initFlyers();
    await loadOrders();
    renderSlider();
    renderProducts();
    updateCartUI();
}

function renderSlider() { /* unchanged behavior */
    const sliderWrapper = document.getElementById('slider-wrapper');
    sliderWrapper.innerHTML = '';
    storeState.slides.forEach(slide => {
        sliderWrapper.innerHTML += `<div class="min-w-full relative h-full"><img src="${slide.image}" class="w-full h-full object-cover"><div class="absolute inset-0 bg-black/40 flex items-center justify-center p-4"><h2 class="text-white text-3xl md:text-5xl font-bold drop-shadow-lg text-center bg-baby-pink/50 px-6 py-2 rounded-full">${slide.text}</h2></div></div>`;
    });
    let index = 0;
    if (window.sliderInterval) clearInterval(window.sliderInterval);
    window.sliderInterval = setInterval(() => {
        if (!document.getElementById('section-store').classList.contains('hidden') && storeState.slides.length) {
            index = (index + 1) % storeState.slides.length;
            sliderWrapper.style.transform = `translateX(-${index * 100}%)`;
        }
    }, 5000);
}

function renderProducts() {
    const grid = document.getElementById('product-grid');
    grid.innerHTML = '';
    storeState.products.forEach(p => {
        grid.innerHTML += `<div class="bg-white p-4 rounded-3xl shadow-md product-card border-2 border-baby-blue-light flex flex-col"><div class="w-full h-36 bg-baby-cream rounded-2xl mb-4 flex items-center justify-center overflow-hidden"><img src="${p.img}" class="h-full object-contain"></div><h3 class="text-base font-semibold text-baby-text h-12 overflow-hidden">${p.name}</h3><p class="text-2xl font-bold text-baby-pink mt-2">${storeState.config.currency}${Number(p.price).toLocaleString()}</p><button onclick="addToCart(${p.id})" class="mt-4 w-full bg-baby-green text-baby-text py-3 rounded-full text-sm font-bold hover:bg-green-300 active:scale-95 transition flex items-center justify-center gap-2"><i class="fa-solid fa-plus-circle"></i> Agregar</button></div>`;
    });
}

function toggleCart() { document.getElementById('cart-drawer').classList.toggle('translate-x-full'); document.getElementById('cart-overlay').classList.toggle('hidden'); }
function addToCart(id) { const product = storeState.products.find(p => Number(p.id) === Number(id)); if (!product) return; const exists = cart.find(item => Number(item.id) === Number(id)); exists ? exists.qty++ : cart.push({ ...product, qty: 1 }); updateCartUI(); }
function removeFromCart(id) { cart = cart.filter(item => Number(item.id) !== Number(id)); updateCartUI(); }
function updateCartUI() { const list = document.getElementById('cart-items'); const countLabel = document.getElementById('cart-count'); const totalLabel = document.getElementById('cart-total'); list.innerHTML = ''; let total = 0; let count = 0; cart.forEach(item => { total += Number(item.price) * item.qty; count += item.qty; list.innerHTML += `<div class="flex items-center gap-3 bg-white p-3 rounded-2xl shadow border border-baby-blue-light"><img src="${item.img}" class="w-16 h-16 object-contain rounded-lg bg-baby-cream"><div class="flex-1"><p class="text-sm font-bold text-baby-text">${item.name}</p><p class="text-xs text-gray-500">${item.qty} x ${storeState.config.currency}${Number(item.price).toLocaleString()}</p></div><button onclick="removeFromCart(${item.id})" class="text-red-300 p-2 hover:text-red-500"><i class="fa-solid fa-trash-can text-lg"></i></button></div>`; }); countLabel.innerText = count; totalLabel.innerText = `${storeState.config.currency}${total.toLocaleString()}`; }

async function sendOrder() {
    const name = document.getElementById('cust-name').value.trim();
    const address = document.getElementById('cust-address').value.trim();

    if (cart.length === 0) return alert('¡El carrito está vacío, dulzura!');
    if (!address) return alert('Por favor, agrega la dirección de envío.');

    const totalNumber = cart.reduce((sum, item) => sum + (Number(item.price) * item.qty), 0);

    let message = `*Nuevo Pedido - Pañalería y Algo Más*\n\n`;
    if (name) message += `*Cliente:* ${name}\n`;
    message += `*Dirección:* ${address}\n\n`;
    message += '*Productos:*\n';
    cart.forEach(item => {
        message += `- ${item.qty}x ${item.name} (${storeState.config.currency}${(Number(item.price) * item.qty).toLocaleString()})\n`;
    });
    message += `\n*TOTAL: ${storeState.config.currency}${totalNumber.toLocaleString()}*`;

    const payload = {
        customer_name: name || null,
        whatsapp_payload: message,
        total: totalNumber,
        items: cart.map(item => ({
            product_id: Number(item.id),
            name_snapshot: item.name,
            price_snapshot: Number(item.price),
            qty: item.qty
        }))
    };

    try {
        const order = await fetchJSON('/api/orders', { method: 'POST', body: JSON.stringify(payload) });
        const encodedMessage = encodeURIComponent(order.whatsapp_payload);
        window.open(`https://wa.me/${storeState.config.whatsappNumber}?text=${encodedMessage}`, '_blank');

        cart = [];
        document.getElementById('cust-name').value = '';
        document.getElementById('cust-address').value = '';
        updateCartUI();
        await loadOrders();
        renderOrdersKanban();
    } catch (error) {
        alert(error.message);
    }
}

function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('nav button').forEach(el => el.classList.remove('active-tab'));

    const sectionId = tabName === 'store' ? 'section-store' : tabName === 'admin' ? 'section-admin' : 'section-flyers';
    const tabId = tabName === 'store' ? 'tab-store' : tabName === 'admin' ? 'tab-admin' : 'tab-flyers';
    document.getElementById(sectionId).classList.remove('hidden');
    document.getElementById(tabId).classList.add('active-tab');

    if (tabName === 'admin') renderAdminList();
    if (tabName === 'flyers') renderFlyerBuilder();
}

function renderAdminList() {
    const list = document.getElementById('admin-product-list');
    list.innerHTML = '';
    storeState.products.forEach(p => {
        list.innerHTML += `<div class="flex items-center gap-3 bg-baby-cream p-3 rounded-xl border border-baby-blue-light hover:border-baby-blue"><img src="${p.img}" class="w-12 h-12 object-contain bg-white rounded-lg"><div class="flex-1"><p class="font-bold text-sm text-baby-text">${p.name}</p><p class="text-xs text-baby-pink font-bold">${storeState.config.currency}${Number(p.price).toLocaleString()}</p></div><button onclick="adminDeleteProduct(${p.id})" class="text-red-400 p-2 hover:text-red-600 active:scale-95"><i class="fa-solid fa-trash-can"></i></button></div>`;
    });

    renderOrdersKanban();
}
async function adminAddProduct() { const nameInput = document.getElementById('admin-prod-name'); const priceInput = document.getElementById('admin-prod-price'); const imgInput = document.getElementById('admin-prod-img'); try { await fetchJSON('/api/products', { method: 'POST', body: JSON.stringify({ name: nameInput.value, price: parseInt(priceInput.value, 10), img: imgInput.value }) }); nameInput.value = ''; priceInput.value = ''; imgInput.value = ''; await initStore(); renderAdminList(); alert('¡Producto añadido con éxito!'); } catch (error) { alert(error.message); } }
async function adminDeleteProduct(id) { if (!confirm('¿Estás seguro de eliminar este producto?')) return; try { await fetchJSON(`/api/products/${id}`, { method: 'DELETE' }); cart = cart.filter(item => Number(item.id) !== Number(id)); await initStore(); renderAdminList(); } catch (error) { alert(error.message); } }



async function loadOrders() {
    try {
        storeState.orders = await fetchJSON('/api/orders');
    } catch (error) {
        console.error(error);
        storeState.orders = [];
    }
}

function formatOrderTime(createdAt) {
    const date = new Date(createdAt);
    if (Number.isNaN(date.getTime())) return createdAt;
    return date.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
}

function orderItemsSummary(items) {
    return items.map(item => `${item.qty}x ${item.name_snapshot}`).join(', ');
}

async function updateOrderStatus(orderId, status) {
    try {
        await fetchJSON(`/api/orders/${orderId}/status`, { method: 'PATCH', body: JSON.stringify({ status }) });
        await loadOrders();
        renderOrdersKanban();
    } catch (error) {
        alert(error.message);
    }
}

function renderOrdersKanban() {
    const statuses = ['nuevo', 'en_preparacion', 'en_viaje', 'entregado'];
    statuses.forEach(status => {
        const column = document.getElementById(`orders-col-${status}`);
        if (!column) return;
        column.innerHTML = '';

        storeState.orders
            .filter(order => order.status === status)
            .forEach(order => {
                const nextStatuses = statuses.filter(candidate => candidate !== order.status)
                    .map(candidate => `<button onclick="updateOrderStatus(${order.id}, '${candidate}')" class="px-2 py-1 text-xs rounded-full bg-white border border-baby-blue-light hover:border-baby-blue">${candidate.replace('_', ' ')}</button>`)
                    .join('');

                column.innerHTML += `<div class="bg-white rounded-xl p-3 border border-baby-blue-light shadow-sm space-y-2"><div class="text-xs text-gray-500">#${order.id} · ${formatOrderTime(order.created_at)}</div><div class="font-bold text-sm">${order.customer_name || 'Cliente sin nombre'}</div><div class="text-xs text-gray-600">${orderItemsSummary(order.items)}</div><div class="text-sm font-bold text-baby-pink">${storeState.config.currency}${Number(order.total).toLocaleString()}</div><a href="https://wa.me/${storeState.config.whatsappNumber}?text=${encodeURIComponent(order.whatsapp_payload)}" target="_blank" class="inline-flex items-center gap-2 text-xs bg-baby-green px-2 py-1 rounded-full"><i class="fa-brands fa-whatsapp"></i> Abrir WhatsApp</a><div class="flex flex-wrap gap-1">${nextStatuses}</div></div>`;
            });
    });
}

async function initFlyers() {
    storeState.flyers = await fetchJSON('/api/flyers');
    const cached = localStorage.getItem('flyerDraftCache');
    if (cached && !flyerState.id) {
        try {
            const parsed = JSON.parse(cached);
            Object.assign(flyerState, parsed);
        } catch (_) {}
    }
    renderFlyerSelectors();
}

function renderFlyerSelectors() {
    const saved = document.getElementById('flyer-saved');
    const product = document.getElementById('flyer-product-select');
    if (!saved || !product) return;

    saved.innerHTML = '<option value="">-- Flyers guardados --</option>';
    storeState.flyers.forEach(f => saved.innerHTML += `<option value="${f.id}">${f.title}</option>`);

    product.innerHTML = '<option value="">Producto relacionado (opcional)</option>';
    storeState.products.forEach(p => product.innerHTML += `<option value="${p.id}">${p.name}</option>`);

    saved.onchange = async (e) => {
        if (!e.target.value) return;
        const data = await fetchJSON(`/api/flyers/${e.target.value}`);
        flyerState.id = Number(data.id);
        flyerState.title = data.title;
        flyerState.productId = data.product_id ? Number(data.product_id) : null;
        flyerState.elements = JSON.parse(data.layout_json || '[]');
        flyerState.selectedElementId = flyerState.elements[0]?.id || null;
        renderFlyerBuilder();
    };
}

function flyerNew() {
    flyerState.id = null;
    flyerState.title = '';
    flyerState.productId = null;
    flyerState.elements = [];
    flyerState.selectedElementId = null;
    renderFlyerBuilder();
}

function flyerAddElement(type) {
    const id = 'e' + Date.now();
    flyerState.elements.push(type === 'text'
        ? { id, type: 'text', value: 'Texto', x: 20, y: 20, w: 180, h: 40 }
        : { id, type: 'image', value: 'https://via.placeholder.com/200x200', x: 40, y: 80, w: 160, h: 160 });
    flyerState.selectedElementId = id;
    renderFlyerBuilder();
}

function flyerApplyProductToSelected() {
    const select = document.getElementById('flyer-product-select');
    const selectedProduct = storeState.products.find(p => Number(p.id) === Number(select.value));
    const selectedElement = flyerState.elements.find(e => e.id === flyerState.selectedElementId);
    if (!selectedProduct || !selectedElement) return alert('Selecciona producto y elemento.');

    flyerState.productId = Number(selectedProduct.id);
    if (selectedElement.type === 'image') selectedElement.value = selectedProduct.img;
    else selectedElement.value = `${selectedProduct.name} - ${storeState.config.currency}${Number(selectedProduct.price).toLocaleString()}`;
    renderFlyerBuilder();
}

function renderFlyerBuilder() {
    const titleInput = document.getElementById('flyer-title');
    const productSelect = document.getElementById('flyer-product-select');
    const canvas = document.getElementById('flyer-canvas');
    const elementsList = document.getElementById('flyer-elements');
    if (!canvas || !elementsList) return;

    titleInput.value = flyerState.title;
    titleInput.oninput = (e) => { flyerState.title = e.target.value; cacheFlyerDraft(); };
    if (productSelect) productSelect.value = flyerState.productId || '';

    canvas.innerHTML = '';
    flyerState.elements.forEach(el => {
        const node = document.createElement(el.type === 'image' ? 'img' : 'div');
        node.className = `absolute border p-1 bg-white/80 ${flyerState.selectedElementId === el.id ? 'border-baby-pink' : 'border-transparent'}`;
        node.style.left = `${el.x}px`; node.style.top = `${el.y}px`; node.style.width = `${el.w}px`; node.style.height = `${el.h}px`;
        if (el.type === 'image') { node.src = el.value; node.style.objectFit = 'cover'; } else { node.textContent = el.value; }
        node.onclick = () => { flyerState.selectedElementId = el.id; renderFlyerBuilder(); };
        canvas.appendChild(node);
    });

    elementsList.innerHTML = '';
    flyerState.elements.forEach(el => {
        elementsList.innerHTML += `<div class="p-2 rounded-xl border ${flyerState.selectedElementId === el.id ? 'border-baby-pink' : 'border-baby-blue-light'}"><div class="text-xs mb-1">${el.type.toUpperCase()}</div><input class="w-full p-2 border rounded" value="${el.value}" oninput="flyerUpdateElement('${el.id}', this.value)"></div>`;
    });

    cacheFlyerDraft();
}

function flyerUpdateElement(id, value) {
    const el = flyerState.elements.find(item => item.id === id);
    if (!el) return;
    el.value = value;
    renderFlyerBuilder();
}

function cacheFlyerDraft() { localStorage.setItem('flyerDraftCache', JSON.stringify(flyerState)); }

async function flyerSave() {
    if (!flyerState.title.trim()) return alert('Agrega un título.');
    const payload = { id: flyerState.id, title: flyerState.title, product_id: flyerState.productId, layout: flyerState.elements };
    const saved = await fetchJSON('/api/flyers', { method: 'POST', body: JSON.stringify(payload) });
    flyerState.id = Number(saved.id);
    localStorage.setItem('flyerDraftCache', JSON.stringify(flyerState));
    await initFlyers();
    alert('Flyer guardado en base de datos.');
}

initStore().catch(error => {
    console.error(error);
    alert('No se pudo cargar la tienda. Revisa la conexión con la base de datos.');
});
