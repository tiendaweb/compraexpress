<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación inicial | CompraExpress</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen py-10 px-4">
    <div class="max-w-3xl mx-auto bg-white rounded-xl shadow-lg p-6 md:p-8">
        <h1 class="text-2xl font-bold text-slate-800">Instalación inicial</h1>
        <p class="text-slate-600 mt-2">Configura la base de datos, nombre de la app y el primer usuario administrador.</p>

        <?php if (!empty($installError)): ?>
            <div class="mt-5 p-4 rounded-md bg-red-100 text-red-800 border border-red-200">
                <?= htmlspecialchars($installError, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= htmlspecialchars($installAction ?? '/install.php', ENT_QUOTES, 'UTF-8') ?>" class="mt-6 space-y-6">
            <section>
                <h2 class="font-semibold text-slate-700 mb-3">Conexión a base de datos</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="flex flex-col gap-1">
                        <span class="text-sm text-slate-600">Host</span>
                        <input required name="db_host" value="<?= htmlspecialchars($old['db_host'] ?? '127.0.0.1', ENT_QUOTES, 'UTF-8') ?>" class="border rounded-md p-2" />
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="text-sm text-slate-600">Puerto</span>
                        <input required type="number" name="db_port" value="<?= htmlspecialchars((string) ($old['db_port'] ?? '3306'), ENT_QUOTES, 'UTF-8') ?>" class="border rounded-md p-2" />
                    </label>
                    <label class="flex flex-col gap-1 md:col-span-2">
                        <span class="text-sm text-slate-600">Nombre de la base de datos</span>
                        <input required name="db_name" value="<?= htmlspecialchars($old['db_name'] ?? 'compraexpress', ENT_QUOTES, 'UTF-8') ?>" class="border rounded-md p-2" />
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="text-sm text-slate-600">Usuario DB</span>
                        <input required name="db_user" value="<?= htmlspecialchars($old['db_user'] ?? 'root', ENT_QUOTES, 'UTF-8') ?>" class="border rounded-md p-2" />
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="text-sm text-slate-600">Password DB</span>
                        <input type="password" name="db_pass" value="<?= htmlspecialchars($old['db_pass'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="border rounded-md p-2" />
                    </label>
                </div>
            </section>

            <section>
                <h2 class="font-semibold text-slate-700 mb-3">Aplicación</h2>
                <label class="flex flex-col gap-1">
                    <span class="text-sm text-slate-600">Nombre de la aplicación</span>
                    <input required name="app_name" value="<?= htmlspecialchars($old['app_name'] ?? 'CompraExpress', ENT_QUOTES, 'UTF-8') ?>" class="border rounded-md p-2" />
                </label>
            </section>

            <section>
                <h2 class="font-semibold text-slate-700 mb-3">Primer administrador</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="flex flex-col gap-1">
                        <span class="text-sm text-slate-600">Nombre</span>
                        <input required name="admin_name" value="<?= htmlspecialchars($old['admin_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="border rounded-md p-2" />
                    </label>
                    <label class="flex flex-col gap-1">
                        <span class="text-sm text-slate-600">Email</span>
                        <input required type="email" name="admin_email" value="<?= htmlspecialchars($old['admin_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="border rounded-md p-2" />
                    </label>
                    <label class="flex flex-col gap-1 md:col-span-2">
                        <span class="text-sm text-slate-600">Password</span>
                        <input required minlength="8" type="password" name="admin_password" class="border rounded-md p-2" />
                    </label>
                </div>
            </section>

            <button type="submit" class="w-full bg-slate-900 text-white py-3 rounded-md font-semibold hover:bg-slate-700 transition">
                Instalar
            </button>
        </form>
    </div>
</body>
</html>
