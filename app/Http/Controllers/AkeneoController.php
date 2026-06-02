<?php

namespace App\Http\Controllers;

use App\Services\AkeneoService;
use Illuminate\Http\JsonResponse;

/**
 * Controller for Akeneo PIM API operations
 */
class AkeneoController extends Controller
{
    protected AkeneoService $akeneoService;

    public function __construct(AkeneoService $akeneoService)
    {
        $this->akeneoService = $akeneoService;
    }

    /**
     * Test connection to Akeneo PIM
     *
     * @return JsonResponse
     */
    public function testConnection(): JsonResponse
    {
        $result = $this->akeneoService->testConnection();

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Fetch all products from Akeneo
     *
     * @return JsonResponse
     */
    public function fetchProducts(): JsonResponse
    {
        $params = request()->only(['limit', 'search', 'page']);
        
        $result = $this->akeneoService->getProducts($params);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Fetch a single product from Akeneo
     *
     * @param string $identifier Product identifier (SKU) or UUID
     * @return JsonResponse
     */
    public function fetchProduct(string $identifier): JsonResponse
    {
        $result = $this->akeneoService->getProductByIdentifier($identifier);

        return response()->json($result, $result['success'] ? 200 : 404);
    }
}
