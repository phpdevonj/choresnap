<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UserAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserAddressController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $addresses = $user->addresses;
        return response()->json($addresses);
    }

    public function store(Request $request)
    {
        $request->validate([
            'address' => 'required|string',
            'label' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $user = Auth::user();
        $address = new UserAddress($request->all());
        $address->user_id = $user->id;
        $address->save();

        return response()->json($address, 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'address' => 'required|string',
            'label' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $address = UserAddress::findOrFail($id);
        $address->update($request->all());

        return response()->json($address);
    }

    public function destroy($id)
    {
        $address = UserAddress::findOrFail($id);
        $address->delete();
        return response()->json('data deleted successfully', 200);
    }
    
} 