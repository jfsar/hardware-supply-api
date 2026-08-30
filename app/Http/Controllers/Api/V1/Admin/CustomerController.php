<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Customers\AdminListCustomers;
use App\Actions\Customers\AdminShowCustomer;
use App\Actions\Customers\RestoreCustomer;
use App\Actions\Customers\SuspendCustomer;
use App\Actions\Customers\UpdateCustomer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminCustomerIndexRequest;
use App\Http\Requests\Admin\AdminRestoreCustomerRequest;
use App\Http\Requests\Admin\AdminSuspendCustomerRequest;
use App\Http\Requests\Admin\AdminUpdateCustomerRequest;
use App\Http\Resources\Admin\AdminCustomerResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Admin customer administration (Phase 8 Task 1, FR-ADMIN-001…003).
 * Read surfaces lift only status-safe PII; suspend/restore revoke live
 * sessions and tokens and are fully audited.
 */
class CustomerController extends Controller
{
    /**
     * Searchable, status-filtered customer listing (customers.view).
     */
    public function index(
        AdminCustomerIndexRequest $request,
        AdminListCustomers $listCustomers,
    ): JsonResponse {
        $customers = ($listCustomers)($request->validated());

        return response()->json([
            'data' => AdminCustomerResource::collection($customers),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'last_page' => $customers->lastPage(),
            ],
        ]);
    }

    /**
     * Customer profile, saved address, and lifetime order counts (customers.view).
     */
    public function show(User $customer, AdminShowCustomer $showCustomer): JsonResponse
    {
        $result = ($showCustomer)($customer);

        return response()->json([
            'data' => (new AdminCustomerResource($result['user']))->withOrderSummary($result['order_summary']),
        ]);
    }

    /**
     * Edit status-safe profile fields (customers.update).
     */
    public function update(
        AdminUpdateCustomerRequest $request,
        User $customer,
        UpdateCustomer $updateCustomer,
    ): JsonResponse {
        $customer = ($updateCustomer)(
            $customer,
            auth('sanctum')->user(),
            $request->validated(),
        );

        return response()->json([
            'data' => new AdminCustomerResource($customer),
        ]);
    }

    /**
     * Suspend a customer and revoke their access (customers.suspend).
     */
    public function suspend(
        AdminSuspendCustomerRequest $request,
        User $customer,
        SuspendCustomer $suspendCustomer,
    ): JsonResponse {
        $customer = ($suspendCustomer)($customer, auth('sanctum')->user());

        return response()->json([
            'data' => new AdminCustomerResource($customer),
        ]);
    }

    /**
     * Lift a suspension (customers.suspend).
     */
    public function restore(
        AdminRestoreCustomerRequest $request,
        User $customer,
        RestoreCustomer $restoreCustomer,
    ): JsonResponse {
        $customer = ($restoreCustomer)($customer, auth('sanctum')->user());

        return response()->json([
            'data' => new AdminCustomerResource($customer),
        ]);
    }
}
