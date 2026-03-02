const storeState = {
    products: [],
    slides: [],
    categories: [],
    storeCategoryFilter: '',
    adminCategoryFilter: '',
    config: {
        whatsappNumber: '573001234567',
        currency: '$',
        appName: 'Pañalería y Algo Más',
        appLogo: '',
        appIcon: 'fa-solid fa-baby-carriage',
        socialLinks: [],
        address: '',
        googleMapsEmbed: ''
    },
    flyers: [],
    orders: [],
    currentUser: null,
    currentRole: 'guest',
    users: [],
    editingUserId: null
};

const flyerState = {
    id: null,
    title: '',
    productId: null,
    subTab: 'editor',
    templateId: 'custom',
    bgColor: '#fffaf0',
    elements: [],
    selectedElementId: null
};

const mediaState = {
    items: [],
    onSelect: null,
};

let cart = [];

const appBasePath = (window.APP_BASE_PATH || '').replace(/\/$/, '');

function appUrl(path) {
    if (!path.startsWith('/')) return path;
    return `${appBasePath}${path}` || '/';
}

function deepClone(value) {
    return JSON.parse(JSON.stringify(value));
}

const IMAGE_UPLOAD_CONFIG = {
    maxPreviewWidth: 1280,
    maxPreviewHeight: 1280,
    quality: 0.82,
    compressThresholdBytes: 1.5 * 1024 * 1024,
};

function showInlineUploadError(elementId, message = '') {
    const node = document.getElementById(elementId);
    if (!node) return;
    if (message) {
        node.textContent = message;
        node.classList.remove('hidden');
        return;
    }
    node.textContent = '';
    node.classList.add('hidden');
}

function deviceSupportsCapture() {
    const input = document.createElement('input');
    input.type = 'file';
    return 'capture' in input;
}

function dataUrlToFile(dataUrl, fileName = 'captura.jpg') {
    const [header, data] = dataUrl.split(',');
    const mimeMatch = header.match(/data:(.*?);base64/);
    const mime = mimeMatch ? mimeMatch[1] : 'image/jpeg';
    const binary = atob(data);
    const array = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i += 1) array[i] = binary.charCodeAt(i);
    return new File([array], fileName, { type: mime });
}

function loadImageFromFile(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => reject(new Error('No se pudo leer la imagen seleccionada.'));
            img.src = reader.result;
        };
        reader.onerror = () => reject(new Error('No se pudo procesar el archivo seleccionado.'));
        reader.readAsDataURL(file);
    });
}

async function prepareImageForUpload(file) {
    if (!file || !file.type?.startsWith('image/')) {
        throw new Error('Selecciona un archivo de imagen válido.');
    }

    const shouldCompress = file.size >= IMAGE_UPLOAD_CONFIG.compressThresholdBytes;
    if (!shouldCompress) {
        return { uploadFile: file, previewUrl: URL.createObjectURL(file), compressed: false };
    }

    const sourceImage = await loadImageFromFile(file);
    const ratio = Math.min(
        1,
        IMAGE_UPLOAD_CONFIG.maxPreviewWidth / sourceImage.width,
        IMAGE_UPLOAD_CONFIG.maxPreviewHeight / sourceImage.height
    );

    const width = Math.max(1, Math.round(sourceImage.width * ratio));
    const height = Math.max(1, Math.round(sourceImage.height * ratio));
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d');
    context.drawImage(sourceImage, 0, 0, width, height);

    const dataUrl = canvas.toDataURL('image/jpeg', IMAGE_UPLOAD_CONFIG.quality);
    const compressedFile = dataUrlToFile(dataUrl, file.name.replace(/\.[^.]+$/, '') + '-compressed.jpg');
    return { uploadFile: compressedFile, previewUrl: dataUrl, compressed: true };
}

function setImagePreview(elementId, previewUrl) {
    const preview = document.getElementById(elementId);
    if (!preview) return;
    if (!previewUrl) {
        preview.removeAttribute('src');
        preview.classList.add('hidden');
        return;
    }
    preview.src = previewUrl;
    preview.classList.remove('hidden');
}

function normalizeTemplateElement(rawElement = {}) {
    const normalized = {
        id: rawElement.id || `e${Date.now()}`,
        type: rawElement.type === 'image' ? 'image' : 'text',
        x: Number(rawElement.x) || 0,
        y: Number(rawElement.y) || 0,
        w: Number(rawElement.w) || 160,
        h: Number(rawElement.h) || 80,
        styles: { ...(rawElement.styles || {}) }
    };

    if (normalized.type === 'image') {
        normalized.value = rawElement.value || rawElement.src || '';
        return normalized;
    }

    normalized.value = rawElement.value || rawElement.content || 'Texto';
    return normalized;
}

function normalizeTemplateDefinition(templateId, template = {}) {
    const elements = Array.isArray(template.elements) ? template.elements.map(normalizeTemplateElement) : [];
    return {
        id: templateId,
        name: template.name || templateId,
        bgColor: template.bgColor || '#fffaf0',
        elements,
    };
}

function getTemplateCatalog() {
    const catalog = window.FLYER_TEMPLATE_CATALOG || {};
    return Object.entries(catalog).reduce((acc, [id, template]) => {
        acc[id] = normalizeTemplateDefinition(id, template);
        return acc;
    }, {});
}

