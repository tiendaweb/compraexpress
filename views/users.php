<div id="section-users" class="tab-content container mx-auto px-4 py-10 hidden space-y-8">
    <h2 class="text-3xl font-bold text-center text-baby-text flex items-center justify-center gap-3">
        <i class="fa-solid fa-users-gear text-baby-pink"></i>
        Gestión de Usuarios
    </h2>

    <div class="grid md:grid-cols-3 gap-8">
        <div class="bg-white p-6 rounded-3xl shadow-lg border-2 border-baby-blue-light space-y-4">
            <h3 id="users-form-title" class="text-xl font-bold text-baby-text">Alta de usuario</h3>
            <input type="text" id="user-form-name" placeholder="Nombre" class="w-full p-3 border rounded-full bg-baby-cream focus:ring-2 focus:ring-baby-pink outline-none">
            <input type="email" id="user-form-email" placeholder="Email" class="w-full p-3 border rounded-full bg-baby-cream focus:ring-2 focus:ring-baby-pink outline-none">
            <select id="user-form-role" class="w-full p-3 border rounded-full bg-baby-cream focus:ring-2 focus:ring-baby-pink outline-none">
                <option value="gestion">Gestión</option>
                <option value="admin">Admin</option>
            </select>
            <input type="password" id="user-form-password" placeholder="Contraseña (opcional en edición)" class="w-full p-3 border rounded-full bg-baby-cream focus:ring-2 focus:ring-baby-pink outline-none">
            <div class="flex gap-3">
                <button onclick="submitUserForm()" class="flex-1 bg-baby-pink text-white py-3 rounded-full font-bold hover:bg-pink-400 transition">
                    Guardar usuario
                </button>
                <button id="user-form-cancel" onclick="resetUserForm()" class="hidden px-4 py-3 rounded-full border border-baby-blue-light">
                    Cancelar
                </button>
            </div>
        </div>

        <div class="md:col-span-2 bg-white p-6 rounded-3xl shadow-lg border-2 border-baby-blue-light">
            <h3 class="text-xl font-bold text-baby-text mb-4"><i class="fa-solid fa-list text-baby-blue"></i> Usuarios</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-baby-blue-light">
                            <th class="py-2 pr-3">Nombre</th>
                            <th class="py-2 pr-3">Email</th>
                            <th class="py-2 pr-3">Rol</th>
                            <th class="py-2 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
