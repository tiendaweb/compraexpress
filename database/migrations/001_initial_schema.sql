CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','gestion') NOT NULL DEFAULT 'gestion',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    slug VARCHAR(140) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    price INT UNSIGNED NOT NULL,
    img VARCHAR(500) NOT NULL,
    category_id BIGINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS slides (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    image VARCHAR(500) NOT NULL,
    text VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(160) NULL,
    whatsapp_payload TEXT NOT NULL,
    total INT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'nuevo',
    archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    name_snapshot VARCHAR(200) NOT NULL,
    price_snapshot INT UNSIGNED NOT NULL,
    qty INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);


CREATE TABLE IF NOT EXISTS order_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    changed_by VARCHAR(160) NOT NULL,
    previous_status VARCHAR(40) NOT NULL,
    new_status VARCHAR(40) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_status_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS flyers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    template_id VARCHAR(80) NOT NULL DEFAULT 'custom',
    bg_color VARCHAR(20) NOT NULL DEFAULT '#fffaf0',
    layout_json JSON NOT NULL,
    latest_export_path VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_flyers_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS flyer_exports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flyer_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(80) NOT NULL DEFAULT 'image/png',
    file_size INT UNSIGNED NOT NULL,
    exported_by VARCHAR(160) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_flyer_exports_flyer FOREIGN KEY (flyer_id) REFERENCES flyers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS media (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    uploaded_by VARCHAR(160) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO settings (`key`, `value`) VALUES
    ('currency', '$'),
    ('whatsappNumber', '573001234567')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

INSERT INTO slides (image, text, sort_order) VALUES
    ('https://via.placeholder.com/1200x400/e0f7fa/546e7a?text=Todo+para+tu+beb%C3%A9', 'Todo para tu bebé, en un solo lugar', 1),
    ('https://via.placeholder.com/1200x400/f8bbd0/546e7a?text=Ofertas+de+la+Semana', 'Ofertas suaves como el cariño', 2)
ON DUPLICATE KEY UPDATE text = VALUES(text);

INSERT INTO categories (name, slug) VALUES
    ('Pañales', 'panales'),
    ('Higiene', 'higiene'),
    ('Cuidado', 'cuidado')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (name, price, img, category_id) VALUES
    ('Pañales Etapa 2 x40', 29900, 'https://via.placeholder.com/300x300/b3e5fc/546e7a?text=Pa%C3%B1ales', (SELECT id FROM categories WHERE slug = 'panales' LIMIT 1)),
    ('Toallitas Húmedas Aloe x80', 9500, 'https://via.placeholder.com/300x300/f8bbd0/546e7a?text=Toallitas', (SELECT id FROM categories WHERE slug = 'higiene' LIMIT 1)),
    ('Crema Antipañalitis 110g', 3400, 'https://via.placeholder.com/300x300/c8e6c9/546e7a?text=%C3%93leo', (SELECT id FROM categories WHERE slug = 'cuidado' LIMIT 1))
ON DUPLICATE KEY UPDATE price = VALUES(price), img = VALUES(img), category_id = VALUES(category_id);


INSERT INTO users (name, email, password_hash, role) VALUES
    ('Administrador', 'admin@compraexpress.local', '$2y$10$Nrh5Tla4e9TLTHfOqefYJ.gSk3jfbQfVIAtF5NCz7L3A2kZZgwyU6', 'admin'),
    ('Gestión Productos', 'gestion@compraexpress.local', '$2y$10$Nrh5Tla4e9TLTHfOqefYJ.gSk3jfbQfVIAtF5NCz7L3A2kZZgwyU6', 'gestion')
ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role);
