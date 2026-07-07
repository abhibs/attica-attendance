<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OutsourceLocation;
use Illuminate\Http\Request;

class OutsourceLocationController extends Controller
{
    public function create()
    {
        return view('admin.outsource.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'location_code' => ['required', 'string', 'max:255', 'unique:outsource_locations,location_code'],
            'name' => ['required', 'string', 'max:255'],
            'addressline' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'url' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        OutsourceLocation::query()->create([
            'location_code' => trim((string) $data['location_code']),
            'name' => trim((string) $data['name']),
            'addressline' => trim((string) ($data['addressline'] ?? '')) ?: null,
            'area' => trim((string) ($data['area'] ?? '')) ?: null,
            'city' => trim((string) ($data['city'] ?? '')) ?: null,
            'state' => trim((string) ($data['state'] ?? '')) ?: null,
            'pincode' => trim((string) ($data['pincode'] ?? '')) ?: null,
            'url' => trim((string) ($data['url'] ?? '')) ?: null,
            'latitude' => (float) $data['latitude'],
            'longitude' => (float) $data['longitude'],
            'status' => 1,
        ]);

        return redirect()->route('admin-outsource-index')->with([
            'message' => 'Outsource location added successfully.',
            'alert-type' => 'success',
        ]);
    }

    public function index()
    {
        $datas = OutsourceLocation::query()
            ->orderBy('location_code')
            ->get();

        return view('admin.outsource.index', compact('datas'));
    }

    public function edit(int $id)
    {
        $data = OutsourceLocation::query()->findOrFail($id);

        return view('admin.outsource.edit', compact('data'));
    }

    public function update(Request $request)
    {
        $id = (int) $request->input('id');
        $location = OutsourceLocation::query()->findOrFail($id);

        $data = $request->validate([
            'location_code' => ['required', 'string', 'max:255', 'unique:outsource_locations,location_code,'.$location->id],
            'name' => ['required', 'string', 'max:255'],
            'addressline' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'url' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        $location->update([
            'location_code' => trim((string) $data['location_code']),
            'name' => trim((string) $data['name']),
            'addressline' => trim((string) ($data['addressline'] ?? '')) ?: null,
            'area' => trim((string) ($data['area'] ?? '')) ?: null,
            'city' => trim((string) ($data['city'] ?? '')) ?: null,
            'state' => trim((string) ($data['state'] ?? '')) ?: null,
            'pincode' => trim((string) ($data['pincode'] ?? '')) ?: null,
            'url' => trim((string) ($data['url'] ?? '')) ?: null,
            'latitude' => (float) $data['latitude'],
            'longitude' => (float) $data['longitude'],
        ]);

        return redirect()->route('admin-outsource-index')->with([
            'message' => 'Outsource location updated successfully.',
            'alert-type' => 'success',
        ]);
    }

    public function inactive(int $id)
    {
        OutsourceLocation::query()->findOrFail($id)->update(['status' => 0]);

        return redirect()->back()->with([
            'message' => 'Outsource location marked inactive.',
            'alert-type' => 'error',
        ]);
    }

    public function active(int $id)
    {
        OutsourceLocation::query()->findOrFail($id)->update(['status' => 1]);

        return redirect()->back()->with([
            'message' => 'Outsource location marked active.',
            'alert-type' => 'success',
        ]);
    }

    public function delete(int $id)
    {
        OutsourceLocation::query()->findOrFail($id)->delete();

        return redirect()->back()->with([
            'message' => 'Outsource location deleted successfully.',
            'alert-type' => 'success',
        ]);
    }
}
