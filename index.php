<?php
session_start();

$configFile = __DIR__ . '/config.php';
$installError = '';

if (!file_exists($configFile)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['install'] ?? '') === '1') {
        $dbHost = trim($_POST['db_host'] ?? 'localhost');
        $dbPort = trim($_POST['db_port'] ?? '3306');
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = $_POST['db_pass'] ?? '';
        $appName = trim($_POST['app_name'] ?? 'CompraExpress');
        $whatsapp = trim($_POST['whatsapp'] ?? '');
        $currency = trim($_POST['currency'] ?? '$');
        $adminName = trim($_POST['admin_name'] ?? 'Admin');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminPass = $_POST['admin_pass'] ?? '';

        try {
            if (!$dbName || !$dbUser || !$adminEmail || strlen($adminPass) < 6) {
                throw new RuntimeException('Completa datos de BD y admin (mínimo 6 caracteres de contraseña).');
            }
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            $configData = var_export([
                'app_name' => $appName,
                'whatsapp_number' => preg_replace('/\D+/', '', $whatsapp),
                'currency' => $currency,
                'db' => [
                    'host' => $dbHost,
                    'port' => $dbPort,
                    'name' => $dbName,
                    'user' => $dbUser,
                    'pass' => $dbPass,
                ],
            ], true);
            file_put_contents($configFile, "<?php\nreturn {$configData};\n");

            require __DIR__ . '/db.php';
            $pdo = db_connect($config);
            initialize_schema($pdo);

            $stmt = $pdo->prepare('INSERT INTO admins(name,email,password_hash) VALUES(?,?,?)');
            $stmt->execute([$adminName, $adminEmail, password_hash($adminPass, PASSWORD_DEFAULT)]);
            $_SESSION['admin_id'] = (int)$pdo->lastInsertId();
            $_SESSION['admin_name'] = $adminName;

            header('Location: index.php');
            exit;
        } catch (Throwable $e) {
            $installError = $e->getMessage();
        }
    }
    ?>
    <!doctype html>
    <html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script><title>Instalador CompraExpress</title></head>
    <body class="bg-slate-100 min-h-screen p-6">
    <div class="max-w-4xl mx-auto bg-white rounded-2xl shadow p-6 space-y-4">
    <h1 class="text-2xl font-bold">Instalador inicial</h1>
    <p>Configura la conexión a base de datos, el nombre de la app y crea tu primer admin.</p>
    <?php if ($installError): ?><div class="bg-red-100 text-red-700 p-3 rounded"><?= htmlspecialchars($installError) ?></div><?php endif; ?>
    <form method="post" class="grid md:grid-cols-2 gap-3">
    <input type="hidden" name="install" value="1">
    <input name="app_name" placeholder="Nombre de la aplicación" class="border p-2 rounded" required>
    <input name="whatsapp" placeholder="Whatsapp vendedor (ej 54911...)" class="border p-2 rounded" required>
    <input name="currency" placeholder="Moneda" value="$" class="border p-2 rounded" required>
    <input name="db_host" placeholder="DB Host" value="localhost" class="border p-2 rounded" required>
    <input name="db_port" placeholder="DB Port" value="3306" class="border p-2 rounded" required>
    <input name="db_name" placeholder="DB Name" class="border p-2 rounded" required>
    <input name="db_user" placeholder="DB User" class="border p-2 rounded" required>
    <input type="password" name="db_pass" placeholder="DB Password" class="border p-2 rounded">
    <input name="admin_name" placeholder="Nombre Admin" class="border p-2 rounded" required>
    <input type="email" name="admin_email" placeholder="Email Admin" class="border p-2 rounded" required>
    <input type="password" name="admin_pass" placeholder="Password Admin" class="border p-2 rounded" required>
    <button class="md:col-span-2 bg-blue-600 text-white rounded p-3 font-bold">Instalar y crear config.php</button>
    </form></div></body></html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CompraExpress</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>.tab{display:none}.tab.active{display:block}.col{min-height:220px}</style>
</head>
<body class="bg-slate-50 text-slate-800">
<header class="bg-white shadow sticky top-0 z-40 p-4 flex justify-between items-center">
  <h1 id="app-name" class="text-2xl font-bold">CompraExpress</h1>
  <div class="flex gap-2">
    <button onclick="show('tienda')" class="px-3 py-2 bg-slate-100 rounded">Tienda</button>
    <button onclick="show('generador')" class="px-3 py-2 bg-slate-100 rounded">Generador</button>
    <button onclick="show('pedidos')" class="px-3 py-2 bg-slate-100 rounded">Pedidos</button>
    <button onclick="show('admin')" class="px-3 py-2 bg-slate-100 rounded">Admin</button>
  </div>
