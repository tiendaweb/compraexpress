const storeState = {
    products: [],
    slides: [],
    config: {
        whatsappNumber: '573001234567',
        currency: '$'
    },
    flyers: [],
    orders: [],
    currentUser: null,
    currentRole: 'guest'
};

const flyerState = {
    id: null,
    title: '',
    productId: null,
    elements: [],
    selectedElementId: null
};

let cart = [];

const appBasePath = (window.APP_BASE_PATH || '').replace(/\/$/, '');

function appUrl(path) {
    if (!path.startsWith('/')) return path;
    return `${appBasePath}${path}` || '/';
}

async function fetchJSON(url, options = {}) {
    const baseHeaders = options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' };
    const headers = {
        ...baseHeaders,
        ...(options.headers || {}),
    };

    const response = await fetch(appUrl(url), {
        ...options,
        headers
    });

    const payload = await response.json().catch(() => null);
    if (!response.ok) {
        const error = new Error((payload && payload.error) || 'Error de servidor');
        error.status = response.status;
        error.code = payload && payload.code ? payload.code : null;
        throw error;
    }

    if (!payload || typeof payload !== 'object') {
        throw new Error('Respuesta inválida del servidor.');
    }

    return payload;
}


function hasRole(...roles) {
    return roles.includes(storeState.currentRole);
}

function openLoginModal() {
    const modal = document.getElementById('login-modal');
    if (modal) modal.classList.remove('hidden');
}

function closeLoginModal() {
    const modal = document.getElementById('login-modal');
    if (modal) modal.classList.add('hidden');
}

async function refreshAuth() {
    try {
        const payload = await fetchJSON('/api/auth/me');
        storeState.currentUser = payload.user;
        storeState.currentRole = String(payload.user.role || '').toLowerCase();
    } catch (error) {
        if (error.status !== 401) throw error;
        storeState.currentUser = null;
        storeState.currentRole = 'guest';
    }

    applyRoleUI();
}

