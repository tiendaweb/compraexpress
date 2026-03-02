<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ProductRepository;
use App\Repositories\SettingRepository;
use App\Repositories\SlideRepository;

class ApiController
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly SlideRepository $slides,
        private readonly SettingRepository $settings,
    ) {
    }

    public function bootstrap(): void
    {
        $this->json([
            'products' => $this->products->all(),
            'slides' => $this->slides->all(),
            'config' => $this->settings->all(),
        ]);
    }

    public function getProducts(): void
    {
        $this->json($this->products->all());
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

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