</header>

<section id="tienda" class="tab active p-6 max-w-6xl mx-auto">
  <div class="grid md:grid-cols-4 gap-4" id="products-grid"></div>
  <div class="mt-6 bg-white rounded-xl p-4 shadow">
    <h2 class="font-bold text-xl mb-2">Carrito</h2>
    <div id="cart"></div>
    <div class="grid md:grid-cols-3 gap-2 mt-3">
      <input id="c-name" class="border p-2 rounded" placeholder="Nombre">
      <input id="c-phone" class="border p-2 rounded" placeholder="Teléfono">
      <input id="c-address" class="border p-2 rounded" placeholder="Dirección">
    </div>
    <button onclick="buyWhatsapp()" class="mt-3 bg-green-600 text-white px-4 py-2 rounded">Comprar por WhatsApp</button>
  </div>
</section>

<section id="generador" class="tab p-6 max-w-4xl mx-auto">
  <div class="bg-white p-4 rounded-xl shadow space-y-3">
    <h2 class="font-bold text-xl">Generador de contenido (usa productos de la base)</h2>
    <select id="gen-product" class="border p-2 rounded w-full"></select>
    <textarea id="gen-text" class="border p-2 rounded w-full h-44"></textarea>
    <button onclick="generatePost()" class="bg-indigo-600 text-white px-4 py-2 rounded">Generar texto</button>
  </div>
</section>

<section id="pedidos" class="tab p-6">
  <h2 class="text-xl font-bold mb-3">Kanban de pedidos</h2>
  <div class="grid md:grid-cols-4 gap-3" id="orders-board"></div>
</section>

<section id="admin" class="tab p-6">
  <div id="login-box" class="bg-white p-4 rounded-xl shadow max-w-md space-y-2">
    <h2 class="font-bold">Login admin</h2>
    <input id="l-email" type="email" class="border p-2 rounded w-full" placeholder="Email">
    <input id="l-pass" type="password" class="border p-2 rounded w-full" placeholder="Password">
    <button onclick="login()" class="bg-blue-600 text-white px-3 py-2 rounded">Entrar</button>
  </div>
  <div id="admin-box" class="hidden space-y-4">
    <div class="bg-white rounded-xl shadow p-4 grid md:grid-cols-5 gap-2">
      <input id="p-name" class="border p-2 rounded" placeholder="Nombre">
      <input id="p-price" type="number" class="border p-2 rounded" placeholder="Precio">
      <input id="p-image" class="border p-2 rounded" placeholder="URL imagen">
      <input id="p-desc" class="border p-2 rounded" placeholder="Descripción breve">
      <button onclick="saveProduct()" class="bg-emerald-600 text-white rounded">Guardar producto</button>
    </div>
    <h3 class="font-bold">Kanban de productos (arrastrar y soltar para estado / archivar)</h3>
    <div class="grid md:grid-cols-3 gap-3" id="products-board"></div>
  </div>
</section>

<script>
let state={products:[],orders:[],cart:[],isAdmin:false,whatsapp:'',currency:'$'};
const orderStates=[['nuevo','Nuevo'],['preparacion','En preparación'],['viaje','En viaje'],['entregado','Entregado']];
const prodStates=[['publicado','Publicado'],['borrador','Borrador'],['archivado','Archivado']];

function show(id){document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));document.getElementById(id).classList.add('active')}
async function api(action,data={}){const r=await fetch('api.php?action='+action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)});return r.json()}

async function load(){
  const res=await fetch('api.php?action=bootstrap');const d=await res.json();
  state={...state,...d};
  document.getElementById('app-name').textContent=d.appName;
  document.getElementById('login-box').classList.toggle('hidden',d.isAdmin);
  document.getElementById('admin-box').classList.toggle('hidden',!d.isAdmin);
  renderProducts();renderCart();renderOrders();renderProductBoard();renderGenerator();
}

