const storeState = {
    products: [],
    slides: [],
    config: {
        whatsappNumber: '573001234567',
        currency: '$'
    }
};

let cart = [];

async function fetchJSON(url, options = {}) {
    const response = await fetch(url, {
        headers: { 'Content-Type': 'application/json' },
        ...options
    });

    const payload = await response.json();
    if (!response.ok) {
        throw new Error(payload.error || 'Error de servidor');
    }

    return payload;
}

async function initStore() {
    const data = await fetchJSON('/api/bootstrap');
    storeState.products = data.products || [];
    storeState.slides = data.slides || [];
    storeState.config = { ...storeState.config, ...(data.config || {}) };

    renderSlider();
    renderProducts();
    updateCartUI();
}

function renderSlider() {
    const sliderWrapper = document.getElementById('slider-wrapper');
    sliderWrapper.innerHTML = '';

    storeState.slides.forEach(slide => {
        sliderWrapper.innerHTML += `
            <div class="min-w-full relative h-full">
                <img src="${slide.image}" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-black/40 flex items-center justify-center p-4">
                    <h2 class="text-white text-3xl md:text-5xl font-bold drop-shadow-lg text-center bg-baby-pink/50 px-6 py-2 rounded-full">${slide.text}</h2>
                </div>
            </div>`;
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
        grid.innerHTML += `
            <div class="bg-white p-4 rounded-3xl shadow-md product-card border-2 border-baby-blue-light flex flex-col">
                <div class="w-full h-36 bg-baby-cream rounded-2xl mb-4 flex items-center justify-center overflow-hidden">
                    <img src="${p.img}" class="h-full object-contain">
                </div>
                <h3 class="text-base font-semibold text-baby-text h-12 overflow-hidden">${p.name}</h3>
                <p class="text-2xl font-bold text-baby-pink mt-2">${storeState.config.currency}${Number(p.price).toLocaleString()}</p>
                <button onclick="addToCart(${p.id})" class="mt-4 w-full bg-baby-green text-baby-text py-3 rounded-full text-sm font-bold hover:bg-green-300 active:scale-95 transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-plus-circle"></i> Agregar
                </button>
            </div>`;
    });
}

function toggleCart() {
    document.getElementById('cart-drawer').classList.toggle('translate-x-full');
    document.getElementById('cart-overlay').classList.toggle('hidden');
}

function addToCart(id) {
    const product = storeState.products.find(p => Number(p.id) === Number(id));
    if (!product) return;

    const exists = cart.find(item => Number(item.id) === Number(id));
    if (exists) {
        exists.qty++;
    } else {
        cart.push({ ...product, qty: 1 });
    }

    updateCartUI();
}

function removeFromCart(id) {
    cart = cart.filter(item => Number(item.id) !== Number(id));
    updateCartUI();
}

function updateCartUI() {
    const list = document.getElementById('cart-items');
    const countLabel = document.getElementById('cart-count');
    const totalLabel = document.getElementById('cart-total');

    list.innerHTML = '';
    let total = 0;
    let count = 0;

    cart.forEach(item => {
        total += Number(item.price) * item.qty;
        count += item.qty;
        list.innerHTML += `
            <div class="flex items-center gap-3 bg-white p-3 rounded-2xl shadow border border-baby-blue-light">
                <img src="${item.img}" class="w-16 h-16 object-contain rounded-lg bg-baby-cream">
                <div class="flex-1">
                    <p class="text-sm font-bold text-baby-text">${item.name}</p>
                    <p class="text-xs text-gray-500">${item.qty} x ${storeState.config.currency}${Number(item.price).toLocaleString()}</p>
                </div>
                <button onclick="removeFromCart(${item.id})" class="text-red-300 p-2 hover:text-red-500"><i class="fa-solid fa-trash-can text-lg"></i></button>
            </div>`;
    });

    countLabel.innerText = count;
    totalLabel.innerText = `${storeState.config.currency}${total.toLocaleString()}`;
}

function sendOrder() {
    const name = document.getElementById('cust-name').value;
    const address = document.getElementById('cust-address').value;

    if (cart.length === 0) return alert('¡El carrito está vacío, dulzura!');
    if (!name || !address) return alert('Por favor, completa tus datos para el envío');

    let message = `*Nuevo Pedido - Pañalería y Algo Más*%0A%0A`;
    message += `*Cliente:* ${name}%0A`;
    message += `*Dirección:* ${address}%0A%0A`;
    message += '*Productos:*%0A';

    cart.forEach(item => {
        message += `- ${item.qty}x ${item.name} (${storeState.config.currency}${Number(item.price) * item.qty})%0A`;
    });

    const total = document.getElementById('cart-total').innerText;
    message += `%0A*TOTAL: ${total}*`;

    const url = `https://wa.me/${storeState.config.whatsappNumber}?text=${message}`;
    window.open(url, '_blank');
}

function showTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('nav button').forEach(el => el.classList.remove('active-tab'));

    if (tabName === 'store') {
        document.getElementById('section-store').classList.remove('hidden');
        document.getElementById('tab-store').classList.add('active-tab');
    } else {
        document.getElementById('section-admin').classList.remove('hidden');
        document.getElementById('tab-admin').classList.add('active-tab');
        renderAdminList();
    }
}

function renderAdminList() {
    const list = document.getElementById('admin-product-list');
    list.innerHTML = '';

    storeState.products.forEach(p => {
        list.innerHTML += `
            <div class="flex items-center gap-3 bg-baby-cream p-3 rounded-xl border border-baby-blue-light hover:border-baby-blue">
                <img src="${p.img}" class="w-12 h-12 object-contain bg-white rounded-lg">
                <div class="flex-1">
                    <p class="font-bold text-sm text-baby-text">${p.name}</p>
                    <p class="text-xs text-baby-pink font-bold">${storeState.config.currency}${Number(p.price).toLocaleString()}</p>
                </div>
                <button onclick="adminDeleteProduct(${p.id})" class="text-red-400 p-2 hover:text-red-600 active:scale-95"><i class="fa-solid fa-trash-can"></i></button>
            </div>`;
    });
}

async function adminAddProduct() {
    const nameInput = document.getElementById('admin-prod-name');
    const priceInput = document.getElementById('admin-prod-price');
    const imgInput = document.getElementById('admin-prod-img');

    const payload = {
        name: nameInput.value,
        price: parseInt(priceInput.value, 10),
        img: imgInput.value
    };

    try {
        await fetchJSON('/api/products', { method: 'POST', body: JSON.stringify(payload) });
        nameInput.value = '';
        priceInput.value = '';
        imgInput.value = '';
        await initStore();
        renderAdminList();
        alert('¡Producto añadido con éxito!');
    } catch (error) {
        alert(error.message);
    }
}

async function adminDeleteProduct(id) {
    if (!confirm('¿Estás seguro de eliminar este producto?')) return;

    try {
        await fetchJSON(`/api/products/${id}`, { method: 'DELETE' });
        cart = cart.filter(item => Number(item.id) !== Number(id));
        await initStore();
        renderAdminList();
    } catch (error) {
        alert(error.message);
    }
}

initStore().catch(error => {
    console.error(error);
    alert('No se pudo cargar la tienda. Revisa la conexión con la base de datos.');
});