function loadTemplateInBuilder(templateId = 'custom') {
    const templates = getTemplateCatalog();
    flyerState.templateId = templateId;

    if (templateId === 'custom' || !templates[templateId]) {
        flyerState.bgColor = '#fffaf0';
        flyerState.elements = [];
        flyerState.selectedElementId = null;
        return;
    }

    const template = deepClone(templates[templateId]);
    flyerState.bgColor = template.bgColor;
    flyerState.elements = template.elements;
    flyerState.selectedElementId = flyerState.elements[0]?.id || null;
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


function parseSocialLinks(raw) {
    if (Array.isArray(raw)) return raw;
    if (typeof raw !== 'string' || !raw.trim()) return [];
    try {
        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch (_) {
        return [];
    }
}

function normalizeConfig(config = {}) {
    const merged = { ...storeState.config, ...(config || {}) };
    merged.appName = String(merged.appName || 'Pañalería y Algo Más').trim() || 'Pañalería y Algo Más';
    merged.appLogo = String(merged.appLogo || '').trim();
    merged.appIcon = String(merged.appIcon || 'fa-solid fa-baby-carriage').trim() || 'fa-solid fa-baby-carriage';
    merged.address = String(merged.address || '').trim();
    merged.googleMapsEmbed = String(merged.googleMapsEmbed || '').trim();
    merged.whatsappNumber = String(merged.whatsappNumber || '573001234567').trim() || '573001234567';
    merged.socialLinks = parseSocialLinks(merged.socialLinks).filter(item => item && item.label && item.url);
    return merged;
}

function applyBranding() {
    const nameEl = document.getElementById('app-name');
    const logoEl = document.getElementById('app-logo');
    const iconEl = document.getElementById('app-icon');
    const titleEl = document.getElementById('app-browser-title');
    const appName = storeState.config.appName || 'Pañalería y Algo Más';

    if (nameEl) nameEl.textContent = appName;
    if (titleEl) titleEl.textContent = `${appName} | Tienda Online`;
    if (logoEl) {
        if (storeState.config.appLogo) {
            logoEl.src = storeState.config.appLogo;
            logoEl.classList.remove('hidden');
            if (iconEl) iconEl.classList.add('hidden');
        } else {
            logoEl.classList.add('hidden');
            if (iconEl) iconEl.classList.remove('hidden');
        }
    }
    if (iconEl) {
        iconEl.className = `${storeState.config.appIcon || 'fa-solid fa-baby-carriage'} text-baby-pink`;
    }
}

function renderStoreContact() {
    const address = document.getElementById('store-contact-address');
    const social = document.getElementById('store-social-links');
    const maps = document.getElementById('store-google-maps');
    const mapsEmpty = document.getElementById('store-google-maps-empty');

    if (address) address.textContent = storeState.config.address || 'Dirección no configurada.';
    if (social) {
        const links = Array.isArray(storeState.config.socialLinks) ? storeState.config.socialLinks : [];
        social.innerHTML = links.length
            ? links.map(link => `<a href="${link.url}" target="_blank" class="px-3 py-2 rounded-full bg-baby-blue-light text-sm"><i class="${link.icon || 'fa-solid fa-link'}"></i> ${link.label}</a>`).join('')
            : '<p class="text-sm text-gray-500">No hay redes configuradas.</p>';
    }

    if (maps && mapsEmpty) {
        if (storeState.config.googleMapsEmbed) {
            maps.src = storeState.config.googleMapsEmbed;
            maps.classList.remove('hidden');
            mapsEmpty.classList.add('hidden');
        } else {
            maps.removeAttribute('src');
            maps.classList.add('hidden');
            mapsEmpty.classList.remove('hidden');
        }
    }
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
    const ordersTab = document.getElementById('tab-orders');
    const flyersTab = document.getElementById('tab-flyers');
    const usersTab = document.getElementById('tab-users');
    const settingsTab = document.getElementById('tab-settings');
    const tabsNav = document.getElementById('main-tabs-nav');
    const ordersSection = document.getElementById('admin-orders-section');
    const loginBtn = document.getElementById('auth-open-login');
    const logoutBtn = document.getElementById('auth-logout');
    const badge = document.getElementById('auth-user-badge');

    const canManageProducts = hasRole('admin', 'gestion');
    const isAdmin = hasRole('admin');
    const isLoggedIn = !!storeState.currentUser;

    if (tabsNav) tabsNav.classList.toggle('hidden', !isLoggedIn);
    if (adminTab) adminTab.classList.toggle('hidden', !canManageProducts);
    if (ordersTab) ordersTab.classList.toggle('hidden', !isAdmin);
    if (flyersTab) flyersTab.classList.toggle('hidden', !isAdmin);
    if (usersTab) usersTab.classList.toggle('hidden', !isAdmin);
    if (settingsTab) settingsTab.classList.toggle('hidden', !isAdmin);
    if (ordersSection) ordersSection.classList.toggle('hidden', !isAdmin);

    if (badge) {
        if (isLoggedIn) {
            badge.classList.remove('hidden');
            badge.textContent = `${storeState.currentUser.name} (${storeState.currentRole})`;
        } else {
            badge.classList.add('hidden');
            badge.textContent = '';
        }
    }

    if (loginBtn) loginBtn.classList.toggle('hidden', isLoggedIn);
    if (logoutBtn) logoutBtn.classList.toggle('hidden', !isLoggedIn);
}

async function initStore() {
    await refreshAuth();

    const data = await fetchJSON('/api/bootstrap');
    storeState.products = data.products || [];
    storeState.slides = data.slides || [];
    storeState.config = normalizeConfig(data.config || {});
    storeState.categories = data.categories || [];

    if (hasRole('admin')) {
        await initFlyers();
    } else {
        storeState.flyers = [];
        renderFlyerExports([]);
    }

    if (hasRole('admin')) {
        await loadOrders();
        await loadUsers();
    } else {
        storeState.orders = [];
        storeState.users = [];
        resetUserForm();
        renderUsersTable();
    }

    applyBranding();
    renderStoreContact();
    renderSlider();
    renderCategoryFilters();
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

    const filteredProducts = storeState.products.filter(product => {
        if (!storeState.storeCategoryFilter) return true;
        return Number(product.category_id) === Number(storeState.storeCategoryFilter);
    });

    filteredProducts.forEach(p => {
        const category = p.category_name ? `<p class="text-xs text-gray-500">${p.category_name}</p>` : '';
        grid.innerHTML += `<div class="bg-white p-4 rounded-3xl shadow-md product-card border-2 border-baby-blue-light flex flex-col"><div class="w-full h-36 bg-baby-cream rounded-2xl mb-4 flex items-center justify-center overflow-hidden"><img src="${p.img}" class="h-full object-contain"></div><h3 class="text-base font-semibold text-baby-text">${p.name}</h3>${category}<p class="text-2xl font-bold text-baby-pink mt-2">${storeState.config.currency}${Number(p.price).toLocaleString()}</p><button onclick="addToCart(${p.id})" class="mt-4 w-full bg-baby-green text-baby-text py-3 rounded-full text-sm font-bold hover:bg-green-300 active:scale-95 transition flex items-center justify-center gap-2"><i class="fa-solid fa-plus-circle"></i> Agregar</button></div>`;
    });
}

function renderCategoryFilters() {
    const storeSelect = document.getElementById('store-category-filter');
    const adminSelect = document.getElementById('admin-category-filter');
    const adminProductCategory = document.getElementById('admin-prod-category');

    const options = ['<option value="">Todas las categorías</option>'].concat(
        storeState.categories.map(c => `<option value="${c.id}">${c.name}</option>`)
    ).join('');

    if (storeSelect) {
        storeSelect.innerHTML = options;
        storeSelect.value = storeState.storeCategoryFilter;
        storeSelect.onchange = event => {
            storeState.storeCategoryFilter = event.target.value;
            renderProducts();
        };
    }

    if (adminSelect) {
        adminSelect.innerHTML = options;
        adminSelect.value = storeState.adminCategoryFilter;
        adminSelect.onchange = event => {
            storeState.adminCategoryFilter = event.target.value;
            renderAdminList();
        };
    }

    if (adminProductCategory) {
        adminProductCategory.innerHTML = '<option value="">Sin categoría</option>' + storeState.categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    }
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
    const isGuest = !storeState.currentUser;
    if (isGuest && tabName !== 'store') {
        openLoginModal();
        tabName = 'store';
    }

    if (tabName === 'admin' && !hasRole('admin', 'gestion')) return;
    if (tabName === 'orders' && !hasRole('admin')) return;
    if (tabName === 'flyers' && !hasRole('admin')) return;
    if (tabName === 'users' && !hasRole('admin')) return;
    if (tabName === 'settings' && !hasRole('admin')) return;

    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('#main-tabs-nav button').forEach(el => el.classList.remove('active-tab'));

    const sectionMap = {
        store: 'section-store',
        admin: 'section-admin',
        orders: 'section-orders',
        flyers: 'section-flyers',
        users: 'section-users',
        settings: 'section-settings',
    };
    const tabMap = {
        store: 'tab-store',
        admin: 'tab-admin',
        orders: 'tab-orders',
        flyers: 'tab-flyers',
        users: 'tab-users',
        settings: 'tab-settings',
    };

    const sectionId = sectionMap[tabName] || 'section-store';
    const tabId = tabMap[tabName] || 'tab-store';
    const section = document.getElementById(sectionId);
    const tab = document.getElementById(tabId);

    if (section) section.classList.remove('hidden');
    if (tab) tab.classList.add('active-tab');

    if (tabName === 'admin') renderAdminList();
    if (tabName === 'orders') renderOrdersKanban();
    if (tabName === 'flyers') {
        renderFlyerBuilder();
        showFlyerSubTab(flyerState.subTab || 'editor');
    }
    if (tabName === 'users') renderUsersTable();
    if (tabName === 'settings') renderSettingsForm();
}


function renderAdminList() {
    const activeList = document.getElementById('admin-product-list-active');
    const archivedList = document.getElementById('admin-product-list-archived');
    if (!activeList || !archivedList) return;

    activeList.innerHTML = '';
    archivedList.innerHTML = '';

    const visibleProducts = storeState.products.filter(product => {
        if (!storeState.adminCategoryFilter) return true;
        return Number(product.category_id) === Number(storeState.adminCategoryFilter);
    });

    visibleProducts.forEach(p => {
        const target = Number(p.is_active) === 1 ? activeList : archivedList;
        const deleteAction = hasRole('admin') ? `<button onclick="adminDeleteProduct(${p.id})" class="text-red-400 p-2 hover:text-red-600 active:scale-95"><i class="fa-solid fa-trash-can"></i></button>` : '';
        target.innerHTML += `<div draggable="${hasRole('admin', 'gestion')}" ondragstart="onProductDragStart(event)" data-product-id="${p.id}" class="bg-white p-3 rounded-xl border border-baby-blue-light hover:border-baby-blue ${hasRole('admin', 'gestion') ? 'cursor-move' : ''}"><div class="flex items-center gap-3"><img src="${p.img}" class="w-12 h-12 object-contain bg-baby-cream rounded-lg"><div class="flex-1"><p class="font-bold text-sm text-baby-text">${p.name}</p><p class="text-xs text-baby-pink font-bold">${storeState.config.currency}${Number(p.price).toLocaleString()}</p><p class="text-xs text-gray-500">${p.category_name || 'Sin categoría'}</p></div>${deleteAction}</div><div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-2"><button onclick="openProductEdit(${p.id})" class="px-2 py-1 text-xs rounded-full bg-baby-blue-light">Editar</button><button onclick="quickToggleProduct(${p.id}, ${Number(p.is_active) === 1 ? 0 : 1})" class="px-2 py-1 text-xs rounded-full bg-white border border-baby-blue-light">${Number(p.is_active) === 1 ? 'Archivar' : 'Activar'}</button></div><div id="edit-product-${p.id}" class="hidden mt-3 space-y-2"><input id="edit-name-${p.id}" class="w-full p-2 border rounded" value="${p.name}"><input id="edit-price-${p.id}" type="number" class="w-full p-2 border rounded" value="${p.price}"><input id="edit-img-${p.id}" class="w-full p-2 border rounded" value="${p.img}"><button onclick="openMediaPicker((item) => { const input = document.getElementById('edit-img-${p.id}'); if (input) input.value = item.file_path; })" class="w-full px-3 py-2 text-xs rounded-full border border-baby-blue-light">Elegir desde File Manager</button><select id="edit-category-${p.id}" class="w-full p-2 border rounded"><option value="">Sin categoría</option>${storeState.categories.map(c => `<option value="${c.id}" ${Number(p.category_id) === Number(c.id) ? 'selected' : ''}>${c.name}</option>`).join('')}</select><select id="edit-active-${p.id}" class="w-full p-2 border rounded"><option value="1" ${Number(p.is_active) === 1 ? 'selected' : ''}>Activo</option><option value="0" ${Number(p.is_active) === 0 ? 'selected' : ''}>Archivado</option></select><button onclick="saveProductEdit(${p.id})" class="w-full px-3 py-2 text-xs rounded-full bg-baby-pink text-white">Guardar edición</button></div></div>`;
    });

    setupProductDnD();
    renderOrdersKanban();
}

function openProductEdit(id) {
    const box = document.getElementById(`edit-product-${id}`);
    if (box) box.classList.toggle('hidden');
}

async function quickToggleProduct(id, isActive) {
    try {
        await fetchJSON(`/api/products/${id}/status`, {
            method: 'PATCH',
            body: JSON.stringify({ is_active: isActive })
        });
        storeState.products = await fetchJSON('/api/products');
        renderAdminList();
        renderProducts();
    } catch (error) {
        alert(error.message);
    }
}

async function saveProductEdit(id) {
    const name = document.getElementById(`edit-name-${id}`).value.trim();
    const price = parseInt(document.getElementById(`edit-price-${id}`).value, 10);
    const img = document.getElementById(`edit-img-${id}`).value.trim();
    const categoryId = document.getElementById(`edit-category-${id}`).value;
    const isActive = parseInt(document.getElementById(`edit-active-${id}`).value, 10);

    try {
        await fetchJSON(`/api/products/${id}`, {
            method: 'PATCH',
            body: JSON.stringify({ name, price, img, category_id: categoryId || null, is_active: isActive })
        });
        storeState.products = await fetchJSON('/api/products');
        renderAdminList();
        renderProducts();
    } catch (error) {
        alert(error.message);
    }
}
function closeMediaPicker() {
    const modal = document.getElementById('media-picker-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function loadMediaLibrary() {
    mediaState.items = await fetchJSON('/api/media');
    const grid = document.getElementById('media-library-grid');
    if (!grid) return mediaState.items;

    if (!mediaState.items.length) {
        grid.innerHTML = '<p class="text-sm text-gray-500 col-span-full">No hay archivos en la librería.</p>';
        return mediaState.items;
    }

    grid.innerHTML = mediaState.items.map(item => `
        <div class="border border-baby-blue-light rounded-xl p-2 bg-baby-cream">
            <button type="button" onclick="selectMediaFile(${item.id})" class="block w-full text-left">
                <img src="${item.file_path}" class="w-full h-24 object-cover rounded-lg mb-2" alt="${item.file_name}">
                <p class="text-xs truncate font-semibold">${item.file_name}</p>
            </button>
            ${hasRole('admin', 'gestion') ? `<button type="button" onclick="deleteMediaFile(${item.id})" class="mt-2 w-full text-xs text-red-500">Eliminar</button>` : ''}
        </div>
    `).join('');

    return mediaState.items;
}

async function openMediaPicker(onSelect) {
    mediaState.onSelect = typeof onSelect === 'function' ? onSelect : null;
    const modal = document.getElementById('media-picker-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    if (!deviceSupportsCapture()) {
        showInlineUploadError('media-picker-upload-error', 'Captura por cámara no disponible en este dispositivo. Puedes subir una imagen existente.');
    } else {
        showInlineUploadError('media-picker-upload-error');
    }

    try {
        await loadMediaLibrary();
    } catch (error) {
        alert(error.message);
    }
}

function selectMediaFile(id) {
    const selected = mediaState.items.find(item => Number(item.id) === Number(id));
    if (!selected) return;
    if (mediaState.onSelect) mediaState.onSelect(selected);
    closeMediaPicker();
}

async function deleteMediaFile(id) {
    if (!confirm('¿Eliminar este archivo de la librería?')) return;

    try {
        await fetchJSON(`/api/media/${id}`, { method: 'DELETE' });
        await loadMediaLibrary();
    } catch (error) {
        alert(error.message);
    }
}

async function uploadMediaFromFile(file, errorElementId = 'media-picker-upload-error') {
    if (!file) {
        showInlineUploadError(errorElementId, 'Selecciona una imagen para subir.');
        return;
    }

    try {
        const prepared = await prepareImageForUpload(file);
        await uploadAdminProductImage(prepared.uploadFile);
        await loadMediaLibrary();
        showInlineUploadError(errorElementId);
    } catch (error) {
        showInlineUploadError(errorElementId, error.message);
    }
}

async function mediaPickerUploadSelected() {
    const input = document.getElementById('media-picker-upload-file');
    if (!input) return;
    await uploadMediaFromFile(input.files?.[0]);
    input.value = '';
}

async function mediaPickerCaptureAndUpload(file) {
    if (!file) return;
    await uploadMediaFromFile(file);
    const captureInput = document.getElementById('media-picker-upload-camera');
    if (captureInput) captureInput.value = '';
}

function triggerMediaPickerCameraCapture() {
    if (!deviceSupportsCapture()) {
        showInlineUploadError('media-picker-upload-error', 'Tu dispositivo no soporta captura por cámara desde el navegador.');
        return;
    }

    const captureInput = document.getElementById('media-picker-upload-camera');
    if (!captureInput) return;
    showInlineUploadError('media-picker-upload-error');
    captureInput.click();
}

function toggleAdminImageSource() {
    const source = document.getElementById('admin-prod-image-source');
    const urlInput = document.getElementById('admin-prod-img-url');
    const fileInput = document.getElementById('admin-prod-img-file');
    const cameraButton = document.getElementById('admin-prod-camera-btn');
    if (!source || !urlInput || !fileInput) return;

    const useUpload = source.value === 'upload';
    const supportsCapture = deviceSupportsCapture();
    urlInput.classList.toggle('hidden', useUpload);
    fileInput.classList.toggle('hidden', !useUpload);
    if (cameraButton) cameraButton.classList.toggle('hidden', !useUpload);

    if (useUpload && !supportsCapture) {
        showInlineUploadError('admin-upload-error', 'Tu dispositivo/navegador no soporta captura directa desde cámara. Usa “Subir archivo”.');
    } else {
        showInlineUploadError('admin-upload-error');
    }
}

async function triggerAdminCameraCapture() {
    if (!deviceSupportsCapture()) {
        showInlineUploadError('admin-upload-error', 'No se pudo abrir la cámara en este dispositivo.');
        return;
    }

    const captureInput = document.getElementById('admin-prod-img-file-camera');
    if (!captureInput) return;
    captureInput.click();
}

async function adminHandleCameraCapture(file) {
    if (!file) return;
    const imageFileInput = document.getElementById('admin-prod-img-file');
    if (!imageFileInput) return;

    try {
        const prepared = await prepareImageForUpload(file);
        const transfer = new DataTransfer();
        transfer.items.add(prepared.uploadFile);
        imageFileInput.files = transfer.files;
        setImagePreview('admin-prod-img-preview', prepared.previewUrl);
        showInlineUploadError('admin-upload-error');
    } catch (error) {
        showInlineUploadError('admin-upload-error', error.message);
    }
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
    const categorySelect = document.getElementById('admin-prod-category');

    try {
        let imgPath = imageUrlInput.value.trim();

        if (sourceSelect.value === 'upload') {
            const file = imageFileInput.files[0];
            if (!file) throw new Error('Selecciona una imagen para subir.');
            const prepared = await prepareImageForUpload(file);
            imgPath = await uploadAdminProductImage(prepared.uploadFile);
            showInlineUploadError('admin-upload-error');
        }

        await fetchJSON('/api/products', {
            method: 'POST',
            body: JSON.stringify({
                name: nameInput.value,
                price: parseInt(priceInput.value, 10),
                img: imgPath,
                category_id: categorySelect.value || null
            })
        });

        nameInput.value = '';
        priceInput.value = '';
        imageUrlInput.value = '';
        imageFileInput.value = '';
        setImagePreview('admin-prod-img-preview', '');
        sourceSelect.value = 'url';
        categorySelect.value = '';
        toggleAdminImageSource();

        await initStore();
        renderAdminList();
        alert('¡Producto añadido con éxito!');
    } catch (error) {
        alert(error.message);
    }
}


async function adminCreateCategory() {
    const input = document.getElementById('admin-new-category-name');
    const name = input.value.trim();
    if (!name) return;

    try {
        await fetchJSON('/api/categories', {
            method: 'POST',
            body: JSON.stringify({ name })
        });

        const categories = await fetchJSON('/api/categories');
        storeState.categories = categories;
        renderCategoryFilters();
        input.value = '';
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
            flyerState.templateId = parsed.templateId || 'custom';
            flyerState.bgColor = parsed.bgColor || '#fffaf0';
            flyerState.elements = Array.isArray(parsed.elements) ? parsed.elements.map(normalizeTemplateElement) : [];
        } catch (_) {}
    }
    renderFlyerSelectors();
    renderFlyerProjectList();
    if (flyerState.id) {
        await loadFlyerExports(flyerState.id);
    } else {
        renderFlyerExports([]);
    }
}

function renderFlyerSelectors() {
    const product = document.getElementById('flyer-product-select');
    const templateSelect = document.getElementById('flyer-template-select');
    if (!product) return;

    product.innerHTML = '<option value="">Producto relacionado (opcional)</option>';
    storeState.products.forEach(p => product.innerHTML += `<option value="${p.id}">${p.name}</option>`);

    if (templateSelect) {
        const templates = getTemplateCatalog();
        const options = Object.values(templates)
            .map(template => `<option value="${template.id}">${template.name}</option>`)
            .join('');
        templateSelect.innerHTML = '<option value="custom">-- Diseño libre --</option>' + options;
        templateSelect.value = flyerState.templateId || 'custom';
        templateSelect.onchange = (event) => {
            loadTemplateInBuilder(event.target.value || 'custom');
            renderFlyerBuilder();
        };
    }

}

async function loadFlyerProject(flyerId) {
    if (!flyerId) return;
    const data = await fetchJSON(`/api/flyers/${flyerId}`);
    flyerState.id = Number(data.id);
    flyerState.title = data.title;
    flyerState.productId = data.product_id ? Number(data.product_id) : null;
    flyerState.templateId = data.template_id || 'custom';
    flyerState.bgColor = data.bg_color || '#fffaf0';
    const parsedElements = JSON.parse(data.layout_json || '[]');
    flyerState.elements = Array.isArray(parsedElements) ? parsedElements.map(normalizeTemplateElement) : [];
    flyerState.selectedElementId = flyerState.elements[0]?.id || null;
    renderFlyerBuilder();
    renderFlyerProjectList();
    await loadFlyerExports(flyerState.id);
}

function renderFlyerProjectList() {
    const projectList = document.getElementById('flyer-project-list');
    if (!projectList) return;

    if (!storeState.flyers.length) {
        projectList.innerHTML = '<p>No hay flyers guardados.</p>';
        return;
    }

    projectList.innerHTML = storeState.flyers.map(flyer => {
        const isActive = Number(flyerState.id) === Number(flyer.id);
        return `<button onclick="openFlyerFromExplorer(${flyer.id})" class="w-full text-left flex items-center justify-between border rounded-xl p-3 ${isActive ? 'border-baby-pink bg-baby-cream' : 'border-baby-blue-light'}"><span class="font-semibold">${flyer.title}</span><span class="text-xs text-gray-500">#${flyer.id}</span></button>`;
    }).join('');
}

async function openFlyerFromExplorer(flyerId) {
    await loadFlyerProject(flyerId);
    showFlyerSubTab('editor');
}

function showFlyerSubTab(subTab) {
    const nextTab = subTab === 'explorer' ? 'explorer' : 'editor';
    flyerState.subTab = nextTab;

    const editorSection = document.getElementById('flyer-subtab-editor');
    const explorerSection = document.getElementById('flyer-subtab-explorer');
    const editorButton = document.getElementById('flyer-subtab-btn-editor');
    const explorerButton = document.getElementById('flyer-subtab-btn-explorer');

    if (editorSection) editorSection.classList.toggle('hidden', nextTab !== 'editor');
    if (explorerSection) explorerSection.classList.toggle('hidden', nextTab !== 'explorer');

    if (editorButton) {
        editorButton.classList.toggle('bg-baby-blue', nextTab === 'editor');
        editorButton.classList.toggle('text-white', nextTab === 'editor');
    }

    if (explorerButton) {
        explorerButton.classList.toggle('bg-baby-blue', nextTab === 'explorer');
        explorerButton.classList.toggle('text-white', nextTab === 'explorer');
    }

    if (nextTab === 'explorer') {
        renderFlyerProjectList();
    }
}

function flyerNew() {
    flyerState.id = null;
    flyerState.title = '';
    flyerState.productId = null;
    loadTemplateInBuilder(flyerState.templateId || 'custom');
    renderFlyerBuilder();
    renderFlyerProjectList();
    renderFlyerExports([]);
}

function flyerAddElement(type) {
    const id = 'e' + Date.now();
    flyerState.elements.push(type === 'text'
        ? { id, type: 'text', value: 'Texto', x: 20, y: 20, w: 180, h: 40, styles: {} }
        : { id, type: 'image', value: '', x: 40, y: 80, w: 160, h: 160, styles: { objectFit: 'cover' } });
    flyerState.selectedElementId = id;
    renderFlyerBuilder();
}

async function flyerUploadElementImage(id, file) {
    if (!file) return;

    try {
        const prepared = await prepareImageForUpload(file);
        const path = await uploadAdminProductImage(prepared.uploadFile);
        showInlineUploadError('flyer-upload-error');
        flyerUpdateElement(id, path);
    } catch (error) {
        showInlineUploadError('flyer-upload-error', error.message);
    }
}


function triggerFlyerCameraCapture() {
    if (!deviceSupportsCapture()) {
        showInlineUploadError('flyer-upload-error', 'Tu dispositivo no soporta captura por cámara en este navegador.');
        return;
    }

    const input = document.getElementById('flyer-camera-input');
    if (!input) return;
    showInlineUploadError('flyer-upload-error');
    input.click();
}

async function flyerCaptureSelectedImage(file) {
    if (!file) return;

    try {
        const prepared = await prepareImageForUpload(file);
        const path = await uploadAdminProductImage(prepared.uploadFile);
        showInlineUploadError('flyer-upload-error');
        flyerSetImageFromMedia(path);
    } catch (error) {
        showInlineUploadError('flyer-upload-error', error.message);
    } finally {
        const input = document.getElementById('flyer-camera-input');
        if (input) input.value = '';
    }
}

function flyerSetImageFromMedia(path) {
    let selectedElement = flyerState.elements.find(el => el.id === flyerState.selectedElementId && el.type === 'image');

    if (!selectedElement) {
        flyerAddElement('image');
        selectedElement = flyerState.elements.find(el => el.id === flyerState.selectedElementId && el.type === 'image');
    }

    if (!selectedElement) return;
    selectedElement.value = path;
    renderFlyerBuilder();
}

async function flyerHandleCanvasDrop(file) {
    if (!file || !file.type.startsWith('image/')) {
        showInlineUploadError('flyer-upload-error', 'Solo puedes soltar imágenes en el canvas.');
        return;
    }

    try {
        const prepared = await prepareImageForUpload(file);
        const path = await uploadAdminProductImage(prepared.uploadFile);
        showInlineUploadError('flyer-upload-error');
        flyerSetImageFromMedia(path);
    } catch (error) {
        showInlineUploadError('flyer-upload-error', error.message);
    }
}

function clampFlyerElement(el) {
    const canvas = document.getElementById('flyer-canvas');
    if (!canvas || !el) return;
    const maxX = Math.max(0, canvas.clientWidth - el.w);
    const maxY = Math.max(0, canvas.clientHeight - el.h);
    el.x = Math.max(0, Math.min(el.x, maxX));
    el.y = Math.max(0, Math.min(el.y, maxY));
    el.w = Math.max(40, Math.min(el.w, canvas.clientWidth));
    el.h = Math.max(40, Math.min(el.h, canvas.clientHeight));
}

function beginFlyerDrag(event, id) {
    event.preventDefault();
    const canvas = document.getElementById('flyer-canvas');
    const element = flyerState.elements.find(item => item.id === id);
    if (!canvas || !element) return;

    flyerState.selectedElementId = id;
    const rect = canvas.getBoundingClientRect();
    const offsetX = event.clientX - rect.left - element.x;
    const offsetY = event.clientY - rect.top - element.y;

    const onMove = (moveEvent) => {
        element.x = moveEvent.clientX - rect.left - offsetX;
        element.y = moveEvent.clientY - rect.top - offsetY;
        clampFlyerElement(element);
        renderFlyerBuilder();
    };

    const onUp = () => {
        window.removeEventListener('mousemove', onMove);
        window.removeEventListener('mouseup', onUp);
    };

    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
}

function beginFlyerResize(event, id) {
    event.preventDefault();
    event.stopPropagation();
    const canvas = document.getElementById('flyer-canvas');
    const element = flyerState.elements.find(item => item.id === id);
    if (!canvas || !element) return;

    const rect = canvas.getBoundingClientRect();
    const startW = element.w;
    const startH = element.h;
    const startX = event.clientX;
    const startY = event.clientY;

    const onMove = (moveEvent) => {
        element.w = startW + (moveEvent.clientX - startX);
        element.h = startH + (moveEvent.clientY - startY);
        clampFlyerElement(element);
        renderFlyerBuilder();
    };

    const onUp = () => {
        window.removeEventListener('mousemove', onMove);
        window.removeEventListener('mouseup', onUp);
    };

    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
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
    const templateSelect = document.getElementById('flyer-template-select');
    if (templateSelect) templateSelect.value = flyerState.templateId || 'custom';

    canvas.innerHTML = '';
    canvas.style.backgroundColor = flyerState.bgColor || '#fffaf0';
    canvas.ondragover = (event) => {
        event.preventDefault();
    };
    canvas.ondrop = async (event) => {
        event.preventDefault();
        const file = event.dataTransfer?.files?.[0];
        if (!file) return;
        await flyerHandleCanvasDrop(file);
    };
    flyerState.elements.forEach(el => {
        const node = document.createElement(el.type === 'image' ? 'img' : 'div');
        const styles = el.styles || {};
        node.className = `absolute border p-1 ${flyerState.selectedElementId === el.id ? 'border-baby-pink' : 'border-transparent'}`;
        node.style.left = `${el.x}px`;
        node.style.top = `${el.y}px`;
        node.style.width = `${el.w}px`;
        node.style.height = `${el.h}px`;
        if (styles.backgroundColor) node.style.backgroundColor = styles.backgroundColor;
        if (styles.borderRadius) node.style.borderRadius = styles.borderRadius;

        if (el.type === 'image') {
            node.src = el.value || el.src || 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="320" height="320"%3E%3Crect width="100%25" height="100%25" fill="%23f8fafc"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" dominant-baseline="middle" fill="%2394a3b8" font-size="22" font-family="Arial"%3ECargar imagen%3C/text%3E%3C/svg%3E';
            node.style.objectFit = styles.objectFit || 'cover';
        } else {
            node.textContent = el.value;
            node.style.fontSize = styles.fontSize || '24px';
            node.style.fontWeight = styles.fontWeight || 'normal';
            node.style.color = styles.color || '#111827';
            node.style.textAlign = styles.textAlign || 'left';
            node.style.display = 'flex';
            node.style.alignItems = 'center';
            node.style.justifyContent = styles.textAlign === 'center' ? 'center' : (styles.textAlign === 'right' ? 'flex-end' : 'flex-start');
        }
        node.onclick = () => { flyerState.selectedElementId = el.id; renderFlyerBuilder(); };
        node.onmousedown = (event) => beginFlyerDrag(event, el.id);
        if (flyerState.selectedElementId === el.id) {
            const resize = document.createElement('button');
            resize.type = 'button';
            resize.className = 'absolute bottom-0 right-0 h-4 w-4 rounded-full bg-baby-pink border border-white';
            resize.onmousedown = (event) => beginFlyerResize(event, el.id);
            node.appendChild(resize);
        }
        canvas.appendChild(node);
    });

    elementsList.innerHTML = '';
    flyerState.elements.forEach(el => {
        if (el.type === 'image') {
            elementsList.innerHTML += `<div class="p-2 rounded-xl border ${flyerState.selectedElementId === el.id ? 'border-baby-pink' : 'border-baby-blue-light'}"><div class="text-xs mb-1">IMAGEN</div><input class="w-full p-2 border rounded mb-2" placeholder="URL opcional" value="${el.value || ''}" oninput="flyerUpdateElement('${el.id}', this.value)"><input type="file" accept="image/*" capture="environment" class="w-full p-2 border rounded" onchange="flyerUploadElementImage('${el.id}', this.files[0])"><button type="button" onclick="openMediaPicker((item) => flyerUpdateElement('${el.id}', item.file_path))" class="w-full mt-2 px-3 py-2 text-xs rounded-full border border-baby-blue-light">Elegir desde File Manager</button></div>`;
            return;
        }

        elementsList.innerHTML += `<div class="p-2 rounded-xl border ${flyerState.selectedElementId === el.id ? 'border-baby-pink' : 'border-baby-blue-light'}"><div class="text-xs mb-1">TEXTO</div><input class="w-full p-2 border rounded" value="${el.value}" oninput="flyerUpdateElement('${el.id}', this.value)"></div>`;
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
    const payload = { id: flyerState.id, title: flyerState.title, product_id: flyerState.productId, template_id: flyerState.templateId, bg_color: flyerState.bgColor, layout: flyerState.elements.map(normalizeTemplateElement) };
    const saved = await fetchJSON('/api/flyers', { method: 'POST', body: JSON.stringify(payload) });
    flyerState.id = Number(saved.id);
    localStorage.setItem('flyerDraftCache', JSON.stringify(flyerState));
    await initFlyers();
    await loadFlyerExports(flyerState.id);
    renderFlyerProjectList();
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



async function loadUsers() {
    if (!hasRole('admin')) {
        storeState.users = [];
        return;
    }

    try {
        storeState.users = await fetchJSON('/api/users');
    } catch (error) {
        console.error(error);
        storeState.users = [];
    }

    renderUsersTable();
}

function renderUsersTable() {
    const tbody = document.getElementById('users-table-body');
    if (!tbody) return;

    if (!storeState.users.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-gray-500">No hay usuarios cargados.</td></tr>';
        return;
    }

    tbody.innerHTML = storeState.users.map(user => `
        <tr class="border-b border-baby-blue-light">
            <td class="py-2 pr-3">${user.name}</td>
            <td class="py-2 pr-3">${user.email}</td>
            <td class="py-2 pr-3"><span class="px-2 py-1 rounded-full bg-baby-blue-light text-xs font-semibold">${user.role}</span></td>
            <td class="py-2 text-right">
                <button onclick="editUser(${user.id})" class="px-3 py-1 text-xs rounded-full bg-baby-pink text-white">Editar</button>
            </td>
        </tr>
    `).join('');
}

function editUser(userId) {
    const user = storeState.users.find(item => Number(item.id) === Number(userId));
    if (!user) return;

    storeState.editingUserId = Number(user.id);
    document.getElementById('users-form-title').textContent = `Editar usuario #${user.id}`;
    document.getElementById('user-form-name').value = user.name || '';
    document.getElementById('user-form-email').value = user.email || '';
    document.getElementById('user-form-role').value = user.role || 'gestion';
    document.getElementById('user-form-password').value = '';
    document.getElementById('user-form-cancel').classList.remove('hidden');
}

function resetUserForm() {
    storeState.editingUserId = null;
    const title = document.getElementById('users-form-title');
    const name = document.getElementById('user-form-name');
    const email = document.getElementById('user-form-email');
    const role = document.getElementById('user-form-role');
    const password = document.getElementById('user-form-password');
    const cancel = document.getElementById('user-form-cancel');

    if (title) title.textContent = 'Alta de usuario';
    if (name) name.value = '';
    if (email) email.value = '';
    if (role) role.value = 'gestion';
    if (password) password.value = '';
    if (cancel) cancel.classList.add('hidden');
}

async function submitUserForm() {
    const name = document.getElementById('user-form-name').value.trim();
    const email = document.getElementById('user-form-email').value.trim();
    const role = document.getElementById('user-form-role').value;
    const password = document.getElementById('user-form-password').value;

    if (!name || !email || !role) {
        alert('Nombre, email y rol son obligatorios.');
        return;
    }

    const payload = { name, email, role };
    const isEditing = Number.isInteger(storeState.editingUserId) && storeState.editingUserId > 0;

    if (!isEditing || password.trim() !== '') {
        payload.password = password;
    }

    try {
        if (isEditing) {
            await fetchJSON(`/api/users/${storeState.editingUserId}`, {
                method: 'PATCH',
                body: JSON.stringify(payload),
            });
        } else {
            await fetchJSON('/api/users', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        }

        await loadUsers();
        resetUserForm();
    } catch (error) {
        alert(error.message);
    }
}

const imageSourceSelect = document.getElementById('admin-prod-image-source');
if (imageSourceSelect) {
    imageSourceSelect.addEventListener('change', toggleAdminImageSource);
    toggleAdminImageSource();
}


const adminImageFileInput = document.getElementById('admin-prod-img-file');
if (adminImageFileInput) {
    adminImageFileInput.addEventListener('change', async (event) => {
        const file = event.target.files?.[0];
        if (!file) {
            setImagePreview('admin-prod-img-preview', '');
            return;
        }

        try {
            const prepared = await prepareImageForUpload(file);
            if (prepared.compressed) {
                const transfer = new DataTransfer();
                transfer.items.add(prepared.uploadFile);
                event.target.files = transfer.files;
            }
            setImagePreview('admin-prod-img-preview', prepared.previewUrl);
            showInlineUploadError('admin-upload-error');
        } catch (error) {
            showInlineUploadError('admin-upload-error', error.message);
        }
    });
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

function renderSettingsForm() {
    document.getElementById('settings-app-name').value = storeState.config.appName || '';
    document.getElementById('settings-app-logo').value = storeState.config.appLogo || '';
    document.getElementById('settings-app-icon').value = storeState.config.appIcon || '';
    document.getElementById('settings-whatsapp').value = storeState.config.whatsappNumber || '';
    document.getElementById('settings-address').value = storeState.config.address || '';
    document.getElementById('settings-google-maps').value = storeState.config.googleMapsEmbed || '';

    const linksWrap = document.getElementById('settings-social-links');
    const links = Array.isArray(storeState.config.socialLinks) ? storeState.config.socialLinks : [];
    linksWrap.innerHTML = links.map((item, idx) => socialLinkRowTemplate(item, idx)).join('');
    if (!links.length) addSocialLinkRow();

    renderSlidesEditor();
}

function socialLinkRowTemplate(item = {}, idx = Date.now()) {
    return `<div class="grid grid-cols-12 gap-2" data-social-row="${idx}">
        <input data-social-field="label" value="${item.label || ''}" class="col-span-3 p-2 border rounded-full" placeholder="Etiqueta">
        <input data-social-field="url" value="${item.url || ''}" class="col-span-6 p-2 border rounded-full" placeholder="URL">
        <input data-social-field="icon" value="${item.icon || 'fa-brands fa-instagram'}" class="col-span-2 p-2 border rounded-full" placeholder="Icono">
        <button type="button" onclick="this.closest('[data-social-row]').remove()" class="col-span-1 text-red-500">✕</button>
    </div>`;
}

function addSocialLinkRow() {
    const linksWrap = document.getElementById('settings-social-links');
    if (!linksWrap) return;
    linksWrap.insertAdjacentHTML('beforeend', socialLinkRowTemplate({}, Date.now() + Math.random()));
}

function collectSocialLinks() {
    return [...document.querySelectorAll('#settings-social-links [data-social-row]')].map(row => ({
        label: row.querySelector('[data-social-field="label"]').value.trim(),
        url: row.querySelector('[data-social-field="url"]').value.trim(),
        icon: row.querySelector('[data-social-field="icon"]').value.trim() || 'fa-solid fa-link'
    })).filter(item => item.label && item.url);
}

async function saveSettings() {
    const payload = {
        appName: document.getElementById('settings-app-name').value.trim(),
        appLogo: document.getElementById('settings-app-logo').value.trim(),
        appIcon: document.getElementById('settings-app-icon').value.trim(),
        whatsappNumber: document.getElementById('settings-whatsapp').value.trim(),
        address: document.getElementById('settings-address').value.trim(),
        googleMapsEmbed: document.getElementById('settings-google-maps').value.trim(),
        socialLinks: collectSocialLinks()
    };

    try {
        const updated = await fetchJSON('/api/settings', { method: 'PATCH', body: JSON.stringify(payload) });
        storeState.config = normalizeConfig(updated);
        applyBranding();
        renderStoreContact();
        alert('Ajustes guardados correctamente.');
    } catch (error) {
        alert(error.message);
    }
}

function renderSlidesEditor() {
    const list = document.getElementById('settings-slides-list');
    if (!list) return;
    list.innerHTML = storeState.slides.map(slide => `<div class="grid lg:grid-cols-12 gap-2 items-center bg-baby-cream p-3 rounded-xl">
        <input id="slide-image-${slide.id}" value="${slide.image}" class="lg:col-span-5 p-2 border rounded-full">
        <input id="slide-text-${slide.id}" value="${slide.text}" class="lg:col-span-4 p-2 border rounded-full">
        <input id="slide-order-${slide.id}" value="${slide.sort_order || 0}" type="number" class="lg:col-span-1 p-2 border rounded-full">
        <button onclick="updateSlideItem(${slide.id})" class="lg:col-span-1 px-2 py-2 bg-baby-blue-light rounded-full">Guardar</button>
        <button onclick="deleteSlideItem(${slide.id})" class="lg:col-span-1 px-2 py-2 bg-red-100 text-red-700 rounded-full">Borrar</button>
    </div>`).join('');
}

async function createSlideFromForm() {
    const image = document.getElementById('slide-form-image').value.trim();
    const text = document.getElementById('slide-form-text').value.trim();
    const sort_order = Number(document.getElementById('slide-form-order').value || 0);
    try {
        await fetchJSON('/api/slides', { method: 'POST', body: JSON.stringify({ image, text, sort_order }) });
        storeState.slides = await fetchJSON('/api/slides');
        renderSlidesEditor();
        renderSlider();
    } catch (error) { alert(error.message); }
}

async function updateSlideItem(id) {
    const image = document.getElementById(`slide-image-${id}`).value.trim();
    const text = document.getElementById(`slide-text-${id}`).value.trim();
    const sort_order = Number(document.getElementById(`slide-order-${id}`).value || 0);
    try {
        await fetchJSON(`/api/slides/${id}`, { method: 'PATCH', body: JSON.stringify({ image, text, sort_order }) });
        storeState.slides = await fetchJSON('/api/slides');
        renderSlidesEditor();
        renderSlider();
    } catch (error) { alert(error.message); }
}

async function deleteSlideItem(id) {
    if (!confirm('¿Eliminar slide?')) return;
    try {
        await fetchJSON(`/api/slides/${id}`, { method: 'DELETE' });
        storeState.slides = await fetchJSON('/api/slides');
        renderSlidesEditor();
        renderSlider();
    } catch (error) { alert(error.message); }
}