function renderProducts(){
  const grid=document.getElementById('products-grid');grid.innerHTML='';
  state.products.filter(p=>p.status==='publicado').forEach(p=>{
    grid.innerHTML+=`<article class='bg-white p-3 rounded-xl shadow'><img src='${p.image_url||"https://via.placeholder.com/300"}' class='h-36 w-full object-cover rounded'><h3 class='font-semibold mt-2'>${p.name}</h3><p>${state.currency}${Number(p.price).toLocaleString()}</p><button onclick='addCart(${p.id})' class='bg-pink-500 text-white px-3 py-1 rounded mt-2'>Agregar</button></article>`
  })
}
function addCart(id){const e=state.cart.find(i=>i.id===id);if(e)e.qty++; else state.cart.push({id,qty:1});renderCart()}
function renderCart(){
  const el=document.getElementById('cart');
  let total=0;
  el.innerHTML=state.cart.map(i=>{const p=state.products.find(x=>x.id==i.id);if(!p)return '';const st=Number(p.price)*i.qty;total+=st;return `<div>${p.name} x${i.qty} - ${state.currency}${st.toLocaleString()}</div>`}).join('')||'Sin items';
  el.innerHTML+=`<div class='font-bold mt-2'>Total: ${state.currency}${total.toLocaleString()}</div>`;
}
async function buyWhatsapp(){
  const payload={customer_name:cn('c-name'),customer_phone:cn('c-phone'),customer_address:cn('c-address'),items:state.cart};
  const r=await api('create_order',payload); if(!r.ok){alert(r.error||'Error');return}
  state.cart=[];renderCart();window.open(r.whatsappUrl,'_blank');await load();show('pedidos');
}
function cn(id){return document.getElementById(id).value.trim()}

function renderGenerator(){
  const sel=document.getElementById('gen-product');sel.innerHTML='';
  state.products.filter(p=>p.status==='publicado').forEach(p=>sel.innerHTML+=`<option value='${p.id}'>${p.name}</option>`);
}
function generatePost(){
  const id=document.getElementById('gen-product').value;
  const p=state.products.find(x=>x.id==id);if(!p)return;
  document.getElementById('gen-text').value=`🔥 ${p.name}\n\nPrecio especial: ${state.currency}${Number(p.price).toLocaleString()}\n${p.description||'Calidad garantizada para tu familia.'}\n\nPedilo hoy por WhatsApp.`;
}

function makeCol(container,states,type){
  container.innerHTML='';
  states.forEach(([key,label])=>{
    const col=document.createElement('div');col.className='bg-white rounded-xl p-3 shadow col';col.dataset.status=key;
    col.innerHTML=`<h4 class='font-bold mb-2'>${label}</h4><div class='space-y-2 drop min-h-32' data-status='${key}'></div>`;
    container.appendChild(col);
  });
}
function dragBind(){
  document.querySelectorAll('[draggable=true]').forEach(card=>card.ondragstart=e=>e.dataTransfer.setData('text/plain',JSON.stringify({id:card.dataset.id,type:card.dataset.type})));
  document.querySelectorAll('.drop').forEach(zone=>{zone.ondragover=e=>e.preventDefault(); zone.ondrop=async e=>{e.preventDefault(); const d=JSON.parse(e.dataTransfer.getData('text/plain')); const status=zone.dataset.status; if(d.type==='order'){await api('order_status',{id:d.id,status})} else {await api('product_status',{id:d.id,status})} await load();};});
}

function renderOrders(){
  const b=document.getElementById('orders-board');makeCol(b,orderStates,'order');
  state.orders.forEach(o=>{const zone=b.querySelector(`.drop[data-status='${o.status}']`); if(!zone)return; zone.innerHTML += `<div draggable='true' data-id='${o.id}' data-type='order' class='border rounded p-2 bg-slate-50'><div class='font-semibold'>#${o.id} ${o.customer_name}</div><div>${state.currency}${Number(o.total).toLocaleString()}</div></div>`});
  dragBind();
}
function renderProductBoard(){
  const b=document.getElementById('products-board');makeCol(b,prodStates,'product');
  state.products.forEach(p=>{const zone=b.querySelector(`.drop[data-status='${p.status}']`); if(!zone)return; zone.innerHTML += `<div draggable='true' data-id='${p.id}' data-type='product' class='border rounded p-2 bg-slate-50'><div class='font-semibold'>${p.name}</div><div>${state.currency}${Number(p.price).toLocaleString()}</div></div>`});
  dragBind();
}

async function login(){const r=await api('login',{email:cn('l-email'),password:cn('l-pass')});if(!r.ok){alert(r.error||'No login');return}await load()}
async function saveProduct(){
  const r=await api('save_product',{name:cn('p-name'),price:Number(cn('p-price')),image_url:cn('p-image'),description:cn('p-desc')});
  if(!r.ok){alert(r.error||'error');return;}['p-name','p-price','p-image','p-desc'].forEach(i=>document.getElementById(i).value='');await load();
}
load();
</script>
</body>
</html>
