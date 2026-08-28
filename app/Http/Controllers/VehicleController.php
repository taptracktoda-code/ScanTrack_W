<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(): View
    {
        return view('vehicles.index', [
            'vehicles' => Vehicle::latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'plate_number' => ['required', 'string', 'max:80', 'unique:vehicles,plate_number'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);

        Vehicle::create($validated);

        return redirect()->route('vehicles')->with('status', 'Vehicle added successfully.');
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'plate_number' => ['required', 'string', 'max:80', Rule::unique('vehicles', 'plate_number')->ignore($vehicle->id)],
            'status' => ['required', 'in:Active,Inactive'],
        ]);

        $vehicle->update($validated);

        return redirect()->route('vehicles')->with('status', 'Vehicle updated successfully.');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $vehicle->delete();

        return redirect()->route('vehicles')->with('status', 'Vehicle deleted successfully.');
    }
}
