<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\FlyerRepository;
use App\Repositories\ProductRepository;
use App\Repositories\SettingRepository;
use App\Repositories\SlideRepository;

class ApiController
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly SlideRepository $slides,
        private readonly SettingRepository $settings,
        private readonly FlyerRepository $flyers,
    ) {
    }

    public function bootstrap(): void
    {
        $this->json([
            'products' => $this->products->all('active'),
            'slides' => $this->slides->all(),
            'config' => $this->settings->all(),
        ]);
    }

    public function getProducts(): void
    {
        $status = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
        $this->json($this->products->all($status));
    }

    public function createProduct(): void
    {
        $data = json_decode((string) file_get_contents('php://input'), true);

        $name = trim((string) ($data['name'] ?? ''));
        $price = (int) ($data['price'] ?? 0);
        $img = trim((string) ($data['img'] ?? ''));

        if ($name === '' || $price <= 0) {
            $this->json(['error' => 'Nombre y precio son obligatorios.'], 422);
            return;
        }

        if ($img === '') {
            $img = 'https://via.placeholder.com/300x300/f0f0f0/999?text=Sin+Imagen';
        }

        $product = $this->products->create($name, $price, $img);
        $this->json($product, 201);
    }

    public function deleteProduct(int $id): void
    {
        $deleted = $this->products->delete($id);

        if (!$deleted) {
            $this->json(['error' => 'Producto no encontrado.'], 404);
            return;
        }

        $this->json(['ok' => true]);
    }

    public function getSlides(): void
    {
        $this->json($this->slides->all());
    }

    public function getSettings(): void
    {
        $this->json($this->settings->all());
    }

    public function getFlyers(): void
    {
        $this->json($this->flyers->all());
    }

    public function getFlyer(int $id): void
    {
        $flyer = $this->flyers->find($id);
        if ($flyer === null) {
            $this->json(['error' => 'Flyer no encontrado.'], 404);
            return;
        }

        $this->json($flyer);
    }

    public function saveFlyer(): void
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        $id = isset($data['id']) ? (int) $data['id'] : null;
        $title = trim((string) ($data['title'] ?? ''));
        $layout = $data['layout'] ?? null;
        $productId = isset($data['product_id']) && $data['product_id'] !== '' ? (int) $data['product_id'] : null;

        if ($title === '' || !is_array($layout)) {
            $this->json(['error' => 'Título y layout son obligatorios.'], 422);
            return;
        }

        $layoutJson = json_encode($layout, JSON_UNESCAPED_UNICODE);
        if ($layoutJson === false) {
            $this->json(['error' => 'Layout inválido.'], 422);
            return;
        }

        if ($id !== null && $id > 0) {
            $flyer = $this->flyers->update($id, $title, $layoutJson, $productId);
            if ($flyer === null) {
                $this->json(['error' => 'Flyer no encontrado para actualizar.'], 404);
                return;
            }

            $this->json($flyer);
            return;
        }

        $this->json($this->flyers->create($title, $layoutJson, $productId), 201);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
