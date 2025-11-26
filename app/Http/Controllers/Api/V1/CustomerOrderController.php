<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TableOrder;
use App\Models\TableMapping;
use App\Models\TableNotification;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerOrderController extends Controller
{
    /**
     * Place a new customer order with seating information
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function placeOrder(Request $request): JsonResponse
    {
        // Validate the incoming request
        $validator = Validator::make($request->all(), [
            'table_number' => 'required|string|max:50',
            'order_number' => 'required|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|integer',
            'items.*.name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
            'notes' => 'nullable|string|max:1000',
            'total_amount' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $tableNumber = strtoupper(trim($request->table_number));
            $orderNumber = trim($request->order_number);
            $items = $request->items;
            $notes = $request->notes;
            $totalAmount = $request->total_amount;

            // Check if order with this order_number already exists
            $existingOrder = TableOrder::where('order_number', $orderNumber)
                ->where('submission_source', 'customer_web_order')
                ->first();

            if ($existingOrder) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'An order with this order number has already been placed. Please refresh and try again.'
                ], 409);
            }

            // Generate unique identifier for this submission
            $uniqueIdentifier = "{$orderNumber}_{$tableNumber}_" . now()->timestamp;

            // Create the order
            $submissionId = "{$orderNumber}_{$tableNumber}_" . now()->timestamp;
            
            $mapping = TableMapping::create([
                'order_number' => $orderNumber,
                'table_number' => $tableNumber,
                'submission_id' => $submissionId,
                'submitted_at' => now(),
                'source' => 'customer_web_order',
                'area' => $this->determineArea($tableNumber),
                'status' => 'active'
            ]);

            $order = TableOrder::create([
                'order_number' => $orderNumber,
                'table_number' => $tableNumber,
                'unique_identifier' => $uniqueIdentifier,
                'mapping_id' => $mapping->id,
                'customer_name' => 'Walk-in Customer',
                'status' => TableOrder::STATUS_PENDING,
                'order_items' => $items,
                'order_notes' => $notes,
                'total_amount' => $totalAmount,
                'submission_source' => 'customer_web_order',
                'area' => $this->determineArea($tableNumber)
            ]);

            DB::commit();

            $this->sendNotificationToExpoAdmins($order, $tableNumber, $items);

            Log::info('Customer web order placed successfully', [
                'order_id' => $order->id,
                'order_number' => $orderNumber,
                'table_number' => $tableNumber,
                'items_count' => count($items),
                'total_amount' => $totalAmount
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully! Your order will be prepared shortly.',
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'table_number' => $order->table_number,
                    'status' => $order->status,
                    'items_count' => count($items),
                    'total_amount' => number_format($order->total_amount, 2, '.', ''),
                    'created_at' => $order->created_at->toISOString()
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to place customer web order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['items']) // Don't log full items array
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to place order. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determine the area based on table number
     * 
     * @param string $tableNumber
     * @return string
     */
    private function determineArea(string $tableNumber): string
    {
        $tableNumber = strtoupper($tableNumber);
        
        // Check for patio tables
        if (str_starts_with($tableNumber, 'P')) {
            return 'patio';
        }
        
        // Check for bar tables
        if (str_starts_with($tableNumber, 'B')) {
            return 'bar';
        }
        
        // Default to dining area
        return 'dining';
    }

    /**
     * Validate table number (optional endpoint for frontend validation)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function validateTableNumber(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'table_number' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'Table number is required'
            ], 422);
        }

        $tableNumber = strtoupper(trim($request->table_number));
        
        // Basic validation - table number should exist in your system
        // You can add more complex validation here based on your table setup
        $isValid = !empty($tableNumber) && strlen($tableNumber) <= 10;

        return response()->json([
            'success' => true,
            'valid' => $isValid,
            'message' => $isValid ? 'Valid table number' : 'Invalid table number'
        ]);
    }

    private function sendNotificationToExpoAdmins($order, $tableNumber, $items)
    {
        try {
            $expoAdmins = Admin::where('role', Admin::ROLE_EXPO)
                ->where('status', Admin::STATUS_ACTIVE)
                ->where('notifications_enabled', true)
                ->get();

            $itemsList = collect($items)->map(function($item) {
                return $item['quantity'] . 'x ' . $item['name'];
            })->take(3)->join(', ');

            if (count($items) > 3) {
                $itemsList .= ' +' . (count($items) - 3) . ' more';
            }

            foreach ($expoAdmins as $admin) {
                TableNotification::create([
                    'type' => TableNotification::TYPE_NEW_ORDER,
                    'title' => 'New Web Order - Table ' . $tableNumber,
                    'message' => "Order #{$order->order_number} - {$itemsList}",
                    'order_number' => $order->order_number,
                    'table_number' => $tableNumber,
                    'customer_name' => 'Walk-in Customer',
                    'location' => $this->determineArea($tableNumber),
                    'priority' => TableNotification::PRIORITY_HIGH,
                    'recipient_type' => TableNotification::RECIPIENT_ADMIN,
                    'recipient_id' => $admin->id,
                    'data' => [
                        'order_id' => $order->id,
                        'items_count' => count($items),
                        'total_amount' => $order->total_amount,
                        'source' => 'customer_web_order'
                    ],
                    'is_read' => false
                ]);
            }

            Log::info('Notifications sent to expo admins', [
                'order_number' => $order->order_number,
                'expo_admins_count' => $expoAdmins->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send notifications to expo admins', [
                'error' => $e->getMessage(),
                'order_number' => $order->order_number
            ]);
        }
    }
}