async function login() {
    const email = document.getElementById('login-email').value.trim();
    const password = document.getElementById('login-password').value;

    try {
        await fetchJSON('/api/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) });
        document.getElementById('login-password').value = '';
        closeLoginModal();
        await initStore();
    } catch (error) {
        alert(error.message);
    }
}

async function logout() {
    try {
        await fetchJSON('/api/auth/logout', { method: 'POST' });
    } finally {
        storeState.currentUser = null;
        storeState.currentRole = 'guest';
        applyRoleUI();
        showTab('store');
    }
}

function applyRoleUI() {
    const adminTab = document.getElementById('tab-admin');
    const flyersTab = document.getElementById('tab-flyers');
    const ordersSection = document.getElementById('admin-orders-section');
    const loginBtn = document.getElementById('auth-open-login');
    const logoutBtn = document.getElementById('auth-logout');
    const badge = document.getElementById('auth-user-badge');

    const canManageProducts = hasRole('admin', 'gestion');
    const isAdmin = hasRole('admin');

    if (adminTab) adminTab.classList.toggle('hidden', !canManageProducts);
    if (flyersTab) flyersTab.classList.toggle('hidden', !isAdmin);
    if (ordersSection) ordersSection.classList.toggle('hidden', !isAdmin);

    if (badge) {
        if (storeState.currentUser) {
            badge.classList.remove('hidden');
            badge.textContent = `${storeState.currentUser.name} (${storeState.currentRole})`;
        } else {
            badge.classList.add('hidden');
            badge.textContent = '';
        }
    }

    if (loginBtn) loginBtn.classList.toggle('hidden', !!storeState.currentUser);
    if (logoutBtn) logoutBtn.classList.toggle('hidden', !storeState.currentUser);
}

async function initStore() {
    await refreshAuth();

    const data = await fetchJSON('/api/bootstrap');
    storeState.products = data.products || [];
    storeState.slides = data.slides || [];
    storeState.config = { ...storeState.config, ...(data.config || {}) };

    if (hasRole('admin')) {
        await initFlyers();
    } else {
        storeState.flyers = [];
        renderFlyerExports([]);
    }

    if (hasRole('admin')) {
        await loadOrders();
    } else {
        storeState.orders = [];
    }

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
    if (tabName === 'admin' && !hasRole('admin', 'gestion')) return;
    if (tabName === 'flyers' && !hasRole('admin')) return;
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
    const activeList = document.getElementById('admin-product-list-active');
    const archivedList = document.getElementById('admin-product-list-archived');
    if (!activeList || !archivedList) return;

    activeList.innerHTML = '';
    archivedList.innerHTML = '';

    storeState.products.forEach(p => {
        const target = Number(p.is_active) === 1 ? activeList : archivedList;
        const deleteAction = hasRole('admin') ? `<button onclick=\"adminDeleteProduct(${p.id})\" class=\"text-red-400 p-2 hover:text-red-600 active:scale-95\"><i class=\"fa-solid fa-trash-can\"></i></button>` : '';
        target.innerHTML += `<div draggable=\"${hasRole('admin', 'gestion')}\" ondragstart=\"onProductDragStart(event)\" data-product-id=\"${p.id}\" class=\"flex items-center gap-3 bg-white p-3 rounded-xl border border-baby-blue-light hover:border-baby-blue ${hasRole('admin', 'gestion') ? 'cursor-move' : ''}\"><img src=\"${p.img}\" class=\"w-12 h-12 object-contain bg-baby-cream rounded-lg\"><div class=\"flex-1\"><p class=\"font-bold text-sm text-baby-text\">${p.name}</p><p class=\"text-xs text-baby-pink font-bold\">${storeState.config.currency}${Number(p.price).toLocaleString()}</p></div>${deleteAction}</div>`;
    });

    setupProductDnD();
    renderOrdersKanban();
}

function toggleAdminImageSource() {
    const source = document.getElementById('admin-prod-image-source');
    const urlInput = document.getElementById('admin-prod-img-url');
    const fileInput = document.getElementById('admin-prod-img-file');
    if (!source || !urlInput || !fileInput) return;

    const useUpload = source.value === 'upload';
    urlInput.classList.toggle('hidden', useUpload);
    fileInput.classList.toggle('hidden', !useUpload);
}

async function uploadAdminProductImage(file) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('uploaded_by', 'admin');

    const response = await fetchJSON('/api/media/upload', {
        method: 'POST',
        body: formData
    });

    return response.path;
}

async function adminAddProduct() {
    const nameInput = document.getElementById('admin-prod-name');
    const priceInput = document.getElementById('admin-prod-price');
    const sourceSelect = document.getElementById('admin-prod-image-source');
    const imageUrlInput = document.getElementById('admin-prod-img-url');
    const imageFileInput = document.getElementById('admin-prod-img-file');

    try {
        let imgPath = imageUrlInput.value.trim();

        if (sourceSelect.value === 'upload') {
            const file = imageFileInput.files[0];
            if (!file) throw new Error('Selecciona una imagen para subir.');
            imgPath = await uploadAdminProductImage(file);
        }

        await fetchJSON('/api/products', {
            method: 'POST',
            body: JSON.stringify({
                name: nameInput.value,
                price: parseInt(priceInput.value, 10),
                img: imgPath
            })
        });

        nameInput.value = '';
        priceInput.value = '';
        imageUrlInput.value = '';
        imageFileInput.value = '';
        sourceSelect.value = 'url';
        toggleAdminImageSource();

        await initStore();
        renderAdminList();
        alert('¡Producto añadido con éxito!');
    } catch (error) {
        alert(error.message);
    }
}
async function adminDeleteProduct(id) { if (!confirm('¿Estás seguro de eliminar este producto?')) return; try { await fetchJSON(`/api/products/${id}`, { method: 'DELETE' }); cart = cart.filter(item => Number(item.id) !== Number(id)); await initStore(); renderAdminList(); } catch (error) { alert(error.message); } }

function onProductDragStart(event) {
    event.dataTransfer.setData('text/product-id', event.currentTarget.dataset.productId);
}

