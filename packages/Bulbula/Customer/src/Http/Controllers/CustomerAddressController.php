<?php

declare(strict_types=1);

namespace Bulbula\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Bulbula\Customer\Http\Requests\StoreCustomerAddressRequest;
use Bulbula\Customer\Http\Requests\UpdateCustomerAddressRequest;
use Bulbula\Customer\Http\Resources\CustomerAddressResource;
use Bulbula\Customer\Models\Customer;
use Bulbula\Customer\Models\CustomerAddress;

class CustomerAddressController extends Controller
{
    public function index(Customer $customer, Request $request): Response
    {
        $query = $customer->addresses()->orderBy('created_at', 'desc');

        $addresses = $query->paginate(15)->withQueryString();

        return Inertia::render('Admin/Customer/Addresses/Index', [
            'customer' => $customer->toArray(),
            'addresses' => CustomerAddressResource::collection($addresses),
        ]);
    }

    public function create(Customer $customer): Response
    {
        return Inertia::render('Admin/Customer/Addresses/Create', [
            'customer' => $customer->toArray(),
        ]);
    }

    public function store(StoreCustomerAddressRequest $request, Customer $customer): RedirectResponse
    {
        $data = $request->validated();
        $data['customer_id'] = $customer->id;

        $address = CustomerAddress::create($data);

        if (!empty($data['is_default_shipping']) || !empty($data['is_default_billing'])) {
            $address->setAsDefault();
        }

        return redirect()
            ->route('admin.customers.addresses.index', $customer)
            ->with('success', 'Address added successfully.');
    }

    public function edit(Customer $customer, CustomerAddress $address): Response
    {
        return Inertia::render('Admin/Customer/Addresses/Edit', [
            'customer' => $customer->toArray(),
            'address' => (new CustomerAddressResource($address))->resolve(),
        ]);
    }

    public function update(UpdateCustomerAddressRequest $request, Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $data = $request->validated();

        $address->update($data);

        if (!empty($data['is_default_shipping']) || !empty($data['is_default_billing'])) {
            $address->setAsDefault();
        }

        return redirect()
            ->route('admin.customers.addresses.index', $customer)
            ->with('success', 'Address updated successfully.');
    }

    public function destroy(Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $address->delete();

        return redirect()
            ->route('admin.customers.addresses.index', $customer)
            ->with('success', 'Address deleted successfully.');
    }

    public function setDefaultShipping(Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $address->update(['is_default_shipping' => true]);
        $address->setAsDefault();

        return back()->with('success', 'Default shipping address set.');
    }

    public function setDefaultBilling(Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $address->update(['is_default_billing' => true]);
        $address->setAsDefault();

        return back()->with('success', 'Default billing address set.');
    }
}
