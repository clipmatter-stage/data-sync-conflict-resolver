<?php

namespace App\Http\Controllers;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ExerciseController extends Controller
{
    public function tierPricing(Request $request)
    {
        $validated = $request->validate([
            'input.quantity' => 'required|integer',
            'input.tiers' => 'required|array',
            'input.tiers.*.min' => 'required|integer',
            'input.tiers.*.price' => 'required|numeric',
        ]);
        $input = $validated['input'];
        $quantity = $input['quantity'];
        $tiers = collect($input['tiers']);
        $tiers = $tiers->sortByDesc('min');
        $validTiers = [];
        foreach ($tiers as $tier) {
            if ($quantity >= $tier['min']) {
                $validTiers[] = $tier;

            }
        }
        if (empty($validTiers)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'Quantity does not meet the minimum requirement for any tier'
            ]);
        }
        $price = $validTiers[0]['price'];
        return response()->json([
            'success' => true,
            'data' => ['price' => $price],
            'error' => null
        ]);
    }

    public function cartValidator(Request $request)
    {
        $validated = $request->validate([
            'input' => 'required|array',
            'input.*.id' => 'required|integer',
            'input.*.required' => 'required|boolean',
            'input.*.done' => 'required|boolean'
        ]);
        $valid = false;
        $input = $validated['input'];
        $invalidItems = [];
        foreach ($input as $item) {
            if ($item['required'] && !$item['done']) {
                $invalidItems[] = $item['id'];
                $valid = false;
            }
        }
        if (empty($invalidItems)) {
            $valid = true;
            return response()->json([
                'success' => false,
                'data' => [
                    'valid' => $valid,
                    'invalid_items' => $invalidItems
                ],
                'error' => 'No invalid  Items Found',
            ]);
        }
        return response()->json([
            'success' => true,
            'data' => [
                'valid' => $valid,
                'invalid_items' => $invalidItems
            ],
            'error' => null,
        ]);
    }

    public function vendorAllocation(Request $request)
    {
        $validated = $request->validateWithBag('vendorAllocation', [
            'input' => 'required|array',
            'input.order_qty' => 'required|integer|min:1',
            'input.vendors' => 'required|array',
            'input.vendors.*.id' => 'required|integer',
            'input.vendors.*.stock' => 'required|integer|min:0',
        ]);
        $input = $validated['input'];
        $orderQty = $input['order_qty'];
        $vendors = $input['vendors'];
        $allocations = [];
        foreach ($vendors as $vendor) {
            if ($orderQty <= 0) {
                break;
            }
            if ($vendor['stock'] <= 0) {
                continue;
            }
            $allocated = min($orderQty, $vendor['stock']);
            $allocations[] = [
                'vendor_id' => $vendor['id'],
                'allocated' => $allocated
            ];
            $orderQty -= $allocated;
        }

        if ($orderQty > 0) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'Not  enough stock to fulfill order'
            ]);
        }
        return response()->json([
            'success' => true,
            'data' => $allocations,
            'error' => null
        ]);
    }

    public function discount(Request $request)
    {
        $validated = $request->validateWithBag('discount', [
            'input' => 'required|array',
            'input.price' => 'required|numeric|min:1',
            'input.discounts' => 'required|array',
            'input.discounts.*.type' => 'required|string|in:percentage,flat',
            'input.discounts.*.value' => 'required|numeric|min:1'
        ]);
        $input = $validated['input'];
        $price = $input['price'];
        $discounts = $input['discounts'];
        $finalPrice = [];
        foreach ($discounts as $discount) {
            if ($discount['type'] === 'percentage') {
                $finalPrice[] = $price - ($price * ($discount['value'] / 100));
                continue;
            } elseif ($discount['type'] === 'flat') {
                $flatDiscount = $price - $discount['value'];
                if ($flatDiscount <= 0) {
                    break;
                } else {
                    $finalPrice[] = $flatDiscount;
                }
            }
        }

        if (empty($finalPrice)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'No valid discounts found'
            ]);
        }

        $lowestPrice = min($finalPrice);
        return response()->json([
            'success' => true,
            'data' => ['final_price' => $lowestPrice],
            'error' => null
        ]);
    }

    public function approvalFlow(Request $request)
    {

        $validated = $request->validate([
            'input' => 'required|array',
            'input.steps' => 'required|array',
            'input.steps.*.id' => 'required|string|regex:/^[A-Z]+$/|in:A,B,C',
            'input.steps.*.depends_on' => 'nullable|string|regex:/^[A-Z]+$/|in:A,B,C'
        ]);
        $input = $validated['input'];
        $lastStepId = null;
        $allValidSteps = [];
        $stepsCount = count($input['steps']);
        foreach ($input['steps'] as $idx => $step) {
            if ($idx === 0 && $step['depends_on'] === null) {
                $lastStepId = $step['id'];
                $allValidSteps[] = $step;
            } elseif ($lastStepId === $step['depends_on'] && $idx > 0 && $step['depends_on'] !== null) {
                $lastStepId = $step['id'];
                $allValidSteps[] = $step;
            }
        }
        if (count($allValidSteps) !== $stepsCount) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'Invalid step sequence'
            ]);
        }
        return response()->json([
            'success' => true,
            'data' => ['valid' => true],
            'error' => null
        ]);
    }
    public function inventory(Request $request)
    {
        $validated = $request->validate([
            "input" => "required|array",
            "input.stock" => "required|integer|min:0",
            "input.requests" => "required|array",
            "input.requests.*" => "numeric"
        ]);

        $stock = $validated['input']['stock'];
        $requests = $validated['input']['requests'];
        $availed = $stock;
        $validRequest = [];

        foreach ($requests as $requestedAmount) {
            if ($requestedAmount <= $availed && $requestedAmount > 0) {
                $availed -= $requestedAmount;
                $validRequest[] = true;
            } else {
                $validRequest[] = false;
            }
        }
        if ((count(array_filter($validRequest, fn($v) => $v === false))) === count($requests)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'No valid requests found'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $validRequest,
            'error' => null
        ]);
    }
    public function shipment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'input' => 'required|array',
            'input.ordered' => 'required|integer|min:1',
            'input.shipped' => 'required|array',
            'input.shipped.*' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $input = $validator->validated()['input'];

        $ordered = $input['ordered'];
        $shipped = $input['shipped'];

        $totalShipped = array_sum($shipped);

        if ($totalShipped > $ordered) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'Total shipped quantity cannot be greater than ordered quantity.'
            ], 422);
        }

        $remaining = $ordered - $totalShipped;

        return response()->json([
            'success' => true,
            'data' => [
                'remaining' => $remaining
            ],
            'error' => null
        ]);
    }

    public function webhook(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'input' => 'required|array',
            "input.*" => "required|array",
            "input.*.id" => "required|string",
            "input.*.time" => "nullable|integer|min:0"
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => $validator->errors()->first()
            ], 422);
        }
        $input = $validator->validated()['input'];
        $unique = collect($input)->sortBy('time')->unique('id')->values()->all();
        return response()->json([
            'success' => true,
            'data' => array_column($unique, 'id'),
            'error' => null
        ]);
    }
    public function quoteExpiry(Request $request)
    {
        $validated = $request->validate([
            "input" => "required|array",
            "input.created_at" => "required|date_format:Y-m-d",
            "input.valid_days" => "required|integer|min:1",
            "input.current_date" => "required|date_format:Y-m-d"
        ]);

        $createdAt = Carbon::parse($validated['input']['created_at']);
        $validDays = $validated['input']['valid_days'];
        $currentDate = Carbon::parse($validated['input']['current_date']);
        $expiryDate = $createdAt->copy()->addDays($validDays);
        $isExpired = $currentDate > $expiryDate;

        return response()->json([
            'success' => true,
            'data' => [
                'valid' => !$isExpired
            ],
            'error' => null
        ]);
    }

    public function productVisibility(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "input" => "required|array",
            "input.customer" => "required|array",
            "input.customer.tags" => "required|array",
            "input.products" => "required|array",
            "input.products.*.id" => "required|integer",
            "input.products.*.allow" => "nullable|array",
            "input.products.*.allow.*" => "required|string",
            "input.products.*.block" => "nullable|array",
            "input.products.*.block.*" => "required|string"
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => $validator->errors()->first()
            ], 422);
        }

        $input = $validator->validated()['input'];
        $customerTags = $input['customer']['tags'];
        $products = $input['products'];

        $allowedProducts = array_filter($products, function ($product) use ($customerTags) {
            if (!empty($product['block']) && !empty(array_intersect($product['block'], $customerTags))) {
                return false;
            }
            if (!empty($product['allow'])) {
                return !empty(array_intersect($product['allow'], $customerTags));
            }
            return true;
        });

        return response()->json([
            'success' => true,
            'data' => ['visible_products' => array_values(array_column($allowedProducts, 'id'))],
            'error' => null
        ]);

    }


    public function bundlePricing(Request $request)
    {
        $validated = $request->validate([
            'input' => 'required|array',
            'input.items' => 'required|array',
            'input.items.*.id' => 'required|integer',
            'input.items.*.price' => 'required|numeric|min:0',
            'input.bundle_price' => 'required|numeric|min:0',
            'input.apply_bundle' => 'required|boolean',
        ]);

        $input = $validated['input'];

        if (empty($input['items'])) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'Items array cannot be empty'
            ]);
        }

        $totalIndividualPrice = array_sum(array_column($input['items'], 'price'));
        $finalPrice = $input['apply_bundle'] ? min($totalIndividualPrice, $input['bundle_price']) : $totalIndividualPrice;

        return response()->json([
            'success' => true,
            'data' => ['final_price' => $finalPrice],
            'error' => null
        ]);
    }

    public function cartMerge(Request $request)
    {
        $validated = $request->validate([
            'input' => 'required|array',
            'input.guest' => 'required|array',
            'input.guest.*.id' => 'required|integer',
            'input.guest.*.qty' => 'required|integer|min:1',
            'input.user' => 'required|array',
            'input.user.*.id' => 'required|integer',
            'input.user.*.qty' => 'required|integer|min:1',
        ]);

        if (empty($validated['input']['guest']) && empty($validated['input']['user'])) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'Both guest and user carts are empty'
            ]);
        }

        $items = array_merge(
            $validated['input']['guest'],
            $validated['input']['user']
        );

        $finalCart = [];

        foreach ($items as $item) {
            $id = $item['id'];

            if (isset($finalCart[$id])) {
                $finalCart[$id]['qty'] += $item['qty'];
            } else {
                $finalCart[$id] = [
                    'id' => $id,
                    'qty' => $item['qty'],
                ];
            }
        }

        $finalCart = array_values($finalCart);

        return response()->json([
            'success' => true,
            'data' => $finalCart,
            'error' => null
        ]);
    }

    public function upsell(Request $request)
    {
        $validated = $request->validate([
            'input' => 'required|array',
            'input.nums' => 'required|array',
            'input.nums.*' => 'required|integer',
            'input.target' => 'required|integer',
        ]);

        $nums = $validated['input']['nums'];
        $target = $validated['input']['target'];
        $numIndices = [];
        foreach ($nums as $index => $num) {
            $complement = $target - $num;
            if (isset($numIndices[$complement])) {
                return response()->json([
                    'success' => true,
                    'data' => [$numIndices[$complement], $index],
                    'error' => null
                ]);
            }
            $numIndices[$num] = $index;
        }
        return response()->json([
            'success' => false,
            'data' => null,
            'error' => 'No solution found'
        ]);
    }

    public function shippingRuleEngine(Request $request)
    {
        $validated = $request->validate([
            'input' => 'required|array',
            'input.order' => 'required|array',
            'input.rules' => 'required|array',
            'input.rules.*.id' => 'required|integer',
            'input.rules.*.method' => 'required|string',
            'input.rules.*.priority' => 'required|integer',
        ]);

        $order = $validated['input']['order'];
        $rules = $validated['input']['rules'];
        $matchingRules = [];

        foreach ($rules as $rule) {
            $match = true;
            Log::info('Checking rule: ' . json_encode($rule));

            foreach ($rule as $key => $value) {

                if (in_array($key, ['id', 'method', 'priority'])) {
                    continue;
                }

                if (str_starts_with($key, 'min_')) {
                    $attribute = substr($key, 4);
                    if (!isset($order[$attribute]) || $order[$attribute] < $value) {
                        $match = false;
                        break;
                    }
                } elseif (str_starts_with($key, 'max_')) {
                    $attribute = substr($key, 4);
                    if (!isset($order[$attribute]) || $order[$attribute] > $value) {
                        $match = false;
                        break;
                    }
                } else {
                    if (!isset($order[$key]) || $order[$key] != $value) {
                        $match = false;
                        break;
                    }
                }
            }

            if ($match) {
                $matchingRules[] = $rule;
            }
        }

        if (empty($matchingRules)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'No matching shipping method found'
            ]);
        }

        usort($matchingRules, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        return response()->json([
            'success' => true,
            'data' => $matchingRules[0]['method'],
            'error' => null
        ]);
    }
    public function fraudCheck(Request $request)
    {
        $validated = $request->validate([
            'input' => 'required|array',
            'input.order' => 'required|array',
            'input.order.amount' => 'required|numeric',
            'input.order.country' => 'required|string',
            'input.order.previous_orders' => 'required|numeric',
            'input.rules' => 'required|array',
            'input.rules.max_amount' => 'required|numeric',
            'input.rules.blocked_countries' => 'required|array'
        ]);

        $input = $validated['input'];
        extract($input);
        $maxAmount = $rules['max_amount'];
        $blockedCountries = $rules['blocked_countries'];

        if ($order['amount'] > $maxAmount) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'Order amount exceeds the maximum allowed'
            ]);
        }

        if (in_array($order['country'], $blockedCountries)) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => 'Orders from this country are blocked'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => 'Order is valid',
            'error' => null
        ]);
    }
    public function shopifyPriceAdjustment(Request $request)
    {
        $validated = $request->validate([
            'input' => 'required|array',
            "input.prices" => 'required|array',
            'input.prices.*.*' => 'required|numeric',
            "input.adjustment_value" => 'required|numeric'
        ]);

        $input = $validated['input'];
        $prices = array_merge(...$input['prices']);
        sort($prices);
        $adjustmentValue = $input['adjustment_value'];
        foreach ($prices as $price) {
            if ($price % $adjustmentValue !== 0) {
                return response()->json([
                    'success' => true,
                    'data' => ["minimum_operations" => -1],
                ]);
            }
        }
        //find Best Target Price
        $middleValue = $prices[intval(count($prices) / 2)];
        $minimumOperations = 0;
        foreach ($prices as $price) {
            $minimumOperations += abs($price - $middleValue) / $adjustmentValue;
        }
        return response()->json([
            'success' => true,
            'data' => ["minimum_operations" => $minimumOperations],
        ]);
    }
    public function dataSync(Request $request)
    {
        $validated = $request->validate([
            'input' => 'required|array',
            'input.shopify' => 'required|array',
            'input.shopify.price' => 'required|numeric',
            'input.shopify.updated_at' => 'required|numeric',
            'input.internal' => 'required|array',
            'input.internal.price' => 'required|numeric',
            'input.internal.updated_at' => 'required|numeric',
        ]);

        $input = $validated['input'];
        $shopify = $input['shopify'];
        $internal = $input['internal'];
        $lastUpdated = max($shopify['updated_at'], $internal['updated_at']);
        $finalPrice = 0;
        if ($lastUpdated === $shopify['updated_at']) {
            $finalPrice = $shopify['price'];
        } elseif ($lastUpdated === $internal['updated_at']) {
            $finalPrice = $internal['price'];
        }
        return response()->json([
            'success' => true,
            'data' => ['price' => $finalPrice],
            'error' => null
        ]);
    }
    public function variantControl(Request $request)
    {
        $validated = $request->validate([
            'input' => 'required|array',
            'input.options' => 'required|array',
            'input.options.*.name' => 'required|string',
            'input.options.*.values' => 'required|numeric',
            'input.limit' => 'required|numeric',
        ]);
        $input = $validated['input'];
        $options = $input['options'];
        $limit = $input['limit'];
        $combinations = array_product(array_column($options, 'values'));
        if ($combinations > $limit) {
            return response()->json([
                'success' => true,
                'data' => [
                    'combinations' => $combinations,
                    'limit_exceeded' => true
                ],
                'error' => 'Number of combinations exceeds the limit'
            ]);
        }
        return response()->json([
            'success' => true,
            'data' => [
                'combinations' => $combinations,
                'limit_exceeded' => false
            ],
            'error' => null
        ]);
    }

    public function orderState(Request $request){
        $validated = $request->validate([
            'input' => 'required|array',
            'input.transitions' => 'required|array',
            'input.transitions.*' => 'required|string|in:created,paid,processing,shipped,delivered',
        ]);

        $transitions = $validated['input']['transitions'];
        $allowedFlow = ['created', 'paid', 'processing', 'shipped', 'delivered'];

        $isValid = true;
        $currentIndex = 0;

        foreach ($transitions as $transition) {
            if ($transition !== $allowedFlow[$currentIndex]) {
                $isValid = false;
                break;
            }
            $currentIndex++;
        }

        return response()->json([
            'success' => true,
            'data' => ['is_valid' => $isValid],
            'error' => null
        ]);
    }
}