function setupProductDnD() {
    document.querySelectorAll('[data-product-status]').forEach(column => {
        column.ondragover = event => event.preventDefault();
        column.ondrop = async event => {
            event.preventDefault();
            const productId = event.dataTransfer.getData('text/product-id');
            const isActive = Number(column.dataset.productStatus);
            if (!productId || Number.isNaN(isActive)) return;

            try {
                await fetchJSON(`/api/products/${productId}/status`, {
                    method: 'PATCH',
                    body: JSON.stringify({ is_active: isActive })
                });
                storeState.products = await fetchJSON('/api/products');
                renderAdminList();
            } catch (error) {
                alert(error.message);
            }
        };
    });
}

async function loadOrders() {
    if (!hasRole('admin')) {
        storeState.orders = [];
        return;
    }

    try {
        const [active, archived] = await Promise.all([
            fetchJSON('/api/orders'),
            fetchJSON('/api/orders?archived=1')
        ]);
        storeState.orders = [...active, ...archived];
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

function onOrderDragStart(event) {
    event.dataTransfer.setData('text/order-id', event.currentTarget.dataset.orderId);
}

async function moveOrderToColumn(orderId, destinationStatus) {
    const body = destinationStatus === 'archived' ? { archived: 1 } : { status: destinationStatus };
    await fetchJSON(`/api/orders/${orderId}/status`, { method: 'PATCH', body: JSON.stringify(body) });
    await loadOrders();
    renderOrdersKanban();
}

function setupOrderDnD() {
    document.querySelectorAll('#orders-kanban [data-status]').forEach(column => {
        column.ondragover = event => event.preventDefault();
        column.ondrop = async event => {
            event.preventDefault();
            const orderId = event.dataTransfer.getData('text/order-id');
            const destinationStatus = column.dataset.status;
            if (!orderId || !destinationStatus) return;

            try {
                await moveOrderToColumn(orderId, destinationStatus);
            } catch (error) {
                alert(error.message);
            }
        };
    });
}

function renderOrdersKanban() {
    const statuses = ['nuevo', 'en_preparacion', 'en_viaje', 'entregado'];
    const columns = [...statuses, 'archived'];

    columns.forEach(status => {
        const column = document.getElementById(`orders-col-${status}`);
        if (!column) return;
        column.innerHTML = '';

        storeState.orders
            .filter(order => status === 'archived' ? Number(order.archived) === 1 : Number(order.archived) === 0 && order.status === status)
            .forEach(order => {
                const actions = Number(order.archived) === 1
                    ? ''
                    : `<button onclick="moveOrderToColumn(${order.id}, 'archived')" class="px-2 py-1 text-xs rounded-full bg-white border border-baby-blue-light hover:border-baby-blue">Archivar</button>`;

                column.innerHTML += `<div draggable="${Number(order.archived) === 0}" ondragstart="onOrderDragStart(event)" data-order-id="${order.id}" class="bg-white rounded-xl p-3 border border-baby-blue-light shadow-sm space-y-2 ${Number(order.archived) === 0 ? 'cursor-move' : ''}"><div class="text-xs text-gray-500">#${order.id} · ${formatOrderTime(order.created_at)}</div><div class="font-bold text-sm">${order.customer_name || 'Cliente sin nombre'}</div><div class="text-xs text-gray-600">${orderItemsSummary(order.items)}</div><div class="text-sm font-bold text-baby-pink">${storeState.config.currency}${Number(order.total).toLocaleString()}</div><a href="https://wa.me/${storeState.config.whatsappNumber}?text=${encodeURIComponent(order.whatsapp_payload)}" target="_blank" class="inline-flex items-center gap-2 text-xs bg-baby-green px-2 py-1 rounded-full"><i class="fa-brands fa-whatsapp"></i> Abrir WhatsApp</a><div class="flex flex-wrap gap-1">${actions}</div></div>`;
            });
    });

    setupOrderDnD();
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
    if (flyerState.id) {
        await loadFlyerExports(flyerState.id);
    } else {
        renderFlyerExports([]);
    }
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
        await loadFlyerExports(flyerState.id);
    };
}

function flyerNew() {
    flyerState.id = null;
    flyerState.title = '';
    flyerState.productId = null;
    flyerState.elements = [];
    flyerState.selectedElementId = null;
    renderFlyerBuilder();
    renderFlyerExports([]);
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

    const roleLabel = document.getElementById('flyer-current-role');
    if (roleLabel) roleLabel.textContent = storeState.currentRole;

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
    await loadFlyerExports(flyerState.id);
    alert('Flyer guardado en base de datos.');
}


function canExportFlyers() {
    return ['admin'].includes(storeState.currentRole);
}

async function flyerExportCurrent() {
    if (!flyerState.id) {
        alert('Primero guarda el flyer antes de exportar.');
        return;
    }

    if (!canExportFlyers()) {
        alert('Tu rol no tiene permisos para exportar flyers.');
        return;
    }

    const canvasNode = document.getElementById('flyer-canvas');
    if (!canvasNode || !window.html2canvas) {
        alert('No se pudo inicializar html2canvas para exportar.');
        return;
    }

    try {
        const renderedCanvas = await window.html2canvas(canvasNode, { backgroundColor: '#fffaf0', useCORS: true, scale: 2 });
        const pngDataUrl = renderedCanvas.toDataURL('image/png');
        autoDownloadFlyer(pngDataUrl, `flyer-${flyerState.id}-${Date.now()}.png`);

        await fetchJSON(`/api/flyers/${flyerState.id}/export`, {
            method: 'POST',
            body: JSON.stringify({
                image: pngDataUrl,
                exported_by: storeState.currentRole,
            }),
        });

        await loadFlyerExports(flyerState.id);
        alert('Flyer exportado y respaldado en el servidor.');
    } catch (error) {
        alert(error.message);
    }
}

function autoDownloadFlyer(dataUrl, filename) {
    const link = document.createElement('a');
    link.href = dataUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

async function loadFlyerExports(flyerId) {
    if (!flyerId) {
        renderFlyerExports([]);
        return;
    }

    try {
        const exports = await fetchJSON(`/api/flyers/${flyerId}/exports`);
        renderFlyerExports(exports);
    } catch (error) {
        renderFlyerExports([], error.message);
    }
}

function renderFlyerExports(items, errorMessage = '') {
    const list = document.getElementById('flyer-export-list');
    const roleLabel = document.getElementById('flyer-current-role');
    const exportButton = document.getElementById('flyer-export-btn');
    if (!list) return;

    if (roleLabel) roleLabel.textContent = storeState.currentRole;
    if (exportButton) exportButton.disabled = !canExportFlyers();

    if (errorMessage) {
        list.innerHTML = `<p class="text-red-500">${errorMessage}</p>`;
        return;
    }

    if (!items.length) {
        list.innerHTML = '<p>No hay exportaciones registradas.</p>';
        return;
    }

    list.innerHTML = items.map(item => {
        const fileName = String(item.file_path || '').split('/').pop();
        return `<div class="flex items-center justify-between border border-baby-blue-light rounded-xl p-2"><div><p class="font-semibold">${fileName}</p><p class="text-xs text-gray-500">${item.created_at}</p></div><a class="px-3 py-1 rounded-full bg-baby-green font-bold" href="${item.file_path}" download>Descargar</a></div>`;
    }).join('');
}


const imageSourceSelect = document.getElementById('admin-prod-image-source');
if (imageSourceSelect) {
    imageSourceSelect.addEventListener('change', toggleAdminImageSource);
    toggleAdminImageSource();
}

function classifyInitError(error) {
    const message = String(error && error.message ? error.message : '').toLowerCase();
    const code = String(error && error.code ? error.code : '').toUpperCase();

    if (code === 'INSTALLATION_INCOMPLETE' || message.includes('instalación incompleta') || message.includes('schema') || message.includes('esquema')) {
        return `No se pudo cargar la tienda porque la instalación está incompleta. Ejecuta el instalador en ${appUrl('/install.php')}.`;
    }

    if (
        message.includes('conexión')
        || message.includes('acceso denegado')
        || message.includes('credencial')
        || message.includes('base de datos')
        || message.includes('could not connect')
        || message.includes('connection')
    ) {
        return 'No se pudo cargar la tienda por un problema de conexión o credenciales de la base de datos.';
    }

    return 'No se pudo cargar la tienda. Inténtalo de nuevo en unos minutos.';
}

initStore().catch(error => {
    console.error(error);
    alert(classifyInitError(error));
});
