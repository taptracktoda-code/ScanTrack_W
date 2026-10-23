<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\TodaOrganization;
use App\Models\Driver;
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
            'vehicles' => Vehicle::with(['driver', 'organization'])->latest()->get(),
            'organizations' => TodaOrganization::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'plate_number' => ['required', 'string', 'max:80', 'unique:vehicles,plate_number'],
            'vehicle_type' => ['nullable', 'string', 'max:80'],
            'organization_id' => ['nullable', 'integer', 'exists:toda_organizations,id'],
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
            'vehicle_type' => ['nullable', 'string', 'max:80'],
            'organization_id' => ['nullable', 'integer', 'exists:toda_organizations,id'],
            'status' => ['required', 'in:Active,Inactive'],
        ]);

        $oldPlateNumber = $vehicle->plate_number;
        $vehicle->update($validated);
        Driver::query()->where('vehicle_number', $oldPlateNumber)->update([
            'vehicle_number' => $vehicle->plate_number,
            'organization_id' => $vehicle->organization_id,
        ]);

        return redirect()->route('vehicles')->with('status', 'Vehicle updated successfully.');
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        $vehicle->delete();

        return redirect()->route('vehicles')->with('status', 'Vehicle deleted successfully.');
    }
}
